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
use Eccube\Annotation\EntityExtension;

/**
 * @EntityExtension("Eccube\Entity\Order")
 */
trait OrderTrait
{
    public function hasStripePriceId(){
        $order_items = $this->getProductOrderItems();
        $product_class = $order_items[0]->getProductClass();
        return $product_class->isRegistered();
    }
    // public function isSetRecurring()
    // {
    //     $order_items = $this->getProductOrderItems();
    //     $product = $order_items[0]->getProduct();

    //     return $product->hasStripePriceId();
    // }
    public function getFullKana(){
        return $this->kana01 . $this->kana02;
    }
}
