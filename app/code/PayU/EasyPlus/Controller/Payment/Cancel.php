<?php
/**
 * PayU_EasyPlus cancelled checkout controller
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

class Cancel extends AbstractAction
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
     * Cancel Express Checkout
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        try {
            $this->_initReference(false);
          
            // if there is an order - cancel it
            $orderId = $this->_getCheckoutSession()->getLastOrderId();

            /** @var \Magento\Sales\Model\Order $order */
            $order = $orderId ? $this->_orderFactory->create()->load($orderId) : false;
            if ($order && $order->getId() 
                && $order->getQuoteId() == $this->_getSession()->getQuoteId()) 
            {
                $order->cancel()->save();
                $this->clearCheckoutSessionData();
                $this->messageManager->addSuccessMessage(
                    __('Payment unsuccessful. Checkout and Order have been canceled.')
                );
            } else {
                $this->messageManager->addSuccessMessage(
                    __('Payment unsuccessful. Checkout has been canceled.')
                );
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Unable to cancel Checkout'));
        }

        /** @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('checkout/cart');
    }
}