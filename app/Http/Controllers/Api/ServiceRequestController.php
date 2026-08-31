<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\ServiceRequest;
use App\Services\BillingStatementService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ServiceRequestController extends Controller
{
    private const CATEGORIES = ['billing_question', 'billing_dispute', 'suspected_leak', 'account_update', 'service_interruption', 'meter_inspection', 'general_inquiry'];

    private const STATUSES = ['open', 'acknowledged', 'in_progress', 'waiting_for_household', 'resolved', 'closed'];

    public function __construct(protected BillingStatementService $audit) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'category' => ['nullable', Rule::in(self::CATEGORIES)],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $admin = $request->user()?->isAdmin() === true;
        $query = ServiceRequest::query()
            ->with(['household:id,name,email,account_number', 'assignee:id,name'])
            ->withCount('messages')
            ->when(! $admin, fn ($builder) => $builder->where('user_id', $request->user()->id))
            ->when(isset($validated['status']), fn ($builder) => $builder->where('status', $validated['status']))
            ->when(isset($validated['category']), fn ($builder) => $builder->where('category', $validated['category']))
            ->when(isset($validated['search']), function ($builder) use ($validated): void {
                $like = '%'.trim($validated['search']).'%';
                $builder->where(fn ($fields) => $fields->where('reference_number', 'like', $like)
                    ->orWhere('subject', 'like', $like)
                    ->orWhereHas('household', fn ($household) => $household->where('name', 'like', $like)->orWhere('account_number', 'like', $like)));
            })
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END")
            ->latest('updated_at');

        return response()->json(['data' => $query->paginate($validated['per_page'] ?? 10)->withQueryString()]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()?->isHousehold(), 403, 'Household access is required.');
        $validated = $request->validate([
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'subject' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:5000'],
            'billing_statement_id' => ['nullable', Rule::exists('billing_statements', 'id')->where('user_id', $request->user()->id)],
        ]);

        $item = ServiceRequest::create([
            ...$validated,
            'reference_number' => 'REQ-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'user_id' => $request->user()->id,
            'priority' => in_array($validated['category'], ['suspected_leak', 'service_interruption'], true) ? 'high' : 'normal',
            'status' => 'open',
        ]);
        $item->messages()->create(['user_id' => $request->user()->id, 'message' => $validated['description']]);
        $this->audit->recordAudit($request->user(), 'service_request_created', 'Created a household service request.', ['reference_number' => $item->reference_number, 'category' => $item->category], $item);

        return response()->json(['message' => 'Your request was submitted.', 'data' => $this->detail($item, false)], 201);
    }

    public function show(Request $request, ServiceRequest $serviceRequest)
    {
        $this->authorizeItem($request, $serviceRequest);

        return response()->json(['data' => $this->detail($serviceRequest, $request->user()->isAdmin())]);
    }

    public function update(Request $request, ServiceRequest $serviceRequest)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Administrator access is required.');
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(self::STATUSES)],
            'priority' => ['sometimes', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('role', 'admin')],
        ]);
        if (isset($validated['status'])) {
            $validated['resolved_at'] = in_array($validated['status'], ['resolved', 'closed'], true) ? now() : null;
        }
        $serviceRequest->update($validated);
        $this->notifyHousehold($serviceRequest, 'Request status updated', "{$serviceRequest->reference_number} is now ".str_replace('_', ' ', $serviceRequest->status).'.');
        $this->audit->recordAudit($request->user(), 'service_request_updated', 'Updated a household service request.', $validated, $serviceRequest);

        return response()->json(['message' => 'Request updated.', 'data' => $this->detail($serviceRequest, true)]);
    }

    public function reply(Request $request, ServiceRequest $serviceRequest)
    {
        $this->authorizeItem($request, $serviceRequest);
        $admin = $request->user()->isAdmin();
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'is_internal' => ['nullable', 'boolean'],
        ]);
        $message = $serviceRequest->messages()->create([
            'user_id' => $request->user()->id,
            'message' => $validated['message'],
            'is_internal' => $admin && ($validated['is_internal'] ?? false),
        ]);
        if ($admin && ! $message->is_internal) {
            $serviceRequest->update(['status' => 'waiting_for_household', 'assigned_to' => $serviceRequest->assigned_to ?? $request->user()->id]);
            $this->notifyHousehold($serviceRequest, 'New response to your request', "Municipal staff replied to {$serviceRequest->reference_number}.");
        } elseif (! $admin && in_array($serviceRequest->status, ['waiting_for_household', 'resolved'], true)) {
            $serviceRequest->update(['status' => 'in_progress', 'resolved_at' => null]);
        }

        return response()->json(['message' => 'Reply added.', 'data' => $this->detail($serviceRequest, $admin)], 201);
    }

    private function authorizeItem(Request $request, ServiceRequest $item): void
    {
        abort_unless($request->user()?->isAdmin() || $request->user()?->id === $item->user_id, 403, 'You are not allowed to access this request.');
    }

    private function detail(ServiceRequest $item, bool $admin): ServiceRequest
    {
        return $item->fresh()->load([
            'household:id,name,email,account_number,address',
            'assignee:id,name',
            'billingStatement:id,statement_number,billing_month,current_charge,remaining_balance,currency',
            'messages' => fn ($query) => $query->when(! $admin, fn ($builder) => $builder->where('is_internal', false))->with('author:id,name,role')->oldest(),
        ]);
    }

    private function notifyHousehold(ServiceRequest $item, string $title, string $message): void
    {
        if ($item->household()->first()?->notificationPreference?->service_request_updates === false) {
            return;
        }

        Notification::create([
            'user_id' => $item->user_id,
            'type' => 'announcement',
            'title' => $title,
            'message' => $message,
            'metadata' => ['service_request_id' => $item->id, 'reference_number' => $item->reference_number],
            'sent_at' => now(),
            'delivery_status' => 'delivered',
        ]);
    }
}
