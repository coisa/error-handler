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

The CoiSA 3.x formatter preset remains the base. A documented local
`single_line_empty_body: false` exception and conservative anonymous-class brace
option keep empty bodies multiline so the complete PSR12 sniff still checks
namespace braces. PER3 sections 4/4.4 make concise
bodies a SHOULD recommendation; no mandatory rule or PHPCS diagnostic is removed.
The former PHP7.4 trailing-comma restriction is removed: the PHP8.3 floor supports
the preset's parameter and match contexts. Review the formatter's
diff as executable code; the migration target must remain the Composer minimum.
Do not format vendor files or introduce a newer PHP syntax than the runtime floor.
PHPUnit uses its 12.5/13.x attribute API, maximum PHPStan includes `src` and `tests`,
and Prophecy uses the installed bridge/analysis extension. Unit tests target real
behavior; dangerous native termination belongs in the bounded PHPUnit Process
suite. The [testing guide](docs/testing.md) describes commands and evidence limits.

## Coverage reporting

The inherited PHP CI policy permits overall decreases while requiring
`MINIMUM_COVERAGE_THRESHOLD`, defaulting to **80% overall** when unset. Authentic
baseline comparisons, file differences/colors and sticky reporting remain in
place. The minimum applies to instrumentable source lines globally, not to each
class or file; 100% line coverage does not prove every branch or behavior.
Execution, invalid-report and artifact failures remain independent failures.
See [coverage and current evidence](docs/testing.md#coverage-and-current-evidence).

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
