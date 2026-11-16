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
 * The reply has six fields inside `data` (since 12 November 2026):
 *   nama_lengkap, nama_jabatan, jenis_jabatan, skpd, unit, jenis_kepegawaian
 *
 * Before that `skpd` and `unit` arrived glued together as `unit_kerja`.
 * `unit_kerja` is still returned, rebuilt from the two, because the audit
 * trail and the stored rows of older assignments use that key.
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
     * @return array{nama_lengkap: string, nama_jabatan: ?string, jenis_jabatan: ?string, skpd: ?string, unit: ?string, unit_kerja: ?string, jenis_kepegawaian: ?string}
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

        return static::parse($data, $nip);
    }

    /**
     * Turn a decoded reply into the fields Nawasara uses.
     *
     * Public and static so the parsing can be tested against real replies
     * without an HTTP call.
     *
     * @throws SimasHebatException  when the reply says the NIP is unknown
     */
    public static function parse(array $data, string $nip): array
    {
        // `{message, access: false}` is the current "no such NIP".
        if (array_key_exists('access', $data) && $data['access'] === false) {
            throw SimasHebatException::notFound($nip);
        }

        // Replies wrap the record in `data`.
        if (isset($data['data']) && is_array($data['data'])) {
            $data = $data['data'];
        }

        // A 200 carrying no name is how "no such NIP" arrives sometimes.
        $nama = trim((string) ($data['nama_lengkap'] ?? ''));
        if ($nama === '') {
            throw SimasHebatException::notFound($nip);
        }

        $skpd = static::cleanOrNull($data['skpd'] ?? null);
        $unit = static::cleanOrNull($data['unit'] ?? null);
        $unitKerja = static::cleanOrNull($data['unit_kerja'] ?? null)
            ?? static::cleanOrNull(trim(($skpd ?? '').' '.($unit ?? '')));

        return [
            'nama_lengkap' => $nama,
            'nama_jabatan' => static::cleanOrNull($data['nama_jabatan'] ?? null),
            'jenis_jabatan' => static::cleanOrNull($data['jenis_jabatan'] ?? null),
            'skpd' => $skpd,
            'unit' => $unit,
            'unit_kerja' => $unitKerja,
            'jenis_kepegawaian' => static::cleanOrNull($data['jenis_kepegawaian'] ?? null),
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

    private static function cleanOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

        return $value === '' ? null : $value;
    }
}
