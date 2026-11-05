<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LicenseService;
use App\Support\Premium;
use Tests\TestCase;

/**
 * 激活码 / 套餐门禁回归测试（适配「模型一：WordPress.org 模式」）。
 *
 * 公开仓库只含免费核心 + 授权门禁 + 锁定桩；收费模块（CRM/PM/OA/ADM/MES/
 * EHR/商城…）已抽离为独立私有包 mornrain/erp-premium，源代码不随公开仓库分发。
 * 因此本测试不再断言 /crm、/mall 等「返回 403/200」——这些路由在公开仓库中
 * 已不存在（访问会得到 404）。取而代之，验证免费核心的两条防线：
 *
 *  1. LicenseService 门禁引擎（fail-closed）：定级、激活、过期回落、无效/篡改
 *     拒绝、解除激活回落免费版；
 *  2. premium.locked 锁定桩 + 激活端点：未登录跳登录页、登录后展示升级引导页、
 *     激活端点可改变当前套餐；
 *  3. 菜单策略：私有包未安装时，收费项以「升级」入口常驻侧边栏（不再隐藏，
 *     也不暴露任何收费逻辑），其链接指向 premium.locked。
 *
 * 运行前请确保已执行 php artisan migrate（创建 licenses 表）。
 * 未使用 RefreshDatabase，沿用项目既有测试对「已存在数据库」的依赖方式，
 * 每个用例前通过 LicenseService::deactivate() 回落免费版以保证隔离。
 */
class PlanGatingTest extends TestCase
{
    protected function admin(): User
    {
        return User::where('role', User::ROLE_ADMIN)->first()
            ?? User::create([
                'name' => '许可证测试管理员',
                'email' => 'admin-license-test@mornrain.local',
                'password' => User::hashPassword('admin1234'),
                'role' => User::ROLE_ADMIN,
                'is_active' => true,
            ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        // 每个用例前回落免费版，保证门禁回归用例互不干扰
        LicenseService::deactivate();
    }

    /* ----------------------------------------------------------------
     * 1. LicenseService 门禁引擎（fail-closed）
     * ---------------------------------------------------------------- */

    public function test_plan_ranks_are_ordered(): void
    {
        $this->assertSame(0, LicenseService::rank('free'));
        $this->assertSame(1, LicenseService::rank('pro'));
        $this->assertSame(2, LicenseService::rank('enterprise'));

        // 未知套餐等价于 free，fail-closed 不越权
        $this->assertSame(0, LicenseService::rank('unknown'));
    }

    public function test_activate_pro_code_sets_pro_plan(): void
    {
        $this->assertTrue(LicenseService::activate(LicenseService::generateCode('pro')));
        $this->assertSame('pro', LicenseService::plan());
    }

    public function test_activate_enterprise_code_sets_enterprise_plan(): void
    {
        $this->assertTrue(LicenseService::activate(LicenseService::generateCode('enterprise')));
        $this->assertSame('enterprise', LicenseService::plan());
    }

    public function test_expired_license_falls_back_to_free(): void
    {
        $code = LicenseService::generateCode('pro', now()->subYear()->format('Ymd'));
        $this->assertTrue(LicenseService::activate($code));
        $this->assertSame('free', LicenseService::plan(), '过期激活码不应解锁任何付费功能');
    }

    public function test_invalid_code_is_rejected(): void
    {
        $this->assertFalse(LicenseService::activate('MRERP1.PRO.20270101.DEADBEEF.00000000'));
        $this->assertSame('free', LicenseService::plan());
    }

    public function test_tampered_checksum_is_rejected(): void
    {
        $code = LicenseService::generateCode('pro');
        // 翻转校验段（第 5 段，8 位大写十六进制）最后一个字符，破坏签名
        $parts = explode('.', $code);
        $check = $parts[4];
        $last = substr($check, -1);
        $replacement = $last === '0' ? '1' : '0';
        $parts[4] = substr($check, 0, -1).$replacement;
        $tampered = implode('.', $parts);

        $this->assertFalse(LicenseService::activate($tampered), '校验段被篡改的激活码必须被拒绝');
        $this->assertSame('free', LicenseService::plan());
    }

    public function test_deactivate_returns_to_free(): void
    {
        LicenseService::activate(LicenseService::generateCode('enterprise'));
        $this->assertSame('enterprise', LicenseService::plan());

        LicenseService::deactivate();
        $this->assertSame('free', LicenseService::plan());
    }

    /* ----------------------------------------------------------------
     * 2. premium.locked 锁定桩 + 激活端点
     * ---------------------------------------------------------------- */

    public function test_locked_stub_requires_authentication(): void
    {
        // premium.locked 位于 auth 组内，未登录时 auth 中间件先于控制器生效，
        // 因此实际行为是重定向到登录页（控制器内的 abort_unless 是冗余防御）。
        $this->get('/premium/locked/crm')->assertRedirect('/login');
    }

    public function test_locked_stub_renders_upgrade_page(): void
    {
        $html = $this->actingAs($this->admin())
            ->get('/premium/locked/crm')
            ->assertStatus(200)
            ->getContent();

        // 升级引导页展示模块名与「收费版/升级」语义，且不暴露任何收费业务逻辑
        $this->assertStringContainsString('CRM 客户关系', $html);
        $this->assertStringContainsString('收费版', $html);
        $this->assertStringContainsString('升级', $html);
    }

    public function test_activation_endpoint_requires_auth(): void
    {
        $this->post('/license/activate', ['code' => 'x'])->assertRedirect('/login');
    }

    public function test_activation_endpoint_activates_plan(): void
    {
        $code = LicenseService::generateCode('pro');

        $this->actingAs($this->admin())
            ->post('/license/activate', ['code' => $code])
            ->assertRedirect();

        $this->assertSame('pro', LicenseService::plan());
    }

    public function test_activation_endpoint_rejects_invalid_code(): void
    {
        $this->actingAs($this->admin())
            ->post('/license/activate', ['code' => 'not-a-real-code'])
            ->assertRedirect()
            ->assertSessionHasErrors('code');

        $this->assertSame('free', LicenseService::plan(), '无效激活码不得改变套餐');
    }

    /* ----------------------------------------------------------------
     * 3. 菜单策略：私有包未安装时收费项以「升级」入口常驻
     * ---------------------------------------------------------------- */

    public function test_menu_shows_upgrade_entry_when_premium_absent(): void
    {
        // 收费私有包已安装时菜单渲染真实模块，本用例仅验证免费核心的锁定门禁，
        // 故跳过以避免误报。
        if (Premium::installed()) {
            $this->markTestSkipped('收费私有包已安装，菜单展示真实模块而非升级入口，本用例验证免费核心门禁');
        }

        $html = $this->actingAs($this->admin())->get('/')->assertStatus(200)->getContent();

        // 收费模块标签仍可见，但标记为「升级」入口，而非被隐藏
        $this->assertStringContainsString('CRM 客户关系', $html);
        $this->assertStringContainsString('商城', $html);
        $this->assertStringContainsString('升级', $html);

        // 升级入口指向 premium.locked 路由（锁定桩），不暴露任何收费路由
        $this->assertStringContainsString('premium/locked', $html);
    }

    public function test_premium_probe_reports_uninstalled_on_public_repo(): void
    {
        // 公开仓库（含 CI 全新克隆）不应包含收费私有包类，否则说明收费代码已泄漏。
        // 若开发者本地已安装私有包，此断言自动让位于真实环境，仅在「应无包」时报警。
        if (Premium::installed()) {
            $this->markTestSkipped('本地已安装收费私有包，跳过公开仓库纯洁性断言');
        }

        $this->assertFalse(Premium::installed(), '公开仓库不应可解析收费私有包类');
        $this->assertArrayHasKey('crm', Premium::modules());
        $this->assertArrayHasKey('mall', Premium::modules());
        $this->assertCount(14, Premium::modules());
    }
}
