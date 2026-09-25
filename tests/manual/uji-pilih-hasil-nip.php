<?php
/*
 * Celah yang ditemukan saat memeriksa layar: hasil pencarian NIP tampil
 * lengkap dan benar, lalu TIDAK ADA cara memilihnya. Klik Simpan berarti
 * aset tersimpan TANPA penanggung jawab, tanpa galat apa pun.
 *
 * Yang diuji di sini perilakunya, bukan kodenya.
 */

use Livewire\Livewire;
use Nawasara\Registry\Livewire\PicPicker;
use Nawasara\Registry\Models\Opd;
use Nawasara\Registry\Services\PicAssignmentService;
use Nawasara\Registry\Services\SimasHebatClient;
use Nawasara\Registry\Services\UnitKerjaMatcher;

$gagal = 0;
$cek = function (string $l, bool $ok, string $d = '') use (&$gagal) {
    if (! $ok) { $gagal++; }
    echo ($ok ? '  OK   ' : '  GAGAL')." {$l}".($d ? "\n         {$d}" : '')."\n";
};

$jawaban = [
    'nama_lengkap' => 'BAYUS ATDINATA S.KOM',
    'nama_jabatan' => 'KEPALA BIDANG',
    'jenis_jabatan' => 'Jabatan Struktural',
    'unit_kerja' => 'DINAS KOMUNIKASI, INFORMATIKA DAN STATISTIK BIDANG APLIKASI DAN INFORMATIKA',
    'jenis_kepegawaian' => 'PNS',
];

/** SIMAS palsu supaya uji tidak memanggil sumber sungguhan. */
class SimasDiam extends SimasHebatClient
{
    public function __construct(private array $jawaban) { parent::__construct('http://x', 't', 'a'); }
    public function pegawai(string $nip): array { return $this->jawaban; }
}

app()->bind(PicAssignmentService::class, fn () => new PicAssignmentService(
    new SimasDiam($jawaban), new UnitKerjaMatcher
));

$kominfo = Opd::where('code', 'DISKOMINFO')->firstOrFail();
$nipAda = '197810202002121002';   // punya akun lokal
$userModel = config('auth.providers.users.model');
$userAda = $userModel::where('username', $nipAda)->firstOrFail();

echo "=== sebelum: hasil pencarian tidak dapat dipilih ===\n";
$c = Livewire::test(PicPicker::class)
    ->set('nip', $nipAda)
    ->call('lookup');

$cek('hasil tampil', $c->get('found') !== null);
$cek('nama benar', ($c->get('found')['nama_lengkap'] ?? '') === 'BAYUS ATDINATA S.KOM');
$cek('OPD dikenali', ($c->get('found')['opd']?->id ?? null) === $kominfo->id);
$cek('ditandai siap dipilih', $c->get('foundState') === PicPicker::ACCOUNT_LINKED, 'dapat: '.$c->get('foundState'));

echo "\n=== inti perbaikan: memilih benar-benar MENGISI penanggung jawab ===\n";
$cek('sebelum dipilih, pjUserId masih kosong', $c->get('pjUserId') === null);

$c->call('selectFound');

$cek('pjUserId terisi', (int) $c->get('pjUserId') === (int) $userAda->id,
    'dapat: '.var_export($c->get('pjUserId'), true));
$cek('opdId ikut terisi dari hasil SIMAS', (int) $c->get('opdId') === $kominfo->id);
$cek('kotak pencarian dibersihkan', $c->get('found') === null && $c->get('nip') === '');

echo "\n=== tuan rumah diberi tahu ===\n";
$c->assertDispatched('pic-selected');
echo "  OK    event pic-selected terkirim\n";

echo "\n=== yang belum punya akun: dibuatkan, dan itu dikatakan lebih dulu ===\n";
/*
 * Dulu kasus ini ditolak. Penolakan itu membuat layar buntu, sementara
 * membuat akun dengan username = NIP justru aman: saat orangnya benar-benar
 * login, penautan mencari lewat username dan mengadopsi baris ini.
 * Rinciannya diuji di uji-akun-dibuat-dari-simas.php.
 */
$nipAsing = '190001011990011001';
$userModel::where('username', $nipAsing)->delete();

$c2 = Livewire::test(PicPicker::class)->set('nip', $nipAsing)->call('lookup');
$cek('ditandai akun akan dibuat',
    $c2->get('foundState') === PicPicker::ACCOUNT_WILL_CREATE, 'dapat: '.$c2->get('foundState'));

$sebelum = $userModel::count();
$cek('PENCARIAN belum membuat akun', $userModel::count() === $sebelum);

$c2->call('selectFound');
$cek('akun dibuat saat ditetapkan', $userModel::count() === $sebelum + 1,
    'sebelum '.$sebelum.', sesudah '.$userModel::count());
$cek('pjUserId terisi', $c2->get('pjUserId') !== null);
$cek('username = NIP', $userModel::where('username', $nipAsing)->exists());

echo "\n=== NIP salah bentuk ===\n";
$c3 = Livewire::test(PicPicker::class)->set('nip', '123')->call('lookup');
$cek('ditolak sebelum memanggil SIMAS', $c3->get('found') === null);
$cek('pesannya jelas', str_contains($c3->get('lookupError'), '18 angka'));

echo "\n".($gagal === 0 ? 'SEMUA LULUS' : "ADA {$gagal} YANG GAGAL")."\n";
