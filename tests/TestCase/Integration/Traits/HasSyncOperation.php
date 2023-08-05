<?php

namespace Tests\TestCase\Integration\Traits;

use Tests\DummyRequirements\Models\EUserModel;
use Mawebcoder\Elasticsearch\Models\BaseElasticsearchModel;

trait HasSyncOperation
{
    public function insertElasticDocument(BaseElasticsearchModel $model, array $data): BaseElasticsearchModel
    {
        foreach ($data as $key => $value) {
            $model->{$key} = $value;
        }

        return $model->mustBeSync()->save();
    }

    public function registerSomeTestUserRecords(): array
    {
        $userId1 = $this->faker->unique()->numberBetween(1, 9);
        $userId2 = $this->faker->numberBetween(10, 14);
        $userId3 = $this->faker->numberBetween(15, 20);

        EUserModel::newQuery()->mustBeSync()->saveMany([
            [
                BaseElasticsearchModel::KEY_ID => $userId1,
                EUserModel::KEY_NAME => $this->faker->unique()->name,
                EUserModel::KEY_AGE => 22,
                EUserModel::KEY_DESCRIPTION => $this->faker->word
            ],
            [
                BaseElasticsearchModel::KEY_ID => $userId2,
                EUserModel::KEY_NAME => $this->faker->unique()->name,
                EUserModel::KEY_AGE => 26,
                EUserModel::KEY_DESCRIPTION => $this->faker->word
            ],
            [
                BaseElasticsearchModel::KEY_ID => $userId3,
                EUserModel::KEY_NAME => $this->faker->unique()->name,
                EUserModel::KEY_AGE => 30,
                EUserModel::KEY_DESCRIPTION => $this->faker->word
            ]
        ]);

        return [
            EUserModel::newQuery()->find($userId1),
            EUserModel::newQuery()->find($userId2),
            EUserModel::newQuery()->find($userId3)
        ];
    }

    public function truncateModel(BaseElasticsearchModel $elasticsearch): void
    {
        $elasticsearch->mustBeSync()->truncate();
    }


    public function update(BaseElasticsearchModel $elasticsearch, array $data): bool
    {
        return $elasticsearch->mustBeSync()->update($data);
    }

    public function deleteModel(BaseElasticsearchModel $elasticsearch): void
    {
        $elasticsearch->mustBeSync()->delete();
    }
}