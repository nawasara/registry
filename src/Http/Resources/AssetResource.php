<?php

namespace Nawasara\Registry\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nawasara\Registry\Models\Asset;

/**
 * Transformer aset registry untuk public API.
 *
 * Aset di sini adalah domain, subdomain, dan akun layanan milik pemerintah —
 * `identifier` berisi nama domain yang memang sudah publik dan bisa dilihat
 * siapa pun lewat DNS.
 *
 * Yang DIBLOK:
 *   - `notes` — catatan operator, bebas isi. Karena tidak ada aturan apa yang
 *     boleh ditulis di sana, tidak ada jaminan isinya aman keluar.
 *   - `ticket_ref` — rujukan tiket internal, tidak bermakna di luar dan
 *     membocorkan cara kerja tim.
 *   - `external_id` — id di sistem pihak ketiga (Cloudflare, WHM). Berguna
 *     hanya bagi yang punya akses ke sistem itu.
 *
 * @mixin Asset
 */
class AssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'type' => $this->type,
            'type_label' => $this->type_label,

            // Nama domain / subdomain / akun. Publik.
            'identifier' => $this->identifier,

            'status' => $this->status,
            'status_label' => $this->status_label,

            // Package Nawasara yang mengelola aset ini (cloudflare, whm, …).
            // Membantu konsumen tahu dari mana data ini berasal.
            'source' => $this->package_ref,

            'opd' => $this->whenLoaded('opd', fn () => $this->opd ? [
                'code' => $this->opd->code,
                'name' => $this->opd->name,
            ] : null),

            // Penanggung jawab — nama dan NIP dari direktori Keycloak.
            // Nomor WhatsApp sengaja tidak ikut: itu nomor pribadi.
            'penanggung_jawab' => $this->when(
                $this->pj_user_id !== null,
                fn () => [
                    'name' => $this->pjProfile()->name,
                    'nip' => $this->pjProfile()->nip,
                ],
            ),

            'registered_at' => $this->registered_at?->toIso8601String(),
            'discovered_at' => $this->discovered_at?->toIso8601String(),
        ];
    }
}
