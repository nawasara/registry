<?php

declare(strict_types=1);

namespace Nawasara\Registry\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PicPicker membuat OPD, unit, dan akun user (audit keamanan 15 Oktober
 * 2026, temuan S4). Tes ini memeriksa sumbernya, karena kegagalannya diam:
 * method tanpa gerbang tetap berjalan normal bagi admin dan baru terlihat
 * saat disalahgunakan.
 */
class PicPickerGuardTest extends TestCase
{
    private function source(): string
    {
        return (string) file_get_contents(dirname(__DIR__).'/src/Livewire/PicPicker.php');
    }

    /** @return array<string, array{0: string}> */
    public static function actions(): array
    {
        return [
            'mount' => ['mount'],
            'lookup' => ['lookup'],
            'review' => ['review'],
            'forceCreateOpd' => ['forceCreateOpd'],
            'saveCreate' => ['saveCreate'],
            'selectFound' => ['selectFound'],
        ];
    }

    #[DataProvider('actions')]
    public function test_aksi_memeriksa_izin(string $method): void
    {
        preg_match('/public function '.$method.'\(.*?\n    \}/s', $this->source(), $m);

        $this->assertNotEmpty($m, "metode {$method}() tidak ditemukan");
        $this->assertStringContainsString('$this->authorizeManage()', $m[0], "{$method}() tanpa gerbang izin");
    }

    /** Hasil SIMAS tidak boleh lagi menjadi properti publik yang bolak-balik ke peramban. */
    public function test_hasil_pencarian_tidak_publik(): void
    {
        $this->assertDoesNotMatchRegularExpression('/public \??array \$found\b/', $this->source());
        $this->assertMatchesRegularExpression('/#\[Locked\]\s+public string \$lookupToken/', $this->source());
    }

    public function test_lookup_dibatasi_dan_tidak_memprovisi_akun(): void
    {
        preg_match('/public function lookup\(.*?\n    \}/s', $this->source(), $m);

        $this->assertStringContainsString('RateLimiter::tooManyAttempts', $m[0]);
        $this->assertStringNotContainsString('resolveAccount(', $m[0]);
    }
}
