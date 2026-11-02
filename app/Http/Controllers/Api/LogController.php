<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SearchFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

class LogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user && ($user->can('logs.view') || $user->hasRole('admin')), 403);

        $query = $this->filteredQuery($request);

        $perPage = max(5, min((int) $request->integer('per_page', 15), 50));
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn ($log) => $this->logPayload($log)),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'filters' => [
                'tags' => Activity::query()->select('log_name')->distinct()->whereNotNull('log_name')->orderBy('log_name')->pluck('log_name'),
                'events' => Activity::query()->select('event')->distinct()->whereNotNull('event')->orderBy('event')->pluck('event'),
                'users' => Activity::query()->select('causer_id', 'causer_type')
                    ->distinct()
                    ->whereNotNull('causer_id')
                    ->with('causer.personalData')
                    ->get()
                    ->map(function ($log) {
                        if (! $log->causer) {
                            return null;
                        }

                        return [
                            'id' => $log->causer_id,
                            'name' => trim(($log->causer->personalData->first_name ?? '').' '.($log->causer->personalData->last_name ?? '')) ?: $log->causer->email,
                        ];
                    })->filter()->unique('id')->values(),
            ],
        ]);
    }

    private function logPayload(Activity $log): array
    {
        $properties = $log->properties;
        $description = $log->description;

        if ($log->log_name === 'email' && $log->event === 'email.failed') {
            $properties = collect([
                'to' => $log->properties?->get('to'),
                'reason' => $this->failureSummary((string) ($log->properties?->get('error') ?? $description)),
            ])->filter(fn ($value) => filled($value));
            $description = 'The email could not be sent. '.$properties->get('reason');
        } elseif ($log->log_name === 'backup' && $log->event === 'backup.restore_failed') {
            $properties = collect([
                'source' => $log->properties?->get('source'),
                'storage_tier' => $log->properties?->get('storage_tier'),
                'scope' => $log->properties?->get('scope'),
                'reason' => $this->failureSummary((string) ($log->properties?->get('error') ?? $description)),
            ])->filter(fn ($value) => filled($value));
            $description = 'The backup could not be restored. '.$properties->get('reason');
        } elseif ($log->log_name === 'backup' && $log->event === 'backup.restored') {
            $properties = collect([
                'source' => $log->properties?->get('source'),
                'storage_tier' => $log->properties?->get('storage_tier'),
                'scope' => $log->properties?->get('scope'),
                'file' => $log->properties?->get('file'),
                'files_restored' => $log->properties?->get('files_restored'),
            ])->filter(fn ($value) => filled($value));
            $description = 'The selected backup was restored successfully.';
        } elseif ($log->log_name === 'financial' && $log->event === 'financial.interest.failed') {
            $accountType = (string) $log->properties?->get('account_type', 'cooperative');
            $error = str_contains($description, ':') ? trim(substr($description, strpos($description, ':') + 1)) : $description;
            $properties = collect([
                'account_type' => $accountType,
                'reason' => $this->failureSummary($error),
            ]);
            $description = 'Interest could not be processed for the '.str_replace('_', ' ', $accountType).' account. '.$properties->get('reason');
        } elseif (filled($log->properties?->get('error')) && is_scalar($log->properties->get('error'))) {
            $rawError = (string) $log->properties->get('error');
            $reason = $this->failureSummary($rawError);
            $properties = $log->properties->except('error')->put('reason', $reason);
            if (str_contains($description, $rawError)) {
                $description = str_replace($rawError, $reason, $description);
            }
        }

        return [
            'id' => $log->id,
            'log_name' => $log->log_name,
            'description' => $description,
            'event' => $log->event,
            'subject_type' => class_basename($log->subject_type),
            'subject_id' => $log->subject_id,
            'subject_name' => $log->subject ? (
                $log->subject instanceof User
                    ? trim(($log->subject->personalData->first_name ?? '').' '.($log->subject->personalData->last_name ?? '')) ?: $log->subject->email
                    : (method_exists($log->subject, 'getName') ? $log->subject->getName() : ($log->subject->name ?? $log->subject->title ?? $log->subject->email ?? null))
            ) : null,
            'causer' => $log->causer ? [
                'id' => $log->causer->id,
                'name' => trim(($log->causer->personalData->first_name ?? '').' '.($log->causer->personalData->last_name ?? '')) ?: $log->causer->email,
                'email' => $log->causer->email,
            ] : null,
            'properties' => $properties,
            'created_at' => $log->created_at->toISOString(),
        ];
    }

    private function failureSummary(string $error): string
    {
        $message = strtolower($error);

        if (str_contains($message, 'htmlspecialchars') || str_contains($message, 'illuminate\\mail\\message')) {
            return 'The email template could not display one of the message details. Ask technical support to review the email template.';
        }

        if (preg_match('/\b(535|authentication failed|invalid credentials|username and password)\b/', $message)) {
            return 'The email provider rejected its sign-in details. Check the email account settings.';
        }

        if (preg_match('/\b(connection refused|could not connect|connection timed out|network is unreachable)\b/', $message)) {
            return 'The system could not connect to the required service. Check the connection or ask technical support to review the server logs.';
        }

        if (str_contains($message, 'sqlstate') || str_contains($message, 'database')) {
            return 'The system could not save or read the required information. Ask technical support to review the server logs.';
        }

        if (str_contains($message, 'class') && str_contains($message, 'not found')) {
            return 'A required system component could not be found. Ask technical support to review the server logs.';
        }

        return 'An unexpected system problem occurred. Ask technical support to review the server logs.';
    }

    public function export(Request $request)
    {
        $user = Auth::user();
        abort_unless($user && ($user->can('logs.export') || $user->hasRole('admin')), 403);

        $filename = 'audit-logs-'.now()->format('Y-m-d').'.csv';
        $logs = $this->filteredQuery($request)->lazy(500);

        return response()->streamDownload(function () use ($logs) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM helps spreadsheet applications recognize the encoding.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Date and Time', 'Area', 'Action', 'Description', 'Related Record Type', 'Related Record ID', 'Related Record', 'Performed By', 'Actor Email', 'Details']);

            foreach ($logs as $log) {
                $payload = $this->logPayload($log);
                $subject = $log->subject;
                $subjectName = $subject ? (
                    $subject instanceof User
                        ? trim(($subject->personalData->first_name ?? '').' '.($subject->personalData->last_name ?? '')) ?: $subject->email
                        : (method_exists($subject, 'getName') ? $subject->getName() : ($subject->name ?? $subject->title ?? $subject->email ?? null))
                ) : null;
                $causer = $log->causer;
                $causerName = $causer
                    ? trim(($causer->personalData->first_name ?? '').' '.($causer->personalData->last_name ?? '')) ?: $causer->email
                    : 'System';
                $properties = $payload['properties'] instanceof Collection
                    ? $payload['properties']->toArray()
                    : ($payload['properties']?->toArray() ?? []);

                fputcsv($out, array_map($this->csvSafe(...), [
                    $log->created_at?->toISOString(),
                    $log->log_name,
                    $log->event,
                    $payload['description'],
                    class_basename($log->subject_type),
                    $log->subject_id,
                    $subjectName,
                    $causerName,
                    $causer?->email,
                    json_encode($properties, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = Activity::query()
            ->with(['causer.personalData'])
            ->with(['subject' => function (MorphTo $morphTo) {
                $morphTo->morphWith([
                    User::class => ['personalData'],
                ]);
            }]);

        if ($search = SearchFilter::escapeLike($request->string('search')->toString())) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('event', 'like', "%{$search}%")
                    ->orWhere('log_name', 'like', "%{$search}%")
                    ->orWhere('subject_type', 'like', "%{$search}%")
                    ->orWhereHas('causer', function ($causerQ) use ($search) {
                        $causerQ->where('email', 'like', "%{$search}%")
                            ->orWhereHas('personalData', function ($personalQ) use ($search) {
                                $personalQ->where('first_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%");
                            });
                    });
            });
        }

        if ($tag = $request->string('tag')->toString()) {
            $query->where('log_name', $tag);
        }

        if ($event = $request->string('event')->toString()) {
            $query->where('event', $event);
        }

        if ($userId = $request->integer('user_id')) {
            $query->where('causer_id', $userId)
                ->where('causer_type', User::class);
        }

        $dateRange = $request->string('date_range')->toString();
        if ($dateRange === 'today') {
            $query->whereDate('created_at', today());
        } elseif ($dateRange === 'week') {
            $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($dateRange === 'month') {
            $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
        }

        if ($from = $request->string('from')->toString()) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->string('to')->toString()) {
            $query->whereDate('created_at', '<=', $to);
        }

        $sortField = $request->string('sort', 'created_at')->toString();
        $sortDirection = $request->string('direction', 'desc')->toString();
        $allowedSorts = ['created_at', 'event', 'log_name', 'description'];
        if (in_array($sortField, $allowedSorts, true)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        }

        return $query;
    }

    private function csvSafe(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $value = (string) ($value ?? '');

        return preg_match('/^[\s\x00-\x1F]*[=+\-@]/u', $value) === 1 ? "'{$value}" : $value;
    }
}
