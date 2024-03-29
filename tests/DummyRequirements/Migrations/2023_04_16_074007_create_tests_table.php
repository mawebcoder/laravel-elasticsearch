<?php

use Tests\DummyRequirements\Models\EUserModel;
use Mawebcoder\Elasticsearch\Migration\BaseElasticMigration;

return new class extends BaseElasticMigration {

    public function getModel(): string
    {
        return EUserModel::class;
    }

    public function schema(BaseElasticMigration $mapper): void
    {
        $mapper->string(EUserModel::KEY_NAME);
        $mapper->integer(EUserModel::KEY_AGE);
        $mapper->boolean(EUserModel::KEY_IS_ACTIVE);
        $mapper->text(EUserModel::KEY_DESCRIPTION);

        $mapper->object(EUserModel::KEY_INFORMATION, function (BaseElasticMigration $baseElasticMigration) {
            return $baseElasticMigration->object('ages', function (BaseElasticMigration $baseElasticMigration) {
                return $baseElasticMigration->integer('age');
            })->string('name')
                ->integer('age');
        });
    }
};
