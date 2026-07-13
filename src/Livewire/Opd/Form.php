<?php

namespace Nawasara\Registry\Livewire\Opd;

use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Nawasara\Registry\Models\Opd;
use Nawasara\Ui\Livewire\Concerns\HasBrowserToast;

class Form extends Component
{
    use HasBrowserToast;

    public ?int $opdId = null;

    public string $code = '';
    public string $name = '';
    public string $address = '';
    public string $phone = '';
    public string $email = '';

    public function mount($id = null)
    {
        Gate::authorize('registry.opd.manage');

        if ($id) {
            $opd = Opd::findOrFail($id);
            $this->opdId = $opd->id;
            $this->code = $opd->code;
            $this->name = $opd->name;
            $this->address = $opd->address ?? '';
            $this->phone = $opd->phone ?? '';
            $this->email = $opd->email ?? '';
        }
    }

    protected function rules()
    {
        return [
            'code' => ['required', 'max:50', Rule::unique('nawasara_registry_opd', 'code')->ignore($this->opdId)],
            'name' => 'required|max:255',
            'address' => 'nullable|max:500',
            'phone' => 'nullable|max:20',
            'email' => 'nullable|email|max:255',
        ];
    }

    public function save()
    {
        Gate::authorize('registry.opd.manage');

        $this->validate();

        Opd::updateOrCreate(
            ['id' => $this->opdId],
            [
                'code' => $this->code,
                'name' => $this->name,
                'address' => $this->address ?: null,
                'phone' => $this->phone ?: null,
                'email' => $this->email ?: null,
            ]
        );

        $this->toastSuccess($this->opdId ? 'OPD berhasil diperbarui' : 'OPD berhasil ditambahkan');

        return redirect()->route('nawasara-registry.opd.index');
    }

    public function render()
    {
        return view('nawasara-registry::livewire.pages.opd.form')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
