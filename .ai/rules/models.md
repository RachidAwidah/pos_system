---
paths:
  - 'app/Models/**'
---

# Models

## Use UUIDs for domain models
Use HasUuids with string, non-incrementing primary keys for all application domain models.

## Use Laravel model class attributes
Declare mass-assignable columns with Laravel 13 #[Fillable([...])] above the model class. When a model needs an explicit non-conventional table name, declare it with #[Table('...')] above the class instead of protected $table. Do not move unrelated casts or hidden configuration unless requested.

## Use Laravel model class attributes
Declare mass-assignable fields with #[Fillable] and declare each model's table and UUID key configuration with #[Table(name: ..., key: 'id', keyType: 'string', incrementing: false)]. Set timestamps: false in #[Table] only when the migration has no created_at and updated_at columns.

## Categories use an adjacency-list hierarchy
Category is self-referencing through nullable parent_id. Keep parent() and children() relationships explicit, and prevent cyclic assignments in category write validation before adding category CRUD.
