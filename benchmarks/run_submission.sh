#!/usr/bin/env bash
set -euo pipefail

if [[ $# -lt 2 ]]; then
  echo "Usage: benchmarks/run_submission.sh <submission-name> <patch-file-or-none>"
  echo "Example: benchmarks/run_submission.sh baseline none"
  echo "Example: benchmarks/run_submission.sh unattended_run_1 submissions/unattended_run_1.patch"
  exit 2
fi

SUBMISSION_NAME="$1"
PATCH_FILE="$2"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RUN_DIR="$ROOT_DIR/benchmark-runs/$SUBMISSION_NAME"
WORK_DIR="$RUN_DIR/work"

rm -rf "$RUN_DIR"
mkdir -p "$WORK_DIR"

rsync -a \
  --exclude benchmark-runs \
  --exclude vendor \
  --exclude .phpunit.cache \
  "$ROOT_DIR/" "$WORK_DIR/"

cd "$WORK_DIR"

if [[ "$PATCH_FILE" != "none" ]]; then
  patch -p0 --batch < "$ROOT_DIR/$PATCH_FILE"
fi

if [[ ! -d vendor ]]; then
  composer install --no-interaction > "$RUN_DIR/composer.log" 2>&1
fi

set +e
composer test:public > "$RUN_DIR/public.log" 2>&1
PUBLIC_EXIT=$?
composer test:hidden > "$RUN_DIR/hidden.log" 2>&1
HIDDEN_EXIT=$?
set -e

php "$ROOT_DIR/benchmarks/score_run.php" \
  "$SUBMISSION_NAME" \
  "$PUBLIC_EXIT" \
  "$HIDDEN_EXIT" \
  "$RUN_DIR/public.log" \
  "$RUN_DIR/hidden.log" \
  > "$RUN_DIR/score.json"

cat "$RUN_DIR/score.json"
