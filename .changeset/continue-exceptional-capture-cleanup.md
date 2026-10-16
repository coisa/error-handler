---
"@coisa/error-handler": patch
---

Continue exceptional output-buffer cleanup when PHP removes a buffer before its
output callback throws. Preserve the original reporter/capture failure and
caller-owned buffers while discarding remaining reachable removable scopes.
