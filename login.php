<?php
require_once __DIR__ . '/includes/auth.php';

/** @var Auth $fakta_auth */
$auth = $GLOBALS['fakta_auth'];

// Already logged in → straight to the right home.
if (Auth::check()) {
    header('Location: ' . fakta_url(Auth::role() === 'super_admin' ? 'admin/index.php' : 'index.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!hash_equals(fakta_csrf(), (string) ($_POST['csrf'] ?? ''))) {
        $error = 'Сесијата истече. Освежи ја страницата и обиди се повторно.';
    } elseif ($email === '' || $password === '') {
        $error = 'Внесете е-пошта и лозинка.';
    } else {
        $user = $auth->login($email, $password);
        if ($user) {
            $dest = $user['role'] === 'super_admin' ? 'admin/index.php' : 'index.php';
            header('Location: ' . fakta_url($dest));
            exit;
        }
        $error = 'Погрешна е-пошта или лозинка.';
    }
}
?>
<!DOCTYPE html>
<html lang="mk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Најава — Факта</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Belt-and-suspenders: drop any leftover tenant drafts before a new login. -->
    <script>
        try {
            for (var i = sessionStorage.length - 1; i >= 0; i--) {
                var k = sessionStorage.key(i);
                if (k && k.indexOf('fakta_') === 0) sessionStorage.removeItem(k);
            }
        } catch (e) {}
    </script>
    <style>
        :root{
            --accent:#336699; --accent-hover:#2a5580; --accent-soft:#eaf1f7; --accent-border:#bcd4e6;
            --ink:#1e2a38; --ink-soft:#5b6b7c; --line:#e4eaf0; --bg:#f7f9fb; --card:#ffffff;
        }
        *{box-sizing:border-box;margin:0;padding:0}
        body{
            font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,sans-serif;color:var(--ink);
            min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;
            background:
                radial-gradient(800px 420px at 50% -10%, var(--accent-soft) 0%, rgba(234,241,247,0) 60%),
                var(--bg);
            -webkit-font-smoothing:antialiased;
        }
        a{text-decoration:none;color:inherit}
        .shell{width:100%;max-width:400px}
        .brand{display:flex;align-items:center;justify-content:center;gap:10px;font-weight:700;font-size:22px;
            letter-spacing:-.01em;margin-bottom:26px}
        .brand svg{color:var(--accent)}
        .card{background:var(--card);border:1px solid var(--line);border-radius:18px;padding:30px;
            box-shadow:0 14px 40px -22px rgba(16,33,51,.4)}
        .card h1{font-size:19px;font-weight:700;margin-bottom:4px}
        .card .sub{font-size:14px;color:var(--ink-soft);margin-bottom:24px}
        label{display:block;font-size:13.5px;font-weight:600;color:var(--ink-soft);margin-bottom:7px}
        .field{width:100%;font-size:14.5px;color:var(--ink);background:#fff;border:1px solid var(--line);
            border-radius:11px;padding:11px 13px;outline:none;transition:.15s;font-family:inherit}
        .field::placeholder{color:#9aa7b4}
        .field:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(51,102,153,.14)}
        .group{margin-bottom:17px}
        .btn-primary{width:100%;display:inline-flex;align-items:center;justify-content:center;gap:8px;
            font-size:15px;font-weight:600;padding:12px 16px;border:0;border-radius:11px;cursor:pointer;
            background:var(--accent);color:#fff;transition:.18s;font-family:inherit;margin-top:4px}
        .btn-primary:hover{background:var(--accent-hover);transform:translateY(-1px);box-shadow:0 8px 20px -8px rgba(51,102,153,.55)}
        .alert{font-size:13.5px;border-radius:11px;padding:11px 13px;margin-bottom:18px;
            background:#fdeced;border:1px solid #f3c2c6;color:#b4232c}
        .back{display:block;text-align:center;margin-top:18px;font-size:13.5px;color:var(--ink-soft)}
        .back:hover{color:var(--accent)}
    </style>
</head>
<body>

    <div class="shell">
        <a href="<?= htmlspecialchars(fakta_url('landing.php')) ?>" class="brand">
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/>
                <path d="M7 21h10"/><path d="M12 3v18"/><path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"/>
            </svg>
            Факта
        </a>

        <div class="card">
            <h1>Најава</h1>
            <p class="sub">Најавете се на вашата компанија.</p>

            <?php if ($error !== ''): ?>
            <div class="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= htmlspecialchars(fakta_url('login.php')) ?>">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(fakta_csrf()) ?>">
                <div class="group">
                    <label for="email">Е-пошта</label>
                    <input type="email" id="email" name="email" class="field" autocomplete="username" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="group">
                    <label for="password">Лозинка</label>
                    <input type="password" id="password" name="password" class="field" autocomplete="current-password" required>
                </div>
                <button type="submit" class="btn-primary">Најави се</button>
            </form>
        </div>

        <a href="<?= htmlspecialchars(fakta_url('landing.php')) ?>" class="back">← Назад кон почетна</a>
    </div>

</body>
</html>
