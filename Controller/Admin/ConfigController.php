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


namespace Plugin\StripeRec\Controller\Admin;

use Eccube\Controller\AbstractController;
use Plugin\StripePaymentGateway\Form\Type\Admin\StripeConfigType;
use Plugin\StripePaymentGateway\Repository\StripeConfigRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Plugin\StripeRec\Form\Type\Admin\StripeRecConfigType;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Plugin\StripeRec\Service\Admin\ConfigService;

class ConfigController extends AbstractController
{
    /**
     * @var ContainerInterface
     */
    protected $container;
    /**
     * @var StripeConfigRepository
     */
    protected $stripeConfigRepository;
    /**
     * ConfigController constructor.
     *
     * @param StripeConfigRepository $stripeConfigRepository
     */
    public function __construct(
        ContainerInterface $container,
        StripeConfigRepository $stripeConfigRepository)
    {
        $this->container = $container;
        $this->stripeConfigRepository = $stripeConfigRepository;
    }

    /**
     * @Route("/%eccube_admin_route%/stripe_rec/config", name="stripe_rec_admin_config")
     * @Template("@StripeRec/admin/stripe_config.twig")
     */
    public function index(Request $request)
    {
        $config_service = $this->get("plg_stripe_rec.service.admin.plugin.config");
        
        $config_data = $config_service->getConfig();

        $form = $this->createForm(StripeRecConfigType::class, $config_data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $new_data = $form->getData();

            $config_service->saveConfig($new_data);

            $this->addSuccess('stripe_payment_gateway.admin.save.success', 'admin');

            return $this->redirectToRoute('stripe_rec_admin_config');
        }

        return [
            'form' => $form->createView(),
        ];
    }
}