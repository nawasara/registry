<div class="space-y-3">
    <div>
        <x-nawasara-ui::form.select
            wire:model.live="opdId"
            label="OPD"
            :options="$this->opdOptions"
            placeholder="-- Pilih OPD --" />
    </div>

    <div>
        <x-nawasara-ui::form.select
            wire:model.live="pjUserId"
            label="Penanggung Jawab"
            :options="$this->pjCandidates"
            :placeholder="$opdId ? '-- Pilih Penanggung Jawab --' : '-- Pilih OPD dulu --'"
            :disabled="! $opdId" />

        @if ($opdId && count($this->pjCandidates) === 0)
            <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                Belum ada anggota di OPD ini. Cari lewat NIP di bawah.
            </p>
        @endif
    </div>

    {{-- Pencarian NIP: untuk orang yang belum terdaftar sebagai anggota OPD. --}}
    <div class="rounded-lg border border-gray-200 dark:border-neutral-700 p-3 space-y-2">
        <p class="text-xs text-neutral-600 dark:text-neutral-300">
            Orangnya belum ada di daftar? Cari lewat NIP di SIMAS Hebat.
        </p>

        <div class="flex items-end gap-2">
            <div class="flex-1">
                <x-nawasara-ui::form.input
                    type="text"
                    wire:model="nip"
                    wire:keydown.enter="lookup"
                    label="NIP"
                    placeholder="18 angka" />
            </div>

            <x-nawasara-ui::button
                color="primary"
                wire:click="lookup"
                wire:loading.attr="disabled"
                wire:target="lookup">
                <span wire:loading.remove wire:target="lookup">Cari</span>
                <span wire:loading wire:target="lookup">Mencari...</span>
            </x-nawasara-ui::button>
        </div>

        @if ($lookupError !== '')
            <p class="text-xs text-rose-600 dark:text-rose-400">{{ $lookupError }}</p>
        @endif

        @if ($found)
            <div class="rounded-md bg-emerald-50 dark:bg-emerald-900/30 p-3 space-y-1">
                <p class="text-sm font-medium text-neutral-800 dark:text-neutral-100">
                    {{ $found['nama_lengkap'] }}
                </p>

                @if ($found['nama_jabatan'])
                    <p class="text-xs text-neutral-700 dark:text-neutral-300">
                        {{ $found['nama_jabatan'] }}
                        @if ($found['jenis_jabatan'])
                            <span class="text-neutral-500 dark:text-neutral-400">
                                ({{ $found['jenis_jabatan'] }})
                            </span>
                        @endif
                    </p>
                @endif

                @if ($found['unit_kerja'])
                    <p class="text-xs text-neutral-600 dark:text-neutral-400">
                        {{ $found['unit_kerja'] }}
                    </p>
                @endif

                @if ($found['opd'])
                    <p class="text-xs text-emerald-700 dark:text-emerald-300">
                        Dikenali sebagai: <strong>{{ $found['opd']->name }}</strong>
                        @if ($found['sub_unit'])
                            &middot; {{ $found['sub_unit'] }}
                        @endif
                    </p>
                @else
                    {{-- OPD-nya belum ada di registry. Ini yang membuat
                         SEKRETARIAT DAERAH dan 19 kecamatan masuk sendiri. --}}
                    <p class="text-xs text-amber-700 dark:text-amber-300">
                        OPD <strong>{{ $found['unit_kerja'] }}</strong> belum terdaftar.
                        Buat dulu saat menyimpan.
                    </p>
                @endif

                <div class="pt-2 flex items-center gap-2">
                    @if ($foundState === 'belum_sso')
                        {{-- Tanpa akun Keycloak tidak ada yang bisa ditautkan.
                             Membuat akun lokal begitu saja melahirkan akun kedua
                             saat orangnya benar-benar login nanti. --}}
                        <p class="text-xs text-amber-700 dark:text-amber-300">
                            Belum pernah masuk lewat SSO, jadi belum bisa dijadikan
                            penanggung jawab.
                        </p>
                    @else
                        <x-nawasara-ui::button color="primary" wire:click="selectFound">
                            Jadikan Penanggung Jawab
                        </x-nawasara-ui::button>
                    @endif

                    <x-nawasara-ui::button color="neutral" wire:click="clearLookup">
                        Hapus hasil
                    </x-nawasara-ui::button>
                </div>
            </div>
        @endif
    </div>
</div>
