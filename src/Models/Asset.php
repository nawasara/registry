<?php

namespace Nawasara\Registry\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Asset extends Model
{
    use LogsActivity;

    protected $table = 'nawasara_registry_assets';

    protected $fillable = [
        'opd_id',
        'pic_id',
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
            ->logOnly(['type', 'identifier', 'status', 'opd_id', 'pic_id'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => "Asset {$eventName}");
    }

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class, 'opd_id');
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(Pic::class, 'pic_id');
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

    public function scopeByType($query, ?string $type)
    {
        return $type ? $query->where('type', $type) : $query;
    }

    public function scopeByStatus($query, ?string $status)
    {
        return $status ? $query->where('status', $status) : $query;
    }

    public function scopeByOpd($query, $opdId)
    {
        return $opdId ? $query->where('opd_id', $opdId) : $query;
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
