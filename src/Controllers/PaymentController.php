<?php //strict

namespace PayPal\Controllers;

use PayPal\Models\LocalizedBasket;
use PayPal\Services\PayPalExpressService;
use PayPal\Services\PayPalInstallmentService;
use Plenty\Modules\Account\Address\Contracts\AddressRepositoryContract;
use Plenty\Modules\Account\Contact\Contracts\ContactAddressRepositoryContract;
use Plenty\Modules\Authorization\Services\AuthHelper;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Frontend\Contracts\Checkout;
use Plenty\Modules\Basket\Models\Basket;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Order\RelationReference\Models\OrderRelationReference;
use Plenty\Modules\Order\Shipping\Countries\Contracts\CountryRepositoryContract;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;
use Plenty\Modules\Payment\Models\Payment;

use PayPal\Services\SessionStorageService;
use Paypal\Services\PaymentService;
use Paypal\Services\PayPalPlusService;
use PayPal\Helper\PaymentHelper;
use Plenty\Plugin\Templates\Twig;

/**
 * Class PaymentController
 * @package PayPal\Controllers
 */
class PaymentController extends Controller
{
    /**
     * @var Request
     */
    private $request;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var ConfigRepository
     */
    private $config;

    /**
     * @var PaymentHelper
     */
    private $paymentHelper;

    /**
     * @var PaymentService
     */
    private $paymentService;

    /**
     * @var BasketRepositoryContract
     */
    private $basketContract;

    /**
     * @var OrderRepositoryContract
     */
    private $orderContract;

    /**
     * @var SessionStorageService
     */
    private $sessionStorage;

    /**
     * PaymentController constructor.
     *
     * @param Request $request
     * @param Response $response
     * @param ConfigRepository $config
     * @param PaymentHelper $paymentHelper
     * @param PaymentService $paymentService
     * @param BasketRepositoryContract $basketContract
     * @param OrderRepositoryContract $orderContract
     * @param SessionStorageService $sessionStorage
     */
    public function __construct(  Request $request,
        Response $response,
        ConfigRepository $config,
        PaymentHelper $paymentHelper,
        PaymentService $paymentService,
        BasketRepositoryContract $basketContract,
        OrderRepositoryContract $orderContract,
        SessionStorageService $sessionStorage)
    {
        $this->request          = $request;
        $this->response         = $response;
        $this->config           = $config;
        $this->paymentHelper    = $paymentHelper;
        $this->paymentService   = $paymentService;
        $this->basketContract   = $basketContract;
        $this->orderContract    = $orderContract;
        $this->sessionStorage   = $sessionStorage;
    }

    /**
     * PayPal redirects to this page if the payment could not be executed or other problems occurred
     */
    public function checkoutCancel($mode=PaymentHelper::MODE_PAYPAL)
    {
        // clear the PayPal session values
        $this->sessionStorage->setSessionValue(SessionStorageService::PAYPAL_PAY_ID, null);
        $this->sessionStorage->setSessionValue(SessionStorageService::PAYPAL_PAYER_ID, null);
        $this->sessionStorage->setSessionValue(SessionStorageService::PAYPAL_INSTALLMENT_CHECK, null);

        // Redirects to the cancellation page. The URL can be entered in the config.json.
        return $this->response->redirectTo('checkout');
    }

    /**
     * PayPal redirects to this page if the payment could not be executed or other problems occurred
     */
    public function payOrderCancel($mode=PaymentHelper::MODE_PAYPAL)
    {
        // clear the PayPal session values
        $this->sessionStorage->setSessionValue(SessionStorageService::PAYPAL_PAY_ID, null);
        $this->sessionStorage->setSessionValue(SessionStorageService::PAYPAL_PAYER_ID, null);

        // Redirects to the cancellation page. The URL can be entered in the config.json.
        return $this->response->redirectTo('my-account');
    }

    /**
     * PayPal redirects to this page if the payment was executed correctly
     */
    public function checkoutSuccess($mode=PaymentHelper::MODE_PAYPAL)
    {
        // Get the PayPal payment data from the request
        $paymentId    = $this->request->get('paymentId');
        $payerId      = $this->request->get('PayerID');

        // Get the PayPal Pay ID from the session
        $ppPayId    = $this->sessionStorage->getSessionValue(SessionStorageService::PAYPAL_PAY_ID);

        // Check whether the Pay ID from the session is equal to the given Pay ID by PayPal
        if($paymentId != $ppPayId)
        {
            return $this->checkoutCancel();
        }

        $this->sessionStorage->setSessionValue(SessionStorageService::PAYPAL_PAYER_ID, $payerId);

        // update or create a contact
        $this->paymentService->handlePayPalCustomer($paymentId, $mode);

        // Redirect to the success page. The URL can be entered in the config.json.
        return $this->response->redirectTo('place-order');
    }

