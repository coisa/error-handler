---
"@coisa/error-handler": patch
---

Preserve the original failure when wrapping container exceptions with string codes such as PDO SQLSTATE. Integer codes remain unchanged; string codes use zero on the wrapper and remain available on its previous throwable.
