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
 * @copyright Swift Otter Studios, 3/14/14
 * @package default
 **/


class SwiftOtter_KitProduct_Model_Observer
{
    public function cataloginventoryStockItemOverride($observer)
    {
        $transport = $observer->getTransport();
        $item = $transport->getItem();

        if ($item->getProduct() && $item->getProduct()->getTypeId() == SwiftOtter_KitProduct_Model_Product_Type_Kit::KIT_TYPE_CODE) {
            $transport->setPrevent(true);
        }
    }

    public function salesOrderSaveBefore(Varien_Object $observer)
    {
        $orderItems = $observer->getData('order')->getAllItems();

        //PHG-2463: Ensure that the order is able to be marked complete if appropriate
        Mage::helper('SwiftOtter_KitProduct/Order')->adjustChildItemsToCompleteOrder($orderItems);
    }

    public function cataloginventoryStockItemSaveCommitAfter($observer)
    {
        /** @var Mage_CatalogInventory_Model_Stock_Item $item */
        $item = $observer->getItem();
        $productId = $item->getProductId();

        $this->_addProductToReindex($productId);
    }

    public function salesOrderItemCancel($observer)
    {
        /** @var Mage_Sales_Model_Order_Item $item */
        $item = $observer->getItem();

        $this->_addProductToReindex($item->getProductId());
    }

    public function salesCreditmemoItemSaveAfter($observer)
    {
        /** @var Mage_Sales_Model_Order_Creditmemo_Item $item */
        $item = $observer->getCreditmemoItem();

        $this->_addProductToReindex($item->getProductId());
    }

    protected function _addProductToReindex($product, $type = '')
    {
        if (is_object($product)) {
            $type = $product->getTypeId();
            $productId = $product->getId();
        } else {
            $type = Mage::getResourceModel('SwiftOtter_KitProduct/Stock_Indexer')->getProductType($product);
            $productId = $product;
        }

        if ($type != SwiftOtter_KitProduct_Model_Product_Type_Kit::KIT_TYPE_CODE) {
            Mage::getResourceModel('SwiftOtter_KitProduct/Stock_Indexer')->addProduct($productId);
        } else {
            Mage::helper('SwiftOtter_KitProduct')->reindexStock($productId);
        }
    }

    public function salesQuoteProductAddAfter($observer)
    {
        $items = $observer->getItems();
        if (Mage::app()->getStore()->isAdmin()) {
            $quote = Mage::getSingleton('adminhtml/sales_order_create')->getQuote();

            if (!$quote->getId() && count($items) > 0) {
                $firstItem = $items[0];
                $quote = $firstItem->getQuote();
            }
        } else {
            $quote = Mage::getSingleton('checkout/cart')->getQuote();
        }

        $configurableType = Mage_Catalog_Model_Product_Type_Configurable::TYPE_CODE;

        /** @var Mage_Sales_Model_Quote_Item $configurableItem */
        $configurableItem = false;

        $kitType = SwiftOtter_KitProduct_Model_Product_Type_Kit::KIT_TYPE_CODE;

        /** @var Mage_Sales_Model_Quote_Item $kitItem */
        $kitItem = false;

        foreach ($items as $item) {
            if (strstr($item->getProductType(), $configurableType) !== false) {
                $configurableItem = $item;
            }

            if ($configurableItem && $item->getProductType() == $kitType) {
                $kitItem = $item;
            }
        }

        if (($configurableItem && !$configurableItem->getId()) ||
            ($kitItem && !$kitItem->getId())) {

            $quote->setDataChanges(true)
                ->save();
        }

        if ($configurableItem && $configurableItem->getId()
            && $kitItem && $kitItem->getId()) {
            Mage::helper('SwiftOtter_KitProduct')->addSubProductsToCart($configurableItem, $kitItem, $quote);

            $quote->save();
        }

        $fakeObserver = new Varien_Object();
        $fakeObserver->setData('quote', $quote);

        $this->normalizeOptions($fakeObserver);
    }

    public function normalizeOptions($observer)
    {
        Mage::helper('SwiftOtter_KitProduct/Quote')->normalizeOptions($observer->getData('quote'));
    }

    public function salesConvertQuoteItemToOrderItem($observer)
    {
        /** @var Mage_Sales_Model_Quote_Item $quoteItem */
        $quoteItem = $observer->getItem();

        /** @var Mage_Sales_Model_Order_Item $orderItem */
        $orderItem = $observer->getOrderItem();

        if ($quoteItem->getParentItem() && $quoteItem->getParentItem()->getProductType() == 'simpleconfigurable' &&
            $orderItem->getProductType() == SwiftOtter_KitProduct_Model_Product_Type_Kit::KIT_TYPE_CODE) {
            $orderItem->setQtyOrdered($quoteItem->getParentItem()->getQty());
        }

        if (!$orderItem->getParentItemId() && $orderItem->getProductType() == SwiftOtter_KitProduct_Model_Product_Type_Kit::KIT_TYPE_CODE) {
            $options = $orderItem->getProductOptions();
            $options['shipment_type'] = Mage_Catalog_Model_Product_Type_Abstract::SHIPMENT_SEPARATELY;
            foreach ($options as $name => $option) {
                $unserialized = $option;
                if (!is_array($unserialized)) {
                    try {
                        $unserialized = unserialize($option);
                    } catch (Exception $ex) {}
                }
                if (is_array($unserialized)) {
                    $options[$name] = $unserialized;
                }
            }

            $orderItem->setProductOptions($options);
        }

        if ($orderItem->getQuoteParentItemId() && $orderItem->getProductType() == Mage_Catalog_Model_Product_Type_Grouped::TYPE_CODE) {
            $options = $orderItem->getProductOptions();
            $orderItem->setProductOptions($options);
        }

        /** @var Mage_Sales_Model_Quote_Item $quoteItem */
        $quoteItem = $observer->getItem();

        if ($additionalOptions = $quoteItem->getOptionByCode('additional_options')) {
            $orderItem = $observer->getOrderItem();
            $options = $orderItem->getProductOptions();
            $options['additional_options'] = unserialize($additionalOptions->getValue());
            $orderItem->setProductOptions($options);
        }
    }
}