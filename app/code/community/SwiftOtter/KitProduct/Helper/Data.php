<?php
/**
 * 
 *
 * @author Joseph Maxwell
 * @copyright Swift Otter Studios, 3/14/13
 * @package default
 **/

class SwiftOtter_KitProduct_Helper_Data extends Mage_Core_Helper_Abstract
{
    const CATALOG_PRODUCT_CLEAR_CACHE = 'catalog_product_clean_cache';

    public function shouldAllowKitMerging()
    {
        if (Mage::app()->getRequest()->getActionName() == "superGroup") {
            return false;
        }

        return true;
    }

    public function reindexStock ($productId)
    {
        $resource = Mage::getResourceModel('SwiftOtter_KitProduct/Stock_Indexer');
        if ($resource->updateAssociatedStockItems($productId)) {
            Mage::helper('SwiftOtter_Base')->cleanProduct($productId);
        }
    }

    public function reindexStockMass($list)
    {
        foreach ($list as $productId) {
            $this->reindexStock($productId);
        }

        return count($list);
    }

    public function getOptions(Mage_Catalog_Model_Product_Configuration_Item_Interface $item)
    {
        return array_merge(
            $this->getKitOptions($item),
            Mage::helper('catalog/product_configuration')->getCustomOptions($item)
        );
    }

    public function getKitOptions(Mage_Catalog_Model_Product_Configuration_Item_Interface $item)
    {
        $options = array();

        /** @var Mage_Catalog_Model_Product $product */
        $product = $item->getProduct();
        /** @var SwiftOtter_KitProduct_Model_Product_Type_Kit $typeInstance */
        $typeInstance = $product->getTypeInstance(true);

        $kitProductOptions = $item->getOptionByCode('kit_product_options');
        $serializedKitProducts = $kitProductOptions
            ? $kitProductOptions->getValue()
            : null;


        if ($serializedKitProducts) {
            $kitProducts = unserialize($serializedKitProducts);
            $option = array(
                'label' => $this->__('Included Products'),
                'value' => array()
            );

            foreach ($kitProducts as $kitProduct) {
                $value = '';

                if ($kitProduct['qty'] > 1) {
                    $value .= sprintf('%u', $kitProduct['qty']) . ' x ';
                }

                $value .= $kitProduct['name'];
                $option['value'][] = $value;
            }

            if ($option['value']) {
                $options[] = $option;
            }
        }

        return array(); //$options;
    }

    public function addSubProductsToCart(Mage_Sales_Model_Quote_Item $configurableItem, Mage_Sales_Model_Quote_Item $kitItem,
                                         Mage_Sales_Model_Quote $quote = null)
    {
        if (!$quote) {
            if (Mage::app()->getStore()->isAdmin()) {
                $quote = Mage::getSingleton('adminhtml/sales_order_create')->getQuote();
            } else {
                $quote = Mage::getSingleton('checkout/cart')->getQuote();
            }
        }

        if (!$configurableItem->getId()) {
            $configurableItem->save();
        }

        $products = $kitItem->getProduct()->getTypeInstance(true)->prepareForCartAdvanced(
            new Varien_Object(array('qty' => $configurableItem->getQty())), $kitItem->getProduct(), 'full'
        );
        $option = $configurableItem->getOptionByCode('additional_options');
        $itemOptions = array();

        foreach ($products as $product) {
            $quoteItem = $quote->getItemByProduct($product);

            if ($product->getId() != $kitItem->getProductId()) {
                if (!$quoteItem || ($quoteItem->getParentItemId() !== $kitItem->getId() && $quoteItem->getParentItemId() !== $configurableItem->getId())) {
                    $quoteItem = Mage::getModel('sales/quote_item');
                    $quoteItem->setQuote($quote);
                }

                $quoteItem->setOptions($product->getCustomOptions())
                    ->setProduct($product)
                    ->setParentItem($configurableItem)
                    ->setParentItemId($configurableItem->getId())
                    ->setQty($product->getCartQty());

                $quote->addItem($quoteItem);

                $itemOptions[] = sprintf('%d x %s (%s)', $quoteItem->getQty(), $product->getName(), $product->getSku());
            } else {
                if ($quoteItem && $quoteItem->getParentItemId() == $configurableItem->getId()) {
                    $quoteItem->setQty(1);
                }
            }
        }

        if (count($itemOptions)){
            $output = array(
                'label'     => 'Included Products',
                'value' => implode('<br/>', $itemOptions),
                'print_value' => implode(', ', $itemOptions)
            );

            if (!$option) {
                $value = array('items' => $output);
                $value = serialize($value);

                $configurableItem->addOption(
                    new Varien_Object(array(
                        'code' => 'additional_options',
                        'product_id' => $configurableItem->getProductId(),
                        'product' => $configurableItem->getProduct(),
                        'value' => $value
                    ))
                );
            } else {
                $additional = unserialize($option->getValue());
                $additional['items'] = $output;
                $option->setValue(serialize($additional));
            }
        }

        $configurableItem->save();

        return $this;
    }

}