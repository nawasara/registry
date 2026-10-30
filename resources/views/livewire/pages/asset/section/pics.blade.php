<div>
    <x-nawasara-ui::modal id="registry-asset-pics" title="Kelola PIC">
        @if ($this->asset)
            <div class="space-y-5">
                <div>
                    <p class="font-mono text-sm text-neutral-800 dark:text-neutral-100">{{ $this->asset->identifier }}</p>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">
                        {{ $this->asset->type_label }} · {{ $this->asset->opd?->name ?? 'Belum ada OPD' }}
                    </p>
                </div>

                <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
                    @forelse ($this->asset->assignments->sortBy(fn ($a) => $a->role === 'primary' ? 0 : 1) as $assignment)
                        <div wire:key="pic-{{ $assignment->id }}"
                             class="flex items-center gap-3 border-b border-neutral-100 px-4 py-3 last:border-b-0 dark:border-neutral-700/60">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm text-neutral-800 dark:text-neutral-100">{{ $assignment->user?->name ?? 'Pengguna #'.$assignment->user_id }}</p>
                                <p class="truncate text-xs text-neutral-500 dark:text-neutral-400">{{ $assignment->user?->email }}</p>
                            </div>
                            <x-nawasara-ui::badge :color="$assignment->role === 'primary' ? 'info' : 'neutral'">
                                {{ $roleLabels[$assignment->role] ?? $assignment->role }}
                            </x-nawasara-ui::badge>
                            @if ($assignment->role === 'backup')
                                <x-nawasara-ui::button color="danger" size="sm"
                                    wire:click="removeBackup({{ $assignment->user_id }})"
                                    wire:confirm="Cabut {{ $assignment->user?->name ?? 'orang ini' }} sebagai PIC cadangan? Aksesnya ke aset ini lewat Akun ASN ikut berakhir.">
                                    Cabut
                                </x-nawasara-ui::button>
                            @endif
                        </div>
                    @empty
                        <p class="px-4 py-6 text-center text-sm text-neutral-500 dark:text-neutral-400">
                            Belum ada PIC. Tetapkan PIC utama lewat Edit aset.
                        </p>
                    @endforelse
                </div>

                <p class="text-xs text-neutral-500 dark:text-neutral-400">
                    PIC utama diubah lewat Edit aset, karena penetapannya ikut menyegarkan data kepegawaian dan OPD orangnya.
                    PIC cadangan boleh dari OPD lain dan tidak memindahkan OPD-nya.
                </p>

                <div class="rounded-lg border border-dashed border-neutral-300 p-4 dark:border-neutral-600">
                    <p class="mb-3 text-sm font-medium text-neutral-800 dark:text-neutral-100">Tambah PIC cadangan</p>
                    <livewire:nawasara-registry.pic-picker event="backup-pic-selected" :key="'backup-picker-'.$assetId" />
                </div>
            </div>
        @endif

        <x-slot name="footer">
            <x-nawasara-ui::button color="neutral" x-on:click="$dispatch('close-modal', 'registry-asset-pics')">
                Tutup
            </x-nawasara-ui::button>
            {{-- wire:click, not submit: the modal renders the footer outside
                 its content div. --}}
            <x-nawasara-ui::button color="primary" wire:click="addBackup" :disabled="! $pickedUserId">
                Tambahkan sebagai Cadangan
            </x-nawasara-ui::button>
        </x-slot>
    </x-nawasara-ui::modal>
</div>
