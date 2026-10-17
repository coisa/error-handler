---
"@coisa/error-handler": major
---

Prepare the unreleased PHP 8.3+ major while retaining the package
`coisa/error-handler` and namespace `CoiSA\ErrorHandler`.
- Require PHP `^8.3`, with a tested CI matrix of PHP 8.3–8.5.
- Require FastForward Container and expose the Interop service provider as the
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
  use a generic empty body, clean reachable buffers after success and failure,
  and retain renderer-failure precedence over cleanup failures.
- Reconcile the major with delivered 2.x handlers, native release tooling,
  distribution checks and organized PHPUnit Unit/Functional/Process coverage.
  Inherit master's configurable 80% overall coverage floor and real comparisons;
  retire the prototype Fire/HTTP/Python parents and custom release publisher.
- Check all complete major cookbook recipes against executable source, retaining
  the exact inventory and omitting only each leading license header.
- Ship the canonical `resources/skills/coisa-error-handler` package with
  configuration-first examples adapted to the existing major provider and pure
  renderer contracts. Preserve its license and optional installer metadata, and
  require the complete consumer payload in distribution checks.
- Add a direct native regression for output-handler destruction that replaces its
  removed buffer, proving bounded cleanup, primary-failure and caller ownership.
- Preserve the JSON, optional Whoops and explicit reporting adapters published in
  2.5.0. Compose independent response providers with pure renderer aliases after
  the major middleware report; retain trusted debug configuration, lazy page
  overrides and fresh middleware scopes without a second diagnostic delivery.
- Keep the current 2.x manifest version and published changelog unchanged. Native
  callable constructor unions and content negotiation remain separate work; this
  note does not claim their implementation or perform a release.
