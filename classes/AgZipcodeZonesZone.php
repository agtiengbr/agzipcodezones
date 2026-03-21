<?php

class AgZipcodeZonesZone extends AgObjectModel
{
    public static $definition = array(
        'table'     => 'agzipcodezones_zone',
        'primary'   => 'id_agzipcodezones_zone',
        'multilang' => false,
        'fields'    => array(
            'id_agzipcodezones_zone' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isInt',
            ),
            'id_zone' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isInt',
                'db_type' => 'int unsigned'
            ),
            'postcode_to_use' => array('type' => self::TYPE_STRING, 'db_type' => 'varchar(8)'),
        ),
        'indexes' => array(
            array(
                'fields' => array('id_agzipcodezones_zone'),
                'prefix' => 'unique',
                'name' => 'unique_id_agzipcode_zone'
            ),
            array(
                'fields' => array('id_zone'),
                'prefix' => 'unique',
                'name' => 'unique_id_zone'
            ),
        )
    );

    public $id_agzipcodezones_zone;
    public $id_zone;
    public $postcode_to_use;

    public static function getAll($id_zone)
    {
        $collection = new PrestaShopCollection('AgZipcodeZonesZone');
        return $collection->getResults();
    }

    public static function getByZone($id_zone)
    {
        $collection = new PrestaShopCollection('AgZipcodeZonesZone');
        $collection->where('id_zone', '=', $id_zone);

        $results = $collection->getResults();
        
        if (is_array($results) && count($results)) {
            return $results[0];
        }

        return new AgZipcodeZonesZone();
    }

    public function delete()
    {
        AgZipcodeZonesInterval::deleteAllFromZone($this->id_zone);
        parent::delete();
    }

    public static function getZoneByName($name)
    {
        $cache_key = get_called_class() . __FUNCTION__ . $name;

        if (!Cache::isStored($cache_key)) {
            $sql = new DbQuery;
            $sql->select('id_zone')->from('zone')->where('name="' . pSQL($name) .'"');

            $id_zone = Db::getInstance()->getValue($sql);

            $zone = new Zone($id_zone);
            Cache::store($cache_key, $zone);
        }

        return Cache::retrieve($cache_key);
    }
}
