<?php

class AgZipcodeZonesUpdateZonesModuleFrontController extends ModuleFrontController
{
	public function __construct()
	{
		parent::__construct();

        Db::getInstance(_PS_USE_SQL_SLAVE_)->query('SHOW COLUMNS FROM `'._DB_PREFIX_.'order_detail` LIKE "id_zone"');
        if (Db::getInstance()->NumRows() == 0) {
            Db::getInstance()->execute('ALTER TABLE `'._DB_PREFIX_.'order_detail` ADD `id_zone` int default 0');
            Db::getInstance()->execute('ALTER TABLE `'._DB_PREFIX_.'order_detail` ADD INDEX agzipcodezones (`id_zone`)');
        }

		$sql = new DbQuery;
		$sql->select('id_order, id_address_delivery');
		$sql->from('orders');

		$orders = Db::getInstance()->executeS($sql);

		foreach ($orders as $order) {
			$id_zone = Hook::exec("ActionGetIDZoneByAddressID", ['id_address' => $order['id_address_delivery']]);

			Db::getInstance()->update('order_detail', ['id_zone' => (int)$id_zone], 'id_order=' . (int)$order['id_order']);
		}

		exit();
	}
}