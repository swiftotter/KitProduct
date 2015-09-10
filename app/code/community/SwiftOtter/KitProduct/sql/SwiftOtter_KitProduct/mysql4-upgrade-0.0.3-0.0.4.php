<?php
/**
 * SwiftOtter_Base is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * SwiftOtter_Base is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with SwiftOtter_Base. If not, see <http://www.gnu.org/licenses/>.
 *
 * Copyright: 2014 (c) SwiftOtter Studios
 *
 * @author Joseph Maxwell
 * @copyright Swift Otter Studios, 7/7/14
 * @package default
 **/

/** @var Mage_Eav_Model_Entity_Setup $installer */
$installer = $this;
$installer->startSetup();

$installer->run("
    CREATE TABLE `{$installer->getTable('SwiftOtter_KitProduct/Stock_Indexer')}` (
        `product_id` INT UNSIGNED NOT NULL,
        KEY `KITPRODUCT_STOCK_INDEXER_ENTITY_ID` (`product_id`),
		CONSTRAINT `FK_KITPRODUCT_STOCK_INDEXER_ENTITY_ID` FOREIGN KEY (`product_id`)
			REFERENCES `{$this->getTable('catalog/product')}` (`entity_id`)
			ON DELETE CASCADE
			ON UPDATE CASCADE
    );
");

$installer->endSetup();