# Container integration in the unreleased major

The standalone `ErrorHandlerContainer`, its factories/exceptions and Laminas
`ConfigProvider` belong to the published 2.x API. They are removed in this major.
Use the [Interop/FastForward service provider](fast-forward.md) and follow the
[migration map](migration.md). Direct handler construction remains available.

FastForward caches services. Resolve and invoke the provider's explicit factory
services when a fresh handler/middleware scope is required. Discovery does not
install native PHP callbacks; applications choose when to register the process
handler. Merge application overrides after library definitions in one aggregate
provider. See the [complete example](../examples/fast-forward.php).

The best-effort aggregate is also explicit: construct it with application reporters
and bind the required reporting interface in the merged map. No factory, alias or
automatic membership selects it. The default aggregate remains fail-fast; see
[best-effort reporting](api.md#best-effort-reporting).

The independent [JSON profile](fast-forward.md#json-profile) and
[Whoops profile](fast-forward.md#development-html-profile) select pure renderers.
Compose base, selected profile and application maps explicitly; middleware owns
the single private report. No legacy capture/reporting wrapper is restored.
