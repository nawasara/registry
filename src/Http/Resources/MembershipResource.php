<?php

namespace Nawasara\Registry\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nawasara\Keycloak\Support\KeycloakProfile;
use Nawasara\Registry\Models\Membership;

/**
 * Transformer keanggotaan OPD untuk public API — memetakan orang ke dinas
 * tempatnya bertugas.
 *
 * Identitas orang diambil dari direktori Keycloak, bukan dari kolom `users`
 * lokal, supaya nama yang tampil sama dengan yang dipakai seluruh aplikasi.
 * Dibatasi ke nama dan NIP saja: email dan nomor WhatsApp sudah tersedia
 * lewat API direktori pegawai bagi yang memang butuh, dan endpoint ini
 * tentang struktur organisasi — bukan buku alamat.
 *
 * `user_id` lokal tidak diekspos; yang stabil lintas sistem adalah
 * `keycloak_id`, dan itu yang dipakai untuk menautkan orang.
 *
 * @mixin Membership
 */
class MembershipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->user ? KeycloakProfile::for($this->user) : null;

        return [
            // Identifier orang yang bertahan meski username berubah.
            'keycloak_id' => $this->user?->keycloak_id,
            'name' => $profile?->name ?? $this->user?->name,
            'nip' => $profile?->nip,

            'opd' => $this->whenLoaded('opd', fn () => $this->opd ? [
                'code' => $this->opd->code,
                'name' => $this->opd->name,
            ] : null),

            'aktif' => (bool) $this->aktif,
        ];
    }
}
