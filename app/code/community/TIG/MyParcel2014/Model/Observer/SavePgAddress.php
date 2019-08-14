<?php
/**
 *                  ___________       __            __
 *                  \__    ___/____ _/  |_ _____   |  |
 *                    |    |  /  _ \\   __\\__  \  |  |
 *                    |    | |  |_| ||  |   / __ \_|  |__
 *                    |____|  \____/ |__|  (____  /|____/
 *                                              \/
 *          ___          __                                   __
 *         |   |  ____ _/  |_   ____ _______   ____    ____ _/  |_
 *         |   | /    \\   __\_/ __ \\_  __ \ /    \ _/ __ \\   __\
 *         |   ||   |  \|  |  \  ___/ |  | \/|   |  \\  ___/ |  |
 *         |___||___|  /|__|   \_____>|__|   |___|  / \_____>|__|
 *                  \/                           \/
 *                  ________
 *                 /  _____/_______   ____   __ __ ______
 *                /   \  ___\_  __ \ /  _ \ |  |  \\____ \
 *                \    \_\  \|  | \/|  |_| ||  |  /|  |_| |
 *                 \______  /|__|    \____/ |____/ |   __/
 *                        \/                       |__|
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to servicedesk@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@tig.nl for more information.
 *
 * @copyright   Copyright (c) 2014 Total Internet Group B.V. (http://www.tig.nl)
 * @license     http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 *
 * @method boolean hasQuote()
 * @method TIG_MyParcel2014_Model_Observer_SavePgAddress setQuote(Mage_Sales_Model_Quote $quote)
 */
class TIG_MyParcel2014_Model_Observer_SavePgAddress extends Varien_Object
{
    /**
     * Regular expressions to validate various address fields.
     */
    const CITY_NAME_REGEX   = '#^[a-zA-Z]+(?:(?:\\s+|-)[a-zA-Z]+)*$#';
    const STREET_NAME_REGEX = "#^[a-zA-Z0-9\s,'-]*$#";
    const HOUSENR_EXT_REGEX = "#^[a-zA-Z0-9\s,'-]*$#";
    const NAME_REGEX        = "#^[a-zA-Z0-9\s,'-]*$#";

    /**
     * Regular expression to validate dutch phone number.
     */
    const PHONE_NUMBER_REGEX = '#^(((\+31|0|0031)){1}[1-9]{1}[0-9]{8})$#i';

    /**
     * Get the current quote.
     *
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        if ($this->hasQuote()) {
            return $this->_getData('quote');
        }

        $quote = Mage::getSingleton('checkout/session')->getQuote();

        $this->setQuote($quote);
        return $quote;
    }

    /**
     * Saves a selected MyParcel address.
     *
     * @param Varien_Event_Observer $observer
     *
     * @return $this
     *
     * @event controller_action_postdispatch_checkout_onepage_saveShippingMethod
     *        |controller_action_postdispatch_onestepcheckout_index_index
     *
     * @observer tig_myparcel_save_pg_address
     */
    public function savePgAddress(Varien_Event_Observer $observer)
    {
        /**
         * Check if the MyParcel extension is enabled.
         */
        if (!Mage::helper('tig_myparcel')->isEnabled()) {
            return $this;
        }

        /**
         * @var Mage_Core_Controller_Varien_Front $controller
         *
         * Get the selected PG address from the controller if available.
         */
        $controller    = $observer->getControllerAction();
        $pgAddressData = $controller->getRequest()->getParam('tig_myparcel_pg_address', array());

        /**
         * Delete any existing pakjeGemak addresses.
         */
        $this->_removePgAddress();
        if (!$this->_isAddressEmpty($pgAddressData)) {
            /**
             * Save the selected address.
             */
            $this->_savePgAddress($pgAddressData);
        }

        return $this;
    }

