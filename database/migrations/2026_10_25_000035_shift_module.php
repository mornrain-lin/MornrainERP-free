<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 多班次考勤模块：
     *  - work_shifts      班次定义（早班/中班/晚班/夜班，支持跨天、迟到宽限、标准工时）
     *  - work_schedules   排班（员工 × 日期 × 班次，一天可多班次轮班）
     *  - hr_attendances   扩班次维度（shift_id），唯一键 employee+date+shift
     */
    public function up(): void
    {
        Schema::create('work_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40);
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_cross_day')->default(false);  // 结束时间是否跨次日
            $table->unsignedInteger('grace_minutes')->default(5); // 迟到宽限（分钟）
            $table->decimal('work_hours', 4, 2)->default(8);  // 标准工时（小时）
            $table->string('color', 20)->default('primary');  // primary/info/success/warn/danger/muted
            $table->boolean('is_active')->default(true);
            $table->string('remark', 255)->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->index('is_active');
        });

        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('shift_id');
            $table->date('date');
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('hr_employees')->onDelete('cascade');
            $table->foreign('shift_id')->references('id')->on('work_shifts')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->unique(['employee_id', 'date', 'shift_id']);
            $table->index('date');
        });

        Schema::table('hr_attendances', function (Blueprint $table) {
            $table->dropUnique(['employee_id', 'date']);
            $table->unsignedBigInteger('shift_id')->nullable()->after('employee_id');
            $table->foreign('shift_id')->references('id')->on('work_shifts')->onDelete('set null');
            $table->unique(['employee_id', 'date', 'shift_id']);
        });
    }

    public function down(): void
    {
        Schema::table('hr_attendances', function (Blueprint $table) {
            $table->dropUnique(['employee_id', 'date', 'shift_id']);
            $table->dropForeign(['shift_id']);
            $table->dropColumn('shift_id');
            $table->unique(['employee_id', 'date']);
        });

        Schema::dropIfExists('work_schedules');
        Schema::dropIfExists('work_shifts');
    }
};
