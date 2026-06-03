# Project: advokatski-fakturi

Lawyer document management app. PHP backend, jQuery + Tailwind frontend.

## Entry point
`index.php` — main dashboard. Sidebar and nav are shared PHP includes.

## Config
`config.php`
- `ENCRYPTION_KEY` — passphrase for AES-256-CBC encryption of sensitive fields.
- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` — MySQL connection.

## Structure

```
index.php               # Dashboard — invoice list + client management
kreraj-faktura.php      # Креирај Фактура page (shell, WIP)
pregled-fakturi.php     # Детален Преглед на Фактури page (shell, WIP)
config.php              # Constants
includes/
  nav.php               # Top navigation bar (shared across all pages)
  sidebar.php           # Left sidebar (shared across all pages)
api/
  client_api.php        # REST-style endpoint for client CRUD
  invoice_api.php       # REST-style endpoint for invoice listing
classes/
  Database.php          # PDO wrapper (lazy connect)
  Encryption.php        # AES-256-CBC encrypt/decrypt
  Client.php            # Client business logic
  Invoice.php           # Invoice read logic
css/
  style.css             # Custom utility classes (Tailwind extended)
js/
  app.js                # All frontend logic
```

## Shared includes

### includes/nav.php
Top navigation bar rendered on every page. Logo is an `<a href="index.php">` link.

### includes/sidebar.php
Left sidebar rendered on every page. Accepts `$currentPage` variable (set before including):

| Value | Page |
|-------|------|
| `'home'` | index.php (default) |
| `'kreraj-faktura'` | kreraj-faktura.php |
| `'pregled-fakturi'` | pregled-fakturi.php |

`$currentPage` controls which sidebar sub-item gets the `.active` class and whether the Фактури sub-menu is pre-expanded.

## Sidebar navigation

### Почетна
`<a href="index.php">` — always first item; gets `.sidebar-btn--active` when `$currentPage === 'home'`.

### Клиенти
`data-scroll="sectionClients"` — scrolls to the clients section on `index.php`. No-ops on other pages (target not found).

### Фактури
Expandable parent button (`#btnInvoicesToggle`). Clicking toggles `.sidebar-btn--open` on the button and `.open` on `#submenuInvoices`. Sub-menu collapses automatically when sidebar is collapsed.

When the sidebar is **collapsed**, hovering over the Фактури icon shows a fixed-position flyout popup to the right of the sidebar. JS sets `top` on mouseenter.

Sub-items (links):
| Label | URL |
|-------|-----|
| Креирај Фактура | `kreraj-faktura.php` |
| Детален Преглед на Фактури | `pregled-fakturi.php` |

## Panels (index.php only)

### Clients
| ID | Shown when |
|----|-----------|
| `panelSelectType` | "Креирај клиент" card clicked |
| `panelFormCompany` | "Правно лице" button clicked |
| `panelFormIndividual` | "Физичко лице" button clicked |
| `panelExistingClients` | "Постоечки клиенти" card clicked |

### Invoices
Always visible on `index.php` — no toggle. Invoice list loads automatically on page ready.
