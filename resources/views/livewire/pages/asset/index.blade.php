<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Registry', 'url' => '#'], ['label' => 'Aset Digital']]" />
    </x-slot>

    {{-- Title + create button hoisted into the section component (which
         owns the asset form modal + reactive filter state). Index is
         just a shell. --}}
    <x-nawasara-ui::page.container>
        @livewire('nawasara-registry.asset.section.table')
    </x-nawasara-ui::page.container>
</div>
