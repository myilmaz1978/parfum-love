<?php
/**
 * This file is part of the FIREGENTO project.
 *
 * FireGento_GermanSetup is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 3 as
 * published by the Free Software Foundation.
 *
 * This script is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * PHP version 5
 *
 * @category  FireGento
 * @package   FireGento_GermanSetup
 * @author    FireGento Team <team@firegento.com>
 * @copyright 2012 FireGento Team (http://www.firegento.de). All rights served.
 * @license   http://opensource.org/licenses/gpl-3.0 GNU General Public License, version 3 (GPLv3)
 * @version   $Id:$
 * @since     0.1.0
 */
/**
 * Enhanced block for product price display of all products in spite of bundles (got own block!).
 * Contains the normal price.phtml rendering and additionally a configured static block.
 *
 * @category  FireGento
 * @package   FireGento_GermanSetup
 * @author    FireGento Team <team@firegento.com>
 * @copyright 2012 FireGento Team (http://www.firegento.de). All rights served.
 * @license   http://opensource.org/licenses/gpl-3.0 GNU General Public License, version 3 (GPLv3)
 * @version   $Id:$
 * @since     0.1.0
 */
class FireGento_GermanSetup_Block_Catalog_Product_Price
    extends FireGento_GermanSetup_Block_Catalog_Product_Price_Abstract
{
    /**
     * @var string Path to default tier price template
     */
    protected $_tierPriceDefaultTemplate  = 'catalog/product/view/tierprices.phtml';

    /**
     * Add content of template block below price html if defined in config
     *
     * @return string
     */
    public function _toHtml()
    {
        $html = trim(parent::_toHtml());

        if (empty($html) || !Mage::getStoreConfigFlag('catalog/price/display_block_below_price')) {
            return $html;
        }

        if ($this->getTemplate() != $this->_tierPriceDefaultTemplate) {
            $htmlObject = new Varien_Object();
            $htmlObject->setParentHtml($html);
            $htmlTemplate = $this->getLayout()->createBlock('core/template')
                ->setTemplate('germansetup/price_info.phtml')
                ->setFormattedTaxRate($this->getFormattedTaxRate())
                ->setIsIncludingTax($this->isIncludingTax())
                ->setIsIncludingShippingCosts($this->isIncludingShippingCosts())
                ->setIsShowShippingLink($this->isShowShippingLink())
                ->toHtml();
            $htmlObject->setHtml($htmlTemplate);

            Mage::dispatchEvent('germansetup_after_product_price',
                array(
                    'html_obj' => $htmlObject,
                    'block' => $this,
                )
            );

            $html = $htmlObject->getPrefix();
            $html .= $htmlObject->getParentHtml();
            $html .= $htmlObject->getHtml();
            $html .= $htmlObject->getSuffix();
        }

        return $html;
    }

    /**
     * Read tax rate from current product.
     *
     * @return string
     */
    public function getTaxRate()
    {
        $taxRateKey = 'tax_rate_'.$this->getProduct()->getId();
        if (!$this->getData($taxRateKey)) {
            $this->setData($taxRateKey, $this->_loadTaxCalculationRate($this->getProduct()));
        }

        return $this->getData($taxRateKey);
    }

    /**
     * Retrieves formatted string of tax rate for user output
     *
     * @return string
     */
    public function getFormattedTaxRate()
    {
        if ($this->getTaxRate() === null
            || $this->getProduct()->getTypeId() == 'bundle'
        ) {
            return '';
        }

        $locale  = Mage::app()->getLocale()->getLocaleCode();
        $taxRate = Zend_Locale_Format::toFloat($this->getTaxRate(), array('locale' => $locale));

        return $this->__('%s%%', $taxRate);
    }

    /**
     * Returns whether or not the price contains taxes
     *
     * @return bool
     */
    public function isIncludingTax()
    {
        if (!$this->getData('is_including_tax')) {
            $this->setData('is_including_tax', Mage::getStoreConfig('tax/display/type'));
        }

        return $this->getData('is_including_tax');
    }

    /**
     * Returns whether or not the price contains taxes
     *
     * @return bool
     */
    public function isIncludingShippingCosts()
    {
        if (!$this->getData('is_including_shipping_costs')) {
            $this->setData(
                'is_including_shipping_costs',
                Mage::getStoreConfig('catalog/price/including_shipping_costs')
            );
        }

        return $this->getData('is_including_shipping_costs');
    }

    /**
     * Returns whether the shipping link needs to be shown
     * on the frontend or not.
     *
     * @return bool
     */
    public function isShowShippingLink()
    {
        $productTypeId = $this->getProduct()->getTypeId();
        $ignoreTypeIds = array('virtual', 'downloadable');
        if (in_array($productTypeId, $ignoreTypeIds)) {
            return false;
        }

        return true;
    }

    /**
     * Gets tax percents for current product
     *
     * @param  Mage_Catalog_Model_Product $product
     * @return string
     */
    protected function _loadTaxCalculationRate(Mage_Catalog_Model_Product $product)
    {
        $taxPercent = $product->getTaxPercent();
        if (is_null($taxPercent)) {
            $taxClassId = $product->getTaxClassId();
            if ($taxClassId) {
                $request    = Mage::getSingleton('tax/calculation')->getRateRequest(null, null, null, null);
                $taxPercent = Mage::getSingleton('tax/calculation')->getRate($request->setProductClassId($taxClassId));
            }
        }

        if ($taxPercent) {
            return $taxPercent;
        }

        return 0;
    }
}
