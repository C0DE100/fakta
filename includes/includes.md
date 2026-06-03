# Includes

PHP partials shared across all pages.

## nav.php
Top navigation bar. Logo is an `<a href="index.php">` link. Standalone — no dependencies.

## sidebar.php
Left sidebar with collapsible Фактури sub-menu.

**Usage:** set `$currentPage` before including.

```php
<?php $currentPage = 'kreraj-faktura'; include 'includes/sidebar.php'; ?>
```

| `$currentPage` value | Active page |
|----------------------|------------|
| `'home'` (default) | index.php — sub-menu closed |
| `'kreraj-faktura'` | kreraj-faktura.php — sub-menu open, item highlighted |
| `'pregled-fakturi'` | pregled-fakturi.php — sub-menu open, item highlighted |
