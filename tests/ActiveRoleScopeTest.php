<?php

declare(strict_types=1);

namespace Nawasara\Registry\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Lingkup data harus menghormati ROLE AKTIF, bukan seluruh role akun.
 *
 * `SwitchRole` menyimpan `active_role` di sesi, dan `RespectsActiveRole`
 * menyempitkan pemeriksaan permission ke role itu saja. Tetapi
 * `MembershipResolver` sempat memakai `hasAnyRole()`, yang membaca SEMUA
 * role yang dipegang akun — jadi lingkup datanya tidak ikut menyempit.
 *
 * Akibatnya: pengembang yang juga memegang `hibah-operator` beralih ke
 * operator, melihat menu setingkat operator, dan **tetap melihat data
 * seluruh OPD**.
 *
 * Yang membuatnya berbahaya bukan kebocorannya saja, melainkan bahwa
 * penyempitannya TERLIHAT bekerja — sehingga isolasi antar-OPD diuji dari
 * akun yang tidak pernah bisa membuktikannya.
 */
class ActiveRoleScopeTest extends TestCase
{
    private function source(): string
    {
        return (string) file_get_contents(dirname(__DIR__).'/src/Support/MembershipResolver.php');
    }

    /**
     * Pemeriksaan privileged tidak boleh langsung memanggil hasAnyRole().
     *
     * Ia harus lewat pembungkus yang membaca `active_role` lebih dulu.
     */
    public function test_privileged_tidak_langsung_memakai_has_any_role(): void
    {
        $src = $this->source();

        // Buang komentar supaya penjelasan tentang jebakan ini tidak
        // dianggap sebagai kodenya.
        $code = preg_replace('#/\*\*.*?\*/#s', '', $src) ?? $src;
        $code = preg_replace('#//[^\n]*#', '', $code) ?? $code;

        preg_match('/public function resolve\(.*?\n    \}/s', $code, $m);

        $this->assertNotEmpty($m, 'metode resolve() tidak ditemukan');

        $this->assertStringNotContainsString(
            '$user->hasAnyRole($privilegedRoles)',
            $m[0],
            'resolve() memanggil hasAnyRole() langsung — role aktif akan diabaikan '
            .'dan operator tetap melihat seluruh OPD',
        );

        $this->assertStringContainsString('actsAsPrivileged(', $m[0]);
    }

    /** Pembungkusnya harus benar-benar membaca active_role. */
    public function test_pembungkus_membaca_active_role(): void
    {
        $src = $this->source();

        $this->assertStringContainsString('protected function actsAsPrivileged(', $src);
        $this->assertStringContainsString("session('active_role')", $src);
    }

    /**
     * `active_role` yang basi TIDAK boleh mengunci pengguna.
     *
     * Role dapat dicabut setelah seseorang beralih ke sana. Bila itu
     * terjadi, pemeriksaan harus jatuh ke perilaku semula, bukan menolak
     * semuanya — pengguna yang tiba-tiba tidak melihat apa pun akan
     * menyimpulkan aplikasinya rusak.
     */
    public function test_active_role_basi_jatuh_ke_pemeriksaan_penuh(): void
    {
        $src = $this->source();

        $this->assertMatchesRegularExpression(
            '/\$activeRole\s*&&.*hasRole\(\$activeRole\)/s',
            $src,
            'active_role harus diverifikasi masih dipegang pengguna sebelum dipakai',
        );

        // Jalur terakhir tetap pemeriksaan penuh.
        $this->assertMatchesRegularExpression(
            '/return \$user->hasAnyRole\(\$privilegedRoles\);\s*\}/s',
            $src,
        );
    }
}
