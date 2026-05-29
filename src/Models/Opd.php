<?php

namespace Nawasara\Registry\Models;

use Illuminate\Database\Eloquent\Model;
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
        'address',
        'phone',
        'email',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'name', 'address', 'phone', 'email'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => "OPD {$eventName}");
    }

    public function pics(): HasMany
    {
        return $this->hasMany(Pic::class, 'opd_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'opd_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'opd_id');
    }

    public function primaryPic()
    {
        return $this->pics()->where('is_primary', true)->first();
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
