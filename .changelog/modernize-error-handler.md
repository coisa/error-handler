---
category: changed
type: major
---

Modernize the library for PHP 8.3+ while retaining the package
`coisa/error-handler` and namespace `CoiSA\ErrorHandler`.
- Require PHP `^8.3`, with a tested CI matrix of PHP 8.3–8.5.
- Require FastForward Container `^1.10` and expose the Interop service provider as the
  container entrypoint. Remove the standalone Container/ConfigProvider APIs,
  their package-specific exceptions and constructor-only factories. Keep explicit
  lifecycle/composition factories under `ServiceProvider\Factory`.
- Keep construction and discovery inert. Preserve cached services and provide
  explicit fresh handler/middleware factories. Require reverse-order unregistration,
  retain one shutdown callback per handler and notify only the active owner.
- Exit 1 after successful registered uncaught-exception reporting. Preserve direct
  calls without termination; propagate reporter failures without retry during a
  failed registration while allowing independent direct deliveries.
- Separate HTTP reporting from rendering. Middleware requires both contracts,
  reports once and then invokes a pure response factory. Use a generic stream by
  default, standard reason phrases and integer HTTP error statuses through 599.
- Keep explicit public output capture as an opt-in renderer adapter. Preserve `0`,
  use a generic fallback for empty output, clean reachable buffers after success and failure,
  and retain renderer-failure precedence over cleanup failures. If no capture
  remains above the caller's entry depth, use the generic fallback without reading caller output.
- Retain delivered 2.x handlers and native release tooling. Add distribution checks
  and organized PHPUnit Unit/Functional/Process coverage. Cover partial callback
  ownership, fail-fast reporter identity and native fatal diagnostic metadata.
  Preserve the configurable 80% overall coverage floor and real comparisons;
  retire the prototype Fire/HTTP/Python parents and custom release publisher.
- Check all complete cookbook recipes against executable source, retaining
  the exact inventory and omitting only each leading license header.
- Ship the canonical `resources/skills/coisa-error-handler` package with
  configuration-first examples for the Interop provider and pure
  renderer contracts. Preserve its license and optional installer metadata, and
  require the complete consumer payload in distribution checks.
- Add a direct native regression for output-handler destruction that replaces its
  removed buffer, proving bounded cleanup, primary-failure and caller ownership.
- Preserve the JSON, optional Whoops and explicit reporting adapters published in
  2.5.0. Compose independent response providers with pure renderer aliases after
  the middleware report; retain trusted debug configuration, lazy page
  overrides and fresh middleware scopes without a second diagnostic delivery.
- Accept matching callables in all three ErrorHandler constructor inputs using
  existing adapters. Preserve interface identity and precedence, required reporter,
  optional null defaults, inert construction and the existing reporting policies.
  Keep explicit provider factories for cached services and fresh scopes.
- Refresh Composer development-tool ranges to the current stable lines, retaining
  PHP 8.3 support through PHPUnit 12.5 and Symfony Process 7.4 alternatives.
  Require Nyholm 1.8.2 in development for its PHP 8.5 fixes; retain the compatible
  PSR ranges and optional Whoops 2.18.7 minimum with its upstream native STDIN source-read fix.
- Describe the accompanying API without temporary PR or publication status,
  and clarify that diagnostic reporter output remains outside the PSR-7 body.
- Correct callable-filtering guidance, clarify generic stream and fatal-reporting
  contracts, and distinguish checkout recipes from bundled application examples.
  Keep installed documentation links usable and release instructions current.
- Keep content negotiation under application control.
