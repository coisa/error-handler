# Source and rights

This package belongs to [coisa/error-handler](https://github.com/coisa/error-handler).
Its reusable instructions, references and application examples are original
project material under the bundled MIT license. Preserve the source identity,
namespace and license when copying it. Third-party implementations are installed
by the consumer through Composer; none are copied into this package.

The supported API contract is `coisa/error-handler ^2.4`, starting with 2.4.0.
Inspect the consumer's actual installed version and matching source before using
version-specific behavior. The package uses PHP 7.4-compatible syntax and the
existing `CoiSA\ErrorHandler` namespace.

Advanced JSON/Whoops/provider routes were published in 2.5.0 and require
`coisa/error-handler ^2.5`, plus their named symbols in the selected installation.
The common baseline remains `^2.4`; optional FastForward integration also requires
`fast-forward/container ^1.9` and application PHP 8.3 or newer.
Whoops is an optional consumer dependency, with a minimum of `^2.18.7` for the
development HTML route. No Whoops source is copied into this skill.
