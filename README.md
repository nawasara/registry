# Nawasara Registry

Master data for the Nawasara superapp framework: organizational units (OPD), persons in charge (PIC), and a generic asset ownership index that other packages link into.

## Features

- **OPD**: code, name, address, phone, email, and a list of related PIC contacts
- **PIC (Person-in-Charge)**: name, position, contact details, scoped to one OPD
- **Asset**: a generic ownership record keyed by `(package_ref, external_id)`. Other packages (Cloudflare DNS, WHM Account, Email account) write here with their canonical IDs so the dashboard can render an "OPD / PIC" column on every resource list and a single OPD detail page can show every asset they own
- **Activity log**: every write is captured via `spatie/laravel-activitylog`
- **Admin pages**: Livewire CRUD for OPD, PIC, and Asset with search, filter, and detail modals

## Installation

```bash
composer require nawasara/registry
php artisan migrate
php artisan db:seed --class="Nawasara\Registry\Database\Seeders\PermissionSeeder" --force
```

Auto-discovered.

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
        'pic_id' => $form['pic_id'] ?: null,
        'status' => 'active',
        'registered_at' => now(),
    ],
);
```

Resource list pages then look up the asset map in one query:

```php
$assetMap = Asset::where('package_ref', 'whm')
    ->whereIn('external_id', $usernames)
    ->with(['opd:id,name,code', 'pic:id,name'])
    ->get()
    ->keyBy('external_id');
```

## Pages

| Route | Permission |
|-------|-----------|
| `/admin/registry/opd` | `registry.opd.view` |
| `/admin/registry/pic` | `registry.pic.view` |
| `/admin/registry/asset` | `registry.asset.view` |

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
