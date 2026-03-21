<?php

class AgZipcodeZonesIntervalController extends ModuleAdminController
{
    public function ajaxProcessGetByZone()
    {
        try {
            $id_zone = Tools::getValue('id_zone');
            $intervals = AgZipcodeZonesInterval::getByZone($id_zone);

            echo json_encode(array(
                'intervals' => $intervals,
                'success' => 1
            ));
        } catch (Exception $e) {
            echo json_encode(array(
                'error' => $e->getMessage(),
                'success' => 0
            ));
        }

        exit();
    }

    public function ajaxProcessDelete()
    {
        $id = Tools::getValue('id');
        $interval = new AgZipcodeZonesInterval($id);
        $interval->delete();

        echo json_encode(array(
            'success' => 1
        ));
        
        exit();
    }

    public function ajaxProcessSave()
    {
        $intervals = Tools::getValue('range');
        $id_zone = Tools::getValue('id_zone');

        
        try {
            Db::getInstance()->execute("START TRANSACTION");

            Db::getInstance()->delete('agzipcodezones_interval', 'id_zone=' . (int)$id_zone);
            foreach ($intervals as $interval_data) {
                $obj = new AgZipcodeZonesInterval();
            
                $obj->zipcode_begin = preg_replace("/[^0-9]/", '', $interval_data['min']);
                $obj->zipcode_end = preg_replace("/[^0-9]/", '', $interval_data['max']);

                $obj->id_zone = $id_zone;
                $obj->id_shop = $this->context->shop->id;
                $obj->region = $interval_data['region'];
                $obj->state = $interval_data['state'];
                $obj->city = $interval_data['city'];
                $obj->neighborhood = $interval_data['neighborhood'];

                $obj->save();
            }

            
            Db::getInstance()->execute("COMMIT");
            echo json_encode([
                'success' => 1
            ]);
        } catch (Exception $e) {
            Db::getInstance()->execute("ROLLBACK");
            echo json_encode(array(
                'success' => 0,
                'error' => $e->getMessage()
            ));
        }
        
        exit();
    }

    public function ajaxProcessUploadCsv()
    {
        if (is_int((int) $id = Tools::getValue('id'))) {

            $file       = $_FILES['file-agzipcodezones'];
            $extension  = pathinfo($file['name'], PATHINFO_EXTENSION);
            $extension  = strtolower($extension);

            if (empty($file['size']) || $file['error'] != 0) {

                echo json_encode(['type' => 'error', 'message' => 'Erro: não foi possível fazer o upload!']);
                return;
            }

            if (!strstr('.csv', $extension)) {

                echo json_encode(['type' => 'error', 'message' => 'Erro: formato do arquivo é inválido!']);
                return;
            }

            $sql_weight   = "SELECT COUNT(*) FROM " . _DB_PREFIX_ . "range_weight WHERE id_carrier = {$id}";
            $sql_delivery = "SELECT COUNT(*) FROM " . _DB_PREFIX_ . "delivery WHERE id_carrier     = {$id}";
            $sql_zone     = "SELECT COUNT(*) FROM " . _DB_PREFIX_ . "carrier_zone WHERE id_carrier = {$id}";
            $carrier      = Db::getInstance()->getRow("SELECT shipping_method, is_free FROM " . _DB_PREFIX_ . "carrier WHERE id_carrier = {$id}");

            if ($carrier['shipping_method'] == Carrier::SHIPPING_METHOD_WEIGHT && $carrier['is_free'] != 1) {
				
                $handle     = fopen($_FILES['file-agzipcodezones']['tmp_name'], 'r');
                $handle_two = fopen($_FILES['file-agzipcodezones']['tmp_name'], 'r');
                fgetcsv($handle);
                fgetcsv($handle_two);

                if(Db::getInstance()->getValue($sql_zone)     > 0) Db::getInstance()->delete('carrier_zone', "id_carrier={$id}");
                if(Db::getInstance()->getValue($sql_delivery) > 0) Db::getInstance()->delete('delivery', "id_carrier={$id}");
                if(Db::getInstance()->getValue($sql_weight)   > 0) Db::getInstance()->delete('range_weight', "id_carrier={$id}");
                
                while (($line = fgetcsv($handle_two)) !== FALSE) {

                    $data   = explode(';', $line[0]);
                    $name = pSQL($data[0]);
                    $region = Db::getInstance()->getRow("SELECT id_zone FROM " . _DB_PREFIX_ ."zone WHERE name='$name'");

                    if($region == false){

                        echo json_encode(['type' => 'error', 'message' => "Erro: a região $data[0] não está cadastrada!"]);
                        return;
                    }

                }
                fclose($handle_two);

                while (($line = fgetcsv($handle)) !== FALSE) {

                    $data   = explode(';', utf8_encode($line[0]));
                    $region = Db::getInstance()->getRow("SELECT id_zone FROM " . _DB_PREFIX_ ."zone WHERE name='$data[0]'");
                    $zone   = Db::getInstance()->getRow("SELECT id_carrier FROM " . _DB_PREFIX_ ."carrier_zone WHERE id_carrier = $id AND id_zone=" . $region['id_zone']);

                    if(empty($zone['id_carrier'])){

                       $delivery = Db::getInstance()->insert('carrier_zone', [
                            'id_carrier' => $id, 
                            'id_zone'    => (int) $region['id_zone'],
                        ]); 
                    }
                    if((new RangeWeight())->rangeExist($id, $data[1], $data[2]) == 1){
                        
			            $id_range_weight = Db::getInstance()->getRow("SELECT id_range_weight FROM " . _DB_PREFIX_ . "range_weight WHERE delimiter1 = '$data[1]' AND delimiter2 = '$data[2]' AND id_carrier = $id");
                        $delivery        = Db::getInstance()->insert('delivery', [
                           'id_carrier'      => $id,
                           'id_range_weight' => (int) $id_range_weight['id_range_weight'],
                           'id_zone'         => (int) $region['id_zone'],
                           'price'           => $data[3],
                        ]);
						
                    }else{

                        $range_weight = new RangeWeight();
                        $range_weight->id_carrier = $id;
                        $range_weight->delimiter1 = $data[1]; 
                        $range_weight->delimiter2 = $data[2]; 
                        $range_weight->save();

                        if(Db::getInstance()->delete('delivery', "id_shop=1 AND id_shop_group=1 AND id_carrier=$id")){

                            $delivery = Db::getInstance()->insert('delivery', [
                               'id_carrier'      => $id,
                               'id_range_weight' => (int) $range_weight->id,
                               'id_zone'         => (int) $region['id_zone'],
                               'price'           => $data[3],
                            ]);
                        }
                    }
                }
                fclose($handle);

                if ($delivery) {

                    echo json_encode(['type' => 'success', 'message' => 'Seu arquivo foi importado com sucesso!']);
                    return;
                } else {

                    echo json_encode(['type' => 'error', 'message' => 'Erro: não foi possível processar o arquivo enviado!']);
                    return;
                }
            } else {

                echo json_encode(['type' => 'error', 'message' => 'Erro: essa transportadora não utiliza faixa de CEP por peso ou está configurada com frete grátis!']);
                return;
            }
        }
    }
}