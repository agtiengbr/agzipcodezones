<?php

require_once _PS_MODULE_DIR_ . 'agcliente/lib/AgGridModule.php';
class BaseAgZipCodeZones extends AgGridModule
{
    protected $hooks = array(
        'displayBackOfficeHeader',
        'actionGetIDZoneByAddressID',
        'actionObjectZoneDeleteAfter',
        'AdminStatsModules'
    );

    public function __construct()
    {
        $this->name     = 'agzipcodezones';
        $this->tab      = 'shipping_logistics';
        $this->version  = '1.5.6';
        $this->author   = 'AGTI';

        $this->bootstrap = true;
        
        parent::__construct();

        $this->displayName = $this->l('Zones by ZipCode');
        $this->description = $this->l('This module allows you to register zipcodes for the regions of your PrestaShop store, allowing you to set custom shipping prices for each of them.');

        $this->columns = [
            [
                'id' => 'zone',
                'header' => 'Região',
                'dataIndex' => 'name',
                'align' => 'left'
            ],
            [
                'id' => 'totalQuantitySold',
                'header' => 'Total de Itens Vendidos',
                'dataIndex' => 'totalQuantitySold',
                'align' => 'center'
            ],
            [
                'id' => 'totalPriceSold',
                'header' => 'Valor Total vendido',
                'dataIndex' => 'totalPriceSold',
                'align' => 'center'
            ]
        ];
    }


    public function install()
    {
        $this->clearTplCache();
        
        return parent::install()
            && $this->addTab([]);
    }

    protected function addTab(
        $tabs = [],
        $id_parent = 0
    )
    {
        $tab_id = Tab::getIdFromClassName('AgZipcodeZonesInterval');

        if ($tab_id) {
            return true;
        }

        $tab             = new Tab();
        $tab->module     = $this->name;
        $tab->active     = 0;
        $tab->class_name = 'AgZipcodeZonesInterval';
        $tab->id_parent  = 0;

        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'Zipcode Zones Intervals';
        }

