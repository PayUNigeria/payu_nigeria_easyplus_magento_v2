<?php
/**
 * PayU_EasyPlus payment config provider
 *
 * @category    PayU
 * @package     PayU_EasyPlus
 * @author      Kenneth Onah
 * @copyright   PayU South Africa (http://payu.co.za)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace PayU\EasyPlus\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Payment\Helper\Data as PaymentHelper;
use PayU\EasyPlus\Helper\Data as EasyPlusHelper;

/**
 * Class Config
 */
class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'redirectpaymentmethod';

    /**
     * @var ResolverInterface
     */
    protected $localeResolver;

    /**
     * @var \Magento\Customer\Helper\Session\CurrentCustomer
     */
    protected $currentCustomer;

    /**
     * @var Helper
     */
    protected $easyplusHelper;

    /**
     * @var string[]
     */
    protected $methodCodes = [
        self::CODE
    ];

    /**
     * @var \Magento\Payment\Model\Method\AbstractMethod[]
     */
    protected $methods = [];

    /**
     * @var PaymentHelper
     */
    protected $paymentHelper;

    /**
     * @param ConfigFactory $configFactory
     * @param ResolverInterface $localeResolver
     * @param CurrentCustomer $currentCustomer
     * @param PaypalHelper $paypalHelper
     * @param PaymentHelper $paymentHelper
     */
    public function __construct(
        ResolverInterface $localeResolver,
        CurrentCustomer $currentCustomer,
        EasyPlusHelper $easyplusHelper,
        PaymentHelper $paymentHelper
    ) {
        $this->localeResolver = $localeResolver;
        $this->currentCustomer = $currentCustomer;
        $this->easyplusHelper = $easyplusHelper;
        $this->paymentHelper = $paymentHelper;

        foreach ($this->methodCodes as $code) {
            $this->methods[$code] = $this->paymentHelper->getMethodInstance($code);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        $config = [
            'payment' => [
                'easyPlus' => [
                    'redirectAcceptanceMarkHref' => $this->getPaymentMethodUrl(),
                    'redirectAcceptanceMarkSrc' => $this->getPaymentMethodImageUrl(),
                ]
            ]
        ];
        foreach ($this->methodCodes as $code) {
            if ($this->methods[$code]->isAvailable()) {
                $config['payment']['easyPlus']['redirectUrl'] = $this->getMethodRedirectUrl($code);
            }
        }
        return $config;
    }

    /**
     * Return redirect URL for method
     *
     * @param string $code
     * @return mixed
     */
    protected function getMethodRedirectUrl($code)
    {
        return $this->methods[$code]->getCheckoutRedirectUrl();
    }

    /**
     * Get "What Is PayPal" localized URL
     * Supposed to be used with "mark" as popup window
     *
     * @param \Magento\Framework\Locale\ResolverInterface $localeResolver
     * @return string
     */
    public function getPaymentMethodUrl(\Magento\Framework\Locale\ResolverInterface $localeResolver = null)
    {
        $countryCode = 'US';
        return sprintf(
            'https://www.paypal.com/%s/cgi-bin/webscr?cmd=xpt/Marketing/popup/OLCWhatIsPayPal-outside',
            strtolower($countryCode)
        );
    }

    /**
     * Get PayPal "mark" image URL
     * Supposed to be used on payment methods selection
     * $staticSize is applicable for static images only
     *
     * @param string $localeCode
     * @param float|null $orderTotal
     * @param string|null $pal
     * @param string|null $staticSize
     * @return string
     */
    public function getPaymentMethodImageUrl($localeCode = '', $staticSize = null)
    {
        return sprintf(
            'https://www.paypalobjects.com/webstatic/en_US/i/buttons/pp-acceptance-%s.png',
            $staticSize
        );
    }
}
