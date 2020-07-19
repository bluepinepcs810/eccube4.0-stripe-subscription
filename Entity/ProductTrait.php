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
     *
     * @ORM\Column(name="recurring_id", type="string", length=255, nullable=true)
     */
    private $recurring_id;

    public function setRecurringId($recurring_id){
        $this->recurring_id = $recurring_id;
        return $this;
    }
    public function getRecurringId(){
        return $this->recurring_id;
    }

    public function isSetRecurring(){
        return !empty($this->recurring_id);
    }

}