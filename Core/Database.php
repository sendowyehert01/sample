<?php

namespace Core;

use Closure;
use PDO;
use PDOStatement;

class Database
{
    private ?PDO $connection = null;
    private Closure $connector;

    public function __construct(PDO|Closure $connection)
    {
        $this->connector = $connection instanceof PDO
            ? static fn (): PDO => $connection
            : $connection;
    }

    public function query(string $sql, array $parameters = []): PDOStatement
    {
        $statement = $this->connection()->prepare($sql);
        $statement->execute($parameters);

        return $statement;
    }

    public function lastInsertId(): int
    {
        return (int) $this->connection()->lastInsertId();
    }

    private function connection(): PDO
    {
        // Open the connection only when a repository runs a query.
        if ($this->connection === null) {
            $connection = ($this->connector)();
            $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            if ($connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                $connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            }

            $this->connection = $connection;
        }

        return $this->connection;
    }
}
