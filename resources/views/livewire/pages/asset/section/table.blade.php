<div>
    @php
        $typeOptions = config('nawasara-registry.asset_types', []);
        $statusOptions = config('nawasara-registry.asset_statuses', []);
        // OPD options keyed by id => name for the filter-panel option list.
        $opdOptions = $this->opdList->mapWithKeys(fn ($o) => [(string) $o->id => $o->code.' - '.$o->name])->all();
    @endphp

    {{-- Page header — title left, primary "Tambah Aset" right.
         No time-window: assets are stable resources (not events), so a
         default 7-day filter would hide most rows on load. --}}
    <x-nawasara-ui::page-header
        title="Aset Digital"
        description="Master daftar aset (DNS, mailbox, VM) yang Nawasara kelola, mapped ke OPD/PIC."
        :count="$this->items->total().' aset'">
        @can('registry.asset.manage')
            <x-nawasara-ui::button wire:click="$dispatch('openCreateAsset')" color="success"
                @click="$dispatch('open-modal', 'registry-asset-form')">
                <x-slot:icon><x-lucide-plus class="size-4" /></x-slot:icon>
                Tambah Aset
            </x-nawasara-ui::button>
        @endcan
    </x-nawasara-ui::page-header>

    {{-- Toolbar — Tipe + Status + OPD multi-select filters, search, the
         "Perlu Review" highlight button, and export. --}}
    <div class="space-y-2 mb-4">
        <div class="flex flex-col md:flex-row md:flex-nowrap md:items-center gap-2">
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <x-nawasara-ui::filter-panel
                    label="Filter"
                    :state="['typeFilter' => $typeFilter, 'statusFilter' => $statusFilter, 'opdFilter' => $opdFilter]"
                    :multiple="['typeFilter', 'statusFilter', 'opdFilter']"
                    :labels="['typeFilter' => $typeOptions, 'statusFilter' => $statusOptions, 'opdFilter' => $opdOptions]"
                    :dimensions="['typeFilter' => 'Tipe', 'statusFilter' => 'Status', 'opdFilter' => 'OPD']">
                    <x-nawasara-ui::filter-group label="Tipe" model="typeFilter" :items="$typeOptions" icon="lucide-package" />
                    <x-nawasara-ui::filter-group label="Status" model="statusFilter" :items="$statusOptions" icon="lucide-circle-check" />
                    <x-nawasara-ui::filter-group label="OPD" model="opdFilter" :items="$opdOptions" icon="lucide-building-2" />
                </x-nawasara-ui::filter-panel>

                {{-- "Perlu Review" toggle — special CTA for discovered
                     but unassigned assets. Stays inline next to the filter
                     panel as a one-click highlight, not a generic dim. --}}
                @if ($this->discoveredCount > 0)
                    <button type="button" wire:click="$toggle('onlyDiscovered')"
                        class="inline-flex items-center gap-2 h-10 px-3 rounded-lg border text-sm font-medium transition-colors shadow-sm
                            {{ $onlyDiscovered
                                ? 'border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100 dark:bg-amber-900/30 dark:border-amber-700 dark:text-amber-300'
                                : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-700' }}">
                        <x-lucide-sparkles class="size-4" />
                        Perlu Review
                        <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full text-[11px] font-semibold bg-amber-600 text-white">
                            {{ $this->discoveredCount }}
                        </span>
                    </button>
                @endif
            </div>

            <x-nawasara-ui::search-input model="search" placeholder="Cari identifier atau OPD..." />

            <div class="flex items-center gap-2 shrink-0">
                <x-nawasara-ui::export-button
                    action="export"
                    tooltip="Ekspor registry aset"
                    permission="registry.asset.manage" />
            </div>
        </div>

        <div wire:ignore data-filter-chips></div>

        @if ($search)
            <div class="flex flex-wrap items-center gap-2">
                <x-nawasara-ui::filter-chip label="Cari: {{ $search }}" model="search" />
            </div>
        @endif
    </div>

    <x-nawasara-ui::table
        stickyLast
        :headers="['#', 'Identifier', 'Tipe', 'OPD', 'PIC', 'Status', 'Dibuat', '']">
        <x-slot:table>
            @forelse ($this->items as $item)
                <tr wire:key="asset-{{ $item->id }}">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $item->id }}</td>
                    <td class="px-6 py-4 text-sm font-medium text-gray-800 dark:text-neutral-200">
                        <div class="flex items-center gap-2">
                            <span>{{ $item->identifier }}</span>
                            @if ($item->discovered_at && (! $item->opd_id || ! $item->pic_id))
                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400"
                                    title="Auto-discovered {{ $item->discovered_at->diffForHumans() }} — perlu assign OPD/PIC">
                                    <x-lucide-sparkles class="size-3" />
                                    NEW
                                </span>
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <x-nawasara-ui::badge color="purple">{{ $item->type_label }}</x-nawasara-ui::badge>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @if ($item->opd)
                            <span class="text-gray-800 dark:text-neutral-200">{{ $item->opd->name }}</span>
                        @else
                            <x-nawasara-ui::badge color="warning">Belum ditetapkan</x-nawasara-ui::badge>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400">
                        {{ $item->pic->name ?? '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @php
                            $assetStatusColor = match($item->status) {
                                'active' => 'success',
                                'pending' => 'warning',
                                default => 'neutral',
                            };
                        @endphp
                        <x-nawasara-ui::badge :color="$assetStatusColor">
                            {{ $item->status_label }}
                        </x-nawasara-ui::badge>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400">
                        {{ $item->created_at->format('d M Y') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                        <x-nawasara-ui::dropdown-menu-action :id="$item->id" :items="[
                            ['type' => 'click', 'label' => 'Edit', 'action' => 'openEdit', 'param' => $item->id, 'modal' => 'registry-asset-form', 'icon' => 'lucide-pencil', 'permission' => 'registry.asset.manage'],
                            ['type' => 'delete', 'label' => 'Hapus', 'name' => $item->identifier, 'permission' => 'registry.asset.manage'],
                        ]" />
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">
                        @if ($search || ! empty($typeFilter) || ! empty($statusFilter) || ! empty($opdFilter) || $onlyDiscovered)
                            <x-nawasara-ui::empty-state
                                icon="lucide-search-x"
                                title="Tidak ada aset yang cocok"
                                description="Coba ubah filter atau hapus search keyword."
                                variant="filter"
                                inline />
                        @else
                            <x-nawasara-ui::empty-state
                                icon="lucide-package"
                                title="Belum ada data aset"
                                description="Aset (DNS records, mailbox, VM) akan auto-populate dari sync service masing-masing."
                                inline />
                        @endif
                    </td>
                </tr>
            @endforelse
        </x-slot:table>

        <x-slot:footer>
            {{ $this->items->links() }}
        </x-slot:footer>
    </x-nawasara-ui::table>

    {{-- Modal Create/Edit --}}
    <x-nawasara-ui::modal id="registry-asset-form" :title="$editingId ? 'Edit Aset' : 'Tambah Aset'">
        <form wire:submit="saveAsset" id="registry-asset-form" class="space-y-4">
            <div>
                <x-nawasara-ui::form.label value="OPD" />
                <x-nawasara-ui::form.select wire:model.live="assetOpdId" name="assetOpdId" placeholder="Pilih OPD">
                    @foreach ($this->opdList as $opd)
                        <option value="{{ $opd->id }}">{{ $opd->code }} - {{ $opd->name }}</option>
                    @endforeach
                </x-nawasara-ui::form.select>
            </div>

            @if ($assetOpdId)
                <div>
                    <x-nawasara-ui::form.label value="PIC (opsional)" />
                    <x-nawasara-ui::form.select wire:model="assetPicId" placeholder="Pilih PIC">
                        @foreach ($this->picList as $pic)
                            <option value="{{ $pic->id }}">{{ $pic->name }} {{ $pic->position ? "({$pic->position})" : '' }}</option>
                        @endforeach
                    </x-nawasara-ui::form.select>
                </div>
            @endif

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <x-nawasara-ui::form.label value="Tipe Aset" />
                    <x-nawasara-ui::form.select wire:model="assetType" name="assetType" placeholder="Pilih tipe">
                        @foreach (config('nawasara-registry.asset_types', []) as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-nawasara-ui::form.select>
                </div>
                <div>
                    <x-nawasara-ui::form.label value="Status" />
                    <x-nawasara-ui::form.select wire:model="assetStatus" name="assetStatus" :placeholder="false">
                        @foreach (config('nawasara-registry.asset_statuses', []) as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-nawasara-ui::form.select>
                </div>
            </div>

            <x-nawasara-ui::form.input label="Identifier" placeholder="portal.dinasX.go.id"
                wire:model="assetIdentifier" useError errorVariable="assetIdentifier" />

            <x-nawasara-ui::form.input label="Referensi Ticket (opsional)" placeholder="#TKT-2026-001"
                wire:model="assetTicketRef" />

            <x-nawasara-ui::form.textarea label="Catatan (opsional)" placeholder="Catatan tambahan..."
                wire:model="assetNotes" />

        </form>

        <x-slot:footer>
            <x-nawasara-ui::button color="neutral" variant="outline"
                @click="$dispatch('close-modal', 'registry-asset-form')">
                Batal
            </x-nawasara-ui::button>
            <x-nawasara-ui::button type="submit" form="registry-asset-form" color="primary">Simpan</x-nawasara-ui::button>
        </x-slot:footer>
    </x-nawasara-ui::modal>
</div>