        return $tab->save();
    }

    public function uninstall()
    {
        $tab_id = Tab::getIdFromClassName('AgZipcodeZonesInterval');
        if ($tab_id) {
            $tab = new Tab($tab_id);
            $tab->delete();
        }

        return parent::uninstall();
    }

    public function getContent()
    {
        if (Tools::getIsSet('downloadSampleCSV')) {
            $this->generateSampleCSV();
            exit();
        }

        if (Tools::getIsSet('importFromBrazil')) {
            try {
                $this->importFromBrazil();

                echo json_encode([
                    'success' => true
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }

            exit();
        }

        try {
            if (isset($_FILES['zipcodes_csv'])) {
                set_time_limit(0);
                ignore_user_abort(0);
                if ($_FILES['zipcodes_csv']['error']) {
                    switch($_FILES['zipcodes_csv']['error']) {
                        case UPLOAD_ERR_INI_SIZE:
                            throw new Exception('O arquivo enviado é maior do que o limite permitido pelo seu servidor de hospedagem.');
                        case UPLOAD_ERR_NO_FILE:
                            goto after_csv;
                        case UPLOAD_ERR_CANT_WRITE:
                            throw new Exception('Erro ao salvar o arquivo. Talvez você tenha atingido o limite de espaço em disco de sua hospedagem.');
                        default:
                            throw new Exception('Ocorreu um erro não esperado no envio do arquivo CSV.');
                    }
                }

                $csvFile = file($_FILES['zipcodes_csv']['tmp_name']);
                $this->proccessCsv($csvFile);

                $success = 'Arquivo CSV processado com sucesso.';
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }                    

        after_csv:
        $agcliente = new agcliente;

        $this->context->smarty->assign([
            'tabs' => [
                'simulation' => $agcliente->renderShippingForm($this),
            ],
            'form_action' => $this->context->link->getAdminLink('AdminModules') . '&configure=' . $this->name,
            'csv_sample_path' => $this->context->link->getAdminLink('AdminModules') . '&configure=' . $this->name . '&downloadSampleCSV',
            'success' => @$success,
            'error' => @$error,
        ]);

        
        $this->context->controller->addCss([
            $this->_path . '/views/css/loadingOverlay.css',
        ]);

        $this->context->controller->addJs(array(
            '//cdn.jsdelivr.net/bluebird/3.5.0/bluebird.min.js',
            'https://cdnjs.cloudflare.com/ajax/libs/riot/2.6.7/riot+compiler.min.js',
            $this->_path . 'views/js/loadingOverlay.js',
            $this->_path . 'views/js/configuration.js',
        ));

        $html = $this->display(_PS_MODULE_DIR_ . $this->name, 'views/templates/admin/configuration.tpl');
        return $html . $this->display(_PS_MODULE_DIR_ . $this->name, 'views/templates/admin/ps-tags.tpl');
    }
  
    public static function postProcessIntervals()
    {
        AgZipcodeZonesInterval::deleteByZone(Tools::getValue('id_zone'));
                
        if (Tools::getIsSet('id_zone')) {
            $id_zone = Tools::getValue('id_zone');
        } else {
            $reflectedClass = new ReflectionClass($this->context->controller);
            $property       = $reflectedClass->getProperty('object');
            $property->setAccessible(true);

            $zone = $property->getValue($this->context->controller);
            $id_zone = $zone->id;
        }

        $intervals_begin = Tools::getValue('zipcode_begin');
        $intervals_end = Tools::getValue('zipcode_end');

        $qtt = count($intervals_begin);

        for ($i=0; $i<$qtt; $i++) {
            $instance = new AgZipcodeZonesInterval();
            $instance->zipcode_begin = $intervals_begin[$i];
            $instance->zipcode_end = $intervals_end[$i];
            $instance->id_zone = $id_zone;

            try {
                $instance->validateFields();
                $instance->add();
            } catch (Exception $e) {
                Logger::addLog($e->getMessage());
                $this->errors[] = $e->getMessage();
            }
        }
    }

    public function hookActionGetIDZoneByAddressID($params)
    {
        $id_address = $params['id_address'];
        $address = new Address($id_address);

        if (!Validate::isLoadedObject($address)) {
            return false;
        }

        $postcode = $address->postcode;
        $postcode = preg_replace("/[^0-9]/","",$postcode);
        $interval = AgZipcodeZonesInterval::getByZipcode($postcode);
        if (!Validate::isLoadedObject($interval)) {
            return false;
        }

        return $interval->id_zone;
    }

    public function clearTplCache()
    {
        parent::_clearCache('vars.tpl');
    }

    public function displayVarsJs()
    {
        $cache_id = $this->getCacheId();

        $this->context->smarty->assign(array(
            'token_agzipcodezones' => Tools::getAdminTokenLite('AgZipcodeZonesInterval'),
            'link' => Context::getContext()->link
        ));

        return $this->display(_PS_MODULE_DIR_ . $this->name, 'vars.tpl');
    }

    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('controller') === 'AdminCarriers' && !Tools::getIsSet('id_carrier')) {
            
            Media::addJsDef([
                'token_url'  => Tools::getAdminTokenLite('AdminModules'),
                'token_ajax' => Tools::getAdminTokenLite('AgZipcodeZonesInterval')
            ]);

            $this->path = __PS_BASE_URI__ . 'modules/agzipcodezones/';
            $this->context->controller->addJquery();
            $this->context->controller->addCss($this->path . 'views/css/admin_carriers.css');
            $this->context->controller->addJs($this->path  . 'views/js/admin_carriers.js');  
        }
        
        switch (Context::getContext()->controller->controller_name) {
            case 'AdminZones':
                $this->context->controller->addCss([
                    _PS_MODULE_DIR_ . "agcliente/views/css/agmodal.css",
                    _PS_MODULE_DIR_ . $this->name . '/views/css/admin_zones.css'
                ]);

                $this->context->controller->addJquery();
                $this->context->controller->addJs(array(
                    '//cdn.jsdelivr.net/bluebird/3.5.0/bluebird.min.js'
                ));

                if (version_compare(_PS_VERSION_, '1.7.8', '<')) {
                    $this->context->controller->addJs(array(
                        _PS_MODULE_DIR_ . $this->name . '/views/js/admin_zones.js'
                    ));
                } else {
                    $this->context->controller->addJs("https://cdn.jsdelivr.net/npm/vue@2.6.14/dist/vue.js");
                    $this->context->controller->addJs('https://cdnjs.cloudflare.com/ajax/libs/axios/0.21.1/axios.min.js');
                    $this->context->controller->addJs("https://cdn.jsdelivr.net/npm/maska@1.5.1/dist/maska.js");

                    $this->context->controller->addJs(_PS_MODULE_DIR_ . "agcliente/views/js/component/grid/table.js");
                    $this->context->controller->addJs(_PS_MODULE_DIR_ . "agcliente/views/js/component/grid/header.js");
                    $this->context->controller->addJs(_PS_MODULE_DIR_ . "agcliente/views/js/component/grid/body.js");
                    $this->context->controller->addJs(_PS_MODULE_DIR_ . "agcliente/views/js/component/loading/loading.vue.js");

                    // $this->addJs(_PS_MODULE_DIR_ . "agcliente/views/js/component/grid/switch.js");


                    $this->context->controller->addJs(_PS_MODULE_DIR_ . "agcliente/views/js/component/modal.js");
                    $this->context->controller->addJs(_PS_MODULE_DIR_ . "agcliente/views/js/component/zipcode_grid/row_actions.vue.js");
                    $this->context->controller->addJs(_PS_MODULE_DIR_ . "agcliente/views/js/component/zipcode_grid/states.vue.js");
                    $this->context->controller->addJs(_PS_MODULE_DIR_ . "agcliente/views/js/component/zipcode_grid/city_autocomplete_list.vue.js");
                    $this->context->controller->addJs(_PS_MODULE_DIR_ . "agcliente/views/js/component/zipcode_grid/cities.vue.js");
                    $this->context->controller->addJs(_PS_MODULE_DIR_ . "agcliente/views/js/component/zipcode_grid/zones.vue.js");
                    $this->context->controller->addJs(_PS_MODULE_DIR_ . "agcliente/views/js/component/zipcode_grid/zipcodes.vue.js");
                    $this->context->controller->addJs(_PS_MODULE_DIR_ . "agcliente/views/js/component/zipcode_grid/neighborhoods.vue.js");
                    $this->context->controller->addJs(_PS_MODULE_DIR_ . "agcliente/views/js/component/zipcode_grid/neighborhood_autocomplete_list.vue.js");
                    $this->context->controller->addJs(_PS_MODULE_DIR_ . "agcliente/views/js/component/zipcode_grid/component.vue.js");
            
                    $this->context->controller->addCss(_PS_MODULE_DIR_ . "agcliente/views/css/component/zipcode_grid.css");

                    $this->context->controller->addJs(_PS_MODULE_DIR_ . "agcliente/views/js/component/form/input-text.vue.js");
                    $this->context->controller->addJs(_PS_MODULE_DIR_ . "agcliente/views/js/component/form/autocomplete.vue.js");

                    $this->context->controller->addJs(array(
                        _PS_MODULE_DIR_ . $this->name . '/views/js/admin_zones.1.7.8.js'
                    ));
                }

                return $this->displayVarsJs();
        }
    }

    public function hookActionObjectZoneDeleteAfter($params)
    {
        $zone = $params['object'];

        $agzone = AgZipcodeZonesZone::getByZone($zone->id);
        $agzone->delete();

        AgZipcodeZonesInterval::deleteAllFromZone($zone->id);
    }

    public function hookAdminStatsModules($params)
    {
        $onlyChildren = (int)Tools::getValue('onlyChildren');

        $engine_params = array(
            'id' => 'id_category',
            'title' => $this->displayName,
            'columns' => $this->columns,
            'defaultSortColumn' => $this->default_sort_column,
            'defaultSortDirection' => $this->default_sort_direction,
            'emptyMessage' => $this->empty_message,
            'pagingMessage' => $this->paging_message,
            'customParams' => array(
                'onlyChildren' => $onlyChildren,
            )
        );

        $this->html = '
            <div class="panel-heading">
                <i class="icon-sitemap"></i> '.$this->displayName.'
            </div>
            '.$this->engine($engine_params);

        return $this->html;
    }

    protected function getData()
    {
        $date_between = $this->getDate();
        $id_lang = $this->getLang();
        $currency = new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));

        $sql = '
            SELECT z.name as name, COUNT(od.id_order_detail) as totalQuantitySold, SUM(od.total_price_tax_incl) as totalPriceSold FROM ' . _DB_PREFIX_ . 'zone z
            INNER JOIN ' . _DB_PREFIX_ . 'order_detail od ON od.id_zone = z.id_zone
            INNER JOIN ' . _DB_PREFIX_ . 'orders o ON o.id_order = od.id_order
            WHERE
                o.date_add BETWEEN ' . $date_between . '
            GROUP BY z.id_zone
        ';

        $this->_values = Db::getInstance()->executeS($sql);

        foreach ($this->_values as &$value) {
            $value['totalPriceSold'] = Tools::displayPrice($value['totalPriceSold'], $currency);
        }
    }

    protected function generateSampleCSV()
    {
        
        if (!Tools::getIsSet('fretes')) {

            $name        = 'zipcodes';
            $csv_columns = ['Nome da Regiao', 'CEP Inicio', 'CEP Fim'];
        }else{

            $name        = 'tabela_de_precos';
            $csv_columns = ['Nome da Regiao', 'Peso 1', 'Peso 2', 'Custo do frete'];
        }

   		header('Content-type: text/csv');
        header('Content-Type: application/force-download; charset=UTF-8');
        header('Cache-Control: no-store, no-cache');
        header('Content-disposition: attachment; filename="'.$name.'.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, $csv_columns, ';');
    }

    protected function proccessCsv(array $csv)
    {
        unset($csv[0]);

        $data = [];
        foreach ($csv as $line) {
            $data[] = str_getcsv($line, ';');
        }

        $processed_zones = [];

        foreach ($data as $i=>$row) {
            if ($row[0] == '') {
                continue;
            }

            try {
                $zone = AgZipcodeZonesZone::getZoneByName($row[0]);
                if (Validate::isLoadedObject($zone) && array_search($row[0], $processed_zones) === false) {
                    AgZipcodeZonesInterval::deleteAllFromZone($zone->id);
                    $processed_zones[] = $row[0];
                }

                $zone->name = $row[0];
                $zone->save();

                if (!Validate::isLoadedObject($zone)) {
                    throw new Exception("Erro salvando a região " . $i+1 . " - $row[0]");
                }

                $interval = new AgZipcodeZonesInterval;

                $row[1] = str_replace('-', '', $row[1]);
                $row[2] = str_replace('-', '', $row[2]);

                $interval->zipcode_begin = $row[1];
                $interval->zipcode_end   = $row[2];
                $interval->id_zone       = $zone->id;
                $interval->id_shop       = $this->context->shop->id;

                $interval->save();
            } catch (Exception $e) {
                $this->context->controller->errors[] = $e->getMessage();
            }
        }
    }

    protected function importFromBrazil()
    {
        AgZipcodeZonesBrStates::installZones();
    }

}
