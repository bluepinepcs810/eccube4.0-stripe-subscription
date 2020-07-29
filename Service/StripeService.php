<?php
/*
* Plugin Name : StripeRec
*
* Copyright (C) 2020 Subspire. All Rights Reserved.
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Plugin\StripeRec\Service;
if( \file_exists(dirname(__FILE__).'/../../StripePaymentGateway/vendor/stripe/stripe-php/init.php')) {
    include_once(dirname(__FILE__).'/../../StripePaymentGateway/vendor/stripe/stripe-php/init.php');
}
use Stripe\Product as StProduct;
use Stripe\Stripe;
use Stripe\Price as StPrice;
use Stripe\Subscription;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Plugin\StripeRec\Repository\StripeRecOrderRepository;
use Eccube\Entity\BaseInfo;
use Eccube\Entity\Product;
use Eccube\Entity\ProductClass;
use Eccube\Entity\MailTemplate;
use Plugin\StripeRec\Entity\StripeRecOrder;
use Plugin\StripeRec\Service\Admin\ConfigService;
use Plugin\StripePaymentGateway\Repository\StripeConfigRepository;
use Eccube\Event\EventArgs;

class StripeService{
    
    protected $container;
    
    public function __construct(
        ContainerInterface $container
        // StripeRecOrderRepository $rec_order_repo
        ){
        $this->container = $container;
        $this->em = $this->container->get('doctrine.orm.entity_manager');       
        
        $stripeConfigRepository = $this->container->get(StripeConfigRepository::class);        
        $StripeConfig = $stripeConfigRepository->getConfigByOrder(null);
        Stripe::setApiKey($StripeConfig->secret_key);
    }

    public function registerProduct(Product $product){
        $stripe_prod = StProduct::create([
            'name'  =>  $product->getName(),
            'description' => $product->getDescriptionDetail()
        ]);
        if($stripe_prod && $stripe_prod->active){
            $product->setStripeProdId($stripe_prod->id);
            $this->em->persist($product);
            $this->em->flush();
            return true;
        }
        return false;
    }
    public function registerPrice(ProductClass $prod_class, $interval = "month"){
        $unit_amount = $prod_class->getPrice02IncTax();
        if(empty($unit_amount)){
            return false;
        }
        $currency = 'jpy';        
        $prod = $prod_class->getProduct();
        if (!$prod->isStripeProduct()){
            return false;
        }
        $stripe_prod_id = $prod->getStripeProdId();

        $stripe_price = StPrice::create([
            'unit_amount'   =>  $unit_amount,
            'currency'      =>  $currency,
            'recurring'     =>  [ 'interval'    =>   $interval],
            'product'       =>  $stripe_prod_id
        ]);
        if($stripe_price && $stripe_price->active){
            $id = $stripe_price->id;
            $prod_class->setStripePriceId($id);        
            return $prod_class;
        }
        return false;   
    }
    public function updatePrice(ProductClass $prod_class){
        if(!$prod_class->isRegistered()){
            return false;
        }
        $prod = $prod_class->getProduct();
        if (!$prod->isStripeProduct()){
            return false;
        }
        $stripe_prod_id = $prod->getStripeProdId();

        $price_id = $prod_class->getStripePriceId();
        $price = StPrice::retrieve($price_id, []);
        if(empty($price) || empty($price->active)){
            return false;
        }
        $interval = $price->recurring->interval;
        $unit_amount = $prod_class->getPrice02IncTax();
        $currency = 'jpy';

        $stripe_price = StPrice::create([
            'unit_amount'   =>  $unit_amount,
            'currency'      =>  $currency,
            'recurring'     =>  [ 'interval'    =>   $interval],
            'product'       =>  $stripe_prod_id
        ]);
        if($stripe_price && $stripe_price->active){
            $id = $stripe_price->id;
            $prod_class->setStripePriceId($id);        
            return $prod_class;
        }
        return false;
    }

    public function updateSubscription($subscription_id, $new_price_id){
        $subscription = Subscription::retrieve($subscription_id);
        if(empty($subscription)){
            return false;
        }
        log_info("StripeService---before---" . $subscription_id);
        $updated_subscription = Subscription::update($subscription_id, [
            'items' => [
                [
                    'id'    =>  $subscription->items->data[0]->id,
                    'price' =>  $new_price_id
                ]
            ]
        ]);
        if(empty($subscription)){
            log_info("StripeService---after---empty" );
            return false;
        }
        log_info("StripeService---after---" . $updated_subscription->id);
        return $updated_subscription->id;
    }

}