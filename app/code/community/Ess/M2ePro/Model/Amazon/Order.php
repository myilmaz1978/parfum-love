<?php

/*
 * @copyright  Copyright (c) 2011 by  ESS-UA.
 */

/**
 * @method Ess_M2ePro_Model_Order getParentObject()
 * @method Ess_M2ePro_Model_Mysql4_Amazon_Order getResource()
 */
class Ess_M2ePro_Model_Amazon_Order extends Ess_M2ePro_Model_Component_Child_Amazon_Abstract
{
    // Parser hack -> Mage::helper('M2ePro')->__('Order Status cannot be Updated. Reason: %msg%');

    const STATUS_PENDING             = 0;
    const STATUS_UNSHIPPED           = 1;
    const STATUS_SHIPPED_PARTIALLY   = 2;
    const STATUS_SHIPPED             = 3;
    const STATUS_UNFULFILLABLE       = 4;
    const STATUS_CANCELED            = 5;
    const STATUS_INVOICE_UNCONFIRMED = 6;

    // ########################################

    private $relatedAmazonItems = NULL;

    private $subTotalPrice = NULL;

    private $grandTotalPrice = NULL;

    // ########################################

    public function _construct()
    {
        parent::_construct();
        $this->_init('M2ePro/Amazon_Order');
    }

    // ########################################

    public function getProxy()
    {
        return Mage::getModel('M2ePro/Amazon_Order_Proxy', $this);
    }

    // ########################################

    /**
     * @return Ess_M2ePro_Model_Amazon_Account
     */
    public function getAmazonAccount()
    {
        return $this->getParentObject()->getAccount()->getChildObject();
    }

    // ########################################

    /**
     * @return array
     */
    public function getRelatedChannelItems()
    {
        if (is_null($this->relatedAmazonItems)) {
            $this->relatedAmazonItems = array();

            foreach ($this->getParentObject()->getItemsCollection()->getItems() as $item) {
                if (!is_null($item->getChildObject()->getAmazonItem())) {
                    $this->relatedAmazonItems[] = $item->getChildObject()->getAmazonItem();
                }
            }
        }

        return $this->relatedAmazonItems;
    }

    // ########################################

    public function getAmazonOrderId()
    {
        return $this->getData('amazon_order_id');
    }

    public function getBuyerName()
    {
        return $this->getData('buyer_name');
    }

    public function getBuyerEmail()
    {
        return $this->getData('buyer_email');
    }

    public function getStatus()
    {
        return (int)$this->getData('status');
    }

    public function getCurrency()
    {
        return $this->getData('currency');
    }

    public function getShippingService()
    {
        return $this->getData('shipping_service');
    }

    public function getShippingPrice()
    {
        return (float)$this->getData('shipping_price');
    }

    /**
     * @return Ess_M2ePro_Model_Amazon_Order_ShippingAddress
     */
    public function getShippingAddress()
    {
        $address = json_decode($this->getData('shipping_address'), true);

        return Mage::getModel('M2ePro/Amazon_Order_ShippingAddress', $this->getParentObject())
            ->setData($address);
    }

    public function getPaidAmount()
    {
        return (float)$this->getData('paid_amount');
    }

    public function getTaxAmount()
    {
        return (float)$this->getData('tax_amount');
    }

    public function getDiscountAmount()
    {
        return (float)$this->getData('discount_amount');
    }

    // ########################################

    public function isFulfilledByAmazon()
    {
        return (bool)$this->getData('is_afn_channel');
    }

    // ########################################

    public function isPending()
    {
        return $this->getStatus() == self::STATUS_PENDING;
    }

    public function isUnshipped()
    {
        return $this->getStatus() == self::STATUS_UNSHIPPED;
    }

    public function isPartiallyShipped()
    {
        return $this->getStatus() == self::STATUS_SHIPPED_PARTIALLY;
    }

    public function isShipped()
    {
        return $this->getStatus() == self::STATUS_SHIPPED;
    }

    public function isUnfulfillable()
    {
        return $this->getStatus() == self::STATUS_UNFULFILLABLE;
    }

    public function isCanceled()
    {
        return $this->getStatus() == self::STATUS_CANCELED;
    }

    public function isInvoiceUnconfirmed()
    {
        return $this->getStatus() == self::STATUS_INVOICE_UNCONFIRMED;
    }

    // ########################################

    public function getSubtotalPrice()
    {
        if (is_null($this->subTotalPrice)) {
            $this->subTotalPrice = $this->getResource()->getItemsTotal($this->getId());
        }

        return $this->subTotalPrice;
    }

    public function getGrandTotalPrice()
    {
        if (is_null($this->grandTotalPrice)) {
            $this->grandTotalPrice = $this->getSubtotalPrice();
            $this->grandTotalPrice += $this->getShippingPrice();
            $this->grandTotalPrice += $this->getTaxAmount();
            $this->grandTotalPrice -= $this->getDiscountAmount();
        }

        return round($this->grandTotalPrice, 2);
    }

    // ########################################

    public function getStatusForMagentoOrder()
    {
        $status = '';
        $this->isUnshipped()        && $status = $this->getAmazonAccount()->getMagentoOrdersStatusProcessing();
        $this->isPartiallyShipped() && $status = $this->getAmazonAccount()->getMagentoOrdersStatusProcessing();
        $this->isShipped()          && $status = $this->getAmazonAccount()->getMagentoOrdersStatusShipped();

        return $status;
    }

    // ########################################

    public function getAssociatedStoreId()
    {
        $storeId = NULL;

        $relatedChannelItems = $this->getRelatedChannelItems();

        if (count($relatedChannelItems) == 0) {
            // 3rd party order
            // ---------------
            $storeId = $this->getAmazonAccount()->getMagentoOrdersListingsOtherStoreId();
            // ---------------
        } else {
            // M2E order
            // ---------------
            if ($this->getAmazonAccount()->isMagentoOrdersListingsStoreCustom()) {
                $storeId = $this->getAmazonAccount()->getMagentoOrdersListingsStoreId();
            } else {
                $firstChannelItem = reset($relatedChannelItems);
                $storeId = $firstChannelItem->getStoreId();
            }
            // ---------------
        }

        if ($storeId == 0) {
            $storeId = Mage::helper('M2ePro/Magento')->getDefaultStoreId();
        }

        return $storeId;
    }

    // ########################################

    public function isReservable()
    {
        if ($this->isCanceled()) {
            return false;
        }

        if ($this->isFulfilledByAmazon() && !$this->getAmazonAccount()->isMagentoOrdersFbaModeEnabled()) {
            return false;
        }

        return true;
    }

    // ########################################

    /**
     * Check possibility for magento order creation
     *
     * @return bool
     */
    public function canCreateMagentoOrder()
    {
        if ($this->isPending() || $this->isCanceled()) {
            return false;
        }

        if ($this->isFulfilledByAmazon() && !$this->getAmazonAccount()->isMagentoOrdersFbaModeEnabled()) {
            return false;
        }

        return true;
    }

    public function beforeCreateMagentoOrder()
    {
        if ($this->isPending() || $this->isCanceled()) {
            throw new Exception('Magento Order creation is not allowed for pending and canceled Amazon Orders.');
        }
    }

    public function afterCreateMagentoOrder()
    {
        if ($this->getAmazonAccount()->isMagentoOrdersCustomerNewNotifyWhenOrderCreated()) {
            $this->getParentObject()->getMagentoOrder()->sendNewOrderEmail();
        }

        if ($this->isFulfilledByAmazon() && !$this->getAmazonAccount()->isMagentoOrdersFbaStockEnabled()) {
            Mage::dispatchEvent('m2epro_amazon_fba_magento_order_place_after', array(
                'magento_order' => $this->getParentObject()->getMagentoOrder()
            ));
        }
    }

    // ########################################

    public function canCreateInvoice()
    {
        if (!$this->getAmazonAccount()->isMagentoOrdersInvoiceEnabled()) {
            return false;
        }

        if ($this->isPending() || $this->isCanceled() || $this->isUnfulfillable() || $this->isInvoiceUnconfirmed()) {
            return false;
        }

        $magentoOrder = $this->getParentObject()->getMagentoOrder();
        if (is_null($magentoOrder)) {
            return false;
        }

        if ($magentoOrder->hasInvoices() || !$magentoOrder->canInvoice()) {
            return false;
        }

        return true;
    }

    // ----------------------------------------

