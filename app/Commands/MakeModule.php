<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Scaffolds a Controller, Model, and migration stub for a new feature
 * module, following this project's naming and folder conventions.
 *
 * Usage:
 *   php spark make:module Booking
 *   php spark make:module Tribute --table=tributes
 */
class MakeModule extends BaseCommand
{
    protected $group       = 'Generators';
    protected $name        = 'make:module';
    protected $description = 'Scaffolds a Controller, Model, and migration stub for a new feature module.';
    protected $usage       = 'make:module <Name> [--table=<table_name>]';
    protected $arguments   = [
        'Name' => 'The module name, PascalCase, singular (e.g. Booking, Tribute, GiftOrder).',
    ];
    protected $options = [
        '--table' => 'Database table name to use in the model. Defaults to a snake_case plural of the module name.',
    ];

    public function run(array $params)
    {
        $name = array_shift($params);

        if (empty($name)) {
            CLI::error('You must provide a module name.');
            CLI::write('Usage: ' . $this->usage);

            return;
        }

        $name  = ucfirst($name);
        $table = CLI::getOption('table') ?? $this->toSnakeCasePlural($name);

        $this->createController($name);
        $this->createModel($name, $table);
        $this->createMigrationStub($name, $table);

        CLI::newLine();
        CLI::write('Module scaffolded. Next steps:', 'yellow');
        CLI::write(' 1. Fill in the migration fields in the new migration file.');
        CLI::write(' 2. Run: php spark migrate');
        CLI::write(" 3. Add routes for {$name}Controller in app/Config/Routes.php");
    }

    protected function createController(string $name): void
    {
        $className = "{$name}Controller";
        $path       = APPPATH . "Controllers/{$className}.php";

        if (file_exists($path)) {
            CLI::error("Controller already exists: {$path}");

            return;
        }

        $modelVar  = lcfirst($name) . 'Model';
        $modelName = "{$name}Model";

        $stub = <<<PHP
<?php

namespace App\Controllers;

use App\Models\\{$modelName};
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;

class {$className} extends ResourceController
{
    use ResponseTrait;

    protected \${$modelVar};

    public function __construct()
    {
        \$this->{$modelVar} = new {$modelName}();
    }

    public function index()
    {
        return \$this->respond(\$this->{$modelVar}->findAll());
    }

    public function show(\$id = null)
    {
        \$record = \$this->{$modelVar}->find(\$id);

        if (\$record === null) {
            return \$this->failNotFound('{$name} not found.');
        }

        return \$this->respond(\$record);
    }

    public function create()
    {
        \$data = \$this->request->getJSON(true);

        \$id = \$this->{$modelVar}->insert(\$data);

        if (\$id === false) {
            return \$this->fail(\$this->{$modelVar}->errors());
        }

        return \$this->respondCreated(\$this->{$modelVar}->find(\$id));
    }

    public function update(\$id = null)
    {
        if (\$this->{$modelVar}->find(\$id) === null) {
            return \$this->failNotFound('{$name} not found.');
        }

        \$data = \$this->request->getJSON(true);

        if (!\$this->{$modelVar}->update(\$id, \$data)) {
            return \$this->fail(\$this->{$modelVar}->errors());
        }

        return \$this->respond(\$this->{$modelVar}->find(\$id));
    }

    public function delete(\$id = null)
    {
        if (\$this->{$modelVar}->find(\$id) === null) {
            return \$this->failNotFound('{$name} not found.');
        }

        \$this->{$modelVar}->delete(\$id);

        return \$this->respondDeleted(['id' => \$id]);
    }
}

PHP;

        $this->writeFile($path, $stub);
    }

    protected function createModel(string $name, string $table): void
    {
        $className = "{$name}Model";
        $path       = APPPATH . "Models/{$className}.php";

        if (file_exists($path)) {
            CLI::error("Model already exists: {$path}");

            return;
        }

        $stub = <<<PHP
<?php

namespace App\Models;

use CodeIgniter\Model;

class {$className} extends Model
{
    protected \$table            = '{$table}';
    protected \$primaryKey       = 'id';
    protected \$useAutoIncrement = true;
    protected \$returnType       = 'array';
    protected \$useSoftDeletes   = false;

    protected \$allowedFields = [
        // TODO: list insertable/updatable fields for {$table}
    ];

    protected \$useTimestamps = true;
    protected \$createdField  = 'created_at';
    protected \$updatedField  = 'updated_at';

    protected \$validationRules    = [
        // TODO: add field validation rules
    ];
    protected \$validationMessages = [];
    protected \$skipValidation     = false;
}

PHP;

        $this->writeFile($path, $stub);
    }

    protected function createMigrationStub(string $name, string $table): void
    {
        $timestamp = date('YmdHis');
        $className = "Create{$name}Table";
        $fileName  = "{$timestamp}_{$className}.php";
        $path      = APPPATH . "Database/Migrations/{$fileName}";

        $stub = <<<PHP
<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class {$className} extends Migration
{
    public function up()
    {
        \$this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            // TODO: add columns for {$table}
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        \$this->forge->addPrimaryKey('id');
        \$this->forge->createTable('{$table}');
    }

    public function down()
    {
        \$this->forge->dropTable('{$table}');
    }
}

PHP;

        $this->writeFile($path, $stub);
    }

    protected function writeFile(string $path, string $contents): void
    {
        helper('filesystem');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        write_file($path, $contents);
        CLI::write('Created: ' . str_replace(APPPATH, 'app/', $path), 'green');
    }

    protected function toSnakeCasePlural(string $name): string
    {
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));

        return $snake . (substr($snake, -1) === 's' ? '' : 's');
    }
}
