---
"@coisa/error-handler": minor
---

Add an opt-in BestEffortThrowableHandlerAggregate that attempts every configured reporter in order and then exposes all reporter failures through AggregateReportingException, preserving the original throwable. Existing fail-fast aggregation, defaults, callback lifecycle and HTTP behavior remain unchanged.
