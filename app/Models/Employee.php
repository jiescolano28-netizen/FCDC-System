<?php

namespace App\Models;

use App\Notifications\EmployeePasswordReset;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class Employee extends Authenticatable
{
    use HasRoles, LogsActivity, Notifiable, SoftDeletes;

    protected $table = 'employees';

    protected $fillable = [
        'username',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new EmployeePasswordReset($token));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['username', 'email'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
