<?php

namespace Tests\Feature;

use App\Models\MallCoupon;
use App\Models\MallOrder;
use App\Models\MallProduct;
use App\Models\MallPromotion;
use App\Models\MallUserCoupon;
use App\Models\Product;
use App\Models\User;
use App\Services\Mall\MallService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MallMarketingModuleTest extends TestCase
{
    private static ?User $staffUser = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (! \App\Support\Premium::installed()) {
            $this->markTestSkipped(
                '收费私有包 mornrain/erp-premium 未安装：该模块已随「模型一」移出公开仓库，'
                .'本测试须在已安装私有包的环境运行。'
            );
        }
        DB::table('mall_order_items')->delete();
        DB::table('mall_reviews')->delete();
        DB::table('mall_carts')->delete();
        DB::table('mall_user_coupons')->delete();
        DB::table('mall_orders')->delete();
        MallCoupon::query()->delete();
        MallPromotion::query()->delete();
        MallProduct::query()->delete();
        Product::where('sku', 'like', 'MKT-%')->delete();
    }

    private function admin(): User
    {
        return User::find(1);
    }

    private function staff(): User
    {
        if (self::$staffUser === null) {
            self::$staffUser = User::firstOrCreate(
                ['email' => 'staff-mall@mornrain.local'],
                [
                    'name' => '测试运营',
                    'password' => User::hashPassword('staff1234'),
                    'role' => User::ROLE_STAFF,
                    'is_active' => true,
                ],
            );
        }

        return self::$staffUser;
    }

    private function makeOnlineProduct(float $price = 100): MallProduct
    {
        $product = Product::create([
            'sku' => 'MKT-' . strtoupper(uniqid()),
            'name' => '营销测试商品',
            'category' => '测试',
            'cost_price' => 1,
            'weight_g' => 0,
            'is_active' => true,
            'stock' => 100,
            'safety_stock' => 0,
        ]);

        return MallProduct::create([
            'product_id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'price' => $price,
            'original_price' => 0,
            'is_online' => true,
            'sort' => 0,
        ]);
    }

    private function makeCoupon(string $type = MallCoupon::TYPE_FIXED, float $value = 10, float $min = 0): MallCoupon
    {
        return MallCoupon::create([
            'code' => 'C' . strtoupper(uniqid()),
            'name' => '测试券',
            'type' => $type,
            'value' => $value,
            'min_amount' => $min,
            'total' => 0,
            'claimed_count' => 0,
            'per_user_limit' => 1,
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addDays(30)->toDateString(),
            'status' => MallCoupon::STATUS_ACTIVE,
        ]);
    }

    private function makePromotion(string $type = MallPromotion::TYPE_FULL_CUT, float $threshold = 50, float $value = 5): MallPromotion
    {
        return MallPromotion::create([
            'name' => '测试活动',
            'type' => $type,
            'threshold' => $threshold,
            'value' => $value,
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addDays(30)->toDateString(),
            'status' => MallPromotion::STATUS_ACTIVE,
            'description' => '测试用全场活动',
        ]);
    }

    private function addToCart(User $user, MallProduct $mp, int $qty = 1): void
    {
        $this->actingAs($user)
            ->post('/mall/cart/add', ['mall_product_id' => $mp->id, 'qty' => $qty])
            ->assertSessionHasNoErrors();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/mall/coupons')->assertRedirect('/login');
        $this->get('/mall/promotions')->assertRedirect('/login');
        $this->get('/mall/reviews')->assertRedirect('/login');
        $this->get('/mall/orders/create')->assertRedirect('/login');
    }

    public function test_staff_without_permission_gets_403(): void
    {
        $this->actingAs($this->staff())
            ->get('/mall/coupons')
            ->assertStatus(403);
        $this->actingAs($this->staff())
            ->get('/mall/promotions')
            ->assertStatus(403);
        $this->actingAs($this->staff())
            ->get('/mall/reviews')
            ->assertStatus(403);
        $this->actingAs($this->staff())
            ->get('/mall/orders/create')
            ->assertStatus(403);
    }

    public function test_admin_can_open_marketing_pages(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/mall/coupons')->assertStatus(200);
        $this->actingAs($admin)->get('/mall/promotions')->assertStatus(200);
        $this->actingAs($admin)->get('/mall/reviews')->assertStatus(200);
    }

    public function test_checkout_uses_coupon_when_better_than_promotion(): void
    {
        $admin = $this->admin();
        $mp = $this->makeOnlineProduct(100);
        $this->makePromotion(); // 满50减5
        $coupon = $this->makeCoupon(MallCoupon::TYPE_FIXED, 10); // 立减10
        $this->actingAs($admin)->post('/mall/coupons/' . $coupon->id . '/claim')->assertRedirect();
        $uc = MallUserCoupon::where('user_id', $admin->id)->where('coupon_id', $coupon->id)->first();
        $this->assertNotNull($uc);
        $this->assertSame(MallUserCoupon::STATUS_AVAILABLE, $uc->status);

        $this->addToCart($admin, $mp, 1);
        $this->actingAs($admin)
            ->get('/mall/orders/create')
            ->assertStatus(200)
            ->assertSee('优惠券');

        $this->actingAs($admin)->post('/mall/orders', [
            'contact_name' => '测试用户',
            'contact_phone' => '13800000000',
            'coupon_id' => $uc->id,
        ])->assertRedirect();

        $order = MallOrder::where('user_id', $admin->id)->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertSame(100.0, (float) $order->total_amount + (float) $order->discount_amount);
        $this->assertSame(10.0, (float) $order->discount_amount);
        $this->assertSame($uc->id, $order->coupon_id);
        $this->assertSame('测试券', $order->coupon_name);
        $this->assertSame(MallUserCoupon::STATUS_USED, $uc->fresh()->status);
    }

    public function test_checkout_prefers_promotion_when_better_than_coupon(): void
    {
        $admin = $this->admin();
        $mp = $this->makeOnlineProduct(100);
        $this->makePromotion(MallPromotion::TYPE_FULL_CUT, 50, 15); // 满50减15
        $coupon = $this->makeCoupon(MallCoupon::TYPE_FIXED, 5); // 立减5
        $this->actingAs($admin)->post('/mall/coupons/' . $coupon->id . '/claim')->assertRedirect();
        $uc = MallUserCoupon::where('user_id', $admin->id)->where('coupon_id', $coupon->id)->first();

        $this->addToCart($admin, $mp, 1);
        $this->actingAs($admin)->post('/mall/orders', [
            'contact_name' => '测试用户',
            'contact_phone' => '13800000000',
            'coupon_id' => $uc->id,
        ])->assertRedirect();

        $order = MallOrder::where('user_id', $admin->id)->latest('id')->first();
        $this->assertSame(15.0, (float) $order->discount_amount);
        $this->assertNull($order->coupon_id);
        $this->assertSame(MallUserCoupon::STATUS_AVAILABLE, $uc->fresh()->status); // 未核销
    }

    public function test_cancel_releases_coupon_back_to_available(): void
    {
        $admin = $this->admin();
        $mp = $this->makeOnlineProduct(100);
        $this->makePromotion(); // 满50减5（弱于券）
        $coupon = $this->makeCoupon(MallCoupon::TYPE_FIXED, 10);
        $this->actingAs($admin)->post('/mall/coupons/' . $coupon->id . '/claim')->assertRedirect();
        $uc = MallUserCoupon::where('user_id', $admin->id)->where('coupon_id', $coupon->id)->first();

        $this->addToCart($admin, $mp, 1);
        $this->actingAs($admin)->post('/mall/orders', [
            'contact_name' => '测试用户',
            'contact_phone' => '13800000000',
            'coupon_id' => $uc->id,
        ])->assertRedirect();

        $order = MallOrder::where('user_id', $admin->id)->latest('id')->first();
        $this->assertSame(MallUserCoupon::STATUS_USED, $uc->fresh()->status);

        $this->actingAs($admin)->post('/mall/orders/' . $order->id . '/cancel')->assertRedirect();
        $this->assertSame(MallUserCoupon::STATUS_AVAILABLE, $uc->fresh()->status);
        $this->assertNull($uc->fresh()->order_id);
    }

    public function test_best_promotion_service_picks_largest_discount(): void
    {
        $this->makePromotion(MallPromotion::TYPE_FULL_CUT, 50, 5);
        $this->makePromotion(MallPromotion::TYPE_PERCENT_OFF, 0, 20); // 满0享2折
        $service = app(MallService::class);
        $best = $service->bestPromotion(100);
        $this->assertNotNull($best);
        $this->assertSame(80.0, $best->discountFor(100));
    }
}
