<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 采购请购申请（采购闭环第一环：请购 → 审批 → 转采购单）
 *  - purchase_requisitions      请购单头（draft/pending/approved/rejected/canceled/converted）
 *  - purchase_requisition_items 请购明细（SKU + 预估单价快照，人民币域）
 *
 * 审批复用电子流程引擎（workflow_instances，key=purchase.requisition）：
 * 提交时发起流程实例，审批结论由 WorkflowService 的业务回调写回本单状态，
 * 审批通过后一键转采购单（purchase_orders），衔接既有采购 / 入库闭环。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('requisition_no', 32)->unique()->comment('PR 单号');
            $table->string('department', 60)->comment('申请部门');
            $table->foreignId('applicant_id')->constrained('users')->restrictOnDelete();
            $table->date('expected_date')->nullable()->comment('期望到货日期');
            $table->string('priority', 10)->default('normal')->comment('normal/urgent');
            $table->string('status', 20)->default('draft')->comment('draft/pending/approved/rejected/canceled/converted');
            $table->text('remark')->nullable();
            $table->foreignId('workflow_instance_id')->nullable()->comment('关联审批流程实例');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('approve_remark', 255)->nullable();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete()->comment('转换后的采购单');
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('department');
        });

        Schema::create('purchase_requisition_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->constrained('purchase_requisitions')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('sku', 64);
            $table->string('product_name', 160);
            $table->integer('quantity')->default(1);
            $table->decimal('unit_cost', 14, 2)->default(0)->comment('预估单价(CNY)');
            $table->timestamps();

            $table->index('sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requisition_items');
        Schema::dropIfExists('purchase_requisitions');
    }
};
