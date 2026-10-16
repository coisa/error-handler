# Contributing to the unreleased major

Use PHP 8.3+ and Composer 2. The runtime constraint is `^8.3`; CI currently tests
8.3–8.5. Resolve tools on the actual selected runtime without bypassing platform
requirements or committing the library's local Composer lockfile:

```sh
composer update --no-plugins --no-scripts --no-interaction --prefer-dist
composer validate --strict --no-check-publish
composer check-platform-reqs
composer show --locked
composer --no-plugins check
```

The CoiSA 3.x formatter preset and its existing rules remain active. Review its
diff as executable code; the migration target must remain the Composer minimum.
Do not format vendor files or introduce a newer PHP syntax than the runtime floor.
PHPUnit uses its 12.5/13.x attribute API, maximum PHPStan includes `src` and `tests`,
and Prophecy uses the installed bridge/analysis extension. Unit tests target real
behavior; dangerous native termination belongs in the bounded PHPUnit Process
suite. The [testing guide](docs/testing.md) describes commands and evidence limits.

Source changes need complete English PHPDoc, meaningful regression tests, affected
examples/guides/consumer skill and a pending Changeset. Runtime code stays separate
from Node release tooling. Preserve the application-owned reporter, callback
registration and HTTP emission boundaries. Do not restore removed Container or
ConfigProvider APIs in this major.

Verify examples with `php examples/verify.php`, documentation source parity with
`php tests/Documentation/site.php`, and an installed no-dev archive consumer with
the [production procedure](tests/Production/README.md). Record actual PHP/package
versions and exact local/remote/PR SHAs. A configured matrix or historical sticky
note is not current proof. See [release maintenance](docs/releases.md); merging,
releasing and publishing remain the owner's decisions.
