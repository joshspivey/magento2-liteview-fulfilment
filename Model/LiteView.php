<?php

namespace JoshSpivey\LiteView\Model;

use JoshSpivey\LiteView\Api\Data\LiteViewInterface;
use JoshSpivey\LiteView\Helper\ConfigHelper;
use Liteview\Connection;
use SimpleXMLElement;

class LiteView extends \Magento\Framework\Model\AbstractModel implements LiteViewInterface
{

    const STATE_SENT_TO_WAREHOUSE   = 'lv_send_liteview';
    const STATE_WAREHOUSE_ERROR     = 'lv_send_error';

    /** @var  ConfigHelper */
    protected $_config;
    protected $liteviewConnection;
    protected $adminConfigModel;
    protected $orderModel;
    protected $searchCriteriaBuilder;
    protected $filterBuilder;
    protected $orderRepository;
    protected $logger;

    public function __construct(
        ConfigHelper $config,
        \Psr\Log\LoggerInterface $logger,
        \JoshSpivey\LiteView\Model\AdminConfig $adminConfigModel,
        \JoshSpivey\LiteView\Model\Order $orderModel,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Api\OrderItemRepositoryInterface $orderItemRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Framework\Api\Search\FilterGroup $filterGroup,
        \Magento\Framework\Api\FilterBuilder $filterBuilder
    )
    {
        $this->_config = $config;

        $this->adminConfigModel = $adminConfigModel;
        $this->orderModel = $orderModel;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;

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
        $orderResponse = $this->liteviewConnection->post('order/cancel', $cancelOrderXml);
        $responseXml = simplexml_load_string($orderResponse->getBody()->getContents());
    }


    public function checkLiteviewTracking() {   

        
        // Get all orders shipped orders from the last week 
        $fromDate = date('Y-m-d H:i:s', strtotime("-1 week"));
        $toDate = date('Y-m-d H:i:s', strtotime("now"));

        $filters[] = $this->filterBuilder->setField('updated_at')
          ->setValue($fromDate)
          ->setConditionType('from')
          ->create();
        $filters[] = $this->filterBuilder->setField('updated_at')
          ->setValue($toDate)
          ->setConditionType('to')
          ->create();

        $filters[] = $this->filterBuilder->setField('status')
          ->setValue('complete')
          ->create();

        $searchCriteria = $this->searchCriteriaBuilder
                        ->addFilters($filters)
                        ->setPageSize(1000)
                        ->setCurrentPage(1)
                        ->create();

        $warehouseOrders = $this->orderRepository->getList($searchCriteria); 
            
        foreach ($warehouseOrders as $order) {
            $orderTrackingNumbers = $order->getTracksCollection();
            $badTracking = null;
            $localNumber = null;
            //If this order already has a tracking number(s) associated with it, don't spend the effort of querying Liteview.
            if ($orderTrackingNumbers->count() > 0){
                foreach ($orderTrackingNumbers as $trackingObject){
                    $localNumber = $trackingObject->getData('number');
                    if ($localNumber === null){
                        $badTracking = $trackingObject;
                    }
                }
                if ($badTracking === null){
                    continue;
                }
            }
            
            $orderId = $order->getIncrementId();

            $shipResponse = $this->liteviewConnection->get('order/ship_status', null, array('order_id' => $orderId));
            $responseXml = simplexml_load_string($shipResponse->getBody()->getContents());
           
            if (isset($responseXml->error) && isset($responseXml->error->error_description)) 
            {
                continue;
            }
             // echo $badTracking.' | '.$localNumber.": ".var_dump($responseXml)." :";die; 
            $trackingNumbers = $responseXml->order_status->order->tracking_numbers;
            if (isset($trackingNumbers) && $trackingNumbers->tracking_count > 0){
                $tracking = $trackingNumbers->shipment->tracking_number;
                
                $shipmentCarrierTitle = $order->getShippingDescription();
                
                //For now add the tracking code to all shipments, we may want to improve on this later
                if($badTracking === null){
                    foreach($order->getShipmentsCollection() as $shipment)
                    {
                        $data = array(
                            'carrier_code' => $order->getShippingCarrier()->getCarrierCode(),
                            'title' => isset($shipmentCarrierTitle) ? $shipmentCarrierTitle : $order->getShippingCarrier()->getConfigData('title'),
                            'number' => $tracking
                        );

                        $track = $this->trackFactory->create()->addData($data);
                        $shipment->addTrack($track)->save();

                        $message = 'Automated Liteview Message: Tracking Number Added: ' . $tracking;
                        $order->addStatusHistoryComment($message, false);
                        $order->save();
                    }
                }
                else
                {
                    $badTracking->setData('number', $tracking)->save();
                    
                    $message = 'Automated Liteview Message: Tracking Number Updated: ' . $tracking;
                    $order->addStatusHistoryComment($message, false);
                    $order->save();
                }
            }
        }
        
    }

