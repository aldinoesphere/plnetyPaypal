<?php //strict

namespace PayPal\Helper;

use Plenty\Modules\Helper\Services\WebstoreHelper;
use Plenty\Modules\Payment\Models\PaymentProperty;
use Plenty\Plugin\ConfigRepository;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Plenty\Modules\Payment\Contracts\PaymentOrderRelationRepositoryContract;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Order\Models\Order;

use PayPal\Services\SessionStorageService;
use PayPal\Services\PaymentService;
use Plenty\Modules\Frontend\Contracts\Checkout;

/**
 * Class PaymentHelper
 * @package PayPal\Helper
 */
class PaymentHelper
{
    const PAYMENTKEY_PAYPAL = 'PAYPAL';
    const PAYMENTKEY_PAYPALEXPRESS = 'PAYPALEXPRESS';
    const PAYMENTKEY_PAYPALPLUS = 'PAYPALPLUS';
    const PAYMENTKEY_PAYPALINSTALLMENT = 'PAYPALINSTALLMENT';

    const MODE_PAYPAL = 'paypal';
    const MODE_PAYPALEXPRESS = 'paypalexpress';
    const MODE_PAYPAL_PLUS = 'plus';
    const MODE_PAYPAL_INSTALLMENT = 'installment';
    const MODE_PAYPAL_NOTIFICATION = 'notification';

    /**
     * @var PaymentMethodRepositoryContract
     */
    private $paymentMethodRepository;

    /**
     * @var ConfigRepository
     */
    private $config;

    /**
     * @var SessionStorageService
     */
    private $sessionService;

    /**
     * @var PaymentOrderRelationRepositoryContract
     */
    private $paymentOrderRelationRepo;

    /**
     * @var PaymentRepositoryContract
     */
    private $paymentRepository;

    /**
     * @var OrderRepositoryContract
     */
    private $orderRepo;

    /**
     * @var PaymentService
     */
    private $paymentService;

    /**
     * @var array
     */
    private $statusMap = array();

    /** @var  Checkout */
    private $checkout;

    /**
     * PaymentHelper constructor.
     *
     * @param PaymentMethodRepositoryContract $paymentMethodRepository
     * @param PaymentRepositoryContract $paymentRepo
     * @param PaymentOrderRelationRepositoryContract $paymentOrderRelationRepo
     * @param ConfigRepository $config
     * @param SessionStorageService $sessionService
     * @param OrderRepositoryContract $orderRepo
     * @param Checkout $checkout
     */
    public function __construct(PaymentMethodRepositoryContract         $paymentMethodRepository,
                                PaymentRepositoryContract               $paymentRepo,
                                PaymentOrderRelationRepositoryContract  $paymentOrderRelationRepo,
                                ConfigRepository                        $config,
                                SessionStorageService                   $sessionService,
                                OrderRepositoryContract                 $orderRepo,
                                Checkout                                $checkout)
    {
        $this->config                   = $config;
        $this->sessionService           = $sessionService;
        $this->paymentMethodRepository  = $paymentMethodRepository;
        $this->paymentOrderRelationRepo = $paymentOrderRelationRepo;
        $this->paymentRepository        = $paymentRepo;
        $this->orderRepo                = $orderRepo;
        $this->statusMap                = array();
        $this->checkout                 = $checkout;
    }

    /**
     * Find MOP ID of given payment key
     *
     * @param string $paymentKey
     *
     * @return int|string
     */
    public function getPayPalMopIdByPaymentKey(string $paymentKey)
    {
        if(strlen($paymentKey))
        {
            // List all payment methods for the given plugin
            $paymentMethods = $this->paymentMethodRepository->allForPlugin('plentyPayPal');

            if( !is_null($paymentMethods) )
            {
                foreach($paymentMethods as $paymentMethod)
                {
                    if($paymentMethod->paymentKey == $paymentKey)
                    {
                        return $paymentMethod->id;
                    }
                }
            }
        }

        return 'no_paymentmethod_found';
    }

    /**
     * Get the REST return URLs for the given mode
     *
     * @param string $mode
     * @return array(success => $url, cancel => $url)
     */
    public function getRestReturnUrls(string $mode)
    {
        $domain = $this->getDomain();

        $urls = [];

        switch($mode)
        {
            case self::MODE_PAYPAL_PLUS:
            case self::MODE_PAYPAL:
                $urls['success'] = $domain.'/payment/payPal/checkoutSuccess/'.$mode;
                $urls['cancel'] = $domain.'/payment/payPal/checkoutCancel/'.$mode;
                break;
            case self::MODE_PAYPAL_INSTALLMENT:
                $urls['success'] = $domain.'/payment/payPalInstallment/prepareInstallment';
                $urls['cancel'] = $domain.'/payment/payPal/checkoutCancel/'.$mode;
                break;
            case self::MODE_PAYPALEXPRESS:
                $urls['success'] = $domain.'/payment/payPal/expressCheckoutReview';
                $urls['cancel'] = $domain.'/payment/payPal/expressCheckoutCancel';
                break;
            case self::MODE_PAYPAL_NOTIFICATION:
                $urls[self::MODE_PAYPAL_NOTIFICATION] = $domain.'/payment/payPal/notification';
                break;
        }

        return $urls;
    }

    /**
     * Get the REST return URLs for the given mode
     *
     * @param int $orderId
     * @return array(success => $url, cancel => $url)
     */
    public function getRestOrderReturnUrls(int $orderId)
    {
        $domain = $this->getDomain();

        $urls = [];
        $urls['success'] = $domain.'/payment/payPal/payOrderSuccess/'.$orderId;
        $urls['cancel'] = $domain.'/payment/payPal/payOrderCancel/'.$orderId;

        return $urls;
    }

    /**
     * @return string
     */
    public function getDomain()
    {
        /** @var WebstoreHelper $webstoreHelper */
        $webstoreHelper = pluginApp(WebstoreHelper::class);

        /** @var \Plenty\Modules\System\Models\WebstoreConfiguration $webstoreConfig */
        $webstoreConfig = $webstoreHelper->getCurrentWebstoreConfiguration();

        $domain = $webstoreConfig->domainSsl;
        if($domain == 'http://dbmaster.plenty-showcase.de' || $domain == 'http://dbmaster-beta7.plentymarkets.eu' || $domain == 'http://dbmaster-stable7.plentymarkets.eu')
        {
            $domain = 'http://master.plentymarkets.com';
        }

        return $domain;
    }

    /**
     * Create a payment in plentymarkets from the PayPal execution response data
     *
     * @param array $paypalPaymentData
     * @param array $paymentData
     * @return Payment
     */
    public function createPlentyPayment(array $paypalPaymentData, array $paymentData = [])
    {
        /** @var Payment $payment */
        $payment                = $this->getPaymentObjectByPPData($paypalPaymentData, $paymentData);
        /** @var PaymentProperty[] $paymentProperties */
        $paymentProperties      = $this->fillPaymentPropertiesFromPPPayment($paypalPaymentData);
        /** @var PaymentProperty $instructionsData */
        $instructionsData       = $this->getPaymentPropertyByPPPaymentInstructionData($paypalPaymentData);
        /** @var PaymentProperty $installmentData */
        $installmentData        = $this->getPaymentPropertyByPPInstallment($paypalPaymentData);

        if(!empty($instructionsData) && $instructionsData instanceof PaymentProperty)
            $paymentProperties[] = $instructionsData;

        if(!empty($installmentData) && $installmentData instanceof PaymentProperty)
            $paymentProperties[] = $installmentData;

        $payment->properties     = $paymentProperties;
        $payment->regenerateHash = true;

        $payment = $this->paymentRepository->createPayment($payment);

        return $payment;
    }

    /**
     * Create refund in plentymarkets base on PayPal refund data
     *
     * @param array $paypalPaymentData
     * @param array $paymentData
     *
     * @return Payment
     */
    public function createPlentyPaymentFromRefund(array $paypalPaymentData, array $paymentData = [])
    {
        /** @var Payment $payment */
        $payment = $this->getPaymentObjectByPPData($paypalPaymentData, $paymentData, true);
        /** @var PaymentProperty[] $paymentProperties */
        $paymentProperties      = $this->fillPaymentPropertiesFromPPPayment($paypalPaymentData, true);

        $payment->properties     = $paymentProperties;
        $payment->regenerateHash = true;

        $payment = $this->paymentRepository->createPayment($payment);

        return $payment;
    }

