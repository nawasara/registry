<?php

namespace Nawasara\Registry\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pengajuan petugas baru oleh admin OPD, menunggu keputusan admin Registry.
 * Lihat MembershipRequestService.
 */
class MembershipRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $table = 'nawasara_registry_membership_requests';

    protected $fillable = [
        'opd_id', 'nip', 'name', 'role', 'note', 'requested_by',
        'status', 'decided_by', 'decided_at', 'decision_note',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class, 'opd_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
