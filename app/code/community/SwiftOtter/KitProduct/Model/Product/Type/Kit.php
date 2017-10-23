<?php
/**
 * @author Joseph Maxwell
 * @copyright Swift Otter Studios, 3/14/13
 * @package default
 **/

class SwiftOtter_KitProduct_Model_Product_Type_Kit extends Mage_Catalog_Model_Product_Type_Grouped
{
    const KIT_TYPE_CODE = 'kit';

    /**
     * Check is product available for sale
     *
     * @param Mage_Catalog_Model_Product $product
     * @return bool
     */
    public function isSalable($product = null)
    {
        $salable = false;
        foreach ($this->getAssociatedProducts($product) as $associatedProduct) {
            $salable = $salable || $associatedProduct->isSalable();
        }
        return $salable;
    }


    /**
     * Pulling the parent's item quantity
     *
     * @param mixed $qty
     * @param null $product
     * @return float
     */
    public function prepareQuoteItemQty($qty, $product = null)
    {
        if ($product && is_object($product->getCustomOption('parent_product_id'))) {
            if ($quote = $this->_loadCurrentQuote()) {
                $productOption = $product->getCustomOption('parent_product_id');

                if ($productOption->getProduct()) { // add to cart
                    $quoteItem = $quote->getItemByProduct($productOption->getProduct());

                    if (is_object($quoteItem)) {
                        if ($quoteItem->getParentItem()) {
                            $parentItem = $quoteItem->getParentItem();
                        } else if ($quoteItem->getParentItemId()) {
                            $parentItem = $quote->getItemById($quoteItem->getParentItemId());
                        }
                    }
                } else {
                    $quoteItem = $quote->getItemById($productOption->getItemId());
                    $parentItem = $quote->getItemById($quoteItem->getParentItemId());
                }

                if ($parentItem) {
                    return floatval($parentItem->getQty());
                }
            }
        }

        return floatval($qty);
    }

    protected function _loadCurrentQuote()
    {
        $quote = Mage::getSingleton('checkout/cart')->getQuote();
        if (Mage::app()->getStore()->isAdmin()) {
            $quote = Mage::getSingleton('adminhtml/sales_order_create')->getQuote();
        }

        if (!$quote && ($order = Mage::registry('current_order'))) {
            $quoteId = $order->getQuoteId();
            $quote = Mage::getModel('sales/quote')->load($quoteId);
        }

        return $quote;
    }

    protected function _uniqueProductArray(array $products) : array
    {
        $output = [];

        array_walk($products, function(Mage_Catalog_Model_Product $product) use (&$output) {
            if (!isset($output[$product->getId()])) {
                $output[$product->getId()] = $product;
            }
        });

        return array_values($output);
    }

    protected function _prepareProduct(Varien_Object $buyRequest, $product, $processMode)
    {
        $isStrictProcessMode = $this->_isStrictProcessMode($processMode);
        $requestGroup = $buyRequest->getSuperGroup();

        $requestQty = 0;
        if (is_array($requestGroup)) {
            foreach ($requestGroup as $productId => $qty) {
                $requestQty += $qty;
            }
        }

        if (!$requestGroup || !$requestQty) {
            $requestGroup = $this->_getAssociatedProducts($product);
            $buyRequest->setSuperGroup($requestGroup);
        }

        $products = parent::_prepareProduct($buyRequest, $product, $processMode);
        if (is_string($products)) { //Unfortunately, there is a kit without physical products in it
            $products = array($product);
        }
        $products = $this->_uniqueProductArray($products);
        $products = array_filter($products, function(Mage_Catalog_Model_Product $check) use ($product) {
            return $check->getId() !== $product->getId();
        });

        /** @var Mage_Catalog_Model_Product $mainProduct */
        $product->setCartQty($buyRequest->getQty());
        $product->setQty($buyRequest->getQty());

        $subProducts = [];
        $optionDisplay = [];
        $optionOutput = '';
        $printOutput = [];

        /** @var Mage_Catalog_Model_Product $productIterate */
        array_walk($products, function(Mage_Catalog_Model_Product $child) use ($product, &$requestGroup, &$printOutput, &$optionDisplay, &$subProducts, &$optionOutput) {
            $child->setData('parent_product_id', $product->getId());
            $qty = $requestGroup[$child->getId()];

            $product->addCustomOption('product_qty_' . $child->getId(), $qty, $product);

            $subProducts[] = $child->getId();
            $optionDisplay[] = array(
                'sku' => $child->getSku(),
                'name' => $child->getName(),
                'qty' => $qty,
                'id' => $child->getId()
            );

            $value = '';
            if ($qty) {
                $value .= sprintf('%d', $qty) . ' x ';
            }
            $value .= $child->getName();
            $value .= ' (' . $child->getSku() . ')';

            $printOutput[] = $value;

            if ($qty > 0) {
                $optionOutput .= $value . "<br/>";
            }
        });

        array_unshift($products, $product);

        $optionText = [
            [
                'label' => Mage::helper('SwiftOtter_KitProduct')->__('Included Products'),
                'value' => $optionOutput,
                'print_value' => implode(', ', $printOutput)
            ]
        ];

        $product->addCustomOption('additional_options', serialize($optionText));
        $product->addCustomOption('kit_products_ids', serialize($subProducts));
        $product->addCustomOption('kit_product_options', serialize($optionDisplay));

        return $products;
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @return array|void
     */
    public function getOrderOptions($product = null)
    {
        $customOptions = array();

        /** @var Mage_Sales_Model_Quote_Item_Option $option */
        foreach ($product->getCustomOptions() as $option) {
            $customOptions[$option->getCode()] = $option->getValue();
        }

        $options = array_merge($customOptions, parent::getOrderOptions($product));

        return $options;
    }

    /**
     * @param $product
     * @param array $requestGroup
     * @return array
     */
    protected function _getAssociatedProducts($product, $requestGroup = array())
    {
        $associatedProducts = $this->getAssociatedProducts($product);

        /** @var Mage_Catalog_Model_Product $associatedProduct */
        foreach ($associatedProducts as $associatedProduct) {
            $requestGroup[$associatedProduct->getId()] = $associatedProduct->getDefaultQty();
        }

        return $requestGroup;
    }


    /**
     * @param $product
     * @return mixed
     */
    public function getAssociatedFilteredProductsCollection($product)
    {
        $collection = $this->getAssociatedProductCollection($product)
            ->addAttributeToSelect('*')
            ->addFilterByRequiredOptions()
            ->setPositionOrder()
            ->addStoreFilter($this->getStoreFilter($product))
            ->addAttributeToFilter('status', array('in' => $this->getStatusFilters($product)));

        return $collection;
    }

    /**
     * Retrieve array of associated products
     *
     * @param Mage_Catalog_Model_Product $product
     * @return array
     */
    public function getAssociatedProducts($product = null, $associatedProducts = array())
    {
        if (!is_array($associatedProducts)) {
            $associatedProducts = array();
        }

        if (!$this->getProduct($product)->hasData($this->_keyAssociatedProducts)) {
            if (!Mage::app()->getStore()->isAdmin()) {
                $this->setSaleableStatus($product);
            }

            $collection = $this->getAssociatedFilteredProductsCollection($product);

            /** @var Mage_Catalog_Model_Product $item */
            foreach ($collection as $item) {
                if (isset($associatedProducts[$item->getId()])) {
                    $this->_mergeProducts($associatedProducts[$item->getId()], $item);
                } else {
                    $item->setDefaultQty($item->getQty());
                    $associatedProducts[$item->getId()] = $item;
                }

                if ($item->getTypeId() == self::KIT_TYPE_CODE && Mage::helper('SwiftOtter_KitProduct')->shouldAllowKitMerging()) {
                    $qty = $item->getQty();
                    if(!$qty) {
                        $qty = 1;
                    }

                    $merge = $this->getAssociatedProducts($item);

                    foreach ($merge as $mergeItem) {
                        $mergeItem->setQty($mergeItem->getQty() * $qty);

                        if (isset($associatedProducts[$mergeItem->getId()])) {
                            $this->_mergeProducts($associatedProducts[$mergeItem->getId()], $item);
                        } else {
                            $associatedProducts[$mergeItem->getId()] = $mergeItem;
                        }
                    }
                }
            }

            $associatedProducts = array_values($associatedProducts);

            $this->getProduct($product)->setData($this->_keyAssociatedProducts, $associatedProducts);
        }
        return $this->getProduct($product)->getData($this->_keyAssociatedProducts);
    }



    protected function _mergeProducts ($keepProduct, $discardProduct)
    {
        $keepProduct->setQty($keepProduct->getQty() + $discardProduct->getQty());

        return $keepProduct;
    }

    /**
     * Default action to get weight of product
     *
     * @param Mage_Catalog_Model_Product $product
     * @return decimal
     */
    public function getWeight($product = null)
    {
        $weight = 0;
        $product = $this->getProduct($product);

        if ($product) {
            if (!$product->getData('calculated_weight')) {
                /** @var Mage_Catalog_Model_Product $subProduct */
                foreach ($this->getAssociatedProducts($product) as $subProduct) {
                    $weight += $subProduct->getQty() * $subProduct->getWeight();
                }

                $product->setData('calculated_weight', $weight);
            }

            return $product->getData('calculated_weight');
        } else {
            return 0;
        }
    }
}
