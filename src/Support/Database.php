<?php
declare(strict_types=1);
namespace ImSafe\Support;
use PDO;

final class Database {
    private ?PDO $connection = null;
    public function __construct(private Config $config) {}
    public function pdo(): PDO {
        return $this->connection ??= new PDO($this->config->dbDsn(), $this->config->dbUser(), $this->config->dbPass(), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    }
}
