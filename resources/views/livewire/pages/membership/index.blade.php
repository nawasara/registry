<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Registry Aset', 'url' => '#'], ['label' => 'Keanggotaan OPD']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page-header
            title="Keanggotaan OPD"
            description="Tautkan user ke OPD. Menentukan data OPD mana yang bisa diakses user di seluruh modul (hibah, dll)."
            :count="$this->rows->total().' anggota'">
            <x-nawasara-ui::button color="primary"
                x-on:click="$dispatch('open-modal', 'registry-membership-assign')">
                <x-slot:icon><x-lucide-user-plus class="size-4" /></x-slot:icon>
                Tambah Keanggotaan
            </x-nawasara-ui::button>
        </x-nawasara-ui::page-header>

        <x-nawasara-ui::table stickyLast :headers="['Nama', 'Email', 'OPD', 'Status', '']">
            <x-slot:table>
                @forelse ($this->rows as $m)
                    <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-700/40">
                        <td class="px-4 py-2.5 text-sm text-neutral-800 dark:text-neutral-100">{{ $m->user?->name ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-sm text-neutral-600 dark:text-neutral-300">{{ $m->user?->email ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-sm text-neutral-800 dark:text-neutral-100">{{ $m->opd?->name ?? '—' }}</td>
                        <td class="px-4 py-2.5">
                            @if ($m->aktif)
                                <x-nawasara-ui::badge color="success">Aktif</x-nawasara-ui::badge>
                            @else
                                <x-nawasara-ui::badge color="neutral">Nonaktif</x-nawasara-ui::badge>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            <div class="inline-flex items-center gap-1">
                                <x-nawasara-ui::icon-button
                                    :icon="$m->aktif ? 'toggle-right' : 'toggle-left'"
                                    :tooltip="$m->aktif ? 'Nonaktifkan' : 'Aktifkan'"
                                    wire:click="toggleAktif({{ $m->id }})" />
                                <x-nawasara-ui::icon-button icon="trash-2" tooltip="Hapus"
                                    wire:click="remove({{ $m->id }})"
                                    wire:confirm="Hapus keanggotaan {{ $m->user?->name }}?" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6">
                            <x-nawasara-ui::empty-state inline
                                icon="lucide-users"
                                title="Belum ada keanggotaan"
                                description="Tambahkan user dan tautkan ke OPD-nya." />
                        </td>
                    </tr>
                @endforelse
            </x-slot:table>
        </x-nawasara-ui::table>

        <div class="mt-4">{{ $this->rows->links() }}</div>

        <x-nawasara-ui::modal id="registry-membership-assign" title="Tambah Keanggotaan" maxWidth="md">
            <form wire:submit="assign" class="space-y-4">
                <div>
                    <x-nawasara-ui::form.select
                        label="User"
                        wire:model="userId"
                        placeholder="— pilih user —"
                        :options="$this->userOptions" />
                    @error('userId') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                </div>
                <div>
                    <x-nawasara-ui::form.select
                        label="OPD"
                        wire:model="opdId"
                        placeholder="— pilih OPD —"
                        :options="$this->opdOptions" />
                    @error('opdId') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                </div>
            </form>

            <x-slot:footer>
                <x-nawasara-ui::button color="primary" wire:click="assign">Simpan</x-nawasara-ui::button>
                <x-nawasara-ui::button color="neutral" variant="outline"
                    x-on:click="$dispatch('close-modal', 'registry-membership-assign')">Batal</x-nawasara-ui::button>
            </x-slot:footer>
        </x-nawasara-ui::modal>
    </x-nawasara-ui::page.container>
</div>
