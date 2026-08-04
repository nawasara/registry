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

        {{-- Pilih OPD dulu, lalu cari orangnya di direktori Keycloak. Klik satu
             hasil = langsung assign (tidak ada tombol Simpan terpisah), karena
             tiap baris hasil adalah aksi tersendiri. --}}
        <x-nawasara-ui::modal id="registry-membership-assign" title="Tambah Keanggotaan" maxWidth="lg">
            <div class="space-y-4">
                <div>
                    <x-nawasara-ui::form.select
                        label="OPD"
                        wire:model.live="opdId"
                        placeholder="— pilih OPD —"
                        :options="$this->opdOptions" />
                    @error('opdId') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                </div>

                <div>
                    <x-nawasara-ui::form.label value="Cari Orang" />
                    <x-nawasara-ui::form.input
                        wire:model.live.debounce.400ms="userSearch"
                        placeholder="Ketik nama, username, atau email (min. 2 huruf)…"
                        autocomplete="off"
                        :disabled="! $opdId" />
                    <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                        Data diambil dari direktori Keycloak. Orang yang belum punya akun
                        Nawasara akan dibuatkan otomatis dengan role default.
                    </p>
                </div>

                <div class="max-h-72 overflow-y-auto rounded-lg border border-neutral-200 dark:border-neutral-700">
                    @php $results = $opdId ? $this->userResults : []; @endphp
                    @forelse ($results as $idx => $u)
                        <button type="button"
                            wire:key="kc-{{ $u['kc_id'] }}"
                            wire:click="assign({{ $idx }})"
                            class="flex w-full items-center justify-between gap-3 border-b border-neutral-100 px-3 py-2.5 text-left last:border-0 hover:bg-emerald-50 dark:border-neutral-800 dark:hover:bg-emerald-900/20">
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-neutral-800 dark:text-neutral-100">{{ $u['name'] }}</span>
                                <span class="block truncate text-xs text-neutral-500 dark:text-neutral-400">
                                    @if ($u['nip'])
                                        NIP {{ $u['nip'] }}
                                    @elseif ($u['email'])
                                        {{ $u['email'] }}
                                    @else
                                        {{ $u['kc_username'] }}
                                    @endif
                                </span>
                            </span>
                            <span class="inline-flex shrink-0 items-center gap-2">
                                @unless ($u['is_local'])
                                    <x-nawasara-ui::badge color="info">Akun baru</x-nawasara-ui::badge>
                                @endunless
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                    <x-lucide-plus class="size-3.5" /> Tambah
                                </span>
                            </span>
                        </button>
                    @empty
                        <div class="px-3 py-6 text-center text-sm text-neutral-500 dark:text-neutral-400">
                            @if (! $opdId)
                                Pilih OPD terlebih dahulu.
                            @elseif (mb_strlen(trim($userSearch)) < 2)
                                Ketik minimal 2 huruf untuk mencari.
                            @else
                                Tidak ada orang yang cocok, atau semuanya sudah tergabung di OPD lain.
                            @endif
                        </div>
                    @endforelse
                </div>
            </div>

            <x-slot:footer>
                <x-nawasara-ui::button color="neutral" variant="outline"
                    x-on:click="$dispatch('close-modal', 'registry-membership-assign')">Tutup</x-nawasara-ui::button>
            </x-slot:footer>
        </x-nawasara-ui::modal>
    </x-nawasara-ui::page.container>
</div>
