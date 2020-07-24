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
use Eccube\Repository\PageRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Plugin\StripeRec\Service\Method\StripeRecurringMethod;
use Plugin\StripeRec\Service\Admin\ConfigService;
use Eccube\Entity\MailTemplate;
use Eccube\Entity\Page;
use Eccube\Entity\PageLayout;
use Eccube\Entity\Layout;

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
        $this->insertMailTemplate($container);
        $this->registerPageForUpdate($container);
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
        $this->removeMailTemplate($container);
        $this->unregisterPageForUpdate($container);
    }
    /**
     * プラグインインストール時の処理
     *
     * @param array $meta
     * @param ContainerInterface $container
     */
    public function install(array $meta, ContainerInterface $container)
    {
        
    }
    /**
     * プラグインアップデート時の処理
     *
     * @param array              $meta
     * @param ContainerInterface $container
     */
    public function update(array $meta, ContainerInterface $container)
    {
        // $this->registerPageForUpdate($container);
    }

    protected function registerPageForUpdate($container){
        $em = $container->get('doctrine.orm.entity_manager');
        $page_consts = array(
            [
            'name' =>  'mypage_stripe_rec',
            'label' =>  'MYページ/定期コース',
            'template'  =>  'StripeRec/Resource/template/default/Mypage/recurring_tab'
            ]
        );
        foreach($page_consts as $page_url){
            $url = $page_url['name'];
            $page = $container->get(PageRepository::class)->findOneBy(compact('url'));
            if(is_null($page)){
                $page = new Page;
            }
            $page->setName($page_url['label']);
            $page->setUrl($url);
            $page->setMetaRobots('noindex');
            $page->setFileName($page_url['template']);
            $page->setEditType(Page::EDIT_TYPE_DEFAULT);

            $em->persist($page);
            $em->flush();
            // $em->commit();
            
            $pageLayoutRepository = $em->getRepository(PageLayout::class);
            $pageLayout = $pageLayoutRepository->findOneBy([
                'page_id' => $page->getId()
            ]);
            // 存在しない場合は新規作成
            if (is_null($pageLayout)) {
                $pageLayout = new PageLayout;
                // 存在するレコードで一番大きいソート番号を取得
                $lastSortNo = $pageLayoutRepository->findOneBy([], ['sort_no' => 'desc'])->getSortNo();
                // ソート番号は新規作成時のみ設定
                $pageLayout->setSortNo($lastSortNo+1);
            }
            // 下層ページ用レイアウトを取得
            $layout = $em->getRepository(Layout::class)->find(Layout::DEFAULT_LAYOUT_UNDERLAYER_PAGE);

            $pageLayout->setPage($page);
            $pageLayout->setPageId($page->getId());
            $pageLayout->setLayout($layout);
            $pageLayout->setLayoutId($layout->getId());

            $em->persist($pageLayout);
            $em->flush();
        }
    }
    protected function unregisterPageForUpdate($container){
        $page_names = [
            'mypage_stripe_rec'
        ];
        $em = $container->get('doctrine.orm.entity_manager');
        foreach($page_names as $page_name){
            $page = $em->getRepository(Page::class)->findOneBy(['url' => $page_name]);
            if(is_null($page)){
                continue;
            }
            $pageLayoutRepository = $em->getRepository(PageLayout::class);
            $pageLayout = $pageLayoutRepository->findOneBy([
                'page_id' => $page->getId()
            ]);
            if(!is_null($pageLayout)){
                $em->remove($pageLayout);
                // $em->persist($pageLayout);
                $em->flush();
            }
            $em->remove($page);
            $em->flush();
        }
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
        $Payment->setMethod("Stripe 定期支払い");
        $Payment->setMethodClass(StripeRecurringMethod::class);
        $entityManager->persist($Payment);
        $entityManager->flush($Payment);
    }
    
    public function insertMailTemplate(ContainerInterface $container){
        $template_list = [
                [
                    'name'      =>  ConfigService::PAID_MAIL_NAME,
                    'file_name' =>  'StripeRec\Resource\template\mail\rec_order_success.twig',
                    'mail_subject'  => 'Stripe 支払い成功',                
                ],
                [
                    'name'      =>  ConfigService::PAY_FAILED_MAIL_NAME,
                    'file_name' =>  'StripeRec\Resource\template\mail\rec_order_failed.twig',
                    'mail_subject'  =>  'Stripe 定期支払い失敗'
                ],
                [
                    'name'      =>  ConfigService::PAY_UPCOMING,
                    'file_name' =>  'StripeRec\Resource\template\mail\rec_order_upcoming.twig',
                    'mail_subject'  =>  'Stripe 定期支払い待機'//"Stripe Subsciption Payment Upcoming"
                ],
                [
                    'name'      =>  ConfigService::REC_CANCELED,
                    'file_name' =>  'StripeRec\Resource\template\mail\rec_order_canceled.twig',
                    'mail_subject'  =>  'Stripe 定期支払いキャンセル済み' // 'Stripe Subscription Cancel'
                ]
            ];
        $em = $container->get('doctrine.orm.entity_manager');
        foreach($template_list as $template){
            $item = new MailTemplate();
            $item->setName($template["name"]);
            $item->setFileName($template["file_name"]);
            $item->setMailSubject($template["mail_subject"]);
            $em->persist($item);            
            $em->flush();
        }
    }
    public function removeMailTemplate(ContainerInterface $container){
        $subject_list = [
            ConfigService::PAID_MAIL_NAME,
            ConfigService::PAY_FAILED_MAIL_NAME,
            ConfigService::PAY_UPCOMING,
            ConfigService::REC_CANCELED
        ];
        $em = $container->get('doctrine.orm.entity_manager');
        foreach($subject_list as $subject){
            $template = $em->getRepository(MailTemplate::class)->findOneBy(["mail_subject" => $subject]);
            if ($template){
                $em->remove($template);
                $em->flush();
            }
        }
    }
}