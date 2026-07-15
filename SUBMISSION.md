# Payment Attribution Incident

---

## The Question

> Create a test assignment for a PHP developer that cannot be solved by AI.  
> How would you verify it?

This document answers all four parts of that brief:

1. The task itself
2. Why AI cannot fully solve it on its own
3. How to tell whether AI was used in the candidate's solution
4. How the exercise is actually solved

---

## 1. The Task

### Short answer

I would not try to ban AI. That is hard to enforce and not how people work in practice.

Instead, I built a task where a copied answer or a shallow patch is not enough. The candidate still has to run the code, trace a real payment flow, fix several related issues, and explain the result on a call.

The rule is not "no AI". The rule is: **you cannot pass with a shallow or unattended solution**.

### What the candidate gets

A small PHP 8.2 project with a realistic payment attribution incident.

**Scenario:** after a recent integration change, some partner signups show up as paid in admin, but partner revenue in reporting stays at zero or does not add up.

The candidate receives:

- `CANDIDATE_TASK.md`
- `composer.json`
- `phpunit.xml`
- `src/`
- `tests/Public/`

They run:

```bash
composer install
composer test:public
```

and send back a patch plus a short `SOLUTION.md`.

### What stays on our side

- `tests/Hidden/`
- `RUBRIC.md`
- `EVALUATOR_GUIDE.md`
- `reference-solution/`
- `benchmarks/`
- `benchmark-runs/`
- `submissions/`

### Why the public test is intentionally vague

The public failure only shows a symptom:

```text
PaymentIncidentPublicTest::testPartnerPaidSignupDoesNotIncreasePartnerRevenue
Failed asserting that 0 is identical to 6900.
```

It does **not** tell the candidate exactly which field or file to change. That is deliberate. A shallow fix can make the public test green without understanding the full payment flow.

---

## 2. Why AI Cannot Fully Solve It

### The core problem for unattended AI

The exercise is not a syntax quiz. It is a multi-layer incident across:

- checkout session metadata
- webhook attribution extraction
- replay and retry semantics
- idempotency key design
- admin serialization
- revenue/source stats
- correction webhooks that arrive after an incomplete first delivery

The public test exposes only one symptom. Hidden tests check the layers people usually miss when they patch from the assertion message alone.

### What shallow fixes typically do

In three independent unattended runs (public package only, no hidden tests, no rubric, no reference solution), every run:

- passed public tests
- failed hidden tests
- scored **25%**

Typical failure pattern:

| Layer | Why AI often misses it |
|---|---|
| `campaign_id` propagation | Public symptom points at revenue, not field names |
| `client_reference_id` fallback | Requires reading provider payload contract, not just metadata |
| Source normalization | Admin and stats disagree subtly; easy to fix only one surface |
| Correction webhook enrichment | Needs understanding of out-of-order / late-arriving events |
| Replay ordering | Marking replay processed too early breaks retry semantics |
| Payment-scoped idempotency | Email-only idempotency looks plausible but breaks separate payments |

### Recorded benchmark evidence

| Run | Public | Hidden | Score |
|---|---|---|---:|
| baseline | fail | fail | 0% |
| reference | pass | pass | 100% |
| unattended_run_1 | pass | fail | 25% |
| unattended_run_2 | pass | fail | 25% |
| unattended_run_3 | pass | fail | 25% |

Target:

- baseline around 0%
- reference around 100%
- unattended runs below 50%

That is what we got.

The first version of this task was too easy. I tightened it. These numbers are from the updated version.

### Where unattended runs failed

| Run | Missed behavior |
|---|---|
| unattended_run_1 | source normalization, correction webhook |
| unattended_run_2 | correction webhook only (6/7 hidden tests passed) |
| unattended_run_3 | source normalization, correction webhook |

### Why this is a better filter than "AI-proof"

A strong developer can still pass, even with normal tooling. The exercise separates:

- someone who traced the flow and understood the contract
- someone who produced a plausible one-file patch from the failing assertion

That is more useful than a CRUD exercise because you get a real diff, an objective score, and a focused interview topic.

---

## 3. How to Tell Whether AI Was Used

