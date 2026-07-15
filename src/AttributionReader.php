<?php

declare(strict_types=1);

namespace Interview\Incident;

final class AttributionReader
{
    /**
     * @return array{source: string, campaign_id: ?string, partner_ref: ?string}
     */
    public function fromCheckoutSession(array $session): array
    {
        $metadata = $session['metadata'] ?? [];

        return [
            'source' => (string) ($metadata['source'] ?? 'direct'),
            'campaign_id' => isset($metadata['campaign']) ? (string) $metadata['campaign'] : null,
            'partner_ref' => isset($metadata['partner_ref']) ? (string) $metadata['partner_ref'] : null,
        ];
    }
}
