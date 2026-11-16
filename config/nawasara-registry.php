<?php

return [
    'asset_types' => [
        'domain' => 'Domain',
        'subdomain' => 'Subdomain',
        'hosting' => 'Hosting / cPanel',
        // Written by nawasara/whm's AccountRegistrySync for every cPanel
        // account. Listed so it gets a label instead of the raw key.
        'hosting_account' => 'Hosting / cPanel (WHM)',
        'email' => 'Email',
        'server' => 'Server / VM',
        'application' => 'Aplikasi',
        'network' => 'Perangkat Jaringan',
        'other' => 'Lainnya',
    ],

    'asset_statuses' => [
        'active' => 'Aktif',
        'inactive' => 'Tidak Aktif',
        'pending' => 'Pending',
    ],

    /*
    |---------------------------------------------------------------------------
    | SIMAS Hebat
    |---------------------------------------------------------------------------
    | Employment lookup by NIP, called when a PIC is assigned. Credentials live
    | in Vault, never here: the Keycloak authenticator hardcodes them because it
    | cannot read Vault, and that is not a pattern to copy into a package.
    */
    'simas' => [
        'vault_group' => 'simas-hebat',
        'timeout' => 15,

        // NIP for the Vault "Test" button. Leave empty: the button then uses a
        // NIP that cannot exist, and SIMAS answering "not found" already proves
        // the URL and token work without showing a real employee's data.
        'test_nip' => env('SIMAS_TEST_NIP', ''),

        // How long employment data may sit before the UI calls it stale.
        'stale_after_days' => 180,
    ],

    /*
    | Data kepegawaian diperbarui dari SIMAS Hebat saat login ke Nawasara
    | (EmployeeSyncService). SIMAS adalah sumber resmi: pegawai yang mutasi
    | otomatis pindah keanggotaan OPD-nya.
    |
    | interval_hours: jarak minimal antar-percobaan per orang. Jangan
    | dikecilkan banyak, firewall SIMAS memblokir permintaan beruntun.
    */
    'employee_sync' => [
        'on_login' => env('REGISTRY_EMPLOYEE_SYNC_ON_LOGIN', true),
        'interval_hours' => 24,
        // Batas "Perbarui dari SIMAS" per admin per menit.
        'manual_per_minute' => 10,
    ],

    /*
    | Peran yang boleh diajukan admin OPD lewat "Ajukan Petugas"
    | (MembershipRequestService). Disetujui admin Registry; persetujuan
    | memberi peran ini sekaligus menautkan pegawai ke OPD pengaju.
    |
    | Daftar tertutup: peran di luar daftar ditolak, supaya pengajuan tidak
    | menjadi jalan memberi peran apa pun (mis. developer).
    */
    'membership_request_roles' => [
        'lapor-opd-operator' => 'Petugas penanganan laporan',
        'lapor-opd-kabid' => 'Kabid pemeriksa',
    ],
];
