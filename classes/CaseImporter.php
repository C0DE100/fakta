<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/CaseFile.php';

/**
 * Bulk CSV import for Предмети.
 *
 * One row = one case with a single client party (matched by name to an existing
 * clients row) and an optional opposing party (stored inline). Headers are in
 * Macedonian (case-insensitive, several aliases accepted); delimiter (,/;/tab)
 * and encoding (UTF-8 / Windows-1251, BOM-tolerant) are auto-detected.
 *
 * Flow: prepare() validates + resolves every row and returns a preview without
 * touching the DB; import() runs the valid rows through CaseFile::importBatch()
 * in a single transaction (all-or-nothing).
 */
class CaseImporter
{
    private Database $db;
    private CaseFile $cases;

    /** Canonical field => accepted header spellings (compared after normalize()). */
    private const ALIASES = [
        'basis'           => ['основ', 'osnov', 'предмет', 'наслов'],
        'value'           => ['вредност', 'vrednost', 'износ', 'сума'],
        'currency'        => ['валута', 'valuta'],
        'admin_number'    => ['административен број', 'админ број', 'админ. број', 'administrativen broj'],
        'client'          => ['клиент', 'klient', 'наш клиент', 'странка'],
        'client_role'     => ['својство на клиент', 'својство клиент', 'улога на клиент', 'svojstvo na klient'],
        'opponent'        => ['спротивна странка', 'спротивна', 'противник', 'sprotivna stranka'],
        'opponent_role'   => ['својство на спротивна', 'улога на спротивна', 'svojstvo na sprotivna'],
        'opponent_representative' => ['застапник', 'застапник на спротивна', 'полномошник', 'zastapnik'],
        'assignees'       => ['доделено на', 'зададено на', 'задолжени', 'вработени', 'zadadeno na'],
    ];

    public function __construct(Database $db, CaseFile $cases)
    {
        $this->db = $db;
        $this->cases = $cases;
    }

    /* ---------------------------------------------------------------------
     | Public: prepare (dry run) and import
     * ------------------------------------------------------------------- */

    /** Validate + resolve every row. No DB writes. */
    public function prepare(int $companyId, string $csv): array
    {
        [$header, $rows] = $this->parse($csv);
        $map = $this->mapHeaders($header);

        if (!isset($map['basis']) || !isset($map['client'])) {
            return [
                'fatal'   => 'CSV-то мора да има барем колони „Основ" и „Клиент". Преземи го шаблонот и обиди се повторно.',
                'columns' => $header,
            ];
        }

        $clientIndex = $this->buildClientIndex($companyId);
        $memberIndex = $this->buildMemberIndex($companyId);

        $out = [];
        $valid = 0;
        foreach ($rows as $n => $raw) {
            $row = $this->resolveRow($raw, $map, $clientIndex, $memberIndex);
            $row['line'] = $n + 2; // +1 header, +1 to 1-index
            if ($row['ok']) {
                $valid++;
            }
            $out[] = $row;
        }

        return [
            'rows'    => $out,
            'total'   => count($out),
            'valid'   => $valid,
            'invalid' => count($out) - $valid,
        ];
    }

    /** Import the valid rows in one transaction. */
    public function import(int $companyId, string $csv, ?int $createdBy): array
    {
        $prep = $this->prepare($companyId, $csv);
        if (isset($prep['fatal'])) {
            return $prep;
        }

        $payload = [];
        foreach ($prep['rows'] as $r) {
            if ($r['ok']) {
                $payload[] = $r['data'];
            }
        }

        if (!$payload) {
            return ['imported' => 0, 'invalid' => $prep['invalid'], 'message' => 'Нема валидни редови за импорт.'];
        }

        $res = $this->cases->importBatch($companyId, $payload, $createdBy);
        return ['imported' => $res['imported'], 'invalid' => $prep['invalid']];
    }

    /* ---------------------------------------------------------------------
     | Parsing
     * ------------------------------------------------------------------- */

    /** Decode, strip BOM, auto-detect delimiter, return [header[], rows[][]]. */
    private function parse(string $csv): array
    {
        // Normalize encoding to UTF-8.
        $csv = ltrim($csv, "\xEF\xBB\xBF"); // strip UTF-8 BOM
        if (!mb_check_encoding($csv, 'UTF-8')) {
            $converted = @iconv('Windows-1251', 'UTF-8//IGNORE', $csv);
            if ($converted !== false) {
                $csv = $converted;
            }
        }

        $delim = $this->detectDelimiter($csv);

        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $csv);
        rewind($fh);

        $header = [];
        $rows = [];
        $first = true;
        while (($cols = fgetcsv($fh, 0, $delim)) !== false) {
            // Skip fully empty lines.
            if ($cols === [null] || (count($cols) === 1 && trim((string) $cols[0]) === '')) {
                continue;
            }
            if ($first) {
                $header = array_map(fn($c) => trim((string) $c), $cols);
                $first = false;
            } else {
                $rows[] = array_map(fn($c) => trim((string) $c), $cols);
            }
        }
        fclose($fh);

        return [$header, $rows];
    }

    private function detectDelimiter(string $csv): string
    {
        $line = strtok($csv, "\r\n") ?: '';
        $counts = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];
        arsort($counts);
        $best = array_key_first($counts);
        return $counts[$best] > 0 ? $best : ',';
    }

    /** header column index => canonical field. */
    private function mapHeaders(array $header): array
    {
        $lookup = [];
        foreach (self::ALIASES as $field => $aliases) {
            foreach ($aliases as $a) {
                $lookup[$this->normalize($a)] = $field;
            }
        }
        $map = [];
        foreach ($header as $i => $name) {
            $key = $this->normalize($name);
            if (isset($lookup[$key])) {
                $map[$lookup[$key]] = $i;
            }
        }
        return $map;
    }

    private function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $s = str_replace(['.', '_', '-'], ' ', $s);
        return trim(preg_replace('/\s+/u', ' ', $s));
    }

    /* ---------------------------------------------------------------------
     | Resolution / validation
     * ------------------------------------------------------------------- */

    private function resolveRow(array $raw, array $map, array $clientIndex, array $memberIndex): array
    {
        $get = fn(string $field) => isset($map[$field]) && isset($raw[$map[$field]]) ? trim((string) $raw[$map[$field]]) : '';

        $errors = [];
        $warnings = [];

        $basis = $get('basis');
        if ($basis === '') {
            $errors[] = 'Недостасува основ';
        }

        // Client (must match an existing client by name).
        $clientName = $get('client');
        $clientId = null;
        if ($clientName === '') {
            $errors[] = 'Недостасува клиент';
        } else {
            $key = $this->normalize($clientName);
            $hits = $clientIndex[$key] ?? [];
            if (!$hits) {
                $errors[] = 'Клиентот „' . $clientName . '" не е најден';
            } elseif (count($hits) > 1) {
                $errors[] = 'Двосмислен клиент „' . $clientName . '" (повеќе совпаѓања)';
            } else {
                $clientId = $hits[0];
            }
        }

        // Value / currency.
        $valueRaw = $get('value');
        $value = null;
        if ($valueRaw !== '') {
            $value = $this->parseNumber($valueRaw);
            if ($value === null) {
                $warnings[] = 'Невалидна вредност — изоставена';
            }
        }
        $currency = $this->normalize($get('currency'));
        $currency = in_array($currency, ['евра', 'evra', 'eur', 'eвро', 'евро'], true) ? 'евра' : 'ден';

        // Opponent (optional).
        $oppName = $get('opponent');
        $parties = [['side' => 'client', 'client_id' => $clientId, 'role' => $get('client_role')]];
        if ($oppName !== '') {
            $parties[] = [
                'side'                    => 'opponent',
                'name'                    => $oppName,
                'opposing_representative' => $get('opponent_representative'),
                'role'                    => $get('opponent_role'),
            ];
        }

        // Assignees (names separated by ; or ,).
        $assigneeIds = [];
        $assigneeRaw = $get('assignees');
        if ($assigneeRaw !== '') {
            foreach (preg_split('/[;,]/u', $assigneeRaw) as $nm) {
                $nm = trim($nm);
                if ($nm === '') {
                    continue;
                }
                $mid = $memberIndex[$this->normalize($nm)] ?? null;
                if ($mid) {
                    $assigneeIds[] = $mid;
                } else {
                    $warnings[] = 'Вработен „' . $nm . '" не е најден — прескокнат';
                }
            }
        }

        $ok = empty($errors);

        return [
            'ok'       => $ok,
            'errors'   => $errors,
            'warnings' => $warnings,
            // What the user sees in the preview.
            'preview'  => [
                'basis'    => $basis,
                'client'   => $clientName,
                'opponent' => $oppName,
                'value'    => $valueRaw !== '' ? $valueRaw . ' ' . $currency : '',
            ],
            // What gets imported (only meaningful when ok).
            'data'     => [
                'basis'          => $basis,
                'value_amount'   => $value,
                'value_currency' => $currency,
                'admin_number'   => $get('admin_number'),
                'parties'        => $parties,
                'assignees'      => $assigneeIds,
            ],
        ];
    }

    /** Robust MK/EU number parse: "15.000,50" / "15000,50" / "15000.50" → float. */
    private function parseNumber(string $s): ?float
    {
        $s = preg_replace('/[^\d.,\-]/', '', $s);
        if ($s === '' || $s === '-') {
            return null;
        }
        $hasComma = strpos($s, ',') !== false;
        $hasDot   = strpos($s, '.') !== false;
        if ($hasComma && $hasDot) {
            // The last separator is the decimal one; the other groups thousands.
            if (strrpos($s, ',') > strrpos($s, '.')) {
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            } else {
                $s = str_replace(',', '', $s);
            }
        } elseif ($hasComma) {
            $s = str_replace(',', '.', $s);
        }
        return is_numeric($s) ? (float) $s : null;
    }

    /* ---------------------------------------------------------------------
     | Lookup indexes
     * ------------------------------------------------------------------- */

    /** normalized client name => [ids]. Multiple ids = ambiguous. */
    private function buildClientIndex(int $companyId): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, type, company_name, full_name
             FROM clients WHERE company_id = :cid AND deleted_at IS NULL"
        );
        $stmt->execute([':cid' => $companyId]);
        $index = [];
        foreach ($stmt->fetchAll() as $c) {
            $name = $c['type'] === 'company' ? $c['company_name'] : $c['full_name'];
            if ($name === null || trim($name) === '') {
                continue;
            }
            $index[$this->normalize($name)][] = (int) $c['id'];
        }
        return $index;
    }

    /** normalized member name => id (first wins on dup names). */
    private function buildMemberIndex(int $companyId): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, name FROM users
             WHERE company_id = :cid AND role IN ('admin','employee','praktikant')"
        );
        $stmt->execute([':cid' => $companyId]);
        $index = [];
        foreach ($stmt->fetchAll() as $u) {
            $key = $this->normalize($u['name']);
            if (!isset($index[$key])) {
                $index[$key] = (int) $u['id'];
            }
        }
        return $index;
    }
}
