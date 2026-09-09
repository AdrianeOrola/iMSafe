<?php
declare(strict_types=1);
namespace ImSafe\Support;

final class Config {
    public function get(string $key, string $default = ''): string { return getenv($key) ?: $default; }
    public function dbDsn(): string { return 'mysql:host=' . $this->get('IMSAFE_DB_HOST', '127.0.0.1') . ';dbname=' . $this->get('IMSAFE_DB_NAME', 'imsafe_oop_local') . ';charset=utf8mb4'; }
    // XAMPP's default local installation exposes root with a blank password.
    // Set IMSAFE_DB_USER and IMSAFE_DB_PASS in Apache for a dedicated account.
    public function dbUser(): string { return $this->get('IMSAFE_DB_USER', 'root'); }
    public function dbPass(): string { return $this->get('IMSAFE_DB_PASS'); }
    // Development-only fallback for a fresh XAMPP install. Override this in
    // Apache before using the app anywhere shared.
    public function adminEmail(): string { return strtolower(trim($this->get('IMSAFE_ADMIN_EMAIL', 'admin@imsafe.local'))); }
    public function adminPassword(): string { return $this->get('IMSAFE_ADMIN_PASSWORD', 'imsafe-local-admin'); }
    public function isDefaultAdminPassword(): bool { return in_array($this->adminPassword(), ['', 'change-me', 'imsafe-local-admin'], true); }
    public function storagePath(): string { return $this->get('IMSAFE_STORAGE_PATH', dirname(__DIR__, 2) . '/storage'); }
    public function aiApiKey(): string { return trim($this->get('IMSAFE_AI_API_KEY', $this->get('OPENAI_API_KEY'))); }
    public function aiModel(): string { return trim($this->get('IMSAFE_AI_MODEL', 'gpt-5-mini')); }
}
