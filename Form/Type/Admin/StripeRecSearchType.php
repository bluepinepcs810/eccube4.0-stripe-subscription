<?php
/*
* Plugin Name : StripeRec
*
* Copyright (C) 2020 Subspire. All Rights Reserved.
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Plugin\StripeRec\Form\Type\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Eccube\Form\Type\ToggleSwitchType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Plugin\StripeRec\Entity\StripeRecOrder;

class StripeRecSearchType extends AbstractType
{    
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder            
            ->add('paid_status', ChoiceType::class, [
                'choices'   =>  [
                    StripeRecOrder::STATUS_PAID,
                    StripeRecOrder::STATUS_PAY_UPCOMING,
                    StripeRecOrder::STATUS_PAY_FAILED,
                    StripeRecOrder::STATUS_PAY_UNDEFINED
                ],
                'required'  => false,
                'expanded'  =>  true,
                'multiple'  =>  true
            ])
            ->add('rec_status', ChoiceType::class,[
                'choices'   =>  [
                    StripeRecOrder::REC_STATUS_ACTIVE,
                    StripeRecOrder::REC_STATUS_CANCELED
                ],
                'required'  =>  false,
                'expanded'  =>  true,
                'multiple'  =>  true
            ]);        
    }
}