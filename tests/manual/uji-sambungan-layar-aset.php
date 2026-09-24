<?php
/*
 * Sambungan layar aset registry ke PicAssignmentService.
 *
 * Yang diuji: apakah jalur barunya benar-benar terpasang, dan apakah aturan
 * yang sudah lulus di tingkat service ikut terlihat di tingkat layar.
 */

$root = 'D:/laragon-www/kominfo/nawasara-dev';
require $root.'/vendor/autoload.php';

$gagal = 0;
$cek = function (string $l, bool $ok, string $d = '') use (&$gagal) {
    if (! $ok) { $gagal++; }
    echo ($ok ? '  OK   ' : '  GAGAL')." {$l}".($d ? "\n         {$d}" : '')."\n";
};

$tabel = file_get_contents($root.'/packages/nawasara-registry/src/Livewire/Asset/Section/Table.php');
$blade = file_get_contents($root.'/packages/nawasara-registry/resources/views/livewire/pages/asset/section/table.blade.php');
$picker = file_get_contents($root.'/packages/nawasara-registry/src/Livewire/PicPicker.php');

echo "=== layar aset memakai service, bukan menulis pj_user_id sendiri ===\n";
$cek('memanggil PicAssignmentService', str_contains($tabel, 'PicAssignmentService::class'));
$cek('payload updateOrCreate TIDAK memuat pj_user_id',
    ! preg_match('/\$payload = \[[^;]*pj_user_id/s', $tabel));
$cek('menangkap PicMismatchException', str_contains($tabel, 'catch (PicMismatchException'));
$cek('menangkap SimasHebatException', str_contains($tabel, 'catch (SimasHebatException'));

echo "\n=== yang tertahan TIDAK tersimpan diam-diam ===\n";
// Di cabang catch harus ada `return;` sebelum toastSuccess.
preg_match('/catch \(PicMismatchException.*?\n(.*?)\n\s+\} catch/s', $tabel, $m);
$cek('cabang mismatch berhenti (return)', str_contains($m[1] ?? '', 'return;'));
$cek('tidak ada toastSuccess di cabang mismatch', ! str_contains($m[1] ?? '', 'toastSuccess'));

echo "\n=== komponen bersama dipasang di blade ===\n";
$cek('livewire:nawasara-registry.pic-picker dipakai', str_contains($blade, '<livewire:nawasara-registry.pic-picker'));
$cek('punya :key supaya tidak tertukar antar-baris', preg_match('/pic-picker[^>]*:key=/s', $blade) === 1);
$cek('select PIC lama sudah dibuang', ! str_contains($blade, 'wire:model="assetPjUserId"'));
$cek('kotak perbandingan ada', str_contains($blade, '$picMismatch'));
$cek('menampilkan KEDUA sisi', str_contains($blade, 'Tercatat di Nawasara') && str_contains($blade, 'Menurut SIMAS Hebat'));
$cek('tombol lanjut untuk SIMAS mati', str_contains($blade, 'Lanjutkan tanpa verifikasi'));

echo "\n=== event antara picker dan tuan rumah ===\n";
$cek('picker mengirim pic-selected', str_contains($picker, "dispatch('pic-selected'"));
$cek('tuan rumah mendengarkannya', str_contains($tabel, "#[On('pic-selected')]"));
$cek('picker mendengar reset', str_contains($picker, "#[On('pic-picker-reset')]"));
$cek('tuan rumah mengirim reset', str_contains($tabel, "dispatch('pic-picker-reset')"));

echo "\n=== payload event cocok dengan yang dibaca ===\n";
preg_match("/dispatch\('pic-selected', \[(.*?)\]\)/s", $picker, $kirim);
$kunciKirim = [];
preg_match_all("/'([a-z_]+)' =>/", $kirim[1] ?? '', $kk);
$kunciKirim = $kk[1] ?? [];
preg_match('/function onPicSelected\(array \$payload\).*?\}/s', $tabel, $terima);
foreach ($kunciKirim as $k) {
    $cek("kunci '{$k}' dibaca tuan rumah", str_contains($terima[0] ?? '', "'{$k}'"));
}

echo "\n=== pjCandidates tidak lagi dobel di registry ===\n";
$cek('tidak ada lagi di Table.php', ! preg_match('/function pjCandidates/', $tabel));
$cek('ada di PicPicker', str_contains($picker, 'getPjCandidatesProperty'));

echo "\n=== import mati sudah dibuang ===\n";
foreach (['KeycloakProfile', 'Membership'] as $k) {
    $pakai = substr_count($tabel, $k);
    $cek("{$k} tidak lagi di-import tanpa dipakai", $pakai === 0, "muncul {$pakai}x");
}

echo "\n=== sintaks PHP ===\n";
foreach ([
    'src/Livewire/Asset/Section/Table.php',
    'src/Livewire/PicPicker.php',
    'src/Services/PicAssignmentService.php',
    'src/Services/SimasHebatClient.php',
    'src/Services/UnitKerjaMatcher.php',
] as $f) {
    $out = [];
    exec('"C:/laragon/bin/php/php-8.4.10-nts-Win32-vs17-x64/php.exe" -l "'.$root.'/packages/nawasara-registry/'.$f.'" 2>&1', $out, $rc);
    $cek(basename($f), $rc === 0, $rc === 0 ? '' : implode(' ', $out));
}

echo "\n".($gagal === 0 ? 'SEMUA LULUS' : "ADA {$gagal} YANG GAGAL")."\n";
exit($gagal === 0 ? 0 : 1);
