<?php

class AgZipcodeZonesInterval extends AgObjectModel
{
    public static $definition = array(
        'table'     => 'agzipcodezones_interval',
        'primary'   => 'id_agzipcodezones_interval',
        'multilang' => false,
        'fields'    => array(
            'id_agzipcodezones_interval' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isInt',
            ),
            'zipcode_begin' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isGenericName',
                'db_type' => 'varchar(8)'
            ),
            'zipcode_end' => array(
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'db_type' => 'varchar(8)'
            ),
            'id_zone' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isInt',
                'db_type' => 'int unsigned'
            ),
            'id_shop' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isInt',
                'db_type' => 'int unsigned'
            ),
            'region' =>       ['type' => self::TYPE_STRING,    'db_type' => 'varchar(80)'],
            'state' =>        ['type' => self::TYPE_STRING,    'db_type' => 'varchar(80)'],
            'city' =>         ['type' => self::TYPE_STRING,    'db_type' => 'varchar(80)'],
            'neighborhood' => ['type' => self::TYPE_STRING,    'db_type' => 'varchar(80)'],
        ),
        'indexes' => array(
            array(
                'fields' => array('zipcode_begin', 'id_shop'),
                'prefix' => 'unique',
                'name' => 'unique_zipcode_begin'
            ),
            array(
                'fields' => array('zipcode_end', 'id_shop'),
                'prefix' => 'unique',
                'name' => 'unique_zipcode_end'
            ),
            array(
                'fields' => array('zipcode_begin', 'zipcode_end', 'id_shop'),
                'prefix' => 'unique',
                'name' => 'unique_zipcode_range'
            )
        )
    );

    public $id_agzipcodezones_interval;
    public $zipcode_begin;
    public $zipcode_end;
    public $id_zone;
    public $id_shop;
    public $region;
    public $state;
    public $city;
    public $neighborhood;

    public static function getByZipcode($zipcode)
    {
        $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'agzipcodezones_interval WHERE '
                . 'CAST(zipcode_begin AS SIGNED INTEGER) <= ' . (int)$zipcode . ' AND CAST(zipcode_end AS SIGNED INTEGER) >= '
                . (int)$zipcode;
        $db_data = Db::getInstance()->getRow($sql);
        
        if (!is_array($db_data)) {
            $db_data = array();
        }
        
        $return = new AgZipcodeZonesInterval();
        $return->hydrate($db_data);

        return $return;
    }

    public function add($auto_date = true, $null_values = false)
    {
        $return = parent::add($auto_date, $null_values);

        Cache::clean(get_called_class() . '*');

        return $return;
    }

    public function update($auto_date = true, $null_values = false)
    {
        $return = parent::update($auto_date, $null_values);

        Cache::clean(get_called_class() . '*');

        return $return;
    }    

    public function delete()
    {
        $return = parent::delete($auto_date, $null_values);

        Cache::clean(get_called_class() . '*');

        return $return;
    }

    public static function getByZone($id_zone)
    {
        $collection = new PrestaShopCollection('AgZipcodeZonesInterval');
        $collection->where('id_zone', '=', $id_zone);

        $id_shop = Context::getContext()->shop->id;
        $collection->where('id_shop', '=', $id_shop);

        return $collection->getResults();
    }

    public static function deleteByZone($id_zone)
    {
        $intervals = self::getByZone($id_zone);
        
        if (is_array($intervals)) {
            foreach ($intervals as $interval) {
                $interval->delete();
            }
        }
    }

    public static function hasIntersectionWithOtherInterval($begin, $end, $id_interval)
    {
        $sql = 'SELECT * FROM '.  _DB_PREFIX_ . 'agzipcodezones_interval ';
        $sql .= ' WHERE CAST(zipcode_end AS SIGNED INTEGER) >= '  .(int) $begin;
        $sql .= ' AND CAST(zipcode_begin AS SIGNED INTEGER) <= '  .(int) $end;


        if ($id_interval) {
            $sql .= ' AND id_agzipcodezones_interval != ' . (int) $id_interval;
        }

        $sql .= ' AND id_shop = ' . (int)Context::getContext()->shop->id;

        $db_data = Db::getInstance()->getRow($sql);
        
        if (!is_array($db_data)) {
            $db_data = array();
        }

        $return = new AgZipcodeZonesInterval();
        $return->hydrate($db_data);

        return $return;
    }

    //adiciona a validação para verificar CEPS em mais de um intervalo
    public function validateFields($die = true, $error_return = false)
    {        
        if (!parent::validateFields($die, $error_return)) {
            return false;
        }

        if ($this->zipcode_begin > $this->zipcode_end) {
            throw new PrestaShopException('O primeiro CEP do intervalo não deve ser maior que o último CEP do intervalo. Faixa escolhida de ' . $this->zipcode_begin . ' a ' . $this->zipcode_end . ' é inválida.');
        }

        $intersection = self::hasIntersectionWithOtherInterval(
            $this->zipcode_begin,
            $this->zipcode_end,
            $this->id
        );
        
        if (Validate::isLoadedObject($intersection)) {
            if ($die) {
                $current_zone = new Zone($this->id_zone);
                $zone = new Zone($intersection->id_zone);

                $module = new AgZipcodeZones();

                throw new PrestaShopException(
                    sprintf(
                        'Erro - Faixa de CEP de %d a %d já está em uso na região %s.',
                        $this->zipcode_begin,
                        $this->zipcode_end,
                        $zone->name
                    )
                );
            }

            return false;
        }

        return true;
    }

    public static function deleteAllFromZone($id_zone)
    {
        Db::getInstance()->delete(
            'agzipcodezones_interval',
            'id_zone = ' . (int)$id_zone
        );
    }

    public static function deleteFromRemovedZones()
    {
        $sql = new DbQuery();
        $sql->from('agzipcodezones_interval', 'a')
            ->select('a.id_agzipcodezones_interval')
            ->join('LEFT JOIN ' . _DB_PREFIX_ . 'zone z ON z.id_zone = a.id_zone')
            ->where('z.id_zone IS NULL');

        $ids = Db::getInstance()->executeS($sql);

        if (is_array($ids)) {
            $ids_to_remove = array();

            foreach ($ids as $id) {
                $ids_to_remove[] = $id['id_agzipcodezones_interval'];
            }

            $where_delete = 'id_agzipcodezones_interval IN ( ' . implode(',', $ids_to_remove) . ')';

            Db::getInstance()->delete('agzipcodezones_interval', $where_delete);
        }
    }
}
