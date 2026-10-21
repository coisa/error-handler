---
category: changed
type: major
---

Negotiate error responses from the current request through a single
`ErrorHandlerServiceProvider`. Replace the JSON and Whoops profile providers with
lazy Problem Details JSON, plain-text and Symfony HTML representations. Accept a required
`ServerRequestInterface` alongside the original throwable in every response
factory and forward it unchanged after one middleware report.

Keep diagnostic output disabled by default. Explicit debug configuration enables
JSON diagnostics and Symfony's detailed HTML, while production HTML remains a
generic status page. Keep Whoops available only through explicit application
composition, without changing the default based on installed optional packages.
Respect Accept quality and exclusions, preserve existing Vary fields and keep
selection independent across requests that reuse the same cached service.

Serialize Problem Details with Symfony Serializer, preserving public HTTP status
codes and keeping trace arguments and objects out of debug responses. Support
compatible Symfony component majors without requiring a matching VarDumper major.
Exclude older component patches that emit native PHP 8.4+ deprecations, including
incompatible transitive VarDumper versions, without adding a direct dependency.
Offer an explicit Symfony CLI reporter through the same provider, writing to
STDERR or the application's Console error output without installing native hooks.
Demonstrate PSR-14 reporting with FastForward Event Dispatcher and its provider.
