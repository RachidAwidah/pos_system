---
paths:
  - 'app/**'
---

# App

## No repository layer
Use Eloquent directly in controllers for simple CRUD and dedicated services for cross-cutting or multi-step domain behavior. Do not introduce a Repository Pattern.

## Security defaults for authentication and audited writes
Use the centralized Password::defaults policy (minimum 10 characters with mixed case, numbers, and symbols). Both web and API login routes must use the named `login` limiter keyed by email and IP. All state-changing domain operations must call AuditLogService with old/new values; deletion audits remain synchronous. Sanctum bearer tokens expire after 720 minutes and expired tokens are pruned daily.

## Security defaults for authentication and audited writes
Use the centralized Password::defaults policy (minimum 8 characters with mixed case, numbers, and symbols). Both web and API login routes must use the named `login` limiter keyed by email and IP. All state-changing domain operations must call AuditLogService with old/new values; deletion audits remain synchronous. Sanctum bearer tokens expire after 720 minutes and expired tokens are pruned daily.

## Audit mutations and explicit detail views
Use AuditLogService for every successful create, update, and delete with old/new values; updates record only changed fields and sensitive values must be redacted. Audit only explicit show/detail reads with action view and viewed_id in new_values—never audit index/list queries or the Eloquent retrieved event. Persist audit records synchronously with business writes.

## Security defaults for authentication and audited writes
Use the centralized Password::defaults policy with a minimum of 8 characters plus mixed case, numbers, and symbols. Apply the named login limiter to web and API login routes, audit state-changing domain operations through AuditLogService, and keep Sanctum bearer tokens limited to 720 minutes.
