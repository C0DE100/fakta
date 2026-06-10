<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/User.php';

/**
 * Session-based authentication.
 *
 * The logged-in user is stored in $_SESSION['user'] as:
 *   ['id', 'company_id', 'name', 'email', 'role']
 *
 * company_id is the single source of truth for tenant scoping — every data
 * query must filter by Auth::companyId(), never by client-supplied input.
 */
class Auth
{
    private User $users;

    public function __construct(Database $db)
    {
        $this->users = new User($db);
        self::startSession();
    }

    /** Start a hardened session exactly once. */
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    /** Attempt login. Returns the user array on success, null on failure. */
    public function login(string $email, string $password): ?array
    {
        $user = $this->users->getByEmail($email);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        // Prevent session fixation.
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id'           => (int) $user['id'],
            'company_id'   => $user['company_id'] !== null ? (int) $user['company_id'] : null,
            'company_name' => $user['company_name'] ?? null,
            'name'         => $user['name'],
            'email'        => $user['email'],
            'role'         => $user['role'],
        ];

        return $_SESSION['user'];
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']['id']);
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function companyId(): ?int
    {
        return $_SESSION['user']['company_id'] ?? null;
    }

    public static function role(): ?string
    {
        return $_SESSION['user']['role'] ?? null;
    }
}
