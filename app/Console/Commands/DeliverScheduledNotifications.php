<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Services\BillingStatementService;
use Illuminate\Console\Command;

class DeliverScheduledNotifications extends Command
{
    protected $signature = 'notifications:deliver-scheduled';

    protected $description = 'Make due scheduled household notifications available in their inboxes';

    public function handle(BillingStatementService $audit): int
    {
        $due = Notification::query()
            ->where('delivery_status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->whereNull('archived_at')
            ->get();

        if ($due->isEmpty()) {
            $this->info('No scheduled notifications are due.');

            return self::SUCCESS;
        }

        $sentAt = now();
        Notification::query()->whereIn('id', $due->pluck('id'))->update([
            'delivery_status' => 'delivered',
            'delivery_error' => null,
            'sent_at' => $sentAt,
        ]);
        $audit->recordAudit(null, 'scheduled_notifications_delivered', 'Delivered scheduled household notifications.', [
            'delivered_count' => $due->count(),
        ]);
        $this->info("Delivered {$due->count()} scheduled notification record(s).");

        return self::SUCCESS;
    }
}
