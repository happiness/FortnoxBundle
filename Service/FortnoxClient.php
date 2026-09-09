<?php

declare(strict_types=1);

namespace KimaiPlugin\FortnoxBundle\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Milestone-2 scaffold.
 *
 * This client deliberately does not own OAuth token persistence yet. Once OAuth is
 * added, uploadCustomerInvoiceInbox() can be called with a valid access token to
 * upload the generated PDF to the Fortnox customer-invoice inbox (Inbox_kf).
 */
final class FortnoxClient
{
    private const API_BASE = 'https://api.fortnox.se/3';

    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    /** @return array<string, mixed> */
    public function uploadCustomerInvoiceInbox(string $accessToken, string $filename, string $pdf): array
    {
        $response = $this->httpClient->request('POST', self::API_BASE . '/inbox?path=Inbox_kf', [
            'auth_bearer' => $accessToken,
            'headers' => [
                'Accept' => 'application/json',
            ],
            'body' => [
                'file' => $pdf,
            ],
        ]);

        return $response->toArray(false);
    }
}
