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
use Eccube\Annotation\EntityExtension;

/**
 * @EntityExtension("Eccube\Entity\Order")
 */
trait OrderTrait
{
    public function isSetRecurring()
    {
        $order_items = $this->getProductOrderItems();
        $product = $order_items[0]->getProduct();

        return $product->isSetRecurring();
    }
}
