<?php
/**
 * PayU_EasyPlus payement response validation model
 *
 * @category    PayU
 * @package     PayU_EasyPlus
 * @author      Kenneth Onah
 * @copyright   PayU South Africa (http://payu.co.za)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace PayU\EasyPlus\Model;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;

class Response extends \Magento\Framework\DataObject
{
	protected $_params;
	protected $_order;
	protected $_errorCode;
	protected $_api;

	public function __construct(
        \PayU\EasyPlus\Model\Error\Code $errorCodes,
        \PayU\EasyPlus\Model\Api $api,
        array $data = array()
	) {
		$this->_errorCode = $errorCodes;
		$this->_api = $api;
		parent::__construct($data);
	}

	public function setResponseParam($params)
	{
		$this->_params = $params;
	}

	public function getResponseParam()
	{
		return $this->_params;
	}

	public function setOrder($order)
	{
		$this->_order = $order;
	}

	public function getOrder()
	{
		return $this->_order;
	}

	public function validateResponse()
	{
		$payuData = $this->_api->getResponseData($this->getResponseParam());
		$order = $this->getOrder();

		if($this->_api->isPaymentSuccessful()) {
			$order->getPayment()->capture();
			return true;
		} else {
			$order->hold()->save();
			return $this->_api->getDisplayMessage();
		}
	}
}