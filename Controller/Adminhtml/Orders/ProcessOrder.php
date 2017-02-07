<?php
namespace JoshSpivey\LiteView\Controller\Adminhtml\Orders;
use Liteview\Connection;
use SimpleXMLElement;

class ProcessOrder extends \Magento\Framework\App\Action\Action
{
    /** @var \Magento\Framework\View\Result\PageFactory  */
    protected $orderModel;
    protected $liteviewConnection;
    protected $adminConfigModel;
    protected $baseData = '<?xml version="1.0" encoding="UTF-8"?><toolkit></toolkit>';

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \JoshSpivey\LiteView\Model\Order $orderModel,
        \JoshSpivey\LiteView\Model\AdminConfig $adminConfigModel
    ){
        $this->orderModel = $orderModel;
        $this->adminConfigModel = $adminConfigModel;

        if($this->adminConfigModel->getTestEnabled()){
            $this->liteviewConnection = new Connection(
                    $this->adminConfigModel->getDevUser(), 
                    $this->adminConfigModel->getDevApiKey()
                );
        }else{
            $this->liteviewConnection = new Connection(
                    $this->adminConfigModel->getProdUser(), 
                    $this->adminConfigModel->getProdApiKey()
                );
        }
        
        parent::__construct($context);
    }

    public function execute()
    {

        header('Content-Type: application/xml');
        $orderId = $this->getRequest()->getParam('order_id');
        echo $this->getOrderXml($orderId, 'order');
    }

    public function getOrderXml($orderId, $dataType){
        
        $orderArr = [];
        if($dataType == "order"){
           $orderArr = $this->orderModel->setOrder($orderId)->getOrderData();
        }
        if($dataType == "cancel"){
            $orderArr = $this->orderModel->setOrder($orderId)->getOrderCancelData();
        }

        $xml = new SimpleXMLElement($this->baseData);

        return $this->_objectManager->create('JoshSpivey\LiteView\Helper\DataHelper')->array_to_xml($orderArr, $xml);
    }

    public function postOrder()
    {
          
        if($this->orderModel->setOrder($orderId)->validateOrder() == true){
            return;
        }
        $this->sendToLiteView($this->getOrderXml($orderId, 'order'));
    }

    public function massSendToWarehouseAction()
    {
        $orderIds = $this->getRequest()->getParam('order_ids');

        foreach($orderIds as $orderId){
            if($this->orderModel->setOrder($orderId)->validateOrder() == true){
                continue;
            }
            $this->sendToLiteView($this->getOrderXml($orderId, 'order'));
        }

        $this->_redirect($this->_redirect->getRefererUrl());
    }

    private function sendToLiteView($orderXml){

        $orderResponse = $this->liteviewConnection->get('order/methods');
        $this->liteviewConnection->post('order/submit', $orderXml);
        $orderId = $this->getRequest()->getParam('order_id');
        $xml = new SimpleXMLElement($orderResponse);
        $error = $xml->error;
        $warnings = $xml->warnings;
        $liteViewNumber = $xml->submit_order->order_information->order_details->ifs_order_number;
        $this->orderModel->setOrder($orderId)->changeLiteViewStatus($error, $warnings, $liteViewNumber);
        
    }
}