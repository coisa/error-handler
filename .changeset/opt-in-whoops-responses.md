---
"@coisa/error-handler": minor
---

Add an optional Whoops HTML response renderer with application-controlled development activation and fresh page configuration. Provide an opt-in Interop profile using the application's PSR-17 factories and private reporter, with a configurable lazy page callback and fresh middleware scopes through FastForward on PHP 8.3+. Reuse explicit reporting composition without changing existing middleware, capture defaults or HTTP negotiation. Require optional Whoops ^2.18.4 or later within 2.x, including the upstream PHP 8.5 fix to avoid its additional PHP deprecation report during rendering. Core PHP 7.4 and ordinary production installations still do not require Whoops or FastForward.
