<?php

namespace JoshSpivey\LiteView\Api\Data;

interface AdminConfigInterface
{

    public function getOrderSource();
	public function getTestEnabled();
    public function getProdUser();
	public function getProdApiKey();
    public function getDevUser();
	public function getDevApiKey();
}