---
paths:
  - 'app/Models/Product.php,database/migrations/*products*,app/Http/Requests/*Product*'
---

# Requests

## Unified sellable catalog
Keep physical goods, non-stock items, and services in products using ProductType (stock, non_stock, service). SKU is required/internal and unique; barcode is separate, nullable, and unique. Every product belongs to a Unit; only stock products track inventory.
