<?php
namespace JoshSpivey\SalesGrid\Controller\Adminhtml\Orders;

class Order extends \Magento\Framework\App\Action\Action
{
    /** @var \Magento\Framework\View\Result\PageFactory  */
    protected $orderModel;
    protected $liteviewConneciton;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \JoshSpivey\LiteView\Model\Order $orderModel,
        \Liteview\Connection $liteviewConneciton
    ){
        $this->orderModel = $orderModel;
        $this->liteviewConneciton = $liteviewConneciton;

        parent::__construct($context);
    }

    public function execute()
    {

      header('Content-Type: application/xml');
      echo $this->postOrders();

    }

    public function postOrders()
    {
      $incrementId = $this->getRequest()->getParam('increment_id');
      return $this->orderModel->getData($incrementId);
    }
}