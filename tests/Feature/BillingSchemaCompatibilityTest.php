<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Device;
use App\Models\Billing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingSchemaCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_billing_and_overview_data_flows_load_without_schema_errors(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/billing-management')->assertOk();
        $this->getJson('/api/summary')->assertOk();
        $this->getJson('/api/analytics/admin')->assertOk();
        $this->getJson('/api/analytics/admin?range=last_30_days')->assertOk();
    }

    public function test_household_billing_and_overview_data_flows_load_without_schema_errors(): void
    {
        $household = User::factory()->create();
        $deviceUser = User::factory()->create(['role' => 'device', 'assigned_user_id' => $household->id]);
        Device::create([
            'uuid' => (string) Str::uuid(),
            'asset_number' => 'SCHEMA-'.Str::random(8),
            'serial_number' => 'SCHEMA-SERIAL-'.Str::random(8),
            'name' => 'Schema Test Meter',
            'household_id' => $household->id,
            'auth_user_id' => $deviceUser->id,
            'status' => 'active',
        ]);
        Billing::create(['water_rate' => 40, 'effective_date' => now()->startOfMonth(), 'is_active' => true]);
        Sanctum::actingAs($household);

        $this->getJson('/api/billing-settings')->assertOk();
        $this->getJson('/api/household-billing')->assertOk();
        $this->getJson('/api/summary')->assertOk();
    }
}
