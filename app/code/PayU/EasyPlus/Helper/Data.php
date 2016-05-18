<?php
/**
 * PayU_EasyPlus data helper model
 *
 * @category    PayU
 * @package     PayU_EasyPlus
 * @author      Kenneth Onah
 * @copyright   PayU South Africa (http://payu.co.za)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace PayU\EasyPlus\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Authorizenet\Model\Directpost;
use Magento\Authorizenet\Model\Authorizenet;

/**
 * PayU EasyPlus Data Helper
 */
class Data extends AbstractHelper
{
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $orderFactory;

    /**
     * Allowed currencies
     *
     * @var array
     */
    protected $allowedCurrencyCodes = ['ZAR'];

    /**
     * Transaction statuses key to value map
     *
     * @var array
     */
    protected $transactionStatuses = [
        'authorizedPendingCapture' => 'Authorized/Pending Capture',
        'capturedPendingSettlement' => 'Captured/Pending Settlement',
        'refundSettledSuccessfully' => 'Refund/Settled Successfully',
        'refundPendingSettlement' => 'Refund/Pending Settlement',
        'declined' => 'Declined',
        'expired' => 'Expired',
        'voided' => 'Voided',
        'FDSPendingReview' => 'FDS - Pending Review',
        'FDSAuthorizedPendingReview' => 'FDS - Authorized/Pending Review'
    ];

    /**
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        OrderFactory $orderFactory
    ) {
        $this->storeManager = $storeManager;
        $this->orderFactory = $orderFactory;
        parent::__construct($context);
    }

    /**
     * Set secure url checkout is secure for current store.
     *
     * @param string $route
     * @param array $params
     * @return string
     */
    protected function _getUrl($route, $params = [])
    {
        $params['_type'] = \Magento\Framework\UrlInterface::URL_TYPE_LINK;
        if (isset($params['is_secure'])) {
            $params['_secure'] = (bool)$params['is_secure'];
        } elseif ($this->storeManager->getStore()->isCurrentlySecure()) {
            $params['_secure'] = true;
        }
        return parent::_getUrl($route, $params);
    }

    /**
     * Retrieve save order url params
     *
     * @param string $controller
     * @return array
     */
    public function getSaveOrderUrlParams($controller)
    {
        $route = [];
        switch ($controller) {
            case 'onepage':
                $route['action'] = 'saveOrder';
                $route['controller'] = 'onepage';
                $route['module'] = 'checkout';
                break;

            case 'sales_order_create':
            case 'sales_order_edit':
                $route['action'] = 'save';
                $route['controller'] = 'sales_order_create';
                $route['module'] = 'admin';
                break;

            default:
                break;
        }

        return $route;
    }

    /**
     * Retrieve redirect url
     *
     * @param array $params
     * @return string
     */
    public function getRedirectUrl($params)
    {
        return $this->_getUrl('payu_easyplus/payment/redirect', $params);
    }

    /**
     * Retrieve place order url
     *
     * @param array $params
     * @return  string
     */
    public function getSuccessOrderUrl($params)
    {
        return $this->_getUrl('checkout/onepage/success', $params);
    }

    /**
     * Converts a lot of messages to message
     *
     * @param  array $messages
     * @return string
     */
    public function convertMessagesToMessage($messages)
    {
        return implode(' | ', $messages);
    }

    /**
     * Return message for gateway transaction request
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param string $requestType
     * @param string $lastTransactionId
     * @param \Magento\Framework\DataObject $card
     * @param bool|float $amount
     * @param bool|string $exception
     * @param bool|string $additionalMessage
     * @return bool|string
     */
    public function getTransactionMessage(
        $payment,
        $requestType,
        $lastTransactionId,
        $card,
        $amount = false,
        $exception = false,
        $additionalMessage = false
    ) {
        $message[] = __('Credit Card: xxxx-%1', $card->getCcLast4());
        if ($amount) {
            $message[] = __('amount %1', $this->formatPrice($payment, $amount));
        }
        $operation = $this->getOperation($requestType);
        if (!$operation) {
            return false;
        } else {
            $message[] = $operation;
        }
        $message[] = ($exception) ? '- ' . __('failed.') : '- ' . __('successful.');
        if ($lastTransactionId !== null) {
            $message[] = __('Authorize.Net Transaction ID %1.', $lastTransactionId);
        }
        if ($additionalMessage) {
            $message[] = $additionalMessage;
        }
        if ($exception) {
            $message[] = $exception;
        }
        return implode(' ', $message);
    }

    /**
     * Return operation name for request type
     *
     * @param  string $requestType
     * @return \Magento\Framework\Phrase|bool
     */
    protected function getOperation($requestType)
    {
        switch ($requestType) {
            case Authorizenet::REQUEST_TYPE_AUTH_ONLY:
                return __('authorize');
            case Authorizenet::REQUEST_TYPE_AUTH_CAPTURE:
                return __('authorize and capture');
            case Authorizenet::REQUEST_TYPE_PRIOR_AUTH_CAPTURE:
                return __('capture');
            case Authorizenet::REQUEST_TYPE_CREDIT:
                return __('refund');
            case Authorizenet::REQUEST_TYPE_VOID:
                return __('void');
            default:
                return false;
        }
    }

    /**
     * Format price with currency sign
     * @param  \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return string
     */
    protected function formatPrice($payment, $amount)
    {
        return $payment->getOrder()->getBaseCurrency()->formatTxt($amount);
    }

    /**
     * Get post return url
     *
     * @param null|int|string $storeId
     * @return string
     */
    public function getReturnUrl($storeId = null)
    {
        $baseUrl = $this->storeManager->getStore($storeId)
            ->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_LINK);
        return $baseUrl . 'payu_easyplus/payment/response';
    }

    /**
     * Get post cancel url
     *
     * @param null|int|string $storeId
     * @return string
     */
    public function getCancelUrl($storeId = null)
    {
        $baseUrl = $this->storeManager->getStore($storeId)
            ->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_LINK);
        return $baseUrl . 'payu_easyplus/payment/cancel';
    }

    /**
     * Get allowed currencies
     *
     * @return array
     */
    public function getAllowedCurrencyCodes()
    {
        return $this->allowedCurrencyCodes;
    }

    /**
     * Get translated transaction status label
     *
     * @param string $key
     * @return \Magento\Framework\Phrase|string
     */
    public function getTransactionStatusLabel($key)
    {
        return isset($this->transactionStatuses[$key]) ? __($this->transactionStatuses[$key]) : $key;
    }

    /**
     * Gateway error response wrapper
     *
     * @param string $text
     * @return \Magento\Framework\Phrase
     */
    public function wrapGatewayError($text)
    {
        return __('Gateway error: %1', $text);
    }
}
