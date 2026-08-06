<?php

namespace Nawasara\Registry\Http\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Nawasara\Registry\Http\Resources\AssetResource;
use Nawasara\Registry\Models\Asset;

/**
 * Public API daftar aset — domain, subdomain, dan akun layanan milik OPD.
 *
 * Auth + scope dicek di middleware:
 *   - api.auth → token valid
 *   - scope:registry.asset.read
 */
class AssetController extends Controller
{
    /**
     * GET /api/v1/registry/assets
     * Scope: registry.asset.read
     *
     * Query params:
     *   q        — cari di identifier
     *   type     — domain | subdomain | … (boleh koma)
     *   status   — active | inactive | … (boleh koma)
     *   opd      — kode OPD (boleh koma)
     *   per_page — 1..100, default 50
     */
    public function index(Request $request): JsonResponse
    {
        // withoutGlobalScopes() TIDAK dipakai di sini: Asset memang tidak
        // memasang ScopedToOpd. Kalau suatu saat dipasang, endpoint ini harus
        // ditinjau ulang — MembershipResolver memperlakukan request tanpa user
        // (dan token API tidak punya user) sebagai `privileged`, yang berarti
        // penyaringan per-OPD akan lewat begitu saja tanpa ada yang menyadari.
        $query = Asset::query()->with('opd');

        if ($q = trim((string) $request->query('q', ''))) {
            $query->search($q);
        }

        if ($type = trim((string) $request->query('type', ''))) {
            $query->byType($this->csv($type));
        }

        if ($status = trim((string) $request->query('status', ''))) {
            $query->byStatus($this->csv($status));
        }

        // Disaring lewat kode OPD, bukan id: kode yang bermakna bagi pemanggil.
        if ($opd = trim((string) $request->query('opd', ''))) {
            $codes = $this->csv($opd);
            $query->whereHas('opd', fn ($q) => $q->whereIn('code', $codes));
        }

        $perPage = min(100, max(1, (int) $request->query('per_page', 50)));

        $assets = $query
            ->orderBy('identifier')
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'data' => AssetResource::collection($assets->items())->resolve(),
            'meta' => [
                'total' => $assets->total(),
                'per_page' => $assets->perPage(),
                'current_page' => $assets->currentPage(),
                'last_page' => $assets->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/registry/assets/{id}
     * Scope: registry.asset.read
     */
    public function show(int $id): JsonResponse
    {
        $asset = Asset::with('opd')->find($id);

        if (! $asset) {
            return response()->json([
                'error' => 'not_found',
                'message' => 'Aset tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'data' => (new AssetResource($asset))->resolve(request()),
        ]);
    }

    /** @return array<int, string> */
    protected function csv(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }
}
