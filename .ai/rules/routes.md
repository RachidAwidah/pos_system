---
paths:
  - routes/api.php
---

# Routes

## Version API routes from v1
All HTTP API contracts live under /api/v1 and route names use the api.v1.* prefix. Keep v1 available when a future breaking v2 is introduced; do not add unversioned duplicate routes. The authenticated /me endpoint is a session bootstrap read and is intentionally not audited, while logout-all revokes every Sanctum token and records logout_all.

## Version API routes from v1
All HTTP API contracts live directly under /v1 (Laravel apiPrefix is intentionally empty) and route names use api.v1.*. Keep v1 available when a future breaking v2 is introduced; do not add /api/v1 or unversioned duplicate routes. Force JSON exception responses for v1/*.

## Direct v1 prefix is authoritative
The direct /v1 contract supersedes the earlier /api/v1 note in this file. Do not restore Laravel's default /api prefix; bootstrap/app.php intentionally sets apiPrefix to an empty string.
