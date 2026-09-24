<?php

declare(strict_types=1);

namespace Nawasara\Registry\Tests;

use Nawasara\Registry\Services\UnitKerjaMatcher;
use PHPUnit\Framework\TestCase;

/**
 * Memisahkan `unit_kerja` SIMAS Hebat menjadi OPD dan sub-unitnya.
 *
 * Medannya datang sebagai `<NAMA OPD><spasi><NAMA SUB-UNIT>` tanpa pemisah
 * apa pun, dan nama OPD sendiri memuat koma serta kata "DAN". Memecahnya
 * dengan aturan (explode koma, cari kata "BIDANG") salah pada sebagian kasus.
 *
 * Yang dipakai adalah pertanyaan sebaliknya: apakah teks ini DIAWALI nama OPD
 * yang sudah dikenal? Karena itu yang diuji di sini bukan hanya yang cocok,
 * melainkan juga yang HARUS menjawab null. Kecocokan yang meleset menyerahkan
 * data OPD lain kepada orang yang salah, jadi "tidak tahu" lebih baik daripada
 * "kira-kira ini".
 *
 * Kelima contoh di bawah adalah `unit_kerja` sungguhan dari produksi.
 */
class UnitKerjaMatcherTest extends TestCase
{
    /** Matcher dengan daftar OPD di memori, bukan basis data. */
    private function matcher(array $namaOpd): UnitKerjaMatcher
    {
        return new class($namaOpd) extends UnitKerjaMatcher
        {
            public function __construct(private array $daftar) {}

            protected function candidates(): \Generator
            {
                foreach ($this->daftar as $nama) {
                    yield $nama => self::normalize($nama);
                }
            }
        };
    }

    private function registriProduksi(): array
    {
        return [
            'DINAS KOMUNIKASI, INFORMATIKA DAN STATISTIK',
            'DINAS PENDIDIKAN',
            'Badan Kepegawaian dan Pengembangan Sumber Daya Manusia',
            'INSPEKTORAT',
            'Dinas Lingkungan Hidup',
        ];
    }

    public function test_opd_dengan_bidang(): void
    {
        $h = $this->matcher($this->registriProduksi())->match(
            'DINAS KOMUNIKASI, INFORMATIKA DAN STATISTIK BIDANG APLIKASI DAN INFORMATIKA'
        );

        $this->assertSame('DINAS KOMUNIKASI, INFORMATIKA DAN STATISTIK', $h['opd']);
        $this->assertSame('BIDANG APLIKASI DAN INFORMATIKA', $h['sub_unit']);
    }

    public function test_opd_dengan_sekolah(): void
    {
        $h = $this->matcher($this->registriProduksi())->match(
            'DINAS PENDIDIKAN SD NEGERI 1 TEGALREJO'
        );

        $this->assertSame('DINAS PENDIDIKAN', $h['opd']);
        $this->assertSame('SD NEGERI 1 TEGALREJO', $h['sub_unit']);
    }

    public function test_opd_tanpa_sub_unit(): void
    {
        $h = $this->matcher($this->registriProduksi())->match('INSPEKTORAT');

        $this->assertSame('INSPEKTORAT', $h['opd']);
        $this->assertNull($h['sub_unit']);
    }

    /**
     * Registry menulis Title Case, SIMAS Hebat HURUF BESAR SEMUA, dan satu
     * baris produksi punya spasi menggantung. Normalisasi menembus ketiganya.
     */
    public function test_beda_penulisan_tetap_cocok(): void
    {
        $h = $this->matcher($this->registriProduksi())->match(
            'BADAN KEPEGAWAIAN DAN PENGEMBANGAN SUMBER DAYA MANUSIA BIDANG PERENCANAAN, PENGADAAN, DAN PENGEMBANGAN'
        );

        $this->assertSame('Badan Kepegawaian dan Pengembangan Sumber Daya Manusia', $h['opd']);
        $this->assertSame('BIDANG PERENCANAAN PENGADAAN DAN PENGEMBANGAN', $h['sub_unit']);
    }

    /**
     * Yang paling penting: OPD yang belum ada di registry menjawab null.
     *
     * `SEKRETARIAT DAERAH` memang belum terdaftar di produksi. Menebaknya
     * sebagai OPD terdekat akan memberi orang itu akses ke data OPD lain.
     */
    public function test_opd_tak_dikenal_menjawab_null(): void
    {
        $h = $this->matcher($this->registriProduksi())->match('SEKRETARIAT DAERAH');

        $this->assertNull($h['opd']);
        $this->assertNull($h['sub_unit']);
        $this->assertSame('SEKRETARIAT DAERAH', $h['raw']);
    }

    public function test_awalan_terpanjang_menang(): void
    {
        $h = $this->matcher(['DINAS PENDIDIKAN', 'DINAS PENDIDIKAN DAN KEBUDAYAAN'])
            ->match('DINAS PENDIDIKAN DAN KEBUDAYAAN BIDANG SMP');

        $this->assertSame('DINAS PENDIDIKAN DAN KEBUDAYAAN', $h['opd']);
        $this->assertSame('BIDANG SMP', $h['sub_unit']);
    }

    /**
     * Awalan harus berhenti di batas kata, kalau tidak "DINAS PERTANIAN"
     * akan mengklaim "DINAS PERTANIANX".
     */
    public function test_awalan_berhenti_di_batas_kata(): void
    {
        $h = $this->matcher(['DINAS PERTANIAN'])->match('DINAS PERTANIANX SESUATU');

        $this->assertNull($h['opd']);
    }

    public function test_spasi_berlebih_dan_tanda_baca(): void
    {
        $h = $this->matcher(['Dinas Lingkungan Hidup'])
            ->match('  DINAS   LINGKUNGAN,  HIDUP   BIDANG TATA LINGKUNGAN  ');

        $this->assertSame('Dinas Lingkungan Hidup', $h['opd']);
        $this->assertSame('BIDANG TATA LINGKUNGAN', $h['sub_unit']);
    }

    public function test_teks_kosong(): void
    {
        $h = $this->matcher($this->registriProduksi())->match('');

        $this->assertNull($h['opd']);
    }
}
