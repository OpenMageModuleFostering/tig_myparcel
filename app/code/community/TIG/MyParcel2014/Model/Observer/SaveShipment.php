<?php

class TIG_MyParcel2014_Model_Observer_SaveShipment
{
    /**
     * @param Varien_Event_Observer $observer
     * @return $this
     * @event controller_action_predispatch_adminhtml_sales_order_shipment_save
     * @observer tig_myparcel_shipment_save
     */
    public function registerConsignmentOption(Varien_Event_Observer $observer)
    {
        if (!Mage::helper('tig_myparcel')->isEnabled()) {
            return $this;
        }

        /**
         * Retrieve and register the chosen option, if any.
         *
         * @var Mage_Core_Controller_Varien_Front $controller
         */
        $controller                 = $observer->getControllerAction();
        $selectedConsignmentOptions = $controller->getRequest()->getParam('tig_myparcel');

        /**
         * Add the selected options to the registry.
         *
         * This registry value will be checked when the MyParcel shipment entity is saved.
         */
        if (!empty($selectedConsignmentOptions)) {
            Mage::register('tig_myparcel_consignment_options', $selectedConsignmentOptions);
        }

        return $this;
    }

    /**
     * Saves the chosen consignment options and creates a MyParcel shipment for the current shipment.
     *
     * @param Varien_Event_Observer $observer
     *
     * @return $this
     * @throws Exception
     * @event sales_order_shipment_save_after
     * @observer tig_myparcel_shipment_save_after
     */
    public function saveConsignmentOption(Varien_Event_Observer $observer)
    {
        $helper = Mage::helper('tig_myparcel');

        /**
         * check if extension is enabled
         */
        if (!$helper->isEnabled()) {
            return $this;
        }

        /**
         * @var Mage_Sales_Model_Order_Shipment $shipment
         */
        $shipment = $observer->getShipment();

        /**
         * check if order is placed with Myparcel
         */
        $shippingMethod = $shipment->getOrder()->getShippingMethod();
        if (!$helper->shippingMethodIsMyParcel($shippingMethod)) {
            return $this;
        }

        /**
         * check if the current shipment already has a myparcel shipment
         */
        if($helper->hasMyParcelShipment($shipment->getId())){
            return $this;
        }

        /**
         * @var TIG_MyParcel2014_Model_Shipment $myParcelShipment
         */
        $myParcelShipment = Mage::getModel('tig_myparcel/shipment')->load($shipment->getId());
        $myParcelShipment->setShipmentId($shipment->getId())
                         ->setConsignmentOptions()
                         ->createConsignment()
                         ->save();

        return $this;
    }

}
