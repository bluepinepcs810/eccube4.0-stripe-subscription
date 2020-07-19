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

namespace Plugin\StripeRec\Controller;
include_once(dirname(__FILE__).'/../../StripePaymentGateway/vendor/stripe/stripe-php/init.php');

use Stripe\Webhook;
use Eccube\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RecurringHookController extends AbstractController{

    protected $container;
    public function __construct(ContainerInterface $container){
        $this->container = $container;        
    }
    /**
     * @Route("/plugin/StripeRec/webhook", name="plugin_stripe_webhook")
     */
    public function webhook(Request $request){
        log_info("webhook_here is.");
        $config_service = $this->get("plg_stripe_rec.service.admin.plugin.config");
        
        $signature = $config_service->getSignature();
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
        log_info("==============[webhook object]======");
        log_info($object);
        switch ($type) {
            case 'invoice.paid':
              // The status of the invoice will show up as paid. Store the status in your
              // database to reference when a user accesses your service to avoid hitting rate
              // limits.
              
              log_info('🔔  Webhook received! ' . $object);
              break;
            case 'invoice.payment_failed':
              // If the payment fails or the customer does not have a valid payment method,
              // an invoice.payment_failed event is sent, the subscription becomes past_due.
              // Use this webhook to notify your user that their payment has
              // failed and to retrieve new card details.
              

              
              log_info('🔔  Webhook received! ' . $object);
              break;
            case 'invoice.finalized':
              // If you want to manually send out invoices to your customers
              // or store them locally to reference to avoid hitting Stripe rate limits.
                log_info('🔔  Webhook received! ' . $object);
              break;
            case 'customer.subscription.deleted':
              // handle subscription cancelled automatically based
              // upon your subscription settings. Or if the user
              // cancels it.
              log_info('🔔  Webhook received! ' . $object);
              break;
            case 'customer.subscription.trial_will_end':
              // Send notification to your user that the trial will end
              log_info('🔔  Webhook received! ' . $object);
              break;
            // ... handle other event types
            default:
              // Unhandled event type
          }
        
        return $this->json(['status' => 'success']);
    }

}