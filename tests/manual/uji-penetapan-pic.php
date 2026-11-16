<?php
/*
 * Uji PicAssignmentService terhadap basis data sungguhan (SQLite memori).
 * Yang dipalsukan hanya SIMAS Hebat-nya.
 *
 * Yang diuji: apakah aturannya BERLAKU, bukan apakah kodenya jalan.
 *  - mutasi mengikuti SIMAS (sumber resmi), tercatat di audit
 *  - OPD tak bisa lahir tanpa jawaban SIMAS
 *  - NIP tidak pernah ditimpa
 *  - jejak audit memuat jawaban SIMAS
 */

$root = 'D:/laragon-www/kominfo/nawasara-dev';
require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->instance('request', Illuminate\Http\Request::create('/', 'GET'));
$app->make(Illuminate\Contracts\Http\Kernel::class)->bootstrap();

$app->make('config')->set('database.default', 'uji');
$app->make('config')->set('database.connections.uji', [
    'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306, 'database' => 'db_uji_pic', 'username' => 'root', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '',
    'foreign_key_constraints' => false,
]);
$app->make('db')->setDefaultConnection('uji');

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Nawasara\Registry\Exceptions\SimasHebatException;
use Nawasara\Registry\Models\Asset;
use Nawasara\Registry\Models\Employee;
use Nawasara\Registry\Models\Membership;
use Nawasara\Registry\Models\Opd;
use Nawasara\Registry\Models\Unit;
use Nawasara\Registry\Services\PicAssignmentService;
use Nawasara\Registry\Services\SimasHebatClient;
use Nawasara\Registry\Services\UnitKerjaMatcher;

$gagal = 0;
$cek = function (string $label, bool $ok, string $detail = '') use (&$gagal) {
    if (! $ok) { $gagal++; }
    echo ($ok ? '  OK   ' : '  GAGAL')." {$label}".($detail ? "\n         {$detail}" : '')."\n";
};

// ---- skema ----
Schema::create('users', function ($t) {
    $t->id(); $t->string('name'); $t->string('email')->unique();
    $t->string('username')->nullable(); $t->timestamps();
});
Schema::create('activity_log', function ($t) {
    $t->id(); $t->string('log_name')->nullable(); $t->text('description')->nullable();
    $t->nullableMorphs('subject'); $t->nullableMorphs('causer');
    $t->text('properties')->nullable(); $t->uuid('batch_uuid')->nullable();
    $t->string('event')->nullable(); $t->timestamps();
});
Schema::create('nawasara_registry_opd', function ($t) {
    $t->id(); $t->foreignId('parent_id')->nullable();
    $t->string('code')->unique(); $t->string('name'); $t->string('simas_name')->nullable();
    $t->text('address')->nullable(); $t->string('phone')->nullable(); $t->string('email')->nullable();
    $t->timestamps();
});
Schema::create('nawasara_registry_units', function ($t) {
    $t->id(); $t->foreignId('opd_id'); $t->string('name'); $t->string('simas_name');
    $t->timestamps(); $t->unique(['opd_id', 'simas_name']);
});
Schema::create('nawasara_registry_employees', function ($t) {
    $t->id(); $t->foreignId('user_id')->unique();
    $t->string('nip', 32)->nullable()->unique();
    $t->string('nama_jabatan')->nullable(); $t->string('jenis_jabatan', 64)->nullable();
    $t->string('jenis_kepegawaian', 32)->nullable(); $t->foreignId('unit_id')->nullable();
    $t->string('simas_unit_kerja')->nullable(); $t->timestamp('simas_synced_at')->nullable();
    $t->string('simas_nama')->nullable(); $t->string('simas_skpd')->nullable(); $t->string('simas_unit')->nullable();
    $t->foreignId('simas_opd_id')->nullable(); $t->string('simas_status', 16)->nullable();
    $t->string('simas_error')->nullable(); $t->string('simas_source', 16)->nullable();
    $t->timestamp('simas_checked_at')->nullable();
    $t->timestamps();
});
Schema::create('nawasara_registry_memberships', function ($t) {
    $t->id(); $t->foreignId('user_id')->unique(); $t->foreignId('opd_id');
    $t->boolean('aktif')->default(true); $t->timestamps();
});
Schema::create('nawasara_registry_assets', function ($t) {
    $t->id(); $t->string('package_ref')->nullable(); $t->string('external_id')->nullable();
    $t->string('type')->nullable(); $t->string('identifier')->nullable();
    $t->foreignId('opd_id')->nullable(); $t->foreignId('pj_user_id')->nullable();
    $t->string('status')->nullable(); $t->timestamp('registered_at')->nullable();
    $t->text('notes')->nullable(); $t->timestamp('discovered_at')->nullable();
    $t->timestamps();
});

