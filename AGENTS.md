# Error Handler maintenance

## Purpose

Maintain a small PHP error-handling library with explicit reporting, process
lifecycle and PSR-15 response boundaries. The current Composer package is
`coisa/error-handler`, under the `CoiSA\ErrorHandler` namespace.

## Ownership

The repository owns its core handlers, PSR adapters, interoperability provider,
tests, documentation and consumer skill. Applications own reporters, PSR-14 and
PSR-17 implementations, HTTP emission and the moment PHP callbacks are installed.

## Local Contracts

- Write code, public documentation, examples and agent guidance in English.
- Identify the public consumer skill as `coisa-error-handler`, matching its
  Composer package, and retain the source repository link for copied installs.
- Declare PHP 8.3+ support (`^8.3`); the tested CI matrix is PHP 8.3–8.5.
  Document the API and behavior of the accompanying code in its current state.
  Do not add temporary PR, branch or unreleased-status disclaimers that require
  an edit after merge/release. Keep migration and release history versioned where relevant.
- Keep core handlers independent of FastForward. The service provider uses
  `Interop\Container\ServiceProviderInterface` and FastForward's reusable factory
  classes. FastForward Container is a required runtime dependency and owns
  service resolution, caching and autowiring; do not add a parallel container.
- Constructing or discovering a service must not call `register()`. Native PHP
  callbacks are process-wide. Unregister in reverse order and use distinct
  handlers for nested scopes. Follow [the lifecycle contract](docs/api.md).
- FastForward caches services. Keep the middleware handler separate from the
  process handler and use the provider's factory services for fresh scopes.
  Prefer `container($application, ErrorHandlerServiceProvider::class)` for normal
  dependency and optional-reporter composition. For required overrides, merge
  application definitions after library definitions in one
  `AggregateServiceProvider`: separate providers prioritize the first and may
  silently fall back on PSR errors.
- Support container integration through the Interop service provider only;
  do not restore the removed ConfigProvider or framework-specific adapters.
  Keep all provider factories under `ServiceProvider\Factory`. Preserve explicit
  factories for reporter composition, optional handlers and fresh middleware
  scopes; use autowiring for plain constructor injection. A possible
  move to php-fast-forward does not itself change package identity or namespace.
- HTTP middleware requires lifecycle and throwable-reporting contracts on the same
  scoped handler. Report once, then pass the original Throwable and current
  ServerRequestInterface to a pure response renderer. The single provider selects
  request-aware negotiation: JSON Problem Details or Symfony HTML when accepted, generic fallback
  otherwise. Trusted debug enables details without forcing a representation.
  Whoops is optional and only selected by an explicit application override;
  its installation never changes defaults. Keep output capture
  an explicit public-renderer adapter; never report diagnostics again during rendering.
- CLI rendering is explicit through SymfonyCliThrowableHandler or the provider's cli flag;
  do not infer it from SAPI or add a second Console exception renderer.
- Explicit Log/Filtering/CallablePHP/BestEffort adapters remain opt-in; the default
  reporter aggregate stays fail-fast and adds no implicit discovery for them.
- Reporters own diagnostic output; HTTP fallback bodies must not leak exception
  details. A shutdown callback reports a fatal error and does not recover it.

## Work Guidance

Inspect the current branch and relevant interfaces before changes. Update
source, meaningful regression tests, examples, affected guides and an independent
`.changelog/*.md` fragment together. Keep runtime code independent of release tooling. Keep
the skill aligned with tested public APIs, without copying the full manual or
adding host-specific dependencies. The Fast Forward Changelog workflow prepares
reviewed version PRs; do not consolidate history or consume pending fragments in
ordinary contributions. Composer manages development skill copies through
`skills.json`; keep generated `.agents/` instructions ignored and never install
skills globally as part of repository maintenance.

Run fatal-error and process-termination experiments only through bounded child
fixtures. Use the PHPUnit Process suite with the existing bounded fixtures and Symfony Process. Never
use application credentials, production logs or a real web server as fixtures.

Publishing Pages, merging, releasing or transferring the repository requires
the owner's corresponding instruction. Preparing a PR does not perform them.

## Verification

Use Composer 2 with `--no-plugins --no-scripts` for dependency installation;
then run `composer --no-plugins check`. This runs formatting, PHPCS, PHPUnit Unit/Functional/Process suites and maximum
PHPStan over source and tests. Run the existing bounded example verifier and static
site validator after documentation/example changes. See [testing](docs/testing.md)
for PHP-version resolution, application dependencies and actual platform evidence.
Run `php examples/fast-forward.php` after provider changes. Verify a production
installation without development dependencies still loads the core and provider.
Check documentation links and inspect the actual CI result for the tested SHA.

## Bundled consumer skill

Read [the consumer skill](resources/skills/coisa-error-handler/SKILL.md) when
changing application integration examples, consumer guides or the skill. Keep
its source-version contract, references and executable examples aligned with
the accompanying public API. The canonical package is under
`resources/skills`; `.agents/skills` is a consumer discovery destination, not a
second source copy. This source link needs no installed copy or host alias.
Repository maintenance and verification continue to follow this AGENTS.md.

## Child DOX Index

- [SECURITY.md](SECURITY.md): latest-stable security support and private vulnerability reporting; ordinary bugs remain public issues.
- [README.md](README.md): installation and entrypoints for consumers.
- [docs/api.md](docs/api.md): core lifecycle, reporters and PSR composition.
- [docs/fast-forward.md](docs/fast-forward.md): provider definitions, overrides,
  cached services and fresh middleware scopes.
- [docs/examples.md](docs/examples.md): all nineteen complete recipes, checked
  byte-for-byte against executable source with only the leading license header omitted;
  preserve setup boundaries and matching website excerpts.
- [docs/releases.md](docs/releases.md): independent fragments, reviewed version PRs,
  immutable release tags and Composer delivery boundaries.
- [docs/testing.md](docs/testing.md): PHPUnit/process tests, commands and evidence limits.
- [CONTRIBUTING.md](CONTRIBUTING.md): dependency resolution and quality entrypoints.
- [examples/README.md](examples/README.md): executable recipe dependencies and verifier.
- [docs/cli.md](docs/cli.md): explicit Symfony terminal reporting and one Console exception owner.
- [docs/http.md](docs/http.md): pure JSON/Whoops rendering, explicit capture,
  reporting and diagnostic-output limits.
- [docs/migration.md](docs/migration.md): breaking changes from published 2.x.
- [docs/agent-skill.md](docs/agent-skill.md): optional project/global skill
  distribution, trust and lifecycle; copying does not establish host activation.
- [resources/skills/coisa-error-handler/SKILL.md](resources/skills/coisa-error-handler/SKILL.md):
  portable consumer integration procedure, bundled examples and source-version
  contract; its package procedure stays in its own entrypoint.

- [skills.json](skills.json): Composer-managed Changelog, Container and Config skill
  sources; generated local instructions are ignored and do not replace the bundled consumer skill.

No subtree needs a separate maintenance contract; the skill's procedure stays
in its SKILL.md entrypoint.
