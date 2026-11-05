/**
 * 晨雨ERP 插件 · 后台交互
 * 仅负责明细行的增删（订单 / 采购），无外部依赖。
 */
(function ($) {
	'use strict';

	function nextIndex($table) {
		var max = -1;
		$table.find('tbody tr').each(function () {
			$(this).find('input, select').each(function () {
				var name = $(this).attr('name') || '';
				var m = name.match(/\[(\d+)\]\[/);
				if (m) {
					max = Math.max(max, parseInt(m[1], 10));
				}
			});
		});
		return max + 1;
	}

	function reindex($table) {
		var i = 0;
		$table.find('tbody tr').each(function () {
			$(this).find('input, select').each(function () {
				var name = $(this).attr('name') || '';
				$(this).attr('name', name.replace(/\[(\d+)\]\[/, '[' + i + ']['));
			});
			i++;
		});
	}

	$(document).on('click', '.gw-add-row', function () {
		var $table = $(this).closest('form').find('.gw-items-table').first();
		if (!$table.length) {
			return;
		}
		var $last = $table.find('tbody tr').last();
		if (!$last.length) {
			return;
		}
		var idx = nextIndex($table);
		var $clone = $last.clone();
		$clone.find('input, select').each(function () {
			var name = $(this).attr('name') || '';
			$(this).attr('name', name.replace(/\[(\d+)\]\[/, '[' + idx + ']['));
			if ($(this).is('input[type="number"]')) {
				$(this).val($(this).attr('name').indexOf('qty') > -1 ? 1 : 0);
			}
		});
		$table.find('tbody').append($clone);
	});

	$(document).on('click', '.gw-remove-row', function () {
		var $table = $(this).closest('table');
		var $tr = $(this).closest('tr');
		if ($table.find('tbody tr').length <= 1) {
			return;
		}
		$tr.remove();
		reindex($table);
	});
})(jQuery);
