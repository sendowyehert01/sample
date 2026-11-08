<?php

namespace Core;

class Response
{
    private function __construct(
        private string $body,
        private int $status,
        private array $headers = []
    ) {
        $this->headers = array_merge(['Content-Type' => 'application/json; charset=utf-8'], $headers);
    }

    public static function json(array $data, int $status = 200, array $headers = []): self
    {
        return new self(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $status,
            $headers
        );
    }

    public static function noContent(): self
    {
        return new self('', 204);
    }

    public function body(): string
    {
        return $this->body;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        echo $this->body;
    }
}
