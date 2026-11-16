<?php

namespace Nawasara\Registry\Livewire\Employee;

use Livewire\Component;

/**
 * Data Pegawai: profil kepegawaian tiap akun Nawasara menurut SIMAS Hebat,
 * kapan terakhir diperbarui, dan aksi memperbaruinya. Hanya kerangka;
 * tabel di Section\Table, rincian di Section\Detail.
 */
class Index extends Component
{
    public function mount(): void
    {
        $this->authorize('registry.employee.view');
    }

    public function render()
    {
        return view('nawasara-registry::livewire.pages.employee.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
