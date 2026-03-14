<?php

declare(strict_types=1);

namespace Herald\Bundle\Controller;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class WebhookSignatureException extends AccessDeniedHttpException
{
}
