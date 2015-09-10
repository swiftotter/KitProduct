<?php

/** @var $installer Mage_Eav_Model_Entity_Setup*/
$installer = $this;
/**
 * Prepare database for install
 */
$installer->startSetup();

$installer->run("
    CREATE TABLE `{$this->getTable('SwiftOtter_Sales/Order_Product')}` (
      id INT UNSIGNED AUTO_INCREMENT NOT NULL,
      store_id SMALLINT UNSIGNED NOT NULL,
      product_id INT unsigned NOT NULL,
      sale_date datetime,
      qty int,
      PRIMARY KEY(id),
      KEY `ORDER_PRDOUCT_SALE` (product_id, sale_date),
      KEY `ORDER_PRODUCT_STORE_ID` (`store_id`),
      CONSTRAINT `FK_ORDER_PRODUCT_STORE_ID` FOREIGN KEY (`store_id`)
            REFERENCES `{$this->getTable('core/store')}` (`store_id`)
            ON DELETE CASCADE
            ON UPDATE CASCADE,
      KEY `ORDER_PRODUCT_PRODUCT_ID` (`product_id`),
      CONSTRAINT `FK_ORDER_PRODUCT_PRODUCT_ID` FOREIGN KEY (`product_id`)
            REFERENCES `{$this->getTable('catalog/product')}` (`entity_id`)
            ON DELETE CASCADE
            ON UPDATE CASCADE
    );
");

/**
 * Prepare database after install
 */
$installer->endSetup();