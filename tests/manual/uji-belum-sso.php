<?php
/*
 * Kasus dari layar: pegawai ditemukan di SIMAS, OPD-nya dikenali, bidangnya
 * BELUM terdaftar, dan orangnya belum punya akun Keycloak.
 *
 * Sebelumnya layar itu buntu: satu-satunya tombol adalah "Hapus hasil",
 * padahal mendaftarkan bidang sama sekali tidak bergantung pada akun.
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

// NIP yang TIDAK punya akun lokal maupun snapshot Keycloak.
$nipAsing = '198406072006041007';

class SimasBpbd extends SimasHebatClient {
    public function __construct() { parent::__construct('http://x','t','a'); }
    public function pegawai(string $n): array {
        return [
            'nama_lengkap' => 'BAYU SUSANTO SE',
            'nama_jabatan' => 'Analis Kebencanaan Keahlian Ahli Muda',
            'jenis_jabatan' => 'Jabatan Fungsional',
            'unit_kerja' => 'BADAN PENANGGULANGAN BENCANA DAERAH BIDANG REHABILITASI DAN REKONSTRUKSI',
            'jenis_kepegawaian' => 'PNS',
        ];
    }
}
app()->bind(PicAssignmentService::class, fn () => new PicAssignmentService(
    new SimasBpbd, new UnitKerjaMatcher
));

$bpbd = Opd::where('code', 'BPBD')->firstOrFail();
Unit::where('opd_id', $bpbd->id)->delete();   // pastikan bidangnya belum ada

echo "=== keadaan seperti di layar ===\n";
$c = Livewire::test(PicPicker::class)->set('nip', $nipAsing)->call('lookup');

$cek('pegawai ditemukan', ($c->get('found')['nama_lengkap'] ?? '') === 'BAYU SUSANTO SE');
$cek('OPD dikenali', ($c->get('found')['opd'] ?? null)?->id === $bpbd->id);
$cek('bidang terdeteksi belum ada', $c->instance()->getUnitMissingProperty() === true);
$cek('ditandai belum pernah SSO', $c->get('foundState') === 'belum_sso');

echo "\n=== dulu buntu: sekarang pendaftaran bidang tetap bisa ===\n";
$c->call('review');
$cek('langkah pemeriksaan terbuka', $c->get('creatingOpd') === true);
$cek('bidang terisi dari hasil', $c->get('newUnitSimasName') === 'BIDANG REHABILITASI DAN REKONSTRUKSI');
$cek('kolom OPD kosong (sudah ada)', $c->get('newOpdSimasName') === '');

$jumlahOpd = Opd::count();
$c->set('newUnitName', 'Bidang Rehabilitasi dan Rekonstruksi')->call('saveCreate');

$cek('tidak ada galat', $c->get('createError') === '', $c->get('createError'));
$cek('TIDAK membuat OPD baru', Opd::count() === $jumlahOpd);

$unit = Unit::where('opd_id', $bpbd->id)->first();
$cek('bidang TERSIMPAN', $unit !== null);
$cek('nama dirapikan', $unit?->name === 'Bidang Rehabilitasi dan Rekonstruksi', 'dapat: '.$unit?->name);
$cek('simas_name tetap mentah', $unit?->simas_name === 'BIDANG REHABILITASI DAN REKONSTRUKSI');

echo "\n=== orangnya tetap TIDAK ditetapkan, dan itu dikatakan ===\n";
$cek('pjUserId tetap kosong', $c->get('pjUserId') === null, 'dapat: '.var_export($c->get('pjUserId'), true));
$cek('ada keterangan tersimpan', str_contains($c->get('createdNotice'), 'Tersimpan'), $c->get('createdNotice'));
$cek('keterangan menyebut alasannya', str_contains($c->get('createdNotice'), 'SSO'));
$cek('form pemeriksaan tertutup', $c->get('creatingOpd') === false);

echo "\n=== pencarian ulang: bidang kini dikenali ===\n";
$c2 = Livewire::test(PicPicker::class)->set('nip', $nipAsing)->call('lookup');
$cek('bidang tidak lagi ditandai hilang', $c2->instance()->getUnitMissingProperty() === false);

echo "\n=== orang yang PUNYA akun tetap dapat ditetapkan ===\n";
$c3 = Livewire::test(PicPicker::class)->set('nip', '197810202002121002')->call('lookup');
$cek('ditandai siap', $c3->get('foundState') === 'siap');
$c3->call('review')->call('saveCreate');
$cek('ditetapkan sebagai PJ', $c3->get('pjUserId') !== null);
$cek('tidak ada keterangan "belum SSO"', $c3->get('createdNotice') === '');

echo "\n".($gagal === 0 ? 'SEMUA LULUS' : "ADA {$gagal} YANG GAGAL")."\n";
