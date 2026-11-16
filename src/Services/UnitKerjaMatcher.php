<?php

namespace Nawasara\Registry\Services;

use Nawasara\Registry\Models\Opd;

/**
 * Splits SIMAS Hebat's `unit_kerja` into an OPD and a sub-unit.
 *
 * The field arrives as `<OPD NAME><space><SUB-UNIT NAME>` with no separator:
 *
 *   SEKRETARIAT DAERAH
 *   DINAS PENDIDIKAN SD NEGERI 1 TEGALREJO
 *   DINAS KOMUNIKASI, INFORMATIKA DAN STATISTIK BIDANG APLIKASI DAN INFORMATIKA
 *
 * Splitting it by rule fails: OPD names contain commas and the word "DAN", so
 * exploding on a comma or hunting for "BIDANG" is wrong on some of them. What
 * works is asking the opposite question: does this text START WITH an OPD we
 * already know? The OPD is always the prefix, so the longest known prefix wins
 * and whatever remains is the sub-unit.
 *
 * This guesses nothing, and that is the point. An unrecognised prefix returns
 * no OPD rather than a near-miss, because a wrong match here hands someone
 * another OPD's data.
 */
class UnitKerjaMatcher
{
    /**
     * Normalise for comparison only: upper case, punctuation to spaces,
     * repeated spaces collapsed.
     *
     * Registry writes `Badan Kepegawaian dan Pengembangan Sumber Daya Manusia`
     * in title case while SIMAS Hebat writes it in caps; one production row has
     * a trailing space in its code. Normalising makes all of those match.
     */
    public static function normalize(string $value): string
    {
        $value = mb_strtoupper(trim($value));
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    /**
     * Match `unit_kerja` against known OPDs.
     *
     * @return array{opd: ?Opd, sub_unit: ?string, raw: string}
     *         `opd` null when no known name is a prefix of the text.
     *         `sub_unit` null when the text IS the OPD name with nothing after.
     */
    public function match(string $unitKerja): array
    {
        $haystack = self::normalize($unitKerja);

        $best = null;
        $bestLength = 0;

        foreach ($this->candidates() as $opd => $needle) {
            if ($needle === '' || ! str_starts_with($haystack, $needle)) {
                continue;
            }

            // A prefix must end at a word boundary: "DINAS PERTANIAN" must not
            // match "DINAS PERTANIANX", and the whole string is fine too.
            $next = mb_substr($haystack, mb_strlen($needle), 1);
            if ($next !== '' && $next !== ' ') {
                continue;
            }

            // Longest wins, so "DINAS PENDIDIKAN DAN KEBUDAYAAN" beats
            // "DINAS PENDIDIKAN" when both exist.
            if (mb_strlen($needle) > $bestLength) {
                $best = $opd;
                $bestLength = mb_strlen($needle);
            }
        }

        if ($best === null) {
            return ['opd' => null, 'sub_unit' => null, 'raw' => $unitKerja];
        }

        $remainder = trim(mb_substr($haystack, $bestLength));

        return [
            'opd' => $best,
            'sub_unit' => $remainder === '' ? null : $remainder,
            'raw' => $unitKerja,
        ];
    }

    /**
     * Match a SIMAS reply, preferring the split `skpd` + `unit` fields.
     *
     * Since 12 November 2026 SIMAS sends the OPD on its own (`skpd`), so the
     * OPD must match it WHOLE: a prefix hit would let a registry entry
     * "DINAS PENDIDIKAN" claim someone whose skpd is "DINAS PENDIDIKAN DAN
     * KEBUDAYAAN". The sub-unit is `unit`, normalised the same way the prefix
     * remainder always was, so units created before the split still match.
     *
     * Replies without `skpd` (older stored ones) fall back to the prefix match.
     *
     * @param  array{skpd?: ?string, unit?: ?string, unit_kerja?: ?string}  $simas
     * @return array{opd: ?Opd, sub_unit: ?string, raw: string}
     */
    public function matchSimas(array $simas): array
    {
        $skpd = trim((string) ($simas['skpd'] ?? ''));

        if ($skpd === '') {
            return $this->match((string) ($simas['unit_kerja'] ?? ''));
        }

        $found = $this->match($skpd);
        $opd = $found['opd'] !== null && $found['sub_unit'] === null ? $found['opd'] : null;

        $unit = self::normalize((string) ($simas['unit'] ?? ''));

        return [
            'opd' => $opd,
            'sub_unit' => $opd !== null && $unit !== '' ? $unit : null,
            'raw' => $skpd,
        ];
    }

    /**
     * Every OPD paired with the normalised name to match against.
     *
     * `simas_name` is preferred when set, since it is bound to the source's
     * spelling; `name` is the fallback so the matcher works before any OPD has
     * been through this screen.
     *
     * @return \Generator<Opd, string>
     */
    protected function candidates(): \Generator
    {
        foreach (Opd::all() as $opd) {
            yield $opd => self::normalize((string) ($opd->simas_name ?: $opd->name));
        }
    }
}
