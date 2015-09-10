<?php
/**
 * 
 *
 * @author Joseph Maxwell
 * @copyright Swift Otter Studios, 09/13/2013
 * @package default
 **/

class SwiftOtter_KitProduct_Block_Adminhtml_Override_Catalog_Product_Edit_Tab_Super_Config_Grid extends Mage_Adminhtml_Block_Catalog_Product_Edit_Tab_Super_Config_Grid
{
    /**
     * @param Mage_Core_Model_Resource_Db_Collection_Abstract $collection
     */
    public function setCollection($collection)
    {
        $this->_collection = $collection;
        if ($this->_getProduct()->getTypeId() == SwiftOtter_KitProduct_Model_Product_Type_Kit::KIT_TYPE_CODE) {
            /** @var Varien_Db_Select $select */
            $select = $collection->getSelect();
            $product = $this->_getProduct();

            $select->reset(Varien_Db_Select::WHERE);

            /**
             * Resetting the collection's where information. This is all just to get rid of the common attribute set
             * requirement for the child products. This is being specifically limited to kit parent products.
             */
            $allowProductTypes = array();
            foreach (Mage::helper('catalog/product_configuration')->getConfigurableAllowedTypes() as $type) {
                $allowProductTypes[] = $type->getName();
            }

            $collection->addFieldToFilter('type_id', $allowProductTypes)
                ->addFilterByRequiredOptions();

            foreach ($product->getTypeInstance(true)->getUsedProductAttributes($product) as $attribute) {
                $collection->addAttributeToFilter($attribute->getAttributeCode(), array('notnull'=>1));
            }
        }

        return $this;
    }
}