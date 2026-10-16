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
  Preserve the existing regression gate; retire the prototype Fire/HTTP/Python
  parents and custom release publisher instead of duplicating master tooling.

The current 2.x manifest version and published changelog remain unchanged. Native
callable constructor unions and ready JSON/developer-HTML adapters remain separate
work; this note does not claim their implementation or perform a release.
