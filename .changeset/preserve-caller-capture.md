---
"@coisa/error-handler": patch
---

Preserve caller-owned output buffers when a capture reporter closes the factory's buffer and returns normally. Use the existing diagnostic fallback without consuming caller bytes or invoking their output callbacks; retain empty-output and `"0"` fallback behavior.
