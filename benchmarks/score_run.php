<?php

declare(strict_types=1);

if ($argc !== 6) {
    fwrite(STDERR, "Usage: php score_run.php <name> <public-exit> <hidden-exit> <public-log> <hidden-log>\n");
    exit(2);
}

[$script, $name, $publicExit, $hiddenExit, $publicLogPath, $hiddenLogPath] = $argv;

$publicLog = is_file($publicLogPath) ? file_get_contents($publicLogPath) : '';
$hiddenLog = is_file($hiddenLogPath) ? file_get_contents($hiddenLogPath) : '';

$publicPassed = ((int) $publicExit) === 0;
$hiddenPassed = ((int) $hiddenExit) === 0;

$score = 0;
$notes = [];

if ($publicPassed) {
    $score += 25;
    $notes[] = 'public tests passed';
} else {
    $notes[] = 'public tests failed';
}

if ($hiddenPassed) {
    $score += 65;
    $notes[] = 'hidden tests passed';
} else {
    $notes[] = 'hidden tests failed';
}

$hiddenSignals = [
    'Repeated webhook delivery' => 'testRepeatedWebhookDeliveryIsIdempotentWithoutDoubleCounting',
    'Separate same-member payments' => 'testDifferentPaymentsForSameMemberAreSeparateConversions',
    'Retry after failure' => 'testReplayEventStaysRetryableAfterFailedWebhookWrite',
    'Client reference fallback' => 'testAttributionRecoveredFromClientReferenceIdFallback',
    'Source normalization' => 'testSourceNormalizationMatchesBetweenAdminAndStats',
    'Correction webhook' => 'testCorrectionWebhookEnrichesAttributionWithoutDoubleCounting',
    'Admin/stats consistency' => 'testStatsAndAdminUseSameCampaignAttributionFromFullScenario',
];

$failedSignals = [];
foreach ($hiddenSignals as $label => $needle) {
    if (str_contains($hiddenLog, $needle)) {
        $failedSignals[] = $label;
    }
}

if ($hiddenPassed && $publicPassed) {
    $score += 10;
    $notes[] = 'full automated behavior appears correct';
}

if ($publicPassed && !$hiddenPassed) {
    $notes[] = 'partial fix; review idempotency, retry semantics, and attribution consistency';
}

if (!$publicPassed && !$hiddenPassed) {
    $score = min($score, 5);
}

$score = min($score, 100);

echo json_encode([
    'submission' => $name,
    'score_percent' => $score,
    'public_exit_code' => (int) $publicExit,
    'hidden_exit_code' => (int) $hiddenExit,
    'public_passed' => $publicPassed,
    'hidden_passed' => $hiddenPassed,
    'failed_hidden_signals' => $failedSignals,
    'notes' => $notes,
    'manual_review_note' => 'Use RUBRIC.md for qualitative review of explanation, hardcoding, and scope.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
