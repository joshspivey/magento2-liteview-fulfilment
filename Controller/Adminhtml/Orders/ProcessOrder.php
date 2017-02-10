<?php
namespace JoshSpivey\LiteView\Controller\Adminhtml\Orders;


class ProcessOrder extends \Magento\Framework\App\Action\Action
{
    /** @var \Magento\Framework\View\Result\PageFactory  */
    protected $orderModel;
    protected $liteViewModel;
    protected $orderId;
    protected $shipmentId;
    

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \JoshSpivey\LiteView\Model\Order $orderModel,
        \JoshSpivey\LiteView\Model\LiteView $liteViewModel
    ){
        $this->orderModel = $orderModel;
        $this->liteViewModel = $liteViewModel;
        
        parent::__construct($context);
    }

    public function execute()
    {
        // echo print_r($this->getRequest()->getParams());
        $action = $this->getRequest()->getParam('action');
        // echo $action;
        $this->orderId = $this->getRequest()->getParam('order_id');
        $this->shipmentId = $this->getRequest()->getParam('shipment_id');

        switch($action){
            case "send":
                $this->sendToWarehouseAction();
            break;
            case "masssend":
                $this->massSendToWarehouseAction();
            break;
            case "cancel":
                $this->cancelOrderAction();
            break;
            default:
                header('Content-Type: application/xml');
                echo $this->orderModel->getOrderXml($this->orderId, 'order');
            break;
        }
    }


    public function sendToWarehouseAction()
    {
        // echo 'test';die;
          
        if($this->orderModel->setOrder($this->orderId)->validateOrder() != true){
            return;
        }
        $this->liteViewModel->sendOrderToLiteView($this->orderModel->getOrderXml($this->orderId, 'order'), $this->orderId);
        $this->_redirect($this->_redirect->getRefererUrl());
    }

    public function massSendToWarehouseAction()
    {
        $orderIds = $this->getRequest()->getParam('order_ids');

        foreach($orderIds as $orderId){
            if($this->orderModel->setOrder($orderId)->validateOrder() != true){
                continue;
            }
            $this->liteViewModel->sendOrderToLiteView($this->orderModel->getOrderXml($orderId, 'order'), $orderId);
        }

        $this->_redirect($this->_redirect->getRefererUrl());
    }

    public function cancelOrderAction()
    {
        $this->liteViewModel->sendCancelOrder($this->orderModel->getOrderXml($this->orderId, 'cancel'), $this->orderId);
    }

}