<?php

namespace Nawasara\Registry\Livewire\Employee\Section;

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Nawasara\Registry\Models\Opd;

/**
 * Akun Nawasara yang ber-NIP, beserta data kepegawaiannya dari SIMAS Hebat.
 *
 * Satu baris per akun, bukan per pegawai: SIMAS tidak punya daftar pegawai,
 * jadi yang dapat ditampilkan hanya orang yang sudah punya akun di sini.
 */
class Table extends Component
{
    use SyncsFromSimas, WithPagination;

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $opdFilter = '';

    #[Url]
    public string $search = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['statusFilter', 'opdFilter', 'search'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function statusOptions(): array
    {
        return [
            'ok' => 'Sesuai SIMAS',
            'opd_unknown' => 'OPD belum dikenal Registry',
            'not_found' => 'Tidak ada di SIMAS',
            'error' => 'Gagal diperbarui',
            'never' => 'Belum pernah diperbarui',
        ];
    }

    #[Computed]
    public function opdOptions(): array
    {
        return Opd::orderBy('name')->pluck('name', 'id')->mapWithKeys(fn ($n, $id) => [(string) $id => $n])->all();
    }

    #[Computed]
    public function rows()
    {
        $userModel = config('auth.providers.users.model');
        $users = (new $userModel)->getTable();

        return $userModel::query()
            ->leftJoin('nawasara_registry_employees as e', 'e.user_id', '=', "{$users}.id")
            ->leftJoin('nawasara_registry_memberships as m', 'm.user_id', '=', "{$users}.id")
            ->leftJoin('nawasara_registry_opd as o', 'o.id', '=', 'm.opd_id')
            ->leftJoin('nawasara_registry_opd as so', 'so.id', '=', 'e.simas_opd_id')
            ->where(fn ($q) => $q->whereRaw("{$users}.username REGEXP '^[0-9]{18}$'")->orWhereNotNull('e.nip'))
            ->when($this->statusFilter !== '', function ($q) {
                match ($this->statusFilter) {
                    'never' => $q->whereNull('e.simas_checked_at'),
                    'opd_unknown' => $q->where('e.simas_status', 'ok')->whereNull('e.simas_opd_id'),
                    'ok' => $q->where('e.simas_status', 'ok')->whereNotNull('e.simas_opd_id'),
                    default => $q->where('e.simas_status', $this->statusFilter),
                };
            })
            ->when($this->opdFilter !== '', fn ($q) => $q->where('m.opd_id', (int) $this->opdFilter))
            ->when(trim($this->search) !== '', function ($q) use ($users) {
                $term = '%'.trim($this->search).'%';
                $q->where(fn ($w) => $w->where("{$users}.name", 'like', $term)
                    ->orWhere("{$users}.username", 'like', $term)
                    ->orWhere('e.nip', 'like', $term)
                    ->orWhere('e.simas_nama', 'like', $term));
            })
            ->select([
                "{$users}.id", "{$users}.name", "{$users}.username",
                'e.nip', 'e.simas_nama', 'e.nama_jabatan', 'e.simas_skpd', 'e.simas_unit',
                'e.simas_status', 'e.simas_source', 'e.simas_synced_at', 'e.simas_checked_at',
                'e.simas_opd_id', 'm.opd_id as member_opd_id', 'm.aktif as member_aktif',
                'o.name as member_opd_name', 'so.name as simas_opd_name',
            ])
            ->orderBy("{$users}.name")
            ->paginate(25);
    }

    public function showDetail(int $userId): void
    {
        $this->authorize('registry.employee.view');
        $this->dispatch('registry-employee-detail', userId: $userId);
    }

    #[On('registry-employee-synced')]
    public function refreshRows(): void
    {
        unset($this->rows);
    }

    public function render()
    {
        return view('nawasara-registry::livewire.pages.employee.section.table');
    }
}
