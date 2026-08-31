<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\SearchFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

class LogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->hasRole('admin'), 403);

        $query = Activity::query()
            ->with(['causer.personalData', 'subject']);

        // Search
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

        // Filter by tag (log_name)
        if ($tag = $request->string('tag')->toString()) {
            $query->where('log_name', $tag);
        }

        // Filter by event
        if ($event = $request->string('event')->toString()) {
            $query->where('event', $event);
        }

        // Date range filter
        $dateRange = $request->string('date_range')->toString();
        if ($dateRange === 'today') {
            $query->whereDate('created_at', today());
        } elseif ($dateRange === 'week') {
            $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($dateRange === 'month') {
            $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
        }

        // Sort
        $sortField = $request->string('sort', 'created_at')->toString();
        $sortDirection = $request->string('direction', 'desc')->toString();
        $allowedSorts = ['created_at', 'event', 'log_name', 'description'];
        if (in_array($sortField, $allowedSorts, true)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        }

        $perPage = max(5, min((int) $request->integer('per_page', 15), 50));
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn ($log) => [
                'id' => $log->id,
                'log_name' => $log->log_name,
                'description' => $log->description,
                'event' => $log->event,
                'subject_type' => class_basename($log->subject_type),
                'subject_id' => $log->subject_id,
                'causer' => $log->causer ? [
                    'id' => $log->causer->id,
                    'name' => trim(($log->causer->personalData->first_name ?? '').' '.($log->causer->personalData->last_name ?? '')) ?: $log->causer->email,
                    'email' => $log->causer->email,
                ] : null,
                'properties' => $log->properties,
                'created_at' => $log->created_at->toISOString(),
            ]),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'filters' => [
                'tags' => Activity::query()->select('log_name')->distinct()->whereNotNull('log_name')->orderBy('log_name')->pluck('log_name'),
                'events' => Activity::query()->select('event')->distinct()->whereNotNull('event')->orderBy('event')->pluck('event'),
            ],
        ]);
    }
}
