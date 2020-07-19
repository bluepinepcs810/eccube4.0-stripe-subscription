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
use Eccube\Common\EccubeConfig;
use Eccube\Controller\AbstractController;
use Eccube\Entity\Customer;
use Eccube\Entity\Order;
use Eccube\Repository\DeliveryTimeRepository;
use Eccube\Service\CartService;
use Eccube\Service\OrderHelper;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Plugin\StripePaymentGateway\Repository\StripeConfigRepository;
use Plugin\StripePaymentGateway\Repository\StripeOrderRepository;
use Plugin\StripePaymentGateway\Entity\StripeCustomer;
use Plugin\StripePaymentGateway\Repository\StripeCustomerRepository;
use Plugin\StripePaymentGateway\StripeClient;
use Stripe\PaymentMethod;
use Stripe\PaymentIntent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\Session;
// use Symfony\Component\DependencyInjection\ContainerInterface;

class StripeRecurringController extends AbstractController
{
    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var DeliveryTimeRepository
     */
    protected $deliveryTimeRepository;

    /**
     * @var CartService
     */
    protected $cartService;

    /**
     * @var OrderHelper
     */
    protected $orderHelper;

    /**
     * @var StripeConfigRepository
     */
    protected $stripeConfigRepository;

    /**
     * @var StripeCustomerRepository
     */
    protected $stripeCustomerRepository;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var Session
     */
    protected $session;

    /**
     * StripePaymentGatewayController constructor.
     *
     * @param DeliveryTimeRepository $deliveryTimeRepository
     * @param CartService $cartService
     */
    public function __construct(
        EccubeConfig $eccubeConfig,
        CartService $cartService,
        OrderHelper $orderHelper,        
        StripeCustomerRepository $stripeCustomerRepository,
        StripeConfigRepository $stripeConfigRepository,
        EntityManagerInterface $entityManager,
        Session $session
    )
    {
        $this->eccubeConfig=$eccubeConfig;
        $this->cartService = $cartService;
        $this->orderHelper = $orderHelper;
        $this->stripeConfigRepository = $stripeConfigRepository;
        $this->stripeCustomerRepository = $stripeCustomerRepository;
        $this->entityManager = $entityManager;
    }

    // /**
    //  * @Route("/plugin/StripeRec/test", name="plugin_stripe_test")
    //  */
    // public function test(Request $request){
    //     $definition = $this->container->getDefinition("Plugin/")
    // }
    /**
     * @Route("/plugin/StripeRec/presubscribe", name="plugin_stripe_presubscribe")
     */
    public function presubscribe(Request $request)
    {

//        $StripeConfig = $this->stripeConfigRepository->get();
        $preOrderId = $this->cartService->getPreOrderId();
        /** @var Order $Order */
        $Order = $this->orderHelper->getPurchaseProcessingOrder($preOrderId);
        if (!$Order) {
            return $this->json(['error' => 'true', 'message' => trans('stripe_payment_gateway.admin.order.invalid_request')]);
        }
		$StripeConfig = $this->stripeConfigRepository->getConfigByOrder($Order);
        $stripeClient = new StripeClient($StripeConfig->secret_key);
        $paymentMethodId = $request->get('payment_method_id');
        // $isSaveCardOn = $request->get('is_save_card_on', 0);
        $isSaveCardOn = true;
        $stripeCustomerId = $this->procStripeCustomer($stripeClient, $Order, $isSaveCardOn);
        if(is_array($stripeCustomerId)) { // エラー
            return $this->json(["error" => "Cusomer Creation Error"]);
        }
        
        if($Order->isSetRecurring()){
            $this->session->getFlashBag()->set("stripe_customer_id", $stripeCustomerId);
            $this->session->getFlashBag()->set("payment_method_id", $paymentMethodId);
            return $this->json(["error" => false]);
        }else {
        
            return $this->json(["error" => "Not Recurring Product"]);
        }
    }

    private function procStripeCustomer(StripeClient $stripeClient, $Order, $isSaveCardOn) {

        $Customer = $Order->getCustomer();
        $isEcCustomer=false;
        $isStripeCustomer=false;
        $StripeCustomer = false;
        $stripeCustomerId = false;

        if($Customer instanceof Customer ){
            $isEcCustomer=true;
            $StripeCustomer=$this->stripeCustomerRepository->findOneBy(array('Customer'=>$Customer));
            if($StripeCustomer instanceof StripeCustomer){
                $stripLibCustomer = $stripeClient->retrieveCustomer($StripeCustomer->getStripeCustomerId());
                if(is_array($stripLibCustomer) || isset($stripLibCustomer['error'])) {
                    if(isset($stripLibCustomer['error']['code']) && $stripLibCustomer['error']['code'] == 'resource_missing') {
                        $isStripeCustomer = false;
                    }
                } else {
                    $isStripeCustomer=true;
                }
            }
        }

        if($isEcCustomer) {//Create/Update customer
            if($isSaveCardOn) {
                //BOC check if is StripeCustomer then update else create one
                if($isStripeCustomer) {
                    $stripeCustomerId=$StripeCustomer->getStripeCustomerId();
                    //BOC save is save card
                    $StripeCustomer->setIsSaveCardOn($isSaveCardOn);
                    $this->entityManager->persist($StripeCustomer);
                    $this->entityManager->flush($StripeCustomer);
                    //EOC save is save card

                    $updateCustomerStatus = $stripeClient->updateCustomerV2($stripeCustomerId,$Customer->getEmail());
                    if (is_array($updateCustomerStatus) && isset($updateCustomerStatus['error'])) {//In case of update fail
                        $errorMessage=StripeClient::getErrorMessageFromCode($updateCustomerStatus['error'], $this->eccubeConfig['locale']);
                        return ['error' => true, 'message' => $errorMessage];
                    }
                } else {
                    $stripeCustomerId=$stripeClient->createCustomerV2($Customer->getEmail(),$Customer->getId());
                    if (is_array($stripeCustomerId) && isset($stripeCustomerId['error'])) {//In case of fail
                        $errorMessage=StripeClient::getErrorMessageFromCode($stripeCustomerId['error'], $this->eccubeConfig['locale']);
                        return ['error' => true, 'message' => $errorMessage];
                    } else {
                        if(!$StripeCustomer) {
                            $StripeCustomer = new StripeCustomer();
                            $StripeCustomer->setCustomer($Customer);
                        }
                        $StripeCustomer->setStripeCustomerId($stripeCustomerId);
                        $StripeCustomer->setIsSaveCardOn($isSaveCardOn);
                        $StripeCustomer->setCreatedAt(new \DateTime());
                        $this->entityManager->persist($StripeCustomer);
                        $this->entityManager->flush($StripeCustomer);
                    }
                }
                //EOC check if is StripeCustomer then update else create one
                return $stripeCustomerId;
            }
        }
        //Create temp customer
        $stripeCustomerId=$stripeClient->createCustomerV2($Order->getEmail(),0,$Order->getId());
        if (is_array($stripeCustomerId) && isset($stripeCustomerId['error'])) {//In case of fail
            $errorMessage=StripeClient::getErrorMessageFromCode($stripeCustomerId['error'], $this->eccubeConfig['locale']);
            return ['error' => true, 'message' => $errorMessage];
        }
        return $stripeCustomerId;
    }

    private function genPaymentResponse($intent) {
        if($intent instanceof PaymentIntent ) {
            log_info("genPaymentResponse: " . $intent->status);
            switch($intent->status) {
                case 'requires_action':
                case 'requires_source_action':
                    return [
                        'action'=> 'requires_action',
                        'payment_intent_id'=> $intent->id,
                        'client_secret'=> $intent->client_secret
                    ];
                case 'requires_payment_method':
                case 'requires_source':
                    return [
                        'error' => true,
                        'message' => StripeClient::getErrorMessageFromCode('invalid_number', $this->eccubeConfig['locale'])
                    ];
                case 'requires_capture':
                    return [
                        'action' => 'requires_capture',
                        'payment_intent_id' => $intent->id
                    ];
                default:
                    return ['error' => true, 'message' => trans('stripe_payment_gateway.front.unexpected_error')];
//                    return ['error' => true, 'message' => trans('stripe_payment_gateway.front.unexpected_error')];
            }
        }
        if(isset($intent['error'])) {
            $errorMessage=StripeClient::getErrorMessageFromCode($intent['error'], $this->eccubeConfig['locale']);
        } else {
            $errorMessage = trans('stripe_payment_gateway.front.unexpected_error');
        }
        return ['error' => true, 'message' => $errorMessage];
    }
}