<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

// Super-admin manages tenants only — keep them out of the company app.
if (current_role() === 'super_admin') {
    header('Location: ' . fakta_url('admin/index.php'));
    exit;
}

$me      = current_user();
$isAdmin = current_role() === 'admin';
?>
<!DOCTYPE html>
<html lang="mk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Поставки – Факта</title>
    <script>if(localStorage.getItem('darkMode')==='1')document.documentElement.setAttribute('data-theme','dark');</script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css">
</head>

<body class="min-h-screen">

    <?php include 'includes/nav.php'; ?>

    <div class="app-layout">

    <?php $currentPage = 'podesuvanja'; include 'includes/sidebar.php'; ?>

    <div class="main-content">
    <div class="max-w-5xl mx-auto px-4 pb-16">

        <div class="pt-10 pb-6">
            <h1 class="text-lg font-semibold text-slate-800">Поставки</h1>
            <p class="text-sm text-slate-400 mt-1">Управувај со твојот профил и пристап</p>
        </div>

        <div class="settings-grid">
        <!-- Profile -->
        <div class="settings-card">
            <div class="settings-card-head">
                <span class="settings-card-icon settings-card-icon--profile">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                    </svg>
                </span>
                <div class="settings-card-head-text">
                    <h2 class="settings-card-title">Мој профил</h2>
                    <p class="settings-card-sub">Основни податоци за твојата сметка</p>
                </div>
            </div>
            <div id="profileAlert" class="settings-alert" style="display:none;"></div>
            <form id="formProfile" class="settings-form">
                <div class="form-row">
                    <label for="set_name" class="form-row-label">Име и презиме</label>
                    <div class="form-row-field"><input type="text" class="field" id="set_name" name="name" value="<?= htmlspecialchars($me['name'] ?? '') ?>" required></div>
                </div>
                <div class="form-row">
                    <label for="set_email" class="form-row-label">Е-пошта</label>
                    <div class="form-row-field"><input type="email" class="field" id="set_email" name="email" value="<?= htmlspecialchars($me['email'] ?? '') ?>" required></div>
                </div>
                <div class="form-row">
                    <label for="set_phone" class="form-row-label">Телефон</label>
                    <div class="form-row-field"><input type="tel" class="field" id="set_phone" name="phone" value="<?= htmlspecialchars($me['phone'] ?? '') ?>" placeholder="07X XXX XXX"></div>
                </div>
                <div class="settings-form-actions">
                    <button type="submit" class="btn-modal-save">Зачувај промени</button>
                </div>
            </form>
        </div>

        <!-- Password -->
        <div class="settings-card">
            <div class="settings-card-head">
                <span class="settings-card-icon settings-card-icon--password">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                </span>
                <div class="settings-card-head-text">
                    <h2 class="settings-card-title">Промени лозинка</h2>
                    <p class="settings-card-sub">Внеси ја тековната и новата лозинка</p>
                </div>
            </div>
            <div id="passwordAlert" class="settings-alert" style="display:none;"></div>
            <form id="formPassword" class="settings-form">
                <div class="form-row">
                    <label for="set_current" class="form-row-label">Тековна лозинка</label>
                    <div class="form-row-field"><input type="password" class="field" id="set_current" name="current_password" autocomplete="current-password" required></div>
                </div>
                <div class="form-row">
                    <label for="set_new" class="form-row-label">Нова лозинка</label>
                    <div class="form-row-field"><input type="password" class="field" id="set_new" name="new_password" autocomplete="new-password" minlength="8" required></div>
                </div>
                <div class="form-row">
                    <label for="set_confirm" class="form-row-label">Потврди лозинка</label>
                    <div class="form-row-field"><input type="password" class="field" id="set_confirm" name="confirm_password" autocomplete="new-password" minlength="8" required></div>
                </div>
                <div class="settings-form-actions">
                    <button type="submit" class="btn-modal-save">Промени лозинка</button>
                </div>
            </form>
        </div>
        </div> <!-- /.settings-grid -->

        <?php if ($isAdmin): ?>
        <!-- Company users (admin only) -->
        <div class="settings-card">
            <div class="settings-card-head settings-card-head--row">
                <div class="settings-card-head-left">
                    <span class="settings-card-icon settings-card-icon--users">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                    </span>
                    <div class="settings-card-head-text">
                        <h2 class="settings-card-title">Корисници во компанијата</h2>
                        <p class="settings-card-sub">Додај и прегледувај членови на твојот тим</p>
                    </div>
                </div>
                <button type="button" id="btnAddUser" class="btn-new-client">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" x2="12" y1="5" y2="19"/><line x1="5" x2="19" y1="12" y2="12"/>
                    </svg>
                    Додај корисник
                </button>
            </div>
            <div id="usersList" class="settings-users"></div>
        </div>
        <?php endif; ?>

    </div>
    </div> <!-- /.main-content -->
    </div> <!-- /.app-layout -->

    <?php if ($isAdmin): ?>
    <!-- Add user modal -->
    <div id="userModal" class="modal-overlay" aria-hidden="true">
        <div class="modal-box" role="dialog" aria-modal="true">
            <div class="modal-panel modal-panel--profile active">
                <div class="profile-hero">
                    <div class="client-avatar client-avatar--lg">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/>
                        </svg>
                    </div>
                    <div class="profile-hero-text">
                        <span class="profile-hero-title">Нов корисник</span>
                        <span class="profile-hero-sub">Создади пристап за член на тимот</span>
                    </div>
                    <button data-user-close class="modal-close" aria-label="Затвори">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18"/><path d="M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div id="userAlert" style="display:none;"></div>
                <form id="formUser">
                    <div class="form-row">
                        <label for="user_name" class="form-row-label">Име и презиме</label>
                        <div class="form-row-field"><input type="text" class="field" id="user_name" name="name" required></div>
                    </div>
                    <div class="form-row">
                        <label for="user_email" class="form-row-label">Е-пошта</label>
                        <div class="form-row-field"><input type="email" class="field" id="user_email" name="email" placeholder="example@firma.mk" required></div>
                    </div>
                    <div class="form-row">
                        <label for="user_phone" class="form-row-label">Телефон</label>
                        <div class="form-row-field"><input type="tel" class="field" id="user_phone" name="phone" placeholder="07X XXX XXX"></div>
                    </div>
                    <div class="form-row">
                        <label for="user_password" class="form-row-label">Лозинка</label>
                        <div class="form-row-field"><input type="password" class="field" id="user_password" name="password" autocomplete="new-password" minlength="8" required></div>
                    </div>
                    <div class="form-row">
                        <label for="user_role" class="form-row-label">Улога</label>
                        <div class="form-row-field">
                            <select class="field" id="user_role" name="role" required>
                                <option value="employee">Вработен</option>
                                <option value="praktikant">Практикант</option>
                                <option value="admin">Администратор</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" data-user-close class="btn-modal-cancel">Откажи</button>
                        <button type="submit" class="btn-modal-save">Создади</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>window.FAKTA_IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;</script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="js/app.js"></script>
    <script src="js/account.js"></script>
</body>
</html>
