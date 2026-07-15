<?php

declare(strict_types=1);

namespace Interview\Incident;

final class PaymentIncidentScenario
{
    public function __construct(
        private readonly CheckoutSessionFactory $checkoutSessionFactory,
        private readonly WebhookProcessor $webhookProcessor
    ) {
    }

    /**
     * @param array<string, mixed> $request
     */
    public function runPaidSignup(array $request, string $eventId, string $paymentId): void
    {
        $session = $this->checkoutSessionFactory->create($request);
        $session['payment_intent'] = $paymentId;
        $session['amount_total'] = $request['amount'] ?? $session['amount'];

        $this->webhookProcessor->processCheckoutCompleted([
            'id' => $eventId,
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => $session,
            ],
        ]);
    }
}
