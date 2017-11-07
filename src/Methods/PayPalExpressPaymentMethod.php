<?php // strict

namespace PayPal\Methods;

use PayPal\Services\PaymentService;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodService;
use Plenty\Plugin\Application;

/**
 * Class PayPalExpressPaymentMethod
 * @package PayPal\Methods
 */
class PayPalExpressPaymentMethod extends PaymentMethodService
{
    /**
     * @var PaymentService
     */
    private $paymentService;

    /**
     * PayPalExpressPaymentMethod constructor.
     * @param PaymentService $paymentService
     */
    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService   = $paymentService;
        $this->paymentService->loadCurrentSettings('paypal');
    }

    /**
     * Check whether PayPal Express is active
     *
     * @return bool
     */
    public function isActive():bool
    {
        return false;
    }

    /**
     * Check if a express checkout for this payment method is available
     *
     * @return bool
     */
    public function isExpressCheckout():bool
    {
        return true;
    }

    /**
     * Check if it is allowed to switch to this payment method
     *
     * @param int $orderId
     * @return bool
     */
    public function isSwitchableTo($orderId)
    {
        return false;
    }
    
    /**
     * Check if it is allowed to switch from this payment method
     *
     * @param int $orderId
     * @return bool
     */
    public function isSwitchableFrom($orderId)
    {
        return true;
    }

    /**
     * Get the path of the icon
     *
     * @return string
     */
    public function getIcon()
    {
        $lang = 'de';
        if( array_key_exists('language', $this->paymentService->settings) &&
            array_key_exists($lang, $this->paymentService->settings['language']) &&
            array_key_exists('logo', $this->paymentService->settings['language'][$lang]))
        {
            switch ($this->paymentService->settings['language'][$lang]['logo'])
            {
                case 0:
                    break;
                case 1:
                    break;
                case 2:
                    break;
            }
        }
        /** @var Application $app */
        $app = pluginApp(Application::class);
        $icon = $app->getUrlPath('paypal').'/images/logos/de-pp-logo.png';

        return $icon;
    }
}
