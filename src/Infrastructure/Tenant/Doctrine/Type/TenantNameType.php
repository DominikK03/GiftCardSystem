<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant\Doctrine\Type;

use App\Domain\Tenant\ValueObject\TenantName;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class TenantNameType extends Type
{
    public const NAME = 'tenant_name';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 255]);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof TenantName) {
            return $value->toString();
        }

        return $value;
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?TenantName
    {
        if ($value === null || $value instanceof TenantName) {
            return $value;
        }

        return TenantName::fromString($value);
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
