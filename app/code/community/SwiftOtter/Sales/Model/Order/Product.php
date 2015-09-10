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
 * @copyright Swift Otter Studios, 7/15/14
 * @package default
 **/


/**
 * Class SwiftOtter_Inventory_Model_Vendor
 *
 * @method string getAccountNumber()
 * @method string getAbbrev()
 * @method string getName()
 * @method string getNotes()
 * @method string getPhone()
 * @method string getFax()
 * @method string getEmail()
 * @method string getWebsite()
 * @method string getBillingCompany()
 * @method string getBillingContact()
 * @method string getBillingAddress1()
 * @method string getBillingAddress2()
 * @method string getBillingCity()
 * @method string getBillingState()
 * @method string getBillingPostCode()
 * @method string getBillingRegion()
 * @method array getDropShipAlert()
 * @method array getInventoryAlert()
 */
class SwiftOtter_Sales_Model_Order_Product extends Mage_Core_Model_Abstract
{
    public function __construct()
    {
        $this->_init('SwiftOtter_Sales/Order_Product');
    }

}