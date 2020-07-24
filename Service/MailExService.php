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

use Symfony\Component\DependencyInjection\ContainerInterface;
use Plugin\StripeRec\Repository\StripeRecOrderRepository;
use Eccube\Entity\BaseInfo;
use Eccube\Entity\MailTemplate;
use Plugin\StripeRec\Entity\StripeRecOrder;
use Plugin\StripeRec\Service\Admin\ConfigService;
use Eccube\Service\MailService;
use Eccube\Event\EventArgs;
use Plugin\StripeRec\Controller\RecurringHookController;


class MailExService{
    
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

    /**
     * @param EventArgs $event
     */
    public function onSendOrderMailBefore(EventArgs $event){
        log_info(RecurringHookController::LOG_IF . "onSendOrderMailBefore");
        
        $order = $event->getArgument('Order');
        $stripe_rec_order = $this->rec_order_repo->getFromOrder($order);
        if(empty($stripe_rec_order)){
            log_info(RecurringHookController::LOG_IF . "stripe_rec_order is empty");
            return;
        }

        // $em = $this->container->get('doctrine.orm.entity_manager');
        $base_info = $this->em->getRepository(BaseInfo::class)->get();

        $status = $stripe_rec_order->getPaidStatus();
        $rec_status = $stripe_rec_order->getRecStatus();

        if($rec_status == StripeRecOrder::REC_STATUS_CANCELED){
            $template = $this->em->getRepository(MailTemplate::class)->findOneBy([
                'name'  =>  ConfigService::REC_CANCELED
            ]);
        }else if($status == StripeRecOrder::STATUS_PAY_UPCOMING){

            $template = $this->em->getRepository(MailTemplate::class)->findOneBy([
                'name'  =>  ConfigService::PAY_UPCOMING
            ]);

        }else if($status == StripeRecOrder::STATUS_PAID){
            $template = $this->em->getRepository(MailTemplate::class)->findOneBy([
                'name'  =>  ConfigService::PAID_MAIL_NAME
            ]);
        }else if($status == StripeRecOrder::STATUS_PAY_FAILED){
            $template = $this->em->getRepository(MailTemplate::class)->findOneBy([
                'name'  =>  ConfigService::PAY_FAILED_MAIL_NAME
            ]);
        }else{
            log_info(RecurringHookController::LOG_IF . "unknow status");
            return;
        }
        
        if(empty($template) || ($template && empty($template->getFileName()))){
            return;
        }
        log_info(RecurringHookController::LOG_IF . "change template");

        $template_path = $template->getFileName();

        $param = ['order'   =>  $order, 'rec_order' =>  $stripe_rec_order];

        $engine = $this->container->get('twig');
        $vtMessage = $engine->render($template_path, $param, null);

        // $orderMassage = str_replace(["\r\n", "\r", "\n"], "<br/>", $vtMessage);
        $orderMassage = $vtMessage;

        $message = $event->getArgument('message');
        // テキスト形式用にHTMLエンティティをデコードして設定
        // $Order->setMessage(htmlspecialchars_decode($orderMassage));
        $order->setMessage($orderMassage);
        $MailService = $this->container->get(MailService::class);
        $htmlFileName = $MailService->getHtmlTemplate($template->getFileName());

        $beforeBody = $message->getChildren();
        $message->detach($beforeBody[0]);
        // HTML形式テンプレートを使用する場合
        if (!is_null($htmlFileName)) {
            // 注文完了メールに表示するメッセージの改行コードをbrタグに変換して再設定
            $htmlBody = $engine->render($htmlFileName, $param);

            // HTML形式で使われるbodyを再設定
            
            $message->addPart(htmlspecialchars_decode($htmlBody), 'text/html');
            
        }
        
        $message->setSubject('['.$base_info->getShopName().'] '.$template->getMailSubject());
        $message->setBody($orderMassage);
        log_info(RecurringHookController::LOG_IF . "send");

    }

}