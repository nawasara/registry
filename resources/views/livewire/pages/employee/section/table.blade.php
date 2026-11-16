<div>
    <x-nawasara-ui::page-header
        title="Data Pegawai"
        description="Profil kepegawaian tiap akun menurut SIMAS Hebat, sumber resmi data ASN. Diperbarui otomatis saat login, paling sering sekali sehari. Pegawai yang mutasi otomatis pindah keanggotaan OPD-nya."
        :count="$this->rows->total()" />

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

            <x-nawasara-ui::search-input model="search" placeholder="Cari nama atau NIP..." />
        </div>

        <div data-filter-chips class="flex flex-wrap items-center gap-2"></div>
    </div>

    @if ($this->rows->total() === 0)
        @if ($search !== '' || $opdFilter !== '' || $statusFilter !== '')
            <x-nawasara-ui::empty-state icon="lucide-search-x"
                title="Tidak ada yang cocok"
                description="Ubah kata kunci atau saringannya." />
        @else
            <x-nawasara-ui::empty-state icon="lucide-inbox"
                title="Belum ada akun ber-NIP"
                description="Akun pegawai muncul di sini setelah login ke Nawasara lewat SSO." />
        @endif
    @else
        <x-nawasara-ui::table :headers="['Pegawai', 'OPD', 'Jabatan', 'Terakhir Diperbarui', 'Status', '']" stickyLast>
            <x-slot:table>
                @foreach ($this->rows as $row)
                    @php
                        $synced = $row->simas_synced_at ? \Illuminate\Support\Carbon::parse($row->simas_synced_at)->timezone('Asia/Jakarta') : null;
                        $sumber = ['login' => 'saat login', 'manual' => 'manual', 'pic' => 'penetapan PIC'][$row->simas_source] ?? null;
                    @endphp
                    <tr wire:key="emp-{{ $row->id }}">
                        <td class="px-4 py-3 text-sm">
                            <div class="font-medium text-neutral-800 dark:text-neutral-100">{{ $row->simas_nama ?: $row->name }}</div>
                            <div class="font-mono text-xs text-neutral-500 dark:text-neutral-400">{{ $row->nip ?: $row->username }}</div>
                        </td>
                        <td class="px-4 py-3 text-sm text-neutral-700 dark:text-neutral-200">
                            {{ $row->member_opd_name ?? 'Belum anggota OPD' }}
                            @if ($row->simas_skpd && $row->simas_opd_id === null)
                                <div class="mt-0.5 text-xs text-amber-700 dark:text-amber-400">SIMAS: {{ $row->simas_skpd }}</div>
                            @elseif ($row->simas_unit)
                                <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ $row->simas_unit }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-neutral-700 dark:text-neutral-200">{{ $row->nama_jabatan ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm text-neutral-700 dark:text-neutral-200">
                            @if ($synced)
                                <span title="{{ $synced->format('d M Y H:i') }} WIB">{{ $synced->diffForHumans() }}</span>
                                @if ($sumber)
                                    <div class="text-xs text-neutral-500 dark:text-neutral-400">{{ $sumber }}</div>
                                @endif
                            @else
                                <span class="text-neutral-500 dark:text-neutral-400">Belum pernah</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm">
                            @if ($row->simas_status === 'ok' && $row->simas_opd_id)
                                <x-nawasara-ui::badge color="success">Sesuai SIMAS</x-nawasara-ui::badge>
                            @elseif ($row->simas_status === 'ok')
                                <x-nawasara-ui::badge color="warning">OPD belum dikenal</x-nawasara-ui::badge>
                            @elseif ($row->simas_status === 'not_found')
                                <x-nawasara-ui::badge color="danger">Tidak ada di SIMAS</x-nawasara-ui::badge>
                            @elseif ($row->simas_status === 'error')
                                <x-nawasara-ui::badge color="danger">Gagal</x-nawasara-ui::badge>
                            @else
                                <x-nawasara-ui::badge color="neutral">Belum diperbarui</x-nawasara-ui::badge>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <x-nawasara-ui::dropdown-menu-action :id="$row->id" :items="[
                                ['type' => 'click', 'label' => 'Lihat rincian', 'wire:click' => 'showDetail('.$row->id.')',
                                 'modal' => 'registry-employee-detail', 'icon' => 'lucide-eye', 'permission' => 'registry.employee.view'],
                                ['type' => 'click', 'label' => 'Perbarui dari SIMAS', 'wire:click' => 'syncNow('.$row->id.')',
                                 'icon' => 'lucide-refresh-cw', 'permission' => 'registry.employee.sync'],
                            ]" />
                        </td>
                    </tr>
                @endforeach
            </x-slot:table>
        </x-nawasara-ui::table>

        <div class="mt-4">{{ $this->rows->links() }}</div>
    @endif
</div>
