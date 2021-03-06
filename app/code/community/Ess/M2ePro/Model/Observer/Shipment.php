<?php

/*
 * @copyright  Copyright (c) 2011 by  ESS-UA.
 */

class Ess_M2ePro_Model_Observer_Shipment
{
    //####################################

    public function salesOrderShipmentSaveAfter(Varien_Event_Observer $observer)
    {
        try {

            if (Mage::helper('M2ePro')->getGlobalValue('skip_shipment_observer')) {
                // Not process invoice observer when set such flag
                Mage::helper('M2ePro')->unsetGlobalValue('skip_shipment_observer');
                return;
            }

            /** @var $shipment Mage_Sales_Model_Order_Shipment */
            $shipment = $observer->getEvent()->getShipment();
            $magentoOrderId = $shipment->getOrderId();

            try {
                /** @var $order Ess_M2ePro_Model_Order */
                $order = Mage::helper('M2ePro/Component')
                    ->getUnknownObject('Order', $magentoOrderId, 'magento_order_id');
            } catch (Exception $e) {
                return;
            }

            Mage::getSingleton('M2ePro/Order_Log_Manager')
                ->setInitiator(Ess_M2ePro_Model_Order_Log::INITIATOR_EXTENSION);

            // -------------
            /** @var $shipmentHandler Ess_M2ePro_Model_Order_Shipment_Handler */
            $shipmentHandler = $this->getShipmentHandler($order);
            $result = $shipmentHandler->handle($order, $shipment);
            // -------------

            if (!is_null(Mage::helper('M2ePro')->getGlobalValue('cron_running'))) {
                return;
            }

            switch ($result) {
                case Ess_M2ePro_Model_Order_Shipment_Handler::HANDLE_RESULT_SUCCEEDED:
                    $this->addSessionSuccessMessage($order);
                    break;
                case Ess_M2ePro_Model_Order_Shipment_Handler::HANDLE_RESULT_FAILED:
                    $this->addSessionErrorMessage($order);
                    break;
            }

        } catch (Exception $exception) {

            Mage::helper('M2ePro/Exception')->process($exception);

        }
    }

    //####################################

    private function getShipmentHandler(Ess_M2ePro_Model_Order $order)
    {
        $handler = null;

        switch ($order->getComponentMode()) {
            case Ess_M2ePro_Helper_Component_Buy::NICK:
                $handler = Mage::getModel('M2ePro/Order_Shipment_Handler');
                break;
            case Ess_M2ePro_Helper_Component_Amazon::NICK:
                $handler = Mage::getModel('M2ePro/Amazon_Order_Shipment_Handler');
                break;
            case Ess_M2ePro_Helper_Component_Ebay::NICK:
                $handler = Mage::getModel('M2ePro/Ebay_Order_Shipment_Handler');
                break;

            case Ess_M2ePro_Helper_Component_Play::NICK:
                $handler = Mage::getModel('M2ePro/Play_Order_Shipment_Handler');
                break;
        }

        if (!$handler) {
            throw new LogicException('Shipment handler not found.');
        }

        return $handler;
    }

    private function addSessionSuccessMessage(Ess_M2ePro_Model_Order $order)
    {
        $message = '';

        switch ($order->getComponentMode()) {
            case Ess_M2ePro_Helper_Component_Ebay::NICK:
                $message = Mage::helper('M2ePro')->__('Shipping Status for eBay Order was updated.');
                break;
            case Ess_M2ePro_Helper_Component_Amazon::NICK:
                $message = Mage::helper('M2ePro')->__('Updating Amazon Order Status to Shipped in Progress...');
                break;
            case Ess_M2ePro_Helper_Component_Buy::NICK:
                $message = Mage::helper('M2ePro')->__('Updating Rakuten.com Order Status to Shipped in Progress...');
                break;
            case Ess_M2ePro_Helper_Component_Play::NICK:
                $message = Mage::helper('M2ePro')->__('Updating Play.com Order Status to Shipped in Progress...');
                break;
        }

        if ($message) {
            Mage::getSingleton('adminhtml/session')->addSuccess($message);
        }
    }

    private function addSessionErrorMessage(Ess_M2ePro_Model_Order $order)
    {
        $url = Mage::helper('adminhtml')->getUrl('M2ePro/adminhtml_log/order', array('order_id' => $order->getId()));

        $startLink = '<a href="' . $url . '" target="_blank">';
        $endLink = '</a>';
        $channel = $order->getComponentTitle();

        $message = Mage::helper('M2ePro')->__(
            'Shipping Status for %s Order was not updated. View %sorder log%s for more details.',
            $channel, $startLink, $endLink
        );

        Mage::getSingleton('adminhtml/session')->addError($message);
    }

    //####################################
}