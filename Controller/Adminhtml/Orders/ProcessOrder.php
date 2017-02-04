<?php
namespace JoshSpivey\LiteView\Controller\Adminhtml\Orders;
use Liteview\Connection;
use SimpleXMLElement;

class ProcessOrder extends \Magento\Framework\App\Action\Action
{
    /** @var \Magento\Framework\View\Result\PageFactory  */
    protected $orderModel;
    protected $liteviewConnection;
    protected $baseData = '<?xml version="1.0" encoding="UTF-8"?><toolkit></toolkit>';

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \JoshSpivey\LiteView\Model\Order $orderModel
    ){
        $this->orderModel = $orderModel;

        $this->liteviewConnection = new Connection('user', 'key');

        parent::__construct($context);
    }

    public function execute()
    {

        header('Content-Type: application/xml');
        $orderId = $this->getRequest()->getParam('order_id');
        echo $this->getOrderXml($orderId);
    }

    public function getOrderXml($orderId){
        
        $orderArr = $this->orderModel->setOrder($orderId)->getOrderData();
        $xml = new SimpleXMLElement($this->baseData);

        return $this->_objectManager->create('JoshSpivey\LiteView\Helper\DataHelper')->array_to_xml($orderArr, $xml);
    }

    public function postOrder()
    {
          
        if($this->orderModel->setOrder($orderId)->validateOrder() == true){
            break;
        }
        $this->sendToLiteView($this->getOrderXml());
    }

    public function massSendToWarehouseAction()
    {
        $orderIds = $this->getRequest()->getParam('order_ids');

        foreach($orderIds as $orderId){
            if($this->orderModel->setOrder($orderId)->validateOrder() == true){
                continue;
            }
            $this->sendToLiteView($this->getOrderXml($orderId));
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