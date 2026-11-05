<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Models\Traits\BelongsToOrganization;
class Supplier extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    protected $fillable = [
        'name', 'contact', 'phone', 'email', 'address', 'remark', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'bool',
    ];

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