    /**
     * Checks if payment is a PayPal payment
     *
     * @param int $mopId
     *
     * @return bool
     */
    public function isPaymentOfTypePP(int $mopId)
    {
        if(     $mopId == $this->getPayPalMopIdByPaymentKey(PaymentHelper::PAYMENTKEY_PAYPAL)
            OR  $mopId == $this->getPayPalMopIdByPaymentKey(PaymentHelper::PAYMENTKEY_PAYPALEXPRESS)
            OR  $mopId == $this->getPayPalMopIdByPaymentKey(PaymentHelper::PAYMENTKEY_PAYPALPLUS)
            OR  $mopId == $this->getPayPalMopIdByPaymentKey(PaymentHelper::PAYMENTKEY_PAYPALINSTALLMENT)  )
        {
            return true;
        }

        return false;
    }

    /**
     * Update parent payment
     *
     * @param int       $saleId
     * @param string    $state
     */
    public function updatePayment(int $saleId, string $state)
    {
        /** @var array $payments */
        $payments = $this->paymentRepository->getPaymentsByPropertyTypeAndValue(PaymentProperty::TYPE_TRANSACTION_ID, $saleId);

        // update the payment
        if(!empty($payments))
        {
            $state = $this->mapStatus((STRING)$state);

            /** @var Payment $payment */
            foreach($payments as $payment)
            {
                if($payment->status != $state)
                {
                    $payment->status = $state;
                    $payment->updateOrderPaymentStatus = true;

                    if($state == Payment::STATUS_APPROVED || $state == Payment::STATUS_CAPTURED)
                    {
                        $payment->unaccountable = 0;
                    }

                    $payment->regenerateHash = true;
                    $this->paymentRepository->updatePayment($payment);
                }
            }
        }
        // create a new payment
        else
        {
            /** @var \PayPal\Services\PaymentService $paymentService */
            $paymentService = pluginApp(\PayPal\Services\PaymentService::class);

            $sale = $paymentService->getSaleDetails($saleId);

            if(empty($sale['error']))
            {
                $this->createPlentyPayment($sale);
            }
        }
    }

    /**
     * Assign the payment to an order in plentymarkets
     *
     * @param Payment   $payment
     * @param int       $orderId
     */
    public function assignPlentyPaymentToPlentyOrder(Payment $payment, int $orderId)
    {
        // Get the order by the given order ID
        $order = $this->orderRepo->findOrderById($orderId);

        // Check whether the order truly exists in plentymarkets
        if(!is_null($order) && $order instanceof Order)
        {
            // Assign the given payment to the given order
            $this->paymentOrderRelationRepo->createOrderRelation($payment, $order);
        }
    }



    // TODO: assignPlentyPaymentToPlentyContact



    /**
     * Map the PayPal payment status to the plentymarkets payment status
     *
     * @param string $status
     * @return int
     *
     */
    public function mapStatus(string $status)
    {
        if(!is_array($this->statusMap) || count($this->statusMap) <= 0)
        {
            $statusConstants = $this->paymentRepository->getStatusConstants();

            if(!is_null($statusConstants) && is_array($statusConstants))
            {
                $this->statusMap['created']               = $statusConstants['captured'];
                $this->statusMap['approved']              = $statusConstants['approved'];
                $this->statusMap['failed']                = $statusConstants['refused'];
                $this->statusMap['partially_completed']   = $statusConstants['partially_captured'];
                $this->statusMap['completed']             = $statusConstants['captured'];
                $this->statusMap['in_progress']           = $statusConstants['awaiting_approval'];
                $this->statusMap['pending']               = $statusConstants['awaiting_approval'];
                $this->statusMap['refunded']              = $statusConstants['refunded'];
                $this->statusMap['denied']                = $statusConstants['refused'];
            }
        }

        return strlen($status)?(int)$this->statusMap[$status]:2;
    }

