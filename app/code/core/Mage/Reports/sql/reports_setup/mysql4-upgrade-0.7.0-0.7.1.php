<?php

/**
 * OpenMage
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available at https://opensource.org/license/osl-3-0-php
 *
 * @category   Mage
 * @package    Mage_Reports
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://www.magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://www.openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

$installer->run("

DROP TABLE IF EXISTS {$this->getTable('report_event_types')};
CREATE TABLE {$this->getTable('report_event_types')} (
  `event_type_id` smallint(6) unsigned NOT NULL auto_increment,
  `event_name` varchar(32) NOT NULL,
  PRIMARY KEY  (`event_type_id`),
  KEY `event_type_id` (`event_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO {$this->getTable('report_event_types')} VALUES
(1, 'catalog_product_view'),
(2, 'sendfriend_product'),
(3, 'catalog_product_compare_add_product'),
(4, 'checkout_cart_add_product'),
(5, 'wishlist_add_product'),
(6, 'wishlist_share');

DROP TABLE IF EXISTS {$this->getTable('report_event')};
CREATE TABLE {$this->getTable('report_event')} (
  `event_id` bigint(20) unsigned NOT NULL auto_increment,
  `logged_at` datetime NOT NULL default '0000-00-00 00:00:00',
  `event_type_id` smallint(6) unsigned NOT NULL default '0',
  `object_id` int(10) unsigned NOT NULL default '0',
  `subject_id` int(10) unsigned NOT NULL default '0',
  `store_id` tinyint(3) unsigned NOT NULL default '0',
  PRIMARY KEY  (`event_id`),
  KEY `subject_id` (`subject_id`),
  KEY `object_id` (`object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

");

$installer->endSetup();
