<?php

namespace Nawasara\Registry\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A division inside an OPD: "BIDANG APLIKASI DAN INFORMATIKA", "SEKRETARIAT".
 *
 * Not a school. See the units migration for why those are OPD rows instead.
 */
class Unit extends Model
{
    use LogsActivity;

    protected $table = 'nawasara_registry_units';

    protected $fillable = [
        'opd_id',
        'name',
        'simas_name',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['opd_id', 'name', 'simas_name'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => "Unit {$eventName}");
    }

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class, 'opd_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'unit_id');
    }
}
