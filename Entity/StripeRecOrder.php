<?php

/*
* Plugin Name : StripeRec
*
* Copyright (C) 2020 Subspire. All Rights Reserved.
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Plugin\StripeRec\Entity;

use Doctrine\ORM\Mapping as ORM;
use Plugin\StripeRec\Entity\StripeRecOrderItem;
use Eccube\Entity\Customer;
/**
 * StripeRecOrder
 * 
 * @ORM\Table(name="plg_stripe_rec_order")
 * @ORM\Entity(repositoryClass="Plugin\StripeRec\Repository\StripeRecOrderRepository")
 */
class StripeRecOrder{
   

    const STATUS_PAY_UPCOMING = "upcoming";
    const STATUS_PAID = "paid";
    const STATUS_PAY_FAILED = "pay_failed";
    const STATUS_PAY_UNDEFINED = "undefined";

    const REC_STATUS_ACTIVE = "active";
    const REC_STATUS_CANCELED = "canceled";

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
     *   @ORM\JoinColumn(name="order_id", referencedColumnName="id", nullable=true)
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
     * @ORM\Column(name="create_date", type="datetimetz", nullable=true)
     */
    private $create_date;

    /**
     * @var string
     * @ORM\Column(name="current_period_start", type="datetimetz", nullable=true)
     */
    private $current_period_start;

    /**
     * @var string
     * 
     * @ORM\Column(name="current_period_end", type="datetimetz", nullable=true)
     */
    private $current_period_end;

    /**
     * @var string
     * 
     * @ORM\Column(name="customer_id", type="text")
     */
    private $stripe_customer_id;

    // /**
    //  * @var string
    //  * 
    //  * @ORM\Column(name="shop_customer_id", type="text", nullable=true)
    //  */
    /**
     * @var Customer
     *
     * @ORM\ManyToOne(targetEntity="Eccube\Entity\Customer")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="shop_customer_id", referencedColumnName="id", nullable=true)
     * })
     */
    private $Customer;

    

    /**
     * @var string
     * 
     * @ORM\Column(name="rec_status", type="text", nullable=true)
     */
    private $rec_status;

    /**
     * @var string
     * @ORM\Column(name="price_id", type="text")
     */
    private $price_id;

    /**
     * @var string
     * @ORM\Column(name="paid_status", type = "text", nullable=true)
     */
    private $paid_status;

    /**
     * @var string
     * 
     * @ORM\Column(name="last_payment_date", type="datetimetz", nullable=true)
     */
    private $last_payment_date;

    /**
     * @var integer
     * @ORM\Column(name="unit_amount", type="integer", nullable=true)
     */
    private $unit_amount;


    /**
     * @var integer
     * @ORM\Column(name="quantity", type="integer", nullable=true)
     */
    private $quantity;

    /**
     * @var string
     * @ORM\Column(name="interval_", type="text", nullable=true)
     */
    private $interval;

    public function setInterval($interval){
        $this->interval = $interval;
        return $this;
    }
    public function getInterval(){
        return $this->interval;
    }

    public function getCurrentPeriodStart(){
        return $this->current_period_start;
    }
    public function setCurrentPeriodStart($current_period_start){
        $this->current_period_start = $this->convertDateTime($current_period_start);
        return $this;
    }

    public function getCustomer(){
        return $this->Customer;
    }
    public function setCustomer($Customer){
        $this->Customer = $Customer;
        return $this;
    }

    public function getPaidStatus(){
        return $this->paid_status;
    }
    public function setPaidStatus($paid_status){
        $this->paid_status = $paid_status;
        return $this;
    }

    public function getLastPaymentDate(){
        return $this->last_payment_date;
    }
    public function setLastPaymentDate($last_payment_date){
        $this->last_payment_date = $this->convertDateTime($last_payment_date);
        return $this;
    }

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
        $this->create_date = $this->convertDateTime($create_date);
        return $this;
    }
    public function getCreateDate(){
        return $this->create_date;
    }

    public function setCurrentPeriodEnd($current_period_end){
        $this->current_period_end = $this->convertDateTime($current_period_end);
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

    public function getQuantity(){
        return $this->quantity;
    }
    public function setQuantity($quantity){
        $this->quantity = $quantity;
        return $this;
    }

    public function getUnitAmount(){
        return $this->unit_amount;
    }
    public function setUnitAmount($unit_amount){
        $this->unit_amount = $unit_amount;

    }

    public function convertDateTime($in){
        if(!($in instanceof \DateTime)){
            $dt1 = new \DateTime();
            $dt1->setTimestamp($in);
            return $dt1;
        }
        return $in;
    }

    public function getAmount(){
        if((!empty($this->unit_amount)) && (!empty($this->quantity))){
            return $this->unit_amount * $this->quantity;
        }
        return 0;
    }

    public function copyFrom($subscription, $Customer = null){
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
        
        $this->setPriceId($item->price->id);
        $this->setUnitAmount($item->price->unit_amount);
        $this->setQuantity($item->quantity);
        if(!empty($Customer)){
            $this->setCustomer($Customer);
        }
        $this->setInterval($item->plan->interval);
    }
    
}