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

namespace Plugin\StripeRec;


use Eccube\Entity\Payment;
use Eccube\Plugin\AbstractPluginManager;
use Eccube\Repository\PaymentRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Plugin\StripeRec\Service\Method\StripeRecurringMethod;

class PluginManager extends AbstractPluginManager{
    
    private $stripe_js_file_path;
    private $backup_path;
    private $stripe_instead;

    public function __construct(){
        $this->stripe_js_file_path = __DIR__ . "/../StripePaymentGateway/Resource/assets/js/stripe_js.twig";        
        $this->backup_path = __DIR__ . "/Resource/assets/js/stripe_js.twig";
        $this->stripe_instead = __DIR__ . "/Resource/assets/js/stripe_recurring_js.twig";
    }

    public function enable(array $meta, ContainerInterface $container)
    {        
        // if(!file_exists($this->stripe_js_file_path)){
        //     return;
        // }

        // $js_contents = file_get_contents($this->stripe_js_file_path);
        // file_put_contents($this->backup_path, $js_contents);
        // $instead_js = file_get_contents($this->stripe_instead);
        // file_put_contents($this->stripe_js_file_path, $instead_js);      
        $this->createTokenPayment($container);
    }

    public function disable(array $meta, ContainerInterface $container){
        // if(!file_exists($this->stripe_js_file_path)){
        //     return;
        // }
        // if(!file_exists($this->backup_path)){
        //     return;
        // }
        // $backup_js = \file_get_contents($this->backup_path);
        // if(!empty($backup_js)){
        //     file_put_contents($this->stripe_js_file_path, $backup_js);
        // }
    }

    private function createTokenPayment(ContainerInterface $container)
    {
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $paymentRepository = $container->get(PaymentRepository::class);
        $Payment = $paymentRepository->findOneBy([], ['sort_no' => 'DESC']);
        $sortNo = $Payment ? $Payment->getSortNo() + 1 : 1;
        $Payment = $paymentRepository->findOneBy(['method_class' => StripeRecurringMethod::class]);
        if ($Payment) {
            return;
        }
        $Payment = new Payment();
        $Payment->setCharge(0);
        $Payment->setSortNo($sortNo);
        $Payment->setVisible(true);
        $Payment->setMethod(trans("StripeRecurring"));
        $Payment->setMethodClass(StripeRecurringMethod::class);
        $entityManager->persist($Payment);
        $entityManager->flush($Payment);
    }
}