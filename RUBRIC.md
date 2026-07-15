# Evaluation Rubric

Use this rubric for manual review. Score behavior, architecture, and verification quality. Do not award points for matching the reference patch line by line.

## Scoring

| Criterion | Weight |
|---|---:|
| Checkout metadata propagation | 20% |
| Webhook attribution handling | 20% |
| Replay/idempotency correctness | 25% |
| Admin and stats consistency | 15% |
| Regression safety and tests | 10% |
| Explanation and investigation quality | 10% |

## Detailed criteria

### Checkout metadata propagation — 20%

- 9-10: Preserves `source`, `campaign_id`, and partner-related request context through checkout metadata and `client_reference_id` without breaking direct checkout.
- 5-8: Handles the main campaign case but misses aliases, existing customers, or edge metadata.
- 1-4: Patches only one source path or only the visible test case.
- 0: No meaningful metadata propagation.

### Webhook attribution handling — 20%

- 9-10: Reads the same metadata contract that checkout writes, falls back to `client_reference_id`, handles missing optional metadata safely, and records conversion/member attribution consistently.
- 5-8: Fixes common metadata but leaves one surface inconsistent.
- 1-4: Suppresses warnings or writes attribution to the wrong field.
- 0: Webhook behavior effectively unchanged.

### Replay and idempotency correctness — 25%

- 9-10: Uses replay safety by event identity, payment-scoped conversion identity, enriches incomplete attribution on correction webhooks without double-counting, allows separate payments for the same member, and marks replay processed only after successful writes.
- 5-8: Prevents obvious duplicates but has a weak key or incomplete retry behavior.
- 1-4: Email-only, process-local, or hardcoded idempotency.
- 0: Disables replay or makes duplicates worse.

### Admin and stats consistency — 15%

- 9-10: Admin member attribution, conversions, and stats use the same effective source/campaign values.
- 5-8: Two surfaces agree but one edge case is stale.
- 1-4: Only display output is patched.
- 0: No meaningful consistency fix.

### Regression safety and tests — 10%

- 9-10: Public and hidden tests pass, with added tests for retries, duplicate delivery, direct checkout, and repeated payments.
- 5-8: Main tests pass but coverage is thin.
- 1-4: Tests are brittle or assert implementation details.
- 0: No reproducible verification.

### Explanation and investigation quality — 10%

- 9-10: Explains symptoms, root cause, rejected alternatives, idempotency design, and verification commands.
- 5-8: Correct summary with minor gaps.
- 1-4: Generic explanation with little evidence.
- 0: No explanation.

## Caps and penalties

- Hardcoded test data: cap at 35%
- Broad webhook error suppression: cap at 40%
- Disabled replay processing: cap at 30%
- Attribution stored only in logs or comments: cap at 25%
- Broken direct checkout or basic paid signup: cap at 60%
- Hidden tests passed only by special-casing test names or IDs: cap at 20%
- Patch missing and only prose provided: cap at 15%
