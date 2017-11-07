<?php
namespace PayPal\Procedures;

use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Order\Models\OrderType;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Payment\Models\PaymentProperty;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;

use PayPal\Services\PaymentService;
use PayPal\Helper\PaymentHelper;

/**
 * Class RefundEventProcedure
 * @package PayPal\Procedures
 */
class RefundEventProcedure
{
    /**
     * @param EventProceduresTriggered      $eventTriggered
     * @param PaymentService                $paymentService
     * @param PaymentRepositoryContract     $paymentRepository
     * @param PaymentHelper                 $paymentHelper
     * @throws \Exception
     */
    public function run(EventProceduresTriggered    $eventTriggered,
                        PaymentService              $paymentService,
                        PaymentRepositoryContract   $paymentRepository,
                        PaymentHelper               $paymentHelper)
    {
        /** @var Order $order */
        $order = $eventTriggered->getOrder();

        $orderId = $this->getOrderId($order);

        if(empty($orderId))
        {
            throw new \Exception('Refund PayPal payment failed! The given order is invalid!');
        }

        /** @var Payment[] $payment */
        $payments = $paymentRepository->getPaymentsByOrderId($orderId);

        /** @var Payment $payment */
        foreach($payments as $payment)
        {
            if($paymentHelper->isPaymentOfTypePP($payment->mopId))
            {
                $saleId = $paymentHelper->getPaymentPropertyValue($payment, PaymentProperty::TYPE_TRANSACTION_ID);

                if(strlen($saleId))
                {
                    if($this->processRefund($saleId, $payment, $order, $paymentService, $paymentHelper, $paymentRepository))
                        break;
                }

                unset($saleId);
            }
        }
    }

    /**
     * Process refund
     *
     * @param $saleId
     * @param Payment $payment
     * @param Order $order
     * @param PaymentService $paymentService
     * @param PaymentHelper $paymentHelper
     * @param PaymentRepositoryContract $paymentRepository
     *
     * @return bool
     * @throws \Exception
     */
    private function processRefund($saleId, Payment $payment, Order $order, PaymentService $paymentService,
                                   PaymentHelper $paymentHelper, PaymentRepositoryContract $paymentRepository)
    {
        // refund the payment
        $refundResult = $paymentService->refundPayment($saleId);

        $refunded = false;

        if($refundResult['error'])
        {
            throw new \Exception($refundResult['error_msg']);
        }

        if(!isset($refundResult['state']) || $refundResult['state'] == 'failed')
        {
            //TODO log the reason_code
        }
        else
        {
            $paymentData = [];
            $paymentData['parentId']    = $payment->id;
            $paymentData['type']        = 'debit';
            $paymentData['mopId']       = $order->methodOfPaymentId;

            // if the refund is pending, set the payment unaccountable
            if($refundResult['state'] == 'pending')
            {
                $paymentData['unaccountable'] = 1;  //1 true 0 false
            }

            $saleDetails = $paymentService->getSaleDetails($saleId);

            $refunded = true;
            // create the new debit payment
            /** @var Payment $debitPayment */
            $debitPayment = $paymentHelper->createPlentyPaymentFromRefund($refundResult, $paymentData);

            $payment->status = $paymentHelper->mapStatus($saleDetails['state']);

            // update the refunded payment
            $paymentRepository->updatePayment($payment);


            if(isset($debitPayment) && $debitPayment instanceof Payment && $order->typeId == OrderType::TYPE_CREDIT_NOTE)
            {
                // assign the new debit payment to the order
                $paymentHelper->assignPlentyPaymentToPlentyOrder($debitPayment, $order->id);
            }
        }

        return $refunded;
    }

    /**
     * Get order ID and
     *
     * @param Order $order
     *
     * @return int
     */
    private function getOrderId(Order $order)
    {
        $orderId = 0;

        // only sales orders and credit notes are allowed order types to refund
        switch($order->typeId)
        {
            case OrderType::TYPE_SALES_ORDER:
                $orderId = $order->id;
                break;
            case OrderType::TYPE_CREDIT_NOTE:
                $originOrders = $order->originOrders;
                if(!$originOrders->isEmpty() && $originOrders->count() > 0)
                {
                    $originOrder = $originOrders->first();

                    if($originOrder instanceof Order)
                    {
                        if($originOrder->typeId == OrderType::TYPE_SALES_ORDER)
                        {
                            $orderId = $originOrder->id;
                        }
                        else
                        {
                            $originOriginOrders = $originOrder->originOrders;
                            if(is_array($originOriginOrders) && count($originOriginOrders) > 0)
                            {
                                $originOriginOrder = $originOriginOrders->first();
                                if($originOriginOrder instanceof Order)
                                {
                                    $orderId = $originOriginOrder->id;
                                }
                            }
                        }
                    }
                }
                break;
        }

        return $orderId;
    }

}