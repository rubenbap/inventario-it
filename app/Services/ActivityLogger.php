<?php

namespace App\Services;

use App\Models\ActionLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    public static function log(
        string $action,
        Model $item,
        ?Model $target = null,
        ?string $note = null,
        ?array $meta = null,
    ): ActionLog {
        return ActionLog::create([
            'created_by' => Auth::id(),
            'action_type' => $action,
            'item_type' => $item::class,
            'item_id' => $item->getKey(),
            'target_type' => $target ? $target::class : null,
            'target_id' => $target?->getKey(),
            'company_id' => $item->getAttribute('company_id'),
            'note' => $note,
            'log_meta' => $meta ?: null,
        ]);
    }

    public static function diff(Model $model): array
    {
        $ignore = ['updated_at', 'created_at', 'custom_values'];
        $changes = [];

        foreach ($model->getChanges() as $key => $new) {
            if (in_array($key, $ignore, true)) {
                continue;
            }
            $changes[$key] = ['old' => $model->getOriginal($key), 'new' => $new];
        }

        return $changes;
    }
}
