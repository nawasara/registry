<?php

declare(strict_types=1);

namespace Nawasara\Registry\Tests;

use Nawasara\Registry\Exceptions\SimasHebatException;
use Nawasara\Registry\Services\SimasHebatClient;
use Nawasara\Registry\Services\UnitKerjaMatcher;
use PHPUnit\Framework\TestCase;

/**
 * Jawaban SIMAS Hebat sejak 12 November 2026: `skpd` + `unit` terpisah.
 *
 * Sebelum ini client membaca `unit_kerja`, yang sudah tidak dikirim, sehingga
 * setiap pencarian NIP berakhir tanpa OPD dan diam-diam meminta admin
 * mendaftarkan OPD yang sebenarnya sudah ada.
 */
class SimasReplyTest extends TestCase
{
    private const NIP = '199506142020121004';

    /** Jawaban nyata 16 November 2026, dipotong ke medan yang dipakai. */
    private function replyNow(): array
    {
        return [
            'message' => 'Data retrieved successfully',
            'access' => true,
            'data' => [
                'nama_lengkap' => ' PRINGGO JUNI SAPUTRO S.Tr.Kom.',
                'nama_jabatan' => 'Pranata Komputer Keterampilan Terampil',
                'jenis_jabatan' => 'Jabatan Fungsional',
                'skpd' => 'DINAS KOMUNIKASI, INFORMATIKA DAN STATISTIK',
                'unit' => 'BIDANG APLIKASI DAN INFORMATIKA',
                'jenis_kepegawaian' => 'PNS',
            ],
        ];
    }

    public function test_jawaban_baru_membawa_skpd_dan_unit(): void
    {
        $d = SimasHebatClient::parse($this->replyNow(), self::NIP);

        $this->assertSame('PRINGGO JUNI SAPUTRO S.Tr.Kom.', $d['nama_lengkap']);
        $this->assertSame('DINAS KOMUNIKASI, INFORMATIKA DAN STATISTIK', $d['skpd']);
        $this->assertSame('BIDANG APLIKASI DAN INFORMATIKA', $d['unit']);
        // Disusun ulang untuk jejak audit dan baris lama.
        $this->assertSame('DINAS KOMUNIKASI, INFORMATIKA DAN STATISTIK BIDANG APLIKASI DAN INFORMATIKA', $d['unit_kerja']);
    }

    public function test_jawaban_lama_tetap_terbaca(): void
    {
        $d = SimasHebatClient::parse([
            'nama_lengkap' => 'X',
            'unit_kerja' => 'DINAS PENDIDIKAN SD NEGERI 1 TEGALREJO',
        ], self::NIP);

        $this->assertNull($d['skpd']);
        $this->assertSame('DINAS PENDIDIKAN SD NEGERI 1 TEGALREJO', $d['unit_kerja']);
    }

    public function test_access_false_berarti_tidak_ditemukan(): void
    {
        $this->expectException(SimasHebatException::class);

        try {
            SimasHebatClient::parse(['message' => 'Pegawai not found', 'access' => false], self::NIP);
        } catch (SimasHebatException $e) {
            $this->assertSame('simas_not_found', $e->errorCode);
            throw $e;
        }
    }

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

    public function test_skpd_dicocokkan_utuh_dan_unit_menjadi_sub_unit(): void
    {
        $m = $this->matcher(['Dinas Komunikasi, Informatika dan Statistik', 'Dinas Pendidikan']);

        $h = $m->matchSimas(SimasHebatClient::parse($this->replyNow(), self::NIP));

        $this->assertSame('Dinas Komunikasi, Informatika dan Statistik', $h['opd']);
        $this->assertSame('BIDANG APLIKASI DAN INFORMATIKA', $h['sub_unit']);
    }

    public function test_skpd_yang_hanya_berawalan_nama_opd_tidak_dicocokkan(): void
    {
        // Registry baru kenal "DINAS PENDIDIKAN"; SIMAS menyebut OPD lain yang
        // namanya diawali itu. Mencocokkan berarti memindah orang ke OPD salah.
        $m = $this->matcher(['DINAS PENDIDIKAN']);

        $h = $m->matchSimas(['skpd' => 'DINAS PENDIDIKAN DAN KEBUDAYAAN', 'unit' => 'BIDANG SMP']);

        $this->assertNull($h['opd']);
        $this->assertNull($h['sub_unit']);
    }

    public function test_unit_bertanda_hubung_dinormalkan_seperti_sisa_awalan_dulu(): void
    {
        $m = $this->matcher(['SEKRETARIAT DAERAH']);

        $h = $m->matchSimas(['skpd' => 'SEKRETARIAT DAERAH', 'unit' => 'BAGIAN UMUM - SEKRETARIAT DAERAH']);

        $this->assertSame('SEKRETARIAT DAERAH', $h['opd']);
        $this->assertSame('BAGIAN UMUM SEKRETARIAT DAERAH', $h['sub_unit']);
    }

    public function test_tanpa_skpd_jatuh_ke_pencocokan_awalan(): void
    {
        $m = $this->matcher(['DINAS PENDIDIKAN']);

        $h = $m->matchSimas(['unit_kerja' => 'DINAS PENDIDIKAN SD NEGERI 1 TEGALREJO']);

        $this->assertSame('DINAS PENDIDIKAN', $h['opd']);
        $this->assertSame('SD NEGERI 1 TEGALREJO', $h['sub_unit']);
    }
}
