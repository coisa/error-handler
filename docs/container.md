# Container integration

Use the [Interop/FastForward service provider](fast-forward.md) for container
integration. Direct handler construction remains available. Applications upgrading
from the 2.x standalone `ErrorHandlerContainer`, its factories/exceptions or
Laminas `ConfigProvider` should follow the [migration map](migration.md).

FastForward caches services. Resolve and invoke the provider's explicit factory
services when a fresh handler/middleware scope is required. Discovery does not
install native PHP callbacks; applications choose when to register the process
handler. Merge application overrides after library definitions in one aggregate
provider as shown under [required overrides](fast-forward.md#required-overrides).
The [bundled required-reporter program](../resources/skills/coisa-error-handler/examples/container.php)
provides complete setup; the short optional-discovery recipe uses a different
composition.

The best-effort aggregate is also explicit: construct it with application reporters
and bind the required reporting interface in the merged map. No factory, alias or
automatic membership selects it. The default aggregate remains fail-fast; see
[best-effort reporting](api.md#best-effort-reporting).

The single [request-aware provider](fast-forward.md#request-aware-responses)
selects a pure renderer from the current request's Accept header. Supply the
application first: `container($application, ErrorHandlerServiceProvider::class)`.
The default HTML implementation is Symfony in both public and debug modes.
Trusted debug enables details but never forces HTML. Optional Whoops requires
an explicit application override. JSON-only
requests do not construct Whoops. Middleware owns the single private report.
Use a merged map only when a required override must not fall through after a
container construction failure.
