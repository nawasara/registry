<div>
    <x-nawasara-ui::modal id="registry-employee-detail" title="Rincian Pegawai" maxWidth="2xl">
        @if ($d)
            @php
                $e = $d['employee'];
                $wib = fn ($t) => $t ? $t->copy()->timezone('Asia/Jakarta')->format('d M Y H:i').' WIB' : '-';
                $sumber = ['login' => 'otomatis saat login', 'manual' => 'manual', 'pic' => 'saat penetapan PIC'][$e?->simas_source] ?? '-';
            @endphp

            <div class="space-y-5 text-sm">
                <div>
                    <div class="text-base font-semibold text-neutral-800 dark:text-neutral-100">{{ $e?->simas_nama ?: $d['user']->name }}</div>
                    <div class="font-mono text-xs text-neutral-500 dark:text-neutral-400">{{ $e?->nip ?: $d['user']->username }}</div>
                </div>

                <section class="space-y-2">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Menurut SIMAS Hebat</h3>
                    @if (! $e || ! $e->simas_checked_at)
                        <p class="text-neutral-600 dark:text-neutral-300">Belum pernah diperbarui dari SIMAS Hebat.</p>
                    @else
                        <dl class="grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-3">
                            <dt class="text-neutral-500 dark:text-neutral-400">SKPD</dt>
                            <dd class="sm:col-span-2 text-neutral-800 dark:text-neutral-100">
                                {{ $e->simas_skpd ?? '-' }}
                                @if ($e->simas_skpd && ! $e->simas_opd_id)
                                    <div class="mt-0.5 text-xs text-amber-700 dark:text-amber-400">
                                        Belum dikenal Registry. Daftarkan OPD-nya atau isi nama versi SIMAS pada OPD yang sesuai, lalu perbarui lagi.
                                    </div>
                                @endif
                            </dd>
                            <dt class="text-neutral-500 dark:text-neutral-400">Unit</dt>
                            <dd class="sm:col-span-2 text-neutral-800 dark:text-neutral-100">{{ $e->simas_unit ?? '-' }}</dd>
                            <dt class="text-neutral-500 dark:text-neutral-400">Jabatan</dt>
                            <dd class="sm:col-span-2 text-neutral-800 dark:text-neutral-100">{{ $e->nama_jabatan ?? '-' }}</dd>
                            <dt class="text-neutral-500 dark:text-neutral-400">Jenis jabatan</dt>
                            <dd class="sm:col-span-2 text-neutral-800 dark:text-neutral-100">{{ $e->jenis_jabatan ?? '-' }}</dd>
                            <dt class="text-neutral-500 dark:text-neutral-400">Kepegawaian</dt>
                            <dd class="sm:col-span-2 text-neutral-800 dark:text-neutral-100">{{ $e->jenis_kepegawaian ?? '-' }}</dd>
                            <dt class="text-neutral-500 dark:text-neutral-400">Terakhir sinkron</dt>
                            <dd class="sm:col-span-2 text-neutral-800 dark:text-neutral-100">
                                {{ $wib($e->simas_synced_at) }}
                                <span class="text-xs text-neutral-500 dark:text-neutral-400">({{ $sumber }})</span>
                            </dd>
                            <dt class="text-neutral-500 dark:text-neutral-400">Percobaan terakhir</dt>
                            <dd class="sm:col-span-2 text-neutral-800 dark:text-neutral-100">
                                {{ $wib($e->simas_checked_at) }}
                                @if ($e->simas_status === 'not_found')
                                    <div class="mt-0.5 text-xs text-red-600 dark:text-red-400">NIP tidak ditemukan di SIMAS Hebat.</div>
                                @elseif ($e->simas_status === 'error')
                                    <div class="mt-0.5 text-xs text-red-600 dark:text-red-400">{{ $e->simas_error }}</div>
                                @endif
                            </dd>
                        </dl>
                    @endif
                </section>

                <section class="space-y-2">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Keanggotaan OPD</h3>
                    @if ($d['membership'])
                        <p class="text-neutral-800 dark:text-neutral-100">
                            {{ $d['membership']->opd?->name ?? '-' }}
                            @unless ($d['membership']->aktif)
                                <x-nawasara-ui::badge color="neutral">Nonaktif</x-nawasara-ui::badge>
                            @endunless
                        </p>
                        @if ($d['moves']->isNotEmpty())
                            <ul class="space-y-1 text-xs text-neutral-600 dark:text-neutral-300">
                                @foreach ($d['moves'] as $move)
                                    <li>
                                        {{ $move->created_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }}:
                                        @if (empty($move->properties['dari']))
                                            menjadi anggota {{ $move->properties['ke'] ?? '-' }}
                                        @else
                                            dipindah dari {{ $move->properties['dari'] }} ke {{ $move->properties['ke'] ?? '-' }}
                                        @endif
                                        ({{ ['login' => 'saat login', 'manual' => 'manual', 'pic' => 'penetapan PIC'][$move->properties['sumber'] ?? ''] ?? '-' }})
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @else
                        <p class="text-neutral-600 dark:text-neutral-300">
                            Belum menjadi anggota OPD mana pun. Keanggotaan dibuat otomatis saat data diperbarui, asalkan SKPD dari SIMAS sudah dikenal Registry.
                        </p>
                    @endif
                </section>

                <section class="space-y-2">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Aset yang dipegang</h3>
                    @forelse ($d['assets'] as $asset)
                        <div class="flex items-start justify-between gap-3 border-b border-gray-200 pb-1 last:border-0 dark:border-neutral-700">
                            <div>
                                <div class="text-neutral-800 dark:text-neutral-100">{{ $asset->identifier }}</div>
                                <div class="text-xs text-neutral-500 dark:text-neutral-400">
                                    {{ (int) $asset->pj_user_id === (int) $d['user']->id ? 'PIC utama' : 'PIC cadangan' }}
                                    · {{ $asset->opd?->name ?? 'tanpa OPD' }}
                                </div>
                            </div>
                            @if ($d['membership'] && $asset->opd_id && (int) $asset->opd_id !== (int) $d['membership']->opd_id)
                                <x-nawasara-ui::badge color="warning">Beda OPD</x-nawasara-ui::badge>
                            @endif
                        </div>
                    @empty
                        <p class="text-neutral-600 dark:text-neutral-300">Tidak memegang aset.</p>
                    @endforelse
                    @if ($d['assets']->contains(fn ($a) => $d['membership'] && $a->opd_id && (int) $a->opd_id !== (int) $d['membership']->opd_id))
                        <p class="text-xs text-amber-700 dark:text-amber-400">
                            Aset bertanda "Beda OPD" tetap milik OPD-nya. Bila PIC-nya sudah mutasi, tetapkan PIC baru dari halaman Aset Digital.
                        </p>
                    @endif
                </section>
            </div>
        @endif

        <x-slot:footer>
            <x-nawasara-ui::button color="neutral" x-on:click="$dispatch('close-modal', 'registry-employee-detail')">Tutup</x-nawasara-ui::button>
            @can('registry.employee.sync')
                @if ($userId)
                    <x-nawasara-ui::button color="primary" wire:click="syncNow({{ $userId }})" wire:loading.attr="disabled" wire:target="syncNow">
                        Perbarui dari SIMAS
                    </x-nawasara-ui::button>
                @endif
            @endcan
        </x-slot:footer>
    </x-nawasara-ui::modal>
</div>
