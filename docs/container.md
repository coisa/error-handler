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
