<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Registry', 'url' => '#'], ['label' => 'Aset Digital']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page.title>Aset Digital</x-nawasara-ui::page.title>

        <x-slot name="actions">
            <x-nawasara-ui::page.actions>
                <x-nawasara-ui::button wire:click="$dispatch('openCreateAsset')" color="success">
                    <x-slot:icon><x-lucide-plus class="size-4" /></x-slot:icon>
                    Tambah Aset
                </x-nawasara-ui::button>
            </x-nawasara-ui::page.actions>
        </x-slot>

        @livewire('nawasara-registry.asset.section.table')
    </x-nawasara-ui::page.container>
</div>
