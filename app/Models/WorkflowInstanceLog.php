<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowInstanceLog extends Model
{
    public const ACTION_SUBMIT = 'submit';
    public const ACTION_APPROVE = 'approve';
    public const ACTION_REJECT = 'reject';
    public const ACTION_CANCEL = 'cancel';

    protected $fillable = ['instance_id', 'node', 'action', 'actor_id', 'actor_name', 'comment'];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'instance_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public static function actionLabel(string $action): string
    {
        return match ($action) {
            self::ACTION_SUBMIT => '发起',
            self::ACTION_APPROVE => '审批通过',
            self::ACTION_REJECT => '驳回',
            self::ACTION_CANCEL => '撤回',
            default => $action,
        };
    }
}
