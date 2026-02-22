<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant\Doctrine\Type;

use App\Domain\Tenant\ValueObject\NIP;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class NIPType extends Type
{
    public const NAME = 'nip';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 10]);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof NIP) {
            return $value->toString();
        }

        return $value;
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?NIP
    {
        if ($value === null || $value instanceof NIP) {
            return $value;
        }

        return NIP::fromString($value);
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
