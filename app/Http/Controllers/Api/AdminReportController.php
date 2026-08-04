<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BillingStatement;
use App\Models\Device;
use App\Models\LeakAlert;
use App\Models\Notification;
use App\Models\User;
use App\Models\WaterReading;
use App\Services\BillingStatementService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminReportController extends Controller
{
    private const REPORT_TYPES = [
        'all', 'consumption', 'billing', 'notifications', 'leaks', 'devices', 'households', 'audit',
    ];

    private const PERIODS = ['daily', 'weekly', 'monthly', 'custom'];

    public function __construct(protected BillingStatementService $audit) {}

    public function index(Request $request)
    {
        $filters = $this->validatedFilters($request);
        $report = $this->buildReport($filters);

        return response()->json(['data' => $report]);
    }

    public function export(Request $request)
    {
        $filters = $this->validatedFilters($request);
        $report = $this->buildReport($filters, true);

        if ($report['summary']['total_records'] === 0) {
            return response()->json([
                'message' => 'No report data is available for the selected filters.',
            ], 422);
        }

        $typeLabel = $this->reportTypeLabel($filters['report_type']);
        $title = $typeLabel.' Report';
        $filename = Str::slug($typeLabel).'-report-'.$report['period']['from'].'-to-'.$report['period']['to'].'.pdf';

        $this->audit->recordAudit($request->user(), 'report_exported', "Exported {$title} to PDF.", [
            'report_type' => $filters['report_type'],
            'period' => $report['period'],
            'search' => $filters['search'],
            'status' => $filters['status'],
            'record_count' => $report['summary']['total_records'],
        ]);

        return Pdf::loadView('reports.pdf', [
            'title' => $title,
            'headers' => collect($report['columns'])->pluck('label')->all(),
            'rows' => collect($report['items'])->map(fn (array $item): array => collect($report['columns'])
                ->map(fn (array $column): string => (string) ($item[$column['key']] ?? '—'))->all())->all(),
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape')->stream($filename);
    }

    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'report_type' => ['nullable', Rule::in(self::REPORT_TYPES)],
            'period' => ['nullable', Rule::in(self::PERIODS)],
            'from' => ['nullable', 'date', 'required_if:period,custom'],
            'to' => ['nullable', 'date', 'after_or_equal:from', 'required_if:period,custom'],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        [$from, $to] = $this->resolvePeriod($validated);

        return [
            'report_type' => $validated['report_type'] ?? 'all',
            'period_type' => $validated['period'] ?? 'monthly',
            'from' => $from,
            'to' => $to,
            'search' => trim((string) ($validated['search'] ?? '')),
            'status' => trim((string) ($validated['status'] ?? '')),
            'page' => (int) ($validated['page'] ?? 1),
            'per_page' => (int) ($validated['per_page'] ?? 25),
        ];
    }

    private function resolvePeriod(array $filters): array
    {
        $period = $filters['period'] ?? 'monthly';
        if ($period === 'custom' && isset($filters['from'], $filters['to'])) {
            $from = Carbon::parse($filters['from'])->startOfDay();
            $to = Carbon::parse($filters['to'])->endOfDay();
        } elseif ($period === 'daily') {
            $from = now()->startOfDay();
            $to = now()->endOfDay();
        } elseif ($period === 'weekly') {
            $from = now()->startOfWeek();
            $to = now()->endOfWeek();
        } else {
            $from = now()->startOfMonth();
            $to = now()->endOfMonth();
        }

        if ($from->greaterThan($to) || $from->diffInDays($to) > 366) {
            throw ValidationException::withMessages([
                'to' => 'The selected report period must be valid and cannot exceed 366 days.',
            ]);
        }

        return [$from, $to];
    }

    private function buildReport(array $filters, bool $forExport = false): array
    {
        $type = $filters['report_type'];
        $categories = $type === 'all'
            ? ['consumption', 'billing', 'notifications', 'leaks', 'devices', 'households', 'audit']
            : [$type];
        if ($type === 'audit') {
            $categories = ['audit'];
        }

        $reports = collect($categories)->mapWithKeys(fn (string $category): array => [
            $category => $this->buildCategory($category, $filters, $forExport),
        ]);
        $items = $reports->flatMap(function (array $category, string $key) use ($type): Collection {
            return collect($category['items'])->map(function (array $item) use ($key, $type): array {
                if ($type !== 'all') {
                    return $item;
                }

                return [
                    'category' => $this->reportTypeLabel($key),
                    'period' => $item['period'] ?? $item['last_seen'] ?? '—',
                    'subject' => $item['household'] ?? $item['actor'] ?? $item['account'] ?? '—',
                    'reference' => $item['meter'] ?? $item['title'] ?? $item['action'] ?? $item['email'] ?? '—',
                    'value' => $item['volume'] ?? $item['consumption'] ?? $item['charge'] ?? $item['flow'] ?? $item['connection'] ?? '—',
                    'status' => $item['status'] ?? '—',
                ];
            });
        })->values();
        $columns = $type === 'all'
            ? [
                ['key' => 'category', 'label' => 'Report'], ['key' => 'period', 'label' => 'Period'],
                ['key' => 'subject', 'label' => 'Household / actor'], ['key' => 'reference', 'label' => 'Record'],
                ['key' => 'value', 'label' => 'Value'], ['key' => 'status', 'label' => 'Status'],
            ]
            : $reports->first()['columns'];
        $summary = [
            'total_records' => $items->count(),
            'categories' => $reports->map(fn (array $category, string $key): array => [
                'key' => $key,
                'label' => $this->reportTypeLabel($key),
                'records' => $category['summary']['total_records'],
            ])->values()->all(),
        ];

        foreach ($reports as $category) {
            foreach ($category['summary'] as $key => $value) {
                if ($key === 'total_records') {
                    continue;
                }
                if (is_numeric($value)) {
                    $summary[$key] = ($summary[$key] ?? 0) + $value;
                }
            }
        }

        $total = $items->count();
        $page = $filters['page'];
        $perPage = $filters['per_page'];
        $visibleItems = $forExport ? $items : $items->forPage($page, $perPage)->values();

        return [
            'report_type' => $type,
            'period' => [
                'type' => $filters['period_type'],
                'from' => $filters['from']->toDateString(),
                'to' => $filters['to']->toDateString(),
                'label' => $filters['from']->format('M j, Y').' – '.$filters['to']->format('M j, Y'),
            ],
            'summary' => $summary,
            'columns' => $columns,
            'items' => $visibleItems->all(),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'from' => $total > 0 ? (($page - 1) * $perPage) + 1 : null,
                'to' => $total > 0 ? min($page * $perPage, $total) : null,
            ],
        ];
    }

    private function buildCategory(string $type, array $filters, bool $forExport): array
    {
        return match ($type) {
            'consumption' => $this->consumption($filters, $forExport),
            'billing' => $this->billing($filters, $forExport),
            'notifications' => $this->notifications($filters, $forExport),
            'leaks' => $this->leaks($filters, $forExport),
            'devices' => $this->devices($filters, $forExport),
            'households' => $this->households($filters, $forExport),
            'audit' => $this->auditLogs($filters, $forExport),
        };
    }

    private function consumption(array $filters, bool $forExport): array
    {
        $query = WaterReading::query()->with(['user:id,name,email,account_number,address', 'device:id,name,asset_number,serial_number'])
            ->whereBetween('recorded_at', [$filters['from'], $filters['to']])
            ->whereHas('user', fn ($builder) => $builder->where('role', 'user'))
            ->when($filters['search'] !== '', function ($builder) use ($filters): void {
                $like = '%'.$filters['search'].'%';
                $builder->where(fn ($nested) => $nested->whereHas('user', fn ($user) => $user
                    ->where('role', 'user')
                    ->where(fn ($fields) => $fields->where('name', 'like', $like)->orWhere('email', 'like', $like)
                        ->orWhere('account_number', 'like', $like)->orWhere('address', 'like', $like)))
                    ->orWhereHas('device', fn ($device) => $device->where('name', 'like', $like)
                        ->orWhere('asset_number', 'like', $like)->orWhere('serial_number', 'like', $like)));
            })
            ->when($filters['status'] === 'threshold', fn ($builder) => $builder->where('is_threshold_exceeded', true))
            ->when($filters['status'] === 'leak', fn ($builder) => $builder->where('is_possible_leak', true));
        $summaryQuery = clone $query;
        $items = $query->latest('recorded_at')->latest('id')->get();
        $rows = $items->map(fn (WaterReading $reading): array => [
            'period' => optional($reading->recorded_at)->format('M j, Y H:i') ?? '—',
            'household' => $reading->user?->name ?? '—',
            'meter' => $reading->device?->asset_number ?? $reading->device_name ?? '—',
            'volume' => number_format((float) $reading->cubic_meter, 4).' m³',
            'estimated_bill' => ($reading->rate_per_cubic_meter ? 'PHP ' : '').number_format((float) $reading->estimated_bill, 2),
            'status' => $reading->is_possible_leak ? 'Possible leak' : ($reading->is_threshold_exceeded ? 'Threshold exceeded' : 'Normal'),
        ])->values();

        return [
            'columns' => [
                ['key' => 'period', 'label' => 'Recorded'], ['key' => 'household', 'label' => 'Household'],
                ['key' => 'meter', 'label' => 'Meter'], ['key' => 'volume', 'label' => 'Volume'],
                ['key' => 'estimated_bill', 'label' => 'Estimated bill'], ['key' => 'status', 'label' => 'Condition'],
            ],
            'items' => $rows->all(),
            'summary' => [
                'total_records' => $summaryQuery->count(),
                'total_liters' => (float) $summaryQuery->sum('liters_used'),
                'total_cubic_meter' => (float) $summaryQuery->sum('cubic_meter'),
                'estimated_bill' => (float) $summaryQuery->sum('estimated_bill'),
                'threshold_count' => (int) (clone $summaryQuery)->where('is_threshold_exceeded', true)->count(),
                'leak_count' => (int) (clone $summaryQuery)->where('is_possible_leak', true)->count(),
            ],
        ];
    }

    private function billing(array $filters, bool $forExport): array
    {
        $query = BillingStatement::query()->with('user:id,name,email,account_number,address')
            ->whereBetween('billing_month', [$filters['from']->toDateString(), $filters['to']->toDateString()])
            ->whereHas('user', fn ($builder) => $builder->where('role', 'user'))
            ->when($filters['search'] !== '', function ($builder) use ($filters): void {
                $like = '%'.$filters['search'].'%';
                $builder->whereHas('user', fn ($user) => $user->where('role', 'user')->where(fn ($fields) => $fields
                    ->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('account_number', 'like', $like)
                    ->orWhere('address', 'like', $like)));
            })
            ->when($filters['status'] !== '', fn ($builder) => $builder->where('status', $filters['status']));
        $summaryQuery = clone $query;
        $items = $query->latest('billing_month')->latest('id')->get();
        $rows = $items->map(fn (BillingStatement $statement): array => [
            'period' => optional($statement->billing_month)->format('M Y') ?? '—',
            'household' => $statement->user?->name ?? '—',
            'consumption' => number_format((float) $statement->total_cubic_meter, 4).' m³',
            'charge' => ($statement->currency ?? 'PHP').' '.number_format((float) $statement->current_charge, 2),
            'balance' => ($statement->currency ?? 'PHP').' '.number_format((float) $statement->remaining_balance, 2),
            'status' => ucfirst($statement->status),
        ])->values();

        return [
            'columns' => [
                ['key' => 'period', 'label' => 'Billing month'], ['key' => 'household', 'label' => 'Household'],
                ['key' => 'consumption', 'label' => 'Consumption'], ['key' => 'charge', 'label' => 'Current charge'],
                ['key' => 'balance', 'label' => 'Balance'], ['key' => 'status', 'label' => 'Status'],
            ],
            'items' => $rows->all(),
            'summary' => [
                'total_records' => $summaryQuery->count(),
                'total_cubic_meter' => (float) $summaryQuery->sum('total_cubic_meter'),
                'current_charge' => (float) $summaryQuery->sum('current_charge'),
                'remaining_balance' => (float) $summaryQuery->sum('remaining_balance'),
            ],
        ];
    }

    private function notifications(array $filters, bool $forExport): array
    {
        $query = Notification::query()->with('user:id,name,email,account_number')
            ->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->whereHas('user', fn ($builder) => $builder->where('role', 'user'))
            ->when($filters['search'] !== '', function ($builder) use ($filters): void {
                $like = '%'.$filters['search'].'%';
                $builder->where(fn ($nested) => $nested->where('title', 'like', $like)->orWhere('message', 'like', $like)
                    ->orWhereHas('user', fn ($user) => $user->where('role', 'user')->where(fn ($fields) => $fields
                        ->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('account_number', 'like', $like))));
            })
            ->when($filters['status'] === 'read', fn ($builder) => $builder->where('is_read', true))
            ->when($filters['status'] === 'unread', fn ($builder) => $builder->where('is_read', false));
        $summaryQuery = clone $query;
        $items = $query->latest('created_at')->latest('id')->get();
        $rows = $items->map(fn (Notification $notification): array => [
            'period' => optional($notification->sent_at ?? $notification->created_at)->format('M j, Y H:i') ?? '—',
            'household' => $notification->user?->name ?? '—',
            'type' => Str::headline($notification->type),
            'title' => $notification->title,
            'status' => $notification->is_read ? 'Read' : 'Unread',
        ])->values();

        return [
            'columns' => [
                ['key' => 'period', 'label' => 'Sent'], ['key' => 'household', 'label' => 'Household'],
                ['key' => 'type', 'label' => 'Category'], ['key' => 'title', 'label' => 'Title'],
                ['key' => 'status', 'label' => 'Read status'],
            ],
            'items' => $rows->all(),
            'summary' => [
                'total_records' => $summaryQuery->count(),
                'unread_count' => (int) (clone $summaryQuery)->where('is_read', false)->count(),
            ],
        ];
    }

    private function leaks(array $filters, bool $forExport): array
    {
        $query = LeakAlert::query()->with(['user:id,name,email,account_number', 'latestReading:id,flow_rate,recorded_at'])
            ->whereBetween('detected_at', [$filters['from'], $filters['to']])
            ->whereHas('user', fn ($builder) => $builder->where('role', 'user'))
            ->when($filters['search'] !== '', function ($builder) use ($filters): void {
                $like = '%'.$filters['search'].'%';
                $builder->whereHas('user', fn ($user) => $user->where('role', 'user')->where(fn ($fields) => $fields
                    ->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('account_number', 'like', $like)));
            })
            ->when($filters['status'] !== '', fn ($builder) => $builder->where('status', $filters['status']));
        $summaryQuery = clone $query;
        $items = $query->latest('detected_at')->latest('id')->get();
        $rows = $items->map(fn (LeakAlert $alert): array => [
            'period' => optional($alert->detected_at)->format('M j, Y H:i') ?? '—',
            'household' => $alert->user?->name ?? '—',
            'flow' => number_format((float) ($alert->latestReading?->flow_rate ?? 0), 2),
            'notified' => $alert->notified_at ? 'Yes' : 'No',
            'status' => Str::headline($alert->status),
        ])->values();

        return [
            'columns' => [
                ['key' => 'period', 'label' => 'Detected'], ['key' => 'household', 'label' => 'Household'],
                ['key' => 'flow', 'label' => 'Latest flow'], ['key' => 'notified', 'label' => 'Household notified'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            'items' => $rows->all(),
            'summary' => [
                'total_records' => $summaryQuery->count(),
                'active_count' => (int) (clone $summaryQuery)->whereIn('status', LeakAlert::ACTIVE_STATUSES)->count(),
                'unnotified_count' => (int) (clone $summaryQuery)->whereNull('notified_at')->count(),
            ],
        ];
    }

    private function devices(array $filters, bool $forExport): array
    {
        $query = Device::query()->with('household:id,name,email,account_number')
            ->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->whereHas('household', fn ($builder) => $builder->where('role', 'user'))
            ->when($filters['search'] !== '', function ($builder) use ($filters): void {
                $like = '%'.$filters['search'].'%';
                $builder->where(fn ($nested) => $nested->where('asset_number', 'like', $like)->orWhere('serial_number', 'like', $like)
                    ->orWhere('name', 'like', $like)->orWhereHas('household', fn ($household) => $household
                        ->where('role', 'user')->where(fn ($fields) => $fields->where('name', 'like', $like)
                            ->orWhere('account_number', 'like', $like))));
            })
            ->when($filters['status'] !== '', fn ($builder) => $builder->where('status', $filters['status']));
        $summaryQuery = clone $query;
        $items = $query->latest('created_at')->latest('id')->get();
        $rows = $items->map(fn (Device $device): array => [
            'meter' => $device->asset_number,
            'household' => $device->household?->name ?? '—',
            'connection' => $device->is_online ? 'Online' : ($device->status === 'pending' ? 'Pending activation' : 'Offline'),
            'last_seen' => optional($device->last_seen_at)->format('M j, Y H:i') ?? 'Never',
            'status' => Str::headline($device->status),
        ])->values();

        return [
            'columns' => [
                ['key' => 'meter', 'label' => 'Meter'], ['key' => 'household', 'label' => 'Household'],
                ['key' => 'connection', 'label' => 'Connection'], ['key' => 'last_seen', 'label' => 'Last seen'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            'items' => $rows->all(),
            'summary' => [
                'total_records' => $summaryQuery->count(),
                'online_count' => (int) $items->where('is_online', true)->count(),
                'offline_count' => (int) $items->where('is_online', false)->count(),
            ],
        ];
    }

    private function households(array $filters, bool $forExport): array
    {
        $query = User::query()->with('devices:id,asset_number,household_id,created_at')->where('role', 'user')->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->when($filters['search'] !== '', function ($builder) use ($filters): void {
                $like = '%'.$filters['search'].'%';
                $builder->where(fn ($nested) => $nested->where('name', 'like', $like)->orWhere('email', 'like', $like)
                    ->orWhere('account_number', 'like', $like)->orWhere('address', 'like', $like));
            })
            ->when($filters['status'] === 'active', fn ($builder) => $builder->where('is_active', true)->where(fn ($q) => $q->whereNull('account_status')->orWhere('account_status', 'active')))
            ->when(in_array($filters['status'], ['inactive', 'suspended', 'archived'], true), function ($builder) use ($filters): void {
                if ($filters['status'] === 'inactive') {
                    $builder->where('is_active', false);
                } else {
                    $builder->where('account_status', $filters['status']);
                }
            });
        $summaryQuery = clone $query;
        $items = $query->latest('created_at')->latest('id')->get();
        $rows = $items->map(fn (User $user): array => [
            'account' => $user->account_number ?? '—',
            'household' => $user->name,
            'email' => $user->email,
            'meter' => $user->devices->sortByDesc('created_at')->first()?->asset_number ?? 'Unassigned',
            'status' => $user->isAccountActive() ? 'Active' : Str::headline($user->account_status ?? 'Inactive'),
        ])->values();

        return [
            'columns' => [
                ['key' => 'account', 'label' => 'Account'], ['key' => 'household', 'label' => 'Household'],
                ['key' => 'email', 'label' => 'Email'], ['key' => 'meter', 'label' => 'Assigned meter'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            'items' => $rows->all(),
            'summary' => [
                'total_records' => $summaryQuery->count(),
                'active_count' => (int) (clone $summaryQuery)->where('is_active', true)->count(),
                'inactive_count' => (int) (clone $summaryQuery)->where('is_active', false)->count(),
            ],
        ];
    }

    private function auditLogs(array $filters, bool $forExport): array
    {
        $query = AuditLog::query()->with('user:id,name,role')
            ->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->when($filters['search'] !== '', function ($builder) use ($filters): void {
                $like = '%'.$filters['search'].'%';
                $builder->where(fn ($nested) => $nested->where('action', 'like', $like)->orWhere('description', 'like', $like));
            })
            ->when($filters['status'] !== '', fn ($builder) => $builder->where('outcome', $filters['status']));
        $summaryQuery = clone $query;
        $items = $query->latest('created_at')->latest('id')->get();
        $rows = $items->map(fn (AuditLog $log): array => [
            'period' => optional($log->created_at)->format('M j, Y H:i') ?? '—',
            'actor' => $log->user?->name ?? 'System',
            'action' => Str::headline($log->action),
            'description' => $log->description,
            'status' => Str::headline($log->outcome ?? 'success'),
        ])->values();

        return [
            'columns' => [
                ['key' => 'period', 'label' => 'Timestamp'], ['key' => 'actor', 'label' => 'Actor'],
                ['key' => 'action', 'label' => 'Action'], ['key' => 'description', 'label' => 'Description'],
                ['key' => 'status', 'label' => 'Outcome'],
            ],
            'items' => $rows->all(),
            'summary' => ['total_records' => $summaryQuery->count()],
        ];
    }

    private function reportTypeLabel(string $type): string
    {
        return match ($type) {
            'all' => 'Operational',
            'consumption' => 'Consumption and Meter Readings',
            'billing' => 'Billing Records',
            'notifications' => 'Notifications and Announcements',
            'leaks' => 'Leak Alerts',
            'devices' => 'Device and Connection Health',
            'households' => 'Household Account Summaries',
            'audit' => 'Administrative Activity',
            default => Str::headline($type),
        };
    }
}
