<?php

declare(strict_types=1);

namespace App\Interface\Http\Request;

final class IsoDateTimeFormat
{
    public const PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/';
}
