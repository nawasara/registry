<?php

namespace Nawasara\Registry\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;
use Nawasara\Registry\Exceptions\SimasHebatException;
use Nawasara\Registry\Models\Membership;
use Nawasara\Registry\Models\Opd;
use Nawasara\Registry\Services\PicAssignmentService;
use Nawasara\Keycloak\Support\KeycloakProfile;

/**
 * The OPD + PIC pair that every screen embeds, plus the NIP lookup behind it.
 *
 * Four packages carried their own copy of `pjCandidates()`, identical apart
 * from whitespace, and each used different property names for the same two
 * values. This component replaces all four, and talks back over an event so
 * the host keeps its own names and no package has to change at the same time
 * as another.
 *
 *   <livewire:nawasara-registry.pic-picker :opd-id="$formOpdId" :pj-user-id="$formPjUserId" />
 *
 *   #[On('pic-selected')]
 *   public function onPicSelected(array $p): void
 *   {
 *       $this->formOpdId    = $p['opd_id'];
 *       $this->formPjUserId = $p['pj_user_id'];
 *   }
 *
 * ⚠️ Selecting here does NOT write anything. The assignment itself runs
 * through PicAssignmentService when the host saves, because every screen ends
 * at the same Asset row and a check living in Blade would be reached around by
 * seeders, sync jobs and any package that has not heard of the rule.
 */
class PicPicker extends Component
{
    public ?int $opdId = null;

    public ?int $pjUserId = null;

    /** NIP typed into the lookup box. */
    public string $nip = '';

    /** Result of the last lookup, or null when nothing has been looked up. */
    public ?array $found = null;

    public string $lookupError = '';

    public bool $searching = false;

    public function mount(?int $opdId = null, ?int $pjUserId = null): void
    {
        $this->opdId = $opdId;
        $this->pjUserId = $pjUserId;
    }

    /** OPDs for the first select. */
    public function getOpdOptionsProperty(): array
    {
        return Opd::orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * Members of the chosen OPD, the people who can be picked without a lookup.
     */
    public function getPjCandidatesProperty(): array
    {
        if (! $this->opdId) {
            return [];
        }

        $candidates = Membership::where('opd_id', $this->opdId)
            ->where('aktif', true)
            ->with('user')
            ->get()
            ->filter(fn ($m) => $m->user !== null)
            ->mapWithKeys(fn ($m) => [$m->user_id => KeycloakProfile::for($m->user)->name])
            ->all();

        asort($candidates);

        return $candidates;
    }

    public function updatedOpdId(): void
    {
        // The previous pick belongs to the previous OPD.
        $this->pjUserId = null;
        $this->emitSelection();
    }

    public function updatedPjUserId(): void
    {
        $this->emitSelection();
    }

    /**
     * Look the typed NIP up in SIMAS Hebat.
     *
     * Button-triggered rather than bound live: one keystroke per call would
     * hammer a source whose firewall blocks the whole IP after a few requests,
     * and a block is total, so the next genuine lookup would fail too.
     */
    public function lookup(): void
    {
        $this->found = null;
        $this->lookupError = '';

        $nip = trim($this->nip);

        if (! preg_match('/^\d{18}$/', $nip)) {
            $this->lookupError = 'NIP terdiri dari 18 angka.';

            return;
        }

        try {
            $this->found = app(PicAssignmentService::class)->lookup($nip);
        } catch (SimasHebatException $e) {
            $this->lookupError = $e->getMessage();
        }
    }

    public function clearLookup(): void
    {
        $this->nip = '';
        $this->found = null;
        $this->lookupError = '';
    }

    #[On('pic-picker-reset')]
    public function resetPicker(): void
    {
        $this->opdId = null;
        $this->pjUserId = null;
        $this->clearLookup();
    }

    protected function emitSelection(): void
    {
        $this->dispatch('pic-selected', [
            'opd_id' => $this->opdId,
            'pj_user_id' => $this->pjUserId,
        ]);
    }

    public function render()
    {
        return view('nawasara-registry::livewire.pic-picker');
    }
}
