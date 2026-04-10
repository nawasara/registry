<?php

namespace Nawasara\Registry\Livewire\Asset;

use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        return view('nawasara-registry::livewire.pages.asset.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