    public function createInvoice()
    {
        if (!$this->canCreateInvoice()) {
            return NULL;
        }

        $magentoOrder = $this->getParentObject()->getMagentoOrder();

        // Create invoice
        // -------------
        /** @var $invoiceBuilder Ess_M2ePro_Model_Magento_Order_Invoice */
        $invoiceBuilder = Mage::getModel('M2ePro/Magento_Order_Invoice');
        $invoiceBuilder->setMagentoOrder($magentoOrder);
        $invoiceBuilder->buildInvoice();
        // -------------

        $invoice = $invoiceBuilder->getInvoice();

        if ($this->getAmazonAccount()->isMagentoOrdersCustomerNewNotifyWhenInvoiceCreated()) {
            $invoice->sendEmail();
        }

        return $invoice;
    }

    // ########################################

    public function canCreateShipment()
    {
        if (!$this->getAmazonAccount()->isMagentoOrdersShipmentEnabled()) {
            return false;
        }

        if (!$this->isShipped()) {
            return false;
        }

        $magentoOrder = $this->getParentObject()->getMagentoOrder();
        if (is_null($magentoOrder)) {
            return false;
        }

        if ($magentoOrder->hasShipments() || !$magentoOrder->canShip()) {
            return false;
        }

        return true;
    }

    // ----------------------------------------

    public function createShipment()
    {
        if (!$this->canCreateShipment()) {
            return NULL;
        }

        $magentoOrder = $this->getParentObject()->getMagentoOrder();

        // Create shipment
        // -------------
        /** @var $shipmentBuilder Ess_M2ePro_Model_Magento_Order_Shipment */
        $shipmentBuilder = Mage::getModel('M2ePro/Magento_Order_Shipment');
        $shipmentBuilder->setMagentoOrder($magentoOrder);
        $shipmentBuilder->buildShipment();
        // -------------

        return $shipmentBuilder->getShipment();
    }

    // ########################################

    public function canUpdateShippingStatus(array $trackingDetails = array())
    {
        if ($this->isShipped() && empty($trackingDetails)) {
            return false;
        }

        if ($this->isPending() || $this->isCanceled() || $this->isFulfilledByAmazon()) {
            return false;
        }

        return true;
    }

    public function updateShippingStatus(array $trackingDetails = array(), array $items = array())
    {
        if (!$this->canUpdateShippingStatus($trackingDetails)) {
            return false;
        }

        if (!isset($trackingDetails['fulfillment_date'])) {
            $trackingDetails['fulfillment_date'] = Mage::helper('M2ePro')->getCurrentGmtDate();
        }

        $params = array(
            'amazon_order_id'  => $this->getAmazonOrderId(),
            'fulfillment_date' => $trackingDetails['fulfillment_date'],
            'tracking_number'  => NULL,
            'carrier_name'     => NULL,
            'shipping_method'  => NULL,
            'items'            => array()
        );

        if (!empty($trackingDetails['tracking_number'])) {
            $params['tracking_number'] = $trackingDetails['tracking_number'];
        }

        if (!empty($trackingDetails['carrier_title'])) {
            $params['shipping_method'] = $trackingDetails['carrier_title'];
        }

        if (!empty($trackingDetails['carrier_code'])) {
            try {
                $carrier = Mage::getSingleton('shipping/config')->getCarrierInstance(
                    $trackingDetails['carrier_code'], $this->getParentObject()->getStoreId()
                );
            } catch (Exception $e) {
                $carrier = false;
            }

            if ($carrier) {
                $params['carrier_name'] = $carrier->getConfigData('title');
            } else {
                $params['carrier_name'] = $trackingDetails['carrier_code'];
            }
        }

        foreach ($items as $item) {
            if (!isset($item['amazon_order_item_id']) || !isset($item['qty'])) {
                continue;
            }

            if ((int)$item['qty'] <= 0) {
                continue;
            }

            $params['items'][] = array(
                'amazon_order_item_id' => $item['amazon_order_item_id'],
                'qty' => (int)$item['qty']
            );
        }

        $orderId     = $this->getParentObject()->getId();
        $action      = Ess_M2ePro_Model_Order_Change::ACTION_UPDATE_SHIPPING;
        $creatorType = Ess_M2ePro_Model_Order_Change::CREATOR_TYPE_OBSERVER;
        $component   = Ess_M2ePro_Helper_Component_Amazon::NICK;

        Mage::getModel('M2ePro/Order_Change')->create($orderId, $action, $creatorType, $component, $params);

        return true;
    }

    // ########################################

    public function deleteInstance()
    {
        return $this->delete();
    }

    // ########################################
}