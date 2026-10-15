<?php

declare(strict_types=1);

namespace Nawasara\Registry\Tests;

use Nawasara\Registry\Support\MembershipResolver;
use PHPUnit\Framework\TestCase;

/**
 * Aturan TULIS `opd_id` (audit keamanan 15 Oktober 2026, temuan S3).
 *
 * ScopedToOpd dulu hanya menyaring BACA. Operator OPD dapat membuat usulan
 * untuk OPD lain, atau memindahkan usulannya sendiri keluar, cukup dengan
 * mengirim `opd_id` karangan. Aturan tulisnya harus mengikuti tiga keadaan
 * yang sama dengan aturan baca.
 */
class OpdWriteGuardTest extends TestCase
{
    private const PRIVILEGED = ['state' => 'privileged', 'opdId' => null];

    private const MEMBER_OF_7 = ['state' => 'member', 'opdId' => 7];

    private const RESTRICTED = ['state' => 'restricted', 'opdId' => null];

    public function test_privileged_bebas_menulis_ke_opd_mana_pun(): void
    {
        $this->assertTrue(MembershipResolver::allowsOpdWrite(self::PRIVILEGED, 3, false, null));
        $this->assertTrue(MembershipResolver::allowsOpdWrite(self::PRIVILEGED, 3, true, 9));
        $this->assertTrue(MembershipResolver::allowsOpdWrite(self::PRIVILEGED, null, true, 9));
    }

    public function test_anggota_boleh_membuat_di_opd_sendiri(): void
    {
        $this->assertTrue(MembershipResolver::allowsOpdWrite(self::MEMBER_OF_7, 7, false, null));
    }

    public function test_anggota_tidak_boleh_membuat_di_opd_lain(): void
    {
        $this->assertFalse(MembershipResolver::allowsOpdWrite(self::MEMBER_OF_7, 3, false, null));
    }

    public function test_anggota_tidak_boleh_membuat_tanpa_opd(): void
    {
        $this->assertFalse(MembershipResolver::allowsOpdWrite(self::MEMBER_OF_7, null, false, null));
    }

    /** Memindahkan baris miliknya sendiri KELUAR dari OPD-nya. */
    public function test_anggota_tidak_boleh_memindahkan_keluar(): void
    {
        $this->assertFalse(MembershipResolver::allowsOpdWrite(self::MEMBER_OF_7, 3, true, 7));
    }

    /** Menarik baris milik OPD lain MASUK ke OPD-nya. */
    public function test_anggota_tidak_boleh_menarik_masuk(): void
    {
        $this->assertFalse(MembershipResolver::allowsOpdWrite(self::MEMBER_OF_7, 7, true, 3));
    }

    public function test_restricted_tidak_boleh_menulis_apa_pun(): void
    {
        $this->assertFalse(MembershipResolver::allowsOpdWrite(self::RESTRICTED, 7, false, null));
        $this->assertFalse(MembershipResolver::allowsOpdWrite(self::RESTRICTED, null, false, null));
    }

    /** Keadaan yang tidak dikenal harus fail-closed, bukan lolos. */
    public function test_keadaan_tak_dikenal_ditolak(): void
    {
        $this->assertFalse(MembershipResolver::allowsOpdWrite(['state' => 'aneh', 'opdId' => 7], 7, false, null));
        $this->assertFalse(MembershipResolver::allowsOpdWrite([], 7, false, null));
    }

    /**
     * Penjaga harus berupa hook `saving` yang MELEMPAR, bukan mengembalikan
     * false: `saving` yang mengembalikan false membatalkan simpan diam-diam
     * dan staf mengira datanya tersimpan.
     */
    public function test_trait_memasang_hook_saving_yang_melempar(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__).'/src/Concerns/ScopedToOpd.php');

        $this->assertStringContainsString('static::saving(', $src);
        $this->assertStringContainsString('MembershipResolver::allowsOpdWrite(', $src);
        $this->assertStringContainsString('throw new AuthorizationException', $src);
    }
}
