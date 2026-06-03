# API

## client_api.php

Single endpoint. Action selected via `action` param (POST body or GET query).

### `create_company` — POST
Creates a legal entity client.

| Field | Required |
|-------|---------|
| `company_name` | yes |
| `headquarters` | yes |
| `embs` | yes |
| `edb` | yes |
| `manager` | yes |

Response: `{ success, message, id }`

---

### `create_individual` — POST
Creates an individual client. `embg` and `id_card_number` are AES-256-CBC encrypted before storage.

| Field | Required |
|-------|---------|
| `full_name` | yes |
| `address` | yes |
| `embg` | yes |
| `id_card_number` | yes |

Response: `{ success, message, id }`

---

### `get_all` — GET
Returns all clients ordered by `created_at DESC`. Encrypted fields are decrypted before returning.

Response: `{ success, data: Client[] }`

---

### `get_one` — GET
Returns a single client by `id`. Encrypted fields are decrypted.

Query param: `id` (int, > 0)

Response: `{ success, data: Client | null }`

---

## invoice_api.php

### `get_list` — GET
Returns a paginated, server-filtered list of invoices joined with client names.

| Param | Type | Default | Notes |
|-------|------|---------|-------|
| `search` | string | `''` | Matches `number`, client name, or `date` |
| `month` | string | `''` | Format `YYYY-MM` — filters by month |
| `client_id` | int | `0` | `0` = all clients |
| `page` | int | `1` | 1-based |

Response:
```json
{
  "success": true,
  "data": [{ "id", "number", "date", "status", "client_name" }],
  "total": 42,
  "pages": 5,
  "page": 1
}
```

10 invoices per page. Ordered by `date DESC, id DESC`.

---

## Error response (all endpoints)
`{ success: false, message: "..." }`

## Tables used
- `clients` — see `classes/classes.md`
- `invoices` — see `classes/classes.md`
