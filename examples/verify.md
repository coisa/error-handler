# Bounded example verification

Run `php examples/verify.php` after installing the documented development graph.
The verifier uses Symfony Process with literal argv, the selected PHP binary's
normal extension configuration, 64 MiB per child,
a five-second timeout and an abort above 16 KiB of observed combined output.
E_ALL diagnostics go to stderr with native logging disabled. Keeping the normal
configuration preserves Composer's actual extension platform for Symfony/Whoops
rendering; minimal core termination fixtures separately use `php -n`.
A one-shot callback prevents a second exception during `finally` cleanup; the
final pipe chunk can exceed the threshold, so it is not an exact memory ceiling.

The child environment drops inherited variable names except the explicit fixture
marker and optional selected Composer autoloader. Inputs are synthetic. Each
recipe must match its exact exit code, stdout and stderr; CLI deliberately exits
1 after reporting a warning and uncaught exception. Other examples return normally.

This verifier is example evidence, not a second test framework. PHPUnit contains
the behavioral assertions and externally bounded native-termination fixtures.
It does not use credentials, network servers, production logs or child coverage
aggregation, and never publishes documentation or a release.

The verifier covers every recipe and documented mode. JSON public/debug, Symfony HTML public/debug,
Symfony CLI direct/provider/Console ownership, manual HTML public/development and HTML provider public/Whoops-default/configured are included
without an extra FastForward flag: the container is a runtime dependency of this
major. The event recipe uses FastForward's published provider/configuration API. The
documented development graph supplies Console and optional Whoops for those tests.
Middleware recipes report the original once before pure rendering; the direct
HTML pipeline reports explicitly once and supplies its current request. A
configured page is a cached service, while runs and request scopes are fresh.
