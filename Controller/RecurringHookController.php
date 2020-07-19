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

namespace Plugin\StripeRec\Controller;

use Eccube\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

class RecurringHookController extends AbstractController{

    public function __construct(){
        
    }
    /**
     * @Route("/plugin/StripeRec/webhook", name="plugin_stripe_webhook")
     */
    public function webhook(Request $request){
        
        return $this->json(['status' => 'failed']);
    }

}