<?php

namespace Nawasara\Registry\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nawasara\Keycloak\Support\KeycloakProfile;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Asset extends Model
{
    use LogsActivity;

    protected $table = 'nawasara_registry_assets';

    protected $fillable = [
        'opd_id',
        'pj_user_id',
        'type',
        'identifier',
        'package_ref',
        'external_id',
        'status',
        'notes',
        'ticket_ref',
        'registered_at',
        'discovered_at',
    ];

    protected $casts = [
        'registered_at' => 'date',
        'discovered_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['type', 'identifier', 'status', 'opd_id', 'pj_user_id'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => "Asset {$eventName}");
    }

    /**
     * Keep the primary assignment row in step with `pj_user_id`, whoever
     * writes it: PicAssignmentService, the "clear PIC" path of the asset
     * form, a seeder, or another package. Hooking the model rather than the
     * service is deliberate: a rule that lives in one caller is skipped by
     * every other caller.
     */
    protected static function booted(): void
    {
        static::saved(function (self $asset) {
            if (! $asset->wasRecentlyCreated && ! $asset->wasChanged('pj_user_id')) {
                return;
            }

            AssetAssignment::query()
                ->where('asset_id', $asset->id)
                ->where('role', AssetAssignment::ROLE_PRIMARY)
                ->where('user_id', '!=', $asset->pj_user_id ?? 0)
                ->delete();

            if ($asset->pj_user_id) {
                // Promoting a backup to primary replaces their backup row.
                AssetAssignment::updateOrCreate(
                    ['asset_id' => $asset->id, 'user_id' => $asset->pj_user_id],
                    ['role' => AssetAssignment::ROLE_PRIMARY, 'assigned_by' => auth()->id()],
                );
            }
        });
    }

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class, 'opd_id');
    }

    /** Every PIC of this asset, primary and backups. */
    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class, 'asset_id');
    }

    /**
     * Backup PICs only. The primary lives in `pj_user_id`, so a list that
     * reads that column alone shows "-" for an asset whose only PIC is a
     * backup.
     */
    public function backupAssignments(): HasMany
    {
        return $this->assignments()->where('role', AssetAssignment::ROLE_BACKUP);
    }

    /** The person responsible for this asset — a Laravel user. */
    public function penanggungJawab(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'pj_user_id');
    }

    /** Contact profile (name/NIP/WhatsApp/email) sourced from Keycloak. */
    public function pjProfile(): KeycloakProfile
    {
        return KeycloakProfile::for($this->penanggungJawab);
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('identifier', 'like', "%{$term}%")
              ->orWhere('notes', 'like', "%{$term}%")
              ->orWhereHas('opd', fn ($q) => $q->where('name', 'like', "%{$term}%"));
        });
    }

    /**
     * Polymorphic asset-type filter. Accepts string for single match or
     * array<string> for multi-select (filter-panel uses arrays). Empty
     * value (null, '', []) is a no-op so callers can pass user input
     * straight through.
     *
     * @param  string|array<int,string>|null  $type
     */
    public function scopeByType($query, string|array|null $type)
    {
        if (empty($type)) {
            return $query;
        }
        return is_array($type)
            ? $query->whereIn('type', $type)
            : $query->where('type', $type);
    }

    /**
     * Polymorphic asset-status filter. Same shape as scopeByType.
     *
     * @param  string|array<int,string>|null  $status
     */
    public function scopeByStatus($query, string|array|null $status)
    {
        if (empty($status)) {
            return $query;
        }
        return is_array($status)
            ? $query->whereIn('status', $status)
            : $query->where('status', $status);
    }

    /**
     * Polymorphic OPD filter. Accepts int/string id, array of ids, or null.
     *
     * @param  int|string|array<int, int|string>|null  $opdId
     */
    public function scopeByOpd($query, int|string|array|null $opdId)
    {
        if (empty($opdId)) {
            return $query;
        }
        return is_array($opdId)
            ? $query->whereIn('opd_id', $opdId)
            : $query->where('opd_id', $opdId);
    }

    public function getTypeLabelAttribute(): string
    {
        return config("nawasara-registry.asset_types.{$this->type}", $this->type);
    }

    public function getStatusLabelAttribute(): string
    {
        return config("nawasara-registry.asset_statuses.{$this->status}", $this->status);
    }
}
