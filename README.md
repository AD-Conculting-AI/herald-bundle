# Herald Bundle for Symfony

Official Symfony bundle for integrating with [Herald](https://herald-ai.net), the multi-agent orchestration engine.

Send a message to your agent stack, receive the AI response via webhook.

## Installation

```bash
composer require herald-ai/herald-bundle
```

## Configuration

```yaml
# config/packages/herald.yaml
herald:
  api_url: '%env(HERALD_API_URL)%'
  api_key: '%env(HERALD_API_KEY)%'
```

## Usage

### Send a message

```php
use Herald\Bundle\Client\HeraldClient;

final readonly class MyService
{
    public function __construct(
        private HeraldClient $heraldClient,
    ) {}

    public function ask(string $question): string
    {
        $response = $this->heraldClient->sendMessage(
            endpointId: 'your-endpoint-id',
            message: $question,
        );

        return $response->conversationId;
    }
}
```

### Receive responses via webhook

```php
use Herald\Bundle\Event\HeraldResponseReceivedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class HeraldListener
{
    public function __invoke(HeraldResponseReceivedEvent $event): void
    {
        if ($event->event !== 'conversation.completed') {
            return;
        }

        $response = $event->response;
        $cost = $event->usage['totalCost'];
        $model = $event->usage['primaryModel'];
    }
}
```

### System messages and metadata

```php
$response = $this->heraldClient->sendMessage(
    endpointId: 'your-endpoint-id',
    message: 'How can I help you?',
    systemMessages: [
        'You are a support agent for Acme Corp.',
        'The customer is on the Pro plan.',
    ],
    metadata: [
        'userId' => 'user-123',
        'source' => 'chat-widget',
    ],
);
```

Metadata is passed through to webhook events, so you can correlate responses with your application data.

## Webhook events

Herald sends webhook events at each stage of conversation processing. Events fall into two categories with different payloads.

### In-flight events

These events are dispatched while the conversation is still being processed. They carry context but no response or usage data.

**`conversation.started`** — The agent stack received the message and began processing.

**`conversation.paused`** — An agent triggered a human-in-the-loop escalation. The conversation is waiting for a human response in the Herald inbox.

**`conversation.resumed`** — A team member answered the escalation. The agent stack resumed processing.

**In-flight payload fields:**

| Field | Type | Description |
|-------|------|-------------|
| `$event->event` | `string` | Event type (`conversation.started`, `conversation.paused`, `conversation.resumed`) |
| `$event->conversationId` | `string` | Unique conversation identifier |
| `$event->nodeId` | `?string` | ID of the node that triggered the event |
| `$event->stackId` | `?string` | ID of the agent stack |
| `$event->stackName` | `?string` | Human-readable stack name |
| `$event->status` | `string` | Current status (`pending`, `paused`) |
| `$event->metadata` | `array` | Your metadata passed via `sendMessage()` |

### Terminal events

These events are dispatched when the conversation reaches a final state. They include the full response and token usage statistics.

**`conversation.completed`** — The agent stack finished processing. The AI response is available in `$event->response`.

**`conversation.failed`** — Processing failed. The reason is available in `$event->failureReason`.

**Terminal payload fields** (all in-flight fields, plus):

| Field | Type | Description |
|-------|------|-------------|
| `$event->response` | `?string` | The AI-generated response (null on failure) |
| `$event->failureReason` | `?string` | Why processing failed (null on success) |
| `$event->usage['inputTokens']` | `int` | Total input tokens consumed |
| `$event->usage['outputTokens']` | `int` | Total output tokens generated |
| `$event->usage['inputCost']` | `?string` | Input cost in USD |
| `$event->usage['outputCost']` | `?string` | Output cost in USD |
| `$event->usage['totalCost']` | `?string` | Total cost in USD |
| `$event->usage['llmCalls']` | `int` | Number of LLM API calls made |
| `$event->usage['primaryModel']` | `?string` | Main LLM model used |
| `$event->usage['generationTimeMs']` | `int` | Total processing time in milliseconds |

## Requirements

- PHP 8.2+
- Symfony 7.0+ or 8.0+

## License

MIT

## Links

- [Herald](https://herald-ai.net) — Multi-agent orchestration engine
- [Documentation](https://herald-ai.net/docs)
- [GitHub](https://github.com/AD-Conculting-AI/herald-bundle)
