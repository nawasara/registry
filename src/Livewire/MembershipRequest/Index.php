<?php

namespace Nawasara\Registry\Livewire\MembershipRequest;

use Livewire\Component;

/**
 * Antrean pengajuan petugas dari admin OPD (lihat MembershipRequestService).
 * Halaman ini hanya kerangka; tabel dan aksinya di Section\Table.
 */
class Index extends Component
{
    public function mount(): void
    {
        $this->authorize('registry.membership.manage');
    }

    public function render()
    {
        return view('nawasara-registry::livewire.pages.membership-request.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
