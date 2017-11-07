<?php

namespace PayPal\Models;

use Plenty\Modules\Account\Address\Contracts\AddressRepositoryContract;
use Plenty\Modules\Basket\Models\Basket;
use Plenty\Modules\Item\Item\Contracts\ItemRepositoryContract;
use Plenty\Modules\Item\Item\Models\Item;
use Plenty\Modules\Frontend\PaymentMethod\Contracts\FrontendPaymentMethodRepositoryContract;
use Plenty\Modules\Order\Shipping\Contracts\ParcelServicePresetRepositoryContract;
use Plenty\Modules\Account\Address\Models\Address;
use IO\Extensions\Filters\URLFilter;
use IO\Services\ItemService;

class LocalizedBasket extends ModelWrapper
{
    /**
     * @var Basket
     */
    public $basket = null;

    /**
     * @var Address
     */
    public $billingAddress = null;

    /**
     * @var Address
     */
    public $deliveryAddress = null;

    public $shippingProvider = "";
    public $shippingProfileName = "";
    public $paymentMethodName = "";
    public $paymentMethodIcon = "";

    public $itemURLs = [];
    public $itemImages = [];
    public $itemNames = [];
    public $isReturnable = false;

    /**
     * @param Basket $basket
     * @param array ...$data
     * @return LocalizedBasket
     */
    public static function wrap( $basket, ...$data ):LocalizedBasket
    {
        /** @var Basket $basket */
        if( $basket == null )
        {
            return null;
        }

        list( $lang ) = $data;

        $instance = pluginApp( self::class );
        $instance->basket = $basket;

        $parcelServicePresetRepository = pluginApp(ParcelServicePresetRepositoryContract::class);
        if($parcelServicePresetRepository instanceof ParcelServicePresetRepositoryContract)
        {
            $shippingProfile = $parcelServicePresetRepository->getPresetById( $basket->shippingProfileId );
            foreach( $shippingProfile->parcelServicePresetNames as $name )
            {
                if( $name->lang === $lang )
                {
                    $instance->shippingProfileName = $name->name;
                    break;
                }
            }

            foreach( $shippingProfile->parcelServiceNames as $name )
            {
                if( $name->lang === $lang )
                {
                    $instance->shippingProvider = $name->name;
                    break;
                }
            }
        }

        $frontentPaymentRepository = pluginApp( FrontendPaymentMethodRepositoryContract::class );
        
        try
        {
            $instance->paymentMethodName = $frontentPaymentRepository->getPaymentMethodNameById( $basket->methodOfPaymentId, $lang );
            $instance->paymentMethodIcon = $frontentPaymentRepository->getPaymentMethodIconById( $basket->methodOfPaymentId, $lang );
        }
        catch(\Exception $e)
        {}


        $urlFilter = pluginApp(URLFilter::class);
        $itemService = pluginApp(ItemService::class);

        foreach( $basket->basketItems as $key => $basketItem)
        {
            if( $basketItem->variationId !== 0)
            {
                $variationId = $basketItem->variationId;
                $itemUrl = '';
                if((INT)$variationId > 0)
                {
                    $itemUrl = $urlFilter->buildVariationURL($variationId, true);
                }

                $instance->itemURLs[$basketItem->variationId] = $itemUrl;
                /** @var ItemService $itemService */
                $itemImage = $itemService->getVariationImage($variationId);
                $instance->itemImages[$variationId] = $itemImage;

                /** @var ItemRepositoryContract $itemContract */
                $itemContract = pluginApp(ItemRepositoryContract::class);

                /** @var Item $item */
                $item = $itemContract->show($basketItem->itemId);
                $instance->itemNames[$variationId] = $item->texts->first()->name;
            }
        }

        /** @var AddressRepositoryContract $addressContract */
        $addressContract = pluginApp(AddressRepositoryContract::class);
        try {
            $instance->billingAddress = $addressContract->findAddressById($basket->customerInvoiceAddressId);
            $instance->deliveryAddress = $addressContract->findAddressById($basket->customerShippingAddressId);
        } catch (\Exception $e) {

        }
        return $instance;
    }

    /**
     * @return array
     */
    public function toArray():array
    {
        $data = [
            "basket"                => $this->basket->toArray(),
            "shippingProvider"      => $this->shippingProvider,
            "shippingProfileName"   => $this->shippingProfileName,
            "paymentMethodName"     => $this->paymentMethodName,
            "paymentMethodIcon"     => $this->paymentMethodIcon,
            "itemURLs"              => $this->itemURLs,
            "itemImages"            => $this->itemImages,
            "itemNames"             => $this->itemNames,
            "isReturnable"          => $this->isReturnable
        ];

        $data["basket"]["billingAddress"] = $this->billingAddress->toArray();
        $data["basket"]["deliveryAddress"] = $this->deliveryAddress->toArray();

        return $data;
    }
}
