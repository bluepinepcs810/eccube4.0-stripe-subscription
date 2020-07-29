<?php

namespace Plugin\StripeRec\Controller\Admin;

use Eccube\Controller\Admin\Product\ProductClassController;
use Symfony\Component\HttpFoundation\Request;
use Eccube\Util\CacheUtil;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Eccube\Entity\ProductClass;
use Eccube\Entity\ProductStock;
use Eccube\Entity\Product;
use Eccube\Repository\BaseInfoRepository;
use Eccube\Repository\ClassCategoryRepository;
use Eccube\Repository\ProductClassRepository;
use Eccube\Repository\ProductRepository;
use Eccube\Repository\TaxRuleRepository;
use Plugin\StripeRec\Entity\StripeRecOrder;

class ProductClassExController extends ProductClassController{

    protected $container;
    protected $err_msg = "";

    /**
     * ProductClassController constructor.
     *
     * @param ProductClassRepository $productClassRepository
     * @param ClassCategoryRepository $classCategoryRepository
     */
    public function __construct(
        ProductRepository $productRepository,
        ProductClassRepository $productClassRepository,
        ClassCategoryRepository $classCategoryRepository,
        BaseInfoRepository $baseInfoRepository,
        TaxRuleRepository $taxRuleRepository,
        ContainerInterface $container
    ) {
        $this->container = $container;
        parent::__construct($productRepository, $productClassRepository, $classCategoryRepository, $baseInfoRepository, $taxRuleRepository);        
    }

    /**
     * 商品規格が登録されていなければ新規登録, 登録されていれば更新画面を表示する
     *
     * @Route("/%eccube_admin_route%/product/product/class/{id}", requirements={"id" = "\d+"}, name="admin_product_product_class")
     * @Template("@StripeRec/admin/product_class_edit.twig")
     */
    public function index(Request $request, $id, CacheUtil $cacheUtil)
    {
        
        $Product = $this->findProduct($id);
        if (!$Product) {
            throw new NotFoundHttpException();
        }

        $ClassName1 = null;
        $ClassName2 = null;

        if ($Product->hasProductClass()) {
            // 規格ありの商品は編集画面を表示する.
            $ProductClasses = $Product->getProductClasses()
                ->filter(function ($pc) {
                    return $pc->getClassCategory1() !== null;
                });

            // 設定されている規格名1, 2を取得(商品規格の規格分類には必ず同じ値がセットされている)
            $FirstProductClass = $ProductClasses->first();
            $ClassName1 = $FirstProductClass->getClassCategory1()->getClassName();
            $ClassCategory2 = $FirstProductClass->getClassCategory2();
            $ClassName2 = $ClassCategory2 ? $ClassCategory2->getClassName() : null;

            // 規格名1/2から組み合わせを生成し, DBから取得した商品規格とマージする.
            $ProductClasses = $this->mergeProductClasses(
                $this->createProductClasses($ClassName1, $ClassName2),
                $ProductClasses);

            // 組み合わせのフォームを生成する.
            $form = $this->createMatrixForm($ProductClasses, $ClassName1, $ClassName2,
                ['product_classes_exist' => true]);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                // フォームではtokenを無効化しているのでここで確認する.
                $this->isTokenValid();
                
                $this->saveProductClasses($Product, $form['product_classes']->getData());
                if(empty($this->err_msg)){
                    $this->addSuccess('admin.common.save_complete', 'admin');
                }


                $cacheUtil->clearDoctrineCache();

                if ($request->get('return_product_list')) {
                    return $this->redirectToRoute('admin_product_product_class', ['id' => $Product->getId(), 'return_product_list' => true]);
                }

                return $this->redirectToRoute('admin_product_product_class', ['id' => $Product->getId()]);
            }
        } else {
            // 規格なし商品
            $form = $this->createMatrixForm();
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                // フォームではtokenを無効化しているのでここで確認する.
                $this->isTokenValid();

                // 登録,更新ボタンが押下されたかどうか.
                $isSave = $form['save']->isClicked();

                // 規格名1/2から商品規格の組み合わせを生成する.
                $ClassName1 = $form['class_name1']->getData();
                $ClassName2 = $form['class_name2']->getData();
                $ProductClasses = $this->createProductClasses($ClassName1, $ClassName2);

                // 組み合わせのフォームを生成する.
                // class_name1, class_name2が取得できるのがsubmit後のため, フォームを再生成して組み合わせ部分を構築している
                // submit後だと, フォーム項目の追加やデータ変更が許可されないため.
                $form = $this->createMatrixForm($ProductClasses, $ClassName1, $ClassName2,
                    ['product_classes_exist' => true]);

                // 登録ボタン押下時
                if ($isSave) {
                    $form->handleRequest($request);
                    if ($form->isSubmitted() && $form->isValid()) {       
                        $this->saveProductClasses($Product, $form['product_classes']->getData());

                        if(empty($this->err_msg)){
                            $this->addSuccess('admin.common.save_complete', 'admin');
                        }

                        $cacheUtil->clearDoctrineCache();

                        if ($request->get('return_product_list')) {
                            return $this->redirectToRoute('admin_product_product_class', ['id' => $Product->getId(), 'return_product_list' => true]);
                        }

                        return $this->redirectToRoute('admin_product_product_class', ['id' => $Product->getId()]);
                    }
                }
            }
        }

        return [
            'Product' => $Product,
            'form' => $form->createView(),
            'clearForm' => $this->createForm(FormType::class)->createView(),
            'ClassName1' => $ClassName1,
            'ClassName2' => $ClassName2,
            'return_product_list' => $request->get('return_product_list') ? true : false,
        ];
    }

    /**
     * 商品規格を登録, 更新する.
     *
     * @param Product $Product
     * @param array|ProductClass[] $ProductClasses
     */
    protected function saveProductClasses(Product $Product, $ProductClasses = [])
    {
        
        foreach ($ProductClasses as $pc) {            
            // 新規登録時、チェックを入れていなければ更新しない            
            if (!$pc->getId() && !$pc->isVisible()) {
                continue;
            }                        
                        
            $stripe_register_flg = false;
            // 無効から有効にした場合は, 過去の登録情報を検索.
            if (!$pc->getId()) {
                /** @var ProductClass $ExistsProductClass */
                $ExistsProductClass = $this->productClassRepository->findOneBy([
                    'Product' => $Product,
                    'ClassCategory1' => $pc->getClassCategory1(),
                    'ClassCategory2' => $pc->getClassCategory2(),
                ]);

                
                // 過去の登録情報があればその情報を復旧する.
                if ($ExistsProductClass) {
                    
                    $stripe_register_flg = $this->checkPriceChange($pc, $ExistsProductClass);
                    $ExistsProductClass->copyProperties($pc, [
                        'id',
                        'price01_inc_tax',
                        'price02_inc_tax',
                        'create_date',
                        'update_date',
                        'Creator',
                    ]);
                    $pc = $ExistsProductClass;
                }else{
                    $stripe_register_flg = $this->checkPriceChange($pc);
                }
            }else{                
                $ExistsProductClass = $this->productClassRepository->findOneBy([
                    'id'    =>  $pc->getId()
                ]);
                if ($ExistsProductClass) {                                        
                    $stripe_register_flg = $this->checkPriceChange($pc, $ExistsProductClass);
                }else{
                    $stripe_register_flg = $this->checkPriceChange($pc);
                }
            }


            // 更新時, チェックを外した場合はPOST内容を破棄してvisibleのみ更新する.
            if ($pc->getId() && !$pc->isVisible()) {
                $this->entityManager->refresh($pc);
                $pc->setVisible(false);
                continue;
            }
            $pc->setProduct($Product);
            if($stripe_register_flg){     
                if($stripe_register_flg === 'update'){
                    $pc_new = $this->updateProductClass($pc);
                    if(empty($pc_new)){
                        $this->addError("stripe_rec.admin.stripe_price.update_err", 'admin');
                        $this->err_msg = true;
                    }else{
                        $pc = $pc_new;
                    }
                }else{                    
                    $pc_new = $this->registerProductClass($pc, $pc->getRegisterFlg());
                    if(empty($pc_new)){
                        $this->addError("stripe_rec.admin.stripe_price.register_err", 'admin');
                    }else{
                        $pc = $pc_new;
                    }
                }
            }
            
            $this->entityManager->persist($pc);            

            // 在庫の更新
            $ProductStock = $pc->getProductStock();
            if (!$ProductStock) {
                $ProductStock = new ProductStock();
                $ProductStock->setProductClass($pc);
                $this->entityManager->persist($ProductStock);
            }
            $ProductStock->setStock($pc->isStockUnlimited() ? null : $pc->getStock());

            if ($this->baseInfoRepository->get()->isOptionProductTaxRule()) {
                $rate = $pc->getTaxRate();
                $TaxRule = $pc->getTaxRule();
                if (is_numeric($rate)) {
                    if ($TaxRule) {
                        $TaxRule->setTaxRate($rate);
                    } else {
                        // 現在の税率設定の計算方法を設定する
                        $TaxRule = $this->taxRuleRepository->newTaxRule();
                        $TaxRule->setProduct($Product);
                        $TaxRule->setProductClass($pc);
                        $TaxRule->setTaxRate($rate);
                        $TaxRule->setApplyDate(new \DateTime());
                        $this->entityManager->persist($TaxRule);
                    }
                } else {
                    if ($TaxRule) {
                        $this->taxRuleRepository->delete($TaxRule);
                        $pc->setTaxRule(null);
                    }
                }
            }
        }
        

        // デフォルト規格を非表示にする.
        $DefaultProductClass = $this->productClassRepository->findOneBy([
            'Product' => $Product,
            'ClassCategory1' => null,
            'ClassCategory2' => null,
        ]);
        $DefaultProductClass->setVisible(false);

        $this->entityManager->flush();
        return true;
    }

    
    /**
     * 商品規格が登録されていなければ新規登録, 登録されていれば更新画面を表示する
     *
     * @Route("/%eccube_admin_route%/product/product/{id}/stripe_register", requirements={"id" = "\d+"}, name="stripe_rec_product_stripe_register")     
     */
    public function registerProduct($id){
        
        $Product = $this->findProduct($id);
        if (!$Product) {
            return $this->json(['result' => false]);
        }
        $stripe_service = $this->container->get('plg_stripe_rec.service.stripe_service');
        $res = $stripe_service->registerProduct($Product);
        return $this->json(['result'    => $res ]);
    }

    public function registerProductClass($prod_class, $interval){
        
        $stripe_service = $this->container->get('plg_stripe_rec.service.stripe_service');
        return $stripe_service->registerPrice($prod_class, $interval);
    }    
    public function updateProductClass($prod_class){
        $stripe_service = $this->container->get('plg_stripe_rec.service.stripe_service');

        $price_id = $prod_class->getStripePriceId();
        $rec_order_repo = $this->entityManager->getRepository(StripeRecOrder::class);
        $rec_orders = $rec_order_repo->findBy(['price_id' => $price_id]);
        
        $new_pc = $stripe_service->updatePrice($prod_class);
        if(empty($new_pc)){
            return false;
        }

        foreach($rec_orders as $rec_order){
            $res = $stripe_service->updateSubscription($rec_order->getSubscriptionid(), $new_pc->getStripePriceId());            
            if(empty($res)){
                return false;
            }
            $rec_order->setSubscriptionId($res);
            $rec_order->setPriceId($new_pc->getStripePriceId());
            $this->entityManager->persist($rec_order);
        }
        return $new_pc;
    }

    public function checkPriceChange($new_pc, $old_pc = null){
        $register_flg = $new_pc->getRegisterFlg();        

        $interval_arr = [
            'day', 'month', 'quarter', 'semiannual','year'
        ];

        if($old_pc && $old_pc->isRegistered()){
            if($new_pc->getPrice02() != $old_pc->getPrice02()){
                return 'update';
            }
            $connection = $this->entityManager->getConnection();
            $statement = $connection->prepare('select price02 from dtb_product_class where id = :id');
            $statement->bindValue('id', $new_pc->getId());
            $statement->execute();
            $pcs = $statement->fetchAll();

            if(!empty($pcs[0]['price02'])){
                if($new_pc->getPrice02() != $pcs[0]['price02']){
                    return 'update';
                }else{
                    return false;
                }
            }
        }
        
        if(empty($register_flg) || !in_array($register_flg, $interval_arr)){
            return false;
        }
        return true;
    }
}