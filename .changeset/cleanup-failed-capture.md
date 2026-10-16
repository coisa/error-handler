---
"@coisa/error-handler": patch
---

Attempt to discard removable output buffers above the stream factory's entry level when reporting or capture throws. If cleanup returns, the primary failure propagates; output callbacks that terminate the process or trigger an engine fatal can prevent that return. Cleanup stops on protected buffers, failed closes, absent progress or callback failure. Successful capture and empty/`0` fallback bytes remain unchanged.
