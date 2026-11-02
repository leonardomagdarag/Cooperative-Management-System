<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TicketController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Display a listing of tickets.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        Gate::authorize('viewAny', Ticket::class);

        $isSupport = $user->isSupportStaff();
        $requestedScope = $request->string('scope', 'all')->toString();
        $scope = $isSupport ? $requestedScope : 'mine';

        $baseQuery = Ticket::query();

        if ($scope === 'mine') {
            $baseQuery->where('user_id', $user->id);
        }

        // Summary counts based on the active scope
        $countsQuery = clone $baseQuery;
        $statusCounts = (clone $countsQuery)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed,
                SUM(CASE WHEN status IN ('open', 'in_progress') THEN 1 ELSE 0 END) as active_total
            ")
            ->first();

        // Apply filters
        $query = clone $baseQuery;

        if ($request->filled('status_group')) {
            $group = $request->string('status_group')->toString();
            if ($group === 'active') {
                $query->whereIn('status', ['open', 'in_progress']);
            } elseif ($group === 'closed') {
                $query->whereIn('status', ['resolved', 'closed']);
            }
        } elseif ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->string('priority')->toString());
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category')->toString());
        }

        if ($request->filled('search')) {
            $search = trim($request->string('search')->toString());
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQ) use ($search) {
                        $userQ->where('email', 'like', "%{$search}%")
                            ->orWhereHas('personalData', function ($pdQ) use ($search) {
                                $pdQ->where('first_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%");
                            });
                    });
            });
        }

        if ($from = $request->string('from')->toString()) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->string('to')->toString()) {
            $query->whereDate('created_at', '<=', $to);
        }

        $sort = $request->string('sort', 'last_reply_at')->toString();
        $direction = $request->string('direction', 'desc')->toString() === 'asc' ? 'asc' : 'desc';
        if (in_array($sort, ['requester', 'assignee'], true)) {
            $column = $sort === 'requester' ? 'user_id' : 'assigned_to';
            $query->orderBy(User::select('personal_data.first_name')->join('personal_data', 'personal_data.id', '=', 'users.personal_data_id')
                ->whereColumn('users.id', 'tickets.'.$column)->limit(1), $direction);
        } elseif (in_array($sort, ['ticket_number', 'subject', 'status', 'priority', 'category', 'created_at', 'last_reply_at'], true)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->latest('last_reply_at');
        }

        $query->orderBy('tickets.id');

        $perPage = max(5, min((int) $request->integer('per_page', 10), 50));
        $paginator = $query->with([
            'user.personalData',
            'user.roles',
            'assignee.personalData',
            'messages' => fn ($q) => $q->latest()->limit(1),
        ])
            ->paginate($perPage);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (Ticket $t) => $this->serializeTicketSummary($t, $user)),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'counts' => [
                'total' => (int) ($statusCounts->total ?? 0),
                'open' => (int) ($statusCounts->open ?? 0),
                'in_progress' => (int) ($statusCounts->in_progress ?? 0),
                'resolved' => (int) ($statusCounts->resolved ?? 0),
                'closed' => (int) ($statusCounts->closed ?? 0),
                'active_total' => (int) ($statusCounts->active_total ?? 0),
            ],
            'meta' => [
                'is_support' => $isSupport,
                'scope' => $scope,
            ],
        ]);
    }

    /**
     * Store a newly created ticket.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        Gate::authorize('create', Ticket::class);

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'category' => 'required|string|max:50',
            'priority' => 'required|in:low,medium,high,urgent',
            'message' => 'required|string|max:5000',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|image|max:5120',
        ]);

        $ticket = DB::transaction(function () use ($user, $validated, $request) {
            $ticket = Ticket::create([
                'user_id' => $user->id,
                'subject' => $validated['subject'],
                'category' => $validated['category'],
                'priority' => $validated['priority'],
                'status' => 'open',
                'last_reply_at' => now(),
            ]);

            $message = TicketMessage::create([
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'message' => $validated['message'],
            ]);

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('tickets/'.$ticket->ticket_number, 'public');
                    TicketAttachment::create([
                        'ticket_id' => $ticket->id,
                        'ticket_message_id' => $message->id,
                        'file_path' => $path,
                        'file_name' => $file->getClientOriginalName(),
                        'file_size' => $file->getSize(),
                        'mime_type' => $file->getClientMimeType() ?: 'image/jpeg',
                    ]);
                }
            }

            return $ticket;
        });

        try {
            $this->notificationService->notifyNewTicket($ticket);
        } catch (\Throwable $e) {
            // Log notification error gracefully
        }

        app(AuditLogService::class)->log('ticket.created', 'Support ticket submitted.', 'ticket', $ticket, $user, ['subject' => $ticket->subject, 'category' => $ticket->category, 'priority' => $ticket->priority]);

        return response()->json([
            'message' => 'Ticket created successfully.',
            'data' => $this->serializeTicketDetail($ticket->fresh(['user.personalData', 'user.roles', 'messages.attachments']), $user),
        ], 201);
    }

    /**
     * Display the specified ticket.
     */
    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);

        $ticket->load([
            'user.personalData',
            'user.roles',
            'assignee.personalData',
            'assignee.roles',
            'messages.user.personalData',
            'messages.user.roles',
            'messages.attachments',
        ]);

        return response()->json([
            'data' => $this->serializeTicketDetail($ticket, $request->user()),
            'meta' => [
                'can_reply' => Auth::user()->can('reply', $ticket),
                'can_update_status' => Auth::user()->can('updateStatus', $ticket),
                'can_assign' => Auth::user()->can('assign', $ticket),
            ],
        ]);
    }

    /**
     * Reply to a ticket.
     */
    public function reply(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('reply', $ticket);

        $user = $request->user();

        $validated = $request->validate([
            'message' => 'required|string|max:5000',
            'is_internal_note' => 'nullable|boolean',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|image|max:5120',
        ]);

        $isInternal = $user->isSupportStaff() && ! empty($validated['is_internal_note']);

        $message = DB::transaction(function () use ($ticket, $user, $validated, $isInternal, $request) {
            $msg = TicketMessage::create([
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'message' => $validated['message'],
                'is_internal_note' => $isInternal,
            ]);

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('tickets/'.$ticket->ticket_number, 'public');
                    TicketAttachment::create([
                        'ticket_id' => $ticket->id,
                        'ticket_message_id' => $msg->id,
                        'file_path' => $path,
                        'file_name' => $file->getClientOriginalName(),
                        'file_size' => $file->getSize(),
                        'mime_type' => $file->getClientMimeType() ?: 'image/jpeg',
                    ]);
                }
            }

            // Auto-transition status on active communication
            if (! $isInternal) {
                $ticket->last_reply_at = now();

                if ($user->isSupportStaff() && $ticket->status === 'open') {
                    $ticket->status = 'in_progress';
                } elseif (! $user->isSupportStaff() && $ticket->status === 'resolved') {
                    $ticket->status = 'in_progress';
                    $ticket->resolved_at = null;
                }

                $ticket->save();
            }

            return $msg;
        });

        if (! $isInternal) {
            try {
                $this->notificationService->notifyTicketReply($ticket, $message);
            } catch (\Throwable $e) {
                // Log notification error gracefully
            }
        }

        app(AuditLogService::class)->log('ticket.reply_added', 'Ticket reply added.', 'ticket', $ticket, $user, ['is_internal' => $isInternal]);

        return response()->json([
            'message' => 'Reply posted successfully.',
            'data' => $this->serializeMessage($message->fresh(['user.personalData', 'user.roles', 'attachments']), $user),
            'ticket_status' => $ticket->status,
        ], 201);
    }

    /**
     * Update ticket status.
     */
    public function updateStatus(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('updateStatus', $ticket);

        $validated = $request->validate([
            'status' => 'required|in:open,in_progress,resolved,closed',
        ]);

        $oldStatus = $ticket->status;
        $newStatus = $validated['status'];

        $ticket->status = $newStatus;

        if ($newStatus === 'resolved') {
            $ticket->resolved_at = now();
        } elseif ($newStatus === 'closed') {
            $ticket->closed_at = now();
        } elseif (in_array($newStatus, ['open', 'in_progress'], true)) {
            $ticket->resolved_at = null;
            $ticket->closed_at = null;
        }

        $ticket->save();

        try {
            $this->notificationService->notifyTicketStatusChanged($ticket, $oldStatus, $newStatus);
        } catch (\Throwable $e) {
            // Ignore
        }

        app(AuditLogService::class)->log('ticket.status_updated', "Ticket status updated to {$newStatus}.", 'ticket', $ticket, $request->user(), ['old_status' => $oldStatus, 'new_status' => $newStatus]);

        return response()->json([
            'message' => "Ticket status updated to {$newStatus}.",
            'data' => [
                'id' => $ticket->id,
                'status' => $ticket->status,
                'resolved_at' => $ticket->resolved_at?->toISOString(),
                'closed_at' => $ticket->closed_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Assign ticket to an officer.
     */
    public function assign(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('assign', $ticket);

        $validated = $request->validate([
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $ticket->assigned_to = $validated['assigned_to'] ?? null;
        $ticket->save();

        $assigneeName = $ticket->assignee ? $ticket->assignee->name : 'Unassigned';

        app(AuditLogService::class)->log('ticket.assigned', "Ticket assigned to {$assigneeName}.", 'ticket', $ticket, $request->user(), ['assigned_to' => $ticket->assigned_to]);

        return response()->json([
            'message' => "Ticket assigned to {$assigneeName}.",
            'data' => [
                'id' => $ticket->id,
                'assigned_to' => $ticket->assigned_to,
                'assignee_name' => $assigneeName,
            ],
        ]);
    }

    /**
     * Get list of support officers available for ticket assignment.
     */
    public function supportOfficers(): JsonResponse
    {
        $officers = User::role(['admin', 'chairman', 'manager', 'accountant'])
            ->with('personalData')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name ?: $u->email,
                'email' => $u->email,
                'role' => $u->primary_role,
            ]);

        return response()->json([
            'data' => $officers,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Serialization helpers
    // ─────────────────────────────────────────────────────────────────────────

    protected function serializeTicketSummary(Ticket $t, ?User $viewer = null): array
    {
        $viewer = $viewer ?? Auth::user();
        $canViewInternal = $viewer && $viewer->isSupportStaff();
        $requesterName = $t->user ? ($t->user->name ?: $t->user->email) : 'Unknown';
        $assigneeName = $t->assignee ? ($t->assignee->name ?: $t->assignee->email) : null;
        $latestMessage = $t->messages->first();

        $latestSnippet = null;
        if ($latestMessage && ($canViewInternal || ! (bool) $latestMessage->is_internal_note)) {
            $latestSnippet = substr($latestMessage->message, 0, 100);
        }

        return [
            'id' => $t->id,
            'ticket_number' => $t->ticket_number,
            'subject' => $t->subject,
            'category' => $t->category,
            'priority' => $t->priority,
            'status' => $t->status,
            'user_id' => $t->user_id,
            'requester_name' => $requesterName,
            'requester_email' => $t->user?->email,
            'requester_role' => $t->user?->primary_role,
            'assigned_to' => $t->assigned_to,
            'assignee_name' => $assigneeName,
            'last_reply_at' => $t->last_reply_at?->toISOString(),
            'created_at' => $t->created_at?->toISOString(),
            'created_at_human' => $t->created_at?->diffForHumans(),
            'latest_message_snippet' => $latestSnippet,
        ];
    }

    protected function serializeTicketDetail(Ticket $t, ?User $viewer = null): array
    {
        $viewer = $viewer ?? Auth::user();
        $canViewInternal = $viewer && $viewer->isSupportStaff();
        $requesterName = $t->user ? ($t->user->name ?: $t->user->email) : 'Unknown';
        $assigneeName = $t->assignee ? ($t->assignee->name ?: $t->assignee->email) : null;

        return [
            'id' => $t->id,
            'ticket_number' => $t->ticket_number,
            'subject' => $t->subject,
            'category' => $t->category,
            'priority' => $t->priority,
            'status' => $t->status,
            'user_id' => $t->user_id,
            'requester_name' => $requesterName,
            'requester_email' => $t->user?->email,
            'requester_role' => $t->user?->primary_role,
            'assigned_to' => $t->assigned_to,
            'assignee_name' => $assigneeName,
            'last_reply_at' => $t->last_reply_at?->toISOString(),
            'resolved_at' => $t->resolved_at?->toISOString(),
            'closed_at' => $t->closed_at?->toISOString(),
            'created_at' => $t->created_at?->toISOString(),
            'created_at_human' => $t->created_at?->diffForHumans(),
            'messages' => $t->messages
                ->filter(fn (TicketMessage $m) => $canViewInternal || ! (bool) $m->is_internal_note)
                ->values()
                ->map(fn (TicketMessage $m) => $this->serializeMessage($m, $viewer)),
        ];
    }

    protected function serializeMessage(TicketMessage $m, ?User $viewer = null): array
    {
        $viewer = $viewer ?? Auth::user();
        $canViewInternal = $viewer && $viewer->isSupportStaff();
        $authorName = $m->user ? ($m->user->name ?: $m->user->email) : 'Unknown';

        return [
            'id' => $m->id,
            'user_id' => $m->user_id,
            'author_name' => $authorName,
            'author_role' => $m->user?->primary_role ?: 'user',
            'is_staff' => $m->user ? $m->user->isSupportStaff() : false,
            'message' => $m->is_internal_note && ! $canViewInternal ? null : $m->message,
            'is_internal_note' => (bool) $m->is_internal_note,
            'created_at' => $m->created_at?->toISOString(),
            'created_at_human' => $m->created_at?->diffForHumans(),
            'attachments' => $m->attachments->map(fn (TicketAttachment $a) => [
                'id' => $a->id,
                'file_name' => $a->file_name,
                'file_size' => $a->file_size,
                'mime_type' => $a->mime_type,
                'url' => $a->url,
            ]),
        ];
    }
}
