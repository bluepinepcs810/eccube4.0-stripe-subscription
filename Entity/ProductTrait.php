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

use Eccube\Entity\Product;

/**
 * @EntityExtension("Eccube\Entity\Product")
 */

trait ProductTrait
{

    /**
     * @var string
     * @ORM\Column(name="stripe_prod_id", type="string", length=255, nullable=true)
     */
    private $stripe_prod_id;

    public function setStripeProdId($stripe_prod_id){
        $this->stripe_prod_id = $stripe_prod_id;
    }
    public function getStripeProdId(){
        return $this->stripe_prod_id;
    }
    public function isStripeProduct(){
        return !empty($this->stripe_prod_id);
    }

}