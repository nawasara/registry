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

## API

Butuh [`nawasara/api`](../nawasara-api). Kalau paket itu tidak terpasang, route tidak di-mount.

Registry adalah data master organisasi, jadi ini endpoint yang paling berguna untuk berbagi data antar aplikasi: dua sistem bisa memakai daftar OPD yang sama alih-alih masing-masing menyimpan salinan yang lambat laun berbeda.

### Scope

| Scope | Akses |
|---|---|
| `registry.opd.read` | Daftar OPD: kode, nama, alamat, kontak dinas |
| `registry.asset.read` | Domain, subdomain, akun layanan + penanggung jawab |
| `registry.membership.read` | Pegawai mana bertugas di dinas mana |

Keanggotaan dipisah karena ia memetakan **orang** ke organisasi, sedangkan dua yang lain adalah data organisasi.

### Endpoint

| Method | Path | Query |
|---|---|---|
| GET | `/api/v1/registry/opd` | `q`, `per_page` (maks 200) |
| GET | `/api/v1/registry/opd/{code}` | dicari lewat kode, bukan id |
| GET | `/api/v1/registry/assets` | `q`, `type`, `status`, `opd` (kode), `per_page` |
| GET | `/api/v1/registry/assets/{id}` | |
| GET | `/api/v1/registry/memberships` | `opd` (kode), `aktif` (`1` default \| `0` \| `all`), `per_page` |

Parameter multi-nilai menerima koma: `?type=domain,subdomain`.

Pakai `code` OPD dan `keycloak_id` orang untuk menautkan data lintas sistem — keduanya bertahan meski nama atau username berubah. `id` baris hanya bermakna di dalam Nawasara.

### Yang tidak dikembalikan

- **`notes`** aset — catatan operator, bebas isi. Karena tidak ada aturan apa yang boleh ditulis di sana, tidak ada jaminan isinya aman keluar.
- **`ticket_ref`, `external_id`** — rujukan internal dan id di sistem pihak ketiga; hanya berguna bagi yang punya akses ke sistem itu.
- **`user_id` lokal** pada keanggotaan; `keycloak_id` yang dipakai sebagai gantinya.

### Catatan bila `ScopedToOpd` dipasang ke Asset

Saat ini `Asset` **tidak** memakai trait itu. Kalau suatu saat dipasang, endpoint aset harus ditinjau ulang: `MembershipResolver` memperlakukan permintaan tanpa user login sebagai `privileged`, dan token API tidak punya user — jadi penyaringan per-OPD akan terlewat begitu saja tanpa error.

## Author

**Pringgo J. Saputro** &lt;odyinggo@gmail.com&gt;

## License

MIT
