# Legacy PSR-11 and ConfigProvider composition

Read when an application already uses a PSR-11 container or Laminas discovery.
Use the complete [container program](../examples/container.php) and its
[dependency/verification instructions](../examples/README.md#legacy-container).
For an application choosing the separate Interop/FastForward integration, first
inspect installed symbols and use [the optional JSON/provider route](json.md).
That route requires application PHP 8.3+ and does not replace these legacy APIs.

The application binds its private reporter under
`CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface`, then passes the parent
to `CoiSA\ErrorHandler\Container\ErrorHandlerContainer`. The parent wins for an
ID it reports as present; otherwise the library resolves its aliases/factories.
Construction/resolution installs no callbacks. A parent lookup that throws is a
failure, not an instruction to silently choose another reporter.

Repeated `get(ErrorHandler::class)` returns the cached handler. Direct
`(new ErrorHandlerFactory())($services)` constructs another core with possibly
shared dependencies. `ErrorHandlerMiddlewareFactory` constructs a wrapper but
resolves its handler from the container, so the handler can still be cached.
Construct a distinct core and middleware for independent nested scopes.

The default aggregate is empty and silent. It attaches an available
`CallableThrowableHandler`; when an application `EventDispatcherInterface` is
present, it also attaches available raw-throwable and ErrorEvent adapters.
Providing only `LoggerInterface`, a filtering reporter or best-effort aggregate
does not automatically attach those services. Compose those reporters explicitly.

A Laminas-style application can merge its library configuration with
application definitions:

```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\Container\ConfigProvider;
use CoiSA\ErrorHandler\Example\RecordingThrowableHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use Laminas\ServiceManager\ServiceManager;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/RecordingThrowableHandler.php';

$definitions = (new ConfigProvider())->getDependencies();
$definitions['services'][ThrowableHandlerInterface::class] = new RecordingThrowableHandler();

$services = new ServiceManager($definitions);
```

Copy the bundled same-named reporter file beside this application example or
substitute the existing application reporter. The library provider exposes
aliases/factories; its Composer Laminas metadata does not register callbacks.
Resolve the core and explicitly select the process/request owner.

HTTP requires application PSR-17 implementations. Any global
`ThrowableHandlerInterface` override also affects the legacy captured-output
renderer: a silent private logger or nonmatching filter can leave empty output
and expose diagnostic fallback text. Select the application
[public response policy](http.md) explicitly.

Unknown IDs use the library PSR-11 NotFoundException. Library factory failures can
be wrapped in ContainerException; direct parent delegation retains its own
failure behavior. Starting with 2.4.2, both public container-exception factories
preserve the exact original as `getPrevious()`; integer codes are copied and
string codes use 0 on the wrapper, retaining the string on the cause. Do not
assume that patch repair in an older installed 2.4 release.

Check application overrides, cached identity, fresh explicit construction,
unknown IDs, missing required implementations and a failing parent factory.
