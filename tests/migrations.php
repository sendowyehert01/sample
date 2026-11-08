<?php

declare(strict_types=1);

use Core\MakeMigrationCommand;
use Phinx\Config\Config;
use Phinx\Console\PhinxApplication;
use Phinx\Migration\Manager;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Repositories\SampleRepository;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Tester\CommandTester;

require_once dirname(__DIR__) . '/vendor/autoload.php';

final class MigrationTests
{
    private const INITIAL_VERSION = 20261108000000;
    private array $config;
    private int $assertions = 0;

    public function __construct()
    {
        $this->config = require dirname(__DIR__) . '/phinx.php';
    }

    public function run(): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            $this->lifecycle();
            $this->tablePrefix();
            $this->existingSchema();
            $this->sharedConfiguration();
            $this->migrationNames();
        } finally {
            restore_error_handler();
        }

        echo "PASS: migration schema, repeat runs, rollback, and reapply\n";
        echo "PASS: prefixed tables and existing-schema baseline\n";
        echo "PASS: shared Phinx/Eloquent database configuration\n";
        echo "PASS: snake_case migration generation and discovery\n";
        echo "{$this->assertions} migration assertions passed.\n";
    }

    private function manager(PDO $connection, string $prefix = ''): Manager
    {
        $config = $this->config;
        $config['environments']['default_migration_table'] = $prefix . 'phinxlog';
        $config['environments']['development'] = [
            'name' => ':memory:',
            'connection' => $connection,
            'table_prefix' => $prefix,
        ];

        return new Manager(new Config($config), new ArrayInput([]), new NullOutput());
    }

    private function lifecycle(): void
    {
        $connection = new PDO('sqlite::memory:');
        $connection->exec('CREATE TABLE unrelated (value TEXT NOT NULL)');
        $connection->exec("INSERT INTO unrelated (value) VALUES ('Preserve me')");
        $manager = $this->manager($connection);
        $manager->migrate('development');

        $columns = $connection->query('PRAGMA table_info(samples)')->fetchAll(PDO::FETCH_ASSOC);
        $this->expect(array_column($columns, 'name') === ['id', 'name', 'description', 'created_at', 'updated_at'], 'The migration must create the fields required by Eloquent and the API.');
        $this->expect((int) $columns[0]['pk'] === 1 && (int) $columns[1]['notnull'] === 1, 'Samples must have a primary key and a required name.');
        $this->expect((int) $columns[2]['notnull'] === 0 && (int) $columns[3]['notnull'] === 0 && (int) $columns[4]['notnull'] === 0, 'Description and timestamps must allow null.');
        $this->expect((int) $connection->query('SELECT version FROM phinxlog')->fetchColumn() === self::INITIAL_VERSION, 'Applied migrations must be recorded.');

        $connection->exec("INSERT INTO samples (name, description) VALUES ('Keep me', 'Existing data')");
        $manager->migrate('development');
        $this->expect($connection->query('SELECT name FROM samples')->fetchColumn() === 'Keep me', 'A repeat migration run must preserve data.');
        $this->expect((int) $connection->query('SELECT COUNT(*) FROM phinxlog')->fetchColumn() === 1, 'A repeat run must not duplicate migration history.');

        $manager->rollback('development', 0);
        $this->expect(!$manager->getEnvironment('development')->getAdapter()->hasTable('samples'), 'Rollback must reverse the table creation.');
        $this->expect((int) $connection->query('SELECT COUNT(*) FROM phinxlog')->fetchColumn() === 0, 'Rollback must remove the applied version.');
        $this->expect($connection->query('SELECT value FROM unrelated')->fetchColumn() === 'Preserve me', 'Rollback must preserve unrelated tables and data.');

        $manager->migrate('development');
        $this->expect($manager->getEnvironment('development')->getAdapter()->hasTable('samples'), 'A rolled-back migration must be reusable.');
    }

    private function tablePrefix(): void
    {
        $connection = new PDO('sqlite::memory:');
        $manager = $this->manager($connection, 'app_');
        $manager->migrate('development');
        $tables = $connection->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);

        $this->expect(in_array('app_samples', $tables, true), 'Migrations must honor the table prefix used by Eloquent.');
        $this->expect(in_array('app_phinxlog', $tables, true), 'Migration history must honor the table prefix too.');
        $this->expect(!in_array('samples', $tables, true), 'A prefixed environment must not create the unprefixed resource table.');
        $manager->rollback('development', 0);
        $this->expect(!$manager->getEnvironment('development')->getAdapter()->hasTable('samples'), 'Rollback must honor the table prefix too.');
    }

    private function existingSchema(): void
    {
        $connection = new PDO('sqlite::memory:');
        $manager = $this->manager($connection);
        $manager->migrate('development');
        $connection->exec("INSERT INTO samples (name) VALUES ('Imported sample')");
        $connection->exec('DELETE FROM phinxlog');

        // Simulate the legacy SQL import: the table exists without migration history.
        $manager->migrate('development', self::INITIAL_VERSION, true);
        $this->expect($connection->query('SELECT name FROM samples')->fetchColumn() === 'Imported sample', 'Baselining an existing schema must preserve its data.');
        $this->expect((int) $connection->query('SELECT version FROM phinxlog')->fetchColumn() === self::INITIAL_VERSION, 'A baseline must record the initial version without creating its table again.');
        $manager->migrate('development');
        $this->expect($connection->query('SELECT name FROM samples')->fetchColumn() === 'Imported sample', 'Normal migrations must work after an existing schema is baselined.');
    }

    private function sharedConfiguration(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'crud-migrations-');

        if ($file === false) {
            throw new RuntimeException('Could not create the temporary migration database.');
        }

        $previous = [];
        $overrides = ['DB_DRIVER' => 'sqlite', 'DB_DATABASE' => $file, 'DB_PREFIX' => 'shared_'];

        foreach ($overrides as $key => $value) {
            $previous[$key] = ['exists' => array_key_exists($key, $_ENV), 'value' => $_ENV[$key] ?? null];
            $_ENV[$key] = $value;
        }

        try {
            $config = require dirname(__DIR__) . '/phinx.php';
            $database = (require dirname(__DIR__) . '/config.php')['database'];
        } finally {
            foreach ($previous as $key => $value) {
                if ($value['exists']) {
                    $_ENV[$key] = $value['value'];
                } else {
                    unset($_ENV[$key]);
                }
            }
        }

        try {
            $manager = new Manager(new Config($config), new ArrayInput([]), new NullOutput());
            $manager->migrate('development');
            $connection = new PDO('sqlite:' . $file);
            $tables = $connection->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
            $this->expect(in_array('shared_samples', $tables, true), 'Phinx must use the same SQLite filename and table prefix as Eloquent.');
            $this->expect(in_array('shared_phinxlog', $tables, true), 'The shared configuration must prefix migration history.');

            $capsule = new Capsule();
            $capsule->addConnection($database);
            $resolver = Model::getConnectionResolver();
            Model::setConnectionResolver($capsule->getDatabaseManager());

            try {
                (new SampleRepository())->create('Shared connection', null);
                $this->expect($connection->query('SELECT name FROM shared_samples')->fetchColumn() === 'Shared connection', 'Eloquent must write to the table created by Phinx using the shared configuration.');
            } finally {
                if ($resolver === null) {
                    Model::unsetConnectionResolver();
                } else {
                    Model::setConnectionResolver($resolver);
                }
                $capsule->getDatabaseManager()->disconnect();
            }
        } finally {
            unset($manager, $connection, $capsule);
            gc_collect_cycles();

            foreach ([$file, $file . '.sqlite3'] as $temporaryFile) {
                if (is_file($temporaryFile)) {
                    unlink($temporaryFile);
                }
            }
        }
    }

    private function migrationNames(): void
    {
        $directory = sys_get_temp_dir() . '/crud-migration-names-' . bin2hex(random_bytes(8));
        mkdir($directory);

        try {
            $config = $this->config;
            $config['paths']['migrations'] = $directory;
            $config['environments']['default_migration_table'] = 'phinxlog';
            $config['environments']['development'] = [
                'name' => ':memory:',
                'connection' => new PDO('sqlite::memory:'),
            ];
            $phinxConfig = new Config($config);
            $command = new MakeMigrationCommand();
            $command->setConfig($phinxConfig);
            $application = new PhinxApplication();
            $application->add($command);
            $tester = new CommandTester($command);
            $tester->execute(['name' => 'create_snake_case_probe_table'], ['interactive' => false]);

            $this->expect($tester->getStatusCode() === 0, 'Creating a migration from a snake_case name must succeed.');
            $files = glob($directory . '/*_create_snake_case_probe_table.php');
            $this->expect(count($files) === 1, 'The generator must create a timestamped snake_case filename.');
            $manager = new Manager($phinxConfig, new ArrayInput([]), new NullOutput());
            $manager->migrate('development');
            $this->expect(count($manager->getEnvironment('development')->getVersions()) === 1, 'Phinx must discover and apply the generated migration.');

            foreach (['create_snake_case_probe_table', 'CreateSnakeCaseProbeTable'] as $name) {
                $rejected = false;

                try {
                    $tester->execute(['name' => $name], ['interactive' => false]);
                } catch (InvalidArgumentException $error) {
                    $rejected = str_contains($error->getMessage(), 'already exists');
                }

                $this->expect($rejected, 'Duplicate snake_case and PascalCase names must resolve to the same migration.');
            }

            $rejected = false;

            try {
                $tester->execute(['name' => '../bad_migration_name'], ['interactive' => false]);
            } catch (InvalidArgumentException $error) {
                $rejected = str_contains($error->getMessage(), 'snake_case');
            }

            $this->expect($rejected, 'Invalid names must produce a useful naming error.');
            $this->expect(count(glob($directory . '/*.php')) === 1, 'Rejected names must not create additional migration files.');
        } finally {
            foreach (glob($directory . '/*.php') as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    private function expect(bool $condition, string $message): void
    {
        $this->assertions++;

        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}

try {
    (new MigrationTests())->run();
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
