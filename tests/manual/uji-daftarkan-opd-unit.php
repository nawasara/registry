<?php
/*
 * Mendaftarkan OPD dan bidang dari layar penetapan PIC, dengan koreksi admin.
 *
 * Yang diuji terutama BATASNYA: nama tampilan boleh dirapikan, tetapi
 * `simas_name` harus tetap persis seperti sumbernya. Kalau perapian ikut
 * mengubah kunci pencocokan, pegawai berikutnya dari OPD yang sama tidak
 * dikenali lagi, dan tidak ada apa pun yang memberi tahu kenapa.
 */

use Livewire\Livewire;
use Nawasara\Registry\Livewire\PicPicker;
use Nawasara\Registry\Models\Employee;
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
$userModel = config('auth.providers.users.model');

/** SIMAS palsu; jawabannya dapat diatur. */
class SimasAtur extends SimasHebatClient
{
    public function __construct(private array $jawaban) { parent::__construct('http://x', 't', 'a'); }
    public function pegawai(string $nip): array { return $this->jawaban; }
}

$pasang = function (string $unitKerja) {
    $jawaban = [
        'nama_lengkap' => 'BAYUS ATDINATA S.KOM',
        'nama_jabatan' => 'KEPALA BIDANG',
        'jenis_jabatan' => 'Jabatan Struktural',
        'unit_kerja' => $unitKerja,
        'jenis_kepegawaian' => 'PNS',
    ];
    app()->bind(PicAssignmentService::class, fn () => new PicAssignmentService(
        new SimasAtur($jawaban), new UnitKerjaMatcher
    ));
};

// Bersihkan sisa uji sebelumnya.
Unit::query()->delete();
Opd::whereIn('code', ['SETDA', 'SETDAUJI'])->delete();

echo "=== OPD belum terdaftar: form pendaftaran terbuka dengan tebakan ===\n";
$pasang('SEKRETARIAT DAERAH BAGIAN PEMERINTAHAN');

$c = Livewire::test(PicPicker::class)->set('nip', $nip)->call('lookup');
$cek('OPD memang tidak dikenali', ($c->get('found')['opd'] ?? null) === null);

$c->call('startCreateOpd');
$cek('form terbuka', $c->get('creatingOpd') === true);
$cek('nama OPD terisi teks utuh', $c->get('newOpdSimasName') === 'SEKRETARIAT DAERAH BAGIAN PEMERINTAHAN');
$cek('kode disarankan', $c->get('newOpdCode') !== '', 'saran: '.$c->get('newOpdCode'));
$cek('bidang dibiarkan kosong (sistem tidak menebak)', $c->get('newUnitSimasName') === '');

echo "\n=== admin MEMBETULKAN pemisahannya ===\n";
// Sistem menawarkan seluruh teks sebagai nama OPD. Admin tahu yang benar
// adalah "SEKRETARIAT DAERAH" dengan bidang "BAGIAN PEMERINTAHAN".
$c->set('newOpdSimasName', 'SEKRETARIAT DAERAH')
  ->set('newOpdName', 'Sekretariat Daerah')
  ->set('newOpdCode', 'SETDAUJI')
  ->set('newUnitSimasName', 'BAGIAN PEMERINTAHAN')
  ->set('newUnitName', 'Bagian Pemerintahan')
  ->call('saveCreate');

$cek('tidak ada galat', $c->get('createError') === '', $c->get('createError'));

$opd = Opd::where('code', 'SETDAUJI')->first();
$cek('OPD terbuat', $opd !== null);
$cek('nama tampilan MEMAKAI koreksi admin', $opd?->name === 'Sekretariat Daerah', 'dapat: '.$opd?->name);
$cek('simas_name MEMAKAI koreksi admin', $opd?->simas_name === 'SEKRETARIAT DAERAH', 'dapat: '.$opd?->simas_name);

$unit = Unit::where('opd_id', $opd?->id)->first();
$cek('bidang terbuat', $unit !== null);
$cek('nama bidang dirapikan', $unit?->name === 'Bagian Pemerintahan', 'dapat: '.$unit?->name);
$cek('simas_name bidang tetap mentah', $unit?->simas_name === 'BAGIAN PEMERINTAHAN', 'dapat: '.$unit?->simas_name);