    /**
     * PayPal redirects to this page if the payment was executed correctly
     *
     * @param $orderId
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function payOrderSuccess($orderId)
    {
        // Get the PayPal payment data from the request
        $paymentId    = $this->request->get('paymentId');
        $payerId      = $this->request->get('PayerID');

        // Get the PayPal Pay ID from the session
        $ppPayId    = $this->sessionStorage->getSessionValue(SessionStorageService::PAYPAL_PAY_ID);

        // Check whether the Pay ID from the session is equal to the given Pay ID by PayPal
        if($paymentId != $ppPayId)
        {
            return $this->payOrderCancel();
        }

        $this->sessionStorage->setSessionValue(SessionStorageService::PAYPAL_PAYER_ID, $payerId);

        // update or create a contact
        $this->paymentService->handlePayPalCustomer($paymentId);

        // Execute the payment
        $payPalPaymentData = $this->paymentService->executePayment();

        // Check whether the PayPal payment has been executed successfully
        if($this->paymentService->getReturnType() != 'errorCode')
        {
            $mopId = $this->sessionStorage->getSessionValue('MethodOfPaymentID');
            $paymentData = array();

            if(!empty($mopId))
                $paymentData['mopId'] = $mopId;

            // Create a plentymarkets payment from the paypal execution params
            $plentyPayment = $this->paymentHelper->createPlentyPayment((array)$payPalPaymentData, $paymentData);

            if($plentyPayment instanceof Payment)
            {
                // Assign the payment to an order in plentymarkets
                $this->paymentHelper->assignPlentyPaymentToPlentyOrder($plentyPayment, $orderId);
            }
        }
        else
        {

        }

        $orderContract = $this->orderContract;

        /** @var \Plenty\Modules\Authorization\Services\AuthHelper $authHelper */
        $authHelper = pluginApp(AuthHelper::class);

        /** @var Order $order */
        // use processUnguarded to find orders for guests
        $order = $authHelper->processUnguarded(
            function () use ($orderContract, $orderId) {
                //unguarded
                return $orderContract->findOrderById($orderId, ['relation']);
            }
        );

        $customerId = 0;
        foreach ($order->relations as $relation)
        {
            if($relation->referenceType == OrderRelationReference::REFERENCE_TYPE_CONTACT)
            {
                $customerId = $relation->referenceId;
            }
        }

        // Redirect to the success page. The URL can be entered in the config.json.
        if($customerId > 0)
        {
            return $this->response->redirectTo('confirmation/'.$orderId);
        }
        else
        {
            return $this->response->redirectTo('confirmation');
        }
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function prepareInstallment()
    {
        // Get the PayPal payment data from the request
        $paymentId    = $this->request->get('paymentId');
        $payerId      = $this->request->get('PayerID');

        // Get the PayPal Pay ID from the session
        $ppPayId    = $this->sessionStorage->getSessionValue(SessionStorageService::PAYPAL_PAY_ID);

        // Check whether the Pay ID from the session is equal to the given Pay ID by PayPal
        if($paymentId != $ppPayId)
        {
            return $this->checkoutCancel();
        }

        $this->sessionStorage->setSessionValue(SessionStorageService::PAYPAL_PAYER_ID, $payerId);
        $this->sessionStorage->setSessionValue(SessionStorageService::PAYPAL_INSTALLMENT_CHECK, 1);

        // Get the offered finacing costs
        /** @var PayPalInstallmentService $payPalInstallmentService */
        $payPalInstallmentService = pluginApp(\PayPal\Services\PayPalInstallmentService::class);
        $creditFinancingOffered = $payPalInstallmentService->getFinancingCosts($paymentId, PaymentHelper::MODE_PAYPAL_INSTALLMENT);
        $this->sessionStorage->setSessionValue(SessionStorageService::PAYPAL_INSTALLMENT_COSTS, $creditFinancingOffered);

        // Redirect to the success page. The URL can be entered in the config.json.
        return $this->response->redirectTo('checkout');
    }

    /**
     * PayPal redirects to this page if the express payment could not be executed or other problems occurred
     */
    public function expressCheckoutCancel()
    {
        // clear the PayPal session values
        $this->sessionStorage->setSessionValue(SessionStorageService::PAYPAL_PAY_ID, null);
        $this->sessionStorage->setSessionValue(SessionStorageService::PAYPAL_PAYER_ID, null);
        $this->sessionStorage->setSessionValue(SessionStorageService::PAYPAL_INSTALLMENT_CHECK, null);

        // Redirects to the cancellation page. The URL can be entered in the config.json.
        return $this->response->redirectTo('basket');

    }

    /**
     * PayPal redirects to this page if the express payment was executed correctly
     */
    public function expressCheckoutSuccess()
    {
        return $this->checkoutSuccess();
    }

