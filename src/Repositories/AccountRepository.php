<?php
declare(strict_types=1);
namespace ImSafe\Repositories;

use ImSafe\Support\Database;

final class AccountRepository {
    public function __construct(private Database $database) {}
    public function create(string $name, string $email, string $password): array {
        $stmt = $this->database->pdo()->prepare('INSERT INTO local_users (display_name,email,password_hash) VALUES (?,?,?)');
        $stmt->execute([$name, strtolower($email), password_hash($password, PASSWORD_DEFAULT)]);
        return ['id' => (int)$this->database->pdo()->lastInsertId(), 'name' => $name, 'email' => strtolower($email)];
    }
    public function authenticate(string $email, string $password): ?array {
        $stmt = $this->database->pdo()->prepare('SELECT id,display_name,email,password_hash FROM local_users WHERE email=? LIMIT 1');
        $stmt->execute([strtolower($email)]); $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) return null;
        $this->database->pdo()->prepare('UPDATE local_users SET last_signed_in=NOW() WHERE id=?')->execute([$user['id']]);
        return ['id' => (int)$user['id'], 'name' => $user['display_name'], 'email' => $user['email']];
    }
}
