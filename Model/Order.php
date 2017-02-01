<?php

namespace JoshSpivey\LiteView\Model;

use JoshSpivey\LiteView\Api\Data\OrderInterface;
use JoshSpivey\LiteView\Helper\ConfigHelper;

class Order extends \Magento\Framework\Model\AbstractModel implements OrderInterface
{
    protected $orderRepository;
    protected $orderItemRepository;
    protected $orderData;
   
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Api\OrderItemRepositoryInterface $orderItemRepository
    )
    {
        $this->orderRepository = $orderRepository;
        $this->orderItemRepository = $orderItemRepository;

    }

    private function getOrderDetails(){
        $orderDetails = [];
        $orderDetails['order_status'] = "Active";
        $orderDetails['order_date'] = $this->orderData["created_at"];
        $orderDetails['order_number'] = $this->orderData["increment_id"];
        $orderDetails['order_source'] = "website.com";//pull from config
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

        return $item;
    }

    private function getOrderNotes($customsAmount){

        $orderNotes = [];
        $orderNotes['note']['note_type'] = "shipping";
        $orderNotes['note']['note_description'] = "Customs declaration total value of customs amount \$".$customsAmount." USD";
        $orderNotes['note']['show_on_ps'] = "TRUE";
        $orderNotes['note']['show_on_ps'] = "FALSE";

        return $orderNotes;
    }

    public function getOrderData($orderId){
        $order = $this->orderRepository->get($orderId);
        $this->orderData = $order->getData();

        $orderItems = $order->getAllItems();
        $itemArr = array_map(array($this, 'getItem'), $orderItems);

        $sum = array_sum(array_map(function($orderItem) {
          return $orderItem->getData()['price'];
        }, $orderItems));

        $customsAmount = (round($this->orderData['base_grand_total'] - $this->orderData['base_shipping_amount']) == 0)? 1 : $sum;

        $itemArr['total_line_items'] = count($itemArr);

        $data = [];
        $data['submit_order']['order_info'] = [
            "order_details" => $this->getOrderDetails(),
            "billing_contact" => $this->getContact($order->getBillingAddress(), 'billto'),
            "shipping_contact" => $this->getContact($order->getShippingAddress(), 'shipto'),
            "billing_details" => $this->getBillingDetails(),
            "shipping_details" => $this->getShippingDetails($order->getBillingAddress()->getCountryId()),
            "order_items" => $itemArr,
            "order_notes" => $this->getOrderNotes($customsAmount)
        ];

        return $data;
    }
}