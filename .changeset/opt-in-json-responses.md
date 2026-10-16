---
"@coisa/error-handler": minor
---

Add an opt-in pure JSON throwable response factory with generic public output, integer HTTP 400–599 status preservation and an explicit development mode with metadata-only traces and no-store caching. Add a separate report-before-render response decorator for the existing 2.x middleware and optional Interop service providers for legacy or JSON composition with fresh HTTP scopes. Applications supply PSR-17 factories and a private reporter; the FastForward integration requires PHP 8.3+ and an application runtime dependency, while the core retains PHP 7.4 and existing container/output-capture defaults. Debug alone needs the optional JSON extension on PHP 7.4.
