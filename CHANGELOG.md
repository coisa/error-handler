# Changelog

Notable changes to `coisa/error-handler`, with the newest release first.

This file is inspired by [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
New entries are generated from [Changesets](https://changesets.dev/) using its
GitHub changelog formatter and change categories.

The entries for 1.0.0 and 2.0.0 were reconstructed from their published Git tags.
Pending changes live in `.changeset/` until a version PR is prepared.

## 2.0.6

### Patch Changes

- [#43](https://github.com/coisa/error-handler/pull/43) [`642ee2a`](https://github.com/coisa/error-handler/commit/642ee2a8c51a2d06772b2a0dcb018bdeca554ba4) - Add bounded child-process and loopback HTTP characterization for the existing 2.x behavior, including explicit known limitations and harness timeout/output-cap cleanup checks. Runtime code and public contracts are unchanged.

- [#55](https://github.com/coisa/error-handler/pull/55) [`b4e6ccb`](https://github.com/coisa/error-handler/commit/b4e6ccb1fc665d2341f26b953f8911beeae264df) - Align the internal functional middleware test namespace with its PSR-4 path so Composer's strict optimized autoloader includes it. Test assertions and runtime behavior are unchanged.

- [#47](https://github.com/coisa/error-handler/pull/47) [`bfd7005`](https://github.com/coisa/error-handler/commit/bfd700501e426e8b8278cabec00e88a12e976a84) - Remove the obsolete PHP 7.2/Xdebug 2.6 development Docker wrappers and document the native Composer/PHP checks. The library's PHP requirements, public API and runtime dependencies are unchanged.

- [#51](https://github.com/coisa/error-handler/pull/51) [`3655296`](https://github.com/coisa/error-handler/commit/36552960b7deb3c794e9dd7d16d7fa6963a21c2b) - Remove the unused PHP-CS-Fixer 2 configuration and its distribution exclusion. The tested PHP-CS-Fixer 3 configuration remains the formatter entrypoint.

- [#52](https://github.com/coisa/error-handler/pull/52) [`010dd85`](https://github.com/coisa/error-handler/commit/010dd856957cc4b37e178e27210f236c221fdcab) - Remove the Makefile and its privileged Composer bootstrap. Use the documented native Composer commands for dependency installation and quality checks.

## 2.0.5

### Patch Changes

- [#48](https://github.com/coisa/error-handler/pull/48) [`5213f01`](https://github.com/coisa/error-handler/commit/5213f01150dd7f95e7a17955c966d0e62d1d77d7) - Check the complete development quality suite on PHP 8.3–8.5 and clean production distributions on PHP 7.4 and 8.0–8.5, including lowest runtime dependency resolutions at both endpoints.

## 2.0.4

### Patch Changes

- [#45](https://github.com/coisa/error-handler/pull/45) [`7584519`](https://github.com/coisa/error-handler/commit/758451907ca2bb61d5afa32475b896bd0e91aa00) - Exclude the development-only formatter configuration and Docker ignore file from Composer distribution archives while preserving all runtime source and useful consumer metadata.

- [#44](https://github.com/coisa/error-handler/pull/44) [`7a3e867`](https://github.com/coisa/error-handler/commit/7a3e867782b29dedb5ebd03314d6daca992339df) - Complete legacy service and fixture PHPDoc, correct file headers, and apply the shared coding-style preset while preserving PHP 7.4 support and the public runtime API.

## 2.0.3

### Patch Changes

- [#37](https://github.com/coisa/error-handler/pull/37) [`8846d6f`](https://github.com/coisa/error-handler/commit/8846d6fe75b6b60556f1d0c6a4d8c682c730dc41) - Declare `psr/container` as a runtime dependency so the existing container and its factories load in production installations. Require `^1.1 || ^2.0`, matching the existing typed identifiers without changing public APIs or the supported PHP range.

- [#42](https://github.com/coisa/error-handler/pull/42) [`8a101be`](https://github.com/coisa/error-handler/commit/8a101bed2c9997e5e8eee5589f67d8cda817fbd6) - Fix alias resolution on the declared PHP 7.4 runtime by retaining AliasFactory's unrestricted return contract in PHPDoc instead of a PHP 8 native `mixed` declaration. Preserve exact delegated values, object identity, exceptions and modern PHP behavior.

- [#39](https://github.com/coisa/error-handler/pull/39) [`83bd9a0`](https://github.com/coisa/error-handler/commit/83bd9a04d9ac0eb1fa958938d07b8faa4367f732) - Restore the development-only Prophecy integrations, update PHPUnit 9 configuration, separate ordinary tests from optional coverage reporting, use the fixer's own file header as its template, and limit multiline trailing commas to PHP 7.4-compatible locations.

- [#38](https://github.com/coisa/error-handler/pull/38) [`63986d0`](https://github.com/coisa/error-handler/commit/63986d072fe25a34eac4563dcdbac39475ba4539) - Handle string exception codes such as PDO SQLSTATE values without a secondary TypeError when creating an HTTP response. Preserve the 2.x integer 400–500 mapping, original reason phrase and throwable stream delegation; string codes fall back to status 500.

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
