<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Registry Aset', 'url' => '#'], ['label' => 'Pengajuan Petugas']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <livewire:nawasara-registry.membership-request.section.table />
    </x-nawasara-ui::page.container>
</div>
