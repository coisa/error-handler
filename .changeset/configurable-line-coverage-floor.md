---
"@coisa/error-handler": patch
---

Permit coverage decreases while requiring an overall line-coverage minimum of
80% by default, configurable with MINIMUM_COVERAGE_THRESHOLD. Preserve authentic
baseline comparisons, per-file differences and the existing sticky PR report.
