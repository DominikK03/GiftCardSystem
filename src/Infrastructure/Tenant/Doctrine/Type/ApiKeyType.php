<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant\Doctrine\Type;

use App\Domain\Tenant\ValueObject\ApiKey;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class ApiKeyType extends Type
{
    public const NAME = 'api_key';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 64]);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof ApiKey) {
            return $value->toString();
        }

        return $value;
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?ApiKey
    {
        if ($value === null || $value instanceof ApiKey) {
            return $value;
        }

        return ApiKey::fromString($value);
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
