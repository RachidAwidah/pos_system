---
paths:
  - 'app/{Models,Services,Http/Controllers}/**/*.php,database/migrations/**/*.php'
---

# Controllers Migrations

## Sales checkout and returns are transactional services
Create completed sales only through CheckoutService and completed returns only through SalesReturnService. Prices, taxes, discounts, payments, credit, loyalty, audit logs, and inventory must be committed in one transaction with row locks; controllers must not mutate these tables directly.
