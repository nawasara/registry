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

            {{-- Penanggung jawab / anggota OPD dikelola lewat halaman Keanggotaan
                 (user ↔ OPD via membership), data kontaknya bersumber dari
                 Keycloak — tidak lagi diketik manual di sini. --}}
            <x-nawasara-ui::page.card>
                <div class="flex items-start gap-3">
                    <x-lucide-info class="size-5 shrink-0 text-sky-500 mt-0.5" />
                    <p class="text-sm text-gray-600 dark:text-neutral-400">
                        Penanggung jawab &amp; anggota OPD dikelola di halaman
                        <a href="{{ route('nawasara-registry.membership.index') }}" wire:navigate
                           class="text-emerald-600 dark:text-emerald-400 hover:underline">Keanggotaan</a>.
                        Data kontak (nama, NIP, WhatsApp, email) otomatis bersumber dari Keycloak.
                    </p>
                </div>
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
