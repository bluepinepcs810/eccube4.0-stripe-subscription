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

namespace Plugin\StripeRec\Service\Admin;

use Symfony\Component\DependencyInjection\ContainerInterface;


class ConfigService{

    public const INVOICE_PAID_URL = "INVOICE_PAID_URL";
    public const INVOICE_PAY_FAILED = "INVOICE_PAY_FAILED";

    public function getRecProperties($key){
        $rec_props_path = $this->container->getParameter('plugin_realdir'). '/StripeRec/Resource/config/webhook.properties';
        
        $rec_props = file($rec_props_path);

        if ($rec_props === false) {
            return null;
        }
        foreach($rec_props as $k => $val){
            $temp_arr = explode("=", $val);
            if(count($temp_arr) == 1){
                continue;
            }
            if($temp_arr[0] == $key){
                return trim($temp_arr[1]);
            }
        }
        return null;
    }
    public function setRecProperties($key, $s_val){
        
        $rec_props_path = $this->container->getParameter('plugin_realdir'). '/StripeRec/Resource/config/webhook.properties';
        $rec_props = file($rec_props_path);
        
        if (empty($rec_props)) {            
            file_put_contents($rec_props_path, $key . "=" . $s_val . "\n");
            return true;            
        }

        $flag = false;
        foreach($rec_props as $k => $val){
            $temp_arr = explode("=", $val);
            if(count($temp_arr) < 2){
                continue;
            }
            if($temp_arr[0] == $key){
                $flag = true;
                $rec_props[$k] = $key . "=" . $s_val . "\n";
            break;
            }
        }
        if(!$flag){
            $rec_props[$k + 1] = $key . "=" . $s_val . "\n";
        }

        $str = join("", $rec_props);
        file_put_contents($rec_props_path, $str);
        return true;
    }
}