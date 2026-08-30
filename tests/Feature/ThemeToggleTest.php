<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ThemeToggleTest extends TestCase
{
    #[DataProvider('themedPageProvider')]
    public function test_the_shared_theme_controls_are_rendered_on_application_pages(string $path): void
    {
        $this->get($path)
            ->assertOk()
            ->assertSee('smart-water-theme', false)
            ->assertSee('data-theme-toggle', false)
            ->assertSee('data-theme-icon', false)
            ->assertSee('aria-label="Switch to dark mode"', false)
            ->assertSee('title="Switch to dark mode"', false)
            ->assertDontSee('data-theme-label', false)
            ->assertDontSee('>Dark mode<', false)
            ->assertDontSee('>Light mode<', false)
            ->assertSee("classList.toggle('dark'", false);
    }

    public static function themedPageProvider(): array
    {
        return [
            'public home' => ['/'],
            'splash screen' => ['/splash'],
            'login page' => ['/login'],
            'household dashboard' => ['/household'],
            'administrator dashboard' => ['/admin'],
        ];
    }

    public function test_household_dashboard_uses_the_compact_authenticated_workspace_layout(): void
    {
        $response = $this->get('/household');

        $response->assertOk()
            ->assertSee('Household Dashboard')
            ->assertSee('data-household-sidebar', false)
            ->assertSee('data-household-sidebar-collapse', false)
            ->assertSee('ui-icon-sidebar-collapse', false)
            ->assertSee('class="sidebar-nav-label"', false)
            ->assertSee('household-kpi-overview', false)
            ->assertSee('metric-overview-grid', false)
            ->assertSee('household-dashboard-grid', false)
            ->assertSee('system-status-panel', false)
            ->assertSee('data-household-usage-content', false)
            ->assertSee('analytics-period-summary', false)
            ->assertSee('analytics-summary-groups', false)
            ->assertSee('Usage snapshot')
            ->assertSee('Period comparison')
            ->assertSee('Meter activity')
            ->assertSee('history-summary-grid', false)
            ->assertSee('household-data-panel', false)
            ->assertSee('table-empty-state', false)
            ->assertSee('account-settings-intro', false)
            ->assertSee('account-settings-grid', false)
            ->assertSee('compact-settings-form', false)
            ->assertSee('role="tablist"', false)
            ->assertSee('aria-selected="true"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('data-refresh', false)
            ->assertDontSee('Monitor usage, estimated billing, and leak alerts in one place.')
            ->assertDontSee('Login');

        $content = $response->getContent();
        $document = new \DOMDocument;
        @$document->loadHTML($content);
        $metricCards = (new \DOMXPath($document))->query(
            "//*[@data-household-view='dashboard']//*[contains(concat(' ', normalize-space(@class), ' '), ' metric-card ')]"
        );

        $this->assertCount(6, $metricCards);
    }

    public function test_household_dashboard_controls_do_not_expand_the_hero_layout(): void
    {
        $response = $this->get('/household');

        $response->assertOk()
            ->assertSee('class="analytics-range-control"', false)
            ->assertSee('id="household-custom-range"', false)
            ->assertSee('data-household-analytics-custom-toggle', false)
            ->assertSee('ui-icon-calendar', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertSee('data-refresh-label', false)
            ->assertSee('<span data-refresh-label>Refresh</span>', false)
            ->assertSee('ui-icon-refresh', false);

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $feedbackInMeta = $xpath->query(
            "//*[contains(concat(' ', normalize-space(@class), ' '), ' household-dashboard-meta ')]//*[@data-dashboard-feedback]"
        );
        $dashboardIcons = $xpath->query(
            "//*[@data-household-view='dashboard']//*[contains(concat(' ', normalize-space(@class), ' '), ' metric-card-icon ')]//*[local-name()='svg']"
        );

        $this->assertCount(1, $feedbackInMeta);
        $this->assertCount(6, $dashboardIcons);
    }

    public function test_household_dashboard_renders_the_accessible_consumption_trend_contract(): void
    {
        $this->get('/household')
            ->assertOk()
            ->assertSee('Water Consumption Trend')
            ->assertSee('Track how your household water usage changes over time.')
            ->assertSee('View full consumption history', false)
            ->assertSee('/household#history', false)
            ->assertSee('data-household-trend-period="daily"', false)
            ->assertSee('data-household-trend-period="weekly"', false)
            ->assertSee('data-household-trend-period="monthly"', false)
            ->assertSee('data-household-trend-period="monthly" aria-pressed="true"', false)
            ->assertSee('data-household-trend-summary', false);
    }

    public function test_authenticated_workspace_shells_identify_the_account_role(): void
    {
        $this->get('/household')
            ->assertOk()
            ->assertSee('data-account-role', false)
            ->assertSee('Household User');

        $this->get('/admin')
            ->assertOk()
            ->assertSee('data-account-role', false)
            ->assertSee('Administrator');
    }

    public function test_administrator_workspace_uses_the_unified_responsive_layout_contract(): void
    {
        $this->get('/admin')
            ->assertOk()
            ->assertSee('admin-workspace-layout', false)
            ->assertSee('admin-device-layout', false)
            ->assertSee('admin-device-list-card', false)
            ->assertSee('admin-device-form-card', false)
            ->assertSee('admin-device-register-form', false)
            ->assertSee('consumption-filter-panel', false)
            ->assertSee('admin-leak-monitoring-card', false)
            ->assertSee('data-report-page-size', false)
            ->assertSee('5 rows')
            ->assertSee('data-admin-notification-toggle', false)
            ->assertSee('aria-controls="admin-notification-dropdown"', false)
            ->assertSee('data-admin-notification-dropdown', false)
            ->assertSee('data-admin-notification-dropdown-list', false)
            ->assertSee('data-admin-notification-view-all', false)
            ->assertSee('admin-refresh-button', false)
            ->assertSee('admin-analytics-range-control', false)
            ->assertSee('admin-analytics-custom-range', false)
            ->assertSee('input-with-currency', false)
            ->assertSee('name="currency" value="PHP"', false)
            ->assertDontSee('data-billing-currency', false)
            ->assertSee('class="filter-bar admin-notification-filters"', false)
            ->assertSee('data-admin-view="customer-management"', false)
            ->assertSee('data-admin-view="device-management"', false)
            ->assertSee('data-admin-view="water-rates"', false)
            ->assertSee('data-admin-view="billing-management"', false)
            ->assertSee('data-admin-view="consumption-monitoring"', false)
            ->assertSee('data-admin-view="leak-monitoring"', false)
            ->assertSee('data-admin-view="reports"', false)
            ->assertSee('data-admin-view="notifications"', false);
    }

    public function test_login_exposes_an_accessible_password_visibility_control(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('data-visibility-toggle="#password"', false)
            ->assertSee('aria-controls="password"', false)
            ->assertSee('aria-label="Show password"', false)
            ->assertSee('class="login-field-label" for="email"', false)
            ->assertSee('class="login-field-label" for="password"', false)
            ->assertSee('data-login-transition', false)
            ->assertSee('Opening your dashboard&hellip;', false)
            ->assertDontSee('form-floating login-field', false);
    }

    public function test_home_navigation_does_not_render_a_permanent_active_highlight(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('class="brand-mark-logo"', false)
            ->assertSee('class="brand-mark-wave"', false)
            ->assertSee('<a href="#home">Home</a>', false)
            ->assertDontSee('<a class="is-active" href="#home">Home</a>', false);
    }

    public function test_home_uses_the_compact_shared_landing_components(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('bi-house-heart', false)
            ->assertSee('bi-buildings', false)
            ->assertSee('bi-shield-check', false)
            ->assertSee('Your water data, in one clear workspace.');
    }

    public function test_home_session_actions_can_be_replaced_with_dashboard_links(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('data-home-session-action', false)
            ->assertSee('data-home-session-label', false)
            ->assertSee('data-authenticated-label="Dashboard"', false)
            ->assertSee('data-authenticated-label="Open Dashboard"', false)
            ->assertSee('data-authenticated-label="Open Your Dashboard"', false)
            ->assertSee('class="nav-cta-icon"', false)
            ->assertSee('bi-arrow-right-short', false);
    }

    public function test_household_header_renders_the_notification_dropdown_contract(): void
    {
        $this->get('/household')
            ->assertOk()
            ->assertSee('data-notification-toggle', false)
            ->assertSee('aria-haspopup="dialog"', false)
            ->assertSee('data-notification-dropdown', false)
            ->assertSee('data-notification-dropdown-list', false)
            ->assertSee('household-notifications-panel', false)
            ->assertSee('notification-page-empty', false)
            ->assertSee('View all notifications');
    }

    public function test_household_billing_view_uses_one_balanced_non_redundant_summary(): void
    {
        $response = $this->get('/household');

        $response->assertOk()
            ->assertSee('household-billing-view', false)
            ->assertSee('billing-summary-grid', false)
            ->assertSee('billing-empty-state', false)
            ->assertSee('billing-reading-empty', false)
            ->assertSee('Assigned water meter')
            ->assertSee('household-billing-grid', false)
            ->assertDontSee('Current billing details');

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $cards = (new \DOMXPath($document))->query(
            "//*[@data-household-billing-summary]//*[contains(concat(' ', normalize-space(@class), ' '), ' metric-card ')]"
        );
        $overviewGrids = (new \DOMXPath($document))->query(
            "//*[contains(concat(' ', normalize-space(@class), ' '), ' metric-overview-grid ')]"
        );

        $this->assertCount(4, $cards);
        $this->assertCount(3, $overviewGrids);
    }
}
