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
 * @copyright Swift Otter Studios, 11/21/14
 * @package default
 **/

class SwiftOtter_KitProduct_KitController extends Mage_Adminhtml_Controller_Action
{
    public function reindexAction()
    {
        $products = array();
        if ($id = $this->getRequest()->getParam('id')) {
            $products[] = $id;
        } else {
            $products = Mage::getResourceModel('SwiftOtter_KitProduct/Stock_Indexer')->getKitProducts();
        }

        $count = Mage::helper('SwiftOtter_KitProduct')->reindexStockMass($products);

        Mage::getSingleton('adminhtml/session')->addSuccess($this->__('%s products were reindexed.', $count));
        $this->_redirect('*/dashboard/index');
    }

    public function cronAction()
    {
        Mage::getSingleton('SwiftOtter_KitProduct/Cron')->reindexKitProducts();
    }
}