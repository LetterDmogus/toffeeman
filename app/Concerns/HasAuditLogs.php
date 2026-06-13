<?php

namespace App\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait HasAuditLogs
{
    /**
     * Boot the trait and register Eloquent model event listeners.
     */
    public static function bootHasAuditLogs(): void
    {
        static::created(function (Model $model) {
            $model->recordAuditLog('create', null, $model->getAttributes());
        });

        static::updating(function (Model $model) {
            $dirty = $model->getDirty();
            $old = [];
            $new = [];

            foreach ($dirty as $key => $value) {
                $old[$key] = $model->getOriginal($key);
                $new[$key] = $value;
            }

            // Exclude updated_at from being logged if it's the only change
            unset($old['updated_at'], $new['updated_at']);

            if (! empty($new)) {
                $model->recordAuditLog('update', $old, $new);
            }
        });

        static::deleted(function (Model $model) {
            $model->recordAuditLog('delete', $model->getAttributes(), null);
        });

        // Register restore listener if the model uses SoftDeletes
        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model) {
                $model->recordAuditLog('restore', null, $model->getAttributes());
            });
        }
    }

    /**
     * Save an audit log entry.
     */
    protected function recordAuditLog(string $action, ?array $oldValues, ?array $newValues): void
    {
        try {
            // Strip sensitive fields like password
            $sensitiveFields = ['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'];

            if ($oldValues) {
                $oldValues = array_diff_key($oldValues, array_flip($sensitiveFields));
            }
            if ($newValues) {
                $newValues = array_diff_key($newValues, array_flip($sensitiveFields));
            }

            AuditLog::create([
                'user_id' => Auth::id(),
                'auditable_type' => get_class($this),
                'auditable_id' => $this->getKey(),
                'action' => $action,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ]);
        } catch (\Throwable $e) {
            // Silently fail or log to log file to prevent breaking main transaction
            logger()->error('Failed to record audit log: '.$e->getMessage());
        }
    }
}
