<?php

namespace Nawasara\Registry\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Pic extends Model
{
    use LogsActivity;

    protected $table = 'nawasara_registry_pic';

    protected $fillable = [
        'opd_id',
        'name',
        'position',
        'phone',
        'email',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'position', 'phone', 'email', 'is_primary'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => "PIC {$eventName}");
    }

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class, 'opd_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'pic_id');
    }
}