    public function checkLiteviewOrderStatus() {    
        
        // Get all orders in sent to warehouse or warehouse on hold status 
        // Realize that you may not be able to ship or invoice an order in "hold" status so refinement may be needed in the future.

        $filters[] = $this->filterBuilder->setField('status')
            ->setValue(['lv_send_liteview', 'lv_send_error'])
            ->setConditionType('in')
            ->create();

        $searchCriteria = $this->searchCriteriaBuilder
                        ->addFilters($filters)
                        ->setPageSize(1000)
                        ->setCurrentPage(1)
                        ->create();

        $warehouseOrders = $this->orderRepository->getList($searchCriteria); 
        
        // echo "Liteview Total order count: " . count($warehouseOrders);die;
        $this->logger->info("Liteview Total order count: " . count($warehouseOrders));
        foreach ($warehouseOrders as $order) {
            $orderId = $order->getIncrementId();
            $this->logger->info("Liteview About to Query Order: " . $orderId);
            // echo "Liteview About to Query Order: " . $orderId;

            $shipResponse = $this->liteviewConnection->get('order/ship_status', null, array('order_id' => $orderId));
            $responseXml = simplexml_load_string($shipResponse->getBody()->getContents());

            if (isset($responseXml->error) && isset($responseXml->error->error_description)) 
            {
                echo $responseXml->error->error_description;
                continue;
            }

            try{
                $dispatchStatus = $responseXml->order_status->order->dispatch_status;
            }catch (Exception $e) {
                echo "Error: ".$orderId." | ".$e->getMessage();
                $dispatchStatus = "Error";
            }

            $hasShipped = ($dispatchStatus == "Shipped");

            $dateShipped = '';

            if($hasShipped){
                $dateShipped = $responseXml->order_status->order->dispatch_date;
            }
            else{
                // The order has not shipped, skip.
                // echo "Liteview - Skipping order as not shipped with status: " . $dispatchStatus;
                $this->logger->info("Liteview - Skipping order as not shipped with status: " . $dispatchStatus);
                continue;
            }
            
            $trackingNumbers = $responseXml->order_status->order->tracking_numbers;
            if (isset($trackingNumbers) && $trackingNumbers->tracking_count > 0){
                $tracking = $trackingNumbers->shipment->tracking_number;
                echo "Liteview - Tracking: " . $tracking;
                $this->logger->info("Liteview - Tracking: " . $tracking);
            }
            echo "canShip: ".$order->canShip();
            if($order->canInvoice() || $tracking != '') {
                // echo '1';
                try{
                    $this->logger->info("About to invoice");
                    $invoice = $this->_objectManager->create('Magento\Sales\Model\Service\InvoiceService')->prepareInvoice($order);

                    $paymentMethod = $order->getPayment()->getMethodInstance()->getCode();
                    $paymentTitle = $order->getPayment()->getMethodInstance()->getTitle();
                    $this->logger->info("Payment: ". $paymentMethod . " - " . $paymentTitle);
                    
                    $captureOffline = ($paymentMethod == "checkmo");  //Todo reference Magento constant if exists
                    
                    if ($captureOffline){
                        $this->logger->info("Capture Offline");
                        $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
                    }
                    else {
                        $this->logger->info("Capture Online");
                        $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
                    }
                    
                    $invoice->register();
                    $transactionSave = Mage::getModel('core/resource_transaction')
                                        ->addObject($invoice)
                                        ->addObject($invoice->getOrder());
 
                    $transactionSave->save();
                    $this->logger->info("Invoice Created");
                  
                    // Add a comment
                    $message = 'Automated Liteview Message: Order invoiced';
                    $order->addStatusHistoryComment($message, false);
                    $this->logger->info("Liteview - Order Invoiced");
                } 
                catch (Exception $e) {
                    echo "Liteview - Exception: " . $e->getMessage();
                    $this->logger->info("Liteview - Exception: " . $e->getMessage());
                    $subject = 'Liteview Cron: Invoice Exception for order: ' . $orderId;
                    $message = 'Liteview: An Exception occurred when invoicing order: ' . $orderId . '. Exception message: ' . $e->getMessage();
                    $order->addStatusHistoryComment($message, false);
                    $order->save();
                    $this->_sendAdminEmail($subject, $message);
                }
            }
            echo "canShip 2: ".$tracking;
            if($order->canShip() || $tracking != ''){    
                try {
                    // echo '$itemQty1';
                    $itemQty =  $this->_getItemQtys($order);
                    echo '$itemQty: '.$itemQty.' ||||| ';
                    // $shipment = Mage::getModel('sales/service_order', $order)->prepareShipment($itemQty);
                    $shipment = $order->prepareShipment();
                    // echo '$shipment: '.$shipment;
                    //$shipmentCarrierCode = $order->getShippingMethod();
                    $shipmentCarrierTitle = $order->getShippingDescription();
 
                    //Todo: Potentially revisit this to ensure that the carrier code matches the options in Magento otherwise the ship carrier is listed as "custom value"
                    $arrTracking = array(
                        'carrier_code' => isset($shipmentCarrierCode) ? $shipmentCarrierCode : $order->getShippingCarrier()->getCarrierCode(),
                        'title' => isset($shipmentCarrierTitle) ? $shipmentCarrierTitle : $order->getShippingCarrier()->getConfigData('title'),
                        'number' => $tracking,
                    );
 
                    // echo print_r($arrTracking);
                    $track = Mage::getModel('sales/order_shipment_track')->addData($arrTracking); 
                    $shipment->addTrack($track);
 
                    // Register Shipment
                    $shipment->register();
 
                    $message = 'Automated Liteview Message: Order shipped';
                    // Save the Shipment
                    $this->_saveShipment($shipment, $order, $message);
                    
                    // Add a comment
                    
                    $order->addStatusHistoryComment($message, false);
 
                    // Finally, Save the Order
                    $this->_saveOrder($order);
                } 
                catch (Exception $e) {
                    // echo print_r($e->message();
                    $this->logger->info("Liteview - Order Shipping Exception: " . $e->getMessage());
                    $subject = 'Liteview Cron: Invoice Exception for order: ' . $orderId;
                    $message = 'Liteview: An Exception occurred when flagging order: ' . $orderId . ' as shipped. Exception message: ' . $e->getMessage();
                    echo $message;
                    $order->addStatusHistoryComment($message, false);
                    $order->save();
                    $this->_sendAdminEmail($subject, $message);
                }
            }           
        }
        
    } 

}