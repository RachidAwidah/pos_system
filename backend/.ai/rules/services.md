---
paths:
  - app/Services/ReportService.php
---

# Services

## Report returns by activity date
Financial reports book sales by orders.order_date and reversals by sales_returns.returned_at. Net sales exclude tax; gross profit is net sales minus COGS, and returned COGS is reversed only when the returned item is restocked.
