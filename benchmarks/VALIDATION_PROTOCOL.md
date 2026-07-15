# Validation Protocol

This protocol checks whether the exercise separates shallow fixes from complete solutions.

## Required runs

| Run | Description | Expected score |
|---|---|---:|
| `baseline` | No code changes | 0-5% |
| `reference` | Maintainer reference fix | 95-100% |
| `unattended_run_1` | Independent attempt, public package only | < 50% |
| `unattended_run_2` | Independent attempt, public package only | < 50% |
| `unattended_run_3` | Independent attempt, public package only | < 50% |

## Unattended run rules

- Provide only `CANDIDATE_TASK.md`, `composer.json`, `phpunit.xml`, `src/`, and `tests/Public/`.
- Do not provide hidden tests, rubric, evaluator guide, or reference solution.
- Use a fixed time limit, for example 45-60 minutes.
- Record the final patch, test output, and short explanation.

## Validity criteria

The exercise is considered valid if:

- baseline scores at or below 5%;
- reference solution scores at or above 95%;
- at least three unattended runs score below 50%;
- failures reflect missed engineering behavior, not broken infrastructure;
- hidden tests match the incident described to the candidate.

## Evidence to keep

For each run, keep:

- final patch
- public test output
- hidden test output
- score JSON
- short review notes

Recommended layout:

```text
benchmark-runs/
  baseline/
  reference/
  unattended_run_1/
  unattended_run_2/
  unattended_run_3/
```
