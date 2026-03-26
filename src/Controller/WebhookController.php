<?php

declare(strict_types=1);

namespace Herald\Bundle\Controller;

use Herald\Bundle\Event\HeraldResponseReceivedEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
final readonly class WebhookController
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private string $heraldWebhookSecret,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $body = (string) $request->getContent();

        if ($this->heraldWebhookSecret !== '') {
            $this->verifySignature($request, $body);
        }

        $data = json_decode($body, true);

        if (!\is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON body.'], Response::HTTP_BAD_REQUEST);
        }

        $event = $data['event'] ?? null;

        if (!\is_string($event) || $event === '') {
            return new JsonResponse(['error' => 'Missing event field.'], Response::HTTP_BAD_REQUEST);
        }

        $conversationId = $data['conversationId'] ?? null;

        if (!\is_string($conversationId) || $conversationId === '') {
            return new JsonResponse(['error' => 'Missing conversationId field.'], Response::HTTP_BAD_REQUEST);
        }

        $this->dispatcher->dispatch(new HeraldResponseReceivedEvent(
            conversationId: $conversationId,
            nodeId: \is_string($data['nodeId'] ?? null) ? $data['nodeId'] : null,
            stackId: \is_string($data['stackId'] ?? null) ? $data['stackId'] : null,
            status: \is_string($data['status'] ?? null) ? $data['status'] : 'unknown',
            response: \is_string($data['response'] ?? null) ? $data['response'] : null,
            metadata: \is_array($data['clientMetadata'] ?? null) ? $data['clientMetadata'] : [],
            usage: $this->parseUsage($data['usage'] ?? null),
            event: $event,
            failureReason: \is_string($data['failureReason'] ?? null) ? $data['failureReason'] : null,
            stackName: \is_string($data['stackName'] ?? null) ? $data['stackName'] : null,
        ));

        return new JsonResponse(['ok' => true]);
    }

    private function verifySignature(Request $request, string $body): void
    {
        $signature = $request->headers->get('X-Herald-Signature');

        if (!\is_string($signature) || $signature === '') {
            throw new WebhookSignatureException('Missing X-Herald-Signature header.');
        }

        $expected = hash_hmac('sha256', $body, $this->heraldWebhookSecret);

        if (!hash_equals($expected, $signature)) {
            throw new WebhookSignatureException('Invalid webhook signature.');
        }
    }

    /**
     * @return array{inputTokens: int, outputTokens: int, inputCost: ?string, outputCost: ?string, totalCost: ?string, llmCalls: int, primaryModel: ?string, generationTimeMs: int}
     */
    private function parseUsage(mixed $raw): array
    {
        if (!\is_array($raw)) {
            return HeraldResponseReceivedEvent::EMPTY_USAGE;
        }

        return [
            'inputTokens' => \is_int($raw['inputTokens'] ?? null) ? $raw['inputTokens'] : 0,
            'outputTokens' => \is_int($raw['outputTokens'] ?? null) ? $raw['outputTokens'] : 0,
            'inputCost' => \is_string($raw['inputCost'] ?? null) ? $raw['inputCost'] : null,
            'outputCost' => \is_string($raw['outputCost'] ?? null) ? $raw['outputCost'] : null,
            'totalCost' => \is_string($raw['totalCost'] ?? null) ? $raw['totalCost'] : null,
            'llmCalls' => \is_int($raw['llmCalls'] ?? null) ? $raw['llmCalls'] : 0,
            'primaryModel' => \is_string($raw['primaryModel'] ?? null) ? $raw['primaryModel'] : null,
            'generationTimeMs' => \is_int($raw['generationTimeMs'] ?? null) ? $raw['generationTimeMs'] : 0,
        ];
    }
}