    /**
     * PayPal Express review page
     */
    public function expressCheckoutReview(Twig $twig, Checkout $checkout)
    {
        // Get the PayPal payment data from the request
        $paymentId    = $this->request->get('paymentId');
        $payerId      = $this->request->get('PayerID');

        // Get the PayPal Pay ID from the session
        $ppPayId    = $this->sessionStorage->getSessionValue(SessionStorageService::PAYPAL_PAY_ID);

        // Check whether the Pay ID from the session is equal to the given Pay ID by PayPal
        if($paymentId != $ppPayId)
        {
            return $this->checkoutCancel();
        }

        $this->sessionStorage->setSessionValue(SessionStorageService::PAYPAL_PAYER_ID, $payerId);

        // update or create a contact
        $this->paymentService->handlePayPalCustomer($paymentId, PaymentHelper::MODE_PAYPALEXPRESS);

        $basket = LocalizedBasket::wrap($this->basketContract->load(), 'de');

        return $twig->render('PayPal::PayPalExpress.ReviewPage', [
            'data' => $basket
        ]);
    }

    /**
     * Redirect to PayPal Express Checkout
     */
    public function expressCheckout()
    {
        /** @var Basket $basket */
        $basket = $this->basketContract->load();

        /** @var Checkout $checkout */
        $checkout = pluginApp(\Plenty\Modules\Frontend\Contracts\Checkout::class);

        if($checkout instanceof Checkout)
        {
            $paymentMethodId = $this->paymentHelper->getPayPalMopIdByPaymentKey(PaymentHelper::PAYMENTKEY_PAYPALEXPRESS);
            if($paymentMethodId > 0)
            {
                $checkout->setPaymentMethodId((int)$paymentMethodId);
            }
        }

        // get the paypal-express redirect URL
        /** @var PayPalExpressService $payPalExpressService */
        $payPalExpressService = pluginApp(\PayPal\Services\PayPalExpressService::class);
        $redirectURL = $payPalExpressService->preparePayPalExpressPayment($basket);

        return $this->response->redirectTo($redirectURL);
    }

    /**
     * @param Twig $twig
     * @param BasketRepositoryContract $basketRepositoryContract
     * @param PayPalPlusService $paypalPlusService
     * @param Checkout $checkout
     * @param CountryRepositoryContract $countryRepositoryContract
     * @return string
     */
    public function refreshPayPalPlusWall(
        Twig                        $twig,
        BasketRepositoryContract    $basketRepositoryContract,
        PayPalPlusService           $paypalPlusService,
        Checkout                    $checkout,
        CountryRepositoryContract   $countryRepositoryContract)
    {
        $content = '';
        $this->paymentService->loadCurrentSettings('paypal');

        if(array_key_exists('payPalPlus', $this->paymentService->settings) && $this->paymentService->settings['payPalPlus'] == 1)
        {
            $content = $paypalPlusService->getPaymentWallContent($basketRepositoryContract->load(), $checkout, $countryRepositoryContract, false);
        }

        return $twig->render('PayPal::PayPalPlus.PayPalPlusWall', ['content'=>$content]);
    }


    /**
     * @param $orderId
     *
     * Redirect to PayPal Express Checkout
     * @return string
     */
    public function orderCheckout($orderId)
    {
        $orderContract = $this->orderContract;

        /** @var \Plenty\Modules\Authorization\Services\AuthHelper $authHelper */
        $authHelper = pluginApp(AuthHelper::class);

        //guarded
        $order = $authHelper->processUnguarded(
            function () use ($orderContract, $orderId) {
                //unguarded
                return $orderContract->findOrderById($orderId);
            }
        );

        /** @var PayPalExpressService $payPalExpressService */
        $payPalExpressService = pluginApp(\PayPal\Services\PaymentService::class);
        $redirectURL = $payPalExpressService->preparePayPalPaymentByOrder($order);
        //header('Location: '.$redirectURL);
        return $redirectURL;
    }

    /**
     * @param $orderId
     *
     * Get the PayPal PLUS payment wall
     * @return string
     */
    public function orderPaymentWall($orderId)
    {
        $orderContract = $this->orderContract;

        /** @var \Plenty\Modules\Authorization\Services\AuthHelper $authHelper */
        $authHelper = pluginApp(AuthHelper::class);

        //guarded
        $order = $authHelper->processUnguarded(
            function () use ($orderContract, $orderId) {
                //unguarded
                return $orderContract->findOrderById($orderId);
            }
        );

        /** @var PayPalPlusService $payPalPlusService */
        $payPalPlusService = pluginApp(\PayPal\Services\PayPalPlusService::class);
        $redirectURL = $payPalPlusService->getPaymentWallContentByOrder($order);
        //header('Location: '.$redirectURL);
        return $redirectURL;
    }

    /**
     * Change the payment method in the basket when user select a none paypal plus method
     *
     * @param Checkout $checkout
     * @param Request $request
     */
    public function changePaymentMethod(Checkout $checkout, Request $request)
    {
        $paymentMethod = $request->get('paymentMethod');
        if(isset($paymentMethod) && $paymentMethod > 0)
        {
            $checkout->setPaymentMethodId($paymentMethod);
        }
    }

    /**
     * @param PayPalInstallmentService $payPalInstallmentService
     * @param Twig $twig
     * @param $amount
     *
     * @return string
     */
    public function calculateFinancingOptions(PayPalInstallmentService $payPalInstallmentService, Twig $twig, $amount)
    {
        return $payPalInstallmentService->calculateFinancingCosts($twig, $amount);
    }
}
