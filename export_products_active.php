<?php
/**
 * Exports ACTIVE products from a Shopify store to CSV (UTF-8 BOM; ';' delimiter)
 * - IDs kept as TEXT for Excel: ="<id>"
 * - json_decode with JSON_BIGINT_AS_STRING (avoids precision loss on large IDs)
 * - Real-time progress logs to stdout/browser
 *
 * Requirements: PHP 7.4+ with cURL and mbstring
 *
 * Configuration (environment variables):
 *   SHOPIFY_SHOP          - e.g. your-store.myshopify.com (required)
 *   SHOPIFY_ACCESS_TOKEN  - Admin API access token, shpat_... (required)
 *   SHOPIFY_API_VERSION   - e.g. 2024-07 (optional, has a default below)
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);
set_time_limit(0);
mb_internal_encoding('UTF-8');

$shop       = getenv('SHOPIFY_SHOP');
$token      = getenv('SHOPIFY_ACCESS_TOKEN');
$apiVersion = getenv('SHOPIFY_API_VERSION') ?: '2024-07';

if (!$shop || !$token) {
    fwrite(STDERR, "Erro: defina SHOPIFY_SHOP e SHOPIFY_ACCESS_TOKEN.\n");
    http_response_code(500);
    exit(1);
}

$limit      = 250; // max allowed by the API
$baseUrl    = "https://{$shop}/admin/api/{$apiVersion}/products.json";
$outFile    = __DIR__ . '/products_active.csv';

$isCli = (php_sapi_name() === 'cli');

/** ===== Helpers ===== */
function log_msg(string $msg) {
    global $isCli;
    $stamp = date('H:i:s');
    if ($isCli) {
        echo "[$stamp] $msg\n";
    } else {
        echo htmlspecialchars("[$stamp] $msg") . "<br/>";
    }
    @ob_flush();
    @flush();
}

function shopifyGet(string $url, string $token, array $params = [], int $retry = 0): array {
    $query = http_build_query($params);
    $full  = $url . ($query ? ('?' . $query) : '');

    $ch = curl_init($full);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'X-Shopify-Access-Token: ' . $token,
            'Content-Type: application/json',
            'User-Agent: Shopify-Export-Script/1.0'
        ],
        CURLOPT_HEADER => true, // capture headers (pagination/rate limit)
    ]);

    $response = curl_exec($ch);
    if ($response === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new Exception("Erro cURL: $err");
    }

    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headersRaw = substr($response, 0, $headerSize);
    $body       = substr($response, $headerSize);
    $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Simple rate-limit (429) handling
    if ($httpCode == 429 && $retry < 3) {
        log_msg("HTTP 429 (rate limit). Aguardando e tentando novamente...");
        sleep(2 + $retry);
        return shopifyGet($url, $token, $params, $retry + 1);
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception("HTTP $httpCode: $body");
    }

    // Find page_info (rel="next") for pagination
    $nextPageInfo = null;
    foreach (explode("\r\n", $headersRaw) as $hline) {
        if (stripos($hline, 'Link:') === 0) {
            if (preg_match('/<([^>]+)>;\s*rel="next"/i', $hline, $m)) {
                $parts = parse_url($m[1]);
                if (!empty($parts['query'])) {
                    parse_str($parts['query'], $q);
                    if (!empty($q['page_info'])) {
                        $nextPageInfo = $q['page_info'];
                    }
                }
            }
        }
    }

    // Keep big integers as strings
    $json = json_decode($body, true, 512, JSON_BIGINT_AS_STRING);

    return ['json' => $json, 'next_page_info' => $nextPageInfo];
}

/** ===== Run ===== */
try {
    $fp = fopen($outFile, 'w');
    if (!$fp) {
        throw new Exception("Nao foi possivel criar $outFile");
    }

    // BOM for Excel + header with ';'
    fwrite($fp, "\xEF\xBB\xBF");
    fputcsv($fp, ['product_title', 'product_id', 'variant_id', 'vendor'], ';');

    log_msg("Iniciando exportacao de produtos ATIVOS a partir de {$shop}.");
    $params   = [
        'limit'  => $limit,
        'status' => 'active',
        // trims payload and speeds up the response
        'fields' => 'id,title,vendor,variants'
    ];
    $pageInfo = null;
    $totalProdutos  = 0;
    $totalVariantes = 0;

    do {
        $callParams = $params;
        if ($pageInfo) {
            $callParams['page_info'] = $pageInfo;
        }

        log_msg("Requisitando pagina (limit={$limit}" . ($pageInfo ? ", page_info presente" : "") . ")...");
        $res  = shopifyGet($baseUrl, $token, $callParams);
        $data = $res['json'];

        $produtos = $data['products'] ?? [];
        $qtdePag  = count($produtos);
        log_msg("Pagina recebida com {$qtdePag} produto(s).");

        $idx = 0;
        foreach ($produtos as $product) {
            $idx++;
            $totalProdutos++;

            $title  = mb_convert_encoding($product['title'] ?? '', 'UTF-8', 'auto');
            $vendor = mb_convert_encoding($product['vendor'] ?? '', 'UTF-8', 'auto');

            // IDs as TEXT for Excel
            $productIdRaw      = (string)($product['id'] ?? '');
            $productIdForExcel = '="' . $productIdRaw . '"';

            $variants = $product['variants'] ?? [];
            $nVar     = count($variants);

            log_msg("Produto {$totalProdutos} nesta execucao (pg item {$idx}/{$qtdePag}): ID={$productIdRaw} | Variantes={$nVar} | Titulo=\"{$title}\"");

            if ($nVar > 0) {
                foreach ($variants as $variant) {
                    $variantIdRaw      = (string)($variant['id'] ?? '');
                    $variantIdForExcel = '="' . $variantIdRaw . '"';
                    fputcsv($fp, [$title, $productIdForExcel, $variantIdForExcel, $vendor], ';');
                    $totalVariantes++;
                    log_msg("  - Variante escrita: variant_id={$variantIdRaw}");
                }
            } else {
                fputcsv($fp, [$title, $productIdForExcel, '=""', $vendor], ';');
                log_msg("  - Sem variantes: linha escrita com variant_id vazio.");
            }
        }

        $pageInfo = $res['next_page_info'] ?? null;
        if ($pageInfo) {
            log_msg("Ha proxima pagina (page_info detectado). Prosseguindo...");
        }
    } while ($pageInfo);

    fclose($fp);
    $real = realpath($outFile) ?: $outFile;

    log_msg("Resumo: {$totalProdutos} produto(s) processado(s), {$totalVariantes} variante(s) escritas.");
    log_msg("Concluido! CSV gerado em: {$real}");

} catch (Exception $e) {
    log_msg("Erro: " . $e->getMessage());
    if (isset($fp) && is_resource($fp)) {
        fclose($fp);
    }
    http_response_code(500);
    exit(1);
}