(require $root.'/packages/nawasara-registry/database/migrations/2026_10_30_100001_create_nawasara_registry_asset_assignments_table.php')->up();

// ---- data awal ----
$kominfo = Opd::create(['code' => 'DISKOMINFO', 'name' => 'DINAS KOMUNIKASI, INFORMATIKA DAN STATISTIK']);
$dindik  = Opd::create(['code' => 'DINDIK', 'name' => 'DINAS PENDIDIKAN']);

DB::table('users')->insert([
    ['id' => 1, 'name' => 'Pringgo', 'email' => 'p@x.id', 'username' => '199506142020121004'],
    ['id' => 2, 'name' => 'Tanpa NIP', 'email' => 'q@x.id', 'username' => 'bukannip'],
]);
$user = new class extends \Illuminate\Database\Eloquent\Model {
    protected $table = 'users'; public $timestamps = false;
    public function getAuthIdentifier() { return $this->id; }
};
$u1 = $user->newQuery()->find(1);

$asset = Asset::create(['package_ref' => 'uji', 'external_id' => 'a1', 'type' => 'domain', 'identifier' => 'x.id']);

/** SIMAS palsu yang dapat diatur jawabannya. */
class SimasPalsu extends SimasHebatClient
{
    public array $panggilan = [];
    public function __construct(private $jawaban) { parent::__construct('http://x', 't', 'a'); }
    public function pegawai(string $nip): array
    {
        $this->panggilan[] = $nip;
        if ($this->jawaban instanceof \Throwable) { throw $this->jawaban; }
        return $this->jawaban;
    }
}

$jawabKominfo = [
    'nama_lengkap' => 'PRINGGO JUNI SAPUTRO S.Tr.Kom.',
    'nama_jabatan' => 'Pranata Komputer Keterampilan Terampil',
    'jenis_jabatan' => 'Jabatan Fungsional',
    'unit_kerja' => 'DINAS KOMUNIKASI, INFORMATIKA DAN STATISTIK BIDANG APLIKASI DAN INFORMATIKA',
    'jenis_kepegawaian' => 'PNS',
];

echo "=== penetapan pertama: data kepegawaian ikut tersimpan ===\n";
$svc = new PicAssignmentService(new SimasPalsu($jawabKominfo), new UnitKerjaMatcher);
$hasil = $svc->assign($asset, $u1);

$emp = Employee::firstWhere('user_id', 1);
$cek('baris kepegawaian dibuat', $emp !== null);
$cek('jabatan tersimpan', $emp?->nama_jabatan === 'Pranata Komputer Keterampilan Terampil', 'dapat: '.($emp?->nama_jabatan ?? 'null'));
$cek('jenis jabatan tersimpan', $emp?->jenis_jabatan === 'Jabatan Fungsional');
$cek('NIP diambil dari username Keycloak', $emp?->nip === '199506142020121004', 'dapat: '.var_export($emp?->nip, true));
$cek('unit_kerja mentah disimpan', $emp?->simas_unit_kerja === $jawabKominfo['unit_kerja']);
$cek('simas_synced_at terisi', $emp?->simas_synced_at !== null);
$cek('aset menunjuk OPD yang benar', (int) $asset->fresh()->opd_id === $kominfo->id);
$cek('keanggotaan dibuat', Membership::where('user_id', 1)->value('opd_id') == $kominfo->id);

echo "\n=== jejak audit memuat jawaban SIMAS ===\n";
$log = DB::table('activity_log')->where('description', 'Penanggung jawab ditetapkan')->first();
$props = json_decode($log->properties ?? '{}', true);
$cek('audit tercatat', $log !== null);
$cek('memuat jawaban SIMAS mentah', isset($props['simas']['unit_kerja']), 'props: '.json_encode(array_keys($props)));
$cek('memuat NIP', ($props['nip'] ?? null) === '199506142020121004');
$cek('menandai sudah diverifikasi', ($props['diverifikasi_simas'] ?? null) === true);

echo "\n=== MUTASI: SIMAS menyebut OPD lain -> keanggotaan ikut pindah ===\n";
$jawabDindik = $jawabKominfo;
$jawabDindik['unit_kerja'] = 'DINAS PENDIDIKAN BIDANG SMP';
$svc2 = new PicAssignmentService(new SimasPalsu($jawabDindik), new UnitKerjaMatcher);

$aset2 = Asset::create(['package_ref' => 'uji', 'external_id' => 'a2', 'type' => 'domain', 'identifier' => 'y.id']);
$hasil2 = $svc2->assign($aset2, $u1);
$cek('tidak ada pertanyaan, langsung jadi', (int) $aset2->fresh()->pj_user_id === 1);
$cek('keanggotaan pindah ke Dindik', Membership::where('user_id', 1)->value('opd_id') == $dindik->id);
$cek('hanya SATU keanggotaan (bukan dua)', Membership::where('user_id', 1)->count() === 1);
$cek('hasil menyebut asal perpindahan', ($hasil2['moved_from'] ?? null)?->id === $kominfo->id);
$logPindah = DB::table('activity_log')->where('description', 'Keanggotaan OPD dipindah mengikuti SIMAS Hebat')->first();
$propsPindah = json_decode($logPindah->properties ?? '{}', true);
$cek('perpindahan tercatat di audit', $logPindah !== null);
$cek('audit menyebut dari dan ke', ($propsPindah['dari'] ?? null) === $kominfo->name && ($propsPindah['ke'] ?? null) === $dindik->name);
$cek('audit menyebut sumber pic', ($propsPindah['sumber'] ?? null) === 'pic');
$cek('aset pertama TIDAK ikut pindah', (int) $asset->fresh()->opd_id === $kominfo->id);

echo "\n=== pilihan admin kalah oleh SIMAS ===\n";
$asetPilih = Asset::create(['package_ref' => 'uji', 'external_id' => 'a2b', 'type' => 'domain', 'identifier' => 'yb.id']);
$svc2->assign($asetPilih, $u1, opdId: $kominfo->id);
$cek('OPD aset mengikuti SIMAS, bukan pilihan admin', (int) $asetPilih->fresh()->opd_id === $dindik->id);

echo "\n=== NIP tidak pernah ditimpa ===\n";
$empSebelum = Employee::firstWhere('user_id', 1)->nip;
$jawabLain = $jawabDindik;
$svc3 = new PicAssignmentService(new SimasPalsu($jawabLain), new UnitKerjaMatcher);
$aset3 = Asset::create(['package_ref' => 'uji', 'external_id' => 'a3', 'type' => 'domain', 'identifier' => 'z.id']);
$svc3->assign($aset3, $u1);
$cek('NIP tetap sama', Employee::firstWhere('user_id', 1)->nip === $empSebelum, 'sebelum='.$empSebelum.' sesudah='.Employee::firstWhere('user_id', 1)->nip);

echo "\n=== OPD TIDAK BISA lahir tanpa jawaban SIMAS ===\n";
$ditolak = false;
try {
    $svc->createOpdFromSimas([], 'SETDA');
} catch (\InvalidArgumentException $e) {
    $ditolak = true;
}
$cek('jawaban kosong ditolak', $ditolak);
$cek('tidak ada OPD baru terbuat', Opd::count() === 2);

echo "\n=== OPD dari jawaban SIMAS: audit memuat alasannya ===\n";
$jawabSetda = $jawabKominfo;
$jawabSetda['unit_kerja'] = 'SEKRETARIAT DAERAH';
$setda = $svc->createOpdFromSimas($jawabSetda, 'SETDA');
$cek('OPD terbuat', $setda->exists);
$cek('simas_name diisi dari jawaban', $setda->simas_name === 'SEKRETARIAT DAERAH');
$logOpd = DB::table('activity_log')->where('description', 'OPD dibuat dari SIMAS Hebat')->first();
$propsOpd = json_decode($logOpd->properties ?? '{}', true);
$cek('audit menyebut alasan', str_contains($propsOpd['alasan'] ?? '', 'tidak dikenali'));
$cek('audit memuat jawaban mentah', ($propsOpd['simas']['unit_kerja'] ?? null) === 'SEKRETARIAT DAERAH');

echo "\n=== SIMAS mati: penetapan ke OPD yang ADA boleh lanjut ===\n";
$svcMati = new PicAssignmentService(new SimasPalsu(SimasHebatException::unreachable()), new UnitKerjaMatcher);
$aset4 = Asset::create(['package_ref' => 'uji', 'external_id' => 'a4', 'type' => 'domain', 'identifier' => 'w.id']);

$dilempar = false;
try { $svcMati->assign($aset4, $u1); } catch (SimasHebatException $e) { $dilempar = true; }
$cek('tanpa izin: penetapan DITOLAK', $dilempar);

$syncSebelum = Employee::firstWhere('user_id', 1)->simas_synced_at;
$svcMati->assign($aset4, $u1, allowWithoutSimas: true);
$cek('dengan izin: penetapan lanjut', (int) $aset4->fresh()->pj_user_id === 1);
$cek('simas_synced_at TIDAK diperbarui',
    Employee::firstWhere('user_id', 1)->simas_synced_at?->eq($syncSebelum) ?? false);

$logMati = DB::table('activity_log')->where('description', 'Penanggung jawab ditetapkan')->latest('id')->first();
$propsMati = json_decode($logMati->properties ?? '{}', true);
$cek('audit menandai TIDAK terverifikasi', ! ($propsMati['diverifikasi_simas'] ?? true));

echo "\n=== unit dibuat dari jawaban, sekali saja ===\n";
$unit1 = $svc->createUnitFromSimas($kominfo, $jawabKominfo, 'BIDANG APLIKASI DAN INFORMATIKA');
$unit2 = $svc->createUnitFromSimas($kominfo, $jawabKominfo, 'BIDANG APLIKASI DAN INFORMATIKA');
$cek('tidak terbuat dua kali', $unit1->id === $unit2->id);
$cek('unit tercatat', Unit::where('opd_id', $kominfo->id)->count() === 1);

echo "
=== SKPD tak dikenal + admin memilih OPD: simas_opd_id tetap kosong ===
";
$jawabAsing = $jawabKominfo;
unset($jawabAsing['unit_kerja']);
$jawabAsing['skpd'] = 'BADAN YANG BELUM TERDAFTAR';
$jawabAsing['unit'] = 'BIDANG X';
$svcAsing = new PicAssignmentService(new SimasPalsu($jawabAsing), new UnitKerjaMatcher);
$asetAsing = Asset::create(['package_ref' => 'uji', 'external_id' => 'a6', 'type' => 'domain', 'identifier' => 'u.id']);
$svcAsing->assign($asetAsing, $u1, opdId: $kominfo->id);
$empAsing = Employee::firstWhere('user_id', 1);
$cek('aset memakai pilihan admin', (int) $asetAsing->fresh()->opd_id === $kominfo->id);
$cek('simas_opd_id kosong (bukan pilihan admin)', $empAsing->simas_opd_id === null, 'dapat: '.var_export($empAsing->simas_opd_id, true));
$cek('skpd mentah tersimpan', $empAsing->simas_skpd === 'BADAN YANG BELUM TERDAFTAR');

echo "\n=== orang tanpa NIP: tidak dipanggilkan ke SIMAS ===\n";
$u2 = $user->newQuery()->find(2);
$palsu = new SimasPalsu($jawabKominfo);
$svcTanpaNip = new PicAssignmentService($palsu, new UnitKerjaMatcher);
$aset5 = Asset::create(['package_ref' => 'uji', 'external_id' => 'a5', 'type' => 'domain', 'identifier' => 'v.id']);
$svcTanpaNip->assign($aset5, $u2, opdId: $kominfo->id);
$cek('SIMAS tidak dipanggil', count($palsu->panggilan) === 0, 'panggilan: '.count($palsu->panggilan));
$cek('penetapan tetap jadi', (int) $aset5->fresh()->pj_user_id === 2);

echo "\n".($gagal === 0 ? 'SEMUA LULUS' : "ADA {$gagal} YANG GAGAL")."\n";
exit($gagal === 0 ? 0 : 1);
