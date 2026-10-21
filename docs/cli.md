# Terminal diagnostics with Symfony

`SymfonyCliThrowableHandler(?OutputInterface $output = null)` formats the original
Throwable through Symfony's `CliErrorRenderer`. With no output argument it writes
to stderr, without requiring Symfony Console. It does not register callbacks,
choose a SAPI, terminate a process or render an HTTP response.

For direct terminal reporting:

```php
use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\SymfonyCliThrowableHandler;

$errors = new ErrorHandler(new SymfonyCliThrowableHandler());
$errors->handleThrowable($failure);
```

Supply a Console `OutputInterface` when the application already owns one.
A `ConsoleOutputInterface` routes the diagnostic to `getErrorOutput()`;
other outputs receive it directly. Writes use `OUTPUT_RAW`, so exception text
is not parsed as Console markup. The output collaborator requires optional
`symfony/console` in that application. Renderer/output failures propagate unchanged.

## Provider composition

The normal provider remains silent when no reporter is supplied. Explicitly opt in
with `new ErrorHandlerServiceProvider(cli: true)`; debug is a separate first
parameter controlling HTTP JSON/HTML diagnostics. The default aggregate appends the
CLI reporter after its optional configured reporters. An application override of
`ThrowableHandlerInterface` remains authoritative and replaces that aggregate.

Bind `OutputInterface::class` through an application service definition when
needed, then use `container($application, new ErrorHandlerServiceProvider(cli: true))`.
Without that service, diagnostics go to stderr. Service construction installs no
callbacks. There is no automatic SAPI detection and no automatic terminal renderer
in an HTTP response.

## Symfony Console owns one catch

Choose either Symfony Console's built-in exception rendering or this adapter.
When the application bootstrap owns the catch, call `setCatchExceptions(false)`
and `setAutoExit(false)` on its Console application. Catch once around
`run()`, report the original once and choose the command's exit status there.
Do not report and then let Console render the same failure again.

The [complete recipe](https://github.com/coisa/error-handler/blob/master/examples/symfony-cli.php)
has direct, provider and Console modes. It uses `BufferedOutput` only to verify
the synthetic diagnostic without sending it to a terminal. The
[bundled consumer program](../resources/skills/coisa-error-handler/examples/symfony-cli.php)
accepts the application's explicit autoloader. Both succeed after verifying one
diagnostic; a real CLI bootstrap chooses its own failure exit status.

Diagnostics can include messages, paths, previous exceptions and trace data.
Keep this reporter in terminal/private diagnostic flows. HTTP responses use their
pure [request-aware renderers](http.md).
