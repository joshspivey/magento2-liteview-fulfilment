<?php

namespace JoshSpivey\LiteView\Api\Data;

interface LiteViewInterface
{

    public function getShippingMethods();
	public function sendOrderToLiteView($orderXml, $orderId);
    public function sendCancelOrder($cancelOrderXml, $orderId);
    public function checkLiteviewTracking();
    public function checkLiteviewOrderStatus();

}