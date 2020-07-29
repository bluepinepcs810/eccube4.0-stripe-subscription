<?php

/*
* Plugin Name : StripeRec
*
* Copyright (C) 2020 Subspire. All Rights Reserved.
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Plugin\StripeRec\Service\Admin;

use Symfony\Component\DependencyInjection\ContainerInterface;


class ConfigService{

    const WEBHOOK_SIGNATURE = "webhook_signature";

    //=======mail config=========
    const PAID_MAIL_NAME = "Stripe Subscription paid";
    const PAY_FAILED_MAIL_NAME = "Stripe Subscription payment failed";
    const PAY_UPCOMING = "Stripe Subsciption Payment Upcoming";
    const REC_CANCELED = "Stripe Subscription Canceled";

    //===========================


    /**
     * コンテナ
     */
    private $container;
    /**
     * コンストラクタ
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        
    }

    public function getConfig(){
        return [
            ConfigService::WEBHOOK_SIGNATURE =>  $this->get(ConfigService::WEBHOOK_SIGNATURE)
        ];
    }

    public function isMailerSetting(){
        $mailer_url = env("MAILER_URL", "none");
        return $mailer_url !== "none";
    }
    public function getSignature(){
        return $this->get(ConfigService::WEBHOOK_SIGNATURE);
    }
    public function saveConfig($new_data){
        $old_data = $this->getConfig();
        $diff_keys = [];
        foreach($old_data as $k => $v){
            if(!empty($new_data[$k]) && $new_data[$k] !== $old_data[$k]){
                $diff_keys[] = $k;
                $this->set($k, $new_data[$k]);
            }
        }
        return $diff_keys;
    }    
    public function get($key){
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
    public function set($key, $s_val){
        
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