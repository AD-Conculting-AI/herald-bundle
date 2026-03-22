<?php

declare(strict_types=1);

namespace Herald\Bundle\Event;

final readonly class HeraldResponseReceivedEvent
{
    /** @var array{inputTokens: int, outputTokens: int, inputCost: ?string, outputCost: ?string, totalCost: ?string, llmCalls: int, primaryModel: ?string, generationTimeMs: int} */
    public const array EMPTY_USAGE = [
        'inputTokens' => 0,
        'outputTokens' => 0,
        'inputCost' => null,
        'outputCost' => null,
        'totalCost' => null,
        'llmCalls' => 0,
        'primaryModel' => null,
        'generationTimeMs' => 0,
    ];

    /**
     * @param string $conversationId Herald conversation ID
     * @param string $status Conversation status (active, paused, completed, failed, cancelled) — NOT the event type
     * @param string $event Webhook event type (conversation.started, conversation.paused, conversation.completed, conversation.failed, conversation.cancelled, conversation.llm_retried, conversation.llm_failed)
     * @param array<string, mixed> $metadata Client metadata passée par le tiers (ex: inboundEmailId)
     * @param array{inputTokens: int, outputTokens: int, inputCost: ?string, outputCost: ?string, totalCost: ?string, llmCalls: int, primaryModel: ?string, generationTimeMs: int} $usage
     */
    public function __construct(
        public string $conversationId,
        public ?string $nodeId,
        public ?string $stackId,
        public string $status,
        public ?string $response,
        public array $metadata = [],
        public array $usage = self::EMPTY_USAGE,
        public string $event = '',
        public ?string $failureReason = null,
        public ?string $stackName = null,
    ) {}
}
