<?php

/**
 * OpenMage
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available at https://opensource.org/license/osl-3-0-php
 *
 * @category   Mage
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://www.magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://www.openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Sales_Model_Entity_Setup $installer */
$installer = $this;

$installer->getConnection()->addColumn(
    $installer->getTable('sales/shipping_aggregated_order'),
    'total_shipping_actual',
    "decimal(12,4) NOT NULL default '0.0000'",
);

$installer->getConnection()->addColumn(
    $installer->getTable('sales/shipping_aggregated'),
    'total_shipping_actual',
    "decimal(12,4) NOT NULL default '0.0000'",
);

$installer->run("
DROP TABLE `{$installer->getTable('sales/order_aggregated_created')}`;
CREATE TABLE `{$installer->getTable('sales/order_aggregated_created')}` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `period` DATE NOT NULL DEFAULT '0000-00-00',
  `store_id` SMALLINT(5) UNSIGNED DEFAULT NULL,
  `order_status` VARCHAR(50) NOT NULL DEFAULT '',
  `orders_count` INT(11) NOT NULL DEFAULT '0',
  `total_qty_ordered` DECIMAL(12,4) NOT NULL DEFAULT '0.0000',
  `total_qty_invoiced` DECIMAL(12,4) NOT NULL DEFAULT '0.0000',
  `total_income_amount` DECIMAL(12,4) NOT NULL DEFAULT '0.0000',
  `total_revenue_amount` DECIMAL(12,4) NOT NULL DEFAULT '0.0000',
  `total_profit_amount` DECIMAL(12,4) NOT NULL DEFAULT '0.0000',
  `total_invoiced_amount` DECIMAL(12,4) NOT NULL DEFAULT '0.0000',
  `total_canceled_amount` DECIMAL(12,4) NOT NULL DEFAULT '0.0000',
  `total_paid_amount` DECIMAL(12,4) NOT NULL DEFAULT '0.0000',
  `total_refunded_amount` DECIMAL(12,4) NOT NULL DEFAULT '0.0000',
  `total_tax_amount` DECIMAL(12,4) NOT NULL DEFAULT '0.0000',
  `total_tax_amount_actual` DECIMAL(12,4) NOT NULL DEFAULT '0.0000',
  `total_shipping_amount` DECIMAL(12,4) NOT NULL DEFAULT '0.0000',
  `total_shipping_amount_actual` DECIMAL(12,4) NOT NULL DEFAULT '0.0000',
  `total_discount_amount` DECIMAL(12,4) NOT NULL DEFAULT '0.0000',
  `total_discount_amount_actual` DECIMAL(12,4) NOT NULL DEFAULT '0.0000',
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNQ_PERIOD_STORE_ORDER_STATUS` (`period`,`store_id`,`order_status`),
  KEY `IDX_STORE_ID` (`store_id`),
  CONSTRAINT `FK_SALES_ORDER_AGGREGATED_CREATED` FOREIGN KEY (`store_id`) REFERENCES `{$installer->getTable('core_store')}` (`store_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=INNODB DEFAULT CHARSET=utf8;
");
