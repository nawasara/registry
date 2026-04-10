<?php

namespace Nawasara\Registry\Livewire\Opd\Section;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Nawasara\Registry\Models\Opd;

class Table extends Component
{
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
        Opd::findOrFail($id)->delete();
        toaster_success('OPD berhasil dihapus');
        $this->dispatch('opd-deleted');
    }

    public function render()
    {
        return view('nawasara-registry::livewire.pages.opd.section.table');
    }
}
