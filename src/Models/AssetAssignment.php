<?php

namespace Nawasara\Registry\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One person responsible for one asset, as primary or backup PIC.
 *
 * The primary row mirrors Asset::pj_user_id and is written by Asset::booted,
 * never directly. Backups are added and removed through
 * AssetAssignmentService.
 */
class AssetAssignment extends Model
{
    use LogsActivity;

    public const ROLE_PRIMARY = 'primary';

    public const ROLE_BACKUP = 'backup';

    public const ROLES = [self::ROLE_PRIMARY, self::ROLE_BACKUP];

    protected $table = 'nawasara_registry_asset_assignments';

    protected $fillable = ['asset_id', 'user_id', 'role', 'assigned_by'];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'user_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['asset_id', 'user_id', 'role'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $event) => "Penugasan PIC {$event}");
    }
}
