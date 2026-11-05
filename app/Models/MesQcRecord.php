<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MesQcRecord extends Model
{
    use HasFactory;

    public const RESULT_PASS = 'pass';
    public const RESULT_NG = 'ng';

    protected $fillable = [
        'work_order_id', 'checked_qty', 'pass_qty', 'fail_qty', 'result', 'inspector', 'remark',
    ];

    protected $casts = [
        'checked_qty' => 'int',
        'pass_qty' => 'int',
        'fail_qty' => 'int',
    ];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(MesWorkOrder::class, 'work_order_id');
    }

    public static function resultLabel(string $result): string
    {
        return $result === self::RESULT_NG ? '不合格' : '合格';
    }

    public static function resultTone(string $result): string
    {
        return $result === self::RESULT_NG ? 'danger' : 'success';
    }
}
