# PHP Incident Test Assignment

## Goal

Investigate and fix a payment attribution incident in a small PHP 8.2 codebase.

The task is intentionally production-like: you are not expected to guess the answer from the failing assertion alone. Start from the runtime symptoms, follow the checkout/webhook flow, fix the root cause, and prove that the fix is safe across retries and repeated payments.

You may use documentation, search, IDE tooling, or other helpers. The final submission must still include a working patch, tests where appropriate, and an explanation you can defend on a technical call.

## Incident

After a recent payment integration change, some paid customers appear as paid in the admin view, but partner revenue and attribution reporting are inconsistent.

Known symptoms:

- direct paid signup mostly works;
- partner signups can look paid in admin while partner revenue stays at zero;
- repeated webhook delivery can produce incorrect conversion state;
- retry behavior is suspicious after malformed webhook events;
- admin member data and source stats can disagree;
- payment provider payloads sometimes carry attribution outside plain session metadata.

## Required Work

1. Run the public test suite and reproduce the incident.
2. Inspect checkout session creation, webhook processing, attribution extraction, replay/idempotency behavior, admin serialization, and stats.
3. Implement a scoped fix.
4. Add or update tests if needed.
5. Submit a patch and a short `SOLUTION.md`.

## Public Commands

```bash
composer install
composer test:public
```

The evaluator will also run hidden integration tests:

```bash
composer test:hidden
composer test:all
```

## Submission Requirements

Your final answer must include:

- a git diff or patch;
- commands you ran;
- a short explanation of the root cause;
- why your idempotency key is safe;
- what happens when a webhook fails halfway through processing;
- how attribution remains consistent across admin output and stats;
- how you handle attribution when metadata is incomplete but provider payload still contains attribution elsewhere.

## Important Constraints

- Do not hardcode seeded emails, event IDs, payment IDs, campaign IDs, or test names.
- Do not suppress webhook errors broadly.
- Do not disable replay processing.
- Do not mark replay events processed before durable writes are complete.
- Do not convert campaign attribution into an unrelated generic field.
- Preserve normal direct checkout behavior.
