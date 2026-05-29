<?php

$prefix = 'nawasara-registry';

return [
    [
        'label' => 'Registry Aset',
        'icon' => 'lucide-building-2',
        'url' => '',
        'permission' => 'registry.opd.view',
        'submenu' => [
            [
                'label' => 'OPD / Instansi',
                'icon' => 'lucide-landmark',
                'url' => url($prefix.'/opd'),
                'permission' => 'registry.opd.view',
                'navigate' => true,
            ],
            [
                'label' => 'Aset Digital',
                'icon' => 'lucide-globe',
                'url' => url($prefix.'/assets'),
                'permission' => 'registry.asset.view',
                'navigate' => true,
            ],
            [
                'label' => 'Keanggotaan OPD',
                'icon' => 'lucide-users',
                'url' => url($prefix.'/membership'),
                'permission' => 'registry.membership.manage',
                'navigate' => true,
            ],
        ],
    ],
];
