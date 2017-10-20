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
 * Copyright: 2013 (c) SwiftOtter Studios
 *
 * @author Joseph Maxwell
 * @copyright Swift Otter Studios, 11/19/14
 * @package default
 **/

class SwiftOtter_KitProduct_Model_Resource_Stock_Indexer extends Mage_Core_Model_Resource_Db_Abstract
{
    protected $_quantityAttributeId;

    protected function _construct()
    {
        $this->_init('SwiftOtter_KitProduct/Stock_Indexer', 'product_id');
    }

    public function addProduct($productId)
    {
        $this->_getWriteAdapter()->insert($this->getMainTable(), array('product_id' => $productId));

        return $this;
    }

    public function getProductType($productId)
    {
        $select = $this->getReadConnection()->select();
        $select->from($this->getTable('catalog/product'), array('type_id'))
            ->where('entity_id = ?', $productId);

        return $this->getReadConnection()->fetchOne($select);
    }

    public function truncate()
    {
        $this->_getWriteAdapter()->truncateTable($this->getMainTable());
    }

    public function getQuantityAttributeId()
    {
        if (!$this->_quantityAttributeId) {
            $read = $this->_getReadAdapter();
            $quantityAttributeQuery = $read->select()
                ->from($this->getTable('catalog/product_link_attribute'), array('product_link_attribute_id'))
                ->where('product_link_attribute_code = ?', 'qty');

            $this->_quantityAttributeId = $read->fetchOne($quantityAttributeQuery);
        }

        return $this->_quantityAttributeId;
    }

    public function getKitProducts()
    {
        $select = $this->getReadConnection()->select();
        $select->from($this->getTable('catalog/product'), array('entity_id'));
        $select->where('type_id = ?', SwiftOtter_KitProduct_Model_Product_Type_Kit::KIT_TYPE_CODE);

        return $this->getReadConnection()->fetchCol($select);
    }

    public function getProducts()
    {
        $select = $this->getReadConnection()->select();
        $select->from(array('indexer' => $this->getMainTable()), array());

        $linkType = Mage_Catalog_Model_Product_Link::LINK_TYPE_GROUPED;

        $select->joinInner(
            array('link' => $this->getTable('catalog/product_link')),
            'indexer.product_id = link.linked_product_id',
            array('parent_product_id' => 'product_id')
        );

        $select->where('link.link_type_id = ?', $linkType);
        $select->group('link.product_id');

        $list = $this->getReadConnection()->fetchCol($select);

        foreach ($list as $productId) {
            $list = $this->_getProductLinks($productId, $list);
        }

        $select = $this->getReadConnection()->select();
        $select->from($this->getTable('catalog/product'), array('entity_id'))
            ->where('type_id = ?', SwiftOtter_KitProduct_Model_Product_Type_Kit::KIT_TYPE_CODE)
            ->where(sprintf('entity_id IN ("%s")', implode('","', $list)));

        $list = $this->getReadConnection()->fetchCol($select);

        return $list;
    }

    protected function _getProductLinks($productId, $list)
    {
        $linkType = Mage_Catalog_Model_Product_Link::LINK_TYPE_GROUPED;

        $select = $this->getReadConnection()->select();
        $select->from($this->getTable('catalog/product_link'), array('product_id'));
        $select->where('link_type_id = ?', $linkType);
        $select->where('linked_product_id = ?', $productId);

        $newList = $this->getReadConnection()->fetchCol($select);
        $list = array_merge($list, $newList);
        foreach ($newList as $newProductId) {
            $list = $this->_getProductLinks($newProductId, $list);
        }

        $list = array_unique($list);

        return $list;
    }

    public function updateAssociatedStockItems($kitProductId)
    {
        $linkType = Mage_Catalog_Model_Product_Link::LINK_TYPE_GROUPED;
        $read = $this->_getReadAdapter();
        $write = $this->_getWriteAdapter();

        $quantityAttributeId = $this->getQuantityAttributeId();

        if ($quantityAttributeId > 0) {
            $select = $read->select();
            $select->from(
                array('link' => $this->getTable('catalog/product_link')),
                array()
            );

            $select->where('link.link_type_id = ?', $linkType);
            $select->where('link.product_id = ?', $kitProductId);

            $select->joinInner(
                array('product' => $this->getTable('catalog/product')),
                'link.linked_product_id = product.entity_id',
                array()
            );

            $select->where('product.type_id = ?', Mage_Catalog_Model_Product_Type::TYPE_SIMPLE);

            $select->joinLeft(
                array('quantity_attribute_table' => $this->getTable('catalog/product_link_attribute_decimal')),
                $read->quoteInto('link.link_id = quantity_attribute_table.link_id AND quantity_attribute_table.product_link_attribute_id = ?', $quantityAttributeId),
                array()
            );

            $condition = " AND `inventory`.manage_stock = 1";
            if ($this->_getConfigManageStock()) {
                $condition = " AND (`inventory`.manage_stock = 1 OR `inventory`.use_config_manage_stock = 1)";
            }


            $select->joinInner(
                array('inventory' => $this->getTable('cataloginventory/stock_item')),
                'link.linked_product_id = inventory.product_id' . $condition,
                array(
                    'is_in_stock' => new Zend_Db_Expr('MIN(is_in_stock)'),
                    'qty' => new Zend_Db_Expr('MIN(qty / IF(quantity_attribute_table.value = 0, 1, quantity_attribute_table.value))'),
                    'qty_on_hand' => new Zend_Db_Expr('MIN(qty_on_hand / IF(quantity_attribute_table.value = 0, 1, quantity_attribute_table.value))')
                )
            );

            $select->group('link.product_id');

            $result = $read->fetchRow($select);

            $rowsUpdated = $write->update(
                $this->getTable('cataloginventory/stock_item'),
                array(
                    'is_in_stock' => $result['is_in_stock'],
                    'qty' => floor($result['qty']),
                    'qty_on_hand' => floor($result['qty_on_hand'])
                ),
                $write->quoteInto('product_id = ?', $kitProductId)
            );

            $write->delete($this->getMainTable(), $write->quoteInto('product_id = ?', $kitProductId));

            return $rowsUpdated;
        }
    }

    /**
     * @return bool
     */
    protected function _getConfigManageStock()
    {
        return Mage::getStoreConfigFlag('cataloginventory/item_options/manage_stock');
    }
}