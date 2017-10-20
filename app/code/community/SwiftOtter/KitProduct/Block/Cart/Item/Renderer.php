<?php
/**
 * 
 *
 * @author Joseph Maxwell
 * @copyright Swift Otter Studios, 3/14/13
 * @package default
 **/

class SwiftOtter_KitProduct_Block_Cart_Item_Renderer extends Mage_Checkout_Block_Cart_Item_Renderer
{
    protected function _beforeToHtml()
    {
        $this->overrideProductThumbnail($this->helper('catalog/image')->init($this->getProduct(), 'image'));
        return parent::_beforeToHtml();
    }
    
    /**
     * Overloaded the default parent to show the products that are a part of this kit
     *
     * @return array
     */
    public function getOptionList()
    {
        return Mage::helper('SwiftOtter_KitProduct')->getOptions($this->getItem());
    }
}