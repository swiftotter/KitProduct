<?php
/**
 * 
 *
 * @author Joseph Maxwell
 * @copyright Swift Otter Studios, 3/14/13
 * @package default
 **/

class SwiftOtter_KitProduct_Block_Catalog_Product_View_Type_Kit extends Mage_Catalog_Block_Product_View_Type_Grouped
{
    public function getHideIncludedProducts()
    {
        return $this->getProduct()->getHideIncludedProducts();
    }
}