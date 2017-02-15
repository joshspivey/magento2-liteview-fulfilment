<?php
namespace JoshSpivey\LiteView\Cron;
class TrackingCron {
 
    protected $_logger;
    protected $_responseFactory;
    protected $_url;
 
    public function __construct(\Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\ResponseFactory $responseFactory,
        \Magento\Framework\UrlInterface $url

    	) {
        $this->_logger = $logger;
        $this->_responseFactory = $responseFactory;
        $this->_url = $url;
    }
 
    public function updateTracking() {
        $this->_logger->info("Liteview Cron Update Tracking");
        $redirectUrl = $this->_url->getUrl('LiteView/Orders/ProcessOrder/action/updatetracking');
        $this->_responseFactory->create()->setRedirect($redirectUrl)->sendResponse();
        return $this;
    }

    public function orderStatus() {
        $this->_logger->info("Liteview Cron Order Status");
        $redirectUrl = $this->_url->getUrl('LiteView/Orders/ProcessOrder/action/orderstatus');
        $this->_responseFactory->create()->setRedirect($redirectUrl)->sendResponse();
        return $this;
    }
}