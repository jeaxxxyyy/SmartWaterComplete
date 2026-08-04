<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BillingStatement;
use App\Models\LeakAlert;
use App\Models\Notification;
use App\Models\User;
use App\Models\WaterReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_report_preview_uses_real_household_records_and_filters_by_period(): void
    {
        $admin = User::factory()->admin()->create();
        $household = User::factory()->create([
            'name' => 'Report Household',
            'email' => 'report-household@example.com',
            'account_number' => 'ACC-REPORT-001',
            'created_at' => now()->subDays(5),
        ]);
        $otherAdmin = User::factory()->admin()->create(['email' => 'report-admin-two@example.com']);
        $reading = WaterReading::create([
            'user_id' => $household->id,
            'flow_rate' => 2,
            'liters_used' => 1500,
            'cubic_meter' => 1.5,
            'estimated_bill' => 60,
            'rate_per_cubic_meter' => 40,
            'recorded_at' => now()->subDay(),
        ]);
        BillingStatement::create([
            'user_id' => $household->id,
            'billing_month' => now()->startOfMonth(),
            'total_liters' => 1500,
            'total_cubic_meter' => 1.5,
            'water_rate' => 40,
            'current_charge' => 60,
            'remaining_balance' => 60,
            'status' => 'unpaid',
            'generated_at' => now(),
        ]);
        Notification::create([
            'user_id' => $household->id,
            'water_reading_id' => $reading->id,
            'type' => 'announcement',
            'title' => 'Report notice',
            'message' => 'A real report notification.',
            'sent_at' => now()->subDay(),
        ]);
        LeakAlert::create([
            'user_id' => $household->id,
            'first_water_reading_id' => $reading->id,
            'latest_water_reading_id' => $reading->id,
            'status' => LeakAlert::STATUS_DETECTED,
            'detected_at' => now()->subDay(),
            'notified_at' => now()->subDay(),
        ]);
        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'report_test_activity',
            'description' => 'A real administrative event.',
            'outcome' => 'success',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/report-management?report_type=consumption&period=custom&from='.now()->subDays(2)->toDateString().'&to='.now()->toDateString())
            ->assertOk()
            ->assertJsonPath('data.summary.total_records', 1)
            ->assertJsonPath('data.items.0.household', 'Report Household')
            ->assertJsonPath('data.period.type', 'custom')
            ->assertJsonMissing(['email' => $otherAdmin->email]);

        $this->getJson('/api/report-management?report_type=billing&period=monthly')
            ->assertOk()
            ->assertJsonPath('data.summary.total_records', 1)
            ->assertJsonPath('data.items.0.status', 'Unpaid');

        $this->getJson('/api/report-management?report_type=leaks&period=monthly')
            ->assertOk()
            ->assertJsonPath('data.summary.total_records', 1)
            ->assertJsonPath('data.items.0.status', 'Detected');
    }

    public function test_report_export_requires_real_data_and_writes_an_audit_record(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/exports/reports?report_type=consumption&from=2020-01-01&to=2020-01-02')
            ->assertStatus(422)
            ->assertJsonPath('message', 'No report data is available for the selected filters.');

        $household = User::factory()->create();
        WaterReading::create([
            'user_id' => $household->id,
            'flow_rate' => 1,
            'liters_used' => 500,
            'cubic_meter' => 0.5,
            'estimated_bill' => 20,
            'rate_per_cubic_meter' => 40,
            'recorded_at' => now(),
        ]);

        $this->get('/api/exports/reports?report_type=consumption&period=daily')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'report_exported',
            'outcome' => 'success',
        ]);
    }

    public function test_household_users_cannot_access_admin_reports_or_exports(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/report-management')->assertForbidden();
        $this->getJson('/api/exports/reports')->assertForbidden();
    }
}
