<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) devcrazygit. All Rights Reserved.
 *
 * https://github.com/devcrazygit
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\StripeRec\Controller\Mypage;


use Symfony\Component\DependencyInjection\ContainerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Routing\Annotation\Route;
use Eccube\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Knp\Component\Pager\Paginator;
use Plugin\StripeRec\Repository\StripeRecOrderRepository;
use Plugin\StripeRec\Entity\StripeRecOrder;

class MypageController extends AbstractController
{
    protected $container;
    protected $rec_repo;
    protected $em;

    public function __construct(
        ContainerInterface $container,
        StripeRecOrderRepository $rec_repo
    ){
        $this->container = $container;
        $this->rec_repo = $rec_repo;
        $this->em = $container->get('doctrine.orm.entity_manager'); 
    }

    /**
     * Mypage rec_order_history
     *
     * @Route("/mypage/stripe_rec_history", name="mypage_stripe_rec")
     * @Template("StripeRec/Resource/template/default/Mypage/recurring_tab.twig")
     * @param Request $request
     * @param Paginator $paginator
     */
    public function index(Request $request, Paginator $paginator){
        if(!$this->isGranted('ROLE_USER')){
            return $this->redirectToRoute('mypage_login');
        }
        $Customer = $this->getUser();

        if(!$Customer){
            return $this->redirectToRoute('mypage_login');
        }
        $qb = $this->rec_repo->getQueryBuilderByCustomer($Customer);
        $pagination = $paginator->paginate(
            $qb,
            $request->get('pageno', 1),
            $this->eccubeConfig['eccube_search_pmax']
        );
        return [
            'pagination'    =>  $pagination
        ];        
    }

    /**
     * Mypage rec_order_history
     *
     * @Route("/mypage/rec_history/{id}/stop", name="mypage_stripe_rec_cancel")
     * @Template("StripeRec/Resource/template/default/Mypage/recurring_tab.twig")
     */
    public function cancelSubscription(Request $request, $id=null){
        if(!$this->isGranted('ROLE_USER')){
            return $this->redirectToRoute('mypage_login');
        }
        $Customer = $this->getUser();

        if(!$Customer){
            return $this->redirectToRoute('mypage_login');
        }
        $rec_order = $this->rec_repo->findOneBy([
            "id" => $id,  
            "rec_status" => StripeRecOrder::REC_STATUS_ACTIVE
        ]);
        if(!empty($rec_order)){
            $util = $this->container->get('plg_stripe_rec.service.util');
            $res = $util->cancelRecurring($rec_order);
            if(!empty($res)){
                $rec_order->setRecStatus(StripeRecOrder::REC_STATUS_CANCELED);
                $this->em->persist($rec_order);
                $this->em->flush();
            }
        }        
        return $this->redirectToRoute("mypage_stripe_rec");

    }

}