<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/billing-settings')->assertOk();
        $this->getJson('/api/household-billing')->assertOk();
        $this->getJson('/api/summary')->assertOk();
    }
}
