# Payment Attribution Incident

Interview exercise for a PHP developer. The package includes the candidate task, a broken baseline project, public tests, hidden evaluator tests, scoring rubric, validation tooling, and a reference solution.

**Full write-up:** see [`SUBMISSION.md`](SUBMISSION.md) for the complete assignment description, benchmark results, AI-resistance rationale, detection signals, and reference solution walkthrough.

**Scope note:** this is a compact, general-purpose example of the evaluation approach — not a model-specific benchmark hardened to defeat current top-tier AI on every run. See the [Scope and Honest Limits](SUBMISSION.md#scope-and-honest-limits) section in `SUBMISSION.md`.

## Layout

```text
WONKA_test_assesment/
  CANDIDATE_TASK.md
  EVALUATOR_GUIDE.md
  RUBRIC.md
  composer.json
  phpunit.xml
  src/
  tests/Public/
  tests/Hidden/
  benchmarks/
  reference-solution/solution.patch
  benchmark-runs/
  submissions/
```

## Candidate package

Share only:

- `CANDIDATE_TASK.md`
- `composer.json`
- `phpunit.xml`
- `src/`
- `tests/Public/`

Candidate commands:

```bash
composer install
composer test:public
```

## Evaluator package

Keep private:

- `tests/Hidden/`
- `RUBRIC.md`
- `EVALUATOR_GUIDE.md`
- `reference-solution/`
- `benchmarks/`
- `benchmark-runs/`
- `submissions/`

Evaluator commands:

```bash
composer install
composer test:all
benchmarks/run_submission.sh baseline none
benchmarks/run_submission.sh reference reference-solution/solution.patch
```

## Why the public test is not enough

The public failure only shows that partner revenue is not counted after a paid signup. It does not reveal whether the issue is in metadata propagation, attribution extraction, admin serialization, stats rules, replay safety, or correction webhooks.

Hidden tests cover:

- duplicate webhook delivery;
- separate payments for the same member;
- retry after malformed webhook processing;
- attribution recovery from `client_reference_id`;
- source normalization between admin and stats;
- correction webhook enrichment without double-counting MRR;
- consistency across admin, conversions, and stats.

## Validation standard

Recorded benchmark runs in `benchmark-runs/`:

- baseline should score around 0%;
- reference solution should score around 100%;
- unattended partial fixes should stay below 50%.

See `benchmarks/VALIDATION_PROTOCOL.md` for details.
