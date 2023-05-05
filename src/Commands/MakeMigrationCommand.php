<?php

namespace Mawebcoder\Elasticsearch\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Mawebcoder\Elasticsearch\Exceptions\CanNotCreateTheFileException;

class MakeMigrationCommand extends Command
{
    protected $signature = 'elastic:make-migration {migrationName}';

    protected $description = 'make migration';

    /**
     * @return void
     * @throws CanNotCreateTheFileException
     */
    public function handle(): void
    {
        ['migration_name' => $migration_name, 'directory' => $directory] = $this->getMigrationDirectoryInfo();

        $this->makeDirectoryIfNotExists($directory);

        $fullPath = $this->getFillAbsolutePath($directory, $migration_name);

        $this->createFile($fullPath);
    }


    /**
     * @param string $fullPath
     * @return void
     * @throws CanNotCreateTheFileException
     */
    public function createFile(string $fullPath): void
    {
        $stream = fopen(rtrim($fullPath, '/') . '.php', 'a+');

        if (!$stream) {
            throw new CanNotCreateTheFileException('can not create the file');
        }

        fwrite($stream, '<?php');
        fwrite($stream, "\n");
        fwrite($stream, "\n");
        fwrite($stream, "use Mawebcoder\Elasticsearch\Migration\BaseElasticMigration;");
        fwrite($stream, "\n");
        fwrite($stream, "\n");
        fwrite($stream, "return new  class  extends BaseElasticMigration");
        fwrite($stream, "\n");
        fwrite($stream, "{");
        fwrite($stream, "\n");
        fwrite($stream, "\n");
        fwrite($stream, "\n");
        fwrite($stream, '    public function getModel(): string');
        fwrite($stream, "\n");
        fwrite($stream, "    {");
        fwrite($stream, "\n");
        fwrite($stream, "    }");
        fwrite($stream, "\n");
        fwrite($stream, "\n");
        fwrite($stream, '     public function schema(BaseElasticMigration $mapper)');
        fwrite($stream, "\n");
        fwrite($stream, '    {');
        fwrite($stream, "\n");
        fwrite($stream, "    }");
        fwrite($stream, "\n");
        fwrite($stream, "};");

        fclose($stream);
    }

    public function getFillAbsolutePath($directory, $migrationName): string
    {
        return rtrim($directory, '/') . '/' . $migrationName;
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

    public function getMigrationDirectoryInfo(): array
    {
        $modelNameArgument = $this->argument('migrationName');

        if (!str_contains($modelNameArgument, '/')) {
            return [
                'directory' => rtrim(config('elasticsearch.base_migrations_path'), '/'),
                'migration_name' => $modelNameArgument
            ];
        }

        $exploded = explode('/', $modelNameArgument);

        $lastIndex = count($exploded) - 1;

        return [
            'directory' => rtrim(config('elasticsearch.base_migrations_path'), '/') . '/' . join('/',
                    Arr::except($exploded, $lastIndex)),
            'migration_name' => $exploded[$lastIndex]
        ];
    }
}