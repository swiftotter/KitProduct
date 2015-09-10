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

    public function cataloginventoryStockItemSaveCommitAfter($observer)
    {
        /** @var Mage_CatalogInventory_Model_Stock_Item $item */
        $item = $observer->getItem();
        $productId = $item->getProductId();

        if (!$item->getProduct()) {
            $type = Mage::getResourceModel('SwiftOtter_KitProduct/Stock_Indexer')->getProductType($productId);
        } else {
            $type = $item->getProduct()->getTypeId();
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
        } else {
            $quote = Mage::getSingleton('checkout/cart')->getQuote();
        };

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

            $quote->save();
        }

        if ($configurableItem && $configurableItem->getId()
            && $kitItem && $kitItem->getId()) {
            Mage::helper('SwiftOtter_KitProduct')->addSubProductsToCart($configurableItem, $kitItem);

            $quote->save();
        }
    }

    public function adminhtmlSalesOrderItemCollectionLoadAfter($observer)
    {
//        $collection = $observer->getOrderItemCollection();
//        $isNewInvoice = Mage::app()->getRequest()->getControllerName() == 'sales_order_invoice' && Mage::app()->getRequest()->getActionName() == 'new';
//
//        /** @var Mage_Sales_Model_Order_Item $orderItem */
//        foreach ($collection as $orderItem) {
//            if (!$orderItem->getParentItemId() && $orderItem->getProductType() == SwiftOtter_KitProduct_Model_Product_Type_Kit::KIT_TYPE_CODE && $isNewInvoice) {
//                $orderItem->setIsVirtual(true);
//                $orderItem->setParentItemId(1);
//                $orderItem->setParentItem(Mage::getModel('sales/order_item'));
//            }
//
//            if ($orderItem->getParentItemId() && $orderItem->getProductType() == Mage_Catalog_Model_Product_Type_Grouped::TYPE_CODE && $isNewInvoice) {
//                $orderItem->setParentItemId(null);
//            }
//        }
    }

    public function salesConvertQuoteItemToOrderItem($observer)
    {
        /** @var Mage_Sales_Model_Quote_Item $quoteItem */
        $quoteItem = $observer->getItem();

        /** @var Mage_Sales_Model_Order_Item $orderItem */
        $orderItem = $observer->getOrderItem();

        if (!$orderItem->getParentItemId() && $orderItem->getProductType() == SwiftOtter_KitProduct_Model_Product_Type_Kit::KIT_TYPE_CODE) {
//            $orderItem->setIsVirtual(true);

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
            if (isset($options['super_product_config'])) {
                $parentProduct = Mage::getModel('catalog/product')->load($options['super_product_config']['product_id']);

                $association = array(
                    'label' => Mage::helper('SwiftOtter_KitProduct')->__('Parent Product'),
                    'value' => sprintf('%s (%s)', $parentProduct->getName(), $parentProduct->getSku())
                );

                if (!isset($options['options'])) {
                    $options['options'] = array();
                }
                $options['options'][] = $association;
            }

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