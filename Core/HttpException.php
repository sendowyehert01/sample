<?php

namespace Core;

use RuntimeException;

class HttpException extends RuntimeException
{
    public function __construct(
        private int $status,
        string $message,
        private array $errors = [],
        private array $headers = []
    ) {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function payload(): array
    {
        $payload = ['message' => $this->getMessage()];

        if ($this->errors !== []) {
            $payload['errors'] = $this->errors;
        }

        return $payload;
    }

    public function headers(): array
    {
        return $this->headers;
    }
}
