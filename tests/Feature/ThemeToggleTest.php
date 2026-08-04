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
            ->assertSee('household-dashboard-grid', false)
            ->assertSee('system-status-panel', false)
            ->assertSee('data-household-usage-content', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('data-refresh', false)
            ->assertDontSee('Monitor usage, estimated billing, and leak alerts in one place.')
            ->assertDontSee('Login');

        $content = $response->getContent();
        $metricCards = substr_count($content, 'class="metric-card ') + substr_count($content, 'class="metric-card"');
        $this->assertSame(6, $metricCards);
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
}
