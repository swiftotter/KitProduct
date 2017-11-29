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
 * @copyright Swift Otter Studios, 11/16/16
 * @package default
 **/

class SwiftOtter_KitProduct_Helper_Order extends Mage_Core_Helper_Abstract
{
    public function adjustChildItemsToCompleteOrder(array $orderItems)
    {
        array_walk($orderItems, function ($item) {
            /** @var Mage_Sales_Model_Order_Item $item */
            if ($item->getData('parent_item_id')
                && $item->getParentItem()
                && $item->getParentItem()->getData('product_type') == 'kit'
                && $item->getParentItem()->getData('qty_shipped') == $item->getData('qty_ordered')) {

                /**
                 * If the item is a child item, and the parent item exists and is a kit, and the
                 * parent has shipped the ordered quantity of the child, the child should be marked
                 * as shipped so the order can be marked complete.
                 */

                $item->setData('qty_shipped', $item->getData('qty_ordered'));

                $item->save();
            }
        });
    }
}
