---
paths:
  - 'app/{Models,Services}/**/*.php,database/{migrations,factories,seeders}/**/*.php'
---

# Models Servicesmigrationsfactoriesseeders

## Cash register sessions and immutable cash movements
A register belongs to a warehouse, and each shift is a cash session for one register with explicit opener and closer. Open/close and cash in/out operations must go through CashSessionService with transactions and row locks. Cash movements require a reason and are append-only; expected cash is derived from opening cash, cash payments, cash in/out, and later cash refunds.
