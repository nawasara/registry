<?php
/*
 * Langkah pemeriksaan sebelum apa pun masuk basis data.
 *
 * Yang diuji:
 *  - langkah itu SELALU lewat, termasuk saat semuanya sudah dikenali
 *  - tidak ada satu baris pun tertulis sebelum tombol terakhir
 *  - admin dapat mengoreksi meski sistem merasa yakin
 */

use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Nawasara\Registry\Livewire\PicPicker;
use Nawasara\Registry\Models\Employee;
use Nawasara\Registry\Models\Membership;
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

$nip = '197810202002121002';

class SimasAtur extends SimasHebatClient {
    public function __construct(private array $j) { parent::__construct('http://x','t','a'); }
    public function pegawai(string $n): array { return $this->j; }
}

$pasang = function (string $unitKerja) {
    app()->bind(PicAssignmentService::class, fn () => new PicAssignmentService(
        new SimasAtur([
            'nama_lengkap' => 'BAYUS ATDINATA S.KOM',
            'nama_jabatan' => 'KEPALA BIDANG',
            'jenis_jabatan' => 'Jabatan Struktural',
            'unit_kerja' => $unitKerja,
            'jenis_kepegawaian' => 'PNS',
        ]),
        new UnitKerjaMatcher
    ));
};

$hitung = fn () => [
    'opd' => Opd::count(),
    'unit' => Unit::count(),
    'employee' => Employee::count(),
    'membership' => Membership::count(),
];

echo "=== A. semuanya SUDAH dikenali: pemeriksaan tetap lewat ===\n";
$pasang('DINAS KOMUNIKASI, INFORMATIKA DAN STATISTIK BIDANG APLIKASI DAN INFORMATIKA');

$c = Livewire::test(PicPicker::class)->set('nip', $nip)->call('lookup');
$cek('OPD dikenali', ($c->get('found')['opd'] ?? null) !== null);

$c->call('review');
$cek('langkah pemeriksaan TERBUKA meski sudah dikenali', $c->get('creatingOpd') === true);
$cek('kolom OPD kosong karena sudah ada', $c->get('newOpdSimasName') === '');
$cek('bidang terisi dari hasil pencocokan', $c->get('newUnitSimasName') === 'BIDANG APLIKASI DAN INFORMATIKA');
$cek('belum ditetapkan', $c->get('pjUserId') === null);

echo "\n=== B. tidak ada yang tertulis sebelum tombol terakhir ===\n";
$sebelum = $hitung();
$c2 = Livewire::test(PicPicker::class)->set('nip', $nip)->call('lookup')->call('review');
$sesudah = $hitung();
foreach ($sebelum as $k => $v) {
    $cek("{$k} tidak bertambah saat memeriksa", $sesudah[$k] === $v, "{$v} -> {$sesudah[$k]}");
}

echo "\n=== C. OPD belum dikenali: kolomnya terbuka untuk dikoreksi ===\n";
Opd::whereIn('code', ['UJIBARU'])->delete();
$pasang('BADAN PENGHUBUNG DAERAH BIDANG KERJA SAMA');

$c3 = Livewire::test(PicPicker::class)->set('nip', $nip)->call('lookup')->call('review');
$cek('OPD memang tidak dikenali', ($c3->get('found')['opd'] ?? null) === null);
$cek('kolom OPD terisi teks utuh', $c3->get('newOpdSimasName') === 'BADAN PENGHUBUNG DAERAH BIDANG KERJA SAMA');
$cek('kode disarankan', $c3->get('newOpdCode') !== '');

$sebelum = $hitung();
$c3->set('newOpdSimasName', 'BADAN PENGHUBUNG DAERAH')
   ->set('newOpdName', 'Badan Penghubung Daerah')
   ->set('newOpdCode', 'UJIBARU')
   ->set('newUnitSimasName', 'BIDANG KERJA SAMA')
   ->set('newUnitName', 'Bidang Kerja Sama')
   ->call('saveCreate');

$cek('tidak ada galat', $c3->get('createError') === '', $c3->get('createError'));
$sesudah = $hitung();
$cek('OPD bertambah satu', $sesudah['opd'] === $sebelum['opd'] + 1);
$cek('unit bertambah satu', $sesudah['unit'] === $sebelum['unit'] + 1);

$baru = Opd::where('code', 'UJIBARU')->first();
$cek('nama tampilan memakai koreksi', $baru?->name === 'Badan Penghubung Daerah');
$cek('simas_name memakai koreksi', $baru?->simas_name === 'BADAN PENGHUBUNG DAERAH');
$cek('langsung jadi PJ', $c3->get('pjUserId') !== null);

echo "\n=== D. sistem YAKIN tapi salah: admin tetap bisa mengoreksi ===\n";
// Pencocokan awalan mengklaim OPD yang salah karena namanya jadi awalan.
$pasang('BADAN PENGHUBUNG DAERAH DAN KEARSIPAN BIDANG ARSIP');

$c4 = Livewire::test(PicPicker::class)->set('nip', $nip)->call('lookup');
$dikenali = $c4->get('found')['opd']?->name ?? null;
echo "  sistem mengenali sebagai: ".($dikenali ?? 'tidak dikenali').PHP_EOL;
$cek('sistem mengklaim OPD yang SALAH', $dikenali === 'Badan Penghubung Daerah');

$c4->call('review')->call('forceCreateOpd');
$cek('admin dapat menolak hasil pencocokan', ($c4->get('found')['opd'] ?? null) === null);
$cek('kolom OPD terbuka kembali', $c4->get('newOpdSimasName') === 'BADAN PENGHUBUNG DAERAH DAN KEARSIPAN BIDANG ARSIP');

echo "\n=== E. batal: tidak ada yang tersimpan ===\n";
$sebelum = $hitung();
$c5 = Livewire::test(PicPicker::class)->set('nip', $nip)->call('lookup')->call('review')->call('cancelCreate');
$cek('form tertutup', $c5->get('creatingOpd') === false);
$sesudah = $hitung();
$cek('tidak ada yang bertambah', $sesudah === $sebelum,
    json_encode($sebelum).' -> '.json_encode($sesudah));

// Bersihkan.
Opd::where('code', 'UJIBARU')->delete();

echo "\n".($gagal === 0 ? 'SEMUA LULUS' : "ADA {$gagal} YANG GAGAL")."\n";
