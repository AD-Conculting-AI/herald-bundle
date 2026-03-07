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

| Event | Description |
|-------|-------------|
| `conversation.started` | Agent stack began processing |
| `conversation.paused` | Waiting for human-in-the-loop |
| `conversation.completed` | Final response available in `$event->response` |
| `conversation.failed` | Processing failed, reason in `$event->failureReason` |

## Requirements

- PHP 8.2+
- Symfony 7.0+ or 8.0+

## License

MIT

## Links

- [Herald](https://herald-ai.net) — Multi-agent orchestration engine
- [Documentation](https://herald-ai.net/docs)
- [GitHub](https://github.com/AD-Conculting-AI/herald-bundle)
