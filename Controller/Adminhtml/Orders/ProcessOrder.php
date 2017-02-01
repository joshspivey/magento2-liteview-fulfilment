<?php
namespace JoshSpivey\LiteView\Controller\Adminhtml\Orders;
use Liteview\Connection;
use SimpleXMLElement;

class ProcessOrder extends \Magento\Framework\App\Action\Action
{
    /** @var \Magento\Framework\View\Result\PageFactory  */
    protected $orderModel;
    protected $liteviewConneciton;
    protected $baseData = '<?xml version="1.0" encoding="UTF-8"?><toolkit></toolkit>';

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \JoshSpivey\LiteView\Model\Order $orderModel
    ){
        $this->orderModel = $orderModel;

        $this->liteviewConneciton = new Connection('user', 'key');

        parent::__construct($context);
    }

    public function execute()
    {

        header('Content-Type: application/xml');
        $orderArr = $this->postOrders();
        $xml = new SimpleXMLElement($this->baseData);
        echo $this->_objectManager->create('JoshSpivey\LiteView\Helper\DataHelper')->array_to_xml($orderArr, $xml);
        // echo $this->_objectManager->create('JoshSpivey\LiteView\Helper\DataHelper')->convert_array_to_xml($this->postOrders());

    }

    public function postOrders()
    {
      $orderId = $this->getRequest()->getParam('order_id');
      return $this->orderModel->getOrderData($orderId);
    }
}