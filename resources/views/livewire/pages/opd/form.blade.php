<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[
                ['label' => 'Registry', 'url' => '#'],
                ['label' => 'OPD', 'url' => route('nawasara-registry.opd.index')],
                ['label' => $opdId ? 'Edit' : 'Tambah']
            ]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page.title>{{ $opdId ? 'Edit OPD' : 'Tambah OPD' }}</x-nawasara-ui::page.title>

        <form wire:submit="save" class="space-y-6">
            {{-- Data OPD --}}
            <x-nawasara-ui::page.card>
                <h3 class="text-base font-semibold text-gray-800 dark:text-neutral-200 mb-4">Data OPD</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-nawasara-ui::form.input label="Kode OPD" placeholder="DISKOMINFO"
                        wire:model="code" useError errorVariable="code" />
                    <x-nawasara-ui::form.input label="Nama OPD" placeholder="Dinas Komunikasi dan Informatika"
                        wire:model="name" useError errorVariable="name" />
                    <x-nawasara-ui::form.input label="Email" type="email" placeholder="kominfo@kab.go.id"
                        wire:model="email" useError errorVariable="email" />
                    <x-nawasara-ui::form.input label="Telepon" placeholder="0352-xxxxxx"
                        wire:model="phone" useError errorVariable="phone" />
                    <div class="md:col-span-2">
                        <x-nawasara-ui::form.textarea label="Alamat" placeholder="Jl. ..."
                            wire:model="address" useError errorVariable="address" />
                    </div>
                </div>
            </x-nawasara-ui::page.card>

            {{-- PIC List --}}
            <x-nawasara-ui::page.card>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-semibold text-gray-800 dark:text-neutral-200">Penanggung Jawab (PIC)</h3>
                    <button type="button" wire:click="addPic"
                        class="py-2 px-3 inline-flex items-center gap-x-1 text-xs font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-sm hover:bg-gray-50 dark:bg-neutral-800 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-700">
                        <x-lucide-plus class="size-3" />
                        Tambah PIC
                    </button>
                </div>

                @forelse ($pics as $index => $pic)
                    <div class="relative border border-gray-200 dark:border-neutral-700 rounded-lg p-4 mb-3 {{ $pic['is_primary'] ? 'bg-green-50/50 dark:bg-green-900/10 border-green-200 dark:border-green-800/50' : '' }}">
                        {{-- Primary badge & actions --}}
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                @if ($pic['is_primary'])
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                        PIC Utama
                                    </span>
                                @else
                                    <button type="button" wire:click="setPrimary({{ $index }})"
                                        class="text-xs text-blue-600 hover:underline">Jadikan PIC Utama</button>
                                @endif
                            </div>
                            <button type="button" wire:click="removePic({{ $index }})"
                                class="text-red-500 hover:text-red-700 text-xs">Hapus</button>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <x-nawasara-ui::form.input label="Nama" placeholder="Nama PIC"
                                wire:model="pics.{{ $index }}.name" useError errorVariable="pics.{{ $index }}.name" />
                            <x-nawasara-ui::form.input label="Jabatan" placeholder="Kabid Infrastruktur"
                                wire:model="pics.{{ $index }}.position" />
                            <x-nawasara-ui::form.input label="Telepon" placeholder="08xx"
                                wire:model="pics.{{ $index }}.phone" />
                            <x-nawasara-ui::form.input label="Email" type="email" placeholder="pic@kab.go.id"
                                wire:model="pics.{{ $index }}.email" />
                        </div>
                    </div>
                @empty
                    <div class="text-center py-6 text-sm text-gray-400 dark:text-neutral-500">
                        Belum ada PIC. Klik "Tambah PIC" untuk menambahkan.
                    </div>
                @endforelse
            </x-nawasara-ui::page.card>

            {{-- Actions --}}
            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('nawasara-registry.opd.index') }}" wire:navigate
                    class="py-2.5 px-4 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-sm hover:bg-gray-50 dark:bg-neutral-800 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-700">
                    Batal
                </a>
                <x-nawasara-ui::button type="submit" color="primary">
                    Simpan
                </x-nawasara-ui::button>
            </div>
        </form>
    </x-nawasara-ui::page.container>
</div>
