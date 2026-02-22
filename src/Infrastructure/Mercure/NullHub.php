<?php

declare(strict_types=1);

namespace App\Infrastructure\Mercure;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Update;

final class NullHub implements HubInterface
{
    public function getPublicUrl(): string
    {
        return 'http://null-hub.local/.well-known/mercure';
    }

    public function getFactory(): ?TokenFactoryInterface
    {
        return null;
    }

    public function publish(Update $update): string
    {
        return 'null-update-id';
    }
}
