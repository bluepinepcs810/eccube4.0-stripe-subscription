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

use Eccube\Annotation\EntityExtension;
use Doctrine\ORM\Mapping as ORM;

use Eccube\Entity\ProductClass;

/**
 * @EntityExtension("Eccube\Entity\ProductClass")
 */
trait ProductClassTrait
{
    /**
     * @var string
     * @ORM\Column(name="stripe_price_id", type="string", length=255, nullable=true)
     */
    private $stripe_price_id;

    public function setStripePriceId($stripe_price_id){
        $this->stripe_price_id = $stripe_price_id;
        return $this;
    }
    public function getStripePriceId(){
        return $this->stripe_price_id;
    }
    

    private $register_flg = 'none';
    
    public function getRegisterFlg(){
        if ($this->stripe_price_id) {            
            return $this->stripe_price_id;
        }
        return $this->register_flg;
    }
    public function setRegisterFlg($register_flg){
        $this->register_flg = $register_flg;
        return $this;
    }

    public function isRegistered(){
        if ($this->stripe_price_id) {            
            return true;
        }
        return false;
    }
}