<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant\Doctrine\Type;

use App\Domain\Tenant\ValueObject\PhoneNumber;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class PhoneNumberType extends Type
{
    public const NAME = 'phone_number';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 20]);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof PhoneNumber) {
            return $value->toString();
        }

        return $value;
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?PhoneNumber
    {
        if ($value === null || $value instanceof PhoneNumber) {
            return $value;
        }

        return PhoneNumber::fromString($value);
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
