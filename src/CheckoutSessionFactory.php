<?php

declare(strict_types=1);

namespace Interview\Incident;

final class CheckoutSessionFactory
{
    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function create(array $request): array
    {
        $email = strtolower((string) $request['email']);
        $query = $request['query'] ?? [];

        return [
            'session_id' => 'cs_' . substr(sha1($email . json_encode($query)), 0, 12),
            'customer_email' => $email,
            'amount' => (int) ($request['amount'] ?? 2900),
            'metadata' => [
                'email' => $email,
                'source' => $query['source'] ?? 'direct',
            ],
        ];
    }
}
