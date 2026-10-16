# Changelog

Notable changes to `coisa/error-handler`, with the newest release first.

This file is inspired by [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
New entries are generated from [Changesets](https://changesets.dev/) using its
GitHub changelog formatter and change categories.

The entries for 1.0.0 and 2.0.0 were reconstructed from their published Git tags.
Pending changes live in `.changeset/` until a version PR is prepared.

## 2.0.2

### Patch Changes

- [#36](https://github.com/coisa/error-handler/pull/36) [`2468eec`](https://github.com/coisa/error-handler/commit/2468eeca15df2b5a93c2e9fd3f38f9e242da35c0) - chore(deps-dev): Update squizlabs/php_codesniffer requirement from ^3.13 to ^3.13 || ^4.0

- [#34](https://github.com/coisa/error-handler/pull/34) [`bc9dd4e`](https://github.com/coisa/error-handler/commit/bc9dd4e06314f0969925bb02fa4b673fa5068eb3) - Add weekly Composer, npm and GitHub Actions dependency checks with automatic, reviewable Changesets for Dependabot pull requests.

- [#35](https://github.com/coisa/error-handler/pull/35) [`65baacb`](https://github.com/coisa/error-handler/commit/65baacb0a5d98d77b72c743180d5d6cca46be834) - Stop tracking the library's Composer lockfile and ignore contributor-local locks. Document fresh dependency resolution and application-owned lockfiles while retaining the Node tooling lockfile and the existing PHP runtime requirements and public API.

- [#32](https://github.com/coisa/error-handler/pull/32) [`ea28301`](https://github.com/coisa/error-handler/commit/ea2830100d60b58a2821c4ba598ca62743f701fa) - Update the development-only PHP_CodeSniffer dependency to the patched 3.x series to address CVE-2026-67434, without changing the library's runtime requirements.

- [#28](https://github.com/coisa/error-handler/pull/28) [`2e91d7f`](https://github.com/coisa/error-handler/commit/2e91d7fa88ebf659aa75ef70f66411539eed116e) - Update development tooling to CoiSA PHP CS Fixer 3 and PHPUnit 9.6, which provides the shared diff dependency required by the current formatter. Check changed PHP files in pull requests through the formatter's reusable workflow. Preserve the library's existing runtime requirements and public API.

## 2.0.1

### Patch Changes

- [#27](https://github.com/coisa/error-handler/pull/27) [`d8973f4`](https://github.com/coisa/error-handler/commit/d8973f4b8c2a861490bf24ce99db103dd6e3ff29) - Add development-only Changesets tooling, GitHub-linked changelogs, reviewed version PRs and GitHub releases after those PRs are merged, preserving the PHP runtime requirements and public API of the 2.x line.

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
