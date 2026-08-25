---
paths:
  - 'database/migrations/**'
---

# Migrations

## Use UUIDs for domain tables
Use UUID primary and foreign keys for application domain tables. Framework infrastructure tables such as jobs and migrations may retain Laravel's native key types.

## Categories use an adjacency-list hierarchy
categories.parent_id is a nullable UUID foreign key to categories.id with restricted deletion so parent removal cannot silently delete or promote an existing subtree.
