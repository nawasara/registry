<?php

namespace Nawasara\Registry\Tests;

use Nawasara\Registry\Support\OpdName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Permintaan 15 Nov 2026: nama OPD tidak boleh ada yang kapital semua. */
class OpdNameTest extends TestCase
{
    #[DataProvider('namaProduksi')]
    public function test_nama_kapital_dari_produksi_dirapikan(string $asli, string $rapi): void
    {
        $this->assertSame($rapi, OpdName::tidy($asli));
    }

    public static function namaProduksi(): array
    {
        return [
            ['BADAN KESATUAN BANGSA DAN POLITIK', 'Badan Kesatuan Bangsa dan Politik'],
            ['BADAN PENDAPATAN, PENGELOLAAN KEUANGAN, DAN ASET DAERAH', 'Badan Pendapatan, Pengelolaan Keuangan, dan Aset Daerah'],
            ['BADAN PERENCANAAN PEMBANGUNAN, RISET DAN INOVASI DAERAH', 'Badan Perencanaan Pembangunan, Riset dan Inovasi Daerah'],
            ['BAGIAN KESEJAHTERAAN RAKYAT', 'Bagian Kesejahteraan Rakyat'],
            ['DINAS KEBUDAYAAN, PARIWISATA, PEMUDA, DAN OLAHRAGA', 'Dinas Kebudayaan, Pariwisata, Pemuda, dan Olahraga'],
            ['DINAS KOMUNIKASI, INFORMATIKA DAN STATISTIK', 'Dinas Komunikasi, Informatika dan Statistik'],
            ['DINAS PERTANIAN, KETAHANAN PANGAN DAN PERIKANAN KABUPATEN PONOROGO', 'Dinas Pertanian, Ketahanan Pangan dan Perikanan Kabupaten Ponorogo'],
            ['DINAS SOSIAL, PEMBERDAYAAN PEREMPUAN DAN PERLINDUNGAN ANAK', 'Dinas Sosial, Pemberdayaan Perempuan dan Perlindungan Anak'],
            ['INSPEKTORAT', 'Inspektorat'],
        ];
    }

    public function test_singkatan_tetap_kapital(): void
    {
        $this->assertSame('Sekretariat DPRD', OpdName::tidy('SEKRETARIAT DPRD'));
        $this->assertSame('RSUD dr. Harjono', OpdName::tidy('RSUD dr. Harjono'), 'sudah campur huruf: tidak disentuh');
        $this->assertSame('UPT Puskesmas (PKK)', OpdName::tidy('UPT PUSKESMAS (PKK)'));
    }

    public function test_kata_sambung_di_awal_tetap_besar(): void
    {
        $this->assertSame('Dan Lain-Lain', OpdName::tidy('DAN LAIN-LAIN'));
    }

    public function test_nama_yang_sudah_rapi_tidak_diubah(): void
    {
        $this->assertSame('Badan Penanggulangan Bencana Daerah', OpdName::tidy('Badan Penanggulangan Bencana Daerah'));
        $this->assertSame('Sekretariat DPRD', OpdName::tidy('Sekretariat DPRD'));
    }

    public function test_spasi_ganda_dirapikan(): void
    {
        $this->assertSame('Dinas Kesehatan', OpdName::tidy('  DINAS   KESEHATAN '));
    }
}
