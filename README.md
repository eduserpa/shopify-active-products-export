# Exportador de Produtos Ativos do Shopify

Script PHP de arquivo único que exporta todo produto **ativo** (e cada
variante) de uma loja Shopify para um CSV pronto pra Excel: título do
produto, id do produto, id da variante, fornecedor — uma linha por
variante.

## Por que existe

O admin do Shopify não exporta ids de produto/variante em massa de uma
forma fácil de colar numa planilha pra cruzar com outros sistemas (ex.:
cruzar SKUs/variant IDs com um catálogo de curso, ERP, ou tabela de
preço). Este script pagina a Admin REST API, trata rate limiting (HTTP
429) com espera/nova tentativa, e escreve os ids como texto seguro pro
Excel (`="123456"`) pra ids numéricos grandes não serem estragados pela
formatação automática do Excel.

## Requisitos

- PHP 7.4+ com cURL e mbstring
- Um token de acesso da Admin API do Shopify com escopo `read_products`

## Configuração

```bash
export SHOPIFY_SHOP="sua-loja.myshopify.com"
export SHOPIFY_ACCESS_TOKEN="shpat_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
export SHOPIFY_API_VERSION="2024-07"   # opcional, padrão 2024-07
```

## Uso

```bash
php export_products_active.php
```

ou coloque numa hospedagem PHP e abra no navegador — de qualquer forma
ele transmite logs de progresso conforme roda e escreve
`products_active.csv` ao lado do script.

## Licença

MIT
