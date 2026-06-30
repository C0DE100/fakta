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
    <title>Факта — Софтвер за адвокати и правни тимови</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{
            --accent:#336699; --accent-hover:#2a5580; --accent-soft:#eaf1f7; --accent-border:#bcd4e6;
            --ink:#1e2a38; --ink-soft:#5b6b7c; --ink-faint:#8a98a6; --line:#e4eaf0; --bg:#f7f9fb; --card:#ffffff;
            /* event kind colors — identical to the live calendar */
            --k-hearing:#eab308; --k-trial:#dc2626; --k-meeting:#7c3aed; --k-other:#0d9488;
        }
        *{box-sizing:border-box;margin:0;padding:0}
        html{scroll-behavior:smooth}
        body{
            font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,sans-serif;
            color:var(--ink);
            background:
                radial-gradient(900px 480px at 50% -8%, var(--accent-soft) 0%, rgba(234,241,247,0) 60%),
                var(--bg);
            -webkit-font-smoothing:antialiased;
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
        .hero{text-align:center;padding:60px 0 14px}
        .eyebrow{display:inline-block;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;
            color:var(--accent);background:var(--accent-soft);border:1px solid var(--accent-border);
            border-radius:999px;padding:6px 14px;margin-bottom:22px}
        .hero h1{font-size:clamp(34px,6vw,56px);font-weight:800;line-height:1.05;letter-spacing:-.025em;margin-bottom:20px}
        .hero p{font-size:18px;line-height:1.65;color:var(--ink-soft);max-width:560px;margin:0 auto 32px}
        .hero .btn{padding:14px 28px;font-size:15px}

        /* App window frame for every snapshot */
        .shot{background:var(--card);border:1px solid var(--line);border-radius:16px;overflow:hidden;
            box-shadow:0 30px 60px -34px rgba(16,33,51,.4),0 2px 6px rgba(16,33,51,.05)}
        .shot-bar{display:flex;align-items:center;gap:7px;padding:11px 14px;border-bottom:1px solid var(--line);background:#fbfcfe}
        .shot-bar i{width:10px;height:10px;border-radius:50%;background:#e2e8f0}
        .shot-bar i:nth-child(2){background:#e8edf2}
        .shot-bar i:nth-child(3){background:#eef1f5}
        .shot-bar span{margin-left:8px;font-size:12px;font-weight:600;color:var(--ink-faint)}
        .shot-body{padding:18px}

        /* Hero big shot */
        .hero-shot{max-width:920px;margin:6px auto 0}

        /* generic mock primitives */
        .m-h{font-size:13px;font-weight:700;color:var(--ink);display:flex;align-items:center;gap:7px;margin-bottom:11px}
        .m-h .ico{color:var(--accent);display:flex}
        .m-grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
        @media(max-width:640px){.m-grid2{grid-template-columns:1fr}}

        /* event rows (Што следи) */
        .m-dayhead{font-size:11px;font-weight:700;color:var(--ink-faint);text-transform:uppercase;letter-spacing:.04em;margin:0 0 7px}
        .m-dayhead.is-today{color:var(--accent)}
        .m-ev{display:flex;gap:10px;align-items:flex-start;padding:9px 11px;border:1px solid var(--line);
            border-left-width:3px;border-radius:9px;margin-bottom:7px;background:#fff}
        .m-ev .t{font-size:12px;font-weight:700;color:var(--ink-soft);white-space:nowrap;padding-top:1px}
        .m-ev .b{flex:1;min-width:0}
        .m-ev .ti{font-size:13px;font-weight:600;color:var(--ink)}
        .m-ev .cs{font-size:11.5px;color:var(--ink-faint);margin-top:2px}
        .m-ev .cs b{color:var(--ink-soft);font-weight:600}
        .badge{font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;white-space:nowrap}
        .b-hearing{background:#fef9c3;color:#854d0e} .ev-hearing{border-left-color:var(--k-hearing)}
        .b-trial{background:#fee2e2;color:#991b1b}   .ev-trial{border-left-color:var(--k-trial)}
        .b-meeting{background:#f3e8ff;color:#6b21a8} .ev-meeting{border-left-color:var(--k-meeting)}
        .b-other{background:#ccfbf1;color:#115e59}   .ev-other{border-left-color:var(--k-other)}

        /* task rows */
        .m-cg{font-size:11.5px;font-weight:700;color:var(--ink-soft);display:flex;align-items:center;gap:7px;margin:2px 0 9px}
        .m-cg .dot{width:8px;height:8px;border-radius:50%;background:var(--accent)}
        .m-td{display:flex;align-items:center;gap:10px;padding:8px 2px;border-top:1px solid var(--line)}
        .m-td .ring{width:17px;height:17px;border-radius:50%;border:2px solid #cbd5e1;flex:none}
        .m-td .tt{flex:1;font-size:13px;color:var(--ink)}
        .pill{font-size:10.5px;font-weight:600;padding:3px 9px;border-radius:999px;display:inline-flex;align-items:center;gap:5px}
        .pill .d{width:7px;height:7px;border-radius:50%}
        .p-prog{background:#eff6ff;color:#1d4ed8}     .p-prog .d{background:#2563eb}
        .p-wait{background:#fefce8;color:#a16207}      .p-wait .d{background:#d4a017}
        .p-open{background:#f1f5f9;color:#64748b}      .p-open .d{background:#a8a29e}
        .due{font-size:11px;font-weight:600;padding:2px 7px;border-radius:6px}
        .due-late{background:#fee2e2;color:#b91c1c} .due-soon{background:#fef9c3;color:#854d0e}

        /* Feature rows */
        .feat{display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:center;padding:46px 0}
        .feat.rev .feat-txt{order:2}
        @media(max-width:820px){.feat{grid-template-columns:1fr;gap:26px;padding:34px 0}.feat.rev .feat-txt{order:0}}
        .feat-kicker{font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--accent);margin-bottom:11px}
        .feat-txt h2{font-size:clamp(24px,3.4vw,32px);font-weight:800;letter-spacing:-.02em;line-height:1.12;margin-bottom:13px}
        .feat-txt p{font-size:15.5px;line-height:1.65;color:var(--ink-soft)}

        /* case-detail mock */
        .m-hero{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px}
        .m-num{font-size:20px;font-weight:800;letter-spacing:-.01em}
        .m-num small{font-size:13px;font-weight:600;color:var(--ink-faint);margin-left:8px}
        .stat{font-size:11px;font-weight:700;padding:4px 11px;border-radius:999px;background:#eff6ff;color:#1d4ed8;display:inline-flex;align-items:center;gap:6px}
        .stat .d{width:7px;height:7px;border-radius:50%;background:#2563eb}
        .facts{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px}
        .facts span{font-size:11.5px;font-weight:600;color:var(--ink-soft);background:#f4f7fa;border:1px solid var(--line);border-radius:8px;padding:5px 10px}
        .parties{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px}
        @media(max-width:480px){.parties{grid-template-columns:1fr}}
        .party-lbl{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--ink-faint);margin-bottom:6px}
        .party-name{font-size:13.5px;font-weight:600;color:var(--ink)}
        .party-role{font-size:11.5px;color:var(--ink-faint);margin-top:1px}
        .tabs{display:flex;gap:4px;border-top:1px solid var(--line);padding-top:12px}
        .tabs span{font-size:12px;font-weight:600;color:var(--ink-faint);padding:6px 11px;border-radius:8px}
        .tabs span.on{background:var(--accent-soft);color:var(--accent)}
        .tabs span b{font-weight:700}

        /* calendar mock */
        .cal-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
        .cal-head .mo{font-size:15px;font-weight:800}
        .cal-views{display:flex;gap:4px}
        .cal-views span{font-size:11px;font-weight:600;color:var(--ink-faint);padding:5px 10px;border-radius:7px;background:#f4f7fa}
        .cal-views span.on{background:var(--accent);color:#fff}
        .cal-dow{display:grid;grid-template-columns:repeat(7,1fr);gap:5px;margin-bottom:5px}
        .cal-dow span{font-size:10px;font-weight:700;color:var(--ink-faint);text-align:center;text-transform:uppercase}
        .cal-grid{display:grid;grid-template-columns:repeat(7,1fr);grid-auto-rows:52px;gap:5px}
        .cal-cell{border:1px solid var(--line);border-radius:8px;padding:5px 6px;background:#fff;overflow:hidden}
        .cal-cell .dn{font-size:10.5px;font-weight:700;color:var(--ink-faint)}
        .cal-cell.muted{background:#fafbfc}.cal-cell.muted .dn{color:#cbd5e1}
        .cal-cell.today{border-color:var(--accent-border);background:var(--accent-soft)}
        .cal-cell.today .dn{color:var(--accent)}
        .ce{display:block;font-size:9.5px;font-weight:600;color:#fff;border-radius:4px;padding:1px 5px;margin-top:3px;
            white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .ce-hearing{background:var(--k-hearing);color:#3f2d00} .ce-trial{background:var(--k-trial)}
        .ce-meeting{background:var(--k-meeting)} .ce-other{background:var(--k-other)}

        /* small feature strip */
        .strip{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;padding:30px 0 78px}
        @media(max-width:760px){.strip{grid-template-columns:1fr 1fr}}
        @media(max-width:440px){.strip{grid-template-columns:1fr}}
        .scell .chip{width:40px;height:40px;border-radius:11px;background:var(--accent-soft);color:var(--accent);
            display:flex;align-items:center;justify-content:center;margin-bottom:12px}
        .scell h4{font-size:14.5px;font-weight:700;margin-bottom:5px}
        .scell p{font-size:13px;line-height:1.55;color:var(--ink-soft)}

        /* closing CTA */
        .cta{text-align:center;background:var(--card);border:1px solid var(--line);border-radius:20px;
            padding:54px 24px;margin-bottom:70px;box-shadow:0 1px 2px rgba(16,33,51,.04)}
        .cta h2{font-size:clamp(26px,4vw,36px);font-weight:800;letter-spacing:-.02em;margin-bottom:12px}
        .cta p{font-size:16px;color:var(--ink-soft);margin-bottom:26px}
        .cta .btn{padding:14px 30px;font-size:15px}

        footer{border-top:1px solid var(--line);padding:26px 0;text-align:center;font-size:13.5px;color:#9aa7b4}
    </style>
</head>
<body>
    <h1>TESTTTTTTT</h1>
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

        <!-- HERO -->
        <section class="hero">
            <span class="eyebrow">Софтвер за адвокати и правни тимови</span>
            <h1>Водете ги предметите<br>без хаос.</h1>
            <p>Факта ги држи предметите, рочиштата, задачите и документите на едно место — прегледно за целата канцеларија.</p>
            <a href="<?= htmlspecialchars($loginUrl) ?>" class="btn btn-primary">
                Најави се
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
            </a>
        </section>

        <!-- HERO SHOT: dashboard (Што следи + Задачи) -->
        <div class="shot hero-shot">
            <div class="shot-bar"><i></i><i></i><i></i><span>Факта · Почетна</span></div>
            <div class="shot-body">
                <div class="m-grid2">
                    <div>
                        <div class="m-h"><span class="ico"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18M8 2v4M16 2v4"/></svg></span>Што следи</div>
                        <div class="m-dayhead is-today">Денес</div>
                        <div class="m-ev ev-hearing"><span class="t">09:30</span><span class="b"><span class="ti">Рочиште · ОС Скопје</span><span class="cs"><b>Предмет 12/26</b> · Марко Стоев</span></span><span class="badge b-hearing">Рочиште</span></div>
                        <div class="m-ev ev-meeting"><span class="t">13:00</span><span class="b"><span class="ti">Состанок со клиент</span><span class="cs"><b>Предмет 7/26</b> · Зора ДОО</span></span><span class="badge b-meeting">Состанок</span></div>
                        <div class="m-dayhead">Утре</div>
                        <div class="m-ev ev-trial"><span class="t">10:00</span><span class="b"><span class="ti">Главна расправа</span><span class="cs"><b>Предмет 3/26</b> · Илиев</span></span><span class="badge b-trial">Судење</span></div>
                    </div>
                    <div>
                        <div class="m-h"><span class="ico"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 12H3M16 6H3M21 18H3"/><path d="m16 16 2 2 4-4"/></svg></span>Задачи</div>
                        <div class="m-cg"><span class="dot"></span>Предмет 12/26 · Марко Стоев</div>
                        <div class="m-td"><span class="ring"></span><span class="tt">Поднеси жалба</span><span class="due due-late">24.06</span><span class="pill p-prog"><span class="d"></span>Во тек</span></div>
                        <div class="m-td"><span class="ring"></span><span class="tt">Подготви доказен материјал</span><span class="due due-soon">утре</span><span class="pill p-open"><span class="d"></span>Отворена</span></div>
                        <div class="m-cg" style="margin-top:14px"><span class="dot" style="background:#16a34a"></span>Предмет 7/26 · Зора ДОО</div>
                        <div class="m-td"><span class="ring"></span><span class="tt">Контактирај сведок</span><span class="pill p-wait"><span class="d"></span>Чека</span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- FEATURE: Предмети -->
        <section class="feat">
            <div class="feat-txt">
                <div class="feat-kicker">Предмети</div>
                <h2>Секој предмет, целосна слика.</h2>
                <p>Странки, основ, статус и админ-броеви — сè за еден предмет на едно место. Документи, белешки, задачи и настани под иста картичка.</p>
            </div>
            <div class="shot">
                <div class="shot-bar"><i></i><i></i><i></i><span>Предмет 12/26</span></div>
                <div class="shot-body">
                    <div class="m-hero">
                        <div class="m-num">Предмет 12/26<small>Спор за сопственост</small></div>
                        <span class="stat"><span class="d"></span>Во тек</span>
                    </div>
                    <div class="facts"><span>Заведен 14.03.2026</span><span>Управен бр. У-482/26</span><span>Доделено: А. Јовановски</span></div>
                    <div class="parties">
                        <div>
                            <div class="party-lbl">Наша странка</div>
                            <div class="party-name">Марко Стоев</div>
                            <div class="party-role">Тужител</div>
                        </div>
                        <div>
                            <div class="party-lbl">Спротивна странка</div>
                            <div class="party-name">Алфа Градба ДОО</div>
                            <div class="party-role">Тужен · адв. П. Николов</div>
                        </div>
                    </div>
                    <div class="tabs">
                        <span class="on">Документи <b>6</b></span><span>Белешки <b>4</b></span><span>Задачи <b>3</b></span><span>Настани <b>2</b></span>
                    </div>
                </div>
            </div>
        </section>

        <!-- FEATURE: Календар -->
        <section class="feat rev">
            <div class="feat-txt">
                <div class="feat-kicker">Календар</div>
                <h2>Ниту едно рочиште промакнато.</h2>
                <p>Рочишта, судења и состаноци по ден, недела и месец — означени по боја. Секој настан со потсетник за оние што се доделени.</p>
            </div>
            <div class="shot">
                <div class="shot-bar"><i></i><i></i><i></i><span>Календар</span></div>
                <div class="shot-body">
                    <div class="cal-head">
                        <span class="mo">Јуни 2026</span>
                        <span class="cal-views"><span>Ден</span><span>Недела</span><span class="on">Месец</span></span>
                    </div>
                    <div class="cal-dow"><span>Пон</span><span>Вто</span><span>Сре</span><span>Чет</span><span>Пет</span><span>Саб</span><span>Нед</span></div>
                    <div class="cal-grid">
                        <div class="cal-cell muted"><span class="dn">26</span></div>
                        <div class="cal-cell muted"><span class="dn">27</span></div>
                        <div class="cal-cell muted"><span class="dn">28</span></div>
                        <div class="cal-cell muted"><span class="dn">29</span></div>
                        <div class="cal-cell muted"><span class="dn">30</span></div>
                        <div class="cal-cell muted"><span class="dn">31</span></div>
                        <div class="cal-cell"><span class="dn">1</span></div>

                        <div class="cal-cell"><span class="dn">2</span><span class="ce ce-hearing">Рочиште</span></div>
                        <div class="cal-cell"><span class="dn">3</span></div>
                        <div class="cal-cell"><span class="dn">4</span><span class="ce ce-meeting">Состанок</span></div>
                        <div class="cal-cell"><span class="dn">5</span></div>
                        <div class="cal-cell"><span class="dn">6</span><span class="ce ce-trial">Судење</span></div>
                        <div class="cal-cell"><span class="dn">7</span></div>
                        <div class="cal-cell"><span class="dn">8</span></div>

                        <div class="cal-cell"><span class="dn">9</span></div>
                        <div class="cal-cell"><span class="dn">10</span><span class="ce ce-other">Рок</span></div>
                        <div class="cal-cell"><span class="dn">11</span></div>
                        <div class="cal-cell"><span class="dn">12</span><span class="ce ce-hearing">Рочиште</span></div>
                        <div class="cal-cell"><span class="dn">13</span></div>
                        <div class="cal-cell"><span class="dn">14</span></div>
                        <div class="cal-cell"><span class="dn">15</span></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- FEATURE: Задачи -->
        <section class="feat">
            <div class="feat-txt">
                <div class="feat-kicker">Задачи и тим</div>
                <h2>Задачи што не се забораваат.</h2>
                <p>Доделувајте задачи по предмет, следете рок и статус, и добивајте известувања кога нешто ви е зададено вам.</p>
            </div>
            <div class="shot">
                <div class="shot-bar"><i></i><i></i><i></i><span>Предмет 3/26 · Задачи</span></div>
                <div class="shot-body">
                    <div class="m-cg"><span class="dot" style="background:#dc2626"></span>Предмет 3/26 · Илиев</div>
                    <div class="m-td"><span class="ring" style="border-color:#2563eb"></span><span class="tt">Изготви поднесок до судот</span><span class="due due-late">вчера</span><span class="pill p-prog"><span class="d"></span>Во тек</span></div>
                    <div class="m-td"><span class="ring"></span><span class="tt">Прибери документи од клиент</span><span class="due due-soon">петок</span><span class="pill p-open"><span class="d"></span>Отворена</span></div>
                    <div class="m-td"><span class="ring"></span><span class="tt">Достави вештачење</span><span class="pill p-wait"><span class="d"></span>Чека</span></div>
                    <div class="m-td"><span class="ring" style="border-color:#16a34a;background:#16a34a"></span><span class="tt" style="color:var(--ink-faint);text-decoration:line-through">Уплати судска такса</span></div>
                </div>
            </div>
        </section>

        <!-- SMALL FEATURE STRIP -->
        <section class="strip">
            <div class="scell">
                <div class="chip"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                <h4>Клиенти</h4>
                <p>Правни и физички лица со шифрирани лични податоци.</p>
            </div>
            <div class="scell">
                <div class="chip"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg></div>
                <h4>Типски документи</h4>
                <p>Документи од шаблони со полиња што сами се пополнуваат.</p>
            </div>
            <div class="scell">
                <div class="chip"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/></svg></div>
                <h4>Фактури</h4>
                <p>Издавање и преглед со автоматска нумерација.</p>
            </div>
            <div class="scell">
                <div class="chip"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></svg></div>
                <h4>Улоги и пристап</h4>
                <p>Адвокат, вработен и практикант — секој со свои дозволи.</p>
            </div>
        </section>

        <!-- CLOSING CTA -->
        <section class="cta">
            <h2>Сè за вашата канцеларија, на едно место.</h2>
            <p>Најавете се и продолжете со работа таму каде што застанавте.</p>
            <a href="<?= htmlspecialchars($loginUrl) ?>" class="btn btn-primary">
                Најави се
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
            </a>
        </section>
    </div>

    <footer>© <?= date('Y') ?> Факта. Сите права задржани.</footer>

</body>
</html>
