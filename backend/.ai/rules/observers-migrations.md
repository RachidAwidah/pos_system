---
paths:
  - 'app/Models/Category.php,app/Observers/CategoryObserver.php,database/migrations/*categories*'
---

# Observers Migrations

## Category adjacency tree
Categories use the parent_id self-reference (adjacency-list pattern). Preserve restrict-on-delete and enforce acyclic trees through CategoryObserver so every Eloquent write path rejects self-parenting and descendant-parent cycles.
