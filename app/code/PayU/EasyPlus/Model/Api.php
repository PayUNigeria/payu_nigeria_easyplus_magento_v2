<?php
/**
 * PayU_EasyPlus PayU API
 *
 * @category    PayU
 * @package     PayU_EasyPlus
 * @author      Kenneth Onah
 * @copyright   PayU South Africa (http://payu.co.za)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace PayU\EasyPlus\Model;

use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Api extends \Magento\Framework\DataObject
{
    protected $scopeConfig;
    protected static $ns = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';

    private static $_soapClient = null;

    // @var string The base sandbox URL for the PayU API endpoint.
    protected static $sandboxUrl = 'https://staging.payu.co.za/service/PayUAPI';
    protected static $sandboxCheckoutUrl = 'https://staging.payu.co.za/rpp.do';

    // @var string The base live URL for the PayU API endpoint.
    protected static $liveUrl = 'https://secure.payu.co.za/service/PayUAPI';
    protected static $liveCheckoutUrl = 'https://secure.payu.co.za/rpp.do';

    // @var string The PayU safe key to be used for requests.
    protected $safeKey;

    // @var string|null The version of the PayU API to use for requests.
    protected static $apiVersion = 'ONE_ZERO';

    protected static $username = '';

    protected static $password = '';

    protected $merchantRef = '';

    protected $payuReference = '';
    protected $payuTransactionData = [];

    protected static $rppUrl = '';
    protected static $checkoutUrl = '';

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @return string The safe key used for requests.
     */
    public function getSafeKey()
    {
        return $this->safeKey;
    }

    /**
     * Sets the safe key to be used for requests.
     *
     * @param string $safeKey
     */
    public function setSafeKey($safeKey)
    {
        $this->safeKey = $safeKey;
    }

    /**
     * @return string The API version used for requests. null if we're using the
     *    latest version.
     */
    public static function getApiVersion()
    {
        return self::$apiVersion;
    }

    /**
     * @return string The soap user used for requests.
     */
    public static function getUsername()
    {
        return self::$username;
    }

    /**
     * Sets the soap username to be used for requests.
     *
     * @param string $username
     */
    public static function setUsername($username)
    {
        self::$username = $username;
    }

    /**
     * @return string The soap password used for requests.
     */
    public static function getPassword()
    {
        return self::$password;
    }

    /**
     * Sets the soap password to be used for requests.
     *
     * @param string $password
     */
    public static function setPassword($password)
    {
        self::$password = $password;
    }

    /**
     * @return string The merchant reference to identify captured payments..
     */
    public function getMerchantReference()
    {
        return $this->$reference;
    }

    /**
     * Sets the merchant reference to identify captured payments.
     *
     * @param string $reference
     */
    public function setMerchantReference($reference)
    {
        $this->$reference = $reference;

        return $this;
    }

    /**
     * @return string The reference from PayU.
     */
    public function getPayUReference()
    {
        return $this->payuReference;
    }

    /**
     * Sets the PayU reference.
     *
     * @param string $reference
     */
    public function setPayUReference($reference)
    {
        $this->payuReference = $reference;

        return $this;
    }

    /**
     * @return string The soap wsdl endpoint to send requests.
     */
    public static function getSoapEndpoint()
    {
        return self::$rppUrl;
    }

    /**
     * @return string The redirect payment page url to be used for requests.
     */
    public static function getCheckoutUrl()
    {
        return self::$checkoutUrl;
    }

    /**
     * Sets the redirect payment page url to be used for requests.
     *
     * @param string $gateway 
     */
    public static function setGatewayEndpoint($gateway)
    {
        if(!$gateway) {
            self::$rppUrl = self::$sandboxUrl;
            self::$checkoutUrl = self::$sandboxCheckoutUrl;
        } else {
            self::$rppUrl = self::$liveUrl;
            self::$checkoutUrl = self::$liveCheckoutUrl;
        }
    }

    private static function getSoapHeader()
    {
        $header  = '<wsse:Security SOAP-ENV:mustUnderstand="1" xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">';
        $header .= '<wsse:UsernameToken wsu:Id="UsernameToken-9" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">';
        $header .= '<wsse:Username>'.self::getUsername().'</wsse:Username>';
        $header .= '<wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">'.self::getPassword().'</wsse:Password>';
        $header .= '</wsse:UsernameToken>';
        $header .= '</wsse:Security>';

        return $header;
    }

    public function getResponseData($txn_id)
    {
        $method = \PayU\EasyPlus\Model\ConfigProvider::CODE;
        $reference = isset($txn_id['PayUReference']) ? $txn_id['PayUReference'] : $txn_id;
        $data = array();
        $data['Api'] = self::getApiVersion();
        $data['Safekey'] = $this->scopeConfig->getValue(
                        "payment/{$method}/safe_key",
                        \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $data['AdditionalInformation']['payUReference'] = $reference;

        $result = self::getSoapSingleton()->getTransaction($data);
        $this->payuTransactionData = json_decode(json_encode($result), true);
        return $this->payuTransactionData;
    }

    public function setRequestData($requestData)
    {        
        $response = self::getSoapSingleton()->setTransaction($requestData);

        return json_decode(json_encode($response), true);
    }

    private static function getSoapSingleton()
    {
        if(is_null(self::$_soapClient))
        {
            $header = self::getSoapHeader();
            $soapWsdlUrl = self::getSoapEndpoint().'?wsdl';
            self::$rppUrl = $soapWsdlUrl;
            
            $headerbody = new \SoapVar($header, XSD_ANYXML, null, null, null);
            $soapHeader = new \SOAPHeader(self::$ns, 'Security', $headerbody, true);

            self::$_soapClient = new \SoapClient($soapWsdlUrl, array('trace' => 1, 'exception' => 0));
            self::$_soapClient->__setSoapHeaders($soapHeader);
        }
        return self::$_soapClient;
    }

    public function fetchTransactionInfo(\Magento\Payment\Model\InfoInterface $payment, $transactionId)
    {
        $data = $this->getResponseData($transactionId);
        $this->importPaymentInfo($this, $payment);
        return $data ? $data : [];
    }

    /**
     * Transfer transaction/payment information from API instance to order payment
     *
     * @param \Magento\Framework\DataObject|AbstractApi $from
     * @param \Magento\Payment\Model\InfoInterface $to
     * @return $this
     */
    public function importPaymentInfo(\Magento\Framework\DataObject $from, \Magento\Payment\Model\InfoInterface $to)
    {
        /**
         * Detect payment review and/or frauds
         */
        if ($from->isFraudDetected()) {
            $to->setIsTransactionPending(true);
            $to->setIsFraudDetected(true);
        }

        // give generic info about transaction state
        if ($from->isPaymentSuccessful()) {
            $to->setIsTransactionApproved(true);
        } else {
            $to->setIsTransactionDenied(true);
        }

        return $this;
    }

    public function isPaymentSuccessful()
    {
        return $this->payuTransactionData['return']['successful'];
    }

    public function getTotalCaptured()
    {
        return ($this->payuTransactionData['return']['paymentMethodsUsed']['amountInCents'] / 100);
    }

    public function getDisplayMessage()
    {
        return $this->payuTransactionData['return']['displayMessage'];
    }

    public function isFraudDetected()
    {
        return isset($this->payuTransactionData['return']['fraud']['resultCode']);   
    }

    public function getTransactionState()
    {
        return $this->payuTransactionData['return']['transactionState'];   
    }
}
