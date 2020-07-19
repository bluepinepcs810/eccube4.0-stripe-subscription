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

namespace Plugin\StripeRec\Entity;

use Doctrine\ORM\Mapping as ORM;
use Plugin\StripeRec\Entity\StripeRecOrderItem;
/**
 * StripeRecOrder
 * 
 * @ORM\Table(name="plg_stripe_rec_order")
 * @ORM\Entity(repositoryClass="Plugin\StripeRec\Repository\StripeRecOrderRepository")
 */
class StripeRecOrder{
   
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", length=11, options={"unsigned":true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var Order
     *
     * @ORM\ManyToOne(targetEntity="Eccube\Entity\Order")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="order_id", referencedColumnName="id")
     * })
     */
    private $Order;

    /**
     * @var string
     * 
     * @ORM\Column(name="subscription_id", type="text")
     */
    private $subscription_id;

    /**
     * @var string
     * 
     * @ORM\Column(name="create_date", type="datetimetz")
     */
    private $create_date;

    /**
     * @var string
     * 
     * @ORM\Column(name="current_period_end", type="datetimetz")
     */
    private $current_period_end;

    /**
     * @var string
     * 
     * @ORM\Column(name="customer_id", type="text")
     */
    private $stripe_customer_id;

    

    /**
     * @var string
     * 
     * @ORM\Column(name="rec_status", type="text")
     */
    private $rec_status;

    /**
     * @var string
     * @ORM\Column(name="price_id", type="text")
     */
    private $price_id;

    

    public function getId(){
        return $this->id;
    }

    public function setOrder($order){
        $this->Order = $order;
        return $this;
    }
    public function getOrder(){
        return $this->Order;
    }

    public function setSubscriptionId($subscription_id){
        $this->subscription_id = $subscription_id;
        return $this;
    }
    public function getSubscriptionid(){
        return $this->subscription_id;
    }

    public function setCreateDate($create_date){
        $this->create_date = $create_date;
        return $this;
    }
    public function getCreateDate(){
        return $this->create_date;
    }

    public function setCurrentPeriodEnd($current_period_end){
        $this->current_period_end = $current_period_end;
        return $this;
    }
    public function getCurrentPeriodEnd(){
        return $this->current_period_end;
    }

    public function setStripeCustomerId($stripe_customer_id){
        $this->stripe_customer_id = $stripe_customer_id;
        return $this;
    }
    public function getStripeCustomerId(){
        return $this->stripe_customer_id;
    }

    public function getPriceId(){
        return $this->price_id;
    }
    public function setPriceId($price_id){
        $this->price_id = $price_id;
        return $this;
    }
    public function getRecStatus(){
        return $this->rec_status;
    }
    public function setRecStatus($rec_status){
        $this->rec_status = $rec_status;
        return $this;
    }

    public function copyFrom($subscription){
        $this->setSubscriptionId($subscription->id);

        $created = $subscription->created;
        $dt = new \DateTime();
        $dt->setTimestamp($created);
        $this->setCreateDate($dt);

        $period_end = $subscription->current_period_end;
        $dt1 = new \DateTime();
        $dt1->setTimestamp($period_end);
        $this->setCurrentPeriodEnd($dt1);
        $this->setStripeCustomerId($subscription->customer);
        $this->setRecStatus($subscription->status);
        
        $item = $subscription->items->data[0];
        
        $this->setPriceId($item->price);
        
    }
}