<?php
/*
 * Pemisahan nama OPD dari nama bidang saat OPD-nya belum dikenali.
 *
 * Kejadian yang memicunya: KECAMATAN BALONG terdaftar dengan simas_name
 * "KECAMATAN BALONG SEKSI PELAYANAN UMUM - KECAMATAN BALONG". Nama
 * tampilannya sudah dirapikan admin, tetapi kunci pencocokannya memuat
 * seluruh teks, sehingga pegawai lain dari kecamatan yang sama TIDAK
 * dikenali, tanpa galat apa pun.
 */

use Livewire\Livewire;
use Nawasara\Registry\Livewire\PicPicker;
use Nawasara\Registry\Models\Opd;
use Nawasara\Registry\Models\Unit;
use Nawasara\Registry\Services\PicAssignmentService;
use Nawasara\Registry\Services\SimasHebatClient;
use Nawasara\Registry\Services\UnitKerjaMatcher;

$gagal = 0;
$cek = function (string $l, bool $ok, string $d = '') use (&$gagal) {
    if (! $ok) { $gagal++; }
    echo ($ok ? '  OK   ' : '  GAGAL')." {$l}".($d ? "\n         {$d}" : '')."\n";
};

class SimasVar extends SimasHebatClient {
    public function __construct(private string $unitKerja) { parent::__construct('http://x','t','a'); }
    public function pegawai(string $n): array {
        return ['nama_lengkap'=>'UJI ORANG','nama_jabatan'=>'STAF',
            'jenis_jabatan'=>'Jabatan Fungsional','unit_kerja'=>$this->unitKerja,
            'jenis_kepegawaian'=>'PNS'];
    }
}
$pasang = fn (string $uk) => app()->bind(PicAssignmentService::class,
    fn () => new PicAssignmentService(new SimasVar($uk), new UnitKerjaMatcher));

echo "=== tebakan pemisahan untuk OPD yang belum dikenali ===\n";

/*
 * Yang diuji cabang "OPD belum dikenali", jadi OPD ujinya harus benar-benar
 * tidak ada. Balong dan Sekretariat Daerah sempat terdaftar dari pengujian
 * sebelumnya, dan itu membuat uji ini menjawab kosong alih-alih salah potong.
 */
Opd::whereIn('code', ['BALONG', 'SETDAUJI', 'SETDA', 'UJIKEC'])->delete();
Opd::where('simas_name', 'like', '%BALONG%')->delete();
Opd::where('simas_name', 'like', '%SEKRETARIAT DAERAH%')->delete();
Opd::where('simas_name', 'like', '%PENGHUBUNG%')->delete();

$kasus = [
    ['KECAMATAN BALONG SEKSI PELAYANAN UMUM - KECAMATAN BALONG', 'KECAMATAN BALONG', 'SEKSI PELAYANAN UMUM'],
    ['KECAMATAN SAMPUNG SEKSI PEMERINTAHAN',                     'KECAMATAN SAMPUNG', 'SEKSI PEMERINTAHAN'],
    ['SEKRETARIAT DAERAH BAGIAN PEMERINTAHAN',                   'SEKRETARIAT DAERAH', 'BAGIAN PEMERINTAHAN'],
    ['BADAN PENGHUBUNG BIDANG KERJA SAMA',                       'BADAN PENGHUBUNG', 'BIDANG KERJA SAMA'],
    ['DINAS SESUATU UPT LABORATORIUM',                           'DINAS SESUATU', 'UPT LABORATORIUM'],
    // Tanpa penanda: seluruhnya dianggap nama OPD, dan itu memang benar.
    ['BADAN TANPA PENANDA APA PUN',                              'BADAN TANPA PENANDA APA PUN', ''],
];

foreach ($kasus as [$in, $opdHarap, $subHarap]) {
    $pasang($in);
    $c = Livewire::test(PicPicker::class)->set('nip', '199999999999999999')->call('lookup')->call('review');

    $opd = $c->get('newOpdSimasName');
    $sub = $c->get('newUnitSimasName');

    echo '  '.substr($in, 0, 56)."\n";
    $cek('   OPD  = '.$opdHarap, $opd === $opdHarap, 'dapat: '.$opd);
    $cek('   sub  = '.($subHarap ?: '(kosong)'), $sub === $subHarap, 'dapat: '.$sub);
}

echo "\n=== peringatan muncul kalau nama OPD masih memuat bidang ===\n";
$pasang('KECAMATAN BALONG SEKSI PELAYANAN UMUM');
$c = Livewire::test(PicPicker::class)->set('nip', '199999999999999999')->call('lookup')->call('review');

$cek('terpisah, jadi tidak ada peringatan', $c->instance()->getOpdNameLooksUnsplitProperty() === false);

// Admin menempelkan kembali seluruh teksnya.
$c->set('newOpdSimasName', 'KECAMATAN BALONG SEKSI PELAYANAN UMUM');
$cek('peringatan MUNCUL', $c->instance()->getOpdNameLooksUnsplitProperty() === true);

echo "\n=== INTI: pegawai berikutnya dari OPD yang sama dikenali ===\n";
Opd::where('code', 'UJIKEC')->delete();

$pasang('KECAMATAN UJICOBA SEKSI PELAYANAN UMUM - KECAMATAN UJICOBA');
$c2 = Livewire::test(PicPicker::class)->set('nip', '199999999999999999')->call('lookup')->call('review');
$c2->set('newOpdCode', 'UJIKEC')->set('newOpdName', 'Kecamatan Ujicoba')->call('saveCreate');
$cek('tidak ada galat', $c2->get('createError') === '', $c2->get('createError'));

$baru = Opd::where('code', 'UJIKEC')->first();
$cek('simas_name HANYA nama OPD', $baru?->simas_name === 'KECAMATAN UJICOBA', 'dapat: '.$baru?->simas_name);
$cek('bidangnya terdaftar terpisah',
    Unit::where('opd_id', $baru?->id)->where('simas_name', 'SEKSI PELAYANAN UMUM')->exists());

$m = new UnitKerjaMatcher;
foreach ([
    'KECAMATAN UJICOBA SEKSI PEMERINTAHAN',
    'KECAMATAN UJICOBA',
] as $t) {
    $h = $m->match($t);
    $cek("'{$t}' dikenali", $h['opd']?->id === $baru?->id, 'dapat: '.($h['opd']?->name ?? 'TIDAK DIKENALI'));
}

Opd::where('code', 'UJIKEC')->delete();

echo "\n".($gagal === 0 ? 'SEMUA LULUS' : "ADA {$gagal} YANG GAGAL")."\n";
