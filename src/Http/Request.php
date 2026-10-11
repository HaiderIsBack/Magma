<?php

namespace Magma\Http;

final class Request
{
    private function __construct(
        private readonly string $method,
        private readonly string $uri,
        private readonly array $queryParams,
        private readonly array $headers,
        private readonly string $body
    ) {
    }

    public static function fromGlobals(): self
    {
        $headers = function_exists('getallheaders')
            ? getallheaders()
            : [];

        // Fallback for environments where getallheaders() is unavailable.
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace(
                    ' ',
                    '-',
                    ucwords(strtolower(str_replace('_', ' ', substr($key, 5))))
                );

                $headers[$name] ??= $value;
            }
        }

        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] ??= $_SERVER['CONTENT_TYPE'];
        }

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $_SERVER['REQUEST_URI'] ?? '/',
            $_GET,
            $headers,
            file_get_contents('php://input') ?: ''
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->queryParams[$key] ?? $default;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return (string) $value;
            }
        }

        return $default;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function json(): array
    {
        if (trim($this->body) === '') {
            return [];
        }

        $data = json_decode(
            $this->body,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (!is_array($data)) {
            throw new \UnexpectedValueException(
                'The JSON request body must be an object or array.'
            );
        }

        return $data;
    }
}
