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
}
