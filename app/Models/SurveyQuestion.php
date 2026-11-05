<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyQuestion extends Model
{
    use HasFactory;

    public const TYPE_SINGLE = 'single';
    public const TYPE_MULTI = 'multi';
    public const TYPE_TEXT = 'text';
    public const TYPE_SCORE = 'score';

    protected $fillable = ['survey_id', 'type', 'title', 'required', 'sort'];

    protected $casts = [
        'required' => 'boolean',
    ];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class, 'survey_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(SurveyOption::class, 'question_id')->orderBy('sort')->orderBy('id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(SurveyAnswer::class, 'question_id');
    }

    public function typeLabel(): string
    {
        return self::types()[$this->type] ?? $this->type;
    }

    public function hasOptions(): bool
    {
        return in_array($this->type, [self::TYPE_SINGLE, self::TYPE_MULTI], true);
    }

    public static function types(): array
    {
        return [
            self::TYPE_SINGLE => '单选',
            self::TYPE_MULTI => '多选',
            self::TYPE_TEXT => '文本',
            self::TYPE_SCORE => '评分（1-5）',
        ];
    }
}
