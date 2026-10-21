# Source and rights

This package belongs to [coisa/error-handler](https://github.com/coisa/error-handler).
Its reusable instructions, references and application examples are original
project material under the bundled MIT license. Preserve the source identity,
namespace and license when copying it. Third-party implementations are installed
by the consumer through Composer; none are copied into this package.

The API baseline is `coisa/error-handler` 3.x, with PHP `^8.3` and the
`CoiSA\ErrorHandler` namespace. Match the installed source and autoloader before
using its provider, lifecycle and HTTP behavior. Keep a copied skill with the
package version whose API it describes.

JSON, Whoops and the reporting decorator were introduced in published 2.5.0.
Those renderer APIs are retained; middleware reports before
rendering and its providers select pure renderers. Use the matching major
composition here rather than copying the 2.x reporting-decorator wiring.
FastForward is required by this source; Whoops remains an optional application
runtime dependency for the selected development HTML route.
