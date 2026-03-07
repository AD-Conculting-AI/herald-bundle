<?php

declare(strict_types=1);

namespace Herald\Bundle\Client;

final readonly class HeraldResponse
{
    public function __construct(
        public string $conversationId,
        public string $status,
    ) {}
}
