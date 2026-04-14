<?php

namespace Nawasara\Registry\Livewire\Opd;

use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Nawasara\Registry\Models\Opd;
use Nawasara\Registry\Models\Pic;

class Form extends Component
{
    public ?int $opdId = null;

    // OPD fields
    public string $code = '';
    public string $name = '';
    public string $address = '';
    public string $phone = '';
    public string $email = '';

    // PIC list (managed inline)
    public array $pics = [];

    public function mount($id = null)
    {
        Gate::authorize('registry.opd.manage');

        if ($id) {
            $opd = Opd::with('pics')->findOrFail($id);
            $this->opdId = $opd->id;
            $this->code = $opd->code;
            $this->name = $opd->name;
            $this->address = $opd->address ?? '';
            $this->phone = $opd->phone ?? '';
            $this->email = $opd->email ?? '';

            $this->pics = $opd->pics->map(fn ($pic) => [
                'id' => $pic->id,
                'name' => $pic->name,
                'position' => $pic->position ?? '',
                'phone' => $pic->phone ?? '',
                'email' => $pic->email ?? '',
                'is_primary' => $pic->is_primary,
            ])->toArray();
        }
    }

    public function addPic()
    {
        $this->pics[] = [
            'id' => null,
            'name' => '',
            'position' => '',
            'phone' => '',
            'email' => '',
            'is_primary' => empty($this->pics), // first PIC is primary by default
        ];
    }

    public function removePic($index)
    {
        unset($this->pics[$index]);
        $this->pics = array_values($this->pics);
    }

    public function setPrimary($index)
    {
        foreach ($this->pics as $i => $pic) {
            $this->pics[$i]['is_primary'] = ($i === $index);
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
            'pics.*.name' => 'required|max:255',
            'pics.*.position' => 'nullable|max:255',
            'pics.*.phone' => 'nullable|max:20',
            'pics.*.email' => 'nullable|email|max:255',
        ];
    }

    protected function messages()
    {
        return [
            'pics.*.name.required' => 'Nama PIC wajib diisi',
            'pics.*.email.email' => 'Format email PIC tidak valid',
        ];
    }

    public function save()
    {
        Gate::authorize('registry.opd.manage');

        $this->validate();

        $opd = Opd::updateOrCreate(
            ['id' => $this->opdId],
            [
                'code' => $this->code,
                'name' => $this->name,
                'address' => $this->address ?: null,
                'phone' => $this->phone ?: null,
                'email' => $this->email ?: null,
            ]
        );

        // Sync PICs
        $existingPicIds = $opd->pics()->pluck('id')->toArray();
        $submittedPicIds = collect($this->pics)->pluck('id')->filter()->toArray();
        $deletedPicIds = array_diff($existingPicIds, $submittedPicIds);

        if (! empty($deletedPicIds)) {
            Pic::whereIn('id', $deletedPicIds)->delete();
        }

        foreach ($this->pics as $picData) {
            Pic::updateOrCreate(
                ['id' => $picData['id']],
                [
                    'opd_id' => $opd->id,
                    'name' => $picData['name'],
                    'position' => $picData['position'] ?: null,
                    'phone' => $picData['phone'] ?: null,
                    'email' => $picData['email'] ?: null,
                    'is_primary' => $picData['is_primary'] ?? false,
                ]
            );
        }

        toaster_success($this->opdId ? 'OPD berhasil diperbarui' : 'OPD berhasil ditambahkan');

        return redirect()->route('nawasara-registry.opd.index');
    }

    public function render()
    {
        return view('nawasara-registry::livewire.pages.opd.form')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