    /**
     * Copies a PakjeGemak address from the quote to the newly placed order.
     *
     * @param Varien_Event_Observer $observer
     *
     * @return $this
     *
     * @throws Exception
     */
    public function copyAddressToOrder(Varien_Event_Observer $observer)
    {
        /**
         * @var Mage_Sales_Model_Order $order
         */
        $order  = $observer->getOrder();
        $helper = Mage::helper('tig_myparcel');

        /**
         * @var Mage_Sales_Model_Quote $quote
         */
        $quote = Mage::getModel('sales/quote')->load($order->getQuoteId());
        if (!$quote || !$quote->getId()) {
            $quote = $order->getQuote();
        }

        if (!$quote || !$quote->getId()) {
            return $this;
        }

        $this->setQuote($quote);

        /**
         * Check if the order is shipped using MyParcel.
         */
        $shippingMethod = $order->getShippingMethod();
        if (!$helper->shippingMethodIsMyParcel($shippingMethod)) {
            $this->_removePgAddress();
            return $this;
        }

        /**
         * Get the PakjeGemak address for this quote.
         */
        $pakjeGemakAddress = $helper->getPgAddress($quote);

        /**
         * If no PakjeGemak address was found we don't need to do anything else.
         */
        if (!$pakjeGemakAddress) {
            return $this;
        }

        $this->_copyAddressToOrder($order, $pakjeGemakAddress);
        return $this;
    }

    /**
     * Check if an array is considered empty.
     *
     * @param array $pgAddressData
     *
     * @return bool
     */
    protected function _isAddressEmpty($pgAddressData)
    {
        foreach ($pgAddressData as $key => $value) {
            /**
             * If the value is an array, check recursively if the value is empty and continue with the next item.
             */
            if (is_array($value)) {
                $isEmpty = $this->_isAddressEmpty($value);
                if (!$isEmpty) {
                    return false;
                }

                continue;
            }

            /**
             * Check if the value is empty or contains a single dash.
             */
            if (!empty($value) && $value !== '-') {
                return false;
            }
        }

        return true;
    }

    /**
     * Removes a PakjeGemak address from the quote.
     *
     * @return $this
     *
     * @throws Exception
     */
    protected function _removePgAddress()
    {
        $quote = $this->getQuote();

        /**
         * Get the PakjeGemak address if it exists.
         */
        $pgAddress = Mage::helper('tig_myparcel')->getPgAddress($quote);
        if (!$pgAddress) {
            return $this;
        }

        /**
         * Delete the address.
         */
        $pgAddress->delete();
        return $this;
    }

    /**
     * Saves or updates a PakjeGemak address for the quote.
     *
     * @param array $pgAddressData
     *
     * @return $this
     *
     * @throws Exception
     */
    protected function _savePgAddress($pgAddressData)
    {
        $helper = Mage::helper('tig_myparcel');
        $quote  = $this->getQuote();

        /**
         * Check if the quote's shipping method is MyParcel.
         */
        $shippingMethod = $quote->getShippingAddress()->getShippingMethod();
        if (!$helper->shippingMethodIsMyParcel($shippingMethod)) {
            return $this;
        }

        /**
         * Create a new address and add the address data.
         */
        $pgAddress = Mage::getModel('sales/quote_address');
        $pgAddress->setAddressType($helper::PG_ADDRESS_TYPE)
                  ->setCity($pgAddressData['city'])
                  ->setCountryId($pgAddressData['country_id'])
                  ->setPostcode($pgAddressData['postcode'])
                  ->setCompany($pgAddressData['company'])
                  ->setFirstname('-')
                  ->setLastname('-')
                  ->setTelephone($pgAddressData['telephone'])
                  ->setStreet($pgAddressData['street']);

        /**
         * Add the address to the quote and save the quote.
         */
        $quote->addAddress($pgAddress)
              ->save();

        /**
         * Save the address.
         * This should not be required, but we've encountered a few cases where this was not done automatically by
         * saving the quote.
         */
        $pgAddress->save();

        /**
         * If this quote has been deactivated, check if it has an order.
         *
         * This is required for OneStepCheckout.
         */
        if (!$quote->getIsActive()) {
            /**
             * @var Mage_Sales_Model_Order $order
             */
            $order = Mage::getModel('sales/order')->load($quote->getId(), 'quote_id');
            if ($order && $order->getId()) {
                /**
                 * Save the PakjeGemak address to the order.
                 */
                $this->_copyAddressToOrder($order, $pgAddress);
            }
        }

        return $this;
    }

