<?php

declare(strict_types=1);

namespace Controllers;

use Services\AuthService;

final class DashboardController
{
    public function __construct(private readonly AuthService $auth = new AuthService())
    {
    }

    public function index(): void
    {
        $user = $this->guard();
        $this->renderAdminPage($user, 'dashboard', 'dashboard/pages/index', [
            'title' => e('Dashboard'),
            'page_kicker' => e('Overview'),
            'page_title' => e('Dashboard'),
            'page_description' => e('Track uploads, monitor delivery performance, and review the most recent file activity at a glance.'),
            'flash_alert' => render_alert(flash(), 'dashboard-alert'),
        ]);
    }

    public function leads(): void
    {
        $user = $this->guard();
        $this->renderAdminPage($user, 'leads', 'dashboard/pages/leads', [
            'title' => e('Leads'),
            'page_kicker' => e('Lead workspace'),
            'page_title' => e('Leads'),
            'page_description' => e('This page will handle file uploads, Excel-to-variable mapping, search, pagination, and lead summaries.'),
            'flash_alert' => '',
        ]);
    }

    public function apiSettings(): void
    {
        $user = $this->guard();
        $this->renderAdminPage($user, 'api-settings', 'dashboard/pages/api-settings', [
            'title' => e('API Settings'),
            'page_kicker' => e('Integration setup'),
            'page_title' => e('API Settings'),
            'page_description' => e('Configure college endpoints, payload formats, throttling rules, and delivery settings from this page.'),
            'flash_alert' => '',
        ]);
    }

    public function systemConfig(): void
    {
        $user = $this->guard();
        $this->renderAdminPage($user, 'system-config', 'dashboard/pages/system-config', [
            'title' => e('System Config'),
            'page_kicker' => e('Platform controls'),
            'page_title' => e('System Config'),
            'page_description' => e('Use this page for environment-level settings, database options, upload rules, and system-wide controls.'),
            'flash_alert' => '',
        ]);
    }

    private function renderAdminPage(array $user, string $activePage, string $contentTemplate, array $overrides = []): void
    {
        $layoutData = $this->layoutData($user, $activePage, $overrides);
        $layoutData['page_content'] = render_template($contentTemplate, $layoutData);
        view('dashboard/layout', $layoutData);
    }

    private function guard(): array
    {
        if (!$this->auth->check()) {
            flash('Please login to continue.');
            redirect('/login');
        }

        return $this->auth->user() ?? [];
    }

    private function layoutData(array $user, string $activePage, array $overrides = []): array
    {
        $base = [
            'app_name' => e(env('APP_NAME', 'Leads API Project')),
            'logout_action' => e(app_url('logout')),
            'css_url' => e(asset('css/app.css')),
            'js_url' => e(asset('js/app.js')),
            'user_email' => e((string) ($user['email'] ?? '')),
            'logged_in_at' => e((string) ($user['logged_in_at'] ?? '-')),
            'dashboard_url' => e(app_url('dashboard')),
            'leads_url' => e(app_url('leads')),
            'api_settings_url' => e(app_url('api-settings')),
            'system_config_url' => e(app_url('system-config')),
            'dashboard_active' => $activePage === 'dashboard' ? 'is-active' : '',
            'leads_active' => $activePage === 'leads' ? 'is-active' : '',
            'api_settings_active' => $activePage === 'api-settings' ? 'is-active' : '',
            'system_config_active' => $activePage === 'system-config' ? 'is-active' : '',
            'flash_alert' => '',
        ];

        return array_merge($base, $overrides);
    }
}
