<?php
/*
* Plugin Name : StripeRec
*
* Copyright (C) 2020 Subspire. All Rights Reserved.
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Plugin\StripeRec\Controller;
if( \file_exists(dirname(__FILE__).'/../../StripePaymentGateway/vendor/stripe/stripe-php/init.php')) {
    include_once(dirname(__FILE__).'/../../StripePaymentGateway/vendor/stripe/stripe-php/init.php');
}

use Stripe\Webhook;
use Eccube\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Plugin\StripeRec\Entity\StripeRecOrder;
use Eccube\Entity\Order;
use Eccube\Service\MailService;

class RecurringHookController extends AbstractController{

    protected $container;
    /**
     * エンティティーマネージャー
     */
    private $em;

    private $rec_order_repo;

    private $config_service;
    private $mail_service;

    const LOG_IF = "rmaj111---";


    public function __construct(ContainerInterface $container, MailService $mail_service){
        $this->container = $container;       
        $this->em = $container->get('doctrine.orm.entity_manager'); 
        $this->rec_order_repo = $this->em->getRepository(StripeRecOrder::class);
        $this->config_service = $container->get("plg_stripe_rec.service.admin.plugin.config");
        $this->mail_service = $mail_service;
    }
    /**
     * @Route("/plugin/StripeRec/webhook", name="plugin_stripe_webhook")
     */
    public function webhook(Request $request){

        $signature = $this->config_service->getSignature();
        if($signature){
            try{
                log_info("=============[webhook sign started] 0============\n");
                log_info("current_sign : $signature");
                $event = Webhook::constructEvent(
                    $request->getContent(), 
                    $request->headers->get('stripe-signature'),
                    $signature, 500
                );
                
                $type = $event['type'];
                $object = $event['data']['object'];
                log_info("webhook_type : $type");
            }catch(Exception $e){
                
                log_error("=============[webhook sign error]============\n" );
                return $this->json(['status' => 'failed']);
            }
        }else{
            log_info("=============[webhook processing without sign]============");
            $data = $request->query->all();
            $type = $data['type'];
            $object = $data['data']['object'];
        }
        log_info("==============[webhook object] $type ======");
        
        switch ($type) {
            case 'invoice.payment_succeeded':
                log_info('🔔 ' . $type . ' Webhook received! ' . $object);            
                $this->invoicePaid($object);
                break;
            case 'invoice.paid':
            // case 'customer.deleted':
              // The status of the invoice will show up as paid. Store the status in your
              // database to reference when a user accesses your service to avoid hitting rate
              // limits.              
              log_info('🔔 ' . $type . ' Webhook received! ' . $object);            
              $this->invoicePaid($object);
              
              break;
            case 'invoice.payment_failed':
              // If the payment fails or the customer does not have a valid payment method,
              // an invoice.payment_failed event is sent, the subscription becomes past_due.
              // Use this webhook to notify your user that their payment has
              // failed and to retrieve new card details.
              log_info('🔔 ' . $type . ' Webhook received! ' . $object);
              $this->invoiceFailed($object);
              break;
            case 'invoice.upcoming':
                log_info('🔔 ' . $type . ' Webhook received! ' . $object);
                $this->invoiceUpcoming($object);
                break;
            case 'invoice.finalized':
              // If you want to manually send out invoices to your customers
              // or store them locally to reference to avoid hitting Stripe rate limits.
                log_info('🔔 ' . $type . ' Webhook received! ' . $object);
              break;
            case 'customer.subscription.deleted':
              // handle subscription cancelled automatically based
              // upon your subscription settings. Or if the user
              // cancels it.
              log_info('🔔 ' . $type . ' Webhook received! ' . $object);
              $this->recurringCanceled($object);
              break;
            case 'customer.subscription.trial_will_end':
              // Send notification to your user that the trial will end
              log_info('🔔 ' . $type . ' Webhook received! ' . $object);
              break;
            case 'customer.subscription.updated':
                log_info('🔔 ' . $type . ' Webhook received! ' . $object);      
                $this->subscriptionUpdated($object);          
            // ... handle other event types
            default:
              // Unhandled event type
          }
        
        return $this->json(['status' => 'success']);
    }
    public function sendRecMail($sub_id){
        if($sub_id instanceof StripeRecOrder){
            $rec_order = $sub_id;
        }else{
            $rec_order = $this->rec_order_repo->findOneBy(['subscription_id' => $sub_id]);
        }
        
        if(empty($rec_order)){
            log_info(RecurringHookController::LOG_IF . "rec_order is empty");
            return;              
        }
        $order = $rec_order->getOrder();
        log_info(RecurringHookController::LOG_IF . "mail sending");
        if(!empty($order)){
            $this->mail_service->sendOrderMail($order);
        }
    }

    public function subscriptionUpdated($object){
        $sub_id = $object->id;
        $items = $object->items->data;
        
        if(!empty($items[0]->price->id)){
            $rec_order = $this->rec_order_repo->findOneBy(['subscription_id' =>  $sub_id]);
            if(!empty($rec_order)){
                $rec_order->setPriceId($items[0]->price->id);
                $this->em->persist($rec_order);
                $this->em->flush();
            }            
        }    
    }

    public function updateRecOrderStatus($sub_id, $paid_status){
        $rec_order = $this->rec_order_repo->findOneBy(['subscription_id' => $sub_id]);
        if(empty($rec_order)){
            log_info(RecurringHookController::LOG_IF . "rec_order is empty");
            return;              
        }
        log_info(RecurringHookController::LOG_IF . "rec_order status setting to $paid_status");
        $rec_order->setPaidStatus($paid_status); // )
        $this->em->persist($rec_order);
        $this->em->flush();
    }
    public function invoicePaid($object){        
        $customer = $object->customer;
        $data = $object->lines->data;
        foreach($data as $item){
            if(!empty($item->subscription)){        
                $pay_date = new \DateTime();
                $pay_date->setTimestamp($object->created);     
                $this->createOrUpdateRecOrder(
                    StripeRecOrder::STATUS_PAID,
                    $item,
                    $customer,
                    $pay_date
                    );                
                $this->sendRecMail($item->subscription);
                log_info("🔔 rmaj111---sended email");
            }
        }
    }

    public function invoiceUpcoming($object){
        $stripe_customer_id = $object->customer;
        $data = $object->lines->data;
        foreach($data as $item){
            if(!empty($item->subscription)){
                $sub_id = $item->subscription;
                $rec_order = $this->rec_order_repo->findOneBy([
                    'subscription_id' => $sub_id, 
                    "stripe_customer_id" => $stripe_customer_id]);
                if(!empty($rec_order)){                 
                    $this->createOrUpdateRecOrder(
                        StripeRecOrder::STATUS_PAY_UPCOMING,
                        $item,
                        $stripe_customer_id
                        );                    
                    $this->sendRecMail($sub_id);
                    log_info("🔔 rmaj111---sended email");
                }
            }
        }
    }
    public function invoiceFailed($object){
        $stripe_customer_id = $object->customer;
        $data = $object->lines->data;
        foreach($data as $item){
            if(!empty($item->subscription)){
                $sub_id = $item->subscription;
                $rec_order = $this->rec_order_repo->findOneBy([
                    'subscription_id' => $sub_id, 
                    "stripe_customer_id" => $stripe_customer_id]);
                if(!empty($rec_order)){
                    $this->createOrUpdateRecOrder(
                        StripeRecOrder::STATUS_PAY_FAILED,
                        $item,
                        $stripe_customer_id
                        );                    
                    $this->sendRecMail($sub_id);
                    log_info("🔔 rmaj111---sended email");
                }
            }
        }
    }
    public function createOrUpdateRecOrder($paid_status, $item, $stripe_customer_id, $last_payment_date = null){
        $sub_id = $item->subscription;
        $rec_order = $this->rec_order_repo->findOneBy(['subscription_id' => $sub_id, "stripe_customer_id" => $stripe_customer_id]);
        if(empty($rec_order)){
            log_info(RecurringHookController::LOG_IF . "rec order is empty in webhook");
            $rec_order = new StripeRecOrder;

            $rec_order->setSubscriptionId($sub_id);            
            $rec_order->setStripeCustomerId($stripe_customer_id);
        }
        
        $rec_order->setCurrentPeriodStart($this->convertDateTime($item->period->start));
        $rec_order->setCurrentPeriodEnd($this->convertDateTime($item->period->end));
        
        $rec_order->setPriceId($item->price->id);
        $rec_order->setPaidStatus($paid_status);
        if(!empty($last_payment_date)){
            $rec_order->setLastPaymentDate($last_payment_date);
        }

        $rec_order->setUnitAmount($item->price->unit_amount);
        $rec_order->setQuantity($item->quantity);
        $rec_order->setInterval($item->plan->interval);
        $this->em->persist($rec_order);
        $this->em->flush();
        $this->em->commit();  
    }

    public function recurringCanceled($object){
        $sub_id = $object->id;
        $rec_order = $this->rec_order_repo->findOneBy(['subscription_id' => $sub_id]);
        if(!empty($rec_order)){
            $rec_order->setRecStatus(StripeRecOrder::REC_STATUS_CANCELED);
            $this->em->persist($rec_order);
            $this->em->flush();
            $this->em->commit();
            $this->sendRecMail($rec_order);
        }
    }

    public function convertDateTime($timestamp){
        $dt1 = new \DateTime();
        $dt1->setTimestamp($timestamp);
        return $dt1;
    }

}