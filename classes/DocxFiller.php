<?php
/*
|------------------------------------------------------------------------------
| DocxFiller — fill [placeholder] values into .docx files
|------------------------------------------------------------------------------
| Imported documents (Типски Документи) are uploaded .docx files holding
| [name] / [number] style placeholders, filled in on download. Only .docx is
| supported (a .docx is a ZIP of XML, so PHP's built-in ZipArchive handles it
| with no external tools).
|
| The hard part is that Word splits a placeholder across several <w:r>/<w:t>
| "runs" (e.g. "[na" + "me]"), so a naive str_replace on document.xml misses it.
| We instead concatenate the text of all <w:t> runs in a part, locate the
| [..] spans in that combined text, then write the replacement into the FIRST
| run the span touches and blank out the remainder — preserving the first run's
| formatting and removing the leftover bracket fragments.
|
| Relevant parts: word/document.xml + every word/header*.xml / footer*.xml.
*/

class DocxFiller
{
    /** XML parts (inside the .docx zip) that can contain visible placeholders. */
    private const PART_RE = '#^word/(document|header\d+|footer\d+)\.xml$#';

    /**
     * Return the unique [placeholder] names found in a .docx, in first-seen order.
     *
     * @return string[]
     */
    public static function extractPlaceholders(string $docxPath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($docxPath) !== true) {
            throw new RuntimeException('Не може да се отвори .docx датотеката.');
        }

        $seen = [];
        $out  = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!preg_match(self::PART_RE, $name)) {
                continue;
            }
            $xml  = $zip->getFromIndex($i);
            $text = self::concatRunText($xml);
            if (preg_match_all('/\[([^\[\]]{1,200})\]/u', $text, $m)) {
                foreach ($m[1] as $raw) {
                    $key = trim(self::xmlDecode($raw));
                    if ($key === '' || isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $out[]      = $key;
                }
            }
        }
        $zip->close();
        return $out;
    }

    /**
     * Copy $srcDocx to $destDocx and replace every [key] with $values[key].
     * Keys not present in $values are left untouched (the bracket stays).
     */
    public static function fill(string $srcDocx, string $destDocx, array $values): void
    {
        if ($srcDocx !== $destDocx && !@copy($srcDocx, $destDocx)) {
            throw new RuntimeException('Не може да се копира датотеката за пополнување.');
        }

        $zip = new ZipArchive();
        if ($zip->open($destDocx) !== true) {
            throw new RuntimeException('Не може да се отвори .docx за пополнување.');
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!preg_match(self::PART_RE, $name)) {
                continue;
            }
            $xml    = $zip->getFromIndex($i);
            $filled = self::fillXmlPart($xml, $values);
            if ($filled !== $xml) {
                $zip->addFromString($name, $filled);
            }
        }
        $zip->close();
    }

    /* ────────────────────────────────────────────────────────── internals ── */

    /**
     * Concatenate the text content of every <w:t> run in an xml part, so a
     * placeholder split across runs reads as one continuous string. The
     * returned text is still XML-entity-encoded (brackets are literal, so
     * matching [..] works directly).
     */
    private static function concatRunText(string $xml): string
    {
        if (!preg_match_all('/<w:t\b[^>]*>(.*?)<\/w:t>/su', $xml, $m)) {
            return '';
        }
        return implode('', $m[1]);
    }

    /** Replace [key] spans in one xml part, run-split aware. */
    private static function fillXmlPart(string $xml, array $values): string
    {
        // Collect every <w:t> body with its byte offset/length in $xml.
        if (!preg_match_all('/<w:t\b[^>]*>(.*?)<\/w:t>/su', $xml, $m, PREG_OFFSET_CAPTURE)) {
            return $xml;
        }

        $segs = [];   // each: ['raw'=>text, 'xmlStart'=>int, 'xmlLen'=>int, 'gStart'=>int]
        $cum  = 0;    // running start index of each seg within the combined text
        foreach ($m[1] as $cap) {
            $raw = $cap[0];
            $segs[] = [
                'raw'      => $raw,
                'xmlStart' => $cap[1],
                'xmlLen'   => strlen($raw),
                'gStart'   => $cum,
                'ops'      => [],   // pending splices in raw-local coords
            ];
            $cum += strlen($raw);
        }

        $combined = '';
        foreach ($segs as $s) {
            $combined .= $s['raw'];
        }

        // Find each [placeholder] span in the combined text (byte offsets).
        if (!preg_match_all('/\[[^\[\]]{1,200}\]/s', $combined, $mm, PREG_OFFSET_CAPTURE)) {
            return $xml;
        }

        $changed = false;
        foreach ($mm[0] as $cap) {
            $span  = $cap[0];
            $start = $cap[1];
            $end   = $start + strlen($span);              // exclusive
            $key   = trim(self::xmlDecode(substr($span, 1, -1)));
            if (!array_key_exists($key, $values)) {
                continue;                                  // leave unknown placeholders intact
            }
            $repl = self::xmlEncode((string) $values[$key]);

            $segA = self::locate($segs, $start);           // segment of first char
            $segB = self::locate($segs, $end - 1);         // segment of last char
            if ($segA === null || $segB === null) {
                continue;
            }
            $la = $start - $segs[$segA]['gStart'];
            $lb = $end   - $segs[$segB]['gStart'];

            if ($segA === $segB) {
                $segs[$segA]['ops'][] = [$la, $lb, $repl];
            } else {
                $segs[$segA]['ops'][] = [$la, $segs[$segA]['xmlLen'], $repl];
                for ($k = $segA + 1; $k < $segB; $k++) {
                    $segs[$k]['ops'][] = [0, $segs[$k]['xmlLen'], ''];
                }
                $segs[$segB]['ops'][] = [0, $lb, ''];
            }
            $changed = true;
        }

        if (!$changed) {
            return $xml;
        }

        // Apply: rewrite each touched seg's body, splicing from the back to keep
        // local offsets valid; then rebuild $xml from back to front likewise.
        $result = $xml;
        for ($i = count($segs) - 1; $i >= 0; $i--) {
            $seg = $segs[$i];
            if (!$seg['ops']) {
                continue;
            }
            $body = $seg['raw'];
            usort($seg['ops'], static fn($a, $b) => $b[0] <=> $a[0]); // desc by start
            foreach ($seg['ops'] as [$s, $e, $ins]) {
                $body = substr($body, 0, $s) . $ins . substr($body, $e);
            }
            $result = substr($result, 0, $seg['xmlStart'])
                . $body
                . substr($result, $seg['xmlStart'] + $seg['xmlLen']);
        }
        return $result;
    }

    /** Index of the segment whose combined-text range contains $pos, or null. */
    private static function locate(array $segs, int $pos): ?int
    {
        foreach ($segs as $i => $s) {
            if ($pos >= $s['gStart'] && $pos < $s['gStart'] + $s['xmlLen']) {
                return $i;
            }
        }
        return null;
    }

    private static function xmlEncode(string $s): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);
    }

    private static function xmlDecode(string $s): string
    {
        return html_entity_decode($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
