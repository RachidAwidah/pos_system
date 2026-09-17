---
paths:
  - 'app/{Models,Services}/**/*.php,database/migrations/**/*.php'
---

# Models Services Migrations

## Purchase orders do not change stock
PurchaseOrderService owns draft/send/cancel transitions. Inventory increases only through GoodsReceiptService, which supports partial receipts, updates received quantities, records supplier ledger debt, and links stock movements to the exact goods receipt.

## Account and loyalty histories are append-only
Customer/supplier balances and customer loyalty points are cached totals backed by append-only ledger/transaction rows. Change them only through CustomerAccountService, SupplierAccountService, or LoyaltyService so balance before/after and audit logs remain consistent.
