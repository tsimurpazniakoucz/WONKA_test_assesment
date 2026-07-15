<?php

declare(strict_types=1);

namespace Interview\Incident\Tests\Hidden;

use Interview\Incident\CheckoutSessionFactory;
use Interview\Incident\InMemoryStore;
use Interview\Incident\PaymentIncidentScenario;
use Interview\Incident\WebhookProcessor;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PaymentIncidentHiddenTest extends TestCase
{
    public function testRepeatedWebhookDeliveryIsIdempotentWithoutDoubleCounting(): void
    {
        $store = new InMemoryStore();
        $processor = new WebhookProcessor($store);
        $event = $this->event(
            eventId: 'evt_hidden_repeat',
            paymentId: 'pi_hidden_repeat',
            email: 'repeat@example.com',
            source: 'partner',
            campaignId: 'repeat-campaign',
            amount: 9900
        );

        $processor->processCheckoutCompleted($event);
        $processor->processCheckoutCompleted($event);

        self::assertCount(1, $store->conversions());
        self::assertSame(9900, $store->sourceStats()['partner']['mrr']);
    }

    public function testDifferentPaymentsForSameMemberAreSeparateConversions(): void
    {
        $store = new InMemoryStore();
        $processor = new WebhookProcessor($store);

        $processor->processCheckoutCompleted($this->event(
            eventId: 'evt_hidden_first',
            paymentId: 'pi_hidden_first',
            email: 'same-member@example.com',
            source: 'partner',
            campaignId: 'first-campaign',
            amount: 3900
        ));
        $processor->processCheckoutCompleted($this->event(
            eventId: 'evt_hidden_second',
            paymentId: 'pi_hidden_second',
            email: 'same-member@example.com',
            source: 'partner',
            campaignId: 'second-campaign',
            amount: 5900
        ));

        self::assertCount(2, $store->conversions());
        self::assertSame(9800, $store->sourceStats()['partner']['mrr']);
        self::assertSame('second-campaign', $store->adminMember('same-member@example.com')['attribution']['campaign_id']);
    }

    public function testReplayEventStaysRetryableAfterFailedWebhookWrite(): void
    {
        $store = new InMemoryStore();
        $processor = new WebhookProcessor($store);

        try {
            $processor->processCheckoutCompleted([
                'id' => 'evt_hidden_retry',
                'type' => 'checkout.session.completed',
                'data' => [
                    'object' => [
                        'id' => 'cs_hidden_retry',
                        'payment_intent' => 'pi_hidden_retry',
                        'amount_total' => 4900,
                        'metadata' => [
                            'source' => 'partner',
                            'campaign_id' => 'retry-campaign',
                        ],
                    ],
                ],
            ]);
            self::fail('The malformed webhook should fail before being marked processed.');
        } catch (RuntimeException) {
            self::assertFalse($store->isReplayProcessed('evt_hidden_retry'));
        }

        $processor->processCheckoutCompleted($this->event(
            eventId: 'evt_hidden_retry',
            paymentId: 'pi_hidden_retry',
            email: 'retry@example.com',
            source: 'partner',
            campaignId: 'retry-campaign',
            amount: 4900
        ));

        self::assertSame('paid', $store->adminMember('retry@example.com')['subscription_status']);
        self::assertTrue($store->isReplayProcessed('evt_hidden_retry'));
    }

    public function testAttributionRecoveredFromClientReferenceIdFallback(): void
    {
        $store = new InMemoryStore();
        $processor = new WebhookProcessor($store);

        $processor->processCheckoutCompleted([
            'id' => 'evt_hidden_client_ref',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_hidden_client_ref',
                    'payment_intent' => 'pi_hidden_client_ref',
                    'customer_email' => 'client-ref@example.com',
                    'amount_total' => 7200,
                    'metadata' => [
                        'email' => 'client-ref@example.com',
                        'source' => 'partner',
                    ],
                    'client_reference_id' => json_encode([
                        'campaign_id' => 'client-ref-campaign',
                        'partner_ref' => 'agency-77',
                    ], JSON_THROW_ON_ERROR),
                ],
            ],
        ]);

        $adminMember = $store->adminMember('client-ref@example.com');

        self::assertSame('client-ref-campaign', $adminMember['attribution']['campaign_id']);
        self::assertSame(7200, $store->sourceStats()['partner']['mrr']);
    }

    public function testSourceNormalizationMatchesBetweenAdminAndStats(): void
    {
        $store = new InMemoryStore();
        $scenario = new PaymentIncidentScenario(
            new CheckoutSessionFactory(),
            new WebhookProcessor($store)
        );

        $scenario->runPaidSignup([
            'email' => 'normalize@example.com',
            'amount' => 5100,
            'query' => [
                'source' => 'Partner',
                'campaign_id' => 'normalize-campaign',
            ],
        ], 'evt_hidden_normalize', 'pi_hidden_normalize');

        $adminMember = $store->adminMember('normalize@example.com');

        self::assertSame('partner', $adminMember['attribution']['source']);
        self::assertSame(5100, $store->sourceStats()['partner']['mrr']);
    }

    public function testCorrectionWebhookEnrichesAttributionWithoutDoubleCounting(): void
    {
        $store = new InMemoryStore();
        $processor = new WebhookProcessor($store);

        $processor->processCheckoutCompleted([
            'id' => 'evt_hidden_correction_initial',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_hidden_correction',
                    'payment_intent' => 'pi_hidden_correction',
                    'customer_email' => 'correction@example.com',
                    'amount_total' => 8300,
                    'metadata' => [
                        'email' => 'correction@example.com',
                        'source' => 'partner',
                    ],
                ],
            ],
        ]);

        $processor->processCheckoutCompleted([
            'id' => 'evt_hidden_correction_followup',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_hidden_correction',
                    'payment_intent' => 'pi_hidden_correction',
                    'customer_email' => 'correction@example.com',
                    'amount_total' => 8300,
                    'metadata' => [
                        'email' => 'correction@example.com',
                        'source' => 'partner',
                    ],
                    'client_reference_id' => json_encode([
                        'campaign_id' => 'corrected-campaign',
                    ], JSON_THROW_ON_ERROR),
                ],
            ],
        ]);

        self::assertCount(1, $store->conversions());
        self::assertSame(8300, $store->sourceStats()['partner']['mrr']);
        self::assertSame('corrected-campaign', $store->adminMember('correction@example.com')['attribution']['campaign_id']);
    }

    public function testStatsAndAdminUseSameCampaignAttributionFromFullScenario(): void
    {
        $store = new InMemoryStore();
        $scenario = new PaymentIncidentScenario(
            new CheckoutSessionFactory(),
            new WebhookProcessor($store)
        );

        $scenario->runPaidSignup([
            'email' => 'stats@example.com',
            'amount' => 12900,
            'query' => [
                'source' => 'partner',
                'campaign_id' => 'stats-campaign',
                'partner_ref' => 'agency-42',
            ],
        ], 'evt_hidden_stats', 'pi_hidden_stats');

        $adminMember = $store->adminMember('stats@example.com');
        $conversions = array_values($store->conversions());

        self::assertSame('stats-campaign', $adminMember['attribution']['campaign_id']);
        self::assertSame('stats-campaign', $conversions[0]['campaign_id']);
        self::assertSame(12900, $store->sourceStats()['partner']['mrr']);
    }

    /**
     * @return array<string, mixed>
     */
    private function event(
        string $eventId,
        string $paymentId,
        string $email,
        string $source,
        string $campaignId,
        int $amount
    ): array {
        return [
            'id' => $eventId,
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_' . $eventId,
                    'payment_intent' => $paymentId,
                    'customer_email' => $email,
                    'amount_total' => $amount,
                    'metadata' => [
                        'email' => $email,
                        'source' => $source,
                        'campaign_id' => $campaignId,
                    ],
                ],
            ],
        ];
    }
}
