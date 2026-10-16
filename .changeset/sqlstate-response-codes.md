---
"@coisa/error-handler": patch
---

Handle string exception codes such as PDO SQLSTATE values without a secondary TypeError when creating an HTTP response. Preserve the 2.x integer 400–500 mapping, original reason phrase and throwable stream delegation; string codes fall back to status 500.
