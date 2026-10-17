# Source and rights

This package belongs to [coisa/error-handler](https://github.com/coisa/error-handler).
Its reusable instructions, references and application examples are original
project material under the bundled MIT license. Preserve the source identity,
namespace and license when copying it. Third-party implementations are installed
by the consumer through Composer; none are copied into this package.

The API contract is the selected unreleased next-major source of
`coisa/error-handler`, with PHP `^8.3` and the existing `CoiSA\ErrorHandler`
namespace. It does not describe the published 2.x line or establish an available
3.x release. Inspect the actual source/autoloader before using its provider,
lifecycle and HTTP behavior. Keep a copied package with that matching source.

JSON, Whoops and the reporting decorator were introduced in published 2.5.0.
This source retains those renderer APIs, but its middleware reports before
rendering and its providers select pure renderers. Use the matching major
composition here rather than copying the 2.x reporting-decorator wiring.
FastForward is required by this source; Whoops remains an optional application
runtime dependency for the selected development HTML route.
