# Nawasara Registry

Master data for the Nawasara superapp framework: organizational units (OPD), persons in charge (PIC), and a generic asset ownership index that other packages link into.

## Features

- **OPD** — code, name, address, phone, email, and a list of related PIC contacts
- **PIC (Person-in-Charge)** — name, position, contact details, scoped to one OPD
- **Asset** — generic ownership record keyed by `(package_ref, external_id)`. Other packages (Cloudflare DNS, WHM Account, Email account) write here with their canonical IDs so the dashboard can render an "OPD / PIC" column on every resource list and a single OPD detail page can show every asset they own
- **Activity log** — every write is captured via `spatie/laravel-activitylog`
- **Admin pages** — Livewire CRUD for OPD, PIC, and Asset with search, filter, and detail modals

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

## Author

**Pringgo J. Saputro** &lt;odyinggo@gmail.com&gt;

## License

MIT
