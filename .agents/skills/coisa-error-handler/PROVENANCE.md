# Source and maintenance

This portable consumer procedure belongs to [coisa/error-handler](https://github.com/coisa/error-handler).
Its instructions and example are original guidance under the bundled MIT license.
Retain the package name, namespace, license and source link when copying it.

The existing 2.x API was checked at repository commit
`774f1ab1971c9d939fae37a7d53a2cac6484c34c`. The next-major consumer procedure was
reviewed for source identity and scope/verification decisions. Its required FastForward
wiring, removed legacy APIs, intersection middleware, pure default renderer and
exit1 claims were rejected for this 2.x procedure.

The additive Interop/JSON provider guidance follows the separately approved 2.x
composition in PR #92, based on `775f6cb87d97d74922524e057b156bc4c718be62` and the
current source changes. It keeps FastForward optional for library consumers,
uses the existing report-before-render decorator and preserves legacy APIs.
Each provider exposes only its own map, following the owner's explicit-composition
request. Separate providers select JSON before the base; a single aggregate
uses base, JSON, application order for required application overrides.
The actual development verification graph includes FastForward Container 1.9.1,
Config 1.6.0 and Interop Service Provider 0.4.1; pending changes are not published
release claims.

No third-party skill text, executable helper, host adapter, icon, secret or
application credential is included. Choose the tag/commit matching the installed
package before using version-sensitive linked guides. Format validation and
behavioral exercises have separate scope; neither proves production delivery.
