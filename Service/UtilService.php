<?php
/*
* Plugin Name : StripeRec
*
* Copyright (C) 2020 devcrazy. All Rights Reserved.
* https://github.com/devcrazygit
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Plugin\StripeRec\Service;
include_once(dirname(__FILE__).'/../../StripePaymentGateway/vendor/stripe/stripe-php/init.php');

use Stripe\Subscription;
use Stripe\Stripe;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Plugin\StripeRec\Repository\StripeRecOrderRepository;
use Eccube\Entity\BaseInfo;
use Eccube\Entity\MailTemplate;
use Plugin\StripeRec\Entity\StripeRecOrder;
use Plugin\StripeRec\Service\Admin\ConfigService;
use Eccube\Event\EventArgs;
use Plugin\StripePaymentGateway\Repository\StripeConfigRepository;

class UtilService{
    
    protected $container;
    protected $rec_order_repo;
    protected $em;

    public function __construct(
        ContainerInterface $container
        // StripeRecOrderRepository $rec_order_repo
        ){
        $this->container = $container;
        $this->em = $this->container->get('doctrine.orm.entity_manager');
        $this->rec_order_repo = $this->em->getRepository(StripeRecOrder::class);
    }

    public function paidStatusObj($paid_status){
        if($paid_status instanceof StripeRecOrder){
            $paid_status = $paid_status->getPaidStatus();
        }
        switch($paid_status){
            case StripeRecOrder::STATUS_PAID:
                return [trans('stripe_recurring.label.paid_status.paid'), '#437ec4'];
            case StripeRecOrder::STATUS_PAY_FAILED:
                return [trans('stripe_recurring.label.paid_status.failed'), '#C04949'];
            case StripeRecOrder::STATUS_PAY_UPCOMING:
                return [trans('stripe_recurring.label.paid_status.upcoming'), '#EEB128'];
            case StripeRecOrder::STATUS_PAY_UNDEFINED:
                return [trans('stripe_recurring.label.paid_status.undefined'), '#A3A3A3'];
        }
        return [trans('stripe_recurring.label.paid_status.undefined'), '#A3A3A3'];
    }
    public function recStatusObj($rec_status){
        if($rec_status instanceof StripeRecOrder){
            $rec_status = $rec_status->getRecStatus();
        }
        switch($rec_status){
            case StripeRecOrder::REC_STATUS_ACTIVE:
                return [trans('stripe_recurring.label.rec_status.active'), '#437ec4'];
            case StripeRecOrder::REC_STATUS_CANCELED:
                return [trans('stripe_recurring.label.rec_status.canceled'), '#A3A3A3'];
        }
        return [trans('stripe_recurring.label.rec_status.active'), '#437ec4'];
    }

    public function cancelRecurring($rec_order){
        $stripeConfigRepository = $this->container->get(StripeConfigRepository::class);        
        $StripeConfig = $stripeConfigRepository->getConfigByOrder(null);

        Stripe::setApiKey($StripeConfig->secret_key);
        $subscription = Subscription::retrieve($rec_order->getSubscriptionId());
        
        if($subscription && $subscription->status != StripeRecOrder::REC_STATUS_CANCELED){
            try{
                $subscription->cancel();
                return true;
            }catch(Exception $ex){
                return false;
            }
        }
    }
    public function cancelRecurringForce($rec_order){
        $this->cancelRecurring($rec_order);
        $em = $this->container->get('doctrine.orm.entity_manager');
        $rec_order->setRecStatus(StripeRecOrder::REC_STATUS_CANCELED);
        $em->persist($rec_order);
        $em->flush();

    }
}