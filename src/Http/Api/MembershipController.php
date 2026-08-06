<?php

namespace Nawasara\Registry\Http\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Nawasara\Registry\Http\Resources\MembershipResource;
use Nawasara\Registry\Models\Membership;

/**
 * Public API keanggotaan OPD — memetakan pegawai ke dinas tempatnya bertugas.
 *
 * Berbeda dari endpoint OPD dan aset yang isinya data organisasi, endpoint ini
 * memetakan ORANG ke organisasi. Karena itu scope-nya dipisah
 * (`registry.membership.read`), supaya aplikasi yang hanya butuh daftar dinas
 * tidak ikut mendapat peta siapa bekerja di mana.
 *
 * Auth + scope dicek di middleware:
 *   - api.auth → token valid
 *   - scope:registry.membership.read
 */
class MembershipController extends Controller
{
    /**
     * GET /api/v1/registry/memberships
     * Scope: registry.membership.read
     *
     * Query params:
     *   opd      — kode OPD (boleh koma)
     *   aktif    — 1 hanya yang aktif (default), 0 hanya nonaktif, all semua
     *   per_page — 1..100, default 50
     */
    public function index(Request $request): JsonResponse
    {
        $query = Membership::query()->with(['opd', 'user']);

        if ($opd = trim((string) $request->query('opd', ''))) {
            $codes = array_values(array_filter(array_map('trim', explode(',', $opd))));
            $query->whereHas('opd', fn ($q) => $q->whereIn('code', $codes));
        }

        // Default hanya yang aktif: keanggotaan nonaktif adalah jejak riwayat,
        // dan menyertakannya diam-diam membuat konsumen menampilkan orang yang
        // sudah tidak lagi di dinas itu.
        $aktif = (string) $request->query('aktif', '1');
        if ($aktif === '1') {
            $query->where('aktif', true);
        } elseif ($aktif === '0') {
            $query->where('aktif', false);
        }

        $perPage = min(100, max(1, (int) $request->query('per_page', 50)));

        $memberships = $query->orderBy('id')->paginate($perPage);

        return response()->json([
            'data' => MembershipResource::collection($memberships->items())->resolve(),
            'meta' => [
                'total' => $memberships->total(),
                'per_page' => $memberships->perPage(),
                'current_page' => $memberships->currentPage(),
                'last_page' => $memberships->lastPage(),
            ],
        ]);
    }
}
