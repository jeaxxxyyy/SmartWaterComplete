<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\User;
use App\Models\WaterReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepairThresholdHistoryCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_but_does_not_change_historical_records(): void
    {
        $reading = $this->incorrectReading();

        $this->artisan('water:repair-threshold-history')->assertSuccessful();

        $this->assertTrue($reading->fresh()->is_threshold_exceeded);
        $this->assertSame(1.0, (float) $reading->fresh()->water_limit_liters);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_apply_repairs_flags_archives_only_invalid_threshold_notices_and_writes_an_audit_log(): void
    {
        $reading = $this->incorrectReading();
        $thresholdNotice = Notification::create([
            'user_id' => $reading->user_id,
            'water_reading_id' => $reading->id,
            'type' => 'threshold_exceeded',
            'title' => 'Usage limit reached',
            'message' => 'Test threshold notice.',
            'delivery_status' => 'delivered',
            'sent_at' => now(),
        ]);
        $leakNotice = Notification::create([
            'user_id' => $reading->user_id,
            'water_reading_id' => $reading->id,
            'type' => 'possible_leak',
            'title' => 'Possible leak',
            'message' => 'This valid alert must be preserved.',
            'delivery_status' => 'delivered',
            'sent_at' => now(),
        ]);

        $this->artisan('water:repair-threshold-history', ['--apply' => true])->assertSuccessful();

        $reading->refresh();
        $this->assertFalse($reading->is_threshold_exceeded);
        $this->assertSame(15000.0, (float) $reading->threshold_liters);
        $this->assertSame(15000.0, (float) $reading->water_limit_liters);
        $this->assertNotNull($thresholdNotice->fresh()->archived_at);
        $this->assertNotNull($thresholdNotice->fresh()->user_deleted_at);
        $this->assertNull($leakNotice->fresh()->archived_at);
        $this->assertNull($leakNotice->fresh()->user_deleted_at);
        $this->assertSame(1, AuditLog::query()->where('action', 'threshold_history_repaired')->count());
    }

    public function test_repair_preserves_a_real_threshold_crossing(): void
    {
        $household = User::factory()->create();
        WaterReading::create([
            'user_id' => $household->id,
            'flow_rate' => 0,
            'liters_used' => 14999,
            'cubic_meter' => 14.999,
            'water_limit_liters' => 15000,
            'threshold_liters' => 15000,
            'is_threshold_exceeded' => false,
            'recorded_at' => now()->startOfMonth()->addDay(),
        ]);
        $incorrect = WaterReading::create([
            'user_id' => $household->id,
            'flow_rate' => 0,
            'liters_used' => 2,
            'cubic_meter' => 0.002,
            'water_limit_liters' => 1,
            'threshold_liters' => 1,
            'is_threshold_exceeded' => true,
            'recorded_at' => now()->startOfMonth()->addDays(2),
        ]);

        $this->artisan('water:repair-threshold-history', ['--apply' => true])->assertSuccessful();

        $this->assertTrue($incorrect->fresh()->is_threshold_exceeded);
        $this->assertSame(15000.0, (float) $incorrect->fresh()->water_limit_liters);
    }

    private function incorrectReading(): WaterReading
    {
        $household = User::factory()->create();

        return WaterReading::create([
            'user_id' => $household->id,
            'flow_rate' => 0,
            'liters_used' => 10,
            'cubic_meter' => 0.01,
            'water_limit_liters' => 1,
            'threshold_liters' => 1,
            'is_threshold_exceeded' => true,
            'recorded_at' => now()->startOfMonth()->addDay(),
        ]);
    }
}
