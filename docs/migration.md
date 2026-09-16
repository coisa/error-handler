# Migrating from 2.0

This is the migration contract for the unreleased next major. It does not describe an already published Packagist release.

FastForward applications can now adopt the [Interop service provider](fast-forward.md)
without changing the existing package or namespace. Merge definitions with
`AggregateServiceProvider`, library first and application last, so a required
reporter's failure cannot fall back to the silent default. Registration remains explicit;
use factory services for fresh/nested scopes because container lookups are cached.
Existing Laminas and standalone PSR-11 integration remain supported.

1. Upgrade the application to PHP 8.3, 8.4 or 8.5. Resolve Composer dependencies again. The checked-in development lock targets PHP 8.5; developers on 8.3/8.4 use `composer update --no-plugins --no-scripts` to resolve compatible tools. Consumers resolve their own lock as usual.
2. Expect exit status **1** after a globally uncaught `Throwable` is reported successfully. Version 2 could exit with status 0. A reporter that explicitly terminates the process still controls its own exit code. Direct `handleThrowable()` calls do not exit.
3. Pair `register()` and `unregister()` in reverse order. A repeated `register()` on the same instance is idempotent, not reference counted. Out-of-order unregistration now throws `LogicException` instead of silently removing someone else's callback. Use separate instances for nested middleware and do not pre-register the middleware's own instance.
4. A reporter must not call its own `ErrorHandler` recursively. After a reporter throws, that instance stops retrying it until it is unregistered and registered again. The failure propagates to the caller or PHP; it is not silently swallowed. Only the active registration receives shutdown notifications, preventing duplicates across nested handlers.
5. Review custom HTTP rendering. Empty output now produces `Internal Server Error`, instead of a stringified exception and stack trace. The string `0` is preserved. Exception messages no longer become HTTP reason phrases. Codes 400–599 map to their matching status; other integer codes and string SQLSTATE codes map to 500.
6. If you used the old PHP 7.2 Docker setup, replace the two-service invocation with `docker compose run --rm --build tests`. It defaults to PHP 8.3 and resolves dependencies in the image, without using your host `vendor/`.

Public handler and factory interfaces retain their names and method signatures. The PHP floor, CLI exit semantics, lifecycle rejection and HTTP output defaults warrant a major release even though most integration code can remain unchanged.

Before upgrading a real application, run the [documented checks](testing.md) and application-level tests for the actual reporter, middleware stack and PHP SAPI. These isolated fixtures do not establish reliability of third-party loggers, remote services or production infrastructure.
