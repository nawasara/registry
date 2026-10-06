<?php

return [
    'asset_types' => [
        'domain' => 'Domain',
        'subdomain' => 'Subdomain',
        'hosting' => 'Hosting / cPanel',
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
];
