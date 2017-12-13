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
 * @author Tyler Schade
 * @copyright Swift Otter Studios, 7/28/16
 * @package default
 **/

class SwiftOtter_KitProduct_Helper_Quote extends Mage_Core_Helper_Abstract
{
    public function normalizeOptions(Mage_Sales_Model_Quote $quote)
    {
        $items = $quote->getItemsCollection()->getItems();

        array_walk($items, function(Mage_Sales_Model_Quote_Item $item) {
            if (!($additionalOptions = $item->getOptionByCode('additional_options'))) {
                return;
            }

            if (class_exists("SwiftOtter_SimpleConfigurable_Model_Product_Type_SimpleConfigurable") &&
                $item->getProductType() == SwiftOtter_SimpleConfigurable_Model_Product_Type_SimpleConfigurable::SIMPLE_TYPE) {
                return;
            }

            $value = $additionalOptions->getValue();
            if (is_array($value)) {
                $additionalOptions->setValue($this->normalizeOption($value, $item->getQty()));
            } else {
                $additionalOptions->setValue($this->normalizeOptionString($value, $item->getQty()));
            }
        });
    }

    protected function normalizeOption (array $value, $qty)
    {
        if (!isset($value['items'])) {
            return $value;
        }

        $value['items'] = array_map(function($value) use ($qty) {
            return preg_replace('/\d+\s[x]/', $qty . ' x', $value);
        }, $value['items']);

        return $value;
    }

    protected function normalizeOptionString ($value, $qty)
    {
        $options = unserialize($value);
        $options = $this->normalizeOption($options, $qty);
        return serialize($options);
    }
}
