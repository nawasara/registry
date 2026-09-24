<?php

namespace Nawasara\Registry\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Opd extends Model
{
    use LogsActivity;

    protected $table = 'nawasara_registry_opd';

    protected $fillable = [
        'code',
        'name',
        'simas_name',
        'parent_id',
        'address',
        'phone',
        'email',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'name', 'simas_name', 'parent_id', 'address', 'phone', 'email'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => "OPD {$eventName}");
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'opd_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'opd_id');
    }

    /** Users belonging to this OPD (via membership) — candidates for asset PJ. */
    public function members(): HasMany
    {
        return $this->hasMany(Membership::class, 'opd_id')->where('aktif', true);
    }

    /** Divisions inside this OPD ("BIDANG ...", "SEKRETARIAT"). */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class, 'opd_id');
    }

    /**
     * The OPD this one sits under, if any. Schools and puskesmas are OPD rows
     * with a parent; a dinas has none.
     *
     * Note this does NOT widen visibility: ScopedToOpd still matches one
     * opd_id exactly, so a parent does not see its children's data.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('code', 'like', "%{$term}%")
              ->orWhere('name', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%");
        });
    }
}
