---
"@coisa/error-handler": patch
---

Use the application's PSR-17 default reason phrase when an exception message contains forbidden HTTP status-line control bytes. Preserve valid messages, the existing 400–500 status mapping, original throwable reporting and response body behavior.
