# Error Handler 2.x maintenance

Maintain `coisa/error-handler` under `CoiSA\ErrorHandler` as a small PHP library. Code, documentation, examples and agent guidance are English.

## Compatibility and ownership

- Preserve the declared PHP `^7.4 || ^8.0` range and existing 2.x public classes, interfaces, factories, ConfigProvider and Laminas discovery metadata. Keep executable example syntax compatible with PHP 7.4.
- Use the actual selected source and Composer version as authority. The proposed major has different container, middleware, rendering and exit contracts; do not present that proposal or a pending release note as a published release.
- Applications own reporters, PSR-14 dispatchers, PSR-17 factories, HTTP emission and when native PHP callbacks are installed. Resolving a service must not install callbacks.
- Preserve the existing container integration. FastForward adoption, removal of legacy classes and API changes require their own explicitly reviewed scope.
- Callbacks are process-wide. Use distinct handlers for nested scopes and restore in reverse order. Direct reporting, registered uncaught exceptions and fatal shutdown reporting are different paths; do not promise recovery or an automatic nonzero exit.
- The existing HTTP stream adapter captures reporter output and falls back to exception text for empty or false-like output. Keep those limitations visible; never describe it as a safe default or pure renderer.
- Treat runtime fixes, formatting, dependency policy, CI, documentation and publication as separate deliveries. Do not widen one to hide another failure.

## Work and verification

Inspect branch/HEAD, tracked/untracked/ignored state, applicable contracts and relevant interfaces before changes. Preserve unrelated work and local ignored Composer state. Do not add a library lockfile.

Use Composer 2 dependency installs with `--no-plugins --no-scripts`, then run `composer --no-plugins check`. Development integrations may need a newer PHP than the library minimum: verify minimum runtime separately through a clean no-dev consumer. Report actual PHP/Composer/dependency versions, the tested SHA and existing failures honestly.

Keep source, meaningful regression coverage, affected examples/guides, the source-linked `coisa-error-handler` consumer skill and an independent `.changelog/*.md` fragment aligned. Never install the skill globally as part of maintenance. The Fast Forward Changelog workflow prepares the reviewed version PR; do not consolidate history or consume pending notes in ordinary contributions.

Run `php examples/verify.php` with the documented application dependencies after example or documented-contract changes. The verifier owns bounded child processes, including fatal experiments. Do not run fatal fixtures in an agent, application, real server or parent test process. Use synthetic records and disposable consumer dependencies; never application credentials or production logs.

Check local links, quoted-example parity and the actual CI result for the exact candidate SHA. Runtime `src/` and Composer requirements must remain unchanged in a documentation-only delivery.

Merging, releasing, tagging, transferring the repository, publishing Pages or deploying requires the owner's corresponding instruction. Preparing a PR or passing CI does not perform those actions.

## Bundled consumer skill

Read [the consumer skill](resources/skills/coisa-error-handler/SKILL.md) when
changing application integration examples, consumer guides or the skill. Keep
its supported version contract, references and executable examples aligned with
the public API. The canonical published package is under `resources/skills`;
`.agents/skills` is a consumer discovery destination, not a second source copy.
This source link needs no installed copy or host alias. Repository maintenance
and verification continue to follow this AGENTS.md.

## Child DOX Index

- [SECURITY.md](SECURITY.md): latest-stable security support and private vulnerability reporting; ordinary bugs remain public issues.
- [README.md](README.md): consumer installation, quick start and guide navigation; distinguishes API family from pending release state.
- [docs/api.md](docs/api.md): reporters, core lifecycle and process behavior; no recovery or exit guarantees.
- [docs/container.md](docs/container.md): legacy container, ConfigProvider, factories and cached scopes; applications supply external implementations.
- [docs/http.md](docs/http.md): existing PSR-15/capture behavior and safe application composition; the library never emits responses.
- [docs/examples.md](docs/examples.md): complete current-2.x recipes, checked against executable source; excludes proposed major APIs and retains existing setup/verification boundaries.
- [docs/index.html](docs/index.html): static 2.x reading surface; source excerpts track executable examples and Pages publishes the owner-configured branch's `/docs` folder.
- [examples/README.md](examples/README.md): runnable recipes for every built-in adapter with explicit application dependencies.
- [examples/verify.md](examples/verify.md): bounded verifier inputs, outputs, child-process effects and recovery.
- [docs/testing.md](docs/testing.md): direct PHPUnit contracts, isolated native process cases, installed-package checks and honest coverage limits; no custom test runner.
- [CONTRIBUTING.md](CONTRIBUTING.md): library dependency resolution and clean production-container checks.
- [docs/releases.md](docs/releases.md): independent fragments, reviewed version PRs, Git-tag version authority and publication boundaries.
- [docs/agent-skill.md](docs/agent-skill.md): optional project/global skill distribution, trust and lifecycle; copying does not establish host activation.
- [resources/skills/coisa-error-handler/SKILL.md](resources/skills/coisa-error-handler/SKILL.md): portable consumer integration procedure, bundled examples and supported API contract; keeps its own package entrypoint and source identity.
- [skills.json](skills.json): Composer-managed development skill sources; installation creates ignored local agent instructions without changing the library's bundled consumer skill.

No subtree needs an additional AGENTS.md. Guides and the skill retain their own procedures without duplicating repository authority.
