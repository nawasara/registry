<?php

namespace Nawasara\Registry\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;
use Nawasara\Registry\Exceptions\SimasHebatException;
use Nawasara\Registry\Models\Membership;
use Nawasara\Registry\Models\Opd;
use Nawasara\Registry\Services\PicAssignmentService;
use Nawasara\Keycloak\Models\KeycloakUser;
use Nawasara\Keycloak\Support\KeycloakProfile;
use Nawasara\Keycloak\Support\KeycloakUserProvisioner;

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

    /**
     * Whether the person just looked up can actually be appointed, and if not,
     * why. Shown instead of the appoint button.
     *
     * 'siap'        — has a Nawasara account, or can be given one from the
     *                 Keycloak directory
     * 'belum_sso'   — not in Keycloak at all, so there is nothing to link to
     */
    public string $foundState = '';

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
        $this->foundState = '';
        $this->lookupError = '';

        $nip = trim($this->nip);

        if (! preg_match('/^\d{18}$/', $nip)) {
            $this->lookupError = 'NIP terdiri dari 18 angka.';

            return;
        }

        try {
            $this->found = app(PicAssignmentService::class)->lookup($nip);
            $this->foundState = $this->resolveAccount($nip) ? 'siap' : 'belum_sso';
        } catch (SimasHebatException $e) {
            $this->lookupError = $e->getMessage();
        }
    }

    /**
     * Appoint the person just looked up.
     *
     * Without this the screen was a dead end: it showed the right name, the
     * right jabatan and the matched OPD, then offered nothing to act on, so
     * saving silently left the asset with no PIC at all. Nothing errored, and
     * the gap only surfaced later when somebody noticed the person was not
     * actually in charge.
     */
    public function selectFound(): void
    {
        if (! $this->found) {
            return;
        }

        $user = $this->resolveAccount(trim($this->nip));

        if (! $user) {
            $this->lookupError = 'Pegawai ini belum pernah masuk lewat SSO, '
                .'jadi belum punya akun yang dapat ditautkan.';

            return;
        }

        // The OPD SIMAS recognised, when it recognised one. An unknown OPD
        // leaves this alone: creating it is its own step, and it needs the
        // reply as its basis (see PicAssignmentService::createOpdFromSimas).
        if ($this->found['opd'] ?? null) {
            $this->opdId = $this->found['opd']->id;
        }

        $this->pjUserId = $user->getAuthIdentifier();
        $this->emitSelection();

        $this->clearLookup();
    }

    /**
     * The Nawasara account for this NIP, creating it from the Keycloak
     * directory when the person has logged in via SSO but never been given a
     * local row.
     *
     * Matching is by USERNAME: Keycloak's username is the NIP on every
     * production account, which makes it the one field always present. The
     * `nip` attribute exists on most but not all of them, so it is the
     * cross-check rather than the source.
     */
    protected function resolveAccount(string $nip): ?object
    {
        $userModel = config('auth.providers.users.model');

        $user = $userModel::where('username', $nip)->first();

        if ($user) {
            return $user;
        }

        $kc = KeycloakUser::where('username', $nip)->first();

        if (! $kc) {
            return null;
        }

        return app(KeycloakUserProvisioner::class)->fromSnapshot($kc);
    }

    public function clearLookup(): void
    {
        $this->nip = '';
        $this->found = null;
        $this->foundState = '';
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
