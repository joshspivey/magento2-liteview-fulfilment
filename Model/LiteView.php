<?php

namespace JoshSpivey\LiteView\Model;

use JoshSpivey\LiteView\Api\Data\LiteViewInterface;
use JoshSpivey\LiteView\Helper\ConfigHelper;
use Liteview\Connection;

class LiteView extends \Magento\Framework\Model\AbstractModel implements LiteViewInterface
{
    /** @var  ConfigHelper */
    protected $_config;
    protected $liteviewConnection;
    protected $adminConfigModel;
    protected $orderModel;

    public function __construct(
        ConfigHelper $config,
        \JoshSpivey\LiteView\Model\AdminConfig $adminConfigModel,
        \JoshSpivey\LiteView\Model\Order $orderModel
    )
    {
        $this->_config = $config;

        $this->adminConfigModel = $adminConfigModel;
        $this->orderModel = $orderModel;

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
    }

    public function getShippingMethods(){
        $orderResponse = $this->liteviewConnection->get('order/methods');
        $responseXml = simplexml_load_string($orderResponse->getBody()->getContents());

        return $responseXml->shipping_methods->shipping_method;
    }

    public function sendOrderToLiteView($orderXml, $orderId){
        $orderResponse = $this->liteviewConnection->post('order/submit', $orderXml);
        $responseXml = simplexml_load_string($orderResponse->getBody()->getContents());
        if(isset($responseXml->submit_order)){
            $liteViewNumber = $xml->submit_order->order_information->order_details->ifs_order_number;
        }else{
            $liteViewNumber = "";
        }
        $this->orderModel->setOrder($orderId)->changeLiteViewStatus($responseXml->error, $responseXml->warnings, $liteViewNumber);
        
    }

    public function sendCancelOrder($cancelOrderXml, $orderId){

    }


}