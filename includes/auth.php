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
