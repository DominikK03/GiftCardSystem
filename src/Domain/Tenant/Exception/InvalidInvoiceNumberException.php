<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Exception;

class InvalidInvoiceNumberException extends TenantException
{
    public static function empty(): self
    {
        return new self('Invoice number cannot be empty');
    }

    public static function invalidFormat(string $value): self
    {
        return new self(sprintf('Invalid invoice number format: %s. Expected: FV/YYYY/MM/NNNNN', $value));
    }

    public static function invalidYear(int $year): self
    {
        return new self(sprintf('Invalid year: %d', $year));
    }

    public static function invalidMonth(int $month): self
    {
        return new self(sprintf('Invalid month: %d', $month));
    }

    public static function invalidSequentialNumber(int $number): self
    {
        return new self(sprintf('Invalid sequential number: %d. Must be positive', $number));
    }
}
