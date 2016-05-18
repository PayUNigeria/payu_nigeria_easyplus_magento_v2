<?php
/**
 * PayU_EasyPlus payment response validation controller
 *
 * @category    PayU
 * @package     PayU_EasyPlus
 * @author      Kenneth Onah
 * @copyright   PayU South Africa (http://payu.co.za)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace PayU\EasyPlus\Controller\Payment;

use PayU\EasyPlus\Controller\AbstractAction;
use Magento\Framework\Controller\ResultFactory;

class Response extends AbstractAction
{
    /**
     * Config mode type
     *
     * @var string
     */
    protected $_configType = 'PayU\EasyPlus\Model\ConfigProvider';

    /**
     * Config method type
     *
     * @var string
     */
    protected $_configMethod = \PayU\EasyPlus\Model\ConfigProvider::CODE;

    /**
     * Retrieve transaction information and validates payment
     *
     * @return void
     */
    public function execute()
    {
        try {
            $params = $this->getRequest()->getParams();
            //$this->_initReference(false);
          
            // if there is an order - cancel it
            $orderId = $this->_getCheckoutSession()->getLastOrderId();

            /** @var \Magento\Sales\Model\Order $order */
            $order = $orderId ? $this->_orderFactory->create()->load($orderId) : false;
            // TODO timeout
            if($params && isset($params['PayUReference'])) {
                $this->_paymentResponse->setResponseParam($params);
                $this->_paymentResponse->setOrder($order);
                $result = $this->_paymentResponse->validateResponse();
                $this->clearCheckoutSessionData();
                if($result !== true) {
                    $this->messageManager->addErrorMessage(
                        __($result)
                    );
                } else {
                    $this->messageManager->addSuccessMessage(
                        __('Payment was successful and we received your order with much fanfare')
                    );
                }
            } 
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Unable to validate Checkout'));
        }

        /** @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('checkout/cart');
    }
}
