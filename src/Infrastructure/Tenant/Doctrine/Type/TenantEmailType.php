<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant\Doctrine\Type;

use App\Domain\Tenant\ValueObject\TenantEmail;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class TenantEmailType extends Type
{
    public const NAME = 'tenant_email';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 255]);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof TenantEmail) {
            return $value->toString();
        }

        return $value;
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?TenantEmail
    {
        if ($value === null || $value instanceof TenantEmail) {
            return $value;
        }

        return TenantEmail::fromString($value);
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
