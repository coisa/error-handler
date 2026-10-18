# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

The entries for 1.0.0 and 2.0.0 were reconstructed from their published Git tags.

## [2.5.2] - 2026-10-17

### Added

- Document security support for the latest stable release and the existing private reporting channel. Include the security policy in Composer distributions and link it from the README and consumer skill. ([#116](https://github.com/coisa/error-handler/pull/116))

### Fixed

- Preserve caller-owned output buffers when a capture reporter closes the factory's buffer and returns normally. Use the existing diagnostic fallback without consuming caller bytes or invoking their output callbacks; retain empty-output and `"0"` fallback behavior. ([#114](https://github.com/coisa/error-handler/pull/114))

## [2.5.1] - 2026-10-17

### Changed

- Document published 2.5.0 as the minimum for JSON, Whoops and Interop provider routes in the guides and bundled consumer skill, while retaining the common 2.4 baseline and existing 2.x behavior. ([#109](https://github.com/coisa/error-handler/pull/109))

## [2.5.0] - 2026-10-17

### Added

- Add an opt-in pure JSON throwable response factory with generic public output, integer HTTP 400–599 status preservation and an explicit development mode with metadata-only traces and no-store caching. Add a separate report-before-render response decorator for the existing 2.x middleware and independent Interop providers for explicit legacy or JSON composition with fresh HTTP scopes. Applications select the JSON provider before the base provider and supply PSR-17 factories and a private reporter; the FastForward integration requires PHP 8.3+ and an application runtime dependency, while the core retains PHP 7.4 and existing container/output-capture defaults. Debug alone needs the optional JSON extension on PHP 7.4. ([#92](https://github.com/coisa/error-handler/pull/92))

- Add an optional Whoops HTML response renderer with application-controlled development activation and page configuration. Provide an independent opt-in Interop profile using the application's PSR-17 factories and private reporter, with a lazy configurable PrettyPageHandler service. Applications compose the base and Whoops maps in one aggregate and obtain fresh middleware scopes through FastForward on PHP 8.3+. Sequential rendering preserves page configuration and replaces diagnostic state, with a fresh Run, response and stream. Reuse explicit reporting composition without changing existing middleware, capture defaults or HTTP negotiation; service resolution and fallback remain the container's policy. Bundle focused HTML guidance and executable consumer programs in the canonical skill, with installed-API availability checks. Require optional Whoops ^2.18.4 or later within 2.x, including the upstream PHP 8.5 fix to avoid its additional PHP deprecation report during rendering. Core PHP 7.4 and ordinary production installations still do not require Whoops or FastForward. ([#96](https://github.com/coisa/error-handler/pull/96))

### Changed

- Clarify the 2.x learning path, integration prerequisites, service aliases and factories, application extension contracts, and reporting/HTTP troubleshooting while retaining the existing Markdown guides and static documentation site. ([#105](https://github.com/coisa/error-handler/pull/105))

## [2.4.4] - 2026-10-17

### Changed

- Make the 2.x README easier to use with a complete quick start, focused integration guides and explicit lifecycle and HTTP boundaries. ([#103](https://github.com/coisa/error-handler/pull/103))

## [2.4.3] - 2026-10-17

### Added

- Ship the portable coisa-error-handler consumer skill in Composer distributions, with a supported 2.4 API contract, complete application examples and optional project synchronization instructions. ([#101](https://github.com/coisa/error-handler/pull/101))

## [2.4.2] - 2026-10-16

### Fixed

- Preserve the original failure when wrapping container exceptions with string codes such as PDO SQLSTATE. Integer codes remain unchanged; string codes use zero on the wrapper and remain available on its previous throwable. ([#93](https://github.com/coisa/error-handler/pull/93))

- Use the application's PSR-17 default reason phrase when an exception message contains forbidden HTTP status-line control bytes. Preserve valid messages, the existing 400–500 status mapping, original throwable reporting and response body behavior. ([#91](https://github.com/coisa/error-handler/pull/91))

## [2.4.1] - 2026-10-16

### Added

- Expose all existing 2.x recipes in a complete source-checked cookbook. Preserve website excerpt validation and verify every full recipe against executable PHP, with application setup, consumer guidance and navigation aligned. ([#86](https://github.com/coisa/error-handler/pull/86))

### Changed

- Permit coverage decreases while requiring an overall line-coverage minimum of 80% by default, configurable with MINIMUM_COVERAGE_THRESHOLD. Preserve authentic baseline comparisons, per-file differences and the existing sticky PR report. ([#87](https://github.com/coisa/error-handler/pull/87))

- Describe the released logging and best-effort adapters as available opt-in 2.x APIs across the guides, cookbook, static site and consumer skill. Retain installed-version checks and the distinction between pending Changesets and published releases. ([#83](https://github.com/coisa/error-handler/pull/83))

### Fixed

- Continue exceptional output-buffer cleanup when PHP removes a buffer before its output callback throws. Preserve the original reporter/capture failure and caller-owned buffers while discarding remaining reachable removable scopes. ([#84](https://github.com/coisa/error-handler/pull/84))

## [2.4.0] - 2026-10-16

### Added

- Add an opt-in BestEffortThrowableHandlerAggregate that attempts every configured reporter in order and then exposes all reporter failures through AggregateReportingException, preserving the original throwable. Existing fail-fast aggregation, defaults, callback lifecycle and HTTP behavior remain unchanged. ([#80](https://github.com/coisa/error-handler/pull/80))

### Removed

- Remove the unused manual Pages upload/deployment jobs now that GitHub Pages publishes the configured branch's docs folder. Keep read-only source, link and excerpt validation and align the publication guidance. ([#81](https://github.com/coisa/error-handler/pull/81))

## [2.3.1] - 2026-10-16

### Changed

- Expand direct PHPUnit class coverage with meaningful contract and failure assertions, remove strictly subsumed legacy tests and scope process isolation to native global state. Document measured coverage and native limits without changing the 2.x runtime or coverage policy. ([#74](https://github.com/coisa/error-handler/pull/74))

- Replace standalone failure and HTTP test runners with PHPUnit contracts and bounded Symfony Process fixtures, declaring the development dependency directly and updating the cookbook verifier and testing entrypoints. ([#77](https://github.com/coisa/error-handler/pull/77))

## [2.3.0] - 2026-10-16

### Added

- Add the explicit CallablePhpErrorHandler adapter for four-argument application callbacks. Preserve the void boundary, ignored callback returns, application-owned mask policy and unchanged typed ErrorHandler constructor. ([#73](https://github.com/coisa/error-handler/pull/73))

## [2.2.0] - 2026-10-16

### Added

- Add the opt-in LogThrowableHandler adapter for direct PSR-3 reporting with the exact throwable message, configured level and original exception context. Preserve logger failures, existing discovery, application-owned transports and PHP 7.4 compatibility; add runnable examples and matching integration guidance. ([#69](https://github.com/coisa/error-handler/pull/69))

## [2.1.0] - 2026-10-16

### Added

- Add the opt-in FilteringThrowableHandler decorator with an explicit boolean predicate, original-object delivery and fail-fast collaborator errors. Keep default discovery, PHP suppression and HTTP response policy unchanged. ([#68](https://github.com/coisa/error-handler/pull/68))

### Fixed

- Attempt to discard removable output buffers above the stream factory's entry level when reporting or capture throws. If cleanup returns, the primary failure propagates; output callbacks that terminate the process or trigger an engine fatal can prevent that return. Cleanup stops on protected buffers, failed closes, absent progress or callback failure. Successful capture and empty/`0` fallback bytes remain unchanged. ([#58](https://github.com/coisa/error-handler/pull/58))

## [2.0.9] - 2026-10-16

### Added

- Add a static 2.x documentation guide with checked example excerpts and a manual Pages workflow. ([#59](https://github.com/coisa/error-handler/pull/59))

### Removed

- Retire the historical Scrutinizer configuration and badges after verifying that its latest public inspection predates the 2.x line. Link the current PHP CI and document its security-analysis limits without changing runtime code or external service settings. ([#63](https://github.com/coisa/error-handler/pull/63))

### Fixed

- Exclude the intentionally invalid compile-fatal fixture from optimized development classmaps while preserving its explicit bounded fire-test execution. ([#64](https://github.com/coisa/error-handler/pull/64))

## [2.0.8] - 2026-10-16

### Added

- Add PHPUnit line-coverage artifacts, overall baseline regression checks and a configurable minimum, with a SHA-aware sticky note for eligible same-repository pull requests. Verify that throwable listeners preserve event identity when callbacks return a replacement. ([#53](https://github.com/coisa/error-handler/pull/53))

### Changed

- Add regression coverage for aggregate reporter order, appended handlers and preservation of the original throwable instance. ([#60](https://github.com/coisa/error-handler/pull/60))

## [2.0.7] - 2026-10-16

### Added

- Document the existing 2.x lifecycle, reporting, container and HTTP APIs with PHP 7.4-compatible runnable examples, bounded example verification and matching portable consumer guidance. Keep application-owned dependencies and observed output/exit limitations explicit. ([#56](https://github.com/coisa/error-handler/pull/56))

## [2.0.6] - 2026-10-16

### Added

- Add bounded child-process and loopback HTTP characterization for the existing 2.x behavior, including explicit known limitations and harness timeout/output-cap cleanup checks. Runtime code and public contracts are unchanged. ([#43](https://github.com/coisa/error-handler/pull/43))

### Removed

- Remove the obsolete PHP 7.2/Xdebug 2.6 development Docker wrappers and document the native Composer/PHP checks. The library's PHP requirements, public API and runtime dependencies are unchanged. ([#47](https://github.com/coisa/error-handler/pull/47))

- Remove the unused PHP-CS-Fixer 2 configuration and its distribution exclusion. The tested PHP-CS-Fixer 3 configuration remains the formatter entrypoint. ([#51](https://github.com/coisa/error-handler/pull/51))

- Remove the Makefile and its privileged Composer bootstrap. Use the documented native Composer commands for dependency installation and quality checks. ([#52](https://github.com/coisa/error-handler/pull/52))

### Fixed

- Align the internal functional middleware test namespace with its PSR-4 path so Composer's strict optimized autoloader includes it. Test assertions and runtime behavior are unchanged. ([#55](https://github.com/coisa/error-handler/pull/55))

## [2.0.5] - 2026-10-16

### Added

- Check the complete development quality suite on PHP 8.3–8.5 and clean production distributions on PHP 7.4 and 8.0–8.5, including lowest runtime dependency resolutions at both endpoints. ([#48](https://github.com/coisa/error-handler/pull/48))

## [2.0.4] - 2026-10-16

### Changed

- Complete legacy service and fixture PHPDoc, correct file headers, and apply the shared coding-style preset while preserving PHP 7.4 support and the public runtime API. ([#44](https://github.com/coisa/error-handler/pull/44))

### Fixed

- Exclude the development-only formatter configuration and Docker ignore file from Composer distribution archives while preserving all runtime source and useful consumer metadata. ([#45](https://github.com/coisa/error-handler/pull/45))

## [2.0.3] - 2026-10-16

### Fixed

- Declare `psr/container` as a runtime dependency so the existing container and its factories load in production installations. Require `^1.1 || ^2.0`, matching the existing typed identifiers without changing public APIs or the supported PHP range. ([#37](https://github.com/coisa/error-handler/pull/37))

- Fix alias resolution on the declared PHP 7.4 runtime by retaining AliasFactory's unrestricted return contract in PHPDoc instead of a PHP 8 native `mixed` declaration. Preserve exact delegated values, object identity, exceptions and modern PHP behavior. ([#42](https://github.com/coisa/error-handler/pull/42))

- Restore the development-only Prophecy integrations, update PHPUnit 9 configuration, separate ordinary tests from optional coverage reporting, use the fixer's own file header as its template, and limit multiline trailing commas to PHP 7.4-compatible locations. ([#39](https://github.com/coisa/error-handler/pull/39))

- Handle string exception codes such as PDO SQLSTATE values without a secondary TypeError when creating an HTTP response. Preserve the 2.x integer 400–500 mapping, original reason phrase and throwable stream delegation; string codes fall back to status 500. ([#38](https://github.com/coisa/error-handler/pull/38))

## [2.0.2] - 2026-10-16

### Added

- Add weekly Composer, npm and GitHub Actions dependency checks with automatic, reviewable Changesets for Dependabot pull requests. ([#34](https://github.com/coisa/error-handler/pull/34))

### Changed

- Expand the development-only PHP_CodeSniffer requirement from `^3.13` to `^3.13 || ^4.0`. ([#36](https://github.com/coisa/error-handler/pull/36))

- Update development tooling to CoiSA PHP CS Fixer 3 and PHPUnit 9.6, which provides the shared diff dependency required by the current formatter. Check changed PHP files in pull requests through the formatter's reusable workflow. Preserve the library's existing runtime requirements and public API. ([#28](https://github.com/coisa/error-handler/pull/28))

- Omit automatic author acknowledgements from generated release notes while retaining delivery references. ([#31](https://github.com/coisa/error-handler/pull/31))

### Removed

- Stop tracking the library's Composer lockfile and ignore contributor-local locks. Document fresh dependency resolution and application-owned lockfiles while retaining the Node tooling lockfile and the existing PHP runtime requirements and public API. ([#35](https://github.com/coisa/error-handler/pull/35))

### Security

- Update the development-only PHP_CodeSniffer dependency to the patched 3.x series to address CVE-2026-67434, without changing the library's runtime requirements. ([#32](https://github.com/coisa/error-handler/pull/32))

## [2.0.1] - 2026-10-15

### Added

- Add development-only Changesets tooling, GitHub-linked changelogs, reviewed version PRs and GitHub releases after those PRs are merged, preserving the PHP runtime requirements and public API of the 2.x line. ([#27](https://github.com/coisa/error-handler/pull/27))

## [2.0.0] - 2024-12-27

### Added

- PSR-14 integration for dispatching throwables and error events, with listener providers, callable listeners and a PSR-3 logging listener. ([#1](https://github.com/coisa/error-handler/pull/1))

- PSR-15 error-handling middleware and throwable response/stream factories built on PSR-17 HTTP factories. ([#1](https://github.com/coisa/error-handler/pull/1))

- A PSR-11 error-handler container, service factories and a `ConfigProvider` with Laminas discovery metadata. ([#1](https://github.com/coisa/error-handler/pull/1))

- Callable throwable and shutdown adapters, a throwable-handler aggregate, and dedicated PHP error and last-error shutdown handlers. ([#1](https://github.com/coisa/error-handler/pull/1))

### Changed

- Require PHP `^7.4 || ^8.0` instead of `^7.1`.

- Replace the callable-based `ErrorHandler` constructor with a `ThrowableHandlerInterface` dependency. Construction no longer installs PHP callbacks or starts output buffering; applications call `register()` and `unregister()` explicitly. ([#1](https://github.com/coisa/error-handler/pull/1))

- Separate throwable reporting, PHP error handling and shutdown handling into `ThrowableHandlerInterface`, `PhpErrorHandlerInterface` and `ShutdownHandlerInterface`, with configurable error and shutdown handlers. ([#1](https://github.com/coisa/error-handler/pull/1))

- Change the declared package license from Apache 2.0 to MIT. ([#1](https://github.com/coisa/error-handler/pull/1))

### Removed

- Replace the invokable `HandlerInterface` and `CollectionHandler` API, and remove the output-specific handlers (`ErrorLogHandler`, `HtmlHandler`, `JsonHandler`, `VarDumpHandler`, `VarExportHandler`), `DebugHandler`, `DebugHelper` and `ExceptionHelper`. ([#1](https://github.com/coisa/error-handler/pull/1))

## [1.0.0] - 2019-09-23

This version is marked as a prerelease on GitHub.

### Added

- PHP errors, uncaught exceptions and shutdown handling under the `CoiSA\ErrorHandler` namespace, registered during `ErrorHandler` construction.

- Callable throwable handling and an invokable handler collection.

- Built-in `error_log`, HTML, JSON, `var_dump` and `var_export` output handlers, with exception and debug output helpers.

- A Composer library requiring PHP `^7.1`, with Apache 2.0 licensing.

[2.5.2]: https://github.com/coisa/error-handler/compare/v2.5.1...v2.5.2
[2.5.1]: https://github.com/coisa/error-handler/compare/v2.5.0...v2.5.1
[2.5.0]: https://github.com/coisa/error-handler/compare/v2.4.4...v2.5.0
[2.4.4]: https://github.com/coisa/error-handler/compare/v2.4.3...v2.4.4
[2.4.3]: https://github.com/coisa/error-handler/compare/v2.4.2...v2.4.3
[2.4.2]: https://github.com/coisa/error-handler/compare/v2.4.1...v2.4.2
[2.4.1]: https://github.com/coisa/error-handler/compare/v2.4.0...v2.4.1
[2.4.0]: https://github.com/coisa/error-handler/compare/v2.3.1...v2.4.0
[2.3.1]: https://github.com/coisa/error-handler/compare/v2.3.0...v2.3.1
[2.3.0]: https://github.com/coisa/error-handler/compare/v2.2.0...v2.3.0
[2.2.0]: https://github.com/coisa/error-handler/compare/v2.1.0...v2.2.0
[2.1.0]: https://github.com/coisa/error-handler/compare/v2.0.9...v2.1.0
[2.0.9]: https://github.com/coisa/error-handler/compare/v2.0.8...v2.0.9
[2.0.8]: https://github.com/coisa/error-handler/compare/v2.0.7...v2.0.8
[2.0.7]: https://github.com/coisa/error-handler/compare/v2.0.6...v2.0.7
[2.0.6]: https://github.com/coisa/error-handler/compare/v2.0.5...v2.0.6
[2.0.5]: https://github.com/coisa/error-handler/compare/v2.0.4...v2.0.5
[2.0.4]: https://github.com/coisa/error-handler/compare/v2.0.3...v2.0.4
[2.0.3]: https://github.com/coisa/error-handler/compare/v2.0.2...v2.0.3
[2.0.2]: https://github.com/coisa/error-handler/compare/v2.0.1...v2.0.2
[2.0.1]: https://github.com/coisa/error-handler/compare/2.0.0...v2.0.1
[2.0.0]: https://github.com/coisa/error-handler/compare/1.0.0...2.0.0
[1.0.0]: https://github.com/coisa/error-handler/releases/tag/1.0.0
