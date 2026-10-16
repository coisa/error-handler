# Bounded example verification

Run `php examples/verify.php` after installing the documented development graph.
The verifier uses Symfony Process with literal argv, `php -n`, 64 MiB per child,
a five-second timeout and an abort above 16 KiB of observed combined output.
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
