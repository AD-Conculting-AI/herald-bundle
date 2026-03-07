<?php

declare(strict_types=1);

namespace Herald\Bundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

final class HeraldBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
