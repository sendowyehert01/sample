<?php

use Core\Application;
use Core\Request;
use Core\Response;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;

require_once dirname(__DIR__) . '/autoload.php';

class ApiTests
{
    private ConnectionResolverInterface $database;
    private ConnectionResolverInterface $unreachable;
    private int $assertions = 0;

    public function __construct()
    {
        $this->database = $this->connect();
        $this->database->connection()->getSchemaBuilder()->create('samples', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // A separate empty database, so queries fail the way an unreachable server would.
        $this->unreachable = $this->connect();
    }

    private function connect(): ConnectionResolverInterface
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);

        return $capsule->getDatabaseManager();
    }

    public function run(): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            $this->routing();
            $this->crud();
            $this->validation();
            $this->jsonRequests();
            $this->serverErrors();
            $this->expect(session_status() === PHP_SESSION_NONE, 'The API must not start a session.');
        } finally {
            restore_error_handler();
        }

        echo "PASS: JSON routes and method handling\n";
        echo "PASS: create, list, read, PUT/PATCH update, and delete\n";
        echo "PASS: field validation and missing records\n";
        echo "PASS: malformed JSON and content types\n";
        echo "PASS: JSON server errors and no sessions\n";
        echo "{$this->assertions} assertions passed.\n";
    }

    private function routing(): void
    {
        $response = $this->request('GET', '/', databaseEnabled: false);
        $this->expect($response->status() === 200, 'The API root should work without a database connection.');
        $this->expect($this->json($response) === ['message' => 'CRUD API', 'resource' => '/samples'], 'The root should describe the API as JSON.');

        foreach (['/missing', '/login', '/register', '/sessions', '/about', '/contact', '/sample?id=1', '/samples/create', '/samples/edit'] as $uri) {
            $response = $this->request('GET', $uri, databaseEnabled: false);
            $this->expect($response->status() === 404, 'Removed or unknown routes should return 404.');
            $this->expect(isset($this->json($response)['message']), 'A missing route should have a JSON error message.');
            $this->expect(!isset($response->headers()['Location']), 'A missing route must not redirect.');
        }

        foreach ([
            ['DELETE', '/samples', 'GET, POST'],
            ['PUT', '/samples', 'GET, POST'],
            ['POST', '/samples/1', 'GET, PUT, PATCH, DELETE'],
            ['PATCH', '/', 'GET'],
        ] as [$method, $uri, $allowed]) {
            $response = $this->request($method, $uri, databaseEnabled: false);
            $this->expect($response->status() === 405, 'Unsupported methods should return 405.');
            $this->expect(($response->headers()['Allow'] ?? '') === $allowed, 'A 405 should list the allowed methods.');
            $this->expect($this->json($response)['message'] === 'Method not allowed.', 'A 405 should use a JSON error.');
        }
    }

    private function crud(): void
    {
        $response = $this->request('GET', '/samples');
        $this->expect($response->status() === 200 && $this->json($response) === ['data' => []], 'A fresh database should return an empty list.');

        $response = $this->request('POST', '/samples', ['name' => '  My first sample  ']);
        $sample = $this->json($response)['data'];
        $id = $sample['id'];
        $this->expect($response->status() === 201, 'Creating a sample should return 201.');
        $this->expect(is_int($id) && $sample['name'] === 'My first sample', 'The created sample should have a numeric ID and trimmed name.');
        $this->expect($sample['description'] === null, 'An omitted description should be stored as null.');
        $this->expect(($response->headers()['Location'] ?? '') === '/samples/' . $id, 'Creation should return the resource URL.');
        $this->expect(array_keys($sample) === ['id', 'name', 'description'], 'A sample should contain just id, name, and description.');

        $response = $this->request('GET', '/samples/' . $id . '?example=1');
        $this->expect($response->status() === 200 && $this->json($response)['data'] === $sample, 'A created sample should be retrievable by URL ID.');

        $response = $this->request('POST', '/samples', ['name' => "Robert'); DROP TABLE samples; --", 'description' => 'Has a description']);
        $second = $this->json($response)['data'];
        $this->expect($second['name'] === "Robert'); DROP TABLE samples; --", 'Parameterized inserts should preserve punctuation as data.');
        $this->expect($second['description'] === 'Has a description', 'A provided description should be stored.');
        $response = $this->request('GET', '/samples/');
        $this->expect($this->json($response)['data'] === [$sample, $second], 'The list should include all records in ID order.');

        $response = $this->request('PUT', '/samples/' . $id, ['name' => 'Updated by PUT', 'description' => 'From PUT']);
        $this->expect($response->status() === 200 && $this->json($response)['data'] === ['id' => $id, 'name' => 'Updated by PUT', 'description' => 'From PUT'], 'PUT should update the sample.');
        $response = $this->request('PATCH', '/samples/' . $id, ['name' => 'Updated by PATCH', 'id' => 999]);
        $this->expect($this->json($response)['data'] === ['id' => $id, 'name' => 'Updated by PATCH', 'description' => null], 'PATCH should replace the fields without changing the resource ID.');
        $this->expect($this->json($this->request('GET', '/samples/' . $id))['data']['name'] === 'Updated by PATCH', 'Updates should persist in the database.');

        $response = $this->request('DELETE', '/samples/' . $id);
        $this->expect($response->status() === 204 && $response->body() === '', 'Deletion should return 204 with an empty body.');
        $response = $this->request('GET', '/samples/' . $id);
        $this->expect($response->status() === 404 && $this->json($response)['message'] === 'Sample not found.', 'Deleted records should return 404.');
        $this->expect($this->request('DELETE', '/samples/' . $id)->status() === 404, 'Deleting a missing record should return 404.');
        $this->expect($this->json($this->request('GET', '/samples'))['data'] === [$second], 'Deleting one record should preserve the others.');
    }

    private function validation(): void
    {
        $before = $this->json($this->request('GET', '/samples'))['data'];

        foreach ([[], ['name' => null], ['name' => 123], ['name' => false], ['name' => []], ['name' => (object) []], ['name' => ''], ['name' => '   '], ['name' => str_repeat('x', 256)]] as $body) {
            $response = $this->request('POST', '/samples', $body);
            $payload = $this->json($response);
            $this->expect($response->status() === 422, 'Invalid name fields should return 422.');
            $this->expect($payload['message'] === 'Validation failed.' && isset($payload['errors']['name'][0]), 'Validation errors should identify the name field.');
        }

        foreach ([123, false, [], (object) [], str_repeat('x', 2001)] as $description) {
            $response = $this->request('POST', '/samples', ['name' => 'Valid name', 'description' => $description]);
            $payload = $this->json($response);
            $this->expect($response->status() === 422, 'Invalid description fields should return 422.');
            $this->expect(isset($payload['errors']['description'][0]), 'Validation errors should identify the description field.');
        }

        $this->expect($this->json($this->request('GET', '/samples'))['data'] === $before, 'Invalid creation must not insert records.');
        $id = $before[0]['id'];

        foreach (['PUT', 'PATCH'] as $method) {
            $response = $this->request($method, '/samples/' . $id, ['name' => '', 'description' => 'Still invalid']);
            $this->expect($response->status() === 422, 'Invalid updates should return 422.');
            $this->expect($this->json($this->request('GET', '/samples/' . $id))['data'] === $before[0], 'Invalid updates must preserve the stored record.');
        }

        $response = $this->request('PUT', '/samples/' . $id, ['name' => str_repeat('x', 255), 'description' => str_repeat('y', 2000)]);
        $this->expect($response->status() === 200, 'Fields at the maximum length should be accepted.');
        $this->expect(strlen($this->json($response)['data']['name']) === 255, 'The maximum-length name should be retained.');

        foreach (['0', '-1', 'invalid', '1.5', '999999', '999999999999999999999999', '%31%20OR%201%3D1'] as $id) {
            foreach (['GET', 'PUT', 'PATCH', 'DELETE'] as $method) {
                $response = $this->request($method, '/samples/' . $id, ['name' => 'No record']);
                $this->expect($response->status() === 404, 'Invalid IDs and missing records should return 404.');
                $this->expect($this->json($response)['message'] === 'Sample not found.', 'Missing records should have a consistent error message.');
            }
        }
    }

    private function jsonRequests(): void
    {
        foreach (['', '{', '{"name":', '[]', 'null', 'false', '0', '"text"'] as $rawBody) {
            $response = $this->request('POST', '/samples', rawBody: $rawBody);
            $this->expect($response->status() === 400, 'Malformed JSON or a non-object root should return 400.');
            $this->expect(isset($this->json($response)['message']), 'Malformed JSON should produce a JSON error.');
        }

        foreach (['', 'text/plain', 'application/x-www-form-urlencoded'] as $contentType) {
            $response = $this->request('POST', '/samples', ['name' => 'Wrong content type'], contentType: $contentType);
            $this->expect($response->status() === 415, 'A non-JSON content type should return 415.');
            $this->expect($this->json($response)['message'] === 'Send the request body as application/json.', 'A content-type error should explain the expected format.');
        }

        $response = $this->request('POST', '/samples', ['name' => '<b>JSON text</b>', '_method' => 'DELETE'], contentType: 'Application/JSON; charset=utf-8');
        $this->expect($response->status() === 201, 'JSON charset parameters should be accepted and form method overrides should be ignored.');
        $this->expect($this->json($response)['data']['name'] === '<b>JSON text</b>', 'JSON data should retain its original text.');
    }

    private function serverErrors(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'crud-api-test-');
        $previousLog = ini_get('error_log');
        ini_set('error_log', $log);

        try {
            $response = $this->request('GET', '/samples', databaseEnabled: false);
            $this->expect($response->status() === 500, 'Unexpected database errors should return 500.');
            $this->expect($this->json($response) === ['message' => 'Internal server error.'], 'Server errors must not expose exception details in the API response.');
            $this->expect(str_contains(file_get_contents($log), 'no such table: samples'), 'Unexpected errors should be logged for debugging.');
        } finally {
            ini_set('error_log', $previousLog);
            unlink($log);
        }
    }

    private function request(
        string $method,
        string $uri,
        array $body = [],
        ?string $rawBody = null,
        string $contentType = 'application/json',
        bool $databaseEnabled = true
    ): Response {
        $container = require dirname(__DIR__) . '/bootstrap.php';
        $request = new Request(
            ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'CONTENT_TYPE' => $contentType],
            $rawBody ?? json_encode((object) $body, JSON_THROW_ON_ERROR)
        );

        $container->bind(Request::class, static fn (): Request => $request);

        // bootstrap.php points Eloquent at MySQL; every request runs against SQLite instead.
        $resolver = Model::getConnectionResolver();
        Model::setConnectionResolver($databaseEnabled ? $this->database : $this->unreachable);

        try {
            $response = $container->resolve(Application::class)->handle();
        } finally {
            Model::setConnectionResolver($resolver);
        }
        $this->expect(($response->headers()['Content-Type'] ?? '') === 'application/json; charset=utf-8', 'All API responses should use the JSON content type.');
        $this->expect(!isset($response->headers()['Set-Cookie']), 'The API should not return session cookies.');

        return $response;
    }

    private function json(Response $response): array
    {
        return json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
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
    (new ApiTests())->run();
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
