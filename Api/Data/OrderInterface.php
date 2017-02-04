<?php

namespace JoshSpivey\LiteView\Api\Data;

interface OrderInterface
{

    public function getOrderData();
    public function setOrder($orderId);
	public function changeLiteViewStatus($error, $warnings, $liteViewNumber = '');

}