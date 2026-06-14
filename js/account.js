// Settings page (podesuvanja.php): profile, password, and (admin) company users.
$(function () {

    var API = 'api/account_api.php';

    function alert$(selector, type, message) {
        $(selector).removeClass('alert-ok alert-err').addClass('alert-' + type).text(message).show();
    }

    function escapeHtml(text) {
        if (!text) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(text));
        return d.innerHTML;
    }

    function initials(name) {
        var parts = (name || '').trim().split(/\s+/).filter(Boolean);
        if (!parts.length) return '?';
        if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }

    // Keep in sync with AVATAR_PALETTE in js/app.js and fakta_avatar_color() in
    // includes/auth.php — same order and count, or colors won't match (mod length).
    var AVATAR_PALETTE = [
        { bg: '#eff6ff', fg: '#1d4ed8' }, // blue
        { bg: '#fff7ed', fg: '#c2410c' }, // orange
        { bg: '#f0fdf4', fg: '#15803d' }, // green
        { bg: '#fdf4ff', fg: '#a21caf' }, // fuchsia
        { bg: '#fef2f2', fg: '#b91c1c' }, // red
        { bg: '#f0f9ff', fg: '#0369a1' }, // sky
        { bg: '#fefce8', fg: '#a16207' }, // amber
        { bg: '#f5f3ff', fg: '#6d28d9' }  // violet
    ];
    function avatarColor(name) {
        var s = (name || '').trim(), hash = 0;
        for (var i = 0; i < s.length; i++) hash = (hash + s.charCodeAt(i)) % AVATAR_PALETTE.length;
        return AVATAR_PALETTE[hash] || AVATAR_PALETTE[0];
    }

    var ROLE_LABELS = { admin: 'Администратор', employee: 'Вработен', praktikant: 'Практикант' };

    // ---- Profile ----
    $('#formProfile').on('submit', function (e) {
        e.preventDefault();
        var $btn = $(this).find('button[type="submit"]');
        $btn.prop('disabled', true).text('Се зачувува...');
        $.ajax({
            url: API, type: 'POST', dataType: 'json',
            data: $(this).serialize() + '&action=update_profile',
            success: function (res) {
                alert$('#profileAlert', res.success ? 'ok' : 'err', res.message);
                if (res.success) {
                    // Reflect the new name/initials in the nav avatar without a reload.
                    var ini = initials(res.name), col = avatarColor(res.name);
                    $('.nav-avatar').text(ini).css({ background: col.bg, color: col.fg });
                    $('#navMenu .nav-menu-name').text(res.name);
                    $('#navMenu .nav-menu-email').text(res.email);
                }
            },
            error: function () { alert$('#profileAlert', 'err', 'Грешка при комуникација со серверот.'); },
            complete: function () { $btn.prop('disabled', false).text('Зачувај промени'); }
        });
    });

    // ---- Password ----
    $('#formPassword').on('submit', function (e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        $btn.prop('disabled', true).text('Се менува...');
        $.ajax({
            url: API, type: 'POST', dataType: 'json',
            data: $form.serialize() + '&action=change_password',
            success: function (res) {
                alert$('#passwordAlert', res.success ? 'ok' : 'err', res.message);
                if (res.success) $form[0].reset();
            },
            error: function () { alert$('#passwordAlert', 'err', 'Грешка при комуникација со серверот.'); },
            complete: function () { $btn.prop('disabled', false).text('Промени лозинка'); }
        });
    });

    // ---- Company users (admin only) ----
    if (!window.FAKTA_IS_ADMIN) return;

    function loadUsers() {
        $('#usersList').html('<p class="list-msg" style="padding:0.75rem 0">Се вчитува...</p>');
        $.ajax({
            url: API, dataType: 'json', data: { action: 'list_users' },
            success: function (res) {
                if (!res.success) { $('#usersList').html('<p class="list-msg">' + escapeHtml(res.message) + '</p>'); return; }
                if (!res.data.length) { $('#usersList').html('<p class="list-msg">Нема корисници.</p>'); return; }
                var html = '';
                $.each(res.data, function (_, u) {
                    var col = avatarColor(u.name);
                    var isSelf = (parseInt(u.id, 10) === window.FAKTA_UID);
                    // Admin can change anyone's role (except their own) via right-click.
                    var editAttrs = isSelf ? '' : ' data-id="' + u.id + '" data-role="' + escapeHtml(u.role) + '" title="Десен клик за промена на улога"';
                    html += '<div class="settings-user-row' + (isSelf ? '' : ' settings-user-row--editable') + '"' + editAttrs + '>'
                          +   '<div class="client-avatar" style="background:' + col.bg + ';color:' + col.fg + '">' + initials(u.name) + '</div>'
                          +   '<div class="settings-user-info">'
                          +     '<span class="settings-user-name">' + escapeHtml(u.name) + (isSelf ? ' <span class="settings-user-you">(вие)</span>' : '') + '</span>'
                          +     '<span class="settings-user-email">' + escapeHtml(u.email) + '</span>'
                          +   '</div>'
                          +   '<span class="settings-role-badge settings-role-badge--' + escapeHtml(u.role) + '">' + escapeHtml(ROLE_LABELS[u.role] || u.role) + '</span>'
                          + '</div>';
                });
                $('#usersList').html(html);
            },
            error: function () { $('#usersList').html('<p class="list-msg">Грешка при вчитување.</p>'); }
        });
    }

    // ---- Add-user modal ----
    function openUserModal() {
        $('#userModal').addClass('open').removeAttr('aria-hidden');
        $('body').addClass('modal-open');
    }
    function closeUserModal() {
        $('#userModal').removeClass('open').attr('aria-hidden', 'true');
        $('body').removeClass('modal-open');
        $('#formUser')[0].reset();
        $('#userAlert').hide();
    }

    $('#btnAddUser').on('click', openUserModal);
    $('[data-user-close]').on('click', closeUserModal);
    $('#userModal').on('click', function (e) { if (e.target === this) closeUserModal(); });

    // ---- Change an existing user's role (right-click context menu) ----
    var ROLE_ORDER = ['admin', 'employee', 'praktikant'];
    var $roleMenu = null;

    function closeRoleMenu() {
        if ($roleMenu) { $roleMenu.remove(); $roleMenu = null; }
    }

    function openRoleMenu(x, y, $row) {
        closeRoleMenu();
        var current = $row.data('role');
        var items = '';
        ROLE_ORDER.forEach(function (r) {
            var isCur = (r === current);
            items += '<button type="button" class="ctx-item" data-role="' + r + '"' + (isCur ? ' aria-current="true"' : '') + '>'
                   +   '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
                   +     (isCur ? '<polyline points="20 6 9 17 4 12"/>' : '<circle cx="12" cy="8" r="4"/><path d="M4 20a8 8 0 0 1 16 0"/>')
                   +   '</svg>'
                   +   (ROLE_LABELS[r] || r)
                   + '</button>';
        });
        $roleMenu = $('<div class="ctx-menu"><div class="ctx-menu-head">Промени улога</div>' + items + '</div>');
        $roleMenu.css({ left: x + 'px', top: y + 'px' }).appendTo('body');

        // Keep the menu within the viewport.
        var rect = $roleMenu[0].getBoundingClientRect();
        if (rect.right > window.innerWidth)  $roleMenu.css('left', (window.innerWidth  - rect.width  - 8) + 'px');
        if (rect.bottom > window.innerHeight) $roleMenu.css('top',  (window.innerHeight - rect.height - 8) + 'px');

        $roleMenu.on('click', '.ctx-item', function () {
            var role = $(this).data('role');
            closeRoleMenu();
            if (role !== current) changeRole($row, role);
        });
    }

    function changeRole($row, role) {
        var id    = $row.data('id');
        var $badge = $row.find('.settings-role-badge');
        var prevHtml = $badge.prop('outerHTML');
        $badge.text('…');
        $.ajax({
            url: API, type: 'POST', dataType: 'json',
            data: { action: 'update_role', id: id, role: role },
            success: function (res) {
                if (res.success) {
                    $row.data('role', role);
                    $badge.replaceWith('<span class="settings-role-badge settings-role-badge--' + role + '">' + (ROLE_LABELS[role] || role) + '</span>');
                } else {
                    $badge.replaceWith(prevHtml);
                    alert(res.message);
                }
            },
            error: function () { $badge.replaceWith(prevHtml); alert('Грешка при комуникација со серверот.'); }
        });
    }

    $('#usersList').on('contextmenu', '.settings-user-row--editable', function (e) {
        e.preventDefault();
        openRoleMenu(e.clientX, e.clientY, $(this));
    });
    $(document).on('click', closeRoleMenu);
    $(document).on('keydown', function (e) { if (e.key === 'Escape') closeRoleMenu(); });
    $(window).on('resize scroll', closeRoleMenu);

    $('#formUser').on('submit', function (e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        $btn.prop('disabled', true).text('Се создава...');
        $.ajax({
            url: API, type: 'POST', dataType: 'json',
            data: $form.serialize() + '&action=create_user',
            success: function (res) {
                alert$('#userAlert', res.success ? 'ok' : 'err', res.message);
                if (res.success) {
                    $form[0].reset();
                    setTimeout(closeUserModal, 700);
                    loadUsers();
                }
            },
            error: function () { alert$('#userAlert', 'err', 'Грешка при комуникација со серверот.'); },
            complete: function () { $btn.prop('disabled', false).text('Создади'); }
        });
    });

    loadUsers();
});
