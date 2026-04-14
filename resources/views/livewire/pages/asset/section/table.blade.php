<div>
    <x-nawasara-ui::filter-bar searchPlaceholder="Cari identifier, OPD..." searchModel="search">
        <x-nawasara-ui::filter-dropdown label="Tipe" model="typeFilter"
            :items="array_merge(['all' => 'Semua Tipe'], config('nawasara-registry.asset_types', []))" />
        <x-nawasara-ui::filter-dropdown label="Status" model="statusFilter"
            :items="array_merge(['all' => 'Semua Status'], config('nawasara-registry.asset_statuses', []))" />

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
                        {{ $item->identifier }}
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
                            ['type' => 'click', 'label' => 'Edit', 'action' => 'openEdit', 'param' => $item->id, 'icon' => 'lucide-pencil'],
                            ['type' => 'delete', 'label' => 'Hapus', 'name' => $item->identifier],
                        ]" />
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-neutral-400">
                        Belum ada data aset.
                    </td>
                </tr>
            @endforelse
        </x-slot:table>

        <x-slot:footer>
            {{ $this->items->links() }}
        </x-slot:footer>
    </x-nawasara-ui::table>

    {{-- Modal Create/Edit --}}
    <x-nawasara-ui::modal wire:model="showModal" :title="$editingId ? 'Edit Aset' : 'Tambah Aset'">
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
            <button type="button" wire:click="$set('showModal', false)"
                class="py-2.5 px-4 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 hover:bg-gray-50 dark:bg-neutral-800 dark:border-neutral-700 dark:text-white">
                Batal
            </button>
            <x-nawasara-ui::button type="submit" form="registry-asset-form" color="primary">Simpan</x-nawasara-ui::button>
        </x-slot:footer>
    </x-nawasara-ui::modal>
</div>
