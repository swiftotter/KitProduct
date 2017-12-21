<?php
/**
 * 
 *
 * @author Joseph Maxwell
 * @copyright Swift Otter Studios, 3/14/13
 * @package default
 **/

class SwiftOtter_KitProduct_Block_Sales_Order_Items_Renderer extends Mage_Sales_Block_Order_Item_Renderer_Default
{
    /**
     * Placeholder for additional functionality - and to ensure method can be called
     *
     * @param $item
     * @return bool
     */
    public function canShowPriceInfo($item)
    {
        return true;
    }

    /**
     * Placeholder for additional functionality - and to ensure method can be called
     *
     * @param $item
     * @return bool
     */
    public function isShipmentSeparately()
    {
        return false;
    }
}