<?php

namespace Core;

use JsonException;
use stdClass;

class Request
{
    private ?array $body = null;

    public function __construct(private array $server, private string $rawBody = '')
    {
    }

    public static function fromGlobals(): self
    {
        $body = file_get_contents('php://input');

        return new self($_SERVER, $body === false ? '' : $body);
    }

    public function path(): string
    {
        return parse_url($this->server['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    }

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function all(): array
    {
        if ($this->body !== null) {
            return $this->body;
        }

        $contentType = strtolower(trim(explode(';', $this->server['CONTENT_TYPE'] ?? '')[0]));

        if ($contentType !== 'application/json') {
            throw new HttpException(415, 'Send the request body as application/json.');
        }

        try {
            $body = json_decode($this->rawBody, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new HttpException(400, 'Malformed JSON request body.');
        }

        if (!$body instanceof stdClass) {
            throw new HttpException(400, 'The request body must be a JSON object.');
        }

        return $this->body = (array) $body;
    }
}
