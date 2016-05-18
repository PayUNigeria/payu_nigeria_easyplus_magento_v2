<?php
/**
 * PayU_EasyPlus payment method model
 *
 * @category    PayU
 * @package     PayU_EasyPlus
 * @author      Kenneth Onah
 * @copyright   PayU South Africa (http://payu.co.za)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace PayU\EasyPlus\Model;

use Magento\Framework\DataObject;
use PayU\EasyPlus\Model\ConfigProvider;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;

/**
 * Redirect payment method model
 */
class RedirectPaymentMethod extends \Magento\Payment\Model\Method\AbstractMethod
{
    const CODE = 'redirectpaymentmethod';
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = self::CODE;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isGateway = false;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canOrder = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canAuthorize = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canCapture = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_isInitializeNeeded = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canCapturePartial = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canUseCheckout = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canUseInternal = false;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canFetchTransactionInfo = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canRefund = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canVoid = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canReviewPayment = true;
    
    protected $_easyPlusApi                 = false;
    protected $_dataFactory                 = false;
    protected $_storeManager                = false;
    protected $_checkoutSession             = false;
    protected $_session                     = false;
    protected $_paymentData                 = false;
    protected $_payuReference               = '';
    protected $_minAmount                   = null;
    protected $_maxAmount                   = null;
    protected $_redirectUrl                 = '';
    protected $_supportedCurrencyCodes      = array('ZAR');
    /**
     * Payment additional information key for payment action
     *
     * @var string
     */
    protected $_isOrderPaymentActionKey = 'is_order_action';

    /**
     * Payment additional information key for number of used authorizations
     *
     * @var string
     */
    protected $_authorizationCountKey = 'authorization_count';

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Session\Generic $session,
        \PayU\EasyPlus\Model\Api $api,
        \PayU\EasyPlus\Helper\DataFactory $dataFactory,
        array $data = array()
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            null,
            null,
            $data
        );

        $this->_dataFactory = $dataFactory;
        $this->_storeManager = $storeManager;
        $this->_checkoutSession = $checkoutSession;
        $this->_easyPlusApi = $api;
        $this->_session = $session;
        $this->_paymentData = $paymentData;

        $this->_easyPlusApi->setSafeKey(
            $this->getConfigData('safe_key')
        );
        $this->_easyPlusApi->setUsername(
            $this->getConfigData('api_username')
        );
        $this->_easyPlusApi->setPassword(
            $this->getConfigData('api_password')
        );
        $this->_easyPlusApi->setGatewayEndpoint(
            $this->getConfigData('gateway')
        );

        $this->_minAmount = $this->getConfigData('min_order_total');
        $this->_maxAmount = $this->getConfigData('max_order_total');
    }

    /**
     * Store setter
     *
     * @param \Magento\Store\Model\Store|int $store
     * @return $this
     */
    public function setStore($store)
    {
        $this->setData('store', $store);
        if (null === $store) {
            $store = $this->_storeManager->getStore()->getId();
        }
        
        return $this;
    }

    /**
     * Can be used in regular checkout
     *
     * @return bool
     */
    public function canUseCheckout()
    {
        return parent::canUseCheckout();
    }

    /**
     * Availability for currency
     *
     * @param string $currencyCode
     * @return bool
     */
    public function canUseForCurrency($currencyCode)
    {
        if (!in_array($currencyCode, $this->_supportedCurrencyCodes)) {
            return false;
        }
        return true;
    }

    /**
     * Payment action getter compatible with payment model
     *
     * @see \Magento\Sales\Model\Order\Payment::place()
     * @return string
     */
    public function getConfigPaymentAction()
    {
        return parent::getConfigPaymentAction();
    }

    /**
     * Attempt to accept a pending payment
     *
     * @param \Magento\Payment\Model\Info|Payment $payment
     * @return bool
     */
    public function acceptPayment(\Magento\Payment\Model\InfoInterface $payment)
    {
        parent::acceptPayment($payment);
        //return $this->_pro->reviewPayment($payment, \Magento\Paypal\Model\Pro::PAYMENT_REVIEW_ACCEPT);
    }

    /**
     * Instantiate state and set it to state object
     *
     * @param string $paymentAction
     * @param DataObject
     */
    public function initialize($paymentAction, $stateObject)
    {
        switch ($paymentAction) {
            case self::ACTION_AUTHORIZE:
            case self::ACTION_AUTHORIZE_CAPTURE:
                $payment = $this->getInfoInstance();
                $order = $payment->getOrder();
                $order->setCanSendNewEmailFlag(false);
                $payment->authorize(true, $order->getBaseTotalDue()); // base amount will be set inside
                $payment->setAmountAuthorized($order->getTotalDue());
                //$order->setState(Order::STATE_PENDING_PAYMENT);
                //$stateObject->setState(Order::STATE_PENDING_PAYMENT);
                //$stateObject->setStatus('processing');
                $stateObject->setIsNotified(false);
                break;
            default:
                break;
        }
    }

    /**
     * Payment order
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Validator\Exception
     */
    public function order(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $this->validateAmount($amount);
        return $this->_placeOrder($payment, $amount, 'order');
    }

    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        return $this->_placeOrder($payment, $amount, 'authorize');
    }

    /**
     * Send capture request to gateway
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Validator\Exception
     */
    /*public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if ($amount <= 0) {
            Mage::throwException(Mage::helper('paygate')->__('Invalid amount for capture.'));
        }
        $payment->setAmount($amount);
        $order = $payment->getOrder();
        $order->setCanSendNewEmailFlag(true);
        if ($payment->getParentTransactionId()) {
            $payment->setPayUTransactionType(self::REQUEST_TYPE_PRIOR_AUTH_CAPTURE);
            $payment->setTransactionId($this->_getRealParentTransactionId($payment));
        } else {
            $payment->setPayUTransactionType(self::REQUEST_TYPE_AUTH_CAPTURE);
        }
        $request= $this->_buildRequest($payment);
        $result = $this->_postRequest($request);
        switch ($result->getResponseCode()) {
            case self::RESPONSE_CODE_APPROVED:
                if ($result->getResponseReasonCode() == self::RESPONSE_REASON_CODE_APPROVED) {
                    if (!$payment->getParentTransactionId() ||
                        $result->getTransactionId() != $payment->getParentTransactionId()) {
                        $payment->setTransactionId($result->getTransactionId());
                    }
                    $payment
                        ->setIsTransactionClosed(0)
                        ->setTransactionAdditionalInfo($this->_realTransactionIdKey, $result->getTransactionId());
                    return $this;
                }
                Mage::throwException($this->_wrapGatewayError($result->getResponseReasonText()));
            case self::RESPONSE_CODE_DECLINED:
            case self::RESPONSE_CODE_ERROR:
                Mage::throwException($this->_wrapGatewayError($result->getResponseReasonText()));
            default:
                Mage::throwException(__('Payment capturing error.'));
        }
    }
    */
    protected function _placeOrder($payment, $amount, $type) 
    {
        $helper = $this->_dataFactory->create('frontend');

        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();
        $requestData = [];
        try {
            $requestData = [
                'Api'               => $this->_easyPlusApi->getApiVersion(),
                'Safekey'           => $this->_easyPlusApi->getSafeKey(),
                'TransactionType'   => $this->getConfigData('payment_type'),
                'AdditionalInformation' => [
                    'merchantReference'         => $this->getConfigData('merchant_ref'),
                    'cancelUrl'                 => $helper->getCancelUrl(),
                    'returnUrl'                 => $helper->getReturnUrl(),
                    'supportedPaymentMethods'   => $this->getConfigData('payment_methods'),
                    'redirectChannel'           => $this->getConfigData('redirect_channel'),
                    'secure3d'                  => $this->getConfigData('secure3d') ? 'True' : 'False'
                ],
                'Basket' => [
                    'description'   => sprintf('#%s, %s', $order->getIncrementId(), $order->getCustomerEmail()),
                    'amountInCents' => $amount * 100,
                    'currencyCode'  => $this->getConfigData('allowed_currency'),
                ],
                'Customer' => [
                    'merchantUserId'    => $order->getCustomerId(),
                    'email'             => $order->getCustomerEmail(),
                    'firstName'         => $order->getCustomerFirstName(),
                    'lastName'          => $order->getCustomerLastName(),
                ]
            ];

            $response = '';
            $response = $this->_easyPlusApi->setRequestData($requestData);
            if(isset($response['return']['payUReference'])) {
                $payuReference = $response['return']['payUReference'];
                $this->_session->setCheckoutReference($payuReference);
                $this->_easyPlusApi->setPayUReference($payuReference);
                $payment->setTransactionId($payuReference);
                $redirectUrl = $this->_easyPlusApi->getCheckoutUrl() . '?PayUReference=' . $payuReference;
                $this->_checkoutSession->setCheckoutRedirectUrl($redirectUrl);

                // set session variables
                $quoteId = $this->_checkoutSession->getQuote()->getId();
                $this->_session->setQuoteId($quoteId);
                $this->_checkoutSession->setLastQuoteId($quoteId)
                    ->setLastSuccessQuoteId($quoteId);
                $this->_checkoutSession->setLastOrderId($order->getId())
                    ->setLastRealOrderId($order->getIncrementId())
                    ->setLastOrderStatus($order->getStatus());
            } else {
                throw new \Magento\Framework\Validator\Exception(__('Inside PayU, server error encountered'));
            }
        } catch (\Exception $e) {
            $this->debugData(['request' => $requestData, 'exception' => $e->getMessage()]);
            $this->debugData(['response' => $response]);
            $this->_logger->error(__('Payment capturing error.'));
            throw new \Magento\Framework\Validator\Exception(__('Payment capturing error.'));
        }

        return $this;
    }

    /**
     * Fetch transaction details info
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param string $transactionId
     * @return array
     */
    public function fetchTransactionInfo(\Magento\Payment\Model\InfoInterface $payment, $transactionId)
    {
        return $this->_easyPlusApi->fetchTransactionInfo($payment, $transactionId);
    }

    /**
     * Check whether payment method can be used
     * @param \Magento\Quote\Api\Data\CartInterface|Quote|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        //return parent::isAvailable($quote) && $this->isMethodAvailable();
        return true;
    }

    /**
     * Void payment
     *
     * @param \Magento\Framework\DataObject|\Magento\Payment\Model\InfoInterface|Payment $payment
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function void(\Magento\Payment\Model\InfoInterface $payment)
    {
        //Switching to order transaction if needed
        if ($payment->getAdditionalInformation(
            $this->_isOrderPaymentActionKey
        ) && !$payment->getVoidOnlyAuthorization()
        ) {
            $orderTransaction = $this->getOrderTransaction($payment);
            if ($orderTransaction) {
                $payment->setParentTransactionId($orderTransaction->getTxnId());
                $payment->setTransactionId($orderTransaction->getTxnId() . '-void');
            }
        }
        return $this;
    }

    /**
     * Check whether method available for checkout or not
     *
     * @param null $methodCode
     *
     * @return bool
     */
    public function isMethodAvailable($methodCode = null)
    {
        $methodCode = $methodCode ?: self::$_code;

        return $this->isMethodActive($methodCode);
    }

    /**
     * Check whether method active in configuration and supported for merchant country or not
     *
     * @param string $method Method code
     * @return bool
     *
     * @todo: refactor this
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function isMethodActive($method)
    {
        $isEnabled = (bool)$this->getConfigData('active');

        return $this->isMethodSupportedForCountry($method) && $isEnabled;
    }

    /**
     * Check whether method supported for specified country or not
     *
     * @param string|null $method
     * @param string|null $countryCode
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function isMethodSupportedForCountry($method = null, $countryCode = null)
    {
        return true;
    }

    protected function validateAmount($amount)
    {
        if ($amount <= 0 || $amount < $this->_minAmount || $amount > $this->_maxAmount) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Invalid amount for checkout with this payment method.'));
        }
    }

    public function getCheckoutRedirectUrl()
    {
        return '';
    }

    /**
     * Get transaction with type auth
     *
     * @param OrderPaymentInterface $payment
     * @return false|\Magento\Sales\Api\Data\TransactionInterface
     */
    protected function getOrderTransaction($payment)
    {
        return $this->transactionRepository->getByTransactionType(
            Transaction::TYPE_AUTH,
            $payment->getId(),
            $payment->getOrder()->getId()
        );
    }
}
