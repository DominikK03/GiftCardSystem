<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Enum;

enum DocumentType: string
{
    case COOPERATION_AGREEMENT = 'COOPERATION_AGREEMENT';
    case SIGNED_COOPERATION_AGREEMENT = 'SIGNED_COOPERATION_AGREEMENT';
    case INVOICE = 'INVOICE';
}
