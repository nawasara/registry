<?php

namespace Nawasara\Registry\Livewire\Opd\Section;

use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Nawasara\Registry\Models\Opd;
use Nawasara\Ui\Livewire\Concerns\HasBrowserToast;
use Nawasara\Ui\Livewire\Concerns\HasExport;

class Table extends Component
{
    use HasBrowserToast;
    use HasExport;
    use WithPagination;

    public string $search = '';

    public function updatedSearch()
    {
        $this->resetPage();
    }

    #[Computed]
    public function items()
    {
        return Opd::query()
            ->withCount(['pics', 'assets'])
            ->search($this->search)
            ->orderBy('name')
            ->paginate(15);
    }

    #[On('opd-deleted')]
    public function onDelete()
    {
        // refresh computed
    }

    public function delete($id)
    {
        Gate::authorize('registry.opd.manage');

        Opd::findOrFail($id)->delete();
        $this->toastSuccess('OPD berhasil dihapus');
        $this->dispatch('opd-deleted');
    }

    /**
     * Export filename base — timestamp + extension appended by HasExport.
     */
    protected function exportFilename(): string
    {
        return 'registry-opd';
    }

    /**
     * Export the FULL dataset (ignoring search filter) per spec. Each row is
     * an associative array; the keys become spreadsheet column headers.
     * Counts are eagerly loaded so the export can include them without N+1.
     */
    protected function exportData(): iterable
    {
        return Opd::query()
            ->withCount(['pics', 'assets'])
            ->orderBy('name')
            ->get()
            ->map(fn (Opd $opd) => [
                'Kode' => $opd->code,
                'Nama OPD' => $opd->name,
                'Email' => $opd->email,
                'Telepon' => $opd->phone,
                'Alamat' => $opd->address,
                'Jumlah PIC' => $opd->pics_count,
                'Jumlah Aset' => $opd->assets_count,
                'Dibuat' => optional($opd->created_at)->format('Y-m-d H:i'),
                'Diubah' => optional($opd->updated_at)->format('Y-m-d H:i'),
            ]);
    }

    public function render()
    {
        return view('nawasara-registry::livewire.pages.opd.section.table');
    }
}
