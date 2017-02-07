<?php

namespace JoshSpivey\LiteView\Model;

use JoshSpivey\LiteView\Api\Data\OrderInterface;
use JoshSpivey\LiteView\Helper\ConfigHelper;

class Order extends \Magento\Framework\Model\AbstractModel implements OrderInterface
{
    const STATE_SENT_TO_WAREHOUSE   = 'lv_send_liteview';
    const STATE_WAREHOUSE_ERROR     = 'lv_send_error';

    protected $orderRepository;
    protected $orderItemRepository;
    protected $orderData;
    protected $order;
    protected $adminConfigModel;
    protected $_messageManager;

   
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Api\OrderItemRepositoryInterface $orderItemRepository,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \JoshSpivey\LiteView\Model\AdminConfig $adminConfigModel
    )
    {
        $this->orderRepository = $orderRepository;
        $this->orderItemRepository = $orderItemRepository;
        $this->_messageManager = $messageManager;
        $this->adminConfigModel = $adminConfigModel;
    }

    public function setOrder($orderId)
    {
        $this->order = $this->orderRepository->get($orderId);
        $this->orderData = $this->order->getData();

        return $this;
    }

    private function getOrderDetails(){
        $orderDetails = [];
        $orderDetails['order_status'] = "Active";
        $orderDetails['order_date'] = date('Y-m-d', strtotime($this->orderData["created_at"]));
        $orderDetails['order_number'] = $this->orderData["increment_id"];
        $orderDetails['order_source'] = $this->adminConfigModel->getOrderSource();
        $orderDetails['order_type'] = "Regular";
        $orderDetails['catalog_name'] = "Default";
        $orderDetails['gift_order'] = "False";
        $orderDetails['allocate_inventory_now'] = "TRUE";

        return $orderDetails;
    }

    private function getContact($contactObj, $prefix){

        $contactData = $contactObj->getData();

        $contact = [];
        $contact[$prefix.'_prefix'] = $contactData['prefix'];
        $contact[$prefix.'_first_name'] = $contactData['firstname'];
        $contact[$prefix.'_last_name'] = $contactData['lastname'];
        $contact[$prefix.'_suffix'] = $contactData['suffix'];
        $contact[$prefix.'_company_name'] = $contactData['company'];
        if(count($contactData['street']) > 1){
            $contact[$prefix.'_address1'] = $contactData['street'][0];
            $contact[$prefix.'_address2'] = $contactData['street'][1];
        }else{
            $contact[$prefix.'_address1'] = $contactData['street'];
            $contact[$prefix.'_address2'] = "";
        }
        $contact[$prefix.'_address3'] = "";
        $contact[$prefix.'_city'] = $contactData['city'];
        $contact[$prefix.'_state'] = $contactData['region'];
        $contact[$prefix.'_postal_code'] = $contactData['postcode'];
        $contact[$prefix.'_country'] = $contactData['country_id'];
        $contact[$prefix.'_telephone_no'] = $contactData['telephone'];
        $contact[$prefix.'_email'] = $contactData['email'];

        return $contact;
    }

    private function getBillingDetails(){
        $billingDetails = [];
        $billingDetails['sub_total'] = "0.00";
        $billingDetails['shipping_handling'] = "0.00";
        $billingDetails['sales_tax_total'] = "0.00";
        $billingDetails['discount_total'] = "0.00";
        $billingDetails['grand_total'] = "0.00";

        return $billingDetails;
    }

    private function getShippingDetails($countryId){
        $method = $this->orderData["shipping_method"];

        //Add in options to multi select in a admin page to allow mapping of shipping methods
        $groupArr = ['customshipprice_customshipprice', 'ups_03', 'ups_02', 'ups_01'];

        $failSafe = $method;

        if(in_array($method, $groupArr)){
            $failSafe = 'fedex_FEDEX_GROUND';
        }

        if($method == 'fedex_FEDEX_GROUND' && $countryId == "CA"){
            $failSafe = 'fedex_INTERNATIONAL_GROUND';
        }

        $shipMethodArr = [//move to config i18n
            "freeshipping_freeshipping" => "Will Call Will Call/Pick Up",
            "fedex_FEDEX_2_DAY" => "FedEx 2 Day",
            "fedex_FEDEX_EXPRESS_SAVER" => "FedEx Express Saver",
            "fedex_FEDEX_GROUND" => "FedEx Ground",
            "fedex_GROUND_HOME_DELIVERY" => "FedEx Home Delivery",
            "fedex_INTERNATIONAL_ECONOMY" => "FedEx International Economy",
            "fedex_INTERNATIONAL_GROUND" => "FedEx International Ground",
            "fedex_SMART_POST" => "FedEx Ground",
            "usps_0_FCP" => "US Postal Service Parcel Post",
            "usps_1" => "US Postal Service Priority Mail",
            "usps_INT_2" => "US Postal Service Priority Mail International",
            "usps_INT_15" => "US Postal Service First-Class Mail International"
        ];

        $shippingDetails = [];
        $shippingDetails['ship_method'] = $shipMethodArr[$failSafe];
        $shippingDetails['ship_options']['signature_requested'] = "FALSE";
        $shippingDetails['ship_options']['insurance_requested'] = "FALSE";
        $shippingDetails['ship_options']['insurance_value'] =  $this->orderData["base_subtotal"];
        $shippingDetails['ship_options']['saturday_delivery_requested'] = "FALSE";
        $shippingDetails['ship_options']['third_party_billing_requested'] = "FALSE";
        $shippingDetails['ship_options']['third_party_billing_account_no'] = "";
        $shippingDetails['ship_options']['third_party_billing_zip'] = "";
        $shippingDetails['ship_options']['third_party_country'] = "";
        $shippingDetails['ship_options']['general_description'] = "";
        $shippingDetails['ship_options']['content_description'] = "";

        return $shippingDetails;
    }

    private function getItem($orderItem){
        $itemData = $orderItem->getData();
 
        $item['item']['inventory_item'] = trim($itemData['sku']);
        $item['item']['inventory_item_sku'] = trim($itemData['sku']);
        $item['item']['inventory_item_description'] = $itemData['name'];
        $item['item']['inventory_item_price'] = "0.00";
        $item['item']['inventory_item_qty'] = $itemData['qty_ordered'];
        $item['item']['inventory_item_ext_price'] = "0.00";

        return ($itemData['weight'] > 0)? $item : [];
    }

    private function getOrderNotes($customsAmount, $requiresCustoms){

        $orderNotes = [];
        $orderNotes['note']['note_type'] = "shipping";
        $orderNotes['note']['note_description'] = ($requiresCustoms)? "Customs declaration total value of customs amount \$".$customsAmount." USD" : "";
        $orderNotes['note']['show_on_ps'] = ($requiresCustoms)? "TRUE" : "FALSE";

        return $orderNotes;
    }

    public function getOrderData(){

        $orderItems = $this->order->getAllItems();
        $itemArr = array_map(array($this, 'getItem'), $orderItems);

        $sum = array_sum(array_map(function($orderItem) {
          return $orderItem->getData()['price'];
        }, $orderItems));

        $customsAmount = (round($this->orderData['base_grand_total'] - $this->orderData['base_shipping_amount']) == 0)? 1 : $sum;
        $requiresCustoms = ($this->order->getShippingAddress()->getCountryId() != "US");

        $itemArr['total_line_items'] = count($itemArr);

        $data = [];
        $data['submit_order']['order_info'] = [
            "order_details" => $this->getOrderDetails(),
            "billing_contact" => $this->getContact($this->order->getBillingAddress(), 'billto'),
            "shipping_contact" => $this->getContact($this->order->getShippingAddress(), 'shipto'),
            "billing_details" => $this->getBillingDetails(),
            "shipping_details" => $this->getShippingDetails($this->order->getBillingAddress()->getCountryId()),
            "order_items" => $itemArr,
            "order_notes" => $this->getOrderNotes($customsAmount, $requiresCustoms)
        ];

        return $data;
    }

    public function getOrderCancelData(){
        $data = [];
        $data['cancel_order']['order_info']['order_details'] = [
            "client_order_number" => $this->order->getIncrementId(),
            "ifs_order_number" => $this->order->getLiteviewOrderId(),
            "notes_for_cancellation" => "cancel order"
        ];

        return $data;
    }

    public function changeLiteViewStatus($error, $warnings, $liteViewNumber = ''){


        if (isset($error) && isset($error->error_description)) 
        {
            //If there was an error then the order never made it to Liteview, therefore leave the status untouched, but add an order history note
            $history = $this->order->addStatusHistoryComment("Order: ".$this->orderData["increment_id"]." was not sent to the warehouse due to the following problem: ".$error->error_description, false);
            $history->setIsCustomerNotified(false);
            $this->_messageManager->addError("Order: ".$this->orderData["increment_id"]." could not be sent to the warehouse due to the following problem: ".$error->error_description);

        }else if(isset($warnings) && $warnings != null && count($warnings->children()) > 0) {

            $this->order->setLiteviewOrderId($liteViewNumber);
            //If an item has warnings, but is not in error, then mark it as sent to the warehouse.  Keep an eye on this to determine the true status
            $this->order->setData("state", "processing");
            $this->order->setStatus(STATE_SENT_TO_WAREHOUSE);

            foreach ($warnings as $warning){
                $history = $this->order->addStatusHistoryComment("Warning when sending this item to the warehouse: ".$warning->warning, false);
                $history->setIsCustomerNotified(false);
                $this->_messageManager->addSuccess("Order: ".$this->orderData["increment_id"]." had the following warning: ".$warning->warning); //$returnWarning);
                $this->_messageManager->addSuccess("Order: ".$this->orderData["increment_id"]." had the following warning: ".$warning->warning);
            }

            $history = $this->order->addStatusHistoryComment("The order was sent to the warehouse with one or more warnings.", false);
            $history->setIsCustomerNotified(false);

        }else{

            $this->order->setLiteviewOrderId($liteViewNumber);
            $this->order->setData("state", "processing");
            $this->order->setStatus(STATE_SENT_TO_WAREHOUSE);
            $history = $this->order->addStatusHistoryComment("The order(s) was sent to the warehouse successfully.", false);
            $history->setIsCustomerNotified(false);
            $this->_messageManager->addSuccess("The order (".$this->orderData["increment_id"].") was sent to the warehouse successfully.");
        }
        $this->order->save();
    }

    protected function validateOrder(){ 
        $shippingAddress = $this->order->getShippingAddress();
        
        if (!isset($shippingAddress) || $shippingAddress == null){
            $this->_messageManager->addError("Order: ".$this->orderData["increment_id"]." could not be sent to the warehouse due to the following problem: ".$error->error_description);
            $this->_messageManager->addError('Order: '.$this->orderData["increment_id"].' is missing a valid shipping address.');
            return false;
        }

        $statesArr = ['complete', 'closed', 'canceled'];
        
        if(!in_array($this->orderData["state"], $statesArr)){
            $this->_messageManager->addError('Order State: '.$this->orderData["state"].' Order: '.$this->orderData["increment_id"].' has a status of: '.$this->orderData["status"].' and cannot be sent to the warehouse at this time.');
            return false;   
        }
        
        return true;
    }

}