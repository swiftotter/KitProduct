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
 * @copyright Swift Otter Studios, 7/15/14
 * @package default
 **/

class SwiftOtter_Sales_Model_Resource_Order_Product extends Mage_Core_Model_Mysql4_Abstract
{
    protected function _construct()
    {
        $this->_init('SwiftOtter_Sales/Order_Product', 'id');
    }

    public function indexSales()
    {
        $offset = $this->_getOffset();
        $orderItemTable = $this->getTable('sales/order_item');
        $orderTable = $this->getTable('sales/order');
        $productTable = $this->getTable('catalog/product');

        $this->_getWriteAdapter()->truncateTable($this->getMainTable());

        $select = $this->_getReadAdapter()->select();
        $select->from(array('order_item' => $orderItemTable), array())
            ->columns(array(
                'store_id' => 'store_id',
                'product_id' => 'product_id',
                'sales_date' => sprintf('DATE_ADD(IF(HOUR(order.created_at) < %1$s, DATE_SUB(DATE(order.created_at), INTERVAL 1 DAY), DATE(order.created_at)), INTERVAL %1$s HOUR)', $offset),
                'qty' => new Zend_Db_Expr('GREATEST(0, SUM(qty_ordered - qty_refunded - qty_canceled))'),
            ));

        $select->joinInner(array('product' => $productTable),
            'order_item.product_id = product.entity_id',
            array()
        );

        $select->joinInner(array('order' => $orderTable),
            'order_item.order_id = order.entity_id',
            array()
        );

        $select->where(sprintf('`order`.state NOT IN("%s")', implode('","', array(
                Mage_Sales_Model_Order::STATE_CANCELED,
                Mage_Sales_Model_Order::STATE_CLOSED)
        )));

        $select->group(array(
            'order_item.store_id',
            'sales_date',
            'order_item.product_id'
        ));


        $insert = $this->_getWriteAdapter()->insertFromSelect($select, $this->getMainTable(), array('store_id', 'product_id', 'sale_date', 'qty'));
        $this->_getWriteAdapter()->query($insert);
    }

    public function addProductRefund($productId, $storeId, $qty, $inputDate = null)
    {
        $this->addProductSale($productId, $storeId, 0-$qty, $inputDate);
    }

    public function addProductSale($productId, $storeId, $qty, $inputDate = null)
    {
//        if (!$inputDate) {
//            $inputDate = Mage::getSingleton('core/date')->gmtDate('Y-m-d h:i:s');
//        }

        $date = new DateTime($inputDate, new DateTimeZone('UTC'));
        $offset = $this->_getOffset();

        if ($date->format('H') < $offset) {
            $date->sub(new DateInterval('P1D'));
        }
        $date->setTime($offset, 30, 0);

        $select = $this->_getReadAdapter()->select()
            ->from($this->getMainTable(), array('id', 'qty'))
            ->where('sale_date = ?', $date->format('Y-m-d h:i:s'))
            ->where('product_id = ?', $productId)
            ->where('store_id = ?', $storeId);

        $result = $this->_getReadAdapter()->fetchRow($select);
        if (isset($result['id'])) {
            $finalQty = max(0, $qty + $result['qty']);

            // update
            $where = $this->_getReadAdapter()->quoteInto('id = ?', $result['id']);
            $this->_getReadAdapter()->update($this->getMainTable(), array(
                'qty' => $finalQty
            ), $where);
        } else {
            // insert
            $this->_getReadAdapter()->insert($this->getMainTable(), array(
                'product_id' => $productId,
                'store_id' => $storeId,
                'sale_date' => $date->format('Y-m-d h:i:s'),
                'qty' => $qty
            ));
        }
    }

    protected function _getOffset()
    {
        return abs(Mage::getModel('core/date')->getGmtOffset(Mage::getStoreConfig('general/locale/timezone'))) / 60 / 60;
    }
}