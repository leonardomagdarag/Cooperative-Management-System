<?php

namespace App\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    public function log(
        string $event,
        string $description,
        string $tag = 'system',
        ?Model $subject = null,
        Model|Authenticatable|null $causer = null,
        array $properties = []
    ): void {
        if (! config('activitylog.enabled', true)) {
            return;
        }

        $request = request();
        $metadata = [
            'route' => $request?->route()?->getName(),
            'method' => $request?->method(),
            'path' => $request?->path(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ];

        $activity = activity($tag)
            ->event($event)
            ->withProperties(array_merge($metadata, $properties));

        $resolvedCauser = $causer ?: Auth::user();
        if ($resolvedCauser instanceof Model) {
            $activity->causedBy($resolvedCauser);
        }

        if ($subject) {
            $activity->performedOn($subject);
        }

        $activity->log($description);
    }
}
