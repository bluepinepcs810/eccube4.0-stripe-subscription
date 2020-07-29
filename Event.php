<?php
/*
* Plugin Name : StripeRec
*
* Copyright (C) 2020 Subspire. All Rights Reserved.
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Plugin\StripeRec;

use Eccube\Event\EventArgs;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Eccube\Common\EccubeConfig;
use Eccube\Event\EccubeEvents;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Eccube\Event\TemplateEvent;
use Plugin\StripePaymentGateway\Repository\StripeConfigRepository;
use Eccube\Entity\Payment;
use Plugin\StripeRec\Service\Method\StripeRecurringMethod;
use Plugin\StripePaymentGateway\Repository\StripeCustomerRepository;
use Eccube\Entity\Customer as Customer;
use Plugin\StripePaymentGateway\StripeClient;
use Stripe\PaymentMethod;
use Symfony\Component\HttpFoundation\Session\Session;

class Event implements EventSubscriberInterface
{
    private $container;
    
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var StripeConfigRepository
     */
    protected $stripeConfigRepository;

    /**
     * @var StripeCustomerRepository
     */
    private $stripeCustomerRepository;
    /**
     * @var エラーメッセージ
     */
    private $errorMessage = null;

    /**
     * @var string ロケール（jaかenのいずれか）
     */
    private $locale = 'en';

    /**
     * @var Session
     */
    protected $session;


    public function __construct(
        ContainerInterface $container,        
        EntityManagerInterface $entityManager,
        StripeConfigRepository $stripeConfigRepository,
        StripeCustomerRepository $stripeCustomerRepository,
        EccubeConfig $eccubeConfig,
        Session $session)
    {        
        $this->container = $container;
        $this->entityManager = $entityManager;
        $this->stripeConfigRepository = $stripeConfigRepository;
        $this->stripeCustomerRepository = $stripeCustomerRepository;

        $this->locale = $eccubeConfig['locale'];
        $this->session = $session;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents(){
        return [
            // EccubeEvents::ADMIN_PRODUCT_EDIT_INITIALIZE => "onProductEditInit",
            // EccubeEvents::ADMIN_PRODUCT_EDIT_COMPLETE => 'onProductEditComplete',
            "@admin/Product/product.twig" => "onProductEdit",
            'Shopping/confirm.twig' => 'onShoppingConfirmTwig',
            'Shopping/index.twig' => 'onShoppingIndexTwig',        
            EccubeEvents::MAIL_ORDER => 'sendOrderMailBefore',

            'Mypage/index.twig' => 'myPageNaviRenderBefore',
            'Mypage/history.twig' => 'myPageNaviRenderBefore',
            'Mypage/favorite.twig' => 'myPageNaviRenderBefore',
            'Mypage/change.twig' => 'myPageNaviRenderBefore',
            'Mypage/change_complete.twig' => 'myPageNaviRenderBefore',
            'Mypage/delivery.twig' => 'myPageNaviRenderBefore',
            'Mypage/delivery_edit.twig' => 'myPageNaviRenderBefore',
            'Mypage/withdraw.twig' => 'myPageNaviRenderBefore',  
            'StripeRec/Resource/template/Mypage/recurring_tab.twig' => 'myPageNaviRenderBefore'
        ];
    }
    public function sendOrderMailBefore(EventArgs $event)
    {
        $this->container->get('plg_stripe_rec.service.email.service')->onSendOrderMailBefore($event);
    }
    public function myPageNaviRenderBefore(TemplateEvent $event){
        $event->addSnippet('@StripeRec/default/Mypage/navi.twig');
    }
    public function onProductEditComplete(EventArgs $event){
        $this->container->get('plg_stripe_rec.service.product_service')->onProductEditComplete($event);
    }
    // public function onProductEditInit(EventArgs $event){
    //     $this->container->get('plg_stripe_rec.service.product_service')->onProductEditInit($event);
    // }

    public function onProductEdit(TemplateEvent $event){
        
        $event->addSnippet('@StripeRec/admin/product_recurring.twig');
    }
    public function onShoppingConfirmTwig(TemplateEvent $event){
        
    }
    public function onShoppingIndexTwig(TemplateEvent $event){
        $Order=$event->getParameter('Order');
        $this->session->getFlashBag()->set("stripe_customer_id", false);
        $this->session->getFlashBag()->set("payment_method_id", false);
        if($Order) {
            $StripeConfig = $this->stripeConfigRepository->getConfigByOrder($Order);
            
            if ($Order->getPayment()->getMethodClass() === StripeRecurringMethod::class
                &&  $this->isEligiblePaymentMethod($Order->getPayment(),$Order->getPaymentTotal())
                 && $Order->hasStripePriceId()) {
                
                $stripeClient = new StripeClient($StripeConfig->secret_key);
                //BOC check if registered shop customer
                $stripePaymentMethodObj = false;
                $customerObj=false;
                $isSaveCardOn=false;
                $Customer=$Order->getCustomer();
                if($Customer instanceof Customer){
                    $customerObj=$Customer;
                    $StripeCustomer=$this->stripeCustomerRepository->findOneBy(array('Customer'=>$Customer));
                    if($StripeCustomer instanceof StripeCustomer){
                        $isSaveCardOn=$StripeCustomer->getIsSaveCardOn();
                        $stripePaymentMethodObj = $stripeClient->retrieveLastPaymentMethodByCustomer($StripeCustomer->getStripeCustomerId());
                        if( !($stripePaymentMethodObj instanceof PaymentMethod) || !$stripeClient->isPaymentMethodId($stripePaymentMethodObj->id) ) {
                            $stripePaymentMethodObj = false;
                        }
                    }
                }
                //EOC check if registered shop customer

                if(isset($_REQUEST['stripe_card_error'])){
                    $this->errorMessage=$_REQUEST['stripe_card_error'];
                }

//                $StripeConfig = $this->stripeConfigRepository->get();
                $stripeCSS = 'StripePaymentGateway/Resource/assets/css/stripe.css.twig';
                $event->addAsset($stripeCSS);

                $stripeOfficialJS = 'StripePaymentGateway/Resource/assets/js/stripe_official.js.twig';
                $event->addAsset($stripeOfficialJS);

                // JSファイルがなければオンデマンドで生成
//                if (!file_exists($this->getScriptDiskPath())) {
//                    $this->makeScript();
//                }

                $event->setParameter('stripConfig', $StripeConfig);
                $event->setParameter('stripeErrorMessage', $this->errorMessage);
                $event->setParameter('stripeCreditCardPaymentId', $Order->getPayment()->getId());
                $event->setParameter('stripePaymentMethodObj', $stripePaymentMethodObj);
                $event->setParameter('customerObj', $customerObj);
                $event->setParameter('stripeIsSaveCardOn', true);
                $event->setParameter('stripe_locale', $this->locale);

                $event->addSnippet('@StripeRec/default/Shopping/stripe_credit_card.twig');
                $stripeJS= 'StripeRec/Resource/assets/js/stripe_recurring_js.twig';
                $event->addAsset($stripeJS);
            }
        }
    }
    private function isEligiblePaymentMethod(Payment $Payment,$total){
        $min = $Payment->getRuleMin();
        $max = $Payment->getRuleMax();
        if (null !== $min && $total < $min) {
            return false;
        }
        if (null !== $max && $total > $max) {
            return false;
        }
        return true;
    }

}
