<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Todo extends Model
{
    use HasFactory;

    // Tambahkan evidence_path ke fillable
    protected $fillable = [
        'title',
        'description',
        'priority',
        'user_id',
        'status',
        'due_date',
        'scheduled_date',
        'target_start_at',
        'target_end_at',
        'started_at',
        'submitted_at',
        'total_work_time',
        'total_work_time_formatted',
        'evidence_path',  // Tambahkan ini
        'evidence_paths', // Tambahkan ini untuk multiple files
        'checked_by',
        'notes',
        'rating'
    ];

    // Pastikan kolom yang di-append
    protected $appends = [
        'formatted_created_at',
        'formatted_updated_at',
        'formatted_started_at',
        'formatted_submitted_at',
        'formatted_due_date',
        'day_of_due_date'
    ];

    // Pastikan kolom tanggal di-cast
    protected $dates = [
        'created_at',
        'updated_at',
        'due_date',
        'scheduled_date',
        'started_at',
        'submitted_at'
    ];

    // Cast untuk evidence_paths sebagai array
    protected $casts = [
        'evidence_paths' => 'array',
    ];

    // Relasi dengan user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relasi: peringatan (warning points) per todo
    public function warnings()
    {
        return $this->hasMany(TodoWarning::class);
    }

    // Accessor untuk created_at
    public function getFormattedCreatedAtAttribute()
    {
        return $this->created_at ? Carbon::parse($this->created_at)->format('Y-m-d H:i:s') : null;
    }

    // Accessor untuk updated_at
    public function getFormattedUpdatedAtAttribute()
    {
        return $this->updated_at ? Carbon::parse($this->updated_at)->format('Y-m-d H:i:s') : null;
    }

    // Accessor untuk started_at
    public function getFormattedStartedAtAttribute()
    {
        return $this->started_at ? Carbon::parse($this->started_at)->format('Y-m-d H:i:s') : null;
    }

    // Accessor untuk submitted_at
    public function getFormattedSubmittedAtAttribute()
    {
        return $this->submitted_at ? Carbon::parse($this->submitted_at)->format('Y-m-d H:i:s') : null;
    }

    // Accessor untuk due_date dengan nama hari dalam Bahasa Indonesia
    public function getDayOfDueDateAttribute()
    {
        if (!$this->due_date) return null;

        $days = [
            'Sunday' => 'Minggu',
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu'
        ];

        $carbonDate = Carbon::parse($this->due_date);
        return $days[$carbonDate->englishDayOfWeek];
    }

    // Accessor untuk due_date dengan format lengkap
    public function getFormattedDueDateAttribute()
    {
        if (!$this->due_date) return null;

        $carbonDate = Carbon::parse($this->due_date);
        $dayName = $this->day_of_due_date;

        return "{$dayName}, {$carbonDate->format('d F Y')}";
    }
}
