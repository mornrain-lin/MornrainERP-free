<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\BelongsToOrganization;

class Warehouse extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['name', 'location', 'note', 'is_default'];

    protected $casts = ['is_default' => 'bool'];

    public function stocks(): HasMany
    {
        return $this->hasMany(WarehouseStock::class);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
