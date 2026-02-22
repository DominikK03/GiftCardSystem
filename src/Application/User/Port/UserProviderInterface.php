<?php

declare(strict_types=1);

namespace App\Application\User\Port;

use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\UserId;

interface UserProviderInterface
{
    public function loadFromId(UserId $id): User;
}
