<?php
/**
 * 
 *
 * @author Joseph Maxwell
 * @copyright Swift Otter Studios, 3/14/13
 * @package default
 **/

class SwiftOtter_KitProduct_Block_Adminhtml_Sales_Order_Items_Renderer extends Mage_Adminhtml_Block_Sales_Order_View_Items_Renderer_Default
{
    public function getOrderOptions()
    {
        $result = array();
        return array(
            'label' => 'Test',
            'values' => array(
                'How are you?'
            )
        );
    }

    public function getValueHtml($item)
    {
        $test = "test";
        return "test";
    }
}