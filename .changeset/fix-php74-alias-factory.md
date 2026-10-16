---
"@coisa/error-handler": patch
---

Fix alias resolution on the declared PHP 7.4 runtime by retaining AliasFactory's unrestricted return contract in PHPDoc instead of a PHP 8 native `mixed` declaration. Preserve exact delegated values, object identity, exceptions and modern PHP behavior.
