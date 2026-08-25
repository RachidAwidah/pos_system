---
paths:
  - 'app/{Models,Http/Controllers}/**/*.php,database/{migrations,factories,seeders}/**/*.php'
---

# Migrationsfactoriesseeders

## Customers and suppliers are separate domains
Do not reintroduce a shared contacts table or Contact model. Sales orders belong to customers through customer_id, purchase orders belong to suppliers through supplier_id, and payments derive their customer through order_id rather than duplicating a customer foreign key. Customer credit/loyalty data and supplier payable data remain in their respective tables.
