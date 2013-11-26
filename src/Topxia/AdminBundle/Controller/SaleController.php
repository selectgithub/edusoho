<?php
namespace Topxia\AdminBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Topxia\Common\Paginator;
use Topxia\Common\ArrayToolkit;

class SaleController extends BaseController
{

	public function indexAction(Request $request){

		$conditions = $request->query->all();

        $count = $this->getOffsaleService()->searchOffsaleCount($conditions);

        $paginator = new Paginator($this->get('request'), $count, 20);

        $offsales = $this->getOffsaleService()->searchOffsales($conditions,'latest', $paginator->getOffsetCount(),  $paginator->getPerPageCount());

        return $this->render('TopxiaAdminBundle:Sale:index.html.twig', array(
            'conditions' => $conditions,
            'offsales' => $offsales ,  
            'paginator' => $paginator
        ));

	}

    public function createAction(Request $request)
    {
        if('POST' == $request->getMethod()){
            $offsetting = $request->request->all();

            $this->getOffsaleService()->createOffsales($offsetting);
            
            return $this->redirect($this->generateUrl('admin_sale')); 
        }

        $offsetting = array(
            'id'=>0,
            'promoName'=>'',
            'reducePrice'=>0,
            'promoNum'=>1,
            'promoPrefix'=>'',
            'prodType'=>'课程',
            'prodId'=>'',
            'strvalidTime'=>'',
            'reuse'=>'不可以'
              );

        return $this->render('TopxiaAdminBundle:Sale:offsale-modal.html.twig',array('offsetting' => $offsetting));
    }

    public function prodCheckAction(Request $request)
    {
        $offsetting =  $request->request->all();

        $result = $this->getOffsaleService()->checkProd($offsetting);

        if ("success" == $result) {
            $response = array('success' => true, 'message' => $result);
        } else {
            $response = array('success' => false, 'message' => $result);
        }
         
        return $this->createJsonResponse($response);
    }

    public function batchDeleteAction(Request $request)
    {
        $ids = $request->request->get('ids', array());
        $this->getOffsaleService()->deleteOffsales($ids);

        return $this->createJsonResponse(true);
    }

   

    private function getOffsaleService()
    {
        return $this->getServiceKernel()->createService('Sale.OffsaleService');
    }
}