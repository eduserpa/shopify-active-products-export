# Shopify Active Products Export

Single-file PHP script that exports every **active** product (and every
variant) from a Shopify store to a CSV file, ready for Excel: product
title, product id, variant id, vendor — one row per variant.

## Why

The Shopify admin doesn't export product/variant IDs in bulk in a form
that's easy to paste into a spreadsheet for matching against other
systems (e.g. matching SKUs/variant IDs against a course catalog, ERP,
or price sheet). This script paginates the Admin REST API, handles
rate limiting (HTTP 429) with backoff/retry, and writes IDs as Excel-safe
text (`="123456"`) so large numeric IDs don't get mangled by Excel's
auto-formatting.

## Requirements

- PHP 7.4+ with cURL and mbstring
- A Shopify Admin API access token with `read_products` scope

## Setup

```bash
export SHOPIFY_SHOP="your-store.myshopify.com"
export SHOPIFY_ACCESS_TOKEN="shpat_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
export SHOPIFY_API_VERSION="2024-07"   # optional, defaults to 2024-07
```

## Usage

```bash
php export_products_active.php
```

or drop it on a PHP host and open it in a browser — either way it
streams progress logs as it goes and writes `products_active.csv` next
to the script.

## License

MIT
