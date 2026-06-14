<?php
/*
|------------------------------------------------------------------------------
| Auth bootstrap / guard
|------------------------------------------------------------------------------
| Require this at the VERY TOP of every protected page or API (before output):
|
|   Pages:   require_once __DIR__ . '/includes/auth.php'; require_login();
|   APIs:    define('FAKTA_API', true);
|            require_once __DIR__ . '/../includes/auth.php'; require_login();
|
| Tenant scoping rule: always use current_company_id() — never trust client input.
*/

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';

$GLOBALS['fakta_db']   = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS);
$GLOBALS['fakta_auth'] = new Auth($GLOBALS['fakta_db']); // starts the session

if (!defined('FAKTA_API')) {
    define('FAKTA_API', false);
}

/** Web path to a file in the app root, regardless of which sub-dir we're in (e.g. /fakta/landing.php). */
function fakta_url(string $path): string
{
    $appRoot = str_replace('\\', '/', dirname(__DIR__));
    $docRoot = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'));
    $base    = $docRoot && str_starts_with($appRoot, $docRoot)
        ? substr($appRoot, strlen($docRoot))
        : '';
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function current_user(): ?array      { return Auth::user(); }
function current_company_id(): ?int   { return Auth::companyId(); }
function current_role(): ?string      { return Auth::role(); }

/** Up to two uppercase initials from a name (Cyrillic-safe). */
function fakta_initials(?string $name): string
{
    $parts = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);
    if (!$parts) return '?';
    if (count($parts) === 1) return mb_strtoupper(mb_substr($parts[0], 0, 2));
    return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[count($parts) - 1], 0, 1));
}

/**
 * Soft bg / readable fg pair for an initial avatar, picked deterministically
 * from the name. Mirrors AVATAR_PALETTE + avatarColor() in js/app.js.
 */
function fakta_avatar_color(?string $name): array
{
    // Must stay in sync with AVATAR_PALETTE in js/app.js (same order, same count) —
    // the modulo depends on the length, so a mismatch produces different colors.
    static $palette = [
        ['bg' => '#eff6ff', 'fg' => '#1d4ed8'], // blue
        ['bg' => '#fff7ed', 'fg' => '#c2410c'], // orange
        ['bg' => '#f0fdf4', 'fg' => '#15803d'], // green
        ['bg' => '#fdf4ff', 'fg' => '#a21caf'], // fuchsia
        ['bg' => '#fef2f2', 'fg' => '#b91c1c'], // red
        ['bg' => '#f0f9ff', 'fg' => '#0369a1'], // sky
        ['bg' => '#fefce8', 'fg' => '#a16207'], // amber
        ['bg' => '#f5f3ff', 'fg' => '#6d28d9'], // violet
    ];
    // Sum Unicode code points (NOT bytes) so this matches avatarColor() in
    // js/app.js exactly — the same name must map to the same color everywhere.
    $s = trim((string) $name);
    $chars = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $hash = 0;
    foreach ($chars as $ch) {
        $hash = ($hash + mb_ord($ch, 'UTF-8')) % count($palette);
    }
    return $palette[$hash] ?? $palette[0];
}

/** Reject the request: JSON 403 for APIs, redirect for pages. */
function fakta_deny(string $message, string $redirectTo): void
{
    if (FAKTA_API) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $message]);
    } else {
        header('Location: ' . fakta_url($redirectTo));
    }
    exit;
}

function require_login(): void
{
    if (!Auth::check()) {
        fakta_deny('Не сте најавени.', 'landing.php');
    }
}

/** Allow only the given role(s); otherwise deny. */
function require_role(string ...$roles): void
{
    require_login();
    if (!in_array(Auth::role(), $roles, true)) {
        fakta_deny('Немате дозвола за оваа акција.', 'index.php');
    }
}