You usually cannot prove AI usage with certainty. You **can** detect shallow or copied solutions reliably.

### Automated signals

Run the submission through the benchmark harness:

```bash
benchmarks/run_submission.sh baseline none
benchmarks/run_submission.sh reference reference-solution/solution.patch
benchmarks/run_submission.sh <candidate_name> <candidate.patch>
```

Strong indicators of an unattended or shallow solution:

| Signal | What it means |
|---|---|
| Public pass + hidden fail | Classic shallow patch pattern |
| Score around 25% | Fixes visible symptom, misses contract layers |
| Same failure class across runs | `correction webhook`, `source normalization`, `client_reference_id` |
| Hidden tests pass only after seeing rubric/hidden tests | Candidate did not independently discover full contract |

### Patch-level signals

Review the diff against `RUBRIC.md`. AI-assisted shallow fixes often look like:

- one or two files changed, with no cross-surface consistency work
- metadata fixed in checkout but not in webhook reader
- email-based idempotency left in place
- replay marked processed before durable writes complete
- admin output patched but stats rules unchanged
- broad "make it more robust" comments with little evidence
- hardcoded emails, event IDs, campaign names, or test-specific branches

Strong human submissions usually look like:

- small, scoped diff across the actual contract boundary
- explicit idempotency strategy tied to payment identity
- replay state updated only after successful writes
- reasoning about duplicate delivery and separate payments
- explanation that matches the actual data flow

### Explanation signals

Ask for `SOLUTION.md` and verify it on a 20-30 minute technical call.

Questions that expose shallow or AI-polished answers:

1. Where did attribution first get lost?
2. Why should checkout and webhook use the same metadata contract?
3. Why is `email` alone not a safe idempotency key?
4. What happens if processing fails halfway through a webhook?
5. How should `client_reference_id` be handled?
6. What changes if provider events can arrive out of order?

Weak answers:

- generic incident-response wording
- correct buzzwords with wrong ordering of operations
- cannot explain why hidden tests still fail
- cannot describe what a correction webhook should do without double-counting MRR

### Practical rule

Do not optimize for "detect AI". Optimize for:

**"Can this person defend a production-safe fix under hidden tests and a technical call?"**

That is what the package is designed to measure.

---

## 4. How the Exercise Is Solved

The reference fix is in `reference-solution/solution.patch`.

### Root cause summary

The baseline breaks the attribution contract in several places at once:

1. **Checkout** drops `campaign_id` and does not populate `client_reference_id`
2. **AttributionReader** reads `campaign` instead of `campaign_id` and ignores `client_reference_id`
3. **WebhookProcessor** marks replay processed too early and uses email-only conversion idempotency
4. **Admin serialization** reads `member['campaign']` instead of `campaign_id`, without normalizing source
5. **Stats** skip partner revenue without `campaign_id` and lowercase source while admin does not
6. **Correction path** is missing: a later webhook cannot enrich attribution without double-counting

### Reference solution by layer

#### CheckoutSessionFactory

- propagate `campaign_id` and `partner_ref` into session metadata
- mirror attribution into `client_reference_id` as JSON fallback payload

#### AttributionReader

- read `campaign_id`, not `campaign`
- parse `client_reference_id` when metadata is incomplete
- normalize source consistently

#### WebhookProcessor

- use payment-scoped conversion key: `conversion:payment:{paymentId}`
- mark replay processed only after successful writes
- on duplicate delivery, enrich incomplete attribution when a correction webhook adds missing `campaign_id` / `partner_ref`
- do not double-count MRR when enriching an existing conversion

#### InMemoryStore

- admin member view uses `campaign_id`
- normalize source in admin output
- stats no longer skip partner conversions just because `campaign_id` was missing at first write

### Reference benchmark result

```text
Public:  ..  2 / 2   OK (2 tests, 6 assertions)
Hidden:  .......  7 / 7   OK (7 tests, 18 assertions)
Score:   100%
```

---

## Test Suite

### Public tests (candidate sees these)