    /**
     * @param Payment   $payment
     * @param int       $propertyType
     *
     * @return null|string
     */
    public function getPaymentPropertyValue(Payment $payment, int $propertyType)
    {
        /** @var PaymentProperty[] $properties */
        $properties = $payment->properties;

        if((count($properties) > 0) || (is_array($properties ) && count($properties ) > 0))
        {
            /** @var PaymentProperty $property */
            foreach($properties as $property)
            {
                if($property instanceof PaymentProperty)
                {
                    if($property->typeId == $propertyType)
                    {
                        return $property->value;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Creates a new Payment object and fills it with given data
     *
     * @param array $paypalPaymentData
     * @param array $paymentData
     * @param bool  $isRefund
     *
     * @return Payment
     */
    private function getPaymentObjectByPPData( array $paypalPaymentData, array $paymentData = [], bool $isRefund = false)
    {
        /** @var Payment $payment */
        $payment = pluginApp( Payment::class );

        $payment->mopId             = !empty($paymentData['mopId']) ? $paymentData['mopId'] : (int)$this->getPayPalMopIdByPaymentKey(PaymentHelper::PAYMENTKEY_PAYPAL);
        $payment->transactionType   = Payment::TRANSACTION_TYPE_BOOKED_POSTING;
        $payment->status            = $this->mapStatus((STRING)$paypalPaymentData['state']);
        $payment->currency          = $this->getPayPalPaymentFieldValue('currency',     $paypalPaymentData, $isRefund);
        $payment->amount            = $this->getPayPalPaymentFieldValue('amount',       $paypalPaymentData, $isRefund);
        $payment->receivedAt        = $this->getPayPalPaymentFieldValue('receivedAt',   $paypalPaymentData, $isRefund);

        if(!empty($paymentData['type']))
        {
            $payment->type = $paymentData['type'];
        }

        if(!empty($paymentData['parentId']))
        {
            $payment->parentId = $paymentData['parentId'];
        }

        if(!empty($paymentData['unaccountable']))
        {
            $payment->unaccountable = $paymentData['unaccountable'];
        }

        return $payment;
    }

    /**
     * Creates properties for payment, based on PayPal Response payment data
     *
     * @param array $paypalPaymentData
     * @param bool $isRefund
     *
     * @return array
     */
    private function fillPaymentPropertiesFromPPPayment( array $paypalPaymentData, bool $isRefund = false)
    {
        $paymentProperties = array();

        $propertyTypesToStore = array(      PaymentProperty::TYPE_TRANSACTION_ID                => ['transactionId']                    ,
                                            PaymentProperty::TYPE_REFERENCE_ID                  => ['referenceId']                      ,
                                            PaymentProperty::TYPE_BOOKING_TEXT                  => ['transactionId', 'invoiceNumber']   ,
                                            PaymentProperty::TYPE_TRANSACTION_PASSWORD          => ['transactionPassword']              ,
                                            PaymentProperty::TYPE_NAME_OF_SENDER                => ['senderFirstName','senderLastName'] ,
                                            PaymentProperty::TYPE_EMAIL_OF_SENDER               => ['senderEmail']                      ,
                                            PaymentProperty::TYPE_ACCOUNT_OF_RECEIVER           => ['accountOfReceiver']                ,
                                            PaymentProperty::TYPE_ORIGIN                        => ''                                   ,
                                            PaymentProperty::TYPE_SHIPPING_ADDRESS_ID           => ''                                   ,
                                            PaymentProperty::TYPE_ITEM_BUYER                    => ['itemBuyer']                        ,
                                            PaymentProperty::TYPE_ITEM_TRANSACTION_ID           => ['itemTransactionId']                ,
                                            PaymentProperty::TYPE_EXTERNAL_TRANSACTION_TYPE     => ['externalTransactionType']          ,
                                            PaymentProperty::TYPE_TRANSACTION_FEE               => ['transactionFee']                    );

        foreach($propertyTypesToStore as $typeId => $fields)
        {
            switch($typeId)
            {
                case PaymentProperty::TYPE_NAME_OF_SENDER:
                    $firstName = $this->getPayPalPaymentFieldValue($fields[0],$paypalPaymentData, $isRefund);
                    $lastName  = $this->getPayPalPaymentFieldValue($fields[1],$paypalPaymentData, $isRefund);
                    if(!empty($firstName) && !empty($lastName))
                        $paymentProperties[] = $this->getPaymentProperty($typeId, $firstName . ' ' . $lastName);
                    break;

                case PaymentProperty::TYPE_BOOKING_TEXT:
                    $transId    = $this->getPayPalPaymentFieldValue($fields[0], $paypalPaymentData, $isRefund);
                    $invoiceId  = $this->getPayPalPaymentFieldValue($fields[1], $paypalPaymentData, $isRefund);
                    $value = '';
                    if(!empty($transId))    $value = $value . 'TransactionID: ' . $transId;
                    if(!empty($invoiceId))  $value = $value . ' - ExOID: ' . $invoiceId;
                    if(!empty($value))      $paymentProperties[] = $this->getPaymentProperty($typeId, $value);
                    break;

                case PaymentProperty::TYPE_ORIGIN:
                    $paymentProperties[] = $this->getPaymentProperty(PaymentProperty::TYPE_ORIGIN, (string)Payment::ORIGIN_PLUGIN); break;

                case PaymentProperty::TYPE_SHIPPING_ADDRESS_ID:
                    if(!empty($this->checkout->getCustomerShippingAddressId()))
                        $paymentProperties[] = $this->getPaymentProperty(PaymentProperty::TYPE_SHIPPING_ADDRESS_ID,
                            (string)$this->checkout->getCustomerShippingAddressId() );
                    break;

                default:
                    if(!empty($value = $this->getPayPalPaymentFieldValue($fields[0], $paypalPaymentData, $isRefund)))
                        $paymentProperties[] = $this->getPaymentProperty($typeId, $value);
                    break;
            }
        }

        return $paymentProperties;
    }

    /**
     * Get PaymentProperty by PayPal installment data
     *
     * @param array $paypalPaymentData
     *
     * @return null|PaymentProperty
     */
    private function getPaymentPropertyByPPInstallment(array $paypalPaymentData)
    {
        $paymentText = [];
        if(!empty($value = $this->getPayPalPaymentFieldValue('installmentFinancingCosts'               , $paypalPaymentData)))
            $paymentText['installmentFinancingCosts']              = $value;
        if(!empty($value = $this->getPayPalPaymentFieldValue('installmentTotalCostsIncludeFinancing'   , $paypalPaymentData)))
            $paymentText['installmentTotalCostsIncludeFinancing']  = $value;
        if(!empty($value = $this->getPayPalPaymentFieldValue('installmentCurrency'                     , $paypalPaymentData)))
            $paymentText['installmentCurrency']                    = $value;

        if(count($paymentText) != 0)
        {

            /** @var PaymentProperty $property */
            $property = $this->getPaymentProperty(PaymentProperty::TYPE_PAYMENT_TEXT, json_encode($paymentText));

            return $property;
        }

        return null;
    }

    /**
     * Get PaymentProperty by PayPal payment instruction data
     *
     * @param array $paypalPaymentData
     *
     * @return null|PaymentProperty
     */
    private function getPaymentPropertyByPPPaymentInstructionData(array $paypalPaymentData)
    {

        $paymentText = [];
        if(!empty($value = $this->getPayPalPaymentFieldValue('bankName'        , $paypalPaymentData))) $paymentText['bankName']           = $value;
        if(!empty($value = $this->getPayPalPaymentFieldValue('accountHolder'   , $paypalPaymentData))) $paymentText['accountHolder']      = $value;
        if(!empty($value = $this->getPayPalPaymentFieldValue('iban'            , $paypalPaymentData))) $paymentText['iban']               = $value;
        if(!empty($value = $this->getPayPalPaymentFieldValue('bic'             , $paypalPaymentData))) $paymentText['bic']                = $value;
        if(!empty($value = $this->getPayPalPaymentFieldValue('referenceNumber' , $paypalPaymentData))) $paymentText['referenceNumber']    = $value;
        if(!empty($value = $this->getPayPalPaymentFieldValue('paymentDue'      , $paypalPaymentData))) $paymentText['paymentDue']         = $value;

        if(count($paymentText) != 0 )
        {
            /** @var PaymentProperty $property */
            $property = $this->getPaymentProperty(PaymentProperty::TYPE_PAYMENT_TEXT, json_encode($paymentText));

            return $property;
        }

        return null;
    }

    /**
     * Returns a PaymentProperty with the given params
     *
     * @param int       $typeId
     * @param string    $value
     *
     * @return PaymentProperty
     */
    private function getPaymentProperty(int $typeId, string $value)
    {
        /** @var PaymentProperty $paymentProperty */
        $paymentProperty = pluginApp( \Plenty\Modules\Payment\Models\PaymentProperty::class );

        $paymentProperty->typeId = $typeId;
        $paymentProperty->value = $value;

        return $paymentProperty;
    }

    /**
     * Given a PayPal object, get values by key.
     *
     * @param string    $field
     * @param array     $paypalPaymentData
     * @param bool      $isRefund
     *
     * @return null|string
     */
    private function getPayPalPaymentFieldValue(string $field, array $paypalPaymentData, bool $isRefund = false)
    {
        try
        {
            switch ($field)
            {
                case 'transactionId':               $value = $isRefund ? $paypalPaymentData['id']                   : $paypalPaymentData['transactions'][0]['related_resources'][0]['sale']['id'];  break;
                case 'referenceId':                 $value = $isRefund ? $paypalPaymentData['sale_id']              : $paypalPaymentData['id'];                                                     break;
                case 'transactionPassword':         $value = $isRefund ? null : $paypalPaymentData['transactions'][0]['related_resources'][0]['sale']['payment_mode'];                              break;
                case 'senderFirstName':             $value = $isRefund ? null : $paypalPaymentData['payer']['payer_info']['first_name'];                                                            break;
                case 'senderLastName':              $value = $isRefund ? null : $paypalPaymentData['payer']['payer_info']['last_name'];                                                             break;
                case 'senderEmail':                 $value = $isRefund ? null : $paypalPaymentData['payer']['payer_info']['email'];                                                                 break;
                case 'accountOfReceiver':           $value = $isRefund ? null : $paypalPaymentData['transactions'][0]['payee']['merchant_id'];                                                      break;
                case 'itemBuyer':                   $value = $isRefund ? null : $paypalPaymentData['payer']['payer_info']['payer_id'];                                                              break;
                case 'itemTransactionId':           $value = $isRefund ? null : $paypalPaymentData['cart'];                                                                                         break;
                case 'externalTransactionType':     $value = $isRefund ? 'refund' : $paypalPaymentData['intent'];                                                                                   break;
                case 'currency':                    $value = $isRefund ? $paypalPaymentData['amount']['currency']   : $paypalPaymentData['transactions'][0]['amount']['currency'];                  break;
                case 'amount':                      $value = $isRefund ? $paypalPaymentData['amount']['total']      : $paypalPaymentData['transactions'][0]['amount']['total'];                     break;
                case 'receivedAt':                  $value = $isRefund ? $paypalPaymentData['create_time']          : $paypalPaymentData['create_time'];                                            break;

                case 'installmentFinancingCosts':   $value = $isRefund ? null : $paypalPaymentData['offeredFinancingCosts']['total_interest']['value'];                                             break;
                case 'installmentTotalCostsIncludeFinancing':
                                                    $value = $isRefund ? null : $paypalPaymentData['offeredFinancingCosts']['total_cost']['value'];                                                 break;
                case 'installmentCurrency':         $value = $isRefund ? null : $paypalPaymentData['offeredFinancingCosts']['total_cost']['currency'];                                              break;

                case 'instructionBankName':         $value = $isRefund ? null : $paypalPaymentData['payment_instruction']['recipient_banking_instruction']['bank_name'];                            break;
                case 'instructionAccountHolder':    $value = $isRefund ? null : $paypalPaymentData['payment_instruction']['recipient_banking_instruction']['account_holder_name'];                  break;
                case 'instructionIban':             $value = $isRefund ? null : $paypalPaymentData['payment_instruction']['recipient_banking_instruction']['international_bank_account_number'];    break;
                case 'instructionBic':              $value = $isRefund ? null : $paypalPaymentData['payment_instruction']['recipient_banking_instruction']['bank_identifier_code'];                 break;
                case 'instructionReferenceNumber':  $value = $isRefund ? null : $paypalPaymentData['payment_instruction']['reference_number'];                                                      break;
                case 'instructionPaymentDue':       $value = $isRefund ? null : $paypalPaymentData['payment_instruction']['payment_due_date'];                                                      break;

                case 'invoiceNumber':               $value = $isRefund ? null : $paypalPaymentData['transactions'][0]['invoice_number'];                                                            break;
                case 'transactionFee':              $value = $isRefund ? null : $paypalPaymentData['transactions'][0]['related_resources'][0]['sale']['transaction_fee']['value'];                  break;

                default:
                    return null;
            }
        }
        catch(\Exception $e)
        {
            return null;
        }

        return empty($value) ? null : $value;
    }


}
