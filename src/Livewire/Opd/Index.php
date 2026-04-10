<?php

namespace Nawasara\Registry\Livewire\Opd;

use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        return view('nawasara-registry::livewire.pages.opd.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
