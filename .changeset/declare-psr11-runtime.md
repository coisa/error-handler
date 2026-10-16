---
"@coisa/error-handler": patch
---

Declare `psr/container` as a runtime dependency so the existing container and its factories load in production installations. Require `^1.1 || ^2.0`, matching the existing typed identifiers without changing public APIs or the supported PHP range.