    /**
     * Copies a given address to the order.
     *
     * @param Mage_Sales_Model_Order         $order
     * @param Mage_Sales_Model_Quote_Address $address
     *
     * @return $this
     *
     * @throws Exception
     */
    protected function _copyAddressToOrder(Mage_Sales_Model_Order $order, Mage_Sales_Model_Quote_Address $address)
    {
        /**
         * Convert the quote address to an order address and add it to the order.
         */
        $orderAddress = Mage::getModel('sales/convert_quote')->addressToOrderAddress($address);

        $order->addAddress($orderAddress)
              ->save();

        /**
         * This is a fix for the order address missing a parent ID.
         */
        if (!$orderAddress->getParentId()) {
            $orderAddress->setParentId($order->getId());
        }

        /**
         * This is required for some PSP extensions which will not save the PakjeGemak address otherwise.
         */
        $orderAddress->save();

        return $this;
    }

    /**
     * Validates an address and returns the validated data.
     *
     * @param array $address
     *
     * @return array
     *
     * @throws TIG_MyParcel2014_Exception
     */
    protected function _validateAddress($address)
    {
        if (!isset($address['city'])
            || !isset($address['country_id'])
            || !isset($address['street'])
            || !is_array($address['street'])
            || !isset($address['postcode'])
            || !isset($address['company'])
        ) {
            throw new TIG_MyParcel2014_Exception(
                Mage::helper('tig_myparcel')->__(
                    'Invalid argument supplied. A valid PakjeGemak address must contain at least a company name, city, '
                    . 'country code, street and zipcode.'
                ),
                'MYPA-0015'
            );
        }

        $city        = $address['city'];
        $countryCode = $address['country_id'];
        $street      = $address['street'][1];
        $houseNumber = $address['street'][2];
        $postcode    = str_replace(' ', '', $address['postcode']);
        $name        = $address['company'];

        $countryCodes = Mage::getResourceModel('directory/country_collection')->getColumnValues('iso2_code');

        $cityValidator        = new Zend_Validate_Regex(array('pattern' => self::CITY_NAME_REGEX));
        $countryCodeValidator = new Zend_Validate_InArray(array('haystack' => $countryCodes));
        $streetValidator      = new Zend_Validate_Regex(array('pattern' => self::STREET_NAME_REGEX));
        $housenumberValidator = new Zend_Validate_Digits();
        $postcodeValidator    = new Zend_Validate_PostCode('nl_NL');
        $nameValidator        = new Zend_Validate_Regex(array('pattern' => self::NAME_REGEX));

        if (!$cityValidator->isValid($city)
            || !$countryCodeValidator->isValid($countryCode)
            || !$streetValidator->isValid($street)
            || !$housenumberValidator->isValid($houseNumber)
            || !$postcodeValidator->isValid($postcode)
            || !$nameValidator->isValid($name)
        ) {
            throw new TIG_MyParcel2014_Exception(
                Mage::helper('tig_myparcel')->__('Invalid PakjeGemak address.'),
                'MYPA-0016'
            );
        }

        $data = array(
            'city'        => $city,
            'countryCode' => $countryCode,
            'street'      => array(
                1 => $street,
                2 => $houseNumber,
                3 => '',
            ),
            'postcode'    => $postcode,
            'name'        => $name,
            'telephone'   => '-'
        );

        if (isset($address['telephone']) && !empty($address['telephone'])) {
            $phoneNumber = $address['telephone'];
            $phoneNumber = str_replace(array('-', ' '), '', $phoneNumber);
            $phoneNumberValidator = new Zend_Validate_Regex(array('pattern' => self::PHONE_NUMBER_REGEX));

            if (!$phoneNumberValidator->isValid($phoneNumber)) {
                throw new TIG_MyParcel2014_Exception(
                    Mage::helper('tig_myparcel')->__('Invalid phone number.'),
                    'MYPA-0017'
                );
            }

            $data['telephone'] = $phoneNumber;
        }

        if (!isset($address['street'][3])) {
            return $data;
        }

        $houseNumberExtension = $address['street'][3];

        $houseNumberExtensionValidator = new Zend_Validate_Regex(array('pattern' => self::HOUSENR_EXT_REGEX));

        if (!$houseNumberExtensionValidator->isValid($houseNumberExtension)) {
            throw new TIG_MyParcel2014_Exception(
                Mage::helper('tig_myparcel')->__('Invalid housenumber extension.'),
                'MYPA-0018'
            );
        }

        $data['street'][3] = $houseNumberExtension;

        return $data;
    }
}
