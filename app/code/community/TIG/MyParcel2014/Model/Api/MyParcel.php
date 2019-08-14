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
 *
 * Myparcel API class. Contains all the functionality to connect to Myparcel and get information or create consignments
 *
 * @method TIG_MyParcel2014_Model_Api_MyParcel setStoreId(int $value)
 * @method bool hasStoreId()
 */
class TIG_MyParcel2014_Model_Api_MyParcel extends Varien_Object
{
    /**
     * Supported request types.
     */
    const REQUEST_TYPE_CREATE_CONSIGNMENT  = 'create-consignment';
    const REQUEST_TYPE_CREATE_CONSIGNMENTS = 'create-consignments';
    const REQUEST_TYPE_REGISTER_CONFIG     = 'register-config';
    const REQUEST_TYPE_RETRIEVE_PDF        = 'retrieve-pdf';
    const REQUEST_TYPE_RETRIEVE_PDFS       = 'retrieve-pdfs';
    const REQUEST_TYPE_RETRIEVE_STATUS     = 'retrieve-status';
    const REQUEST_TYPE_CONSIGNMENT_CREDIT  = 'consignment-credit';
    const REQUEST_TYPE_CREATE_RETOURLINK   = 'create-retourlink';

    /**
     * @var string
     */
    protected $apiUsername = '';

    /**
     * @var string
     */
    protected $apiKey = '';

    /**
     * @var string
     */
    protected $apiUrl = '';

    /**
     * @var string
     */
    protected $requestString = '';

    /**
     * @var string
     */
    protected $requestHash = '';

    /**
     * @var string
     */
    protected $requestType = '';

    /**
     * @var string
     */
    protected $requestResult = false;

    /**
     * @var string
     */
    protected $requestError = false;

    /**
     * @var string
     */
    protected $requestErrorDetail = false;

    /**
     * sets the api username and api key on construct.
     *
     * @return void
     */
    protected function _construct()
    {
        $storeId  = $this->getStoreId();
        $helper   = Mage::helper('tig_myparcel');
        $username = $helper->getConfig('username', 'api', $storeId);
        $key      = $helper->getConfig('key', 'api', $storeId, true);

        if (empty($username) && empty($key)) {
            return;
        }

        $this->apiUrl      = $helper->getConfig('url') . '/api/';
        $this->apiUsername = $username;
        $this->apiKey      = $key;
    }

    /**
     * @return int
     */
    public function getStoreId()
    {
        if ($this->hasStoreId()) {
            return $this->_getData('store_id');
        }

        $storeId = Mage::app()->getStore()->getId();

        $this->setStoreId($storeId);
        return $storeId;
    }

    /**
     * returns the response as an array, when an error occurs it will return the error message as a string
     * @return array
     */
    public function getRequestResponse()
    {
        if(!empty($this->requestError)){
            return $this->requestError;
        }

        return $this->requestResult;
    }

    public function getRequestErrorDetail()
    {
        $errorDetail = $this->requestErrorDetail;

        if(!$errorDetail){
            return false;
        }

        if(is_string($errorDetail))
        {
            return $errorDetail;
        }

        if(is_array($errorDetail) && !empty($errorDetail))
        {
            $return = $this->requestError.' - ';
            foreach($errorDetail as $key => $errorMessage)
            {
                $return .= $key;
                if(is_string($errorMessage))
                {
                    $return .= ': '.$errorMessage;
                }

                if(is_array($errorMessage) && !empty($errorMessage))
                {
                    $return .= ':<br/>'."\n";
                    foreach($errorMessage as $key => $value)
                    {
                        $return .= $key .' - '.$value[0];
                    }
                }
            }

            if($return == '')
            {
                return false;
            }

            return $return;
        }
        return false;
    }

