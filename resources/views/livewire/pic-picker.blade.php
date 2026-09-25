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

                    @if ($this->unitMissing)
                        {{-- Nama bidangnya cocok, tetapi barisnya belum pernah
                             dibuat. Tanpa keterangan ini layarnya tampak beres
                             sementara unit_id diam-diam tetap kosong. --}}
                        <p class="text-xs text-amber-700 dark:text-amber-300">
                            Bidang <strong>{{ $found['sub_unit'] }}</strong> belum terdaftar.
                        </p>
                    @endif
                @else
                    {{-- OPD-nya belum ada di registry. Ini yang membuat
                         SEKRETARIAT DAERAH dan 19 kecamatan masuk sendiri. --}}
                    <p class="text-xs text-amber-700 dark:text-amber-300">
                        OPD <strong>{{ $found['unit_kerja'] }}</strong> belum terdaftar.
                    </p>
                @endif

                @if ($createdNotice !== '')
                    <p class="text-xs text-emerald-700 dark:text-emerald-300">
                        {{ $createdNotice }}
                    </p>
                @elseif ($foundState === 'belum_sso')
                    {{-- Menautkan akun harus lewat Keycloak, dan orangnya belum
                         ada di sana. Membuat akun lokal begitu saja melahirkan
                         akun kedua saat ia benar-benar login nanti, dan keduanya
                         tidak saling mengenal. --}}
                    <p class="text-xs text-amber-700 dark:text-amber-300">
                        Belum pernah masuk lewat SSO, jadi belum bisa dijadikan
                        penanggung jawab.
                        @if (! $found['opd'] || $this->unitMissing)
                            OPD dan bidangnya tetap dapat didaftarkan sekarang.
                        @endif
                    </p>
                @endif

                @if (! $creatingOpd)
                    <div class="pt-2 flex flex-wrap items-center gap-2">
                        @if ($foundState === 'belum_sso')
                            {{-- Orangnya belum bisa ditetapkan, tetapi OPD dan
                                 bidangnya tetap bisa didaftarkan: keduanya tidak
                                 ada hubungannya dengan akun. Menutup semua jalan
                                 di sini membuat admin buntu padahal ada pekerjaan
                                 yang jelas bisa diselesaikan. --}}
                            @if (! $found['opd'] || $this->unitMissing)
                                <x-nawasara-ui::button color="warning" wire:click="review">
                                    Daftarkan {{ $found['opd'] ? 'bidangnya' : 'OPD-nya' }}
                                </x-nawasara-ui::button>
                            @endif
                        @else
                            {{-- Satu jalan, selalu lewat langkah pemeriksaan.

                                 Melewatinya saat semuanya "sudah dikenali"
                                 berarti melewatkan pemeriksaan justru di
                                 tempat salah deteksi paling berbahaya: yang
                                 sistemnya merasa yakin, sehingga tidak ada
                                 yang memeriksanya lagi. --}}
                            <x-nawasara-ui::button color="primary" wire:click="review">
                                Periksa &amp; jadikan Penanggung Jawab
                            </x-nawasara-ui::button>
                        @endif

                        <x-nawasara-ui::button color="neutral" wire:click="clearLookup">
                            Hapus hasil
                        </x-nawasara-ui::button>
                    </div>
                @endif

                {{-- Form pendaftaran OPD / bidang.

                     Semua isinya boleh dikoreksi. SIMAS memulangkan nama OPD
                     dan nama bidang menyatu tanpa pemisah, jadi pemisahannya
                     adalah tebakan sistem, dan tebakan itu bisa salah. Yang
                     melihat layar ini tahu mana yang benar. --}}
                @if ($creatingOpd)
                    <div class="mt-3 rounded-lg border border-amber-300 dark:border-amber-700/50 bg-amber-50 dark:bg-amber-900/30 p-3 space-y-3">
                        <div>
                            <p class="text-sm font-medium text-amber-800 dark:text-amber-200">
                                Periksa sebelum disimpan
                            </p>
                            <p class="text-xs text-neutral-600 dark:text-neutral-400 mt-1">
                                Belum ada yang tersimpan. Sistem memisahkan nama OPD
                                dan bidang dari satu teks tanpa pemisah, jadi
                                pemisahannya bisa meleset. Perbaiki bila perlu.
                            </p>
                        </div>

                        <div class="rounded bg-white/60 dark:bg-neutral-800 p-2">
                            <p class="text-xs text-neutral-500 dark:text-neutral-400">Teks asli dari SIMAS Hebat</p>
                            <p class="text-xs text-neutral-800 dark:text-neutral-100 mt-0.5">
                                {{ $found['unit_kerja'] }}
                            </p>
                        </div>

                        {{-- Apa yang akan terjadi, dinyatakan terang. Tanpa ini
                             admin tidak tahu mana yang sudah ada dan mana yang
                             akan lahir dari penekanan tombol ini. --}}
                        <div class="rounded bg-white/60 dark:bg-neutral-800 p-2 space-y-1">
                            <p class="text-xs text-neutral-500 dark:text-neutral-400">Yang akan disimpan</p>

                            <p class="text-xs text-neutral-800 dark:text-neutral-100">
                                OPD:
                                @if ($found['opd'])
                                    <strong>{{ $found['opd']->name }}</strong>
                                    <span class="text-neutral-500 dark:text-neutral-400">(sudah terdaftar)</span>
                                @else
                                    <strong>{{ $newOpdName ?: '(belum diisi)' }}</strong>
                                    <span class="text-amber-700 dark:text-amber-300">(akan dibuat)</span>
                                @endif
                            </p>

                            <p class="text-xs text-neutral-800 dark:text-neutral-100">
                                Bidang:
                                @if (trim($newUnitSimasName) === '')
                                    <span class="text-neutral-500 dark:text-neutral-400">tidak ada</span>
                                @elseif ($found['opd'] && ! $this->unitMissing)
                                    <strong>{{ $newUnitName ?: $newUnitSimasName }}</strong>
                                    <span class="text-neutral-500 dark:text-neutral-400">(sudah terdaftar)</span>
                                @else
                                    <strong>{{ $newUnitName ?: $newUnitSimasName }}</strong>
                                    <span class="text-amber-700 dark:text-amber-300">(akan dibuat)</span>
                                @endif
                            </p>

                            <p class="text-xs text-neutral-800 dark:text-neutral-100">
                                Penanggung jawab: <strong>{{ $found['nama_lengkap'] }}</strong>
                            </p>
                        </div>

                        @if ($found['opd'] && $newOpdSimasName === '')
                            {{-- OPD sudah dikenali, jadi kolomnya tidak perlu
                                 diisi. Tetapi pencocokan bisa salah mengenali
                                 OPD yang mirip namanya, dan tanpa jalan keluar
                                 di sini admin hanya bisa membatalkan. --}}
                            <p class="text-xs text-neutral-600 dark:text-neutral-400">
                                OPD-nya salah?
                                <button type="button" wire:click="forceCreateOpd"
                                    class="underline text-amber-700 dark:text-amber-300">
                                    daftarkan sebagai OPD lain
                                </button>
                            </p>
                        @endif

                        @if ($newOpdSimasName !== '' || ! $found['opd'])
                            <div class="space-y-2">
                                <div>
                                    <x-nawasara-ui::form.input
                                        type="text"
                                        wire:model="newOpdSimasName"
                                        label="Nama OPD menurut SIMAS" />
                                    <p class="mt-1 text-xs text-neutral-600 dark:text-neutral-400">
                                        Potongan awal teks di atas. Dipakai untuk mencocokkan
                                        pegawai berikutnya, jadi tulis persis seperti sumbernya.
                                    </p>
                                </div>

                                <div>
                                    <x-nawasara-ui::form.input
                                        type="text"
                                        wire:model="newOpdName"
                                        label="Nama OPD untuk ditampilkan" />
                                    <p class="mt-1 text-xs text-neutral-600 dark:text-neutral-400">
                                        Boleh dirapikan. Tidak memengaruhi pencocokan.
                                    </p>
                                </div>

                                <div>
                                    <x-nawasara-ui::form.input
                                        type="text"
                                        wire:model="newOpdCode"
                                        label="Kode OPD" />
                                    <p class="mt-1 text-xs text-neutral-600 dark:text-neutral-400">
                                        Singkatan seperti DISKOMINFO atau BAKESRA. Saran di atas
                                        hanya untuk menghemat ketikan.
                                    </p>
                                </div>

                                <div>
                                    <x-nawasara-ui::form.select
                                        wire:model="newOpdParentId"
                                        label="Bernaung di bawah (opsional)"
                                        :options="$this->opdOptions"
                                        placeholder="-- Berdiri sendiri --" />
                                    <p class="mt-1 text-xs text-neutral-600 dark:text-neutral-400">
                                        Untuk sekolah dan puskesmas, yang instansinya sendiri
                                        tetapi berada di bawah sebuah dinas.
                                    </p>
                                </div>
                            </div>
                        @endif

                        <div class="border-t border-amber-300 dark:border-amber-700/50 pt-2 space-y-2">
                            <div>
                                <x-nawasara-ui::form.input
                                    type="text"
                                    wire:model="newUnitSimasName"
                                    label="Nama bidang menurut SIMAS (opsional)" />
                                <p class="mt-1 text-xs text-neutral-600 dark:text-neutral-400">
                                    Sisa teks setelah nama OPD. Kosongkan bila orang ini
                                    tidak berada di bidang mana pun.
                                </p>
                            </div>

                            @if ($newUnitSimasName !== '')
                                <div>
                                    <x-nawasara-ui::form.input
                                        type="text"
                                        wire:model="newUnitName"
                                        label="Nama bidang untuk ditampilkan" />
                                </div>
                            @endif
                        </div>

                        @if ($createError !== '')
                            <p class="text-xs text-rose-600 dark:text-rose-400">{{ $createError }}</p>
                        @endif

                        <div class="flex flex-wrap items-center gap-2">
                            <x-nawasara-ui::button color="primary" wire:click="saveCreate">
                                @if ($foundState === 'belum_sso')
                                    Simpan OPD dan bidangnya
                                @else
                                    Simpan dan jadikan Penanggung Jawab
                                @endif
                            </x-nawasara-ui::button>

                            <x-nawasara-ui::button color="neutral" wire:click="cancelCreate">
                                Batal
                            </x-nawasara-ui::button>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
