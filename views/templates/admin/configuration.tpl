<ps-alert-hint>Se tiver dúvidas com a configuração do módulo por favor acesse <a href="https://agtieng.atlassian.net/wiki/spaces/AGTI/pages/8650757/Primeiros+Passos+com+o+m+dulo+de+Faixas+de+CEP" target="_blank">esse link</a> para uma breve descrição.</ps-alert-hint>

<ul class="nav nav-tabs" role="tablist">
    <li class='active'>
        <a data-toggle="tab" href="#tabRanges">
            <i class="icon-map-marker"></i> FAIXAS DE CEP
        </a>
    </li>

    <li>
        <a data-toggle="tab" href="#tabSimulation">
            <i class="icon-cogs"></i> SIMULAÇÃO DE FRETE
        </a>
    </li>
</ul>
<div class='tab-content'>
    <div class='tab-pane active in' id="tabRanges">
        <div class='panel'>
            <form name='' class="form-horizontal" method="post" action="{$form_action|escape:'htmlall':'utf-8'}" enctype='multipart/form-data'>
    
                <ps-alert-hint data-alertClass='info'>Você pode importar as faixas de CEP para a sua loja através de um arquivo CSV. Para baixar o arquivo de modelo <a href='{$csv_sample_path}'> clique aqui</a>. Se as regiões não existirem em sua loja elas serão criadas; se elas existirem as faixas de CEP serão eliminadas antes da importação. Se utilizar acentos o arquivo precisa estar no formato UTF-8.</ps-alert-hint>

                <div class='form-group'>
                    <label>Enviar Arquivo CSV</label>
                    <input type="file" name="zipcodes_csv"></ps-panel>
                </div>

                <p>
                    OU clique no botão abaixo para crias as faixas de CEP dos estados e capitais do Brasil.
                </p>
                <button type="button" class="btn btn-default import_brazil">Criar Faixas de CEP do Brasil</button>

                <ps-panel-footer>
                    <ps-panel-footer-submit direction="left" title="Cancelar" icon='process-icon-cancel'></ps-panel-footer-submit>
                    <ps-panel-footer-submit direction="right" title="Salvar" icon='process-icon-save' name="agzipcodezones-save"></ps-panel-footer-submit>
                </ps-panel-footer>
            </form>
        </div>
    </div>
    <div class='tab-pane' id="tabSimulation">{$tabs['simulation']}</div>  
</div>