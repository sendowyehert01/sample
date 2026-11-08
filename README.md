# PHP CRUD API Template

An API-only PHP project using OOP controllers, repositories, constructor injection, and the Eloquent ORM. The example resource is a sample with `id`, `name`, and `description` fields. All routes are public, and responses are JSON. There are no views, no authentication, and no sessions.

Treat the `sample` resource as a worked example: keep the structure, rename it, and swap in your own fields and table.

## Requirements

- PHP 8.2 or newer with `PDO` and `pdo_mysql` enabled.
- [Composer](https://getcomposer.org/) to install the Eloquent ORM.
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
SOURCE database/schema.sql;
```

Alternatively, open and execute [database/schema.sql](database/schema.sql) in MySQL Workbench or phpMyAdmin. It creates the `crud_demo` database and a `samples` table, including the `created_at` and `updated_at` columns Eloquent maintains automatically.

## 2. Configure the connection

Edit [config.php](config.php) to match your MySQL host, port, database name, username, and password. Defaults are:

```php
'database' => [
    'driver' => 'mysql',
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'crud_demo',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
],
```

Set `password` to your actual MySQL password. Leave it empty only if that database account has no password. `bootstrap.php` hands this array straight to Eloquent's connection manager, so any key Eloquent supports works here.

## 3. Start the API

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
2. Add the new table to `database/schema.sql` and run its SQL. A new model picks up the table by naming convention, or set `protected $table` explicitly as `Sample` does.
3. Register the new validator, repository, and controller in `bootstrap.php`.
4. Add routes in `routes.php` using the same collection and `/resource/{id}` pattern, then adapt the tests for the new fields.

`Application` turns HTTP exceptions into JSON errors and logs unexpected errors. `HttpRouter` passes URL parameters to controller methods. Controllers validate input, call repositories, and return `Response::json()` or `Response::noContent()`. Repositories perform all database work through Eloquent models, so controllers never touch the connection directly.

Eloquent is booted once in `bootstrap.php` through its `Capsule` manager, which registers the connection globally. Models resolve that connection automatically, so a repository needs no constructor dependency to reach the database.

## Tests

Enable `pdo_sqlite` and run:

```sh
php tests/run.php
```

The suite uses an in-memory SQLite database and checks CRUD, JSON bodies, HTTP statuses, field validation, missing IDs, allowed methods, and error responses. It does not use your MySQL database. PHP warnings cause test failures.

## Project structure

| Path | Purpose |
| --- | --- |
| `public/index.php` | Starts the application. |
| `autoload.php` | Loads Composer's autoloader plus classes from paths matching their namespaces. |
| `bootstrap.php` | Boots Eloquent and wires services in the container. |
| `config.php` | MySQL connection settings. |
| `routes.php` | API routes mapped to controller methods. |
| `Core/` | Application, router, request/response objects, container, and HTTP errors. |
| `Http/Controllers/` | API information and sample CRUD handlers. |
| `Http/Validation/` | Sample input validation. |
| `Models/` | Eloquent models. |
| `Repositories/` | Database queries through Eloquent. |
| `database/schema.sql` | MySQL database and samples table setup. |
| `tests/run.php` | API integration tests. |

If the API reports a server error, check the MySQL credentials in `config.php` and that the schema has been created. For a missing PDO driver, run `php --ini`, enable `extension=pdo_mysql` in the loaded configuration, and restart the PHP server.
