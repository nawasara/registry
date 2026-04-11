<div>
    <x-nawasara-ui::filter-bar searchPlaceholder="Cari kode, nama OPD..." searchModel="search" />

    <x-nawasara-ui::table :headers="['#', 'Kode', 'Nama OPD', 'PIC', 'Aset', 'Kontak', 'Dibuat', '']" title="Data OPD">
        <x-slot:table>
            @forelse ($this->items as $item)
                <tr>
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
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                            {{ $item->pics_count }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 dark:text-neutral-200">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">
                            {{ $item->assets_count }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400">
                        {{ $item->email ?? '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400">
                        {{ $item->created_at->format('d M Y') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                        <x-nawasara-ui::dropdown-menu-action :id="$item->id" :items="[
                            ['type' => 'href-navigate', 'label' => 'Edit', 'url' => route('nawasara-registry.opd.edit', $item->id), 'icon' => 'lucide-pencil'],
                            ['type' => 'delete', 'label' => 'Hapus', 'name' => $item->name],
                        ]" />
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-neutral-400">
                        Belum ada data OPD.
                    </td>
                </tr>
            @endforelse
        </x-slot:table>

        <x-slot:footer>
            {{ $this->items->links() }}
        </x-slot:footer>
    </x-nawasara-ui::table>
</div>
