<?php

namespace Nawasara\Registry\Http\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Nawasara\Registry\Http\Resources\OpdResource;
use Nawasara\Registry\Models\Opd;

/**
 * Public API daftar OPD — data master organisasi perangkat daerah.
 *
 * Ini yang paling sering dibutuhkan aplikasi lain: daftar dinas beserta
 * kodenya, supaya keduanya bicara tentang organisasi yang sama tanpa
 * masing-masing menyimpan salinan yang lambat laun berbeda.
 *
 * Auth + scope dicek di middleware:
 *   - api.auth → token valid
 *   - scope:registry.opd.read
 */
class OpdController extends Controller
{
    /**
     * GET /api/v1/registry/opd
     * Scope: registry.opd.read
     *
     * Query params:
     *   q        — cari di kode / nama
     *   per_page — 1..200, default 100 (jumlah OPD terbatas, jadi batas
     *              halamannya lebih longgar dari endpoint lain)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Opd::query()->withCount(['assets', 'memberships']);

        if ($q = trim((string) $request->query('q', ''))) {
            $query->search($q);
        }

        $perPage = min(200, max(1, (int) $request->query('per_page', 100)));

        $opd = $query->orderBy('name')->orderBy('id')->paginate($perPage);

        return response()->json([
            'data' => OpdResource::collection($opd->items())->resolve(),
            'meta' => [
                'total' => $opd->total(),
                'per_page' => $opd->perPage(),
                'current_page' => $opd->currentPage(),
                'last_page' => $opd->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/registry/opd/{code}
     * Scope: registry.opd.read
     *
     * Dicari lewat `code`, bukan id — kode OPD bermakna di luar Nawasara
     * dan tidak berubah, sedangkan id hanya nomor baris.
     */
    public function show(string $code): JsonResponse
    {
        $opd = Opd::withCount(['assets', 'memberships'])->where('code', $code)->first();

        if (! $opd) {
            return response()->json([
                'error' => 'not_found',
                'message' => 'OPD tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'data' => (new OpdResource($opd))->resolve(request()),
        ]);
    }
}
