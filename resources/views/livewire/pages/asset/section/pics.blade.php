<div>
    <x-nawasara-ui::modal id="registry-asset-pics" title="OPD & PIC">
        @if ($this->asset)
            @php
                $primary = $this->asset->assignments->firstWhere('role', 'primary');
                $backups = $this->asset->assignments->where('role', 'backup');
            @endphp
            <div class="space-y-5">
                <div>
                    <p class="font-mono text-sm text-neutral-800 dark:text-neutral-100">{{ $this->asset->identifier }}</p>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">
                        {{ $this->asset->type_label }} · {{ $this->asset->opd?->name ?? 'Belum ada OPD' }}
                    </p>
                </div>

                {{-- ===== OPD & PIC utama ===== --}}
                <section class="space-y-2">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-neutral-800 dark:text-neutral-100">OPD &amp; PIC utama</p>
                        @if ($mode !== 'primary')
                            <x-nawasara-ui::button :color="$primary ? 'info' : 'primary'" size="sm" wire:click="startPrimary">
                                {{ $primary ? 'Ganti' : 'Tetapkan' }}
                            </x-nawasara-ui::button>
                        @endif
                    </div>

                    <div class="flex items-center gap-3 rounded-lg border border-neutral-200 px-4 py-3 dark:border-neutral-700">
                        @if ($primary)
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm text-neutral-800 dark:text-neutral-100">{{ $primary->user?->name ?? 'Pengguna #'.$primary->user_id }}</p>
                                <p class="truncate text-xs text-neutral-500 dark:text-neutral-400">{{ $primary->user?->email }}</p>
                            </div>
                            <x-nawasara-ui::badge color="info">{{ $roleLabels['primary'] }}</x-nawasara-ui::badge>
                            <x-nawasara-ui::button color="danger" size="sm"
                                wire:click="clearPrimary"
                                wire:confirm="Lepas {{ $primary->user?->name ?? 'orang ini' }} sebagai PIC utama? Aksesnya ke aset ini lewat Akun ASN ikut berakhir.">
                                Lepas
                            </x-nawasara-ui::button>
                        @elseif ($mode === 'primary')
                            <p class="text-sm text-neutral-500 dark:text-neutral-400">Belum ada PIC utama. Pilih OPD dan orangnya di bawah.</p>
                        @else
                            {{-- Kotak berbingkai ini tampak seperti kolom isian, jadi orang
                                 mengkliknya. Dulu hanya teks dan klik itu tidak berbuat apa-apa
                                 — terasa macet. Kini kotaknya sendiri membuka picker. --}}
                            <button type="button" wire:click="startPrimary"
                                class="-mx-4 -my-3 flex w-[calc(100%+2rem)] items-center justify-between gap-3 rounded-lg px-4 py-3 text-left text-sm text-neutral-500 hover:bg-neutral-50 hover:text-neutral-800 dark:text-neutral-400 dark:hover:bg-neutral-700/40 dark:hover:text-neutral-100">
                                <span>Belum ada PIC utama — klik untuk menetapkan</span>
                                <x-lucide-plus class="size-4 shrink-0" />
                            </button>
                        @endif
                    </div>

                    @if ($mode === 'primary')
                        <div class="space-y-3 rounded-lg border border-dashed border-neutral-300 p-4 dark:border-neutral-600">
                            <livewire:nawasara-registry.pic-picker event="primary-pic-selected"
                                :opd-id="$pickedOpdId" :pj-user-id="$pickedUserId"
                                :key="'primary-picker-'.$assetId" />

                            @if ($picMismatch)
                                {{-- Ditahan, bukan diperbaiki sendiri: yang dipertaruhkan
                                     adalah siapa boleh melihat data OPD mana. --}}
                                <div class="space-y-2 rounded-lg border border-amber-300 bg-amber-50 p-3 dark:border-amber-700/50 dark:bg-amber-900/30">
                                    <p class="text-sm font-medium text-amber-800 dark:text-amber-200">OPD orang ini berbeda menurut SIMAS Hebat</p>
                                    <dl class="space-y-1 text-xs">
                                        <div class="flex gap-2">
                                            <dt class="w-36 shrink-0 text-neutral-600 dark:text-neutral-400">Tercatat di Nawasara</dt>
                                            <dd class="text-neutral-800 dark:text-neutral-100">{{ $picMismatch['tercatat'] }}</dd>
                                        </div>
                                        <div class="flex gap-2">
                                            <dt class="w-36 shrink-0 text-neutral-600 dark:text-neutral-400">Menurut SIMAS Hebat</dt>
                                            <dd class="text-neutral-800 dark:text-neutral-100">{{ $picMismatch['simas'] }}</dd>
                                        </div>
                                    </dl>
                                    <p class="text-xs text-neutral-600 dark:text-neutral-400">
                                        Kalau orang ini memang sudah pindah, lanjutkan untuk memindahkan
                                        keanggotaannya. Perpindahan dicatat di log audit.
                                    </p>
                                    <x-nawasara-ui::button color="warning" size="sm" wire:click="savePrimaryConfirmed">
                                        Pindahkan dan simpan
                                    </x-nawasara-ui::button>
                                </div>
                            @endif

                            @if ($picSimasError)
                                <div class="space-y-2 rounded-lg border border-gray-300 bg-gray-50 p-3 dark:border-neutral-600 dark:bg-neutral-800">
                                    <p class="text-sm text-neutral-800 dark:text-neutral-100">{{ $picSimasError }}</p>
                                    <p class="text-xs text-neutral-600 dark:text-neutral-400">
                                        Penetapan tetap dapat dilanjutkan, tetapi data kepegawaiannya
                                        tidak diperbarui dan penetapan ini ditandai belum terverifikasi.
                                    </p>
                                    <x-nawasara-ui::button color="neutral" size="sm" wire:click="savePrimaryConfirmed">
                                        Lanjutkan tanpa verifikasi
                                    </x-nawasara-ui::button>
                                </div>
                            @endif

                            <div class="flex justify-end gap-2">
                                <x-nawasara-ui::button color="neutral" size="sm" wire:click="closePicker">Batal</x-nawasara-ui::button>
                                <x-nawasara-ui::button color="primary" size="sm" wire:click="savePrimary">Simpan</x-nawasara-ui::button>
                            </div>
                        </div>
                    @endif

                    <p class="text-xs text-neutral-500 dark:text-neutral-400">
                        Menetapkan PIC utama ikut memeriksa SIMAS Hebat dan menyegarkan OPD orangnya.
                    </p>
                </section>

                {{-- ===== PIC cadangan ===== --}}
                <section class="space-y-2">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-neutral-800 dark:text-neutral-100">PIC cadangan</p>
                        @if ($mode !== 'backup')
                            <x-nawasara-ui::button color="primary" size="sm" wire:click="startBackup">Tambah</x-nawasara-ui::button>
                        @endif
                    </div>

                    <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
                        @forelse ($backups as $assignment)
                            <div wire:key="pic-{{ $assignment->id }}"
                                 class="flex items-center gap-3 border-b border-neutral-100 px-4 py-3 last:border-b-0 dark:border-neutral-700/60">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm text-neutral-800 dark:text-neutral-100">{{ $assignment->user?->name ?? 'Pengguna #'.$assignment->user_id }}</p>
                                    <p class="truncate text-xs text-neutral-500 dark:text-neutral-400">{{ $assignment->user?->email }}</p>
                                </div>
                                <x-nawasara-ui::badge color="neutral">{{ $roleLabels['backup'] }}</x-nawasara-ui::badge>
                                <x-nawasara-ui::button color="danger" size="sm"
                                    wire:click="removeBackup({{ $assignment->user_id }})"
                                    wire:confirm="Cabut {{ $assignment->user?->name ?? 'orang ini' }} sebagai PIC cadangan? Aksesnya ke aset ini lewat Akun ASN ikut berakhir.">
                                    Cabut
                                </x-nawasara-ui::button>
                            </div>
                        @empty
                            @if ($mode === 'backup')
                                <p class="px-4 py-4 text-center text-sm text-neutral-500 dark:text-neutral-400">Belum ada PIC cadangan.</p>
                            @else
                                <button type="button" wire:click="startBackup"
                                    class="flex w-full items-center justify-center gap-2 px-4 py-4 text-sm text-neutral-500 hover:bg-neutral-50 hover:text-neutral-800 dark:text-neutral-400 dark:hover:bg-neutral-700/40 dark:hover:text-neutral-100">
                                    <x-lucide-plus class="size-4" /> Belum ada PIC cadangan — klik untuk menambah
                                </button>
                            @endif
                        @endforelse
                    </div>

                    @if ($mode === 'backup')
                        <div class="space-y-3 rounded-lg border border-dashed border-neutral-300 p-4 dark:border-neutral-600">
                            <livewire:nawasara-registry.pic-picker event="backup-pic-selected" :key="'backup-picker-'.$assetId" />
                            <div class="flex justify-end gap-2">
                                <x-nawasara-ui::button color="neutral" size="sm" wire:click="closePicker">Batal</x-nawasara-ui::button>
                                <x-nawasara-ui::button color="primary" size="sm" wire:click="addBackup" :disabled="! $pickedUserId">
                                    Tambahkan sebagai Cadangan
                                </x-nawasara-ui::button>
                            </div>
                        </div>
                    @endif

                    <p class="text-xs text-neutral-500 dark:text-neutral-400">
                        PIC cadangan boleh dari OPD lain dan tidak memindahkan OPD-nya.
                    </p>
                </section>
            </div>
        @endif

        <x-slot name="footer">
            <x-nawasara-ui::button color="neutral" x-on:click="$dispatch('close-modal', 'registry-asset-pics')">
                Tutup
            </x-nawasara-ui::button>
        </x-slot>
    </x-nawasara-ui::modal>
</div>
