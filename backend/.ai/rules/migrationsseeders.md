---
paths:
  - 'app/{Models,Services,Http/Controllers}/**/*.php,database/{migrations,seeders}/**/*.php'
---

# Migrationsseeders

## Inventory balances and append-only ledger
Products are catalog records and must not store quantity or reorder levels. Warehouse-specific on-hand, reserved quantity, reorder level, and average cost belong to inventory_balances. All stock changes must go through InventoryService inside a database transaction with row locking and must append a stock_movements entry; stock movements are never updated or deleted.
