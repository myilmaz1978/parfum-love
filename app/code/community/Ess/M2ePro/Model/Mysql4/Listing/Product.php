<?php

/*
 * @copyright  Copyright (c) 2011 by  ESS-UA.
 */

class Ess_M2ePro_Model_Mysql4_Listing_Product extends Ess_M2ePro_Model_Mysql4_Component_Parent_Abstract
{
    public function _construct()
    {
        $this->_init('M2ePro/Listing_Product', 'id');
    }
}