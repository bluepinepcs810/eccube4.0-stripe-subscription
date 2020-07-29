<?php

namespace Plugin\StripeRec\Form\Extension;

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Form\Type\Admin\ProductClassEditType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Eccube\Entity\ProductClass;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class ProductClassExtension extends AbstractTypeExtension
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;
    
    public function __construct(EntityManagerInterface $entityManager){    
        $this->entityManager = $entityManager;
    }
    
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options){
        $builder->add('register_flg', ChoiceType::class, [
            'required'  => false,
            // 'label'     =>  trans('stripe_recurring.admin.product_class.register_flg')
            'choices'   =>  [
                'None'              =>  'none',
                'day'       =>  'day',
                'monthly'   => 'month',
                'every 3 month'   =>  'quarter',
                'every 6 month'     =>  'semiannual',
                'Yearly'            =>  'year'
            ]
        ]);
        // $this->setPriceChange($builder);
    }
    /**
     * {@inheritdoc}
     */
    public function getExtendedType()
    {
        return ProductClassEditType::class;
    }
    protected function setPriceChange(FormBuilderInterface $builder){
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();

            $data = $event->getData();     
            if (!$data instanceof ProductClass) {
                return;
            }       
            $id = $data->getId();
            if(empty($id)){
                return;
            }
            $connection = $this->entityManager->getConnection();
            $statement = $connection->prepare('select price02 from dtb_product_class where id = :id');
            $statement->bindValue('id', $data->getId());
            $statement->execute();
            $pcs = $statement->fetchAll();
            echo "here" .$pcs[0]['price02']. "<br>".$data->getPrice02(); die();            
        });
    }
    // /**
    //  * 各行の登録チェックボックスの制御.
    //  *
    //  * @param FormBuilderInterface $builder
    //  */
    // protected function setRecId(FormBuilderInterface $builder)
    // {
        
    //     $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
    //         $data = $event->getData();            
    //         if (!$data instanceof ProductClass) {
    //             return;
    //         }
    //         if ($data->getId() && $data->getRecurringId()) {
    //             $form = $event->getForm();
    //             $form['register_flg']->setData($data->getRegisterFlg());
    //             if($data->getRegisterFlg()){
    //                 $options = $form['register_flg']->getOptions();
    //                 $options['attr']['disabled'] = true;
    //                 $builder->add('register_flg', CheckboxType::class, $options);
    //             }
    //         }
    //     });

    //     $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
    //         $form = $event->getForm();

    //         $data = $event->getData();
    //         $register_flg = $form['register_flg']->getData();
    //         if(!empty($register_flg)){
    //             $data->setRegisterFlg($register_flg);
    //         }            
    //     });
    // }
}