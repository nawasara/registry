<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Registry', 'url' => '#'], ['label' => 'OPD / Instansi']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page.title>OPD / Instansi</x-nawasara-ui::page.title>

        <x-slot name="actions">
            <x-nawasara-ui::page.actions>
                @can('registry.opd.manage')
                    <a href="{{ route('nawasara-registry.opd.create') }}" wire:navigate>
                        <x-nawasara-ui::button color="success">
                            <x-slot:icon><x-lucide-plus class="size-4" /></x-slot:icon>
                            Tambah OPD
                        </x-nawasara-ui::button>
                    </a>
                @endcan
            </x-nawasara-ui::page.actions>
        </x-slot>

        @livewire('nawasara-registry.opd.section.table')
    </x-nawasara-ui::page.container>
</div>
