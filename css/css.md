# CSS: style.css

Custom utility classes that extend Tailwind. All layout/spacing beyond these uses Tailwind directly in HTML.

Classes marked **→ Tailwind** have been removed from style.css and are now applied as utility classes directly on HTML elements.

## Buttons
| Class | Status | Notes |
|-------|--------|-------|
| `.btn-primary` | → Tailwind | `inline-flex items-center gap-2 text-sm font-medium py-2.5 px-4 rounded-lg cursor-pointer select-none bg-slate-900 text-white border-0 transition-colors hover:bg-slate-700 disabled:opacity-50 disabled:cursor-not-allowed` |
| `.btn-outline` | → Tailwind | `inline-flex items-center gap-2 text-sm font-medium py-2.5 px-4 rounded-lg cursor-pointer select-none bg-white border border-slate-300 text-slate-700 transition-colors hover:bg-slate-50` |
| `.btn-icon` | → Tailwind | `inline-flex items-center justify-center p-1.5 rounded-lg text-slate-400 cursor-pointer bg-transparent border-0 transition-colors hover:text-slate-600 hover:bg-slate-100` |

## Form
| Class | Status | Notes |
|-------|--------|-------|
| `.field` | CSS | Text/search/select input — sets `width: 100%`; override with inline style or Tailwind when used in flex rows |
| `.label` | → Tailwind | `block text-sm font-medium text-slate-600 mb-1.5` |

## Alerts
| Class | Use |
|-------|-----|
| `.alert-ok` | Green success alert — JS-toggled, stays in CSS |
| `.alert-err` | Red error alert — JS-toggled, stays in CSS |

## Layout
| Class | Status | Notes |
|-------|--------|-------|
| `.app-layout` | CSS | Top-level flex row containing sidebar + main content |
| `.main-content` | CSS | Flex child wrapping page content; `flex: 1; min-width: 0` |
| `.card` | → Tailwind | `bg-white border border-slate-200 rounded-xl shadow-sm p-6` |
| `.panel` | CSS | Hidden by default; `.panel.active` shows as block — JS-toggled |
| `.section-label` | CSS | Uppercase grey section heading with icon (non-standard `letter-spacing: 0.06em`) |
| `.action-grid` | → Tailwind | `grid grid-cols-2 gap-3` |
| `.action-card` | CSS | Clickable card in action grid; `.active` state highlights it — JS-toggled |
| `.action-title` | CSS | Bold title inside action card (non-standard `font-size: 0.9375rem`) |
| `.action-desc` | CSS | Subtitle inside action card (non-standard `font-size: 0.8125rem`) |

## Sidebar
| Class | Use |
|-------|-----|
| `.sidebar` | Left sticky sidebar, `width: 15rem`; `overflow: hidden`; CSS width transition |
| `.sidebar.collapsed` | Collapsed state — width shrinks to `3.5rem` |
| `.sidebar-header` | Flex row, right-aligned, holds the toggle button |
| `.sidebar-toggle` | 28×28px chevron button; transitions color |
| `.sidebar-toggle svg` | Chevron icon; rotates 180° when `.collapsed` |
| `.sidebar-nav` | Flex column of navigation buttons |
| `.sidebar-btn` | Full-width text nav button with hover highlight; works on `<button>` and `<a>` |
| `.sidebar-btn--active` | Active/current-page highlight (set server-side via PHP) |
| `.sidebar-btn--parent` | Modifier for buttons that have a sub-menu; holds `.sidebar-btn-caret` |
| `.sidebar-btn--open` | Applied when sub-menu is expanded; rotates caret 90° |
| `.sidebar-btn-caret` | Chevron icon inside parent button; auto-margin-left; fades color and rotates |
| `.sidebar-group` | Wrapper div around a parent button + its submenu; enables CSS hover flyout when collapsed |
| `.sidebar-submenu` | Hidden sub-menu container; `display: flex` (column) when `.open` |
| `.sidebar-submenu.open` | Expanded sub-menu state |
| `.sidebar-sub-btn` | Individual sub-menu link item; smaller font, `white-space: normal` (wraps) |
| `.sidebar-sub-btn.active` | Highlighted state for current page |

Collapsed behaviour: `.sidebar.collapsed` hides `.sidebar-btn-label` and `.sidebar-btn-caret`, hides `.sidebar-submenu`. `.sidebar.collapsed .sidebar-group:hover .sidebar-submenu` shows a `position: fixed` flyout to the right of the sidebar; `top` is set by JS on mouseenter.

## Client list
| Class | Use |
|-------|-----|
| `.client-row` | Single client list item — JS-rendered |
| `.badge-company` | Grey pill — legal entity — JS-rendered |
| `.badge-individual` | Amber pill — individual — JS-rendered |
| `.list-msg` | Empty/loading/error message; `.list-msg.err` for errors — JS-rendered |

## Invoice list
| Class | Use |
|-------|-----|
| `.inv-header` | Column header row (uppercase, grey) |
| `.inv-month-group` | Wrapper for one month's invoices; add `.is-first` to remove top border on first group — JS-rendered |
| `.inv-month-sep` | Grey uppercase month/year separator label — JS-rendered |
| `.inv-row` | Single invoice row — JS-rendered |
| `.inv-num` | Invoice number cell (bold, fixed min-width) — JS-rendered |
| `.inv-name` | Client name cell (flex: 1, truncated) — JS-rendered |
| `.inv-date` | Formatted date cell (grey, nowrap) — JS-rendered |
| `.inv-tag` | Status pill (neutral grey) — JS-rendered |

## Pagination
| Class | Use |
|-------|-----|
| `.page-btn` | Pagination button; `.active` = current page — JS-rendered |

`#clientsPager` and `#invoicesPager` containers now carry Tailwind classes directly (`flex flex-wrap gap-1.5 mt-4`).
