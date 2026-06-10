/* Shared helpers for the super-admin console (loaded after jQuery). */
window.FaktaAdmin = (function ($) {
    'use strict';

    function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }

    function initials(n) {
        return (n || '?').trim().split(/\s+/).slice(0, 2).map(function (w) { return w[0]; }).join('').toUpperCase();
    }

    function fmtDate(s) {
        return (s || '').slice(0, 10).split('-').reverse().join('.');
    }

    function roleLabel(r) {
        return r === 'admin' ? 'Администратор'
             : r === 'employee' ? 'Вработен'
             : r === 'praktikant' ? 'Практикант'
             : r;
    }
    function roleClass(r) {
        return r === 'admin' ? 'pill-admin' : r === 'praktikant' ? 'pill-prak' : 'pill-emp';
    }

    function debounce(fn, ms) {
        var t;
        return function () { clearTimeout(t); var a = arguments, c = this; t = setTimeout(function () { fn.apply(c, a); }, ms || 300); };
    }

    function alertBox(sel, ok, msg) {
        $(sel).removeClass('ok err').addClass(ok ? 'ok' : 'err').addClass('show').text(msg);
    }

    /* Render a pager into $el. opts = {page, pages, total, perPage, onGo(page)} */
    function renderPager($el, opts) {
        if (!opts.total || opts.pages <= 1) { $el.empty(); return; }
        var from = (opts.page - 1) * opts.perPage + 1;
        var to   = Math.min(opts.page * opts.perPage, opts.total);
        var btns = '';
        var add = function (label, p, dis, active) {
            btns += '<button class="page-btn' + (active ? ' active' : '') + '" ' + (dis ? 'disabled' : 'data-go="' + p + '"') + '>' + label + '</button>';
        };
        add('‹', opts.page - 1, opts.page <= 1, false);
        var start = Math.max(1, opts.page - 2), end = Math.min(opts.pages, start + 4);
        start = Math.max(1, end - 4);
        for (var i = start; i <= end; i++) add(i, i, false, i === opts.page);
        add('›', opts.page + 1, opts.page >= opts.pages, false);

        $el.html(
            '<div class="info">' + from + '–' + to + ' од ' + opts.total + '</div>' +
            '<div class="pages">' + btns + '</div>'
        );
        $el.find('[data-go]').on('click', function () { opts.onGo(+$(this).data('go')); });
    }

    return { esc: esc, initials: initials, fmtDate: fmtDate, roleLabel: roleLabel, roleClass: roleClass, debounce: debounce, alertBox: alertBox, renderPager: renderPager };
})(jQuery);
