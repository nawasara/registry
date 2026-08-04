<?php

namespace Nawasara\Registry\Livewire\Membership;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Nawasara\Keycloak\Concerns\SearchesKeycloakDirectory;
use Nawasara\Registry\Models\Membership;
use Nawasara\Registry\Models\Opd;

/**
 * Admin: link a user to the OPD they belong to. This membership is what
 * activates per-OPD data scoping in consumer packages (hibah, etc.). A user
 * without a membership is "restricted" (sees nothing) unless they hold a
 * privileged role — see MembershipResolver.
 *
 * Orang dipilih dari direktori KEYCLOAK, bukan dari tabel `users` lokal.
 * Tabel lokal hanya berisi orang yang pernah login atau pernah ditambahkan
 * manual, jadi memilih dari sana membuat pegawai yang belum pernah membuka
 * Nawasara mustahil di-assign. Saat dipilih, user lokal dibuatkan otomatis
 * dengan role default (`auth.sso.default_role`, biasanya `guest`).
 */
class Index extends Component
{
    use WithPagination;
    use SearchesKeycloakDirectory;

    public ?int $opdId = null;

    /** Kata kunci pencarian direktori Keycloak. */
    public string $userSearch = '';

    public function mount(): void
    {
        $this->authorize('registry.membership.manage');
    }

    #[Computed]
    public function rows()
    {
        return Membership::query()
            ->with(['user:id,name,email,username,keycloak_id', 'opd:id,code,name'])
            ->latest('id')
            ->paginate(25);
    }

    #[Computed]
    public function opdOptions(): array
    {
        return Opd::orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * Kandidat dari direktori Keycloak, tanpa orang yang sudah tergabung di
     * OPD manapun (satu user hanya boleh satu OPD — unique di tabel).
     *
     * @return array<int, array{kc_id:string, kc_username:?string, name:string, nip:?string, email:?string, is_local:bool}>
     */
    #[Computed]
    public function userResults(): array
    {
        [$usernames, $emails] = $this->assignedIdentities();

        return $this->keycloakSearchResults($this->userSearch, $usernames, $emails);
    }

    /**
     * Tambahkan orang dari direktori Keycloak sebagai anggota OPD terpilih.
     *
     * Index merujuk posisi di hasil pencarian yang dihitung ulang server-side,
     * bukan identitas yang dipercaya dari DOM — lihat CLAUDE.md 13.f.
     */
    public function assign(int $index): void
    {
        $this->authorize('registry.membership.manage');

        $this->validate([
            'opdId' => ['required', 'exists:nawasara_registry_opd,id'],
        ], [], [
            'opdId' => 'OPD',
        ]);

        [$usernames, $emails] = $this->assignedIdentities();

        $kc = $this->keycloakUserAt($index, $this->userSearch, $usernames, $emails);

        if (! $kc) {
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Pilihan tidak valid, coba cari ulang.',
            ]);

            return;
        }

        $user = $this->provisioner()->fromSnapshot($kc);

        // Fail-safe: jangan pernah membuat keanggotaan kedua untuk satu user.
        // Daftar exclude di atas sudah menyaring, tapi baris lokal bisa saja
        // baru ter-provision di sela pencarian dan klik.
        if (Membership::where('user_id', $user->id)->exists()) {
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'User sudah terdaftar di OPD lain.',
            ]);

            return;
        }

        Membership::create([
            'user_id' => $user->id,
            'opd_id' => $this->opdId,
            'aktif' => true,
        ]);

        $this->reset(['opdId', 'userSearch']);
        $this->dispatch('modal-close:registry-membership-assign');
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Keanggotaan ditambahkan.']);
    }

    public function toggleAktif(int $id): void
    {
        $this->authorize('registry.membership.manage');

        $m = Membership::findOrFail($id);
        $m->update(['aktif' => ! $m->aktif]);

        $this->dispatch('toast', [
            'type' => 'info',
            'message' => $m->aktif ? 'Keanggotaan diaktifkan.' : 'Keanggotaan dinonaktifkan.',
        ]);
    }

    public function remove(int $id): void
    {
        $this->authorize('registry.membership.manage');

        Membership::findOrFail($id)->delete();
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Keanggotaan dihapus.']);
    }

    /**
     * Username + email milik user yang sudah punya keanggotaan, dalam lowercase,
     * untuk menyaring hasil pencarian direktori.
     *
     * @return array{0: array<int,string>, 1: array<int,string>}
     */
    protected function assignedIdentities(): array
    {
        $model = config('auth.providers.users.model');

        $assigned = $model::query()
            ->whereIn('id', Membership::pluck('user_id'))
            ->get(['username', 'email']);

        return [
            $assigned->pluck('username')->filter()->map(fn ($u) => mb_strtolower($u))->all(),
            $assigned->pluck('email')->filter()->map(fn ($e) => mb_strtolower($e))->all(),
        ];
    }

    public function render()
    {
        return view('nawasara-registry::livewire.pages.membership.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