| Test | What it checks |
|---|---|
| `testBasicPaidSignupStillWorks` | Direct paid signup still works after the fix |
| `testPartnerPaidSignupDoesNotIncreasePartnerRevenue` | Partner signup is paid, but partner MRR stays at zero in baseline |

### Hidden tests (evaluator only)

| Test | What it checks |
|---|---|
| `testRepeatedWebhookDeliveryIsIdempotentWithoutDoubleCounting` | Same webhook delivered twice does not double-count MRR |
| `testDifferentPaymentsForSameMemberAreSeparateConversions` | Two payments for one member remain separate conversions |
| `testReplayEventStaysRetryableAfterFailedWebhookWrite` | Replay is not marked processed before durable writes complete |
| `testAttributionRecoveredFromClientReferenceIdFallback` | Attribution can be recovered from `client_reference_id` when metadata is incomplete |
| `testSourceNormalizationMatchesBetweenAdminAndStats` | Admin and stats use the same normalized source value |
| `testCorrectionWebhookEnrichesAttributionWithoutDoubleCounting` | Late correction webhook enriches attribution without adding MRR twice |
| `testStatsAndAdminUseSameCampaignAttributionFromFullScenario` | End-to-end scenario keeps admin and stats campaign attribution aligned |

### Score weights

| Area | Weight |
|---|---:|
| Public tests | 25% |
| Hidden tests | 65% |
| Full pass | 10% |

Manual qualitative review uses `RUBRIC.md`.

---

## Benchmark Results

### Summary table

| Run | Public | Hidden | Score | Notes |
|---|---|---:|---:|---|
| baseline | fail | fail | 0% | Broken on purpose |
| reference | pass | pass | 100% | Maintainer fix |
| unattended_run_1 | pass | fail | 25% | Missed normalization + correction webhook |
| unattended_run_2 | pass | fail | 25% | Missed correction webhook only |
| unattended_run_3 | pass | fail | 25% | Missed normalization + correction webhook |

### Sample output: baseline public

```text
.F                                                                  2 / 2

PaymentIncidentPublicTest::testPartnerPaidSignupDoesNotIncreasePartnerRevenue
Failed asserting that 0 is identical to 6900.
```

### Sample output: baseline hidden

```text
FFFFFFF                                                             7 / 7

Failures include:
- duplicate webhook double-counting
- separate payments collapsed into one conversion
- replay marked processed too early
- client_reference_id ignored
- source mismatch: expected 'partner', actual 'Partner'
- correction webhook cannot enrich attribution
- admin/stats campaign mismatch
```

### Sample output: reference solution

```text
Public:  ..  2 / 2   OK (2 tests, 6 assertions)
Hidden:  .......  7 / 7   OK (7 tests, 18 assertions)
```

### Sample output: best unattended run (run_2)

```text
.....F.                                                             7 / 7

PaymentIncidentHiddenTest::testCorrectionWebhookEnrichesAttributionWithoutDoubleCounting
Failed asserting that null is identical to 8300.
```

Full raw logs are stored in:

```text
benchmark-runs/
  baseline/public.log
  baseline/hidden.log
  reference/public.log
  reference/hidden.log
  unattended_run_2/public.log
  unattended_run_2/hidden.log
  */score.json
```

---

## Package Layout

```text
WONKA_test_assesment/
  SUBMISSION.md              # this document
  README.md                  # repo overview
  CANDIDATE_TASK.md          # what the candidate receives
  EVALUATOR_GUIDE.md
  RUBRIC.md
  src/
  tests/
    Public/
    Hidden/
  benchmarks/
    run_submission.sh
    score_run.php
    VALIDATION_PROTOCOL.md
  reference-solution/
    solution.patch
  benchmark-runs/
  submissions/
```

### Candidate commands

```bash
composer install
composer test:public
```

### Evaluator commands

```bash
composer install
composer test:all
benchmarks/run_submission.sh baseline none
benchmarks/run_submission.sh reference reference-solution/solution.patch
```

---

## Bottom Line

I would use this before the interview call.

- The baseline is broken on purpose.
- The reference fix passes fully.
- Unattended partial fixes do not.

That gives a better filter than resume comparison alone, and a much better starting point for the conversation.
