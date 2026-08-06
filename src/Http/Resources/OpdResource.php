<?php

namespace Nawasara\Registry\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nawasara\Registry\Models\Opd;

/**
 * Transformer OPD (organisasi perangkat daerah) untuk public API.
 *
 * Seluruh field di sini adalah informasi dinas resmi — kode, nama, alamat
 * kantor, telepon dan email dinas — yang memang dimaksudkan untuk dihubungi
 * publik. Tidak ada yang perlu ditahan.
 *
 * @mixin Opd
 */
class OpdResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // `code` adalah identifier stabil lintas sistem; pakai ini untuk
            // menautkan data, bukan `id` yang hanya bermakna di dalam Nawasara.
            'code' => $this->code,
            'name' => $this->name,

            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,

            'assets_count' => $this->whenCounted('assets'),
            'members_count' => $this->whenCounted('memberships'),
        ];
    }
}
