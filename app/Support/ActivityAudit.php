<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

class ActivityAudit
{
    public static function recordChange(
        Model $subject,
        string $description,
        array $attributes,
        array $old = [],
    ): void {
        if ($attributes === [] && $old === []) {
            return;
        }

        activity()
            ->performedOn($subject)
            ->withProperties([
                'attributes' => $attributes,
                'old' => $old,
            ])
            ->log($description);
    }
}
