<?php

declare(strict_types=1);

namespace Herald\Bundle\Client;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class HeraldClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $heraldApiUrl,
        private string $heraldApiKey,
        private bool $heraldVerifyPeer = true,
    ) {}

    public function isConfigured(): bool
    {
        return $this->heraldApiUrl !== '' && $this->heraldApiKey !== '';
    }

    /**
     * @param list<string>         $systemMessages
     * @param array<string, mixed> $metadata
     */
    public function sendMessage(
        string $endpointId,
        string $message,
        array $systemMessages = [],
        array $metadata = [],
    ): HeraldResponse {
        $body = ['message' => $message];

        if ($systemMessages !== []) {
            $body['systemMessages'] = $systemMessages;
        }

        if ($metadata !== []) {
            $body['metadata'] = $metadata;
        }

        $options = [
            'json' => $body,
            'headers' => [
                'X-Api-Key' => $this->heraldApiKey,
                'Content-Type' => 'application/json',
            ],
        ];

        if (!$this->heraldVerifyPeer) {
            $options['verify_peer'] = false;
            $options['verify_host'] = false;
        }

        $response = $this->httpClient->request('POST', sprintf(
            '%s/public/api/v1/inbound/%s',
            rtrim($this->heraldApiUrl, '/'),
            $endpointId,
        ), $options);

        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new HeraldApiException(sprintf(
                'Herald API returned HTTP %d: %s',
                $statusCode,
                $response->getContent(false),
            ), $statusCode);
        }

        /** @var array{id?: string, status?: string} $data */
        $data = $response->toArray();

        $conversationId = $data['id'] ?? null;

        if (!\is_string($conversationId) || $conversationId === '') {
            throw new HeraldApiException('Herald API response missing conversation id.');
        }

        return new HeraldResponse(
            conversationId: $conversationId,
            status: \is_string($data['status'] ?? null) ? $data['status'] : 'unknown',
        );
    }
}
