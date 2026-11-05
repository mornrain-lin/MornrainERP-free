<?php

/*
|--------------------------------------------------------------------------
| 晨雨ERP · 电子流程目录（50+ 电子流程）
|--------------------------------------------------------------------------
| 每个流程模板定义：name 名称 / desc 说明 / nodes 审批节点链 / fields 表单字段。
| 流程实例（发起 / 审批）数据存于 workflow_instances 表，节点按 nodes 顺序推进。
| 新增流程只需在此追加一项，无需改动引擎与界面。
|
| field: ['key'=>, 'label'=>, 'type'=>text|number|date|textarea|select, 'required'=>bool, 'options'=>[]]
*/

return [
    'purchase' => [
        'label' => '采购',
        'items' => [
            'purchase.apply'    => ['name' => '采购申请',   'desc' => '部门提出采购需求，经审批后转采购单', 'nodes' => ['直属主管', '采购经理', '财务'], 'fields' => [['key' => 'item', 'label' => '采购物品', 'type' => 'text', 'required' => true], ['key' => 'amount', 'label' => '预算金额', 'type' => 'number', 'required' => true], ['key' => 'reason', 'label' => '采购事由', 'type' => 'textarea']]],
            'purchase.order'    => ['name' => '采购订单审批', 'desc' => '采购订单确认与审批', 'nodes' => ['采购经理', '财务'], 'fields' => [['key' => 'order_no', 'label' => '订单号', 'type' => 'text', 'required' => true], ['key' => 'supplier', 'label' => '供应商', 'type' => 'text', 'required' => true], ['key' => 'amount', 'label' => '金额', 'type' => 'number', 'required' => true]]],
            'purchase.return'   => ['name' => '采购退货',   'desc' => '不合格采购退回供应商', 'nodes' => ['采购经理', '质量'], 'fields' => [['key' => 'order_no', 'label' => '原订单号', 'type' => 'text', 'required' => true], ['key' => 'qty', 'label' => '退货数量', 'type' => 'number', 'required' => true], ['key' => 'reason', 'label' => '退货原因', 'type' => 'textarea', 'required' => true]]],
            'purchase.requisition' => ['name' => '请购申请', 'desc' => '部门物料请购，审批通过后转采购单（请购申请模块自动回写状态）', 'nodes' => ['直属主管', '采购'], 'fields' => [['key' => 'department', 'label' => '申请部门', 'type' => 'text', 'required' => true], ['key' => 'sku', 'label' => 'SKU', 'type' => 'text', 'required' => true], ['key' => 'qty', 'label' => '数量', 'type' => 'number', 'required' => true], ['key' => 'expected_date', 'label' => '期望到货', 'type' => 'date'], ['key' => 'priority', 'label' => '优先级', 'type' => 'select', 'options' => ['普通', '加急']], ['key' => 'remark', 'label' => '备注', 'type' => 'textarea']]],
            'supplier.entry'    => ['name' => '供应商准入', 'desc' => '新供应商引入评审', 'nodes' => ['采购经理', '财务', '总经理'], 'fields' => [['key' => 'name', 'label' => '供应商名称', 'type' => 'text', 'required' => true], ['key' => 'contact', 'label' => '联系人', 'type' => 'text'], ['key' => 'level', 'label' => '等级', 'type' => 'select', 'options' => ['战略', '普通', '临时']]]],
            'supplier.exit'     => ['name' => '供应商淘汰', 'desc' => '供应商退出评审', 'nodes' => ['采购经理', '总经理'], 'fields' => [['key' => 'name', 'label' => '供应商名称', 'type' => 'text', 'required' => true], ['key' => 'reason', 'label' => '淘汰原因', 'type' => 'textarea', 'required' => true]]],
        ],
    ],
    'finance' => [
        'label' => '财务',
        'items' => [
            'fin.payment'   => ['name' => '付款申请', 'desc' => '对外付款审批', 'nodes' => ['部门主管', '财务', '总经理'], 'fields' => [['key' => 'payee', 'label' => '收款方', 'type' => 'text', 'required' => true], ['key' => 'amount', 'label' => '付款金额', 'type' => 'number', 'required' => true], ['key' => 'use', 'label' => '用途', 'type' => 'textarea', 'required' => true]]],
            'fin.receive'   => ['name' => '收款登记', 'desc' => '回款入账登记', 'nodes' => ['财务'], 'fields' => [['key' => 'from', 'label' => '付款方', 'type' => 'text', 'required' => true], ['key' => 'amount', 'label' => '金额', 'type' => 'number', 'required' => true]]],
            'fin.reimburse' => ['name' => '费用报销', 'desc' => '员工费用报销', 'nodes' => ['直属主管', '财务'], 'fields' => [['key' => 'amount', 'label' => '报销金额', 'type' => 'number', 'required' => true], ['key' => 'type', 'label' => '费用类型', 'type' => 'select', 'options' => ['差旅', '招待', '办公', '其他'], 'required' => true], ['key' => 'detail', 'label' => '明细说明', 'type' => 'textarea', 'required' => true]]],
            'fin.loan'      => ['name' => '借款申请', 'desc' => '员工借款', 'nodes' => ['直属主管', '财务', '总经理'], 'fields' => [['key' => 'amount', 'label' => '借款金额', 'type' => 'number', 'required' => true], ['key' => 'reason', 'label' => '借款事由', 'type' => 'textarea', 'required' => true]]],
            'fin.repay'     => ['name' => '还款申请', 'desc' => '借款归还', 'nodes' => ['财务'], 'fields' => [['key' => 'amount', 'label' => '还款金额', 'type' => 'number', 'required' => true]]],
            'fin.advance'   => ['name' => '预付款申请', 'desc' => '供应商 / 客户预付款', 'nodes' => ['部门主管', '财务'], 'fields' => [['key' => 'party', 'label' => '往来方', 'type' => 'text', 'required' => true], ['key' => 'amount', 'label' => '预付金额', 'type' => 'number', 'required' => true]]],
            'fin.budget'    => ['name' => '预算调整', 'desc' => '部门预算变更', 'nodes' => ['部门主管', '财务', '总经理'], 'fields' => [['key' => 'dept', 'label' => '部门', 'type' => 'text', 'required' => true], ['key' => 'delta', 'label' => '调整额度', 'type' => 'number', 'required' => true]]],
            'fin.reconcile' => ['name' => '对账差异处理', 'desc' => '账目差异核实与处理', 'nodes' => ['财务主管'], 'fields' => [['key' => 'account', 'label' => '科目', 'type' => 'text', 'required' => true], ['key' => 'diff', 'label' => '差异金额', 'type' => 'number', 'required' => true], ['key' => 'reason', 'label' => '差异说明', 'type' => 'textarea', 'required' => true]]],
        ],
    ],
    'hr' => [
        'label' => '人事',
        'items' => [
            'hr.recruit'   => ['name' => '招聘需求', 'desc' => '岗位招聘申请', 'nodes' => ['部门主管', 'HR', '总经理'], 'fields' => [['key' => 'position', 'label' => '岗位', 'type' => 'text', 'required' => true], ['key' => 'count', 'label' => '人数', 'type' => 'number', 'required' => true], ['key' => 'reason', 'label' => '需求说明', 'type' => 'textarea']]],
            'hr.onboard'   => ['name' => '入职登记', 'desc' => '新员工入职办理', 'nodes' => ['HR'], 'fields' => [['key' => 'name', 'label' => '姓名', 'type' => 'text', 'required' => true], ['key' => 'dept', 'label' => '部门', 'type' => 'text', 'required' => true]]],
            'hr.confirm'   => ['name' => '转正申请', 'desc' => '试用期转正', 'nodes' => ['直属主管', 'HR', '总经理'], 'fields' => [['key' => 'name', 'label' => '员工', 'type' => 'text', 'required' => true], ['key' => 'summary', 'label' => '转正总结', 'type' => 'textarea', 'required' => true]]],
            'hr.resign'    => ['name' => '离职申请', 'desc' => '员工离职', 'nodes' => ['直属主管', 'HR', '总经理'], 'fields' => [['key' => 'name', 'label' => '员工', 'type' => 'text', 'required' => true], ['key' => 'reason', 'label' => '离职原因', 'type' => 'textarea', 'required' => true]]],
            'hr.transfer'  => ['name' => '调岗申请', 'desc' => '员工岗位调动', 'nodes' => ['直属主管', 'HR'], 'fields' => [['key' => 'name', 'label' => '员工', 'type' => 'text', 'required' => true], ['key' => 'to', 'label' => '目标岗位', 'type' => 'text', 'required' => true]]],
            'hr.leave'     => ['name' => '请假申请', 'desc' => '各类请假', 'nodes' => ['直属主管', 'HR'], 'fields' => [['key' => 'type', 'label' => '假别', 'type' => 'select', 'options' => ['事假', '病假', '年假', '调休'], 'required' => true], ['key' => 'start', 'label' => '开始日期', 'type' => 'date', 'required' => true], ['key' => 'days', 'label' => '天数', 'type' => 'number', 'required' => true]]],
            'hr.travel'    => ['name' => '出差申请', 'desc' => '因公出差', 'nodes' => ['直属主管', 'HR'], 'fields' => [['key' => 'to', 'label' => '目的地', 'type' => 'text', 'required' => true], ['key' => 'start', 'label' => '出发日期', 'type' => 'date', 'required' => true], ['key' => 'days', 'label' => '天数', 'type' => 'number', 'required' => true]]],
            'hr.ot'        => ['name' => '加班申请', 'desc' => '加班登记', 'nodes' => ['直属主管'], 'fields' => [['key' => 'date', 'label' => '日期', 'type' => 'date', 'required' => true], ['key' => 'hours', 'label' => '小时数', 'type' => 'number', 'required' => true]]],
            'hr.salary'    => ['name' => '薪资调整', 'desc' => '调薪申请', 'nodes' => ['直属主管', 'HR', '总经理'], 'fields' => [['key' => 'name', 'label' => '员工', 'type' => 'text', 'required' => true], ['key' => 'delta', 'label' => '调整额', 'type' => 'number', 'required' => true]]],
            'hr.train'     => ['name' => '培训申请', 'desc' => '培训需求', 'nodes' => ['部门主管', 'HR'], 'fields' => [['key' => 'topic', 'label' => '培训主题', 'type' => 'text', 'required' => true], ['key' => 'budget', 'label' => '预算', 'type' => 'number']]],
            'hr.attend'    => ['name' => '考勤异常申诉', 'desc' => '考勤异常处理', 'nodes' => ['直属主管', 'HR'], 'fields' => [['key' => 'date', 'label' => '日期', 'type' => 'date', 'required' => true], ['key' => 'reason', 'label' => '申诉说明', 'type' => 'textarea', 'required' => true]]],
        ],
    ],
    'adm' => [
        'label' => '行政',
        'items' => [
            'adm.seal'      => ['name' => '用印申请', 'desc' => '公章 / 合同章使用', 'nodes' => ['部门主管', '行政', '总经理'], 'fields' => [['key' => 'type', 'label' => '用印类型', 'type' => 'select', 'options' => ['公章', '合同章', '财务章'], 'required' => true], ['key' => 'doc', 'label' => '用印文件', 'type' => 'text', 'required' => true]]],
            'adm.sealuse'   => ['name' => '印章使用', 'desc' => '印章外带 / 使用登记', 'nodes' => ['行政', '总经理'], 'fields' => [['key' => 'seal', 'label' => '印章', 'type' => 'text', 'required' => true], ['key' => 'reason', 'label' => '用途', 'type' => 'textarea', 'required' => true]]],
            'adm.meeting'   => ['name' => '会议室预订', 'desc' => '会议室预约', 'nodes' => ['行政'], 'fields' => [['key' => 'room', 'label' => '会议室', 'type' => 'text', 'required' => true], ['key' => 'date', 'label' => '日期', 'type' => 'date', 'required' => true], ['key' => 'hours', 'label' => '时段', 'type' => 'text', 'required' => true]]],
            'adm.car'       => ['name' => '用车申请', 'desc' => '公务用车', 'nodes' => ['部门主管', '行政'], 'fields' => [['key' => 'to', 'label' => '目的地', 'type' => 'text', 'required' => true], ['key' => 'date', 'label' => '日期', 'type' => 'date', 'required' => true]]],
            'adm.asset_get' => ['name' => '资产领用', 'desc' => '固定资产领用', 'nodes' => ['部门主管', '行政'], 'fields' => [['key' => 'asset', 'label' => '资产名称', 'type' => 'text', 'required' => true], ['key' => 'qty', 'label' => '数量', 'type' => 'number', 'required' => true]]],
            'adm.asset_back'=> ['name' => '资产归还', 'desc' => '资产归还', 'nodes' => ['行政'], 'fields' => [['key' => 'asset', 'label' => '资产名称', 'type' => 'text', 'required' => true]]],
            'adm.asset_scrap'=>['name' => '资产报废', 'desc' => '资产报废处置', 'nodes' => ['部门主管', '行政', '总经理'], 'fields' => [['key' => 'asset', 'label' => '资产名称', 'type' => 'text', 'required' => true], ['key' => 'reason', 'label' => '报废原因', 'type' => 'textarea', 'required' => true]]],
            'adm.card'      => ['name' => '名片申请', 'desc' => '员工名片印制', 'nodes' => ['部门主管', '行政'], 'fields' => [['key' => 'name', 'label' => '姓名', 'type' => 'text', 'required' => true], ['key' => 'title', 'label' => '职务', 'type' => 'text']]],
            'adm.license'   => ['name' => '证照借用', 'desc' => '营业执照等证照借用', 'nodes' => ['行政', '总经理'], 'fields' => [['key' => 'license', 'label' => '证照', 'type' => 'text', 'required' => true], ['key' => 'reason', 'label' => '用途', 'type' => 'textarea', 'required' => true]]],
        ],
    ],
    'sales' => [
        'label' => '销售',
        'items' => [
            'sales.quote'   => ['name' => '销售报价审批', 'desc' => '对外报价评审', 'nodes' => ['销售主管', '财务'], 'fields' => [['key' => 'customer', 'label' => '客户', 'type' => 'text', 'required' => true], ['key' => 'amount', 'label' => '报价金额', 'type' => 'number', 'required' => true]]],
            'sales.contract'=> ['name' => '销售合同评审', 'desc' => '销售合同评审', 'nodes' => ['销售主管', '法务', '总经理'], 'fields' => [['key' => 'customer', 'label' => '客户', 'type' => 'text', 'required' => true], ['key' => 'amount', 'label' => '合同金额', 'type' => 'number', 'required' => true]]],
            'sales.return'  => ['name' => '销售退货审批', 'desc' => '销售退货处理', 'nodes' => ['销售主管', '财务'], 'fields' => [['key' => 'order_no', 'label' => '订单号', 'type' => 'text', 'required' => true], ['key' => 'qty', 'label' => '退货数量', 'type' => 'number', 'required' => true], ['key' => 'reason', 'label' => '退货原因', 'type' => 'textarea', 'required' => true]]],
            'sales.credit'  => ['name' => '客户信用额度', 'desc' => '授予客户信用额度', 'nodes' => ['销售主管', '财务', '总经理'], 'fields' => [['key' => 'customer', 'label' => '客户', 'type' => 'text', 'required' => true], ['key' => 'limit', 'label' => '额度', 'type' => 'number', 'required' => true]]],
            'sales.discount'=> ['name' => '折扣审批', 'desc' => '特殊折扣申请', 'nodes' => ['销售主管', '财务'], 'fields' => [['key' => 'order_no', 'label' => '订单号', 'type' => 'text', 'required' => true], ['key' => 'pct', 'label' => '折扣%', 'type' => 'number', 'required' => true]]],
        ],
    ],
    'warehouse' => [
        'label' => '仓库',
        'items' => [
            'wh.transfer'  => ['name' => '仓库调拨审批', 'desc' => '跨仓库存调拨', 'nodes' => ['仓库主管', '物流'], 'fields' => [['key' => 'from', 'label' => '调出仓', 'type' => 'text', 'required' => true], ['key' => 'to', 'label' => '调入仓', 'type' => 'text', 'required' => true], ['key' => 'sku', 'label' => 'SKU', 'type' => 'text', 'required' => true], ['key' => 'qty', 'label' => '数量', 'type' => 'number', 'required' => true]]],
            'wh.assemble'  => ['name' => '组装拆卸单', 'desc' => 'BOM 组装 / 拆卸', 'nodes' => ['仓库主管'], 'fields' => [['key' => 'product', 'label' => '成品', 'type' => 'text', 'required' => true], ['key' => 'qty', 'label' => '数量', 'type' => 'number', 'required' => true]]],
            'wh.stocktake' => ['name' => '库存盘点', 'desc' => '定期盘点', 'nodes' => ['仓库主管'], 'fields' => [['key' => 'warehouse', 'label' => '仓库', 'type' => 'text', 'required' => true], ['key' => 'date', 'label' => '盘点日期', 'type' => 'date', 'required' => true]]],
            'wh.loss'      => ['name' => '报损报溢', 'desc' => '库存盈亏处理', 'nodes' => ['仓库主管', '财务'], 'fields' => [['key' => 'sku', 'label' => 'SKU', 'type' => 'text', 'required' => true], ['key' => 'qty', 'label' => '数量', 'type' => 'number', 'required' => true], ['key' => 'reason', 'label' => '原因', 'type' => 'textarea', 'required' => true]]],
            'wh.other'     => ['name' => '其他出入库', 'desc' => '非订单类出入库', 'nodes' => ['仓库主管'], 'fields' => [['key' => 'type', 'label' => '类型', 'type' => 'select', 'options' => ['入库', '出库'], 'required' => true], ['key' => 'sku', 'label' => 'SKU', 'type' => 'text', 'required' => true], ['key' => 'qty', 'label' => '数量', 'type' => 'number', 'required' => true]]],
        ],
    ],
    'mes' => [
        'label' => '生产',
        'items' => [
            'mes.order'   => ['name' => '生产工单', 'desc' => '生产任务下达', 'nodes' => ['生产主管', '计划'], 'fields' => [['key' => 'product', 'label' => '产品', 'type' => 'text', 'required' => true], ['key' => 'qty', 'label' => '数量', 'type' => 'number', 'required' => true]]],
            'mes.outsource'=>['name' => '委外加工', 'desc' => '外发加工', 'nodes' => ['生产主管', '采购'], 'fields' => [['key' => 'product', 'label' => '产品', 'type' => 'text', 'required' => true], ['key' => 'qty', 'label' => '数量', 'type' => 'number', 'required' => true]]],
            'mes.qc'      => ['name' => '质量检验', 'desc' => '质检工单', 'nodes' => ['质检主管'], 'fields' => [['key' => 'batch', 'label' => '批次', 'type' => 'text', 'required' => true], ['key' => 'result', 'label' => '结论', 'type' => 'select', 'options' => ['合格', '不合格'], 'required' => true]]],
            'mes.maint'   => ['name' => '设备保养', 'desc' => '设备维护申请', 'nodes' => ['设备主管'], 'fields' => [['key' => 'device', 'label' => '设备', 'type' => 'text', 'required' => true], ['key' => 'type', 'label' => '保养类型', 'type' => 'select', 'options' => ['日常', '定期', '大修']]]],
            'mes.report'  => ['name' => '工序报工', 'desc' => '工序完工报工', 'nodes' => ['生产主管'], 'fields' => [['key' => 'order', 'label' => '工单', 'type' => 'text', 'required' => true], ['key' => 'qty', 'label' => '合格数', 'type' => 'number', 'required' => true]]],
        ],
    ],
    'retail' => [
        'label' => '零售',
        'items' => [
            'retail.pos'     => ['name' => '门店开单', 'desc' => '门店零售开单', 'nodes' => ['店长'], 'fields' => [['key' => 'store', 'label' => '门店', 'type' => 'text', 'required' => true], ['key' => 'amount', 'label' => '金额', 'type' => 'number', 'required' => true]]],
            'retail.refund'  => ['name' => '零售退货', 'desc' => '零售退货', 'nodes' => ['店长'], 'fields' => [['key' => 'receipt', 'label' => '小票号', 'type' => 'text', 'required' => true], ['key' => 'amount', 'label' => '退款金额', 'type' => 'number', 'required' => true]]],
            'retail.member'  => ['name' => '会员储值', 'desc' => '会员充值', 'nodes' => ['店长'], 'fields' => [['key' => 'member', 'label' => '会员', 'type' => 'text', 'required' => true], ['key' => 'amount', 'label' => '储值额', 'type' => 'number', 'required' => true]]],
        ],
    ],
    'pm' => [
        'label' => '项目',
        'items' => [
            'pm.init'    => ['name' => '项目立项', 'desc' => '项目启动评审', 'nodes' => ['部门主管', 'PMO', '总经理'], 'fields' => [['key' => 'name', 'label' => '项目名称', 'type' => 'text', 'required' => true], ['key' => 'budget', 'label' => '预算', 'type' => 'number', 'required' => true]]],
            'pm.task'    => ['name' => '任务分派', 'desc' => '项目任务分配', 'nodes' => ['项目经理'], 'fields' => [['key' => 'task', 'label' => '任务', 'type' => 'text', 'required' => true], ['key' => 'owner', 'label' => '负责人', 'type' => 'text', 'required' => true]]],
            'pm.timesheet'=>['name' => '工时填报', 'desc' => '项目工时登记', 'nodes' => ['项目经理'], 'fields' => [['key' => 'project', 'label' => '项目', 'type' => 'text', 'required' => true], ['key' => 'hours', 'label' => '工时', 'type' => 'number', 'required' => true]]],
        ],
    ],
    'general' => [
        'label' => '通用 / IT',
        'items' => [
            'gen.kb'        => ['name' => '知识库发布', 'desc' => '知识文档发布', 'nodes' => ['部门主管'], 'fields' => [['key' => 'title', 'label' => '标题', 'type' => 'text', 'required' => true]]],
            'gen.forum'     => ['name' => '论坛发帖', 'desc' => '内部论坛发帖', 'nodes' => ['版主'], 'fields' => [['key' => 'title', 'label' => '标题', 'type' => 'text', 'required' => true]]],
            'gen.survey'    => ['name' => '问卷发布', 'desc' => '问卷 / 投票发布', 'nodes' => ['部门主管'], 'fields' => [['key' => 'title', 'label' => '标题', 'type' => 'text', 'required' => true]]],
            'gen.report'    => ['name' => '报表发布', 'desc' => '自定义报表发布', 'nodes' => ['部门主管'], 'fields' => [['key' => 'title', 'label' => '报表名', 'type' => 'text', 'required' => true]]],
            'gen.mall'      => ['name' => '商城上架', 'desc' => '商品上架审批', 'nodes' => ['运营主管', '财务'], 'fields' => [['key' => 'sku', 'label' => 'SKU', 'type' => 'text', 'required' => true], ['key' => 'price', 'label' => '售价', 'type' => 'number', 'required' => true]]],
            'gen.refund'    => ['name' => '退款审批', 'desc' => '平台 / 订单退款', 'nodes' => ['客服主管', '财务'], 'fields' => [['key' => 'order_no', 'label' => '订单号', 'type' => 'text', 'required' => true], ['key' => 'amount', 'label' => '退款额', 'type' => 'number', 'required' => true]]],
            'gen.ticket'    => ['name' => '客服工单', 'desc' => '客服问题工单', 'nodes' => ['客服主管'], 'fields' => [['key' => 'title', 'label' => '问题', 'type' => 'text', 'required' => true]]],
            'gen.aftersale' => ['name' => '云售后工单', 'desc' => '售后工单', 'nodes' => ['售后主管'], 'fields' => [['key' => 'order_no', 'label' => '订单号', 'type' => 'text', 'required' => true], ['key' => 'desc', 'label' => '问题描述', 'type' => 'textarea', 'required' => true]]],
            'gen.contract'  => ['name' => '合同评审', 'desc' => '通用合同评审', 'nodes' => ['法务', '总经理'], 'fields' => [['key' => 'title', 'label' => '合同名', 'type' => 'text', 'required' => true], ['key' => 'amount', 'label' => '金额', 'type' => 'number']]],
            'gen.dataexport'=> ['name' => '数据导出申请', 'desc' => '敏感数据导出', 'nodes' => ['部门主管', '安全'], 'fields' => [['key' => 'scope', 'label' => '导出范围', 'type' => 'textarea', 'required' => true]]],
            'gen.perm'      => ['name' => '系统权限申请', 'desc' => '账号 / 权限开通', 'nodes' => ['部门主管', 'IT'], 'fields' => [['key' => 'account', 'label' => '账号', 'type' => 'text', 'required' => true], ['key' => 'perm', 'label' => '权限', 'type' => 'text', 'required' => true]]],
        ],
    ],
];
