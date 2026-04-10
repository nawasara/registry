<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Registry', 'url' => '#'], ['label' => 'Aset Digital']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page.title>Aset Digital</x-nawasara-ui::page.title>

        @livewire('nawasara-registry.asset.section.table')
    </x-nawasara-ui::page.container>
</div>
