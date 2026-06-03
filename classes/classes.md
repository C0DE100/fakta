# Classes

## Database
`Database.php` — PDO wrapper with lazy connection.

| Method | Returns | Notes |
|--------|---------|-------|
| `__construct(host, dbName, username, password)` | — | Stores credentials; does not connect yet |
| `getConnection()` | `PDO` | Connects on first call; reuses connection |
| `prepare(sql)` | `PDOStatement` | Calls `getConnection()->prepare()` |
| `lastInsertId()` | `string` | Delegates to PDO |

PDO config: `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, emulated prepares off, charset `utf8mb4`.

---

## Encryption
`Encryption.php` — AES-256-CBC via OpenSSL.

Key is derived with `sha256(passphrase)` to produce a 256-bit key.
IV is random per encryption, stored as `base64(iv . '::' . ciphertext)`.

| Method | Notes |
|--------|-------|
| `__construct(key)` | Derives 256-bit key from passphrase |
| `encrypt(plainText)` | Returns base64-encoded `iv::ciphertext` |
| `decrypt(encryptedText)` | Returns plaintext, or `'[Грешка при декрипција]'` on failure |

---

## Client
`Client.php` — CRUD for the `clients` table.

Encrypted fields for individuals: `embg`, `id_card_number`.
No encrypted fields for companies.

| Method | Returns | Notes |
|--------|---------|-------|
| `createCompany(companyName, headquarters, embs, edb, manager)` | `int` | Inserted row ID |
| `createIndividual(fullName, address, embg, idCardNumber)` | `int` | Encrypts `embg` and `idCardNumber` |
| `getAll()` | `array` | All clients, decrypted, ordered by `created_at DESC` |
| `getById(id)` | `?array` | Single client, decrypted, or `null` |

---

## Invoice
`Invoice.php` — Read-only access to the `invoices` table.

| Method | Returns | Notes |
|--------|---------|-------|
| `getList(search, month, clientId, page)` | `array` | Paginated + filtered list; 10 per page |

`getList` returns: `{ data[], total, pages, page }`

Each row in `data`: `id`, `number`, `date`, `status`, `client_name` (resolved from join).

Filters applied server-side: full-text search across `number`, client name, `date`; exact month match; exact client match.

---

## Database schema

### Table: `clients`

| Column | Notes |
|--------|-------|
| `id` | PK, auto-increment |
| `type` | `'company'` or `'individual'` |
| `company_name` | company only |
| `headquarters` | company only |
| `embs` | company only |
| `edb` | company only |
| `manager` | company only |
| `full_name` | individual only |
| `address` | individual only |
| `embg` | individual only — encrypted |
| `id_card_number` | individual only — encrypted |
| `created_at` | `datetime`, set to `NOW()` on insert |

### Table: `invoices`

| Column | Notes |
|--------|-------|
| `id` | PK, auto-increment |
| `number` | varchar — invoice number |
| `date` | invoice date |
| `status` | varchar — possible values TBD |
| `client_id` | int, FK → `clients.id` — confirmed |
