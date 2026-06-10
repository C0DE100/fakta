<?php
require_once __DIR__ . '/includes/auth.php';

/** @var Auth $fakta_auth */
$GLOBALS['fakta_auth']->logout();

$landing = fakta_url('landing.php');
?>
<!DOCTYPE html>
<html lang="mk">
<head>
    <meta charset="UTF-8">
    <title>Одјава…</title>
    <!-- Clear any tenant drafts left in this browser, then leave. -->
    <script>
        try {
            for (var i = sessionStorage.length - 1; i >= 0; i--) {
                var k = sessionStorage.key(i);
                if (k && k.indexOf('fakta_') === 0) sessionStorage.removeItem(k);
            }
        } catch (e) {}
        location.replace(<?= json_encode($landing) ?>);
    </script>
    <meta http-equiv="refresh" content="0;url=<?= htmlspecialchars($landing) ?>">
</head>
<body></body>
</html>
