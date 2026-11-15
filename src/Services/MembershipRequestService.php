<?php

namespace Nawasara\Registry\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Nawasara\Keycloak\Models\KeycloakUser;
use Nawasara\Keycloak\Support\KeycloakUserProvisioner;
use Nawasara\Registry\Models\Membership;
use Nawasara\Registry\Models\MembershipRequest;
use Nawasara\Registry\Exceptions\MembershipRequestException;
use Nawasara\Registry\Models\Opd;

/**
 * Pengajuan petugas baru: admin OPD mengajukan, admin Registry memutuskan.
 *
 * ## Kenapa bukan admin OPD langsung menautkan
 *
 * Menautkan pegawai ke OPD = memberinya akses ke seluruh laporan OPD itu.
 * Tebakan yang meleset (NIP salah ketik, pegawai dinas lain) membuka data
 * warga ke orang yang tidak berhak, tanpa ada yang menyadarinya. Jadi admin
 * OPD hanya mengajukan; keputusan tetap di admin Registry. Bedanya dari
 * sebelumnya: admin Registry tinggal menekan Setujui, bukan mencari ulang
 * orangnya dan memasang tautan serta peran di dua layar terpisah.
 *
 * ## Peran yang boleh diajukan
 *
 * Hanya yang tercantum di `nawasara-registry.membership_request_roles`.
 * Peran bebas dari pemanggil akan menjadikan ini alat pemberian peran apa pun,
 * termasuk `developer`.
 */
class MembershipRequestService
{
    public function __construct(
        protected KeycloakUserProvisioner $provisioner,
    ) {}

    /** @return array<string, string> peran => label */
    public static function allowedRoles(): array
    {
        return (array) config('nawasara-registry.membership_request_roles', []);
    }

    /** NIP dibersihkan dari spasi/titik yang sering ikut tersalin. */
    public static function normalizeNip(string $nip): string
    {
        return preg_replace('/\D+/', '', $nip) ?? '';
    }

    /** Pegawai di direktori Keycloak (realm ASN: username = NIP). */
    public function findPerson(string $nip): ?KeycloakUser
    {
        $nip = self::normalizeNip($nip);

        if ($nip === '') {
            return null;
        }

        return KeycloakUser::query()
            ->where('username', $nip)
            ->where('enabled', true)
            ->first();
    }

    /**
     * @throws MembershipRequestException
     */
    public function submit(Opd $opd, string $nip, string $role, ?string $note, Authenticatable $requester): MembershipRequest
    {
        $nip = self::normalizeNip($nip);

        if (! array_key_exists($role, self::allowedRoles())) {
            throw new MembershipRequestException('Peran yang diajukan tidak dikenali.');
        }

        if (strlen($nip) !== 18) {
            throw new MembershipRequestException('NIP harus 18 digit.');
        }

        $person = $this->findPerson($nip);

        if (! $person) {
            throw new MembershipRequestException('NIP tidak ditemukan di Akun Kisara. Pastikan pegawai sudah pernah masuk ke Akun Kisara.');
        }

        $pending = MembershipRequest::where('nip', $nip)
            ->where('status', MembershipRequest::STATUS_PENDING)
            ->exists();

        if ($pending) {
            throw new MembershipRequestException('Pegawai ini sudah diajukan dan masih menunggu persetujuan.');
        }

        return MembershipRequest::create([
            'opd_id' => $opd->id,
            'nip' => $nip,
            'name' => $person->full_name ?: $person->username,
            'role' => $role,
            'note' => $note !== null ? trim($note) : null,
            'requested_by' => $requester->getAuthIdentifier(),
            'status' => MembershipRequest::STATUS_PENDING,
        ]);
    }

    /**
     * Setujui: buat akun lokal bila belum ada, tautkan ke OPD, beri peran.
     *
     * @throws MembershipRequestException
     */
    public function approve(MembershipRequest $request, Authenticatable $decider): MembershipRequest
    {
        $this->assertPending($request);

        if (! array_key_exists($request->role, self::allowedRoles())) {
            throw new MembershipRequestException('Peran pada pengajuan ini tidak lagi diizinkan.');
        }

        $person = $this->findPerson($request->nip);

        if (! $person) {
            throw new MembershipRequestException('NIP tidak ditemukan atau akunnya nonaktif di Akun Kisara.');
        }

        return DB::transaction(function () use ($request, $decider, $person) {
            $user = $this->provisioner->fromSnapshot($person);

            $existing = Membership::where('user_id', $user->id)->first();

            // Satu akun hanya boleh satu OPD (unik di tabel). Memindahkan
            // pegawai dari OPD lain adalah keputusan berbeda — tidak boleh
            // terjadi diam-diam lewat persetujuan pengajuan.
            if ($existing && (int) $existing->opd_id !== (int) $request->opd_id) {
                $opdLain = $existing->opd?->name ?? 'OPD lain';
                throw new MembershipRequestException("Pegawai ini sudah tertaut ke {$opdLain}. Pindahkan lewat halaman Keanggotaan OPD bila memang mutasi.");
            }

            if (! $existing) {
                Membership::create([
                    'user_id' => $user->id,
                    'opd_id' => $request->opd_id,
                    'aktif' => true,
                ]);
            } elseif (! $existing->aktif) {
                $existing->update(['aktif' => true]);
            }

            $user->assignRole($request->role);

            $request->update([
                'status' => MembershipRequest::STATUS_APPROVED,
                'decided_by' => $decider->getAuthIdentifier(),
                'decided_at' => now(),
            ]);

            activity('registry')
                ->performedOn($request)
                ->causedBy($decider)
                ->withProperties(['nip' => $request->nip, 'opd_id' => $request->opd_id, 'role' => $request->role])
                ->log('Pengajuan petugas disetujui');

            return $request->refresh();
        });
    }

    /**
     * @throws MembershipRequestException
     */
    public function reject(MembershipRequest $request, Authenticatable $decider, string $reason): MembershipRequest
    {
        $this->assertPending($request);

        $reason = trim($reason);

        if ($reason === '') {
            throw new MembershipRequestException('Alasan penolakan wajib diisi — admin OPD perlu tahu kenapa.');
        }

        $request->update([
            'status' => MembershipRequest::STATUS_REJECTED,
            'decided_by' => $decider->getAuthIdentifier(),
            'decided_at' => now(),
            'decision_note' => $reason,
        ]);

        activity('registry')
            ->performedOn($request)
            ->causedBy($decider)
            ->withProperties(['nip' => $request->nip, 'alasan' => $reason])
            ->log('Pengajuan petugas ditolak');

        return $request->refresh();
    }

    protected function assertPending(MembershipRequest $request): void
    {
        if (! $request->isPending()) {
            throw new MembershipRequestException('Pengajuan ini sudah diputuskan.');
        }
    }
}