    /**
     * send the created request to MyParcel
     *
     * @return $this|false|array|string
     */
    public function sendRequest()
    {
        if (!$this->_checkConfigForRequest()) {
            return false;
        }

        $body = $this->requestString . '&signature=' . $this->requestHash;

        $options = array(
            CURLOPT_POST           => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_AUTOREFERER    => true,
        );

        $config = array(
            'header'  => 0,
            'timeout' => 60,
        );

        $url = $this->apiUrl . $this->requestType;

        $helper = Mage::helper('tig_myparcel');

        $helper->log($url);

        parse_str(urldecode($body), $bodyArray);
        $bodyArray['json'] = json_decode($bodyArray['json']);

        $helper->log($bodyArray['json']);

        $request = new Varien_Http_Adapter_Curl();
        foreach($options as $option => $value)
        {
            $request->addOption($option, $value);
        }
        $request->setConfig($config)
            ->write(Zend_Http_Client::POST, $url, '1.1', array(), $body);

        $response = $request->read();
        $helper->log(json_decode($response, true));

        if ($response === false) {
            $error              = $request->getError();
            $this->requestError = $error;

            return $this;
        }

        $result = json_decode($response, true);

        if(isset($result['error'])){
            $this->requestError = $result['error'];
            unset($result['error']);
            $this->requestErrorDetail = $result;
            $request->close();

            return $this;
        }

        $this->requestResult = $result;

        $request->close();

        return $this;
    }

    /**
     * Checks if all the requirements are set to send a request to MyParcel
     * @return bool
     */
    protected function _checkConfigForRequest()
    {
        if(empty($this->apiUsername) || empty($this->apiKey)){
            return false;
        }

        if(empty($this->requestType)){
            return false;
        }

        if(empty($this->requestString)){
            return false;
        }

        if(empty($this->requestHash)){
            return false;
        }

        return true;
    }

    /**
     * Prepares the API for processing a create consignment request.
     *
     * @param TIG_MyParcel2014_Model_Shipment $myParcelShipment
     *
     * @return $this
     */
    public function createConsignmentRequest(TIG_MyParcel2014_Model_Shipment $myParcelShipment)
    {
        $data = array(
            'process'     => 1,
            'consignment' => $this->_getConsignmentData($myParcelShipment),
        );

        $requestString = $this->_createRequestString($data);

        $this->_setRequestParameters($requestString, self::REQUEST_TYPE_CREATE_CONSIGNMENT);

        return $this;
    }

    /**
     * @TODO for use in massaction
     * @param array $shippingIds
     */
    public function createConsignmentsRequest($shippingIds = array()){}

    /**
     * Prepares the API for retrieving pdf's for a consignment ID.
     *
     * @param array $consignmentId
     *
     * @return $this
     */
    public function createRetrievePdfRequest($consignmentId)
    {
        $data = array(
            'consignment_id' => $consignmentId,
            'format'         => 'json',
        );

        $requestString = $this->_createRequestString($data);

        $this->_setRequestParameters($requestString, self::REQUEST_TYPE_RETRIEVE_PDF);

        return $this;
    }

    /**
     * Prepares the API for retrieving pdf's for an array of consignment IDs.
     *
     * @param array       $consignmentIds
     * @param int|string  $start
     * @param string      $perpage
     *
     * @return $this
     */
    public function createRetrievePdfsRequest($consignmentIds = array(), $start = 1, $perpage = 'A4')
    {
        $data = array(
            'consignments' => $consignmentIds,
            'start'        => intval($start),
            'perpage'      => strtoupper($perpage),
            'format'       => 'json',
        );

        $requestString = $this->_createRequestString($data);

        $this->_setRequestParameters($requestString, self::REQUEST_TYPE_RETRIEVE_PDFS);

        return $this;
    }

    /**
     * Prepares the API for retrieving pdf's for a consignment ID.
     *
     * @return $this
     */
    public function createRegisterConfigRequest()
    {
        $data = array(
            'webshop_version' => 'Magento ' . Mage::getVersion(),
            'plugin_version'  => (string) Mage::getConfig()->getModuleConfig('TIG_MyParcel2014')->version,
            'php_version'     => phpversion(),
        );

        $requestString = $this->_createRequestString($data);

        $this->_setRequestParameters($requestString, self::REQUEST_TYPE_REGISTER_CONFIG);

        return $this;
    }

    /**
     * @param $consignmentId
     * @return $this
     */
    public function createRetrieveStatusRequest($consignmentId)
    {
        $data = array(
            'consignment_id' => $consignmentId,
        );

        $requestString = $this->_createRequestString($data);

        $this->_setRequestParameters($requestString, self::REQUEST_TYPE_RETRIEVE_STATUS);

        return $this;
    }

    /**
     * @param $consignmentId
     * @return $this
     */
    public function createConsignmentCreditRequest($consignmentId)
    {
        $data = array(
            'consignment_id' => $consignmentId,
        );

        $requestString = $this->_createRequestString($data);

        $this->_setRequestParameters($requestString, self::REQUEST_TYPE_CONSIGNMENT_CREDIT);

        return $this;
    }

    /**
     * @param $consignmentId
     * @return $this
     */
    public function createRetourlinkRequest($consignmentId)
    {
        $data = array(
            'consignment_id' => $consignmentId,
        );

        $requestString = $this->_createRequestString($data);

        $this->_setRequestParameters($requestString, self::REQUEST_TYPE_CREATE_RETOURLINK);

        return $this;
    }

    /**
     * Gets the shipping address and product code data for this shipment.
     *
     * @param TIG_MyParcel2014_Model_Shipment $myParcelShipment
     *
     * @return array
     */
    protected function _getConsignmentData(TIG_MyParcel2014_Model_Shipment $myParcelShipment)
    {
        $helper = Mage::helper('tig_myparcel');
        $order = $myParcelShipment->getOrder();
        $storeId = $order->getStore()->getId();

        $shippingAddress = $myParcelShipment->getShippingAddress();
        $streetData      = $helper->getStreetData($shippingAddress,$storeId);
        $email           = $myParcelShipment->getOrder()->getCustomerEmail();

        $data = array(
            'ToAddress'     => array(
                'country_code'    => $shippingAddress->getCountry(),
                'name'            => $shippingAddress->getName(),
                'business'        => $shippingAddress->getCompany(),
                'postcode'        => $shippingAddress->getPostcode(),
                'street'          => $streetData['streetname'],
                'house_number'    => $streetData['housenumber'],
                'number_addition' => $streetData['housenumberExtension'],
                'town'            => $shippingAddress->getCity(),
                'email'           => $email,
            ),
            'ProductCode'    => $this->_getProductCodeData($myParcelShipment),
            'insured_amount' => $this->_getInsuredAmount($myParcelShipment),
            'custom_id'      => $order->getIncrementId(),
            'comments'       => '',
        );

        if($shippingAddress->getCountry() != 'NL')
        {
            $data['ToAddress']['eps_postcode'] = $data['ToAddress']['postcode'];
            $data['ToAddress']['street'] = trim(str_replace('  ', ' ', implode(' ', $streetData)));
            unset($data['ToAddress']['postcode']);
            unset($data['ToAddress']['house_number']);
            unset($data['ToAddress']['number_addition']);
        }

        // add customs data for EUR3 and World shipments
        if($helper->countryNeedsCustoms($shippingAddress->getCountry()))
        {
            $data['customs_shipment_type'] = $helper->getConfig('customs_type', 'shipment', $storeId);
            $data['customs_invoice']       = $order->getIncrementId();
            $data['CustomsContent']        = array();

            $customsContentType = $helper->getConfig('customs_hstariffnr', 'shipment', $storeId);
            if($myParcelShipment->getCustomsContentType()){
                $customsContentType = $myParcelShipment->getCustomsContentType();
            }

            $totalWeight = 0;
            $items = $myParcelShipment->getOrder()->getAllItems();
            $i = 0;
            foreach($items as $item)
            {
                if($item->getProductType() == 'simple')
                {
                    $parentId = $item->getParentItemId();
                    $weight = floatval($item->getWeight());
                    $price = floatval($item->getPrice());
                    $qty = intval($item->getQtyOrdered());

                    if(!empty($parentId))
                    {
                        $parent = Mage::getModel('sales/order_item')->load($parentId);

                        // TODO: check for multiple test cases with configurable and bundled products
                        if(empty($weight))
                        {
                            $weight = $parent->getWeight();
                        }
                        if(empty($price))
                        {
                            $price = $parent->getPrice();
                        }
                    }

                    $weight *= $qty;
                    $weight = max(array(1, $weight));
                    $totalWeight += $weight;

                    $price *= $qty;

                    $data['CustomsContent'][$i] = array(
                        'Description'     => $item->getName(),
                        'Quantity'        => $qty,
                        'Weight'          => $weight,
                        'Value'           => $price,
                        'HSTariffNr'      => $customsContentType,
                        'CountryOfOrigin' => Mage::getStoreConfig('general/country/default', $storeId),
                    );

                    if(++$i >= 5) {
                        break; // max 5 entries
                    }
                }
            }
            $data['weight'] = $totalWeight;
        }

        /**
         * If the customer has chosen to pick up their order at a PakjeGemak location, add the PakjeGemak address.
         */
        $pgAddress      = $helper->getPgAddress($myParcelShipment);
        $shippingMethod = $order->getShippingMethod();

        if ($pgAddress && $helper->shippingMethodIsPakjegemak($shippingMethod)) {
            $pgStreetData      = $helper->getStreetData($pgAddress,$storeId);
            $data['PgAddress'] = array(
                'country_code'    => $pgAddress->getCountry(),
                'name'            => $pgAddress->getName(),
                'business'        => $pgAddress->getCompany(),
                'postcode'        => $pgAddress->getPostcode(),
                'street'          => $pgStreetData['streetname'],
                'house_number'    => $pgStreetData['housenumber'],
                'number_addition' => $pgStreetData['housenumberExtension'],
                'town'            => $pgAddress->getCity(),
                'email'           => $email,
            );
        }

        return $data;
    }

    /**
     * Gets the product code parameters for this shipment.
     *
     * @param TIG_MyParcel2014_Model_Shipment $myParcelShipment
     *
     * @return array
     */
    protected function _getProductCodeData(TIG_MyParcel2014_Model_Shipment $myParcelShipment)
    {
        $data = array(
            'extra_size'           => 0,
            'home_address_only'    => $myParcelShipment->getHomeAddressOnly(),
            'signature_on_receipt' => $myParcelShipment->getSignatureOnReceipt(),
            'return_if_no_answer'  => $myParcelShipment->getReturnIfNoAnswer(),
            'insured'              => $myParcelShipment->getInsured(),
        );
        if($myParcelShipment->getShippingAddress()->getCountry() != 'NL')
        {
            // strip all Dutch domestic options if shipment is not NL
            unset($data['home_address_only']);
            unset($data['signature_on_receipt']);
            unset($data['return_if_no_answer']);
            unset($data['insured']);
        }

        return $data;
    }

    /**
     * Get the insured amount for this shipment.
     *
     * @param TIG_MyParcel2014_Model_Shipment $myParcelShipment
     *
     * @return int
     */
    protected function _getInsuredAmount(TIG_MyParcel2014_Model_Shipment $myParcelShipment)
    {
        if ($myParcelShipment->getInsured()) {
            return (int) $myParcelShipment->getInsuredAmount();
        }

        return 0;
    }

    /**
     * Creates a url-encoded request string.
     *
     * @param array $data
     *
     * @return string
     */
    protected function _createRequestString(array $data)
    {
        $json = json_encode($data);

        $string = http_build_query(
            array(
                'json'      => $json,
                'nonce'     => 0, // @TODO What are we supposed to do with this parameter
                'test'      => 0,
                'timestamp' => time(),
                'username'  => $this->apiUsername,
            )
        );

        return $string;
    }

    /**
     * Sets the parameters for an API call based on a string with all required request parameters and the requested API
     * method.
     *
     * @param string $requestString
     * @param string $requestType
     *
     * @return $this
     */
    protected function _setRequestParameters($requestString, $requestType)
    {
        $this->requestString = $requestString;
        $this->requestType   = $requestType;

        $this->_hashRequest();

        return $this;
    }

    /**
     * Creates a hash to ensure security, sets the $requestHash class-variable
     *
     * @return $this
     */
    protected function _hashRequest()
    {
        //generate hash
        $this->requestHash = hash_hmac('sha1', 'POST&' . urlencode($this->requestString), $this->apiKey);

        return $this;
    }
}
