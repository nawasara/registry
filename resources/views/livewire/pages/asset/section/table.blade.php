<div>
    <x-nawasara-ui::filter-bar searchPlaceholder="Cari identifier, OPD..." searchModel="search">
        <x-nawasara-ui::filter-dropdown label="Tipe" model="typeFilter"
            :items="array_merge(['all' => 'Semua Tipe'], config('nawasara-registry.asset_types', []))" />
        <x-nawasara-ui::filter-dropdown label="Status" model="statusFilter"
            :items="array_merge(['all' => 'Semua Status'], config('nawasara-registry.asset_statuses', []))" />

        @if ($this->discoveredCount > 0)
            <x-nawasara-ui::button
                :color="$onlyDiscovered ? 'warning' : 'neutral'"
                :variant="$onlyDiscovered ? 'flat' : 'outline'"
                size="sm"
                wire:click="$toggle('onlyDiscovered')">
                <x-slot:icon><x-lucide-sparkles /></x-slot:icon>
                Perlu Review
                <x-slot:trailing>
                    <x-nawasara-ui::badge color="warning" variant="solid" size="sm">
                        {{ $this->discoveredCount }}
                    </x-nawasara-ui::badge>
                </x-slot:trailing>
            </x-nawasara-ui::button>
        @endif

        <x-slot:chips>
            @if ($typeFilter)
                <x-nawasara-ui::filter-chip label="Tipe: {{ config('nawasara-registry.asset_types.'.$typeFilter, $typeFilter) }}" model="typeFilter" />
            @endif
            @if ($statusFilter)
                <x-nawasara-ui::filter-chip label="Status: {{ config('nawasara-registry.asset_statuses.'.$statusFilter, $statusFilter) }}" model="statusFilter" />
            @endif
            @if ($search)
                <x-nawasara-ui::filter-chip label="Cari: {{ $search }}" model="search" />
            @endif
        </x-slot:chips>
    </x-nawasara-ui::filter-bar>

    <x-nawasara-ui::table :headers="['#', 'Identifier', 'Tipe', 'OPD', 'PIC', 'Status', 'Dibuat', '']" title="Daftar Aset Digital">
        <x-slot:table>
            @forelse ($this->items as $item)
                <tr>
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
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">
                            {{ $item->type_label }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @if ($item->opd)
                            <span class="text-gray-800 dark:text-neutral-200">{{ $item->opd->name }}</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-50 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">
                                Belum ditetapkan
                            </span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400">
                        {{ $item->pic->name ?? '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @php
                            $statusClass = match($item->status) {
                                'active' => 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                                'inactive' => 'bg-gray-100 text-gray-600 dark:bg-neutral-700 dark:text-neutral-400',
                                'pending' => 'bg-yellow-50 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
                                default => 'bg-gray-100 text-gray-800',
                            };
                        @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">
                            {{ $item->status_label }}
                        </span>
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
                    <td colspan="7">
                        <x-nawasara-ui::empty-state
                            icon="lucide-package"
                            title="Belum ada data aset"
                            description="Aset (DNS records, mailbox, VM) akan auto-populate dari sync service masing-masing."
                            inline />
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
