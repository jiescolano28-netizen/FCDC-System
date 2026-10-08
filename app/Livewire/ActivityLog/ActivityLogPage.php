<?php

namespace App\Livewire\ActivityLog;

use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

class ActivityLogPage extends Component
{
    use WithPagination;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('activity-log.view'), 403);
    }

    public function render()
    {
        return view('livewire.activity-log.activity-log-page', [
            'activities' => Activity::query()
                ->with(['causer', 'subject'])
                ->orderByDesc('id')
                ->paginate(25),
        ])->layout('layouts.app', ['title' => 'Activity log']);
    }
}
