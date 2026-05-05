<?php

namespace App\Tests\Support;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Symfony\Bridge\Doctrine\Types\AbstractUidType;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\Uuid;

/**
 * UUID type that stores as VARCHAR(36) RFC 4122 strings.
 *
 * Doctrine's default AbstractUidType uses BLOB on SQLite (no native GUID),
 * which breaks DQL parameter comparison. This override forces string storage
 * so tests on SQLite behave the same as PostgreSQL's native UUID type.
 */
final class StringUuidType extends AbstractUidType
{
    public const NAME = 'uuid';

    public function getName(): string
    {
        return self::NAME;
    }

    protected function getUidClass(): string
    {
        return Uuid::class;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(36)';
    }

    public function getBindingType(): \Doctrine\DBAL\ParameterType
    {
        return \Doctrine\DBAL\ParameterType::STRING;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }
        if ($value instanceof AbstractUid) {
            return $value->toRfc4122();
        }

        return Uuid::fromString((string) $value)->toRfc4122();
    }
}
