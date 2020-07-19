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

namespace Plugin\StripeRec\Service\Method;

include_once(dirname(__FILE__).'/../../../StripePaymentGateway/vendor/stripe/stripe-php/init.php');
use Stripe\Customer as StripeLibCustomer;
use Stripe\PaymentMethod;
use Stripe\Subscription;
use Stripe\Stripe;

use Eccube\Common\EccubeConfig;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Entity\Customer;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Service\Payment\PaymentDispatcher;
use Eccube\Service\Payment\PaymentMethodInterface;
use Eccube\Service\Payment\PaymentResult;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Plugin\RemisePayment4\Form\Type\Admin\ConfigType;
use Symfony\Component\Form\FormInterface;
use Plugin\StripePaymentGateway\Entity\StripeConfig;
use Plugin\StripePaymentGateway\Repository\StripeConfigRepository;
use Plugin\StripePaymentGateway\Entity\StripeLog;
use Plugin\StripePaymentGateway\Repository\StripeLogRepository;
use Plugin\StripePaymentGateway\Entity\StripeOrder;
use Plugin\StripePaymentGateway\Repository\StripeOrderRepository;
use Plugin\StripePaymentGateway\Entity\StripeCustomer;
use Plugin\StripePaymentGateway\Repository\StripeCustomerRepository;
use Plugin\StripePaymentGateway\StripeClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Eccube\Event\EventArgs;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Plugin\StripeRec\Entity\StripeRecOrder;
/**
 * クレジットカード(トークン決済)の決済処理を行う.
 */
class StripeRecurringMethod implements PaymentMethodInterface
{
    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var Order
     */
    protected $Order;

    /**
     * @var FormInterface
     */
    protected $form;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var OrderStatusRepository
     */
    private $orderStatusRepository;

    /**
     * @var StripeConfigRepository
     */
    private $stripeConfigRepository;

    /**
     * @var StripeLogRepository
     */
    private $stripeLogRepository;

    /**
     * @var StripeOrderRepository
     */
    private $stripeOrderRepository;

    /**
     * @var StripeCustomerRepository
     */
    private $stripeCustomerRepository;


    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var PurchaseFlow
     */
    private $purchaseFlow;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var EventDispatcherInterface
     */
    protected $event_dispatcher;

    /**
     * CreditCard constructor.
     *
     * EccubeConfig $eccubeConfig
     * @param EntityManagerInterface $entityManager
     * @param OrderStatusRepository $orderStatusRepository
     * @param StripeConfigRepository $stripeConfigRepository
     * @param StripeLogRepository $stripeLogRepository
     * @param StripeOrderRepository $stripeOrderRepository
     * @param StripeCustomerRepository $stripeCustomerRepository
     * @param ContainerInterface $containerInterface
     * @param PurchaseFlow $shoppingPurchaseFlow
     * @param Session $session
     * @param EventDispatcherInterface $event_dispatcher
     */
    public function __construct(
        EccubeConfig $eccubeConfig,
        EntityManagerInterface $entityManager,
        OrderStatusRepository $orderStatusRepository,
        StripeConfigRepository $stripeConfigRepository,
        StripeLogRepository $stripeLogRepository,
        StripeOrderRepository $stripeOrderRepository,
        StripeCustomerRepository $stripeCustomerRepository,
        ContainerInterface $containerInterface,
        PurchaseFlow $shoppingPurchaseFlow,
        Session $session,
        EventDispatcherInterface $event_dispatcher
    ) {
        $this->eccubeConfig=$eccubeConfig;
        $this->entityManager = $entityManager;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->stripeConfigRepository = $stripeConfigRepository;
        $this->stripeLogRepository = $stripeLogRepository;
        $this->stripeOrderRepository = $stripeOrderRepository;
        $this->stripeCustomerRepository = $stripeCustomerRepository;
        $this->container = $containerInterface;
        $this->purchaseFlow = $shoppingPurchaseFlow;
        $this->session = $session;
        $this->event_dispatcher = $event_dispatcher;
    }

    /**
     * 注文確認画面遷移時に呼び出される.
     *
     * クレジットカードの有効性チェックを行う.
     *
     * @return PaymentResult
     *
     * @throws \Eccube\Service\PurchaseFlow\PurchaseException
     */
    public function verify()
    {
        $customer_id = $this->session->getFlashBag()->get("stripe_customer_id");
        $payment_method_id = $this->session->getFlashBag()->get("payment_method_id");

        $result = new PaymentResult();
        
        if(!empty($customer_id) && $customer_id[0]) {
            if(!empty($payment_method_id) && $payment_method_id[0]) {
                $this->session->getFlashBag()->set("stripe_customer_id", $customer_id);
                $this->session->getFlashBag()->set("payment_method_id", $payment_method_id);
                $result->setSuccess(true);
                return $result;
            }
        }

        $result->setSuccess(false);
        $result->setErrors([trans('stripe_recurring.confirm.card_verify_error')]);

        return $result;
    }

    /**
     * 注文時に呼び出される.
     *
     * 受注ステータス, 決済ステータスを更新する.
     * ここでは決済サーバとの通信は行わない.
     *
     * @return PaymentDispatcher|null
     */
    public function apply()
    {
        // 受注ステータスを決済処理中へ変更
        $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PENDING);
        $this->Order->setOrderStatus($OrderStatus);

        // purchaseFlow::prepareを呼び出し, 購入処理を進める.
        $this->purchaseFlow->prepare($this->Order, new PurchaseContext());

    }

    /**
     * 注文時に呼び出される.
     *
     * クレジットカードの決済処理を行う.
     *
     * @return PaymentResult
     */
    public function checkout()
    {
        // 決済サーバに仮売上のリクエスト送る(設定等によって送るリクエストは異なる)
        // ...
        $customer_id = $this->session->getFlashBag()->get("stripe_customer_id");
        $payment_method_id = $this->session->getFlashBag()->get("payment_method_id");
        
        $customer_id = $customer_id[0];
        $payment_method_id = $payment_method_id[0];

        $StripeConfig = $this->stripeConfigRepository->getConfigByOrder($this->Order);
        // $stripeClient = new StripeClient($StripeConfig->secret_key);
        Stripe::setApiKey($StripeConfig->secret_key);

        try{
            $payment_method = PaymentMethod::retrieve($payment_method_id);
            $payment_method->attach([
                'customer' => $customer_id
            ]);
        }catch(Exception $e){            
            $result->setSuccess(false);
            $result->setErrors([trans('stripe_recurring.checkout.payment_method.retrieve_error')]);
            return $result;
        }

        StripeLibCustomer::update($customer_id, [
            'invoice_settings' => [
                'default_payment_method' => $payment_method_id
            ]
        ]);

        $order_items = $this->Order->getProductOrderItems();
        $product = $order_items[0]->getProduct();
        $price_id = $product->getRecurringId();
        $subscription = Subscription::create([
            'customer' => $customer_id,
            'items'    => [
                [
                    'price' => $price_id
                ]
            ],
            'expand' => ['latest_invoice.payment_intent']
        ]);

        
        if(isset($subscription['error'])){
            $result->setSuccess(false);
            $result->setErrors([trans('stripe_recurring.subscribe.failed')]);
            return $result;
        }
        

        $stripeOrder = new StripeRecOrder();
        $stripeOrder->setOrder($this->Order);
        $stripeOrder->copyFrom($subscription);
        
        $this->entityManager->persist($stripeOrder);

        // purchaseFlow::commitを呼び出し, 購入処理を完了させる.
        $this->purchaseFlow->commit($this->Order, new PurchaseContext());

        $result = new PaymentResult();
        $result->setSuccess(true);
        //EOC create stripe Order

        return $result;
    }


    /**
     * {@inheritdoc}
     */
    public function setFormType(FormInterface $form)
    {
        $this->form = $form;
    }

    /**
     * {@inheritdoc}
     */
    public function setOrder(Order $Order)
    {
        $this->Order = $Order;
    }

    private function writeRequestLog(Order $order, $api) {
        $logMessage = '[Order' . $order->getId() . '][' . $api . '] リクエスト実行';
        log_info($logMessage);

        $stripeLog = new StripeLog();
        $stripeLog->setMessage($logMessage);
        $stripeLog->setCreatedAt(new \DateTime());
        $this->entityManager->persist($stripeLog);
        $this->entityManager->flush($stripeLog);
    }

    private function writeResponseLog(Order $order, $api, $result) {
        $logMessage = '[Order' . $order->getId() . '][' . $api . '] ';
        if (is_object($result)) {
            $logMessage .= '成功';
        } elseif (! is_array($result)) {
            $logMessage .= print_r($result, true);
        } elseif (isset($result['error'])) {
            $logMessage .= $result['error']['message'];
        } else {
            $logMessage .= '成功';
        }
        log_info($logMessage);
        $stripeLog = new StripeLog();
        $stripeLog->setMessage($logMessage);
        $stripeLog->setCreatedAt(new \DateTime());
        $this->entityManager->persist($stripeLog);
        $this->entityManager->flush($stripeLog);
    }

    protected function generateUrl($route, $parameters = array(), $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH)
    {
        return $this->container->get('router')->generate($route, $parameters, $referenceType);
    }
}
