<?php

declare(strict_types=1);

namespace Interview\Incident\Tests\Public;

use Interview\Incident\CheckoutSessionFactory;
use Interview\Incident\InMemoryStore;
use Interview\Incident\PaymentIncidentScenario;
use Interview\Incident\WebhookProcessor;
use PHPUnit\Framework\TestCase;

final class PaymentIncidentPublicTest extends TestCase
{
    public function testBasicPaidSignupStillWorks(): void
    {
        $store = new InMemoryStore();
        $scenario = new PaymentIncidentScenario(
            new CheckoutSessionFactory(),
            new WebhookProcessor($store)
        );

        $scenario->runPaidSignup([
            'email' => 'buyer@example.com',
            'amount' => 4900,
            'query' => ['source' => 'newsletter'],
        ], 'evt_public_basic', 'pi_public_basic');

        $adminMember = $store->adminMember('buyer@example.com');

        self::assertSame('paid', $adminMember['subscription_status']);
        self::assertSame('pi_public_basic', $adminMember['last_payment_id']);
        self::assertSame('newsletter', $adminMember['attribution']['source']);
    }

    public function testPartnerPaidSignupDoesNotIncreasePartnerRevenue(): void
    {
        $store = new InMemoryStore();
        $scenario = new PaymentIncidentScenario(
            new CheckoutSessionFactory(),
            new WebhookProcessor($store)
        );

        $scenario->runPaidSignup([
            'email' => 'partner-buyer@example.com',
            'amount' => 6900,
            'query' => [
                'source' => 'partner',
                'campaign_id' => 'spring-php-2026',
            ],
        ], 'evt_public_partner', 'pi_public_partner');

        $adminMember = $store->adminMember('partner-buyer@example.com');
        $partnerMrr = $store->sourceStats()['partner']['mrr'] ?? 0;

        self::assertSame('paid', $adminMember['subscription_status']);
        self::assertSame('partner', $adminMember['attribution']['source']);
        self::assertSame(6900, $partnerMrr);
    }
}
