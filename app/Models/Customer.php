<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Models\Traits\BelongsToOrganization;
class Customer extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    public const LEVEL_NORMAL = 'normal';
    public const LEVEL_VIP = 'vip';
    public const LEVEL_GOLD = 'gold';

    protected $fillable = ['name', 'contact', 'phone', 'email', 'address', 'level', 'remark', 'is_active'];

    protected $casts = ['is_active' => 'bool'];

    public function saleOrders(): HasMany
    {
        return $this->hasMany(SaleOrder::class, 'customer_id');
    }

    public static function levelLabel(string $level): string
    {
        return match ($level) {
            self::LEVEL_VIP => 'VIP',
            self::LEVEL_GOLD => '金牌',
            default => '普通',
        };
    }
}
