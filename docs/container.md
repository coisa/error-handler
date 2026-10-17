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

The independent [JSON profile](fast-forward.md#json-profile) and
[Whoops profile](fast-forward.md#development-html-profile) select pure renderers.
Compose base, selected profile and application maps explicitly; middleware owns
the single private report. No legacy capture/reporting wrapper is restored.
