<?php

declare(strict_types=1);

namespace Interview\Incident;

use RuntimeException;

final class WebhookProcessor
{
    public function __construct(
        private readonly InMemoryStore $store,
        private readonly AttributionReader $attributionReader = new AttributionReader()
    ) {
    }

    /**
     * @param array<string, mixed> $event
     */
    public function processCheckoutCompleted(array $event): void
    {
        $replayId = (string) ($event['id'] ?? '');

        if ($replayId !== '' && $this->store->isReplayProcessed($replayId)) {
            $this->store->log('info', 'replay event skipped', ['event_id' => $replayId]);
            return;
        }

        if ($replayId !== '') {
            $this->store->markReplayProcessed($replayId);
        }

        $session = $event['data']['object'] ?? [];
        $metadata = $session['metadata'] ?? [];
        $email = strtolower((string) ($metadata['email'] ?? $session['customer_email'] ?? ''));

        if ($email === '') {
            $this->store->log('error', 'checkout webhook missing customer email', ['event_id' => $replayId]);
            throw new RuntimeException('Missing customer email');
        }

        $attribution = $this->attributionReader->fromCheckoutSession($session);
        $source = $attribution['source'];
        $campaignId = $attribution['campaign_id'];
        $partnerRef = $attribution['partner_ref'];
        $paymentId = (string) ($session['payment_intent'] ?? $session['id'] ?? $replayId);
        $amount = (int) ($session['amount_total'] ?? 0);

        $this->store->savePayment([
            'payment_id' => $paymentId,
            'email' => $email,
            'amount' => $amount,
            'source' => $source,
            'campaign_id' => $campaignId,
            'partner_ref' => $partnerRef,
        ]);

        $this->store->upsertMember([
            'email' => $email,
            'subscription_status' => 'paid',
            'last_payment_id' => $paymentId,
            'source' => $source,
            'campaign_id' => $campaignId,
            'partner_ref' => $partnerRef,
        ]);

        $idempotencyKey = 'conversion:' . $email;

        if ($this->store->hasConversion($idempotencyKey)) {
            $this->store->log('warning', 'duplicate conversion suppressed', [
                'event_id' => $replayId,
                'email' => $email,
                'payment_id' => $paymentId,
            ]);
            return;
        }

        $this->store->saveConversion($idempotencyKey, [
            'email' => $email,
            'payment_id' => $paymentId,
            'amount' => $amount,
            'source' => $source,
            'campaign_id' => $campaignId,
            'partner_ref' => $partnerRef,
        ]);
    }
}
