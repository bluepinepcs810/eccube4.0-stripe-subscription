<?php
/*
* Plugin Name : StripeRec
*
* Copyright (C) 2020 Subspire. All Rights Reserved.
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/


namespace Plugin\StripeRec\Controller\Admin;

use Eccube\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Plugin\StripeRec\Service\Admin\ConfigService;
use Plugin\StripeRec\Form\Type\Admin\StripeRecSearchType;
use Eccube\Repository\Master\PageMaxRepository;
use Knp\Component\Pager\PaginatorInterface;
use Eccube\Util\FormUtil;
use Plugin\StripeRec\Repository\StripeRecOrderRepository;
use Plugin\StripeRec\Entity\StripeRecOrder;


class StripeRecOrderController extends AbstractController
{
    protected $container;

    /**
     * @var PageMaxRepository
     */
    protected $pageMaxRepository;

    /**
     * @var StripeRecOrderRepository
     */    
    protected $stripe_rec_repo;
    protected $em;

    public function __construct(
        ContainerInterface $container,
        PageMaxRepository $pageMaxRepository,
        StripeRecOrderRepository $stripe_rec_repo
    ){
        $this->container = $container;
        $this->pageMaxRepository = $pageMaxRepository;
        $this->stripe_rec_repo = $stripe_rec_repo;
        $this->em = $container->get('doctrine.orm.entity_manager');
    }

    /**
     * Recurring Order screen.
     *     
     * @Route("/%eccube_admin_route%/plugin/striperec/order", name="admin_striperec_order")
     * @Route("/%eccube_admin_route%/plugin/striperec/order/page/{page_no}", requirements={"page_no" = "\d+"}, name="striperec_order_page")
     * @Template("@StripeRec/admin/rec_order.twig")
     */
    public function index(Request $request, $page_no = null, PaginatorInterface $paginator)
    {
        $builder = $this->formFactory
            ->createBuilder(StripeRecSearchType::class);

        $searchForm = $builder->getForm();

        $page_count = $this->session->get('plugin.striperec.order.pagecount',
            $this->eccubeConfig->get('eccube_default_page_count'));

        $page_count_param = (int) $request->get('page_count');
        $pageMaxis = $this->pageMaxRepository->findAll();

        if ($page_count_param) {
            foreach ($pageMaxis as $pageMax) {
                if ($page_count_param == $pageMax->getName()) {
                    $page_count = $pageMax->getName();
                    $this->session->set('plugin.striperec.order.pagecount', $page_count);
                    break;
                }
            }
        }

        if ('POST' === $request->getMethod()) {
            $searchForm->handleRequest($request);

            if ($searchForm->isValid()) {
                /**
                 * 検索が実行された場合は, セッションに検索条件を保存する.
                 * ページ番号は最初のページ番号に初期化する.
                 */
                $page_no = 1;
                $searchData = $searchForm->getData();

                // 検索条件, ページ番号をセッションに保持.
                $this->session->set('plugin.striperec.order.search', FormUtil::getViewData($searchForm));
                $this->session->set('plugin.striperec.order.search.page_no', $page_no);
            } else {
                // 検索エラーの際は, 詳細検索枠を開いてエラー表示する.
                return [
                    'searchForm' => $searchForm->createView(),
                    'pagination' => [],
                    'pageMaxis' => $pageMaxis,
                    'page_no' => $page_no,
                    'page_count' => $page_count,
                    'has_errors' => true,
                ];
            }
        } else {
            if (null !== $page_no || $request->get('resume')) {
                /*
                 * ページ送りの場合または、他画面から戻ってきた場合は, セッションから検索条件を復旧する.
                 */
                if ($page_no) {
                    // ページ送りで遷移した場合.
                    $this->session->set('plugin.striperec.order.search.page_no', (int) $page_no);
                } else {
                    // 他画面から遷移した場合.
                    $page_no = $this->session->get('plugin.striperec.order.search.page_no', 1);
                }
                $viewData = $this->session->get('plugin.striperec.order.search', []);
                $searchData = FormUtil::submitAndGetData($searchForm, $viewData);
            } else {
                /**
                 * 初期表示の場合.
                 */
                $page_no = 1;
                $viewData = [];

                if ($statusId = (int) $request->get('order_status_id')) {
                    $viewData = ['status' => $statusId];
                }

                $searchData = FormUtil::submitAndGetData($searchForm, $viewData);

                // セッション中の検索条件, ページ番号を初期化.
                $this->session->set('plugin.striperec.order.search', $viewData);
                $this->session->set('plugin.striperec.order.search.page_no', $page_no);
            }
        }



        $qb = $this->stripe_rec_repo->getQueryBuilderBySearchDataForAdmin($searchData);

        $pagination = $paginator->paginate(
            $qb,
            $page_no,
            $page_count
        );

        return [
            'searchForm' => $searchForm->createView(),
            'pagination' => $pagination,
            'pageMaxis' => $pageMaxis,
            'page_no' => $page_no,
            'page_count' => $page_count,
            'has_errors' => false
        ];
    }

    /**
     * Admin rec_order_history
     * @Route("/%eccube_admin_route%/plugin/striperec/order/{id}/stop", name="admin_striperec_order_stop")     
     */
    public function cancelSubscription(Request $request, $id=null){
        
        $rec_order = $this->stripe_rec_repo->findOneBy([
            "id" => $id,  
            "rec_status" => StripeRecOrder::REC_STATUS_ACTIVE
        ]);
        if(!empty($rec_order)){
            $util = $this->container->get('plg_stripe_rec.service.util');
            $res = $util->cancelRecurring($rec_order);
            if(!empty($res)){
                $rec_order->setRecStatus(StripeRecOrder::REC_STATUS_CANCELED);
                $this->em->persist($rec_order);
                $this->em->flush();
            }
        }        
        return $this->redirectToRoute("admin_striperec_order");

    }

    
}