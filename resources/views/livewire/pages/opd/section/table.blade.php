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
                            ['type' => 'href-navigate', 'label' => 'Edit', 'url' => route('nawasara-registry.opd.edit', $item->id), 'icon' => 'lucide-pencil', 'permission' => 'registry.opd.manage'],
                            ['type' => 'delete', 'label' => 'Hapus', 'name' => $item->name, 'permission' => 'registry.opd.manage'],
                        ]" />
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center">
                        <div class="inline-flex items-center justify-center size-12 rounded-2xl bg-gray-100 dark:bg-neutral-800 mb-3">
                            <x-lucide-building-2 class="size-6 text-gray-400 dark:text-neutral-500" />
                        </div>
                        <p class="text-sm font-semibold text-gray-700 dark:text-neutral-300">Belum ada data OPD</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-neutral-400">Tambahkan OPD untuk mulai mapping aset (DNS, mailbox, dst) ke unit kerja.</p>
                    </td>
                </tr>
            @endforelse
        </x-slot:table>

        <x-slot:footer>
            {{ $this->items->links() }}
        </x-slot:footer>
    </x-nawasara-ui::table>
</div>
