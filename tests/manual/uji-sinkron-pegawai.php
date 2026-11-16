<?php
/*
 * Uji EmployeeSyncService + pendengar login terhadap MySQL sungguhan.
 * Yang dipalsukan hanya SIMAS Hebat-nya. Basis data db_uji_pegawai dibuat
 * ulang setiap kali dijalankan.
 *
 * Aturan yang diuji (keputusan 16 November 2026, SIMAS sumber resmi):
 *  - pegawai mutasi: keanggotaan PINDAH tanpa persetujuan, tercatat di audit
 *  - tanpa keanggotaan: TIDAK dibuatkan (hak akses lewat Pengajuan Petugas)
 *  - skpd tak dikenal: keanggotaan tetap, baris ditandai
 *  - NIP tak ditemukan / SIMAS galat: data lama tetap, synced_at tidak bergerak
 *  - halaman blokir: semua jalur berhenti satu jam
 *  - login: antre sekali, tidak antre lagi dalam interval
 *  - aset TIDAK ikut pindah
 */

$root = 'D:/laragon-www/kominfo/nawasara-dev';
require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->instance('request', Illuminate\Http\Request::create('/', 'GET'));
$app->make(Illuminate\Contracts\Http\Kernel::class)->bootstrap();

$pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', '');
$pdo->exec('DROP DATABASE IF EXISTS db_uji_pegawai');
$pdo->exec('CREATE DATABASE db_uji_pegawai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

$config = $app->make('config');
$config->set('database.connections.uji', [
    'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306, 'database' => 'db_uji_pegawai',
    'username' => 'root', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '',
]);
$config->set('database.default', 'uji');
$config->set('cache.default', 'array');
$config->set('queue.default', 'sync');
$app->make('db')->setDefaultConnection('uji');

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Nawasara\Registry\Exceptions\SimasHebatException;
use Nawasara\Registry\Jobs\SyncEmployeeFromSimasJob;
use Nawasara\Registry\Listeners\QueueEmployeeSyncOnLogin;
use Nawasara\Registry\Models\Asset;
use Nawasara\Registry\Models\Employee;
use Nawasara\Registry\Models\Membership;
use Nawasara\Registry\Models\Opd;
use Nawasara\Registry\Models\Unit;
use Nawasara\Registry\Services\EmployeeSyncService;
use Nawasara\Registry\Services\SimasHebatClient;
use Nawasara\Registry\Services\UnitKerjaMatcher;

$gagal = 0;
$cek = function (string $label, bool $ok, string $detail = '') use (&$gagal) {
    if (! $ok) { $gagal++; }
    echo ($ok ? '  OK   ' : '  GAGAL')." {$label}".($detail ? "\n         {$detail}" : '')."\n";
};

// ---- skema: tabel induk tiruan, lalu migrasi registry yang sebenarnya ----
Schema::create('users', function ($t) {
    $t->id(); $t->string('name'); $t->string('email')->unique();
    $t->string('username')->nullable(); $t->timestamps();
});
Schema::create('activity_log', function ($t) {
    $t->id(); $t->string('log_name')->nullable(); $t->text('description');
    $t->nullableMorphs('subject'); $t->nullableMorphs('causer');
    $t->json('properties')->nullable(); $t->uuid('batch_uuid')->nullable();
    $t->string('event')->nullable(); $t->timestamps();
});
Schema::create('nawasara_registry_opd', function ($t) {
    $t->id(); $t->foreignId('parent_id')->nullable();
    $t->string('code')->unique(); $t->string('name'); $t->string('simas_name')->nullable();
    $t->text('address')->nullable(); $t->string('phone')->nullable(); $t->string('email')->nullable();
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
    $t->text('notes')->nullable(); $t->string('ticket_ref')->nullable(); $t->timestamp('discovered_at')->nullable();
    $t->timestamps();
});
$mig = $root.'/packages/nawasara-registry/database/migrations/';
foreach (['2026_09_24_100002_create_nawasara_registry_units_table.php',
          '2026_09_24_100003_create_nawasara_registry_employees_table.php',
          '2026_10_30_100001_create_nawasara_registry_asset_assignments_table.php',
          '2026_11_16_100001_add_simas_profile_to_registry_employees.php'] as $f) {
    (require $mig.$f)->up();
}
$cek('migrasi baru jalan di MySQL', Schema::hasColumns('nawasara_registry_employees',
    ['simas_skpd', 'simas_unit', 'simas_opd_id', 'simas_status', 'simas_checked_at']));

// ---- data awal ----
$kominfo = Opd::create(['code' => 'KOMINFO', 'name' => 'Dinas Komunikasi, Informatika dan Statistik']);
$dindik = Opd::create(['code' => 'DINDIK', 'name' => 'Dinas Pendidikan', 'simas_name' => 'DINAS PENDIDIKAN']);
$bidangApl = Unit::create(['opd_id' => $kominfo->id, 'name' => 'Bidang Aplikasi', 'simas_name' => 'BIDANG APLIKASI DAN INFORMATIKA']);

$userModel = config('auth.providers.users.model');
DB::table('users')->insert([
    ['id' => 1, 'name' => 'Pringgo', 'email' => 'p@x.id', 'username' => '199506142020121004'],
    ['id' => 2, 'name' => 'Tanpa Anggota', 'email' => 'q@x.id', 'username' => '197810202002121002'],
    ['id' => 3, 'name' => 'Admin Lokal', 'email' => 'r@x.id', 'username' => 'admin'],
]);
$u1 = $userModel::find(1);
$u2 = $userModel::find(2);
$u3 = $userModel::find(3);

Membership::create(['user_id' => 1, 'opd_id' => $kominfo->id, 'aktif' => true]);
$asetLama = Asset::create(['package_ref' => 'uji', 'external_id' => 'a1', 'type' => 'domain',
    'identifier' => 'kominfo.ponorogo.go.id', 'opd_id' => $kominfo->id, 'pj_user_id' => 1]);

class SimasPalsu extends SimasHebatClient
{
    public int $panggilan = 0;
    public function __construct(public $jawaban) { parent::__construct('http://x', 't', 'a'); }
    public function pegawai(string $nip): array
    {
        $this->panggilan++;
        if ($this->jawaban instanceof \Throwable) { throw $this->jawaban; }
        return SimasHebatClient::parse($this->jawaban, $nip);
    }
}

$jawab = fn (string $skpd, string $unit) => ['access' => true, 'data' => [
    'nama_lengkap' => ' PRINGGO JUNI SAPUTRO S.Tr.Kom.', 'nama_jabatan' => 'Pranata Komputer',
    'jenis_jabatan' => 'Jabatan Fungsional', 'skpd' => $skpd, 'unit' => $unit, 'jenis_kepegawaian' => 'PNS',
]];

$simas = new SimasPalsu($jawab('DINAS KOMUNIKASI, INFORMATIKA DAN STATISTIK', 'BIDANG APLIKASI DAN INFORMATIKA'));
$svc = new EmployeeSyncService($simas, new UnitKerjaMatcher);

echo "=== OPD sama: data terisi, keanggotaan tetap ===\n";
$h = $svc->sync($u1, EmployeeSyncService::SOURCE_MANUAL);
$e = Employee::firstWhere('user_id', 1);
$cek('status ok', $h['status'] === 'ok');
$cek('OPD dicocokkan lewat nama (tanpa simas_name)', $h['opd']?->id === $kominfo->id);
$cek('skpd dan unit tersimpan mentah', $e->simas_skpd === 'DINAS KOMUNIKASI, INFORMATIKA DAN STATISTIK' && $e->simas_unit === 'BIDANG APLIKASI DAN INFORMATIKA');
$cek('nama dirapikan dari spasi depan', $e->simas_nama === 'PRINGGO JUNI SAPUTRO S.Tr.Kom.');
$cek('unit terhubung', (int) $e->unit_id === $bidangApl->id);
$cek('sumber = manual', $e->simas_source === 'manual');
$cek('synced_at dan checked_at terisi', $e->simas_synced_at !== null && $e->simas_checked_at !== null);
$cek('NIP diambil dari username', $e->nip === '199506142020121004');
$cek('tidak ada perpindahan', $h['moved_from'] === null);

echo "\n=== MUTASI: SIMAS menyebut Dinas Pendidikan ===\n";
$simas->jawaban = $jawab('DINAS PENDIDIKAN', 'SMP NEGERI 3 PONOROGO');
$h = $svc->sync($u1, EmployeeSyncService::SOURCE_LOGIN);
$m = Membership::firstWhere('user_id', 1);
$e->refresh();
$cek('keanggotaan PINDAH tanpa persetujuan', (int) $m->opd_id === $dindik->id);
$cek('tetap satu baris keanggotaan', Membership::where('user_id', 1)->count() === 1);
$cek('hasil menyebut asalnya', $h['moved_from']?->id === $kominfo->id, $h['message']);
$cek('unit lama (Kominfo) dikosongkan', $e->unit_id === null);
$cek('simas_opd_id = Dindik', (int) $e->simas_opd_id === $dindik->id);
$log = DB::table('activity_log')->where('description', 'Keanggotaan OPD dipindah mengikuti SIMAS Hebat')->latest('id')->first();
$p = json_decode($log->properties ?? '{}', true);
$cek('audit tercatat pada keanggotaan', $log && $log->subject_id == $m->id);
$cek('audit: dari, ke, sumber, skpd', ($p['dari'] ?? '') === $kominfo->name && ($p['ke'] ?? '') === $dindik->name
    && ($p['sumber'] ?? '') === 'login' && ($p['simas']['skpd'] ?? '') === 'DINAS PENDIDIKAN');
$cek('aset TIDAK ikut pindah', (int) $asetLama->fresh()->opd_id === $kominfo->id);

echo "\n=== keanggotaan nonaktif: pindah, tetapi tetap nonaktif ===\n";
$m->forceFill(['aktif' => false])->save();
$simas->jawaban = $jawab('DINAS KOMUNIKASI, INFORMATIKA DAN STATISTIK', 'BIDANG APLIKASI DAN INFORMATIKA');
$svc->sync($u1, EmployeeSyncService::SOURCE_MANUAL);
$m->refresh();
$cek('OPD ikut SIMAS', (int) $m->opd_id === $kominfo->id);
$cek('status nonaktif dari admin tidak dihidupkan', ! $m->aktif);
$m->forceFill(['aktif' => true])->save();

echo "\n=== skpd tak dikenal Registry: keanggotaan tetap, ditandai ===\n";
$simas->jawaban = $jawab('BADAN BARU YANG BELUM ADA', 'BIDANG X');
$h = $svc->sync($u1, EmployeeSyncService::SOURCE_MANUAL);
$e->refresh();
$cek('status tetap ok', $h['status'] === 'ok');
$cek('keanggotaan TIDAK berubah', (int) Membership::where('user_id', 1)->value('opd_id') === $kominfo->id);
$cek('ditandai: simas_opd_id kosong', $e->simas_opd_id === null);
$cek('pesan menjelaskan', str_contains($h['message'], 'belum dikenal'), $h['message']);

echo "\n=== awalan saja tidak cukup ===\n";
$simas->jawaban = $jawab('DINAS PENDIDIKAN DAN KEBUDAYAAN', 'BIDANG SMP');
$svc->sync($u1, EmployeeSyncService::SOURCE_MANUAL);
$cek('tidak dipindah ke Dinas Pendidikan', (int) Membership::where('user_id', 1)->value('opd_id') === $kominfo->id);

echo "\n=== tanpa keanggotaan: TIDAK dibuatkan ===\n";
$simas->jawaban = $jawab('DINAS PENDIDIKAN', 'SD NEGERI 1 BEDOHO');
$h = $svc->sync($u2, EmployeeSyncService::SOURCE_LOGIN);
$cek('data pegawai terisi', Employee::where('user_id', 2)->value('simas_skpd') === 'DINAS PENDIDIKAN');
$cek('keanggotaan tidak dibuat', ! Membership::where('user_id', 2)->exists());

echo "\n=== NIP tidak ditemukan: data lama dipertahankan ===\n";
$sebelum = Employee::firstWhere('user_id', 1);
$simas->jawaban = ['message' => 'Pegawai not found', 'access' => false];
$h = $svc->sync($u1, EmployeeSyncService::SOURCE_MANUAL);
$sesudah = Employee::firstWhere('user_id', 1);
$cek('status not_found', $h['status'] === 'not_found' && $sesudah->simas_status === 'not_found');
$cek('skpd lama tetap', $sesudah->simas_skpd === $sebelum->simas_skpd);
$cek('synced_at tidak bergerak', $sesudah->simas_synced_at?->eq($sebelum->simas_synced_at) ?? false);
$cek('keanggotaan tidak disentuh', (int) Membership::where('user_id', 1)->value('opd_id') === $kominfo->id);

echo "\n=== SIMAS galat: dicatat, dilempar, data tetap ===\n";
$simas->jawaban = SimasHebatException::unreachable();
$dilempar = false;
try { $svc->sync($u1, EmployeeSyncService::SOURCE_MANUAL); } catch (SimasHebatException $ex) { $dilempar = true; }
$sesudah2 = Employee::firstWhere('user_id', 1);
$cek('galat dilempar ke pemanggil', $dilempar);
$cek('status error + pesannya', $sesudah2->simas_status === 'error' && $sesudah2->simas_error !== null);
$cek('synced_at tetap', $sesudah2->simas_synced_at?->eq($sebelum->simas_synced_at) ?? false);

echo "\n=== halaman blokir: berhenti sejam untuk semua ===\n";
$simas->jawaban = SimasHebatException::blocked();
try { $svc->sync($u1, EmployeeSyncService::SOURCE_MANUAL); } catch (SimasHebatException $ex) {}
$cek('tanda blokir dipasang', Cache::has(EmployeeSyncService::BLOCKED_CACHE_KEY));
$simas->jawaban = $jawab('DINAS PENDIDIKAN', 'X');
$sebelumPanggil = $simas->panggilan;
try { $svc->sync($u2, EmployeeSyncService::SOURCE_MANUAL); } catch (SimasHebatException $ex) {}
$cek('selama blokir, SIMAS tidak dipanggil', $simas->panggilan === $sebelumPanggil);
Cache::forget(EmployeeSyncService::BLOCKED_CACHE_KEY);

echo "\n=== akun tanpa NIP ===\n";
$h = $svc->sync($u3, EmployeeSyncService::SOURCE_MANUAL);
$cek('dijawab no_nip, SIMAS tidak dipanggil', $h['status'] === 'no_nip');

echo "\n=== pendengar login ===\n";
Queue::fake();
$listener = new QueueEmployeeSyncOnLogin;
$login = fn ($u) => new Illuminate\Auth\Events\Login('web', $u, false);

Employee::where('user_id', 2)->update(['simas_checked_at' => now()->subHours(25)]);
$listener->handle($login($u2));
Queue::assertPushed(SyncEmployeeFromSimasJob::class, 1);
$cek('data basi: dijadwalkan', true);

$listener->handle($login($u2));
Queue::assertPushed(SyncEmployeeFromSimasJob::class, 1);
$cek('login kedua dalam 10 menit: tidak dobel', true);

Employee::where('user_id', 1)->update(['simas_checked_at' => now()->subHours(2)]);
$listener->handle($login($u1));
Queue::assertPushed(SyncEmployeeFromSimasJob::class, 1);
$cek('dicek 2 jam lalu: dilewati', true);

$listener->handle($login($u3));
Queue::assertPushed(SyncEmployeeFromSimasJob::class, 1);
$cek('akun tanpa NIP: dilewati', true);

Cache::flush();
Cache::put(EmployeeSyncService::BLOCKED_CACHE_KEY, true, 60);
Employee::where('user_id', 2)->update(['simas_checked_at' => null]);
$listener->handle($login($u2));
Queue::assertPushed(SyncEmployeeFromSimasJob::class, 1);
$cek('saat SIMAS memblokir: dilewati', true);
Cache::flush();

Schema::table('nawasara_registry_employees', fn ($t) => $t->renameColumn('simas_checked_at', 'x_checked'));
$tidakPecah = true;
try { $listener->handle($login($u2)); } catch (\Throwable $ex) { $tidakPecah = false; }
$cek('kolom hilang (belum migrate): login tidak pecah', $tidakPecah);
Schema::table('nawasara_registry_employees', fn ($t) => $t->renameColumn('x_checked', 'simas_checked_at'));

echo "\n=== job memakai service yang terdaftar ===\n";
$app->bind(EmployeeSyncService::class, fn () => $svc);
$simas->jawaban = $jawab('DINAS PENDIDIKAN', 'SMP NEGERI 3 PONOROGO');
(new SyncEmployeeFromSimasJob(1))->handle($svc);
$cek('job memindah keanggotaan', (int) Membership::where('user_id', 1)->value('opd_id') === $dindik->id);
$cek('sumber = login', Employee::where('user_id', 1)->value('simas_source') === 'login');
$simas->jawaban = SimasHebatException::unreachable();
$aman = true;
try { (new SyncEmployeeFromSimasJob(1))->handle($svc); } catch (\Throwable $ex) { $aman = false; }
$cek('galat SIMAS tidak membuat job gagal', $aman);

$pdo->exec('DROP DATABASE IF EXISTS db_uji_pegawai');
echo "\n".($gagal === 0 ? 'SEMUA LULUS' : "ADA {$gagal} YANG GAGAL")."\n";
exit($gagal === 0 ? 0 : 1);