echo "\n=== langsung dijadikan PJ tanpa langkah tambahan ===\n";
$cek('pjUserId terisi', $c->get('pjUserId') !== null, 'dapat: '.var_export($c->get('pjUserId'), true));
$cek('opdId menunjuk OPD baru', (int) $c->get('opdId') === (int) $opd?->id);
$c->assertDispatched('pic-selected');
echo "  OK    event pic-selected terkirim\n";

echo "\n=== INTI: pencocokan berikutnya memakai simas_name, bukan nama rapi ===\n";
$m = new UnitKerjaMatcher;
$h = $m->match('SEKRETARIAT DAERAH BAGIAN HUKUM');
$cek('pegawai lain dari OPD sama tetap dikenali', $h['opd']?->id === $opd?->id,
    'dapat: '.($h['opd']?->name ?? 'null'));
$cek('bidangnya terbaca benar', $h['sub_unit'] === 'BAGIAN HUKUM', 'dapat: '.var_export($h['sub_unit'], true));

echo "\n=== kode ganda ditolak ===\n";
$pasang('BADAN SESUATU YANG LAIN');
$c2 = Livewire::test(PicPicker::class)->set('nip', $nip)->call('lookup')->call('startCreateOpd');
$sebelum = Opd::count();
$c2->set('newOpdCode', 'SETDAUJI')->call('saveCreate');
$cek('ditolak dengan pesan', str_contains($c2->get('createError'), 'sudah dipakai'), $c2->get('createError'));
$cek('tidak ada OPD baru', Opd::count() === $sebelum);

echo "\n=== kode kosong ditolak ===\n";
$c3 = Livewire::test(PicPicker::class)->set('nip', $nip)->call('lookup')->call('startCreateOpd');
$c3->set('newOpdCode', '')->call('saveCreate');
$cek('ditolak', str_contains($c3->get('createError'), 'wajib diisi'), $c3->get('createError'));

echo "\n=== OPD sudah ada, bidangnya yang belum ===\n";
$pasang('DINAS KOMUNIKASI, INFORMATIKA DAN STATISTIK BIDANG APLIKASI DAN INFORMATIKA');
$c4 = Livewire::test(PicPicker::class)->set('nip', $nip)->call('lookup');
$cek('OPD dikenali', ($c4->get('found')['opd'] ?? null) !== null);
$cek('bidang ditandai belum ada', $c4->get('unitMissing') === true || $c4->instance()->getUnitMissingProperty() === true);

$c4->call('startCreateUnit');
$cek('form bidang terbuka', $c4->get('creatingOpd') === true);
$cek('kolom OPD dikosongkan (tidak dibuat ulang)', $c4->get('newOpdSimasName') === '');
$cek('bidang terisi dari hasil', $c4->get('newUnitSimasName') === 'BIDANG APLIKASI DAN INFORMATIKA');

$jumlahOpd = Opd::count();
$c4->set('newUnitName', 'Bidang Aplikasi dan Informatika')->call('saveCreate');
$cek('tidak ada galat', $c4->get('createError') === '', $c4->get('createError'));
$cek('TIDAK membuat OPD baru', Opd::count() === $jumlahOpd, 'sebelum '.$jumlahOpd.', sesudah '.Opd::count());

$kominfo = Opd::where('code', 'DISKOMINFO')->first();
$u2 = Unit::where('opd_id', $kominfo->id)->where('simas_name', 'BIDANG APLIKASI DAN INFORMATIKA')->first();
$cek('bidang terbuat di bawah Kominfo', $u2 !== null);
$cek('namanya dirapikan', $u2?->name === 'Bidang Aplikasi dan Informatika', 'dapat: '.$u2?->name);

echo "\n=== jejak audit mencatat bahwa admin mengoreksi ===\n";
$log = DB::table('activity_log')->where('description', 'OPD dibuat dari SIMAS Hebat')->latest('id')->first();
$p = json_decode($log->properties ?? '{}', true);
$cek('audit ada', $log !== null);
$cek('memuat jawaban SIMAS mentah', ($p['simas']['unit_kerja'] ?? null) !== null,
    'unit_kerja: '.($p['simas']['unit_kerja'] ?? '-'));
$cek('menandai dikoreksi admin', ($p['dikoreksi_admin'] ?? null) === true,
    'dapat: '.var_export($p['dikoreksi_admin'] ?? null, true));

echo "\n".($gagal === 0 ? 'SEMUA LULUS' : "ADA {$gagal} YANG GAGAL")."\n";
