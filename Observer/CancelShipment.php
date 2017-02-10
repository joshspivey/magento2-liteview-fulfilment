<?php
namespace JoshSpivey\LiteView\Observer;
use \Magento\Framework\Event\Observer;
use \Magento\Framework\Event\ObserverInterface;

class CancelShipment implements ObserverInterface
{
    protected $_responseFactory;
    protected $_url;

    public function __construct(
        \Magento\Framework\App\ResponseFactory $responseFactory,
        \Magento\Framework\UrlInterface $url
    ) {
        $this->_responseFactory = $responseFactory;
        $this->_url = $url;
    }
    /**
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return $this
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $shipment = $observer->getEvent()->getShipment();
        $order = $shipment->getOrder();

        $redirectUrl = $this->_url->getUrl('LiteView/Orders/ProcessOrder/action/cancel/order_id/'.$order->getId());
        $this->_responseFactory->create()->setRedirect($redirectUrl)->sendResponse();

    }
}