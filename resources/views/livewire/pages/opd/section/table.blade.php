<div>
    {{-- Toolbar — search left/center, export right. No filter dimensions on
         OPD (only one model + search), so the filter zone is omitted; the
         search field expands to fill its slot.

         Layout mirrors the DNS records page so the design language stays
         consistent across modules. --}}
    <div class="space-y-2 mb-4">
        <div class="flex flex-col md:flex-row md:flex-nowrap md:items-center gap-2">
            {{-- Search zone — fills available space. --}}
            <x-nawasara-ui::search-input model="search" placeholder="Cari kode atau nama OPD..." />

            {{-- Action zone. Export full OPD dataset (xlsx/csv/json).
                 Gated on registry.opd.manage so only admins can export PII
                 like contact numbers/addresses. --}}
            <div class="flex items-center gap-2 shrink-0">
                <x-nawasara-ui::export-button
                    action="export"
                    tooltip="Ekspor data OPD"
                    permission="registry.opd.manage" />
            </div>
        </div>

        {{-- Search chip — keeps the active search visible after the input
             scrolls out of view on small screens. --}}
        @if ($search)
            <div class="flex flex-wrap items-center gap-2">
                <x-nawasara-ui::filter-chip label="Cari: {{ $search }}" model="search" />
            </div>
        @endif
    </div>

    <x-nawasara-ui::table
        stickyLast
        :headers="['#', 'Kode', 'Nama OPD', 'PIC', 'Aset', 'Kontak', 'Dibuat', '']"
        :title="'Data OPD ('.$this->items->total().' OPD)'">
        <x-slot:table>
            @forelse ($this->items as $item)
                <tr wire:key="opd-{{ $item->id }}">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400">
                        {{ $item->id }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-800 dark:text-neutral-200">
                        {{ $item->code }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-800 dark:text-neutral-200">
                        {{ $item->name }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 dark:text-neutral-200">
                        <x-nawasara-ui::badge color="blue">{{ $item->pics_count }}</x-nawasara-ui::badge>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 dark:text-neutral-200">
                        <x-nawasara-ui::badge color="purple">{{ $item->assets_count }}</x-nawasara-ui::badge>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400">
                        {{ $item->email ?? '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400">
                        {{ $item->created_at->format('d M Y') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                        <x-nawasara-ui::dropdown-menu-action :id="$item->id" :items="[
                            ['type' => 'href-navigate', 'label' => 'Edit', 'url' => route('nawasara-registry.opd.edit', $item->id), 'icon' => 'lucide-pencil', 'permission' => 'registry.opd.manage'],
                            ['type' => 'delete', 'label' => 'Hapus', 'name' => $item->name, 'permission' => 'registry.opd.manage'],
                        ]" />
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">
                        @if ($search)
                            <x-nawasara-ui::empty-state
                                icon="lucide-search-x"
                                title="Tidak ada OPD yang cocok"
                                description="Coba ubah keyword pencarian."
                                variant="filter"
                                inline />
                        @else
                            <x-nawasara-ui::empty-state
                                icon="lucide-building-2"
                                title="Belum ada data OPD"
                                description="Tambahkan OPD untuk mulai mapping aset (DNS, mailbox, dst) ke unit kerja."
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
</div>
