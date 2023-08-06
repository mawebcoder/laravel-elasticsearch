<?php

namespace Tests\TestCase\Integration\Traits;

use InvalidArgumentException;
use Tests\DummyRequirements\Models\EUserModel;

trait HasFakeMigration
{
    const DUMMY_MIGRATIONS_PATH = 'DummyRequirements/Migrations/';
    const TEST_FOLDER_RELATIVE_PATH = __DIR__ . '/../../../';

    public function getTestMigrationNameByModel($model): string
    {
        return match ($model) {
            EUserModel::class => '2023_04_16_074007_create_tests_table.php',
            default => throw new InvalidArgumentException("You Must passed valid Model!")
        };
    }

    public function getMigrationPathByModel($model)
    {
        $relativePath = self::TEST_FOLDER_RELATIVE_PATH . static::DUMMY_MIGRATIONS_PATH . $this->getTestMigrationNameByModel(
                $model
            );

        return realpath($relativePath);
    }

    public static function getMigrationPath(): string
    {
        return realpath(self::TEST_FOLDER_RELATIVE_PATH  . static::DUMMY_MIGRATIONS_PATH);
    }
}