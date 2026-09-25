<?php
/*
 * Membuat akun untuk pegawai yang dikenali SIMAS tetapi belum pernah SSO.
 *
 * Yang paling penting diuji BUKAN bahwa akunnya terbuat, melainkan bahwa
 * akun itu nanti DIADOPSI saat orangnya benar-benar login. Kalau tidak,
 * lahir akun kedua dan keduanya tidak saling mengenal.
 */

use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Nawasara\Keycloak\Support\KeycloakUserProvisioner;
use Nawasara\Registry\Livewire\PicPicker;
use Nawasara\Registry\Models\Membership;
use Nawasara\Registry\Models\Opd;
use Nawasara\Registry\Services\PicAssignmentService;
use Nawasara\Registry\Services\SimasHebatClient;
use Nawasara\Registry\Services\UnitKerjaMatcher;

$gagal = 0;
$cek = function (string $l, bool $ok, string $d = '') use (&$gagal) {
    if (! $ok) { $gagal++; }
    echo ($ok ? '  OK   ' : '  GAGAL')." {$l}".($d ? "\n         {$d}" : '')."\n";
};

$nip = '198406072006041007';
$userModel = config('auth.providers.users.model');

// Bersihkan sisa uji sebelumnya.
$userModel::where('username', $nip)->delete();

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

echo "=== pencarian: ditandai akan dibuatkan akun, TANPA menulis ===\n";
$sebelumUser = $userModel::count();

$c = Livewire::test(PicPicker::class)->set('nip', $nip)->call('lookup');
$cek('ditemukan di SIMAS', ($c->get('found')['nama_lengkap'] ?? '') === 'BAYU SUSANTO SE');
$cek('ditandai akun akan dibuat', $c->get('foundState') === PicPicker::ACCOUNT_WILL_CREATE, 'dapat: '.$c->get('foundState'));
$cek('pencarian TIDAK membuat akun', $userModel::count() === $sebelumUser,
    $sebelumUser.' -> '.$userModel::count());

echo "\n=== pemeriksaan juga belum menulis ===\n";
$c->call('review');
$cek('form terbuka', $c->get('creatingOpd') === true);
$cek('masih belum ada akun', $userModel::count() === $sebelumUser);

echo "\n=== simpan: akun dibuat dan orangnya ditetapkan ===\n";
$c->call('saveCreate');
$cek('tidak ada galat', $c->get('createError') === '', $c->get('createError'));

$user = $userModel::where('username', $nip)->first();
$cek('akun terbuat', $user !== null);
$cek('username = NIP', $user?->username === $nip);
$cek('auth_type = sso', $user?->auth_type === 'sso', 'dapat: '.$user?->auth_type);
$cek('keycloak_id masih kosong (memang belum tahu)', $user?->keycloak_id === null);
$cek('nama dari SIMAS', $user?->name === 'BAYU SUSANTO SE', 'dapat: '.$user?->name);
// Role bawaan hanya diberikan kalau rolenya memang ada; di basis data lokal
// ini belum ada `guest`, dan provisioner sengaja melewatinya tanpa galat.
$adaGuest = \Spatie\Permission\Models\Role::where('name', 'guest')->exists();
$cek('role bawaan diberikan bila rolenya ada',
    ! $adaGuest || ($user && $user->hasRole('guest')),
    $adaGuest ? 'role: '.$user?->getRoleNames()->implode(',') : 'role guest belum ada di lokal, dilewati');

$cek('terpilih sebagai PJ di layar', (int) $c->get('pjUserId') === (int) $user?->id);

// Keanggotaan OPD BELUM terbentuk di sini, dan itu memang rancangannya:
// memilih di layar tidak menulis apa pun. Yang menulis adalah penyimpanan
// form aset, lewat PicAssignmentService::assign().
$bpbd = Opd::where('code','BPBD')->first();
$cek('keanggotaan BELUM ada (menunggu form aset disimpan)',
    Membership::where('user_id', $user?->id)->doesntExist());

echo "\n=== form aset disimpan: barulah keanggotaan terbentuk ===\n";
$aset = \Nawasara\Registry\Models\Asset::create([
    'package_ref' => 'uji-akun-baru', 'external_id' => 'a-'.time(),
    'type' => 'domain', 'identifier' => 'uji-akun-baru.ponorogo.go.id',
    'status' => 'active', 'registered_at' => now(),
]);
app(PicAssignmentService::class)->assign(asset: $aset, user: $user);

$cek('jadi anggota BPBD', Membership::where('user_id',$user->id)->value('opd_id') == $bpbd?->id,
    'opd_id: '.(Membership::where('user_id',$user->id)->value('opd_id') ?? 'null'));
$cek('aset menunjuk orang ini', (int) $aset->fresh()->pj_user_id === (int) $user->id);

echo "\n=== INTI: saat orangnya LOGIN nanti, akun ini DIADOPSI ===\n";
$sub = 'kc-uuid-'.uniqid();
$sebelum = $userModel::count();

$hasil = app(KeycloakUserProvisioner::class)->fromClaims([
    'id' => $sub,
    'username' => $nip,          // Keycloak memakai NIP sebagai username
    'email' => 'bayu@ponorogo.go.id',
    'name' => 'BAYU SUSANTO, S.E.',
]);

$cek('TIDAK lahir akun kedua', $userModel::count() === $sebelum,
    $sebelum.' -> '.$userModel::count());
$cek('yang dipakai akun yang SAMA', (int) $hasil->getAuthIdentifier() === (int) $user->id,
    'id lama '.$user->id.', dapat '.$hasil->getAuthIdentifier());

$user->refresh();
$cek('keycloak_id kini terisi', $user->keycloak_id === $sub, 'dapat: '.var_export($user->keycloak_id, true));
$cek('keanggotaan OPD tetap', Membership::where('user_id',$user->id)->value('opd_id') == $bpbd?->id);

echo "\n=== memanggil dua kali tidak menggandakan ===\n";
$sebelum = $userModel::count();
app(KeycloakUserProvisioner::class)->fromExternalIdentity($nip, 'BAYU SUSANTO SE');
$cek('jumlah akun tetap', $userModel::count() === $sebelum);

echo "\n".($gagal === 0 ? 'SEMUA LULUS' : "ADA {$gagal} YANG GAGAL")."\n";
