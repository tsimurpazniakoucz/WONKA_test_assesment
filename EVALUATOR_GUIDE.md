# Evaluator Guide

## What this exercise measures

This is not a syntax quiz. It checks whether a PHP developer can investigate a realistic payment incident across several layers:

- request/query input
- checkout session metadata
- webhook processing
- replay and retry behavior
- idempotency
- admin serialization
- revenue/source stats

## Suggested flow

1. Send the candidate `CANDIDATE_TASK.md`, `composer.json`, `phpunit.xml`, `src/`, and `tests/Public/`.
2. Keep hidden tests, rubric, and reference solution private.
3. Ask for a patch plus a short `SOLUTION.md`.
4. Run public and hidden tests.
5. Review the diff against `RUBRIC.md`.
6. Spend 20-30 minutes on a technical call.

## Technical call questions

1. Where did attribution first get lost?
2. Why should checkout and webhook use the same metadata contract?
3. Why is `email` alone not a safe idempotency key?
4. What happens if processing fails halfway through a webhook?
5. How should `client_reference_id` be handled?
6. What would change if provider events could arrive out of order?

## Strong submissions usually include

- a small, scoped diff
- a clear idempotency strategy
- replay state updated only after successful writes
- reasoning about duplicate delivery and repeated payments
- a concrete explanation of the data flow

## Weak submissions often look like

- broad "make it more robust" wording with little evidence
- one-line fixes in a single file
- suppressed exceptions
- hardcoded event IDs, emails, or campaign names
- public tests passing while hidden tests still fail
