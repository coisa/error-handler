# Changelog

Notable changes to `coisa/error-handler`, with the newest release first.

This file is inspired by [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
New entries are generated from [Changesets](https://changesets.dev/) using its
GitHub changelog formatter and change categories.

The entries for 1.0.0 and 2.0.0 were reconstructed from their published Git tags.
Pending changes live in `.changeset/` until a version PR is prepared.

## 2.0.0

[Release](https://github.com/coisa/error-handler/releases/tag/2.0.0) ·
[Changes since 1.0.0](https://github.com/coisa/error-handler/compare/1.0.0...2.0.0)

### Major Changes

- Require PHP `^7.4 || ^8.0` instead of `^7.1`.
- Replace the callable-based `ErrorHandler` constructor with a
  `ThrowableHandlerInterface` dependency. Construction no longer installs PHP
  callbacks or starts output buffering; applications call `register()` and
  `unregister()` explicitly.
- Separate throwable reporting, PHP error handling and shutdown handling into
  `ThrowableHandlerInterface`, `PhpErrorHandlerInterface` and
  `ShutdownHandlerInterface`, with configurable error and shutdown handlers.
- Replace the invokable `HandlerInterface` and `CollectionHandler` API, and
  remove the output-specific handlers (`ErrorLogHandler`, `HtmlHandler`,
  `JsonHandler`, `VarDumpHandler`, `VarExportHandler`), `DebugHandler`,
  `DebugHelper` and `ExceptionHelper`.
- Change the declared package license from Apache 2.0 to MIT.

### Added

- PSR-14 integration for dispatching throwables and error events, with listener
  providers, callable listeners and a PSR-3 logging listener.
- PSR-15 error-handling middleware and throwable response/stream factories
  built on PSR-17 HTTP factories.
- A PSR-11 error-handler container, service factories and a `ConfigProvider`
  with Laminas discovery metadata.
- Callable throwable and shutdown adapters, a throwable-handler aggregate,
  and dedicated PHP error and last-error shutdown handlers.

## 1.0.0

[Initial release](https://github.com/coisa/error-handler/releases/tag/1.0.0)
(marked as a prerelease on GitHub).

### Added

- PHP errors, uncaught exceptions and shutdown handling under the
  `CoiSA\ErrorHandler` namespace, registered during `ErrorHandler` construction.
- Callable throwable handling and an invokable handler collection.
- Built-in `error_log`, HTML, JSON, `var_dump` and `var_export` output handlers,
  with exception and debug output helpers.
- A Composer library requiring PHP `^7.1`, with Apache 2.0 licensing.
