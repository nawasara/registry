<?php

namespace Nawasara\Registry\Tests;

use Nawasara\Registry\Services\MembershipRequestService;
use PHPUnit\Framework\TestCase;

/**
 * Pengajuan petugas (15 Nov 2026). Alur setujui/tolak diuji di aplikasi
 * karena butuh basis data; di sini aturan murni yang salahnya tidak
 * menghasilkan galat.
 */
class MembershipRequestRulesTest extends TestCase
{
    public function test_nip_dibersihkan_dari_spasi_dan_titik(): void
    {
        $this->assertSame('199001012020011001', MembershipRequestService::normalizeNip(' 19900101 202001 1 001 '));
        $this->assertSame('199001012020011001', MembershipRequestService::normalizeNip('19900101.202001.1.001'));
    }

    public function test_daftar_peran_tertutup_tidak_memuat_developer(): void
    {
        $config = require __DIR__.'/../config/nawasara-registry.php';
        $roles = $config['membership_request_roles'];

        $this->assertArrayHasKey('lapor-opd-operator', $roles);
        $this->assertArrayHasKey('lapor-opd-kabid', $roles);
        $this->assertArrayNotHasKey('developer', $roles, 'Pengajuan tidak boleh menjadi jalan memberi peran apa pun.');
        $this->assertArrayNotHasKey('lapor-opd-admin', $roles, 'Admin OPD tidak boleh mengangkat admin OPD lain.');
    }
}
