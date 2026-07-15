<?php

declare(strict_types=1);

namespace Interview\Incident;

final class InMemoryStore
{
    /** @var array<string, array<string, mixed>> */
    private array $members = [];

    /** @var array<string, array<string, mixed>> */
    private array $payments = [];

    /** @var array<string, bool> */
    private array $processedReplayEvents = [];

    /** @var array<string, array<string, mixed>> */
    private array $conversions = [];

    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    private array $logs = [];

    /**
     * @param array<string, mixed> $member
     */
    public function upsertMember(array $member): void
    {
        $email = strtolower((string) $member['email']);
        $existing = $this->members[$email] ?? [];
        $this->members[$email] = array_replace($existing, $member, ['email' => $email]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findMember(string $email): ?array
    {
        return $this->members[strtolower($email)] ?? null;
    }

    /**
     * @param array<string, mixed> $payment
     */
    public function savePayment(array $payment): void
    {
        $this->payments[(string) $payment['payment_id']] = $payment;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function payments(): array
    {
        return $this->payments;
    }

    public function markReplayProcessed(string $replayId): void
    {
        $this->processedReplayEvents[$replayId] = true;
    }

    public function isReplayProcessed(string $replayId): bool
    {
        return $this->processedReplayEvents[$replayId] ?? false;
    }

    public function hasConversion(string $idempotencyKey): bool
    {
        return isset($this->conversions[$idempotencyKey]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findConversion(string $idempotencyKey): ?array
    {
        return $this->conversions[$idempotencyKey] ?? null;
    }

    /**
     * @param array<string, mixed> $conversion
     */
    public function saveConversion(string $idempotencyKey, array $conversion): void
    {
        $this->conversions[$idempotencyKey] = $conversion;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function conversions(): array
    {
        return $this->conversions;
    }

    /**
     * @return array<string, mixed>
     */
    public function adminMember(string $email): array
    {
        $member = $this->findMember($email);

        if ($member === null) {
            return [];
        }

        return [
            'email' => $member['email'],
            'subscription_status' => $member['subscription_status'] ?? 'free',
            'last_payment_id' => $member['last_payment_id'] ?? null,
            'attribution' => [
                'source' => $member['source'] ?? null,
                'campaign_id' => $member['campaign'] ?? null,
            ],
        ];
    }

    /**
     * @return array<string, array{paid_members: int, mrr: int}>
     */
    public function sourceStats(): array
    {
        $stats = [];

        foreach ($this->conversions as $conversion) {
            $source = strtolower((string) ($conversion['source'] ?? 'unknown'));

            if ($source === 'partner' && empty($conversion['campaign_id'])) {
                continue;
            }

            $stats[$source] ??= ['paid_members' => 0, 'mrr' => 0];
            $stats[$source]['paid_members']++;
            $stats[$source]['mrr'] += (int) ($conversion['amount'] ?? 0);
        }

        return $stats;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $this->logs[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }

    /**
     * @return list<array{level: string, message: string, context: array<string, mixed>}>
     */
    public function logs(): array
    {
        return $this->logs;
    }
}
