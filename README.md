# PHP CRUD API Template

An API-only PHP project using OOP controllers, repositories, constructor injection, the Eloquent ORM, and Phinx migrations. The example resource is a sample with `id`, `name`, and `description` fields. All routes are public, and responses are JSON. There are no views, no authentication, and no sessions.

Phinx is maintained by CakePHP and uses its database library. Eloquent handles application queries; Phinx handles schema changes. This project uses the standalone `robmorgan/phinx` package, so it does not need the CakePHP application framework or the `cakephp/migrations` plugin.

Treat the `sample` resource as a worked example: keep the structure, rename it, and swap in your own fields and table.

## Requirements

- 64-bit PHP 8.2 or newer with `PDO` and `pdo_mysql` enabled.
- [Composer](https://getcomposer.org/) to install Eloquent and Phinx.
- A running MySQL 8.x server or a compatible MariaDB server.
- `pdo_sqlite` to run the automated tests.

There is no frontend build step.

Open a terminal in this directory, where `composer.json` and `config.php` are located, and install the dependencies:

```sh
composer install
php -v
php --ri pdo_mysql
```

If PHP is not found on Windows, add its installation directory to `PATH`, such as `C:\php` or `C:\xampp\php`.

## 1. Set up the database

Start MySQL and connect from the project directory:

```sh
mysql -u root -p
```

If `mysql` is not on `PATH`, run its executable directly in PowerShell, adjusting the path for your installation:

```powershell
& 'C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe' -u root -p
```

At the MySQL prompt, run:

```sql
CREATE DATABASE IF NOT EXISTS crud_demo
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
```

Alternatively, execute this statement in HeidiSQL, MySQL Workbench, or phpMyAdmin. Phinx creates the tables in step 3; schema changes are defined in `database/migrations`.

## 2. Configure the connection

For a new checkout, copy `.env.example` to `.env` and edit the connection settings:

```dotenv
DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=crud_demo
DB_USERNAME=root
DB_PASSWORD=your_mysql_password
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
DB_PREFIX=
```

Set `DB_PASSWORD` to your actual MySQL password. Leave it empty only if that database account has no password. Both the API and migration CLI use [config.php](config.php), which loads `.env` and supplies their shared database settings and table prefix. Environment values take precedence over the defaults in that file. `.env` is excluded from Git.

## 3. Run migrations

From the project directory, run:

```sh
composer migrate
composer migrate:status
```

The initial migration creates `samples`, including Eloquent's nullable `created_at` and `updated_at` columns. Phinx records applied versions in `phinxlog` and skips them on later runs. `DB_PREFIX` applies to resource tables and the migration history table.

To create another migration:

```sh
composer make:migration -- add_sample_status
```

Use snake_case migration names. Phinx adds a timestamp prefix to create a filename such as `20261108120000_add_sample_status.php` and uses the PHP class `AddSampleStatus` to load it. The existing `20261108000000_create_samples_table.php` follows the same filename format.

Edit the generated PHP class in `database/migrations`, then run `composer migrate`. Use Phinx's `change()` method for automatically reversible schema operations, or `up()` and `down()` when you need explicit behavior. See the [Phinx migration documentation](https://book.cakephp.org/phinx/0/en/migrations.html).

To reverse the most recently applied migration:

```sh
composer migrate:rollback
```

Rolling back `CreateSamplesTable` drops the `samples` table and its data. Commands accept Phinx options after `--`, such as `composer migrate -- --dry-run`.

If you already have a `samples` table, first verify its structure matches the [initial migration](database/migrations/20261108000000_create_samples_table.php). Record just that version as applied without executing its table creation:

```sh
composer migrate -- --target=20261108000000 --fake
composer migrate:status
```

Future migration runs then apply only newer versions. The baseline command preserves existing sample data.

## 4. Start the API

Run this command from the project directory:

```sh
php -S 127.0.0.1:8080 -t public public/index.php
```

Keep the terminal running. Press `Ctrl+C` to stop the development server. The base URL is **http://127.0.0.1:8080**.

`GET /` returns API information and works without a database connection:

```json
{"message":"CRUD API","resource":"/samples"}
```

## CRUD endpoints

| Method | Path | Result |
| --- | --- | --- |
| GET | `/samples` | `200`: list all samples, ordered by ID. |
| GET | `/samples/{id}` | `200`: read one sample. |
| POST | `/samples` | `201`: create a sample; `Location` contains its URL. |
| PUT | `/samples/{id}` | `200`: update the sample. |
| PATCH | `/samples/{id}` | `200`: update the sample. |
| DELETE | `/samples/{id}` | `204`: delete a sample; the response body is empty. |

Send a JSON object with `Content-Type: application/json` for POST, PUT, and PATCH:

```json
{"name":"My first sample","description":"Optional details"}
```

`name` is required for creation and both update methods. It must be a string of 1 to 255 bytes after trimming. `description` is optional: omit it or send `null` for no description, otherwise it must be a string of up to 2000 bytes. An omitted, `null`, or blank description is stored as `null`. Both update methods replace the whole record, so a field left out of a PUT or PATCH body is cleared. The database assigns `id`.

Create, read, and update responses wrap the record in `data`:

```json
{"data":{"id":1,"name":"My first sample","description":null}}
```

List responses use `{"data":[...]}`; an empty table returns `{"data":[]}`. Replace `{id}` with a positive integer.

## Example requests

These curl examples work in Bash, WSL, Linux, and macOS. Replace `1` with the ID returned by the create request:

```sh
curl -i http://127.0.0.1:8080/samples
curl -i -X POST http://127.0.0.1:8080/samples -H 'Content-Type: application/json' -d '{"name":"My first sample"}'
curl -i http://127.0.0.1:8080/samples/1
curl -i -X PUT http://127.0.0.1:8080/samples/1 -H 'Content-Type: application/json' -d '{"name":"Updated sample","description":"With details"}'
curl -i -X PATCH http://127.0.0.1:8080/samples/1 -H 'Content-Type: application/json' -d '{"name":"Another update"}'
curl -i -X DELETE http://127.0.0.1:8080/samples/1
```

On Windows PowerShell, use `Invoke-RestMethod`. This example uses the actual created ID:

```powershell
$samplesUrl = 'http://127.0.0.1:8080/samples'
Invoke-RestMethod -Uri $samplesUrl
$created = Invoke-RestMethod -Uri $samplesUrl -Method Post -ContentType 'application/json' -Body (@{ name = 'My first sample' } | ConvertTo-Json)
$sampleUrl = $samplesUrl + '/' + $created.data.id
Invoke-RestMethod -Uri $sampleUrl
Invoke-RestMethod -Uri $sampleUrl -Method Put -ContentType 'application/json' -Body (@{ name = 'Updated sample'; description = 'With details' } | ConvertTo-Json)
Invoke-RestMethod -Uri $sampleUrl -Method Patch -ContentType 'application/json' -Body (@{ name = 'Another update' } | ConvertTo-Json)
Invoke-RestMethod -Uri $sampleUrl -Method Delete
```

## Errors

| Status | Meaning |
| --- | --- |
| 400 | Malformed JSON or a JSON body that is not an object. |
| 404 | Missing route, invalid record ID, or missing record. |
| 405 | Method not supported for that route; the `Allow` header lists supported methods. |
| 415 | The request body was sent without `Content-Type: application/json`. |
| 422 | The `name` or `description` field failed validation. |
| 500 | An unexpected server error; check the server terminal or PHP error log. |

For example, submitting `{"name":""}` returns `422`:

```json
{
  "message": "Validation failed.",
  "errors": {
    "name": ["The name field must be a non-empty string of up to 255 bytes."]
  }
}
```

When several fields fail at once, every failing field appears as a key in `errors`:

```json
{
  "message": "Validation failed.",
  "errors": {
    "name": ["The name field must be a non-empty string of up to 255 bytes."],
    "description": ["The description field must be a string of up to 2000 bytes."]
  }
}
```

## Use this as a CRUD template

1. Copy `Models/Sample.php`, `SampleController`, `SampleRepository`, and `SampleValidator` for your new resource, then rename the classes and update their fields.
2. Generate a migration with `composer make:migration -- create_widgets_table`, define the new table, and run `composer migrate`. A new model picks up the table by naming convention, or set `protected $table` explicitly as `Sample` does.
3. Register the new validator, repository, and controller in `bootstrap.php`.
4. Add routes in `routes.php` using the same collection and `/resource/{id}` pattern, then adapt the tests for the new fields.

`Application` turns HTTP exceptions into JSON errors and logs unexpected errors. `HttpRouter` passes URL parameters to controller methods. Controllers validate input, call repositories, and return `Response::json()` or `Response::noContent()`. Repositories perform all database work through Eloquent models, so controllers never touch the connection directly.

Eloquent is booted once in `bootstrap.php` through its `Capsule` manager, which registers the connection globally. Models resolve that connection automatically, so a repository needs no constructor dependency to reach the database.

## Tests

Enable `pdo_sqlite` and run:

```sh
composer test
```

The suites use isolated SQLite databases, including a temporary file to verify that Phinx and Eloquent share the same connection settings. Migration tests check table creation, repeat runs, rollback, table prefixes, baselining an existing schema, and discovery of migrations generated from snake_case names. API tests build their schema from the real migrations, then check CRUD, JSON bodies, HTTP statuses, field validation, missing IDs, allowed methods, and error responses. They do not use your MySQL database. PHP warnings cause test failures. You can also run `php tests/migrations.php` or `php tests/run.php` separately.

## Project structure

| Path | Purpose |
| --- | --- |
| `public/index.php` | Starts the application. |
| `bootstrap.php` | Boots Eloquent and wires services in the container. |
| `config.php` | Loads `.env` and supplies shared database settings. |
| `phinx.php` | Phinx configuration using the API's database settings. |
| `bin/make_migration.php` | Creates migrations from snake_case names. |
| `routes.php` | API routes mapped to controller methods. |
| `Core/` | Application, router, request/response objects, container, and HTTP errors. |
| `Http/Controllers/` | API information and sample CRUD handlers. |
| `Http/Validation/` | Sample input validation. |
| `Models/` | Eloquent models. |
| `Repositories/` | Database queries through Eloquent. |
| `database/migrations/` | Versioned database schema changes. |
| `tests/migrations.php` | Migration lifecycle and baseline tests. |
| `tests/run.php` | API integration tests. |

If the API reports a server error, check the MySQL credentials in `.env` and that migrations have been run. For a missing PDO driver, run `php --ini`, enable `extension=pdo_mysql` in the loaded configuration, and restart the PHP server.
