<?php
require_once __DIR__ . '/includes/auth.php';

// Logged-in users don't need the marketing page — send them to their app.
if (Auth::check()) {
    header('Location: ' . fakta_url(Auth::role() === 'super_admin' ? 'admin/index.php' : 'index.php'));
    exit;
}
$loginUrl = fakta_url('login.php');
?>
<!DOCTYPE html>
<html lang="mk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Факта — Фактури и документи без мака</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{
            --accent:#336699; --accent-hover:#2a5580; --accent-soft:#eaf1f7; --accent-border:#bcd4e6;
            --ink:#1e2a38; --ink-soft:#5b6b7c; --line:#e4eaf0; --bg:#f7f9fb; --card:#ffffff;
        }
        *{box-sizing:border-box;margin:0;padding:0}
        html,body{height:100%}
        body{
            font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,sans-serif;
            color:var(--ink);
            background:
                radial-gradient(900px 480px at 50% -8%, var(--accent-soft) 0%, rgba(234,241,247,0) 60%),
                var(--bg);
            -webkit-font-smoothing:antialiased;
            min-height:100%;
            display:flex;flex-direction:column;
        }
        a{text-decoration:none;color:inherit}
        .wrap{width:100%;max-width:1080px;margin:0 auto;padding:0 24px}

        /* Top bar */
        .topbar{display:flex;align-items:center;justify-content:space-between;padding:22px 0}
        .brand{display:flex;align-items:center;gap:9px;font-weight:700;font-size:19px;letter-spacing:-.01em}
        .brand svg{color:var(--accent)}
        .btn{display:inline-flex;align-items:center;gap:8px;font-size:14px;font-weight:600;
            padding:11px 20px;border-radius:11px;cursor:pointer;transition:.18s;border:1px solid transparent;line-height:1}
        .btn-primary{background:var(--accent);color:#fff}
        .btn-primary:hover{background:var(--accent-hover);transform:translateY(-1px);box-shadow:0 8px 20px -8px rgba(51,102,153,.55)}
        .btn-ghost{background:#fff;border-color:var(--line);color:var(--ink)}
        .btn-ghost:hover{border-color:var(--accent-border);background:var(--accent-soft);color:var(--accent)}

        /* Hero */
        .hero{text-align:center;padding:72px 0 60px}
        .eyebrow{display:inline-block;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;
            color:var(--accent);background:var(--accent-soft);border:1px solid var(--accent-border);
            border-radius:999px;padding:6px 14px;margin-bottom:22px}
        .hero h1{font-size:clamp(34px,6vw,56px);font-weight:800;line-height:1.05;letter-spacing:-.025em;color:var(--ink);margin-bottom:20px}
        .hero p{font-size:18px;line-height:1.65;color:var(--ink-soft);max-width:600px;margin:0 auto 34px}
        .hero .btn{padding:14px 28px;font-size:15px}

        /* Features */
        .features{display:grid;gap:20px;grid-template-columns:repeat(3,1fr);padding-bottom:80px}
        @media(max-width:760px){.features{grid-template-columns:1fr}.hero{padding:48px 0 40px}}
        .card{background:var(--card);border:1px solid var(--line);border-radius:18px;padding:26px;
            text-align:left;transition:.2s;box-shadow:0 1px 2px rgba(16,33,51,.04)}
        .card:hover{transform:translateY(-4px);box-shadow:0 22px 44px -26px rgba(16,33,51,.3);border-color:var(--accent-border)}
        .chip{width:44px;height:44px;border-radius:13px;background:var(--accent-soft);color:var(--accent);
            display:flex;align-items:center;justify-content:center;margin-bottom:18px}
        .card h3{font-size:16px;font-weight:700;color:var(--ink);margin-bottom:7px}
        .card p{font-size:14px;line-height:1.6;color:var(--ink-soft)}

        footer{margin-top:auto;border-top:1px solid var(--line);padding:26px 0;text-align:center;font-size:13.5px;color:#9aa7b4}
    </style>
</head>
<body>

    <div class="wrap">
        <header class="topbar">
            <div class="brand">
                <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/>
                    <path d="M7 21h10"/><path d="M12 3v18"/><path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"/>
                </svg>
                Факта
            </div>
            <a href="<?= htmlspecialchars($loginUrl) ?>" class="btn btn-ghost">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
                Најава
            </a>
        </header>

        <section class="hero">
            <span class="eyebrow">Софтвер за правни и деловни субјекти</span>
            <h1>Фактури и типски документи<br>без мака.</h1>
            <p>Факта ги обединува вашите клиенти, фактури и документи на едно место. Креирајте, прегледувајте и управувајте — брзо, прегледно и безбедно за целиот тим.</p>
            <a href="<?= htmlspecialchars($loginUrl) ?>" class="btn btn-primary">
                Најави се
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
            </a>
        </section>

        <section class="features">
            <?php
            $features = [
                ['Клиенти', 'Држете ги сите правни и физички лица на едно место, со безбедно шифрирани лични податоци.',
                 '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
                ['Фактури', 'Креирајте и пребарувајте фактури со автоматска нумерација и преглед по период и клиент.',
                 '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/>'],
                ['Типски документи', 'Изработувајте документи од шаблони со променливи полиња — повторливата работа станува едноставна.',
                 '<rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>'],
            ];
            foreach ($features as [$title, $desc, $icon]): ?>
            <div class="card">
                <div class="chip">
                    <svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $icon ?></svg>
                </div>
                <h3><?= $title ?></h3>
                <p><?= $desc ?></p>
            </div>
            <?php endforeach; ?>
        </section>
    </div>

    <footer>© <?= date('Y') ?> Факта. Сите права задржани.</footer>

</body>
</html>
