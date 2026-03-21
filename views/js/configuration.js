$(function(){
	$('.import_brazil').click(function(){
		if (!confirm('Essa ação irá criar as faixas de CEP para as capitais e interior dos estados do Brasil. Se já houverem zonas com os mesmos nomes elas serão excluídas. Confirma?')) {
			return false;
		}

		var spinHandle = loadingOverlay().activate();

		$.ajax({
			url: location.href,
			dataType: 'JSON',
			data: {
				importFromBrazil: 1
			}
		})
		.then(function(data){
			if (data.success) {
				$.growl.notice({title: '', message: 'Regiões criadas com sucesso.'});
			} else if (typeof data.error !== 'undefined') {
				$.growl.error({title: '', message: data.error});
			} else {
				$.growl.error({title: '', message: 'Ocorreu um erro inesperado.'});
			}
		})
		.fail(function(){
			$.growl.error({title: '', message: 'Ocorreu um erro inesperado.'});
		})
		.always(function(){
			loadingOverlay().cancel(spinHandle);
		});
	});
});