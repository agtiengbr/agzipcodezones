$(function(){
	var form = $('#form-zone');
	var table = form.find('table.zone');
	var modal;
	var id_zone;

	function addButtonToRow(row)
	{
		var dropdown = $(row).find('.dropdown-menu');

		var new_item = $('<li/>');
		var link = $('<a/>', {
			class: 'agzipcodezones',
			title: agzipcodezones.translations.button_title,
			text: agzipcodezones.translations.button_title,
			href: '#'
		});

		link.prepend($('<i/>', {
			class: 'icon-pencil'
		}));

		$(new_item).append(link);
		dropdown.append(new_item);
	}

	function createModal()
	{
		modal = "" +
			"<div class='bootstrap modal fade agzipcodezones'>" +
				"<div class='modal-dialog'>" +
					"<div class='modal-content'>" +
						"<div class='modal-body'>" +
								"<h2></h2>" + 
								"<div class='alert alert-info'>Não incluir hífens nem pontos, apenas os dígitos dos CEPs.</div>" +
								"<div class='intervals-list'>" +
								"</div>" +
								"<button class='btn btn-primary btn-new-interval'>" +
									"<i class='icon-plus'></i>" +
									'Novo Intervalo' +
								"</button>" +
						"</div>" +						
					"</div>" +				
				"</div>" +
			"</div>";

		modal = $(modal);
		modal.appendTo($('body'));
	}

	function fillIntervals(intervals)
	{	
		var html = "";
		$.each(intervals, function(key, value){
			html += "<div class='row' data-id='" + value.id + "'>";
			html += "<span>" + agzipcodezones.translations.from + ' </span><input class="form-control" type="number" value="' +
				    value.zipcode_begin + '" step="1"/><span>' + agzipcodezones.translations.to + '</span><input class="form-control" type="number" value="' +
				    value.zipcode_end + '" step="1"/><button class="btn btn-default delete-interval"><i class="icon-trash"></i></button>';
			html += "</div>";				   
		});

		$(modal).find('.intervals-list').html(html);
	}

	function getIntervalsFromZone(id_zone)
	{
		return new Promise(function(resolve, reject){
			$.ajax({
				url: 'ajax-tab.php',
				dataType: 'json',
				data: {
					ajax: true,
					controller: 'AgZipcodeZonesInterval',
					token: agzipcodezones.tokens.agzipcodezones,
					action: 'getByZone',

					id_zone: id_zone
				},
				success: function(data){
					if (data.success) {
						resolve(data.intervals);
					} else {
						reject(new Error(data.error));
					}
				},
				error: function() {
					reject(new Error(agzipcodezones.translations.error));
				}
			});
		});	
	}

	function deleteInterval(id)
	{
		return new Promise(function(resolve, reject){
			$.ajax({
				url: 'ajax-tab.php',
				dataType: 'json',
				data: {
					ajax: true,
					controller: 'AgZipcodeZonesInterval',
					token: agzipcodezones.tokens.agzipcodezones,
					action: 'delete',

					id: id
				},
				success: function(data){
					if (data.success) {
						resolve();
					} else {
						reject(new Error(data.error));
					}
				},
				error: function() {
					reject(new Error(agzipcodezones.translations.error));
				}
			});
		});	
	}
	
	function saveInterval(interval)
	{
		return new Promise(function(resolve, reject){
			$.ajax({
				url: 'ajax-tab.php',
				dataType: 'json',
				data: {
					ajax: true,
					controller: 'AgZipcodeZonesInterval',
					token: agzipcodezones.tokens.agzipcodezones,
					action: 'save',

					range: interval.range,
					id_zone: interval.id_zone
				},
				success: function(data){
					if (data.success) {
						resolve(data.id);
					} else {
						reject(new Error(data.error));
					}
				},
				error: function() {
					reject(new Error(agzipcodezones.translations.error));
				}
			});
		});	
	}


	$(table).find('tbody tr').each(function(){
		addButtonToRow($(this));
	});
	createModal();

	$('a.agzipcodezones').click(function(){
		//obtém o id da região selecionada
		var td = $(this).closest('td');
		var btn_edit = $(td).find('a.edit');

		id_zone = btn_edit[0].href.match("id_zone=([0-9]*)")[1];

		var td_zone = $(this).closest('tr').find('td')[2];
		var name_zone = $.trim($(td_zone).text());
		
		getIntervalsFromZone(id_zone)
			.then(function(intervals){
				$(modal).modal('show');
				$(modal).find('h2').text(name_zone);

				fillIntervals(intervals);
			})
			.catch(function(error){
				$.growl.error({ title: "", message: error.message});
			});
	});

	$('.btn-new-interval').click(function(){
		var html = "<div class='row'>";
		html += "<span>" + agzipcodezones.translations.from + ' </span><input class="form-control" type="number" step="1"/><span>' + agzipcodezones.translations.to +
				'</span><input class="form-control" type="number" step="1"/><button class="btn btn-default delete-interval"><i class="icon-trash"></i></button>';
		html += "</div>";

		$('.intervals-list').append($(html));
	});

	$('.intervals-list input').live('change', function(){	
		var div = $(this).closest('.row');
		var inputs = $(div).find('input');

		var input_begin = $(inputs[0]);
		var input_end = $(inputs[1]);

		if (input_begin.val() == '' || input_end.val() == '') {
			return;
		}

		var that = this;
		let intervals = [];
		$('.intervals-list .row').each(function(){
			let inputs = $(this).find('input');
			intervals.push({
				min: inputs[0].value,
				max: inputs[1].value
			});
		})

		saveInterval({
			range: intervals,
			id_zone: id_zone,
			id: $(div).attr('data-id')
		})
		.then(function(){
			$.growl.notice({ title: "", message: agzipcodezones.translations.saved_with_success});
		})
		.catch(function(error){
			$.growl.error({ title: "", message: error.message});
		});	
	});

	$('.intervals-list .delete-interval').live('click', function(){
		if (window.confirm(agzipcodezones.translations.are_you_sure)) {
			var div = $(this).closest('.row');
			var id_interval = $(div).attr('data-id');

			if (!id_interval) {
				$(this).closest('.row').remove();
				return;
			}

			var that = this;

			deleteInterval(id_interval)
				.then(function(){
					$.growl.notice({ title: "", message: agzipcodezones.translations.removed_with_success});
					$(that).closest('.row').remove();
				})
				.catch(function(error){
					$.growl.error({ title: "", message: error.message});
				});
		}
	});

	$('.agzipcodezones input').live('keypress', function(e){
		//evita caracteres '+', '-' e '.'
		if (e.which == 46 || e.keyCode == 46 || e.which == 45 || e.keyCode == 45 || e.which == 43 || e.keyCode == 43) {
			return false;
		}

		return true;
	});

	// 	return false;
	// });

	// var form_groups_begin = $('.form-group.zipcode_begin');
	// var form_groups_end = $('.form-group.zipcode_end');

	// var qtt_intervals = $('.form-group.zipcode_begin').length;

	// function addInterval()
	// {
	// 	var well = $(form_groups_begin).closest('.well').clone();
	// 	well.find('input').val('');
	// 	well.appendTo($('#zone_form .form-wrapper'));
	// 	qtt_intervals = $('.form-group.zipcode_begin').length;
	// }

	// $('.add_zipcode_interval').live('click', function(){
	// 	addInterval();
	// 	return false;
	// });

	// $('.remove_zipcode_interval').live('click', function(){
	// 	if (qtt_intervals == 1) {
	// 		$.growl.error({ title: "", message: agzipcodezones.translations.last_interval_remove_error});
	// 	} else if (window.confirm(agzipcodezones.translations.are_you_sure)) {
	// 		$(this).closest('.well').remove()		
	// 	}

	// 	qtt_intervals = $('.form-group.zipcode_begin').length;

	// 	return false;
	// });

	// for (var i = 0; i < qtt_intervals; i++) {
	// 	var well = $('<div/>', {
	// 		class : 'well row'
	// 	});

	// 	var btn_add = $('<button/>', {
	// 		class: 'btn btn-default pull-right add_zipcode_interval'			
	// 	}).append($('<i/>', {
	// 		class: 'icon-plus'
	// 	}));

	// 	var btn_cancel = $('<button/>', {
	// 		class: 'btn btn-default pull-right remove_zipcode_interval'			
	// 	}).append($('<i/>', {
	// 		class: 'icon-trash'
	// 	}));

	// 	well
	// 		.append(form_groups_begin[i])
	// 		.append(form_groups_end[i])
	// 		.append(btn_add)
	// 		.append(btn_cancel)
	// 		.appendTo($('#zone_form .form-wrapper'));

	// 	$(form_groups_begin[i]).find('input').attr('name', 'zipcode_begin[]');
	// 	$(form_groups_end[i]).find('input').attr('name', 'zipcode_end[]');
	// }
});