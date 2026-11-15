<div>
    <x-nawasara-ui::page-header
        title="Pengajuan Petugas"
        description="Admin OPD mengajukan pegawai sebagai petugas atau Kabid di panel GO. Menyetujui akan menautkan pegawai ke OPD pengaju dan memberinya peran."
        :count="$this->pendingCount.' menunggu'" />

    <div class="space-y-2 mb-4">
        <div class="flex flex-col md:flex-row md:flex-nowrap md:items-center gap-2">
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <x-nawasara-ui::filter-panel label="Filter"
                    :state="['statusFilter' => $statusFilter, 'opdFilter' => $opdFilter]"
                    :labels="['statusFilter' => $this->statusOptions, 'opdFilter' => $this->opdOptions]">
                    <x-nawasara-ui::filter-group label="Status" model="statusFilter"
                        :items="$this->statusOptions" icon="lucide-circle-dot" />
                    <x-nawasara-ui::filter-group label="OPD" model="opdFilter"
                        :items="$this->opdOptions" icon="lucide-building-2" />
                </x-nawasara-ui::filter-panel>
            </div>

            <x-nawasara-ui::search-input model="search" placeholder="Cari NIP atau nama..." />
        </div>

        <div data-filter-chips class="flex flex-wrap items-center gap-2"></div>
    </div>

    @if ($this->rows->total() === 0)
        @if ($search !== '' || $opdFilter !== '' || $statusFilter !== 'pending')
            <x-nawasara-ui::empty-state icon="lucide-search-x"
                title="Tidak ada yang cocok"
                description="Ubah kata kunci atau saringannya." />
        @else
            <x-nawasara-ui::empty-state icon="lucide-inbox"
                title="Tidak ada pengajuan yang menunggu"
                description="Pengajuan dari admin OPD di panel GO akan muncul di sini." />
        @endif
    @else
        <x-nawasara-ui::table :headers="['Pegawai', 'OPD', 'Peran', 'Diajukan', 'Status', '']" stickyLast>
            <x-slot:table>
                @foreach ($this->rows as $row)
                    <tr wire:key="mreq-{{ $row->id }}">
                        <td class="px-4 py-3 text-sm">
                            <div class="font-medium text-neutral-800 dark:text-neutral-100">{{ $row->name ?? '—' }}</div>
                            <div class="font-mono text-xs text-neutral-500 dark:text-neutral-400">{{ $row->nip }}</div>
                        </td>
                        <td class="px-4 py-3 text-sm text-neutral-700 dark:text-neutral-200">{{ $row->opd?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-neutral-700 dark:text-neutral-200">
                            {{ $this->roleLabels[$row->role] ?? $row->role }}
                            @if ($row->note)
                                <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ $row->note }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-neutral-700 dark:text-neutral-200">
                            {{ $row->requester?->name ?? '—' }}
                            <div class="text-xs text-neutral-500 dark:text-neutral-400">{{ $row->created_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }}</div>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            @if ($row->status === 'pending')
                                <x-nawasara-ui::badge color="warning">Menunggu</x-nawasara-ui::badge>
                            @elseif ($row->status === 'approved')
                                <x-nawasara-ui::badge color="success">Disetujui</x-nawasara-ui::badge>
                            @else
                                <x-nawasara-ui::badge color="danger">Ditolak</x-nawasara-ui::badge>
                                @if ($row->decision_note)
                                    <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ $row->decision_note }}</div>
                                @endif
                            @endif
                            @if ($row->decider)
                                <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">oleh {{ $row->decider->name }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if ($row->status === 'pending')
                                <x-nawasara-ui::dropdown-menu-action :id="$row->id" :items="[
                                    ['type' => 'click', 'label' => 'Setujui', 'wire:click' => 'approve('.$row->id.')',
                                     'icon' => 'lucide-check', 'confirm' => 'Setujui pengajuan ini? Pegawai akan ditautkan ke OPD dan mendapat perannya.',
                                     'permission' => 'registry.membership.manage'],
                                    ['type' => 'click', 'label' => 'Tolak', 'wire:click' => 'openReject('.$row->id.')',
                                     'icon' => 'lucide-x', 'permission' => 'registry.membership.manage'],
                                ]" />
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-slot:table>
        </x-nawasara-ui::table>

        <div class="mt-4">{{ $this->rows->links() }}</div>
    @endif

    <x-nawasara-ui::modal id="registry-request-reject" title="Tolak Pengajuan" maxWidth="md">
        <x-nawasara-ui::form.textarea label="Alasan penolakan" wire:model="rejectReason" :rows="3"
            placeholder="Mis. NIP ini pegawai Dinas Kesehatan, bukan DPUPKP." />
        @error('rejectReason') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        <p class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">Alasan ini dibaca admin OPD di panel GO.</p>

        <x-slot:footer>
            <x-nawasara-ui::button color="neutral" x-on:click="$dispatch('close-modal', 'registry-request-reject')">Batal</x-nawasara-ui::button>
            <x-nawasara-ui::button color="danger" wire:click="reject">Tolak</x-nawasara-ui::button>
        </x-slot:footer>
    </x-nawasara-ui::modal>
</div>
