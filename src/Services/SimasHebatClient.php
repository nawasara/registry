<?php

namespace Nawasara\Registry\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Nawasara\Registry\Exceptions\SimasHebatException;
use Nawasara\Vault\Facades\Vault;

/**
 * Looks one employee up in SIMAS Hebat by NIP.
 *
 * The whole API is two endpoints, `/auth/` and `/pegawai/`, and the second
 * takes a single NIP. There is no "list employees of OPD X", no search and no
 * paging, so Nawasara cannot pull a directory from here: it can only enrich
 * someone whose NIP it already knows. That shapes everything above this class.
 *
 * The reply is exactly five fields:
 *   nama_lengkap, nama_jabatan, jenis_jabatan, unit_kerja, jenis_kepegawaian
 *
 * ⚠️ NO RETRY, deliberately. The source firewall blocks the calling IP after a
 * few requests, and a block is total: NIPs that answered a moment ago start
 * returning 403, including the docs page. Retrying a failure is the fastest way
 * to earn one, so a failure is reported rather than repeated.
 */
class SimasHebatClient
{
    public function __construct(
        private string $baseUrl,
        private string $token,
        private string $appId,
        private int $timeout = 15,
    ) {}

    /**
     * Build from Vault (group `simas-hebat`); null when not configured.
     */
    public static function fromVault(): ?static
    {
        $group = (string) config('nawasara-registry.simas.vault_group', 'simas-hebat');

        $baseUrl = (string) Vault::get($group, 'base_url');
        $token   = (string) Vault::get($group, 'token');
        $appId   = (string) Vault::get($group, 'app_id');

        if ($baseUrl === '' || $token === '' || $appId === '') {
            return null;
        }

        return new static(
            baseUrl: rtrim($baseUrl, '/'),
            token: $token,
            appId: $appId,
            timeout: (int) config('nawasara-registry.simas.timeout', 15),
        );
    }

    /**
     * Fetch employment data for one NIP.
     *
     * @return array{nama_lengkap: string, nama_jabatan: ?string, jenis_jabatan: ?string, unit_kerja: ?string, jenis_kepegawaian: ?string}
     *
     * @throws SimasHebatException
     */
    public function pegawai(string $nip): array
    {
        $nip = trim($nip);

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->token,
                    'Accept' => 'application/json',
                ])
                ->asJson()
                ->post($this->baseUrl.'/api/v1/external/pegawai/', [
                    'app_id' => $this->appId,
                    'nip' => $nip,
                ]);
        } catch (ConnectionException $e) {
            throw SimasHebatException::unreachable($e);
        }

        if ($response->status() === 403) {
            // Could be a rejected token or the firewall. The firewall answers
            // with an HTML block page rather than JSON, which tells them apart.
            throw $this->looksLikeBlockPage($response->body())
                ? SimasHebatException::blocked()
                : SimasHebatException::authFailed();
        }

        if ($response->status() === 401) {
            throw SimasHebatException::authFailed();
        }

        if ($response->status() === 404) {
            throw SimasHebatException::notFound($nip);
        }

        if (! $response->successful()) {
            throw new SimasHebatException(
                'SIMAS Hebat menjawab HTTP '.$response->status().'.',
                'simas_error',
            );
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw SimasHebatException::unexpectedShape();
        }

        // Some replies wrap the record in `data`.
        if (isset($data['data']) && is_array($data['data'])) {
            $data = $data['data'];
        }

        // A 200 carrying no name is how "no such NIP" arrives sometimes.
        $nama = trim((string) ($data['nama_lengkap'] ?? ''));
        if ($nama === '') {
            throw SimasHebatException::notFound($nip);
        }

        return [
            'nama_lengkap' => $nama,
            'nama_jabatan' => $this->cleanOrNull($data['nama_jabatan'] ?? null),
            'jenis_jabatan' => $this->cleanOrNull($data['jenis_jabatan'] ?? null),
            'unit_kerja' => $this->cleanOrNull($data['unit_kerja'] ?? null),
            'jenis_kepegawaian' => $this->cleanOrNull($data['jenis_kepegawaian'] ?? null),
        ];
    }

    /**
     * Test button in the Vault panel.
     *
     * Must return the key `success`, not `ok` — the panel reads `success` and
     * shows a red toast for anything else, however friendly the message.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array
    {
        // Bawaannya NIP yang pasti tidak ada, sama seperti tombol Uji PDAM:
        // SIMAS menjawabnya 404 "Pegawai not found" (diperiksa 6 Oktober 2026),
        // yang sudah membuktikan alamat benar dan token diterima, tanpa menarik
        // data pegawai sungguhan ke layar admin. Token yang ditolak dijawab
        // 401/403, jadi tidak tertukar.
        $nip = (string) config('nawasara-registry.simas.test_nip', '') ?: '000000000000000000';

        try {
            $data = $this->pegawai($nip);

            return [
                'success' => true,
                'message' => 'Terhubung ke SIMAS Hebat. Data uji terbaca: '.$data['nama_lengkap'],
            ];
        } catch (SimasHebatException $e) {
            if ($e->errorCode === 'simas_not_found') {
                return ['success' => true, 'message' => 'Terhubung ke SIMAS Hebat, token diterima.'];
            }

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * The firewall's block page is HTML and says so in Indonesian; the API
     * itself always answers JSON.
     */
    private function looksLikeBlockPage(string $body): bool
    {
        return str_contains($body, 'Akses Ditolak')
            || str_contains($body, 'aktivitas mencurigakan')
            || str_starts_with(ltrim($body), '<');
    }

    private function cleanOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

        return $value === '' ? null : $value;
    }
}
