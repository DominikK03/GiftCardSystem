<?php

declare(strict_types=1);

namespace App\Domain\Tenant\ValueObject;

use App\Domain\Tenant\Exception\InvalidInvoiceNumberException;

final class InvoiceNumber
{
    private function __construct(
        private readonly string $value
    ) {
    }

    public static function generate(int $year, int $month, int $sequentialNumber): self
    {
        if ($year < 2000 || $year > 9999) {
            throw InvalidInvoiceNumberException::invalidYear($year);
        }

        if ($month < 1 || $month > 12) {
            throw InvalidInvoiceNumberException::invalidMonth($month);
        }

        if ($sequentialNumber < 1) {
            throw InvalidInvoiceNumberException::invalidSequentialNumber($sequentialNumber);
        }

        $value = sprintf('FV/%04d/%02d/%05d', $year, $month, $sequentialNumber);

        return new self($value);
    }

    public static function fromString(string $value): self
    {
        if (empty($value)) {
            throw InvalidInvoiceNumberException::empty();
        }

        if (!preg_match('/^FV\/\d{4}\/\d{2}\/\d{5}$/', $value)) {
            throw InvalidInvoiceNumberException::invalidFormat($value);
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
