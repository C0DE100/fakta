# JS: app.js

jQuery `$(document).ready` block. No modules.

## State

| Variable | Type | Purpose |
|----------|------|---------|
| `allClients` | `Array` | Full client list from last `get_all` call; filtered client-side |
| `PAGE_SIZE` | `const 15` | Rows per client pagination page |
| `CLIENT_CREATE_PANELS` | `const string[]` | Panel IDs that belong to the "create client" flow |
| `MK_MONTHS` | `const string[]` | Macedonian month names, 0-indexed (Jan = index 0) |
| `invoiceSearchTimer` | timer ref | Debounce handle for invoice search input |

## Sidebar

**Collapsed state persistence** — on ready, `localStorage.getItem('sidebarCollapsed') === '1'` immediately adds `.collapsed` to `#sidebar`. On toggle, `localStorage.setItem('sidebarCollapsed', ...)` saves the new state. This persists across page navigations.

`#sidebarToggle` click — toggles `.collapsed` on `#sidebar`, saves to localStorage. When collapsing: removes `.open` and `.flyout-visible` from all submenus, removes `.sidebar-btn--open` from all parent buttons.

`.sidebar-btn[data-scroll]` click — calls `scrollIntoView({ behavior: 'smooth', block: 'start' })` on the element matching `data-scroll` (e.g. `sectionClients`). No-ops if element not found (other pages).

`#btnInvoicesToggle` click — toggles `.sidebar-btn--open` on the button and `.open` on `#submenuInvoices`. CSS handles caret rotation and sub-menu visibility.

**Collapsed flyout** — JS-driven (not pure CSS `:hover`), because the fixed-position flyout is outside the `.sidebar-group` DOM hover area:
- `.sidebar-group` `mouseenter`: if collapsed, sets `top` on the submenu and adds `.flyout-visible`
- `.sidebar-group` `mouseleave`: starts 80ms timer to remove `.flyout-visible`
- `#submenuInvoices` `mouseenter`: cancels the timer (keeps flyout open while mouse is on it)
- `#submenuInvoices` `mouseleave`: starts 80ms timer to remove `.flyout-visible`

## Panel system

`showPanel(id)` — hides all `.panel` elements, shows `#<id>`, highlights the matching `.action-card`.

`[data-go]` click handler — delegates to `showPanel`. Toggling an already-active action card closes it.
Special trigger on open:
- `panelExistingClients` → calls `loadClients()`

## Client list

| Function | Notes |
|----------|-------|
| `loadClients()` | Fetches `get_all`, stores in `allClients`, calls `renderClients` |
| `getFilteredClients()` | Filters `allClients` by `#searchClients` input (name, case-insensitive) |
| `renderClients(clients, page)` | Renders paginated `.client-row` list + `#clientsPager` buttons |

Search `#searchClients` re-renders on `input`. Pager `#clientsPager` re-renders on page button click.

## Client forms

`#btnCompany` / `#btnIndividual` — reset and show the respective form panel.

`#formCompany`, `#formIndividual` submit — POST to `api/client_api.php` with serialised fields + `action`. Shows alert via `showAlert`.

## Invoice list

| Function | Notes |
|----------|-------|
| `getInvoiceParams(page)` | Reads current filter inputs, returns params object for the API call |
| `loadInvoices(page)` | Calls `invoice_api.php?action=get_list` with current filters; calls `renderInvoices` |
| `renderInvoices(invoices, page, pages)` | Groups rows by `YYYY-MM`, renders `.inv-month-group` blocks + `#invoicesPager` |
| `loadClientsFilter()` | Populates `#filterClient` dropdown from `client_api.php get_all`; called once on page ready; no-ops if already loaded |
| `formatDate(dateStr)` | `'2026-03-02'` → `'02 Март, 2026'` using `MK_MONTHS` |

Filters: `#searchInvoices` (debounced 300ms), `#filterMonth` (type=month, value=YYYY-MM), `#filterClient` (select).
All filters trigger server-side re-fetch. Pager `#invoicesPager` uses `data-inv-page` attribute.

## Utilities

| Function | Notes |
|----------|-------|
| `showAlert(selector, type, message)` | Sets `.alert-ok` or `.alert-err` class + text on `$(selector)`, then shows it |
| `escapeHtml(text)` | DOM-based XSS-safe HTML escaping |
