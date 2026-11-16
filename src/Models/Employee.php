<?php

namespace Nawasara\Registry\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Employment data for one user, sourced from SIMAS Hebat.
 *
 * Refreshed at login (at most once per interval), from the "Perbarui dari
 * SIMAS" action, and when a PIC is assigned. Never on a schedule: SIMAS Hebat
 * has no "list employees" endpoint and its firewall blocks bursts. All writes
 * go through EmployeeSyncService.
 */
class Employee extends Model
{
    use LogsActivity;

    protected $table = 'nawasara_registry_employees';

    protected $fillable = [
        'user_id',
        'nip',
        'nama_jabatan',
        'jenis_jabatan',
        'jenis_kepegawaian',
        'unit_id',
        'simas_unit_kerja',
        'simas_synced_at',
        'simas_nama',
        'simas_skpd',
        'simas_unit',
        'simas_opd_id',
        'simas_status',
        'simas_error',
        'simas_source',
        'simas_checked_at',
    ];

    protected $casts = [
        'simas_synced_at' => 'datetime',
        'simas_checked_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'nip', 'nama_jabatan', 'jenis_jabatan',
                'jenis_kepegawaian', 'unit_id', 'simas_unit_kerja',
                'simas_skpd', 'simas_unit', 'simas_opd_id',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Data kepegawaian {$eventName}");
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    /** The registry OPD that SIMAS `skpd` matched; null when not recognised. */
    public function simasOpd(): BelongsTo
    {
        return $this->belongsTo(Opd::class, 'simas_opd_id');
    }

    /**
     * Structural officials (kepala bidang and up) versus functional staff.
     * It is `jenis_jabatan` that marks this, not the job title: a title like
     * "Pranata Komputer ..." says what someone does, not whether they head
     * anything.
     */
    public function isStruktural(): bool
    {
        return str_contains(mb_strtolower((string) $this->jenis_jabatan), 'struktural');
    }

    /** Whether the SIMAS data is older than the given number of days. */
    public function isStale(int $days = 180): bool
    {
        return $this->simas_synced_at === null
            || $this->simas_synced_at->lt(now()->subDays($days));
    }
}
