<?php

namespace Mawebcoder\Elasticsearch\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Mawebcoder\Elasticsearch\Exceptions\CanNotCreateTheFileException;
use Mawebcoder\Elasticsearch\Models\BaseElasticsearchModel;

class MakeModelCommand extends Command
{
    protected $signature = 'elastic:make-model {modelName}';

    protected $description = 'make a model';


    /**
     * @throws Exception
     */
    public function handle(): void
    {
        ['model_name' => $modelName, 'directory' => $directory] = $this->getModelNameDirectoryInfo();


        $this->makeDirectoryIfNotExists($directory);

        $fullPath = $this->getFillAbsolutePath($directory, $modelName);

        $this->createFile($fullPath, $directory);
    }

    public function getModelNameDirectoryInfo(): ?array
    {
        $modelNameArgument = $this->argument('modelName');

        if (!str_contains($modelNameArgument, '/')) {
            return [
                'directory' => rtrim(config('elasticsearch.base_models_path'), '/'),
                'model_name' => $modelNameArgument
            ];
        }

        $exploded = explode('/', $modelNameArgument);

        $lastIndex = count($exploded) - 1;


        return [
            'directory' => rtrim(config('elasticsearch.base_models_path'), '/') . '/' . join('/',
                    Arr::except($exploded, $lastIndex)),
            'model_name' => $exploded[$lastIndex]
        ];
    }

    /**
     * @param $directory
     * @return void
     */
    public function makeDirectoryIfNotExists($directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        mkdir($directory, recursive: true);
    }


    public function getFillAbsolutePath($directory, $modelName): string
    {
        return rtrim($directory, '/') . '/' . $modelName;
    }


    /**
     * @throws CanNotCreateTheFileException
     */
    public function createFile(string $fullPath, string $directory): void
    {
        $namespace = str_replace('/', '\\', ucfirst(trim(str_replace(base_path(), '', $directory), '/')));

        $stream = fopen(rtrim($fullPath, '/') . '.php', 'a+');


        if (!$stream) {
            throw new CanNotCreateTheFileException('can not create the file');
        }

        $modelName = Arr::last(explode('/', $fullPath));

        fwrite($stream, '<?php');
        fwrite($stream, "\n");
        fwrite($stream, "\n");
        fwrite($stream, 'namespace ' . $namespace . ';');
        fwrite($stream, "\n");
        fwrite($stream, "\n");
        fwrite($stream, "use Mawebcoder\\Elasticsearch\Models\\BaseElasticsearchModel;");
        fwrite($stream, "\n");
        fwrite($stream, "\n");
        fwrite($stream, "class $modelName extends BaseElasticsearchModel");
        fwrite($stream, "\n");
        fwrite($stream, "{");
        fwrite($stream, "\n");
        fwrite($stream, "\n");
        fwrite($stream, "\n");
        fwrite($stream, '    public function getIndex(): string');
        fwrite($stream, "\n");
        fwrite($stream, "    {");
        fwrite($stream, "\n");
        fwrite($stream, "    }");
        fwrite($stream, "\n");
        fwrite($stream, "\n");
        fwrite($stream, "}");

        fclose($stream);
    }
}