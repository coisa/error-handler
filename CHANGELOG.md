# Changelog

## Unreleased — next major

### Changed

- Require PHP 8.3 or newer and test PHP 8.3–8.5. PHP 7.x and PHP 8.0–8.2 are outside the support contract.
- Exit with status 1 when the registered uncaught-exception handler completes, preserving failure signals in CLI jobs. Direct `handleThrowable()` calls still return normally.
- Reject out-of-order `unregister()` calls without removing another handler. Only the active registration handles shutdown events.
- Return a generic HTTP body when a reporter emits nothing; use standard reason phrases instead of exception messages. Preserve valid error status codes through 599.
- Update PHPUnit, PHPStan, PHP CS Fixer, PHP_CodeSniffer, Docker and CI. Declare PSR-11 as a runtime dependency.
- Require FastForward Container at runtime and reuse its alias/invokable factories. Remove the standalone `ErrorHandlerContainer`, its alias factory and container exceptions, and constructor-only dispatch/HTTP factories; use autowiring for plain injection.
- Keep the Interop service provider as the single container integration entrypoint. Remove the Laminas `ConfigProvider`, discovery metadata and ServiceManager development dependency; move both remaining `Container\Factory` classes to `ServiceProvider\Factory` and migrate their behavior tests.
- Separate throwable output capture from buffer cleanup through private methods in `ThrowableStreamFactory`, preserving renderer-failure precedence and caller-owned buffers.

### Fixed

- Skip an optional callable reporter when an autowiring container advertises it but retrieval reports PSR-11 not-found; continue propagating other construction exceptions.
- Re-registering an instance no longer adds duplicate shutdown callbacks.
- Failing or recursive reporters are not invoked repeatedly during exception/shutdown handling.
- Custom shutdown callbacks still run after a reported uncaught throwable; replacing the active callback continues to suppress the old registration.
- Throwable rendering cleans reachable removable buffers when the renderer throws, stops at non-removable buffers, preserves the original failure if cleanup also throws, preserves the body `0`, and accepts SQLSTATE exception codes without raising a secondary type error.
- Shutdown reporting excludes nonfatal core and compile warnings.

### Added

- An Interop service provider with nine explicit definitions, constructor autowiring, process-handler binding and factory services for independent middleware scopes.
- Complete multiline PHPDoc for changed source, tests and examples, including lifecycle state, failure contracts and typed test data.
- Integration tests using the released FastForward container, an executable provider example, English wiring/lifetime documentation, AGENTS.md and the portable `coisa-error-handler` consumer skill with explicit package provenance.
- Bounded subprocess fire tests, HTTP loopback tests, lifecycle and response regression tests.
- Regression coverage for constructor autowiring, concrete adapter overrides, required renderer interface failures and reporter reset after re-registration; document FastForward's concrete-class fallback boundary.
- English usage, API, migration and testing documentation, plus a static documentation site with manual default-branch Pages deployment.
- Weekly Composer and GitHub Actions update proposals through Dependabot.

The historical 2.0.0 and 1.0.0 releases are available in [GitHub Releases](https://github.com/coisa/error-handler/releases).
