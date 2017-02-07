<?php

namespace JoshSpivey\LiteView\Model;

use JoshSpivey\LiteView\Api\Data\AdminConfigInterface;
use JoshSpivey\LiteView\Helper\ConfigHelper;

class AdminConfig extends \Magento\Framework\Model\AbstractModel implements AdminConfigInterface
{
    /** @var  ConfigHelper */
    protected $_config;


    public function __construct(
        ConfigHelper $config
    )
    {
        $this->_config = $config;
    }

    public function getOrderSource()
    {
        return $this->_config->getConfig('txt/orderSource');
    }

    public function getTestEnabled()
    {
        return $this->_config->getConfig('txt/testEnabled');
    }

    public function getProdUser()
    {
        return $this->_config->getConfig('txt/prodUser');
    }

    public function getProdApiKey()
    {
        return $this->_config->getConfig('txt/prodApiKey');
    }

    public function getDevUser()
    {
        return $this->_config->getConfig('txt/devUser');
    }

    public function getDevApiKey()
    {
        return $this->_config->getConfig('txt/devApiKey');
    }
}