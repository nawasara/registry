<?php

namespace Nawasara\Registry\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;
use Nawasara\Registry\Exceptions\SimasHebatException;
use Nawasara\Registry\Models\Membership;
use Nawasara\Registry\Models\Opd;
use Nawasara\Registry\Services\PicAssignmentService;
use Nawasara\Registry\Services\UnitKerjaMatcher;
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

    /** Orangnya sudah punya akun, atau ada di direktori Keycloak. */
    public const ACCOUNT_LINKED = 'linked';

    /** Belum ada di mana pun; akunnya dibuat dari data SIMAS saat ditetapkan. */
    public const ACCOUNT_WILL_CREATE = 'will_create';

    /**
     * Keadaan akun orang yang baru dicari, salah satu ACCOUNT_* di atas.
     *
     * Keduanya dapat ditetapkan. Bedanya dikatakan di layar, bukan dipakai
     * untuk memblokir: yang kedua membuat akun baru, dan admin pantas tahu
     * sebelum menekan tombolnya.
     *
     * Tetap konstanta meski nilainya cuma dibandingkan di dua tempat: string
     * telanjang yang salah ketik tidak menghasilkan galat apa pun, cabangnya
     * hanya diam tidak pernah jalan.
     */
    public string $foundState = '';

    public bool $searching = false;

    /*
     * Form pembuatan OPD / unit, terbuka hanya setelah pencarian berhasil.
     *
     * Isinya dapat dikoreksi admin, dan itu disengaja: pencocokan awalan bisa
     * salah menebak batas antara nama OPD dan nama bidangnya. Tanpa jalan
     * mengoreksi, tebakan yang keliru menjadi permanen.
     *
     * ⚠️ Yang dikoreksi hanya yang DITAMPILKAN. Potongan mentah dari SIMAS
     * tetap disimpan apa adanya sebagai kunci pencocokan (lihat
     * PicAssignmentService::createOpdFromSimas).
     */
    public bool $creatingOpd = false;

    public string $newOpdCode = '';

    /** Nama yang akan dibaca manusia. Boleh dirapikan. */
    public string $newOpdName = '';

    /** Potongan `unit_kerja` yang menjadi nama OPD, dipakai untuk mencocokkan. */
    public string $newOpdSimasName = '';

    /** Sisa `unit_kerja` setelah nama OPD dipotong, yaitu nama bidangnya. */
    public string $newUnitSimasName = '';

    public string $newUnitName = '';

    /** OPD induk, untuk sekolah dan puskesmas yang bernaung di bawah dinas. */
    public ?int $newOpdParentId = null;

    public string $createError = '';

    /** Konfirmasi setelah OPD/bidang tersimpan tanpa penetapan PIC. */
    public string $createdNotice = '';

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
            // Tanpa `create: true`: pencarian tidak boleh menulis apa pun.
            $this->foundState = $this->resolveAccount($nip)
                ? self::ACCOUNT_LINKED
                : self::ACCOUNT_WILL_CREATE;
        } catch (SimasHebatException $e) {
            $this->lookupError = $e->getMessage();
        }
    }

    /**
     * Apakah akunnya akan dibuat saat ditetapkan nanti.
     *
     * Disediakan supaya blade tidak perlu menulis nama kelas penuh untuk
     * membaca konstantanya, dan tidak jatuh kembali ke string telanjang.
     */
    public function getWillCreateAccountProperty(): bool
    {
        return $this->foundState === self::ACCOUNT_WILL_CREATE;
    }

    /**
     * Whether the bidang SIMAS named already exists under the matched OPD.
     *
     * Shown so the gap is visible: without it the screen looked complete while
     * `unit_id` quietly stayed empty, since the name matched but nothing had
     * ever created the row.
     */
    public function getUnitMissingProperty(): bool
    {
        $opd = $this->found['opd'] ?? null;
        $sub = trim((string) ($this->found['sub_unit'] ?? ''));

        if (! $opd || $sub === '') {
            return false;
        }

        return app(PicAssignmentService::class)->findUnit($opd, $sub) === null;
    }

    /**
     * Open the create form, pre-filled with the system's best guess.
     *
     * Everything in it is editable. Prefix matching decides where the OPD name
     * ends and the sub-unit begins, and it can put that boundary in the wrong
     * place: `DINAS PENDIDIKAN SD NEGERI 1 TEGALREJO` is unambiguous only
     * because we happen to know `DINAS PENDIDIKAN`. For an OPD nobody has
     * registered yet there is no known prefix at all, so the guess is the
     * whole string and the admin is the one who can see it is wrong.
     */
    public function startCreateOpd(): void
    {
        $this->review();
    }

    public function startCreateUnit(): void
    {
        $this->review();
    }

    /**
     * Open the confirmation step. ALWAYS, even when everything was recognised.
     *
     * This is the last point before anything reaches the database, and it
     * exists because the matcher can be confidently wrong. SIMAS returns the
     * OPD name and the bidang name as one string with no separator, so where
     * one ends and the other begins is inferred, and a longest-prefix match
     * that lands on the wrong boundary produces a result that looks perfectly
     * ordinary on screen.
     *
     * Showing this only for unrecognised rows would skip review exactly where
     * a wrong match is most dangerous: the cases the system feels sure about,
     * which nobody would then check.
     *
     * Nothing here is written yet. Writing happens in saveCreate().
     */
    public function review(): void
    {
        if (! $this->found) {
            return;
        }

        $this->createError = '';
        $this->createdNotice = '';
        $this->creatingOpd = true;

        $unitKerja = trim((string) ($this->found['unit_kerja'] ?? ''));
        $opd = $this->found['opd'] ?? null;

        if ($opd) {
            // Recognised. The boxes show what was matched so the admin can see
            // the split, and the OPD fields stay empty because it exists.
            $this->newOpdSimasName = '';
            $this->newOpdName = '';
            $this->newOpdCode = '';
            $this->newOpdParentId = null;

            $sub = trim((string) ($this->found['sub_unit'] ?? ''));
            $this->newUnitSimasName = $sub;
            $this->newUnitName = $this->existingUnitName($opd, $sub) ?: $sub;

            return;
        }

        // Nothing recognised, so there is no known prefix to split on. Offering
        // the whole string as the OPD name and asking the admin to cut it left
        // KECAMATAN BALONG registered with simas_name "KECAMATAN BALONG SEKSI
        // PELAYANAN UMUM - KECAMATAN BALONG", which then matched nobody else
        // from that kecamatan, silently.
        //
        // So the split is guessed here, from the words that introduce a
        // sub-unit. The guess is visible and editable; what it prevents is the
        // whole string being kept by default.
        [$opdPart, $subPart] = $this->guessSplit($unitKerja);

        $this->newOpdSimasName = $opdPart;
        $this->newOpdName = $opdPart;
        $this->newUnitSimasName = $subPart;
        $this->newUnitName = $subPart;
        $this->newOpdParentId = null;
        $this->newOpdCode = $this->suggestCode($opdPart);
    }

    /**
     * Split `unit_kerja` at the first word that introduces a sub-unit.
     *
     * SIMAS joins the two names with nothing but a space, but the sub-unit
     * almost always opens with one of a small set of words. Cutting there is a
     * guess, not a rule, which is why the result lands in editable boxes.
     *
     * @return array{0: string, 1: string} [nama OPD, nama sub-unit]
     */
    protected function guessSplit(string $unitKerja): array
    {
        $unitKerja = trim($unitKerja);

        // Beberapa jawaban mengulang nama OPD di belakang, dipisah tanda
        // hubung: "KECAMATAN BALONG SEKSI ... - KECAMATAN BALONG".
        $unitKerja = trim((string) preg_split('/\s+-\s+/', $unitKerja)[0]);

        $penanda = ['BIDANG', 'SEKSI', 'SEKRETARIAT', 'SUB BAGIAN', 'SUBBAGIAN',
            'BAGIAN', 'UPT', 'UPTD', 'PUSKESMAS', 'SD ', 'SMP ', 'SMA ', 'SMK ',
            'TK ', 'KELURAHAN', 'DESA'];

        $atas = mb_strtoupper($unitKerja);

        $potong = null;
        foreach ($penanda as $kata) {
            $pos = mb_strpos($atas, $kata);

            // Awalan 0 berarti seluruh teksnya memang sub-unit, bukan OPD.
            if ($pos !== false && $pos > 0 && ($potong === null || $pos < $potong)) {
                $potong = $pos;
            }
        }

        if ($potong === null) {
            return [$unitKerja, ''];
        }

        return [
            trim(mb_substr($unitKerja, 0, $potong)),
            trim(mb_substr($unitKerja, $potong)),
        ];
    }

    /**
     * Apakah nama OPD yang akan disimpan masih memuat nama bidangnya.
     *
     * Dipakai untuk memperingatkan di layar. Tanpa ini kesalahannya tidak
     * terlihat sama sekali sampai berbulan-bulan kemudian, saat pegawai lain
     * dari OPD yang sama tidak dikenali dan tidak ada yang tahu sebabnya.
     */
    public function getOpdNameLooksUnsplitProperty(): bool
    {
        $nama = trim($this->newOpdSimasName);

        if ($nama === '' || ($this->found['opd'] ?? null)) {
            return false;
        }

        [, $sisa] = $this->guessSplit($nama);

        return $sisa !== '';
    }

    /**
     * Treat a recognised OPD as wrong and register a different one instead.
     *
     * The matcher takes the longest known prefix, which can claim a reply that
     * belongs to an OPD nobody has registered yet: `DINAS PENDIDIKAN` matches
     * the opening of `DINAS PENDIDIKAN DAN KEBUDAYAAN ...` too. Without this,
     * an admin who can see the match is wrong could only cancel.
     */
    public function forceCreateOpd(): void
    {
        if (! $this->found) {
            return;
        }

        $unitKerja = trim((string) ($this->found['unit_kerja'] ?? ''));

        $this->found['opd'] = null;
        $this->newOpdSimasName = $unitKerja;
        $this->newOpdName = $unitKerja;
        $this->newOpdCode = $this->suggestCode($unitKerja);
        $this->newUnitSimasName = '';
        $this->newUnitName = '';
        $this->newOpdParentId = null;
    }

    /** Display name of a bidang that already exists, else null. */
    protected function existingUnitName(Opd $opd, string $subUnit): ?string
    {
        if ($subUnit === '') {
            return null;
        }

        return app(PicAssignmentService::class)->findUnit($opd, $subUnit)?->name;
    }

    public function cancelCreate(): void
    {
        $this->creatingOpd = false;
        $this->createError = '';
        $this->createdNotice = '';
    }

    /**
     * Create the OPD and/or the sub-unit, then appoint the person.
     *
     * One button rather than three steps: the admin came here to put somebody
     * in charge, and the missing rows are in the way of that, not a separate
     * errand.
     */
    public function saveCreate(): void
    {
        if (! $this->found) {
            return;
        }

        $this->createError = '';

        $svc = app(PicAssignmentService::class);
        $opd = $this->found['opd'] ?? null;

        try {
            if (! $opd) {
                $code = strtoupper(trim($this->newOpdCode));
                $simasName = trim($this->newOpdSimasName);

                if ($code === '' || $simasName === '') {
                    $this->createError = 'Kode dan nama OPD wajib diisi.';

                    return;
                }

                if (Opd::where('code', $code)->exists()) {
                    $this->createError = "Kode {$code} sudah dipakai OPD lain.";

                    return;
                }

                $opd = $svc->createOpdFromSimas(
                    simasData: $this->found,
                    code: $code,
                    parentId: $this->newOpdParentId,
                    name: $this->newOpdName,
                    simasName: $simasName,
                );
            }

            if (trim($this->newUnitSimasName) !== '') {
                $svc->createUnitFromSimas(
                    opd: $opd,
                    simasData: $this->found,
                    subUnit: trim($this->newUnitSimasName),
                    name: $this->newUnitName,
                );
            }
        } catch (\Throwable $e) {
            $this->createError = $e->getMessage();

            return;
        }

        // The OPD now exists, so re-matching finds it and the picker can carry
        // on as though it had been registered all along.
        $this->found['opd'] = $opd;
        $this->found['sub_unit'] = trim($this->newUnitSimasName) ?: ($this->found['sub_unit'] ?? null);

        $this->creatingOpd = false;

        $this->selectFound();
    }

    /**
     * A starting point for the OPD code, which the admin then edits.
     *
     * Codes in production are human abbreviations (DISKOMINFO, BAKESRA) and
     * SIMAS returns nothing like them, so this only saves typing; it is not
     * trying to be right.
     */
    protected function suggestCode(string $unitKerja): string
    {
        $kata = preg_split('/\s+/', UnitKerjaMatcher::normalize($unitKerja)) ?: [];

        // Buang kata sambung yang tidak menyumbang apa pun ke singkatan.
        $kata = array_values(array_filter(
            $kata,
            fn ($k) => ! in_array($k, ['DAN', 'DI', 'KE', 'DARI', 'KABUPATEN', 'PONOROGO'], true)
        ));

        $singkatan = '';
        foreach (array_slice($kata, 0, 4) as $k) {
            $singkatan .= mb_substr($k, 0, min(4, mb_strlen($k)));
        }

        return mb_substr($singkatan, 0, 20);
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

        // `create: true` — akunnya dibuat di sini bila belum ada. Lihat
        // resolveAccount() untuk alasan mengapa ini tidak melahirkan akun ganda.
        $user = $this->resolveAccount(trim($this->nip), create: true);

        if (! $user) {
            $this->lookupError = 'Akun untuk pegawai ini tidak dapat dibuat.';

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
    protected function resolveAccount(string $nip, bool $create = false): ?object
    {
        $userModel = config('auth.providers.users.model');

        $user = $userModel::where('username', $nip)->first();

        if ($user) {
            return $user;
        }

        $kc = KeycloakUser::where('username', $nip)->first();

        if ($kc) {
            return app(KeycloakUserProvisioner::class)->fromSnapshot($kc);
        }

        if (! $create) {
            return null;
        }

        // Tidak ada di Keycloak, tetapi SIMAS Hebat mengenalinya. Akunnya
        // dibuat dari identitas itu, dengan username = NIP.
        //
        // Aman dari akun ganda justru KARENA username-nya NIP: saat orangnya
        // benar-benar login, penautan mencari lewat keycloak_id lalu username,
        // menemukan baris ini, dan mengadopsinya.
        return app(KeycloakUserProvisioner::class)->fromExternalIdentity(
            username: $nip,
            name: (string) ($this->found['nama_lengkap'] ?? $nip),
        );
    }

    public function clearLookup(): void
    {
        $this->nip = '';
        $this->found = null;
        $this->foundState = '';
        $this->lookupError = '';
        $this->creatingOpd = false;
        $this->createError = '';
        $this->createdNotice = '';
        $this->newOpdCode = '';
        $this->newOpdName = '';
        $this->newOpdSimasName = '';
        $this->newUnitName = '';
        $this->newUnitSimasName = '';
        $this->newOpdParentId = null;
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
