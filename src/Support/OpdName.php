<?php

namespace Nawasara\Registry\Support;

/**
 * Merapikan nama OPD yang tertulis HURUF KAPITAL SEMUA, mis.
 * "DINAS KOMUNIKASI, INFORMATIKA DAN STATISTIK" →
 * "Dinas Komunikasi, Informatika dan Statistik".
 *
 * Nama kapital semua datang dari SIMAS Hebat (unit_kerja ditulis kapital) dan
 * tampil berdampingan dengan nama yang sudah rapi — di daftar OPD, pilihan
 * filter, dan laporan, campuran itu terlihat seperti data rusak.
 *
 * Hanya nama yang SELURUHNYA kapital yang diubah. Nama yang sudah memakai
 * huruf kecil di mana pun dianggap sengaja ditulis begitu oleh admin dan
 * dibiarkan, sehingga "Sekretariat DPRD" tidak berubah.
 *
 * Kolom `simas_name` TIDAK pernah dilewatkan ke sini: itu kunci pencocokan
 * dengan jawaban SIMAS (lihat UnitKerjaMatcher), dan harus tetap persis seperti
 * sumbernya.
 */
class OpdName
{
    /** Kata sambung/depan yang ditulis kecil kecuali di awal nama (PUEBI). */
    private const LOWER = ['dan', 'di', 'ke', 'dari', 'untuk', 'serta', 'atau', 'yang', 'pada', 'dengan', 'bagi', 'oleh'];

    /** Singkatan yang tetap kapital di dalam nama. */
    private const ACRONYMS = ['DPRD', 'RSUD', 'RS', 'UPT', 'UPTD', 'PKK', 'KB', 'BLUD', 'PDAM', 'BUMD', 'SD', 'SMP', 'SMA', 'SMK', 'TK', 'PAUD', 'PPID', 'KUA', 'UMKM', 'IKM', 'PMI', 'KONI'];

    public static function isAllCaps(string $name): bool
    {
        return preg_match('/\p{L}/u', $name) === 1 && mb_strtoupper($name) === $name;
    }

    /** Nama rapi untuk $name; dikembalikan apa adanya bila bukan kapital semua. */
    public static function tidy(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);

        if (! self::isAllCaps($name)) {
            return $name;
        }

        $words = explode(' ', $name);

        foreach ($words as $i => $word) {
            $words[$i] = self::word($word, $i === 0);
        }

        return implode(' ', $words);
    }

    private static function word(string $word, bool $first): string
    {
        // Pisahkan tanda baca di tepi kata ("INFORMATIKA," / "(UPT)") supaya
        // pemeriksaan singkatan dan kata sambung melihat katanya saja.
        if (! preg_match('/^(\P{L}*)(.*?)(\P{L}*)$/u', $word, $m)) {
            return $word;
        }

        [, $lead, $core, $trail] = $m;

        if ($core === '') {
            return $word;
        }

        if (in_array($core, self::ACRONYMS, true)) {
            return $lead.$core.$trail;
        }

        $lower = mb_strtolower($core);

        if (! $first && in_array($lower, self::LOWER, true)) {
            return $lead.$lower.$trail;
        }

        // Kata bertanda hubung: tiap bagian diawali huruf besar ("Kota-Desa").
        $parts = array_map(
            fn (string $p) => mb_strtoupper(mb_substr($p, 0, 1)).mb_substr($p, 1),
            explode('-', $lower),
        );

        return $lead.implode('-', $parts).$trail;
    }
}
