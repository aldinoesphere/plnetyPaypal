<?php

namespace PayPal\Providers\DataProvider;

use PayPal\Helper\PaymentHelper;
use Plenty\Modules\Frontend\Contracts\Checkout;
use Plenty\Plugin\Templates\Twig;

class PayPalPlusReinitializePayment
{
    public function call(Twig $twig, $arg):string
    {
        $paymentHelper = pluginApp(PaymentHelper::class);
        $paymentMethodId = $paymentHelper->getPayPalMopIdByPaymentKey(PaymentHelper::PAYMENTKEY_PAYPALPLUS);
        return $twig->render('PayPal::PayPalPlusReinitializePayment', ["order" => $arg[0], "paymentMethodId" => $paymentMethodId]);
    }
}
