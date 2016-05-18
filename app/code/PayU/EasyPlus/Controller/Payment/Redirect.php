<?php
/**
 *
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace PayU\EasyPlus\Controller\Payment;

use PayU\EasyPlus\Controller\AbstractAction;
use Magento\Framework\Controller\ResultFactory;

class Redirect extends AbstractAction
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
     * Retrieve params and put javascript into iframe
     *
     * @return void
     */
    public function execute()
    {
        try {    
            $url = $this->_checkoutSession->getCheckoutRedirectUrl();
            if($url) {
                return $this->getResponse()->setRedirect($url);
            } else {
                $this->messageManager->addErrorMessage(
                    __('Unable to redirect to PayU. Checkout has been canceled.')
                );
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Unable to redirect to PayU. Server error encountered'));
        }

        /** @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('checkout/cart');
    }
}
