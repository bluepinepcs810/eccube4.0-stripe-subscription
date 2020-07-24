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

namespace Plugin\StripeRec\Repository;

use Eccube\Repository\AbstractRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Plugin\StripeRec\Entity\StripeRecOrder;
use Eccube\Util\StringUtil;
// use Plugin\StripeRec\Entity\StripeRecOrder;

class StripeRecOrderRepository extends AbstractRepository{
    /**
     * @param RegistryInterface $registry
     */
    public function __construct(RegistryInterface $registry){
        parent::__construct($registry, StripeRecOrder::class);
    }
    /**
     * @param int $id
     * @return null|StripeRecOrder
     */
    public function get($id = 1){
        return $this->find($id);
    }

    public function getFromOrder($order){
        $stripe_rec_order = $this->findOneBy(['Order' => $order]);
        return $stripe_rec_order;
    }
    public function getQueryBuilderBySearchDataForAdmin($search_data){
        $qb = $this->createQueryBuilder('ro')
            ->select('ro, o')
            ->leftJoin('ro.Order', 'o');            
        
        if(isset($search_data['paid_status']) && StringUtil::isNotBlank($search_data['paid_status'])){
            
            $qb
                ->andWhere($qb->expr()->in('ro.paid_status', ':paid_status'))
                ->setParameter('paid_status', $search_data['paid_status']);
        }
        if(isset($search_data['rec_status']) && StringUtil::isNotBlank($search_data['rec_status'])){
            // print_r($search_data['rec_status']); die();
            $qb
                ->andWhere($qb->expr()->in('ro.rec_status', ':rec_status'))
                ->setParameter('rec_status', $search_data['rec_status']);
        }
        $qb->orderBy('ro.current_period_end', 'DESC');
        return $qb->addorderBy('ro.last_payment_date', 'DESC');

    }
    public function getQueryBuilderByCustomer($Customer){
        $qb = $this->createQueryBuilder("ro")
            ->where("ro.Customer = :customer")
            // ->andWhere("ro.rec_status = :rec_status")
            ->setParameter("customer", $Customer);
            // ->setParameter("rec_status", StripeRecOrder::REC_STATUS_ACTIVE);
        return $qb->orderBy("ro.create_date", "DESC");
    }
}
