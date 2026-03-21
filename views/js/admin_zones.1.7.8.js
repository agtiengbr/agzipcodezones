
window.addEventListener('load', function(){
	var table = $('#zone_grid_table');
	let open = false;
	var id_zone;
	let ranges = [];

	function addButtonToRow(row)
	{
		var dropdown = $(row).find('.dropdown-menu');

		var link = $('<a/>', {
			class: 'agzipcodezones btn tooltip-link js-submit-row-action dropdown-item grid-apagar-row-link',
			title: agzipcodezones.translations.button_title,
			text: agzipcodezones.translations.button_title,
			href: '#'
		});

		link.prepend($('<i/>', {
			class: 'material-icons',
			text: 'edit'
		}));

		dropdown.append(link);
	}

	function createModal()
	{
		let modalHtml = `
			<div id='agzipcodezones-modal'>
				<agmodal v-if="open">
					<template slot="body">
						<agti-zipcodes-grid v-on:range='rangeChanged' :rows='ranges' :api-url="urls.agcliente_zipcode"></
					agti-zipcodes-grid>
					</template>

					<template slot="footer">
						<button type="button" class="btn btn-primary" @click="save" v-bind:disabled="disableSubmit">Confirmar</button>
						<button type="button" class="btn btn-danger" @click="cancel" data-dismiss="modal">Cancelar</button>
					</template>
				</agmodal>
			</div>
		`;
		$('body').append(modalHtml);

		app = new Vue({
			el: '#agzipcodezones-modal',
			data: {
				open: open,
				disableSubmit: false,
				ranges: ranges,
				urls: {
					agcliente_zipcode: agzipcodezones.url_zipcode_component
				},
				errors: []
			},
			methods: {
				rangeChanged: function(ranges){
					this.ranges = ranges;
				},
				save: async function(){
					let data = new FormData;
					data.set('id_zone', id_zone);

					$.each(this.ranges, function(key, range) {
                        console.log('range: ', range);
						data.set(`range[${key}][min]`, range.min);
						data.set(`range[${key}][max]`, range.max);
						data.set(`range[${key}][region]`, range.zone);
						data.set(`range[${key}][state]`, range.state);
						data.set(`range[${key}][city]`, range.city.city);
						data.set(`range[${key}][neighborhood]`, range.neighborhood.neighborhood ? range.neighborhood.neighborhood : '');
					});

					let r = await axios.post(agzipcodezones.url_controller_intervals + '&action=save&ajax=true', data);
					if (!r.data.success) {
						$.growl.error({title: '', message: r.data.error});
					} else {
						this.errors = [];
						$.growl.notice({title: '', message: 'Faixas de CEP salvas com sucesso.'});
						this.open = false;
					}
				},
				cancel: function(){
					this.open = false;
				}
			},
            watch: {
                ranges: function() {
                    if(this.ranges) {
                        this.ranges.forEach((range) => {
                            range.city = {city: range.city, state: range.state};
                            range.neighborhood = {
                                neighborhood: range.neighborhood, 
                                city: range.city,
                                state: range.state
                            };

                            if(range.zipcode_begin) {
                                range.cep_start = range.zipcode_begin.padStart(8, "0");
                            }
                            
                            if(range.zipcode_end) {
                                range.cep_end = range.zipcode_end.padStart(8, "0");
                            }
                        });
                    }
                }
            }
		})
	}

	function fillIntervals(intervals)
	{	
		var html = "";
		$.each(intervals, function(key, value){
			html += "<div class='row' data-id='" + value.id + "'>";
			html += "<span>" + agzipcodezones.translations.from + ' </span><input class="form-control" type="number" value="' +
				    value.zipcode_begin + '" step="1"/><span>' + agzipcodezones.translations.to + '</span><input class="form-control" type="number" value="' +
				    value.zipcode_end + '" step="1"/><button class="btn btn-default delete-interval"><i class="material-icons">delete</i></button>';
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

					interval: interval
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
		var btn_edit = $(td).find('.material-icons:contains("edit")').parent();
		id_zone = $(btn_edit).attr('href').match("/zones/([0-9]*)")[1];

		getIntervalsFromZone(id_zone)
			.then(function(intervals){
				app.$data.ranges = intervals;
				app.$data.open = true;
			})
			.catch(function(error){
				app.modal = true;
				$.growl.error({ title: "", message: error.message});
			});

		return false;
	});

	$('.btn-new-interval').click(function(){
		var html = "<div class='row'>";
		html += "<span>" + agzipcodezones.translations.from + ' </span><input class="form-control" type="number" step="1"/><span>' + agzipcodezones.translations.to +
				'</span><input class="form-control" type="number" step="1"/><button class="btn btn-default delete-interval"><i class="material-icons">delete</i></button>';
		html += "</div>";

		$('.intervals-list').append($(html));
	});

	$('.intervals-list').on('change', 'input', function(){	
		var div = $(this).closest('.row');
		var inputs = $(div).find('input');

		var input_begin = $(inputs[0]);
		var input_end = $(inputs[1]);

		if (input_begin.val() == '' || input_end.val() == '') {
			return;
		}

		var that = this;

		if (parseInt(input_begin.val()) <= parseInt(input_end.val())) {
			saveInterval({
				zipcode_begin: input_begin.val(),
				zipcode_end: input_end.val(),
				id_zone: id_zone,
				id: $(div).attr('data-id')
			})
				.then(function(){
					$.growl.notice({ title: "", message: agzipcodezones.translations.saved_with_success});
				})
				.catch(function(error){
					$.growl.error({ title: "", message: error.message});
				});	
		};
	});

	$('.intervals-list').on('click', '.delete-interval', function(){
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

	$('.agzipcodezones ').on('keypress', 'input', function(e){
		//evita caracteres '+', '-' e '.'
		if (e.which == 46 || e.keyCode == 46 || e.which == 45 || e.keyCode == 45 || e.which == 43 || e.keyCode == 43) {
			return false;
		}

		return true;
	});
});
