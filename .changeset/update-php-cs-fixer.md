---
"@coisa/error-handler": patch
---

Update development tooling to CoiSA PHP CS Fixer 3 and PHPUnit 9.6, which provides the shared diff dependency required by the current formatter. Check changed PHP files in pull requests through the formatter's reusable workflow. Preserve the existing PHP runtime requirements and public API.

The committed development lockfile requires PHP `>=8.4.1 <8.5`.
