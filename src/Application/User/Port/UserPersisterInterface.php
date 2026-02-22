<?php

declare(strict_types=1);

namespace App\Application\User\Port;

use App\Domain\User\Entity\User;

interface UserPersisterInterface
{
    public function handle(User $user): void;
}
