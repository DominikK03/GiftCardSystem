<?php

declare(strict_types=1);

namespace App\Application\Tenant\Port;

use App\Domain\Tenant\Entity\Tenant;

interface PdfGeneratorInterface
{
    public function generateCooperationAgreement(Tenant $tenant): string;

    /**
     * @param array<array{description: string, quantity: int, unitPriceAmount: int}> $items
     */
    public function generateInvoice(
        Tenant $tenant,
        string $invoiceNumber,
        array  $items,
        string $currency,
        int    $vatRate
    ): string;
}
