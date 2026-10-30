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

        {{-- Backup PICs. Its own component so the second PicPicker inside
             reports on its own event and never fills the asset form. --}}
        @livewire('nawasara-registry.asset.section.pics')
    </x-nawasara-ui::page.container>
</div>
