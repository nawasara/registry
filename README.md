# Nawasara Registry

Master data for the Nawasara superapp framework: organizational units (OPD), persons in charge (PIC), and a generic asset ownership index that other packages link into.

## Features

- **OPD**: code, name, address, phone, email, plus `parent_id` for institutions that sit under another OPD
- **Unit**: divisions inside an OPD ("BIDANG APLIKASI DAN INFORMATIKA")
- **Employee**: employment data per user, sourced from SIMAS Hebat and refreshed at login (see "Employment data follows SIMAS Hebat")
- **Membership**: which user belongs to which OPD, the relation other packages scope against
- **Asset**: a generic ownership record keyed by `(package_ref, external_id)`. Other packages (Cloudflare DNS, WHM Account, Email account) write here with their canonical IDs so the dashboard can render an "OPD / PIC" column on every resource list and a single OPD detail page can show every asset they own
- **Activity log**: every write is captured via `spatie/laravel-activitylog`
- **Admin pages**: Livewire CRUD for OPD, Membership, and Asset with search, filter, and detail modals

## Appointing a PIC: why it calls an external system

Appointing someone is not a field write here. It goes through
`PicAssignmentService`, which first asks SIMAS Hebat where that person
currently works.

The reason is that **nothing tells Nawasara about a transfer**. A membership is
recorded once and then sits there forever. Somebody who moved from Kominfo to
Dinas Pendidikan last month still carries Kominfo's `opd_id`, and since
`ScopedToOpd` filters rows by exactly that column, appointing them to a new
asset would hand them an OPD's data they no longer belong to. That is a leak,
not a stale label.

So one assignment does three things:

1. refreshes the person's employment data (`nama_jabatan`, `jenis_jabatan`, `jenis_kepegawaian`, `simas_skpd`, `simas_unit`)
2. creates the OPD and sub-unit when the registry does not have them yet
3. records the membership and writes the asset

Three decisions in there are easy to reverse without knowing why they were
made:

**SIMAS wins a mismatch, without asking.** Until v0.2.x a mismatch was held
(`PicMismatchException`) for an admin to confirm. Since 16 November 2026 SIMAS
Hebat is the official source of where an ASN works, so the membership simply
follows it, the asset takes that OPD, and the screen says what moved. The
move is written to the activity log ("Keanggotaan OPD dipindah mengikuti SIMAS
Hebat") with the old OPD, the new one and the raw `skpd`.

The worry behind the old rule was a matcher that slips. That is now handled at
the matcher instead: `skpd` must equal an OPD's name **whole**
(`UnitKerjaMatcher::matchSimas`), so "DINAS PENDIDIKAN" can no longer claim
someone from "DINAS PENDIDIKAN DAN KEBUDAYAAN". Do not bring back a prefix
match on `skpd`.

**An OPD cannot be created without a SIMAS reply, and the reply is stored in
the audit entry.** An OPD is a permission container. One born from an admin's
memory, with only "admin X created OPD Y" recorded, cannot be judged right or
wrong by anyone six months later.

When SIMAS is unreachable, assigning to an OPD that already exists still goes
through on previously verified data, with `simas_synced_at` left untouched and
the audit entry marked unverified. Creating a new OPD does not: without a reply
there is no basis for it.

### `unit_kerja` is matched by prefix, not split

> Since 12 November 2026 SIMAS sends `skpd` and `unit` as separate fields and
> no longer sends `unit_kerja`. `SimasHebatClient::parse()` reads the new shape
> and rebuilds `unit_kerja` for old audit entries; `matchSimas()` matches
> `skpd` whole. The prefix match below only runs for replies without `skpd`.
> Before v0.3.0 the client still read `unit_kerja`, so every NIP lookup came
> back with no OPD.

SIMAS returns `<OPD NAME><space><SUB-UNIT>` with no separator, and OPD names
themselves contain commas and the word "DAN":

```
DINAS KOMUNIKASI, INFORMATIKA DAN STATISTIK BIDANG APLIKASI DAN INFORMATIKA
```

Splitting that on a comma, or hunting for the word "BIDANG", is wrong on some
rows. `UnitKerjaMatcher` asks the opposite question instead: does this text
start with an OPD we already know? The longest matching prefix wins and the
remainder is the sub-unit.

An unrecognised prefix returns nothing rather than the nearest guess.
`SEKRETARIAT DAERAH` is genuinely missing from the registry, and a near-miss
there would give someone another OPD's data.

### NIP is read-only on that screen

The NIP links a person to their Keycloak account, where the username **is** the
NIP on all 10,430 production users. A mistyped digit cuts an account loose from
its owner, and `nip` is unique, so pasting someone else's turns into an error
rather than two accounts sharing an identity. A NIP that is genuinely wrong is
fixed at the source. Keycloak is never written to from here either, though the
Admin API would allow it.

### Schools are OPDs with a parent, bidang are units

`BIDANG APLIKASI DAN INFORMATIKA` and `SD NEGERI 1 TEGALREJO` arrive in the same
position of `unit_kerja` but are not the same kind of thing. A bidang is a
division inside an OPD; a school is its own institution under one, with its own
assets, PIC and budget.

Schools therefore become OPD rows with `parent_id`, which is what keeps a
teacher scoped to their own school instead of to all of Dinas Pendidikan.

⚠️ `parent_id` records the relationship only. `ScopedToOpd` still matches one
`opd_id` exactly, so a parent does **not** see its children's data. Widening
that would change data isolation in hibah, aspirations and nawasara-api at
once, and it is its own decision rather than a side effect of adding a column.

### The shared picker

Four packages carried their own `pjCandidates()`, identical apart from
whitespace. `<livewire:nawasara-registry.pic-picker>` replaces them and reports
the choice over an event, so each host keeps its own property names:

```blade
<livewire:nawasara-registry.pic-picker :opd-id="$formOpdId" :pj-user-id="$formPjUserId" />
```

```php
#[On('pic-selected')]
public function onPicSelected(array $p): void
{
    $this->formOpdId    = $p['opd_id'];
    $this->formPjUserId = $p['pj_user_id'];
}
```

The picker writes nothing; the host still saves through `PicAssignmentService`.
A check living in Blade would be reached around by seeders, sync jobs and any
package that never heard of the rule.

Status: registry uses it. cloudflare, whm and zoom still have their own copies
and keep working; they move one at a time, each with its own tag.

A page that embeds a **second** picker (the backup PICs modal next to the
asset form) passes its own event name, or both hosts react to every choice:

```blade
<livewire:nawasara-registry.pic-picker event="backup-pic-selected" />
```

## Employment data follows SIMAS Hebat

`EmployeeSyncService` keeps each account's employment data and OPD membership in
line with SIMAS Hebat. Three callers share it:

| When | How often | Source tag |
|---|---|---|
| SSO login to **any** app (Portal ASN, godash, ...): `PullSsoLoginsJob` reads Keycloak LOGIN events | every 5 min, 15 min window | `sso` |
| Login to Nawasara (`Login` event, queued job) | at most once per 24 h per person | `login` |
| "Perbarui dari SIMAS" on **Data Pegawai** | 10 per admin per minute | `manual` |
| Appointing a PIC | each time | `pic` |

The **Data Pegawai** page (`/nawasara-registry/employees`) shows every account
with a NIP: what SIMAS says, the matched OPD, when it was last synced and by
which path, the assets they hold, and the history of OPD moves.

Decisions that look like gaps but are not:

- **Someone with no membership gets one** as soon as their `skpd` matches an
  OPD (decided 16 November 2026, v0.2.22). It is logged as "Keanggotaan OPD
  dibuat dari SIMAS Hebat". Roles are NOT granted: what a user may do with the
  OPD's data still comes from Pengajuan Petugas.
- **An `skpd` the registry does not know moves nothing.** There is nowhere to
  move it to. The row is flagged ("OPD belum dikenal Registry"); register the
  OPD or set its `simas_name`, then refresh.
- **Assets do not move with their PIC.** A domain belongs to the OPD, not to the
  person looking after it. The detail modal marks assets held by someone now in
  another OPD ("Beda OPD") so a new PIC can be appointed.
- **A deactivated membership stays deactivated** after a move. That was an
  admin's decision, not SIMAS data.
- **Not found / SIMAS down leaves the old data.** `simas_checked_at` records the
  attempt, `simas_synced_at` only moves when data actually arrived, so "last
  synced" never lies.
- **SSO logins elsewhere are read from Keycloak, not SIMAS.** Keycloak
  (authenticator 2.4.0) asks SIMAS on every login and stores the reply as
  `simas_*` user attributes. `SsoLoginImporter` reads recent LOGIN events and
  copies those attributes for people who have a Nawasara account, so a login
  to Portal ASN updates Nawasara with no extra SIMAS call. The 15-minute
  window overlaps the 5-minute schedule on purpose: Keycloak fills the
  attributes a moment after the event, and a run in between catches it next
  time. Rows already as new as Keycloak's `simas_synced_at` are skipped
  (compared to the second; the DB keeps whole seconds). Needs "Save events"
  on in the realm and `view-events` on Nawasara's admin client.
- **Throttled on purpose.** The SIMAS firewall blocks the calling IP after a
  burst, and the block is total. A block page sets a one-hour back-off for every
  path, and there is no "refresh everyone" button.

Config: `nawasara-registry.employee_sync` (`on_login`, `interval_hours`,
`manual_per_minute`). Set `REGISTRY_EMPLOYEE_SYNC_ON_LOGIN=false` to turn the
login refresh off.

## Several PICs per asset

`nawasara_registry_asset_assignments` (asset, user, role `primary` |
`backup`). An agency site or a shared mailbox is usually looked after by two
or three people, sometimes from another agency, and the ASN account portal
lists every asset a civil servant looks after.

- **The primary is still `pj_user_id`.** `Asset::booted` mirrors it into a
  `primary` row on every save, whoever writes it, so all existing readers of
  `pj_user_id` keep working. The migration copies existing `pj_user_id`
  values into `primary` rows.
- **Backups do not move OPD membership.** Appointing the primary moves the
  person's membership to the asset's OPD (`PicAssignmentService`). A backup
  can come from another agency, and moving them would silently change what
  `ScopedToOpd` lets them see, so backups go through
  `AssetAssignmentService::addBackup()` instead, which touches nothing else.
- **Removing a PIC is sudo-gated** (primary or backup): it revokes their
  access to the asset through the account portal, including password-less
  access to an agency mailbox.

### One place for OPD and every PIC

OPD, the primary PIC and backup PICs are all changed in the **OPD & PIC**
modal (`Section\Pics`), opened from the asset row menu. The asset form edits
only the asset itself: type, identifier, status, notes, ticket.

This used to be split: the primary was picked in the asset form, backups in
a separate "Kelola PIC" modal. Staff opened Edit, found a PIC field that
worked, and concluded the other menu was redundant or broken. Do not put a
PIC field back into the asset form.

- The form's save payload deliberately omits `opd_id` and `pj_user_id`, so
  editing a note can never wipe them.
- A newly created asset opens the OPD & PIC modal straight away.
- The SIMAS Hebat mismatch / unreachable confirmations moved with the primary
  picker; `picMismatch` and `picSimasError` stay `#[Locked]`.
- Only one picker is mounted at a time: two OPD + NIP pickers in one modal
  was a wall of fields, and both answered the same reset event.
- Other packages reuse the modal: `nawasara/proxmox` embeds
  `nawasara-registry.asset.section.pics` on its VM page and dispatches
  `asset-pics-open`. After any change the modal dispatches
  `asset-pics-changed` so lists showing PICs can refresh.
- The asset list shows backups under the primary, labelled Cadangan. An asset
  whose only PIC is a backup used to show "-".
- `AssetAssignmentService::assetsForKeycloakSub($sub, $types)` answers "what
  does this person look after", by Keycloak `sub` (what the portal holds).
  Active assets only.

## Installation

```bash
composer require nawasara/registry
php artisan migrate
php artisan db:seed --class="Nawasara\Registry\Database\Seeders\PermissionSeeder" --force
```

Auto-discovered.

Register the package with Tailwind, in `resources/css/app.css`:

```css
@source "../../vendor/nawasara/registry";
```

Without it **none** of the package's Tailwind classes compile, and the pages
render unstyled rather than erroring.

### SIMAS Hebat credentials

Fill the `simas-hebat` group in the Vault panel (`base_url`, `token`,
`app_id`). Until that is done, the OPD and PIC selects still work; only the NIP
lookup is unavailable, and it says so on screen rather than failing silently.

Set `SIMAS_TEST_NIP` to any real NIP if you want the Vault "Test" button to
verify the connection.

⚠️ Do not hardcode these. They appear in plain text inside
`SimasHebatAuthenticator.java` because a Keycloak authenticator cannot read
Vault; that is a constraint of where it runs, not a pattern to copy.

⚠️ The source firewall blocks the calling IP after a handful of requests, and
the block is total: NIPs that worked a moment ago start returning 403,
including the docs page. That is why the client never retries and why the
lookup is a button rather than a live-bound field.

## Asset linking pattern

Other packages create an asset row whenever they create a managed resource:

```php
use Nawasara\Registry\Models\Asset;

Asset::updateOrCreate(
    ['package_ref' => 'whm', 'external_id' => $username],
    [
        'type' => 'hosting_account',
        'identifier' => $domain,
        'opd_id' => $form['opd_id'] ?: null,
        'status' => 'active',
        'registered_at' => now(),
    ],
);
```

⚠️ Note what is **not** in that payload: `pj_user_id`. Appointing someone runs
through `PicAssignmentService` (see above), not through a field write, because
it re-checks their posting and may create an OPD:

```php
use Nawasara\Registry\Services\PicAssignmentService;

app(PicAssignmentService::class)->assign($asset, $user);
```

Resource list pages then look up the asset map in one query:

```php
$assetMap = Asset::where('package_ref', 'whm')
    ->whereIn('external_id', $usernames)
    ->with(['opd:id,name,code', 'penanggungJawab'])
    ->get()
    ->keyBy('external_id');
```

## Pages

| Route | Permission |
|-------|-----------|
| `/nawasara-registry/opd` | `registry.opd.view` |
| `/nawasara-registry/assets` | `registry.asset.view` |
| `/nawasara-registry/membership` | `registry.membership.manage` |
| `/nawasara-registry/membership-requests` | `registry.membership.manage` |
| `/nawasara-registry/employees` | `registry.employee.view` (refresh: `registry.employee.sync`) |

## API

Requires [`nawasara/api`](../nawasara-api). If that package is not installed, the routes are not mounted.

Registry is the organization's master data, so these are the most useful endpoints for sharing data between applications: two systems can use the same OPD list instead of each keeping a copy that slowly drifts apart.

### Scope

| Scope | Access |
|---|---|
| `registry.opd.read` | OPD list: code, name, address, agency contacts |
| `registry.asset.read` | Domains, subdomains, service accounts and their responsible party |
| `registry.membership.read` | Which employee works at which agency |

Membership is separate because it maps **people** to organizations, while the other two are organization data.

### Endpoints

| Method | Path | Query |
|---|---|---|
| GET | `/api/v1/registry/opd` | `q`, `per_page` (max 200) |
| GET | `/api/v1/registry/opd/{code}` | looked up by code, not id |
| GET | `/api/v1/registry/assets` | `q`, `type`, `status`, `opd` (code), `per_page` |
| GET | `/api/v1/registry/assets/{id}` | |
| GET | `/api/v1/registry/memberships` | `opd` (code), `aktif` (`1` default, `0`, or `all`), `per_page` |

Multi-value parameters accept commas: `?type=domain,subdomain`.

Use an OPD's `code` and a person's `keycloak_id` to link data across systems; both survive a change of name or username. A row's `id` only means something inside Nawasara.

### What is not returned

- **Asset `notes`**: free-form operator notes. Since there is no rule about what may be written there, there is no guarantee the content is safe to expose.
- **`ticket_ref`, `external_id`**: internal references and ids in third-party systems; only useful to someone with access to those systems.
- **The local `user_id`** on memberships; `keycloak_id` is returned instead.

### Note if `ScopedToOpd` is applied to Asset

Right now `Asset` does not use that trait. If it is ever applied, the asset endpoints must be reviewed: `MembershipResolver` treats a request with no logged-in user as `privileged`, and an API token has no user, so per-OPD filtering would be skipped silently with no error.

## Author

**Pringgo J. Saputro** &lt;odyinggo@gmail.com&gt;

## License

MIT
