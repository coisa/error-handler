---
"coisa/error-handler": patch
---

Discard removable output buffers above the stream factory's entry level when rendering or capture throws, preserving the primary failure. Cleanup is best-effort and stops on protected buffers, failed closes, absent progress or callback failure. Successful capture and empty/`0` fallback bytes remain unchanged.
