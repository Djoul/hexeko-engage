<?php

namespace App\Console\Commands\DevTools;

use Artisan;
use Illuminate\Console\Command;
use Str;

/*
 * Documentation: https://hexeko.atlassian.net/wiki/spaces/UpEngage/pages/258670601/make+crud-model
 */
class MakeCRUDModelCommand extends Command
{
    protected $signature = 'make:crud-model {name}';

    protected $description = 'Create a model with migration, repository, interface, service, controller, actions and tests';

    private const STUBS_PATH = '/app/Console/Commands/DevTools/Stubs/';

    private const CONTRACTS_DIRECTORY = '/Repositories/Contracts';

    private const REPOSITORY_DIRECTORY = '/Repositories/Models';

    private const CONTROLLER_DIRECTORY = '/Http/Controllers/V1';

    private const FORMREQUEST_DIRECTORY = '/Http/Requests';

    private const SERVICE_DIRECTORY = '/Services/Models';

    private const ACTIONS_DIRECTORY = '/Actions/';

    private const TEST_FEATURE_DIRECTORY = '../tests/feature/ModelsCRUD';

    private const TEST_UNIT_DIRECTORY = '../tests/unit/Models';

    public function handle(): void
    {
        $modelName = Str::lower($this->argument('name'));

        $this->createModel($modelName);

        $this->createMigration($modelName);

        $this->createRepositoryInterface($modelName);

        $this->createRepositoryClass($modelName);

        $this->createServiceClass($modelName);

        $this->createController($modelName);

        $this->createFormRequest($modelName);

        $this->createActions($modelName);

        $this->createFeatureTests($modelName);

        $this->createUnitTests($modelName);

        $this->info('Base Files created successfully !!!');
        $this->info('Once your migration are created and migrated, you\'ll be allowed to automatically generate:: Factory and Resources using barryvdh/laravel-ide-helper package');
    }

    private function createModel(string $modelName): void
    {
        Artisan::call('make:model', [
            'name' => $modelName,
        ]);
    }

    private function createMigration(string $modelName): void
    {
        Artisan::call('make:migration', [
            'name' => "create_{$modelName}s_table",
        ]);
    }

    private function createRepositoryInterface(string $model): void
    {
        $this->createFile(
            $model,
            'RepositoryInterface.stub',
            self::CONTRACTS_DIRECTORY,
            Str::studly($model).'RepositoryInterface.php'
        );
        $this->info('Repository Interface created successfully !!!');
    }

    private function createFile(string $model, string $stubName, string $outputDirectory, string $outputFileName): void
    {
        $modelName = Str::studly($model);
        $stubPath = base_path(self::STUBS_PATH.$stubName);
        $fileContent = str_replace(
            ['{$modelName}', '{$modelVar}'],
            [$modelName, $model],
            file_get_contents($stubPath) ?: ''
        );

        $directoryPath = app_path($outputDirectory);
        if (! is_dir($directoryPath)) {
            mkdir($directoryPath, 0755, true);
        }

        $filePath = $directoryPath.'/'.$outputFileName;
        file_put_contents($filePath, $fileContent);
    }

    private function createRepositoryClass(string $model): void
    {
        $this->createFile(
            $model,
            'Repository.stub',
            self::REPOSITORY_DIRECTORY,
            Str::studly($model).'Repository.php'
        );
        $this->info('Repository Class created successfully !!!');
    }

    private function createServiceClass(string $model): void
    {
        $this->createFile(
            $model,
            'Service.stub',
            self::SERVICE_DIRECTORY,
            Str::studly($model).'Service.php'
        );
        $this->info('Service Class created successfully !!!');
    }

    private function createController(string $model): void
    {
        $this->createFile(
            $model,
            'Controller.stub',
            self::CONTROLLER_DIRECTORY,
            Str::studly($model).'Controller.php'
        );
        $this->info('Controller created successfully !!!');
    }

    private function createActions(string $model): void
    {
        $this->createFile(
            $model,
            'Actions/CreateAction.stub',
            self::ACTIONS_DIRECTORY.'/'.Str::studly($model),
            'Create'.Str::studly($model).'Action.php'
        );
        $this->info('createAction created  successfully !!!');

        $this->createFile(
            $model,
            'Actions/UpdateAction.stub',
            self::ACTIONS_DIRECTORY.'/'.Str::studly($model),
            'Update'.Str::studly($model).'Action.php'
        );
        $this->info('updateAction created  successfully !!!');

        $this->createFile(
            $model,
            'Actions/DeleteAction.stub',
            self::ACTIONS_DIRECTORY.'/'.Str::studly($model),
            'Delete'.Str::studly($model).'Action.php'
        );
        $this->info('deleteAction created  successfully !!!');
    }

    private function createFormRequest(string $model): void
    {
        $this->createFile(
            $model,
            'FormRequest.stub',
            self::FORMREQUEST_DIRECTORY,
            Str::studly($model).'FormRequest.php'
        );
        $this->info('FormRequest Class created successfully !!!');
    }

    private function createFeatureTests(string $model): void
    {
        $this->createFile(
            $model,
            'Tests/CreateTest.stub',
            self::TEST_FEATURE_DIRECTORY.'/'.Str::studly($model),
            'Create'.Str::studly($model).'Test.php'
        );
        $this->info('CreateTest created  successfully !!!');

        $this->createFile(
            $model,
            'Tests/DeleteTest.stub',
            self::TEST_FEATURE_DIRECTORY.'/'.Str::studly($model),
            'Delete'.Str::studly($model).'Test.php'
        );
        $this->info('DeleteTest created  successfully !!!');

        $this->createFile(
            $model,
            'Tests/FormRequestTest.stub',
            self::TEST_FEATURE_DIRECTORY.'/'.Str::studly($model),
            Str::studly($model).'FormRequestTest.php'
        );
        $this->info('FormRequestTest created  successfully !!!');

        $this->createFile(
            $model,
            'Tests/FetchByIdTest.stub',
            self::TEST_FEATURE_DIRECTORY.'/'.Str::studly($model),
            'Fetch'.Str::studly($model).'ByIdTest.php'
        );
        $this->info('FetchByIdTest created  successfully !!!');

        $this->createFile(
            $model,
            'Tests/FetchTest.stub',
            self::TEST_FEATURE_DIRECTORY.'/'.Str::studly($model),
            'Fetch'.Str::studly($model).'Test.php'
        );
        $this->info('FetchTest created  successfully !!!');

        $this->createFile(
            $model,
            'Tests/UpdateTest.stub',
            self::TEST_FEATURE_DIRECTORY.'/'.Str::studly($model),
            'Update'.Str::studly($model).'Test.php'
        );
        $this->info('UpdateTest created  successfully !!!');
    }

    private function createUnitTests(string $model): void
    {
        $this->createFile(
            $model,
            'Tests/SmokeTest.stub',
            self::TEST_UNIT_DIRECTORY.'/'.Str::studly($model),
            Str::studly($model).'SmokeTest.php'
        );
        $this->info('CreateTest created  successfully !!!');

    }
}
