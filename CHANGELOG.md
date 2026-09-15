# Changelog

## Unreleased — next major

### Changed

- Require PHP 8.3 or newer and test PHP 8.3–8.5. PHP 7.x and PHP 8.0–8.2 are outside the support contract.
- Exit with status 1 when the registered uncaught-exception handler completes, preserving failure signals in CLI jobs. Direct `handleThrowable()` calls still return normally.
- Reject out-of-order `unregister()` calls without removing another handler. Only the active registration handles shutdown events.
- Return a generic HTTP body when a reporter emits nothing; use standard reason phrases instead of exception messages. Preserve valid error status codes through 599.
- Update PHPUnit, PHPStan, PHP CS Fixer, PHP_CodeSniffer, Docker and CI. Declare PSR-11 as a runtime dependency.

### Fixed

- Re-registering an instance no longer adds duplicate shutdown callbacks.
- Failing or recursive reporters are not invoked repeatedly during exception/shutdown handling.
- Throwable rendering cleans its output buffers even when the renderer throws, preserves the body `0`, and accepts SQLSTATE exception codes without raising a secondary type error.
- Shutdown reporting excludes nonfatal core and compile warnings.

### Added

- Bounded subprocess fire tests, HTTP loopback tests, lifecycle and response regression tests.
- English usage, API, migration and testing documentation, plus a static documentation site with manual default-branch Pages deployment.

The historical 2.0.0 and 1.0.0 releases are available in [GitHub Releases](https://github.com/coisa/error-handler/releases).
