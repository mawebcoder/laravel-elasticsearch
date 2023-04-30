<?php

namespace Mawebcoder\Elasticsearch\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Arr;
use Mawebcoder\Elasticsearch\Http\ElasticApiService;
use ReflectionException;

class ReIndexMigrationJob implements ShouldBeUnique
{
    use Dispatchable;
    use SerializesModels;
    use Queueable;
    use InteractsWithQueue;

    public function __construct(
        public string $taskId,
        public array $currentMapping,
        public array $newMappings,
        public string $tempIndex,
        public string $modelIndex,
        public array $dropFields
    ) {
    }

    /**
     * @param ElasticApiService $elasticApiService
     * @return void
     * @throws ReflectionException
     */
    public function handle(ElasticApiService $elasticApiService): void
    {
        while (true) {
            sleep(1);

            $response = $elasticApiService->get('_tasks/' . $this->taskId);

            $isCompleted = boolval(json_decode($response->getBody(), true)['completed']);

            if (!$isCompleted) {
                continue;
            }

            $finalMappings = $this->currentMapping;

            foreach ($this->newMappings as $key => $newMapping) {
                if (array_key_exists($key, $finalMappings)) {
                    continue;
                }

                $finalMappings[$key] = $newMapping;
            }

            $finalMappings = Arr::except($finalMappings, array_keys($this->dropFields));

            $chosenSource = array_keys(Arr::except($finalMappings, array_keys($this->dropFields)));

            $elasticApiService->put($this->modelIndex . DIRECTORY_SEPARATOR . '_mapping', [
                "properties" => $finalMappings
            ]);

            $elasticApiService->post(path: '_reindex', data: [
                "source" => [
                    "index" => $this->tempIndex,
                    "_source" => $chosenSource
                ],
                "dest" => [
                    "index" => $this->modelIndex
                ]
            ]);

            break;
        }

        $elasticApiService->delete($this->tempIndex);
    }
}