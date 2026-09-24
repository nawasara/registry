<?php
/*
 * Apakah pic-picker benar-benar ter-register dan blade-nya nyambung?
 *
 * Komponen dan slot yang salah di repo ini gagal DIAM-DIAM: tidak ada galat,
 * isinya hanya tidak muncul. Jadi yang diperiksa keberadaan isinya, bukan
 * ketiadaan exception.
 */

$root = 'D:/laragon-www/kominfo/nawasara-dev';
require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->instance('request', Illuminate\Http\Request::create('/', 'GET'));
$app->make(Illuminate\Contracts\Http\Kernel::class)->bootstrap();

$gagal = 0;
$cek = function (string $l, bool $ok, string $d = '') use (&$gagal) {
    if (! $ok) { $gagal++; }
    echo ($ok ? '  OK   ' : '  GAGAL')." {$l}".($d ? "\n         {$d}" : '')."\n";
};

echo "=== alias Livewire ===\n";
try {
    $reg = app(\Livewire\Mechanisms\ComponentRegistry::class);
    $kelas = $reg->getClass('nawasara-registry.pic-picker');
    $cek('alias terdaftar', $kelas === \Nawasara\Registry\Livewire\PicPicker::class, 'dapat: '.$kelas);
} catch (\Throwable $e) {
    $cek('alias terdaftar', false, $e->getMessage());
}

$blade = file_get_contents($root.'/packages/nawasara-registry/resources/views/livewire/pic-picker.blade.php');
$dirUi = $root.'/packages/nawasara-ui/resources/views/components/';

echo "\n=== komponen nawasara-ui yang dipakai memang ada ===\n";
preg_match_all('/<x-nawasara-ui::([a-z0-9.\-]+)/i', $blade, $m);
foreach (array_unique($m[1]) as $komponen) {
    $path = $dirUi.str_replace('.', '/', $komponen).'.blade.php';
    $cek("komponen {$komponen} ada", file_exists($path), file_exists($path) ? '' : "dicari di: {$path}");
}

echo "\n=== prop sampai ke komponennya ===\n";
/*
 * Prop yang TIDAK dideklarasikan di @props tetap sampai ke elemennya lewat
 * $attributes->merge(). Jadi `disabled` di form.select dan `placeholder` di
 * form.input bukan kesalahan, asalkan komponennya meneruskan sisa atribut.
 */
$select = file_get_contents($dirUi.'form/select.blade.php');
foreach (['options', 'label', 'placeholder'] as $prop) {
    $cek("form.select memakai \${$prop}", str_contains($select, '$'.$prop));
}
$cek('form.select meneruskan sisa atribut (disabled dll.)', str_contains($select, '$attributes->merge'));

$input = file_get_contents($dirUi.'form/input.blade.php');
$cek('form.input memakai label', str_contains($input, "attributes['label']"));
$cek('form.input meneruskan sisa atribut (type, placeholder)', str_contains($input, '$attributes->merge'));

echo "\n=== jebakan grid: form.input wajib dibungkus div ===\n";
$cek('form.select PUNYA pembungkus sendiri', str_contains($select, '<div class="flex flex-col gap-1'));
$cek('form.input menyatakan TIDAK punya pembungkus', str_contains($input, 'Tidak punya pembungkus'));

$jumlahInput = substr_count($blade, '<x-nawasara-ui::form.input');
$dibungkus = preg_match_all('/<div[^>]*>\s*<x-nawasara-ui::form\.input/', $blade);
echo "  form.input dipakai {$jumlahInput}x, dibungkus {$dibungkus}x\n";
$cek('setiap form.input dibungkus div', $dibungkus === $jumlahInput);

echo "\n=== dark mode: setiap warna terang punya pasangan gelap ===\n";
preg_match_all('/\b(text|bg|border)-(gray|neutral|emerald|rose|amber|sky|blue)-(\d{2,3})(\/\d+)?\b/', $blade, $warna, PREG_SET_ORDER);
$tanpaDark = [];
foreach ($warna as $w) {
    $penuh = $w[0];
    foreach (explode("\n", $blade) as $baris) {
        if (str_contains($baris, $penuh) && ! preg_match('/dark:'.preg_quote($w[1], '/').'-/', $baris)) {
            $tanpaDark[$penuh] = trim($baris);
        }
    }
}
$cek('tidak ada warna tanpa pasangan gelap', count($tanpaDark) === 0,
    count($tanpaDark) ? implode("\n         ", array_keys($tanpaDark)) : '');

echo "\n=== label yang dilihat staf berbahasa Indonesia ===\n";
preg_match_all('/(?:label|placeholder)="([^"]+)"/', $blade, $label);
$inggris = array_filter($label[1], fn ($t) => preg_match('/^(Select|Choose|Search|Name|Status|Delete|Edit|Save|Cancel)\b/i', $t));
$cek('tidak ada label Inggris', count($inggris) === 0, implode(', ', $inggris));

echo "\n=== properti yang dipakai blade memang ada di komponennya ===\n";
$php = file_get_contents($root.'/packages/nawasara-registry/src/Livewire/PicPicker.php');
foreach (['opdId', 'pjUserId', 'nip', 'found', 'lookupError'] as $p) {
    $cek("\${$p} ada di komponen", preg_match('/public [^\s]+ \$'.$p.'\b/', $php) === 1);
}
foreach (['opdOptions', 'pjCandidates'] as $p) {
    $cek("\$this->{$p} punya getter", str_contains($php, 'get'.ucfirst($p).'Property'));
}
foreach (['lookup', 'clearLookup'] as $metode) {
    $cek("wire:click=\"{$metode}\" punya metodenya", str_contains($php, "function {$metode}("));
}

echo "\n".($gagal === 0 ? 'SEMUA LULUS' : "ADA {$gagal} YANG GAGAL")."\n";
exit($gagal === 0 ? 0 : 1);
