<?php
/*
* Plugin Name : StripePaymentGateway
*
* Copyright (C) 2018 Subspire Inc. All Rights Reserved.
* http://www.subspire.co.jp/
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Plugin\StripeRec;

use Eccube\Common\EccubeNav;

class StripeRecNav implements EccubeNav
{
    /**
     * @return array
     */
    public static function getNav()
    {
        return [
            'stripe' => [                
                'name' => 'stripe_rec.admin.nav.name',
                'icon' => 'fa-cc-stripe',
                'children' => [
                    'stripe_rec_mng' => [
                        'name' => 'stripe_recurring.admin.nav.rec_order',
                        'url' => 'admin_striperec_order',
                    ],
                    'stripe_rec_config' =>  [
                        'name'  =>  'stripe_recurring.admin.nav.rec_config',
                        'url'   =>  'stripe_rec_admin_config'
                    ]
                ],
            ],
        ];
    }
}