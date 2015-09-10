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
 * @copyright Swift Otter Studios, 1/22/15
 * @package default
 **/

class SwiftOtter_Sales_Model_Observer
{
    const ACTION_ADD = 'add';
    const ACTION_REMOVE = 'remove';

    public function salesOrderPlaceAfter($observer)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getOrder();

        $this->_reindexItems(self::ACTION_ADD, $order->getStoreId(), $order->getAllItems());
    }

    public function salesOrderCreditmemoRefund($observer)
    {
        /** @var Mage_Sales_Model_Order_Creditmemo $creditMemo */
        $creditMemo = $observer->getCreditmemo();
        $date = $creditMemo->getOrder()->getCreatedAt();

        $resource = Mage::getResourceModel('SwiftOtter_Sales/Order_Product');
        $products = array();
        /** @var Mage_Sales_Model_Order_Creditmemo_Item $item */
        foreach ($creditMemo->getAllItems() as $item) {
            if (isset($products[$item->getProductId()])) {
                $products[$item->getProductId()] += $item->getQty();
            } else {
                $products[$item->getProductId()] = $item->getQty();
            }
        }

        foreach ($products as $productId => $qty) {
            $resource->addProductRefund($productId, $creditMemo->getStoreId(), $qty, $date);
        }
    }

    public function orderCancelAfter($observer)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getOrder();
        $date = $order->getCreatedAt();

        $this->_reindexItems(self::ACTION_REMOVE, $order->getStoreId(), $order->getAllItems(), $date);
    }

    protected function _reindexItems ($action, $storeId, $collection, $date = null)
    {
        $resource = Mage::getResourceModel('SwiftOtter_Sales/Order_Product');
        $products = array();
        /** @var Mage_Sales_Model_Order_Item $item */
        foreach ($collection as $item) {
            if (isset($products[$item->getProductId()])) {
                $products[$item->getProductId()] += $item->getQtyOrdered();
            } else {
                $products[$item->getProductId()] = $item->getQtyOrdered();
            }
        }

        foreach ($products as $productId => $qty) {
            if ($action == self::ACTION_ADD) {
                $resource->addProductSale($productId, $storeId, $qty, $date);
            } else {
                $resource->addProductRefund($productId, $storeId, $qty, $date);
            }
        }
    }
}