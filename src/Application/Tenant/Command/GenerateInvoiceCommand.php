<?php

declare(strict_types=1);

namespace App\Application\Tenant\Command;

final readonly class GenerateInvoiceCommand
{
    /**
     * @param array<array{description: string, quantity: int, unitPriceAmount: int}> $items
     */
    public function __construct(
        public string $tenantId,
        public array  $items,
        public string $currency,
        public int    $vatRate
    ) {
    }
}
