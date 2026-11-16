<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Registry Aset', 'url' => '#'], ['label' => 'Data Pegawai']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <livewire:nawasara-registry.employee.section.table />
        <livewire:nawasara-registry.employee.section.detail />
    </x-nawasara-ui::page.container>
</div>
