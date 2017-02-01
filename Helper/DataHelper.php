<?php
namespace JoshSpivey\LiteView\Helper;

class DataHelper extends \Magento\Framework\App\Helper\AbstractHelper
{

	public function convert_array_to_xml($array, $namespace = '') {
		$xml = '';
		foreach($array as $key => $value) {
			if(is_array($value)) {
				$value = $this->convert_array_to_xml($value);
			}
			$xml .= "<$key" . rtrim(" $namespace") . ">$value</$key>";
		}
		
		return $xml;
	}


	public function array_to_xml($data, &$xml) {
	    foreach($data as $key => $value) {
	        if(is_array($value)) {
	            if(!is_numeric($key)){
	                $subnode = $xml->addChild("$key");
	                $this->array_to_xml($value, $subnode);
	            }
	            else{
	                $subnode = $xml->addChild("item");
	                $this->array_to_xml(array_shift($value), $subnode);
	            }
	        }
	        else {
	            $xml->addChild("$key", htmlspecialchars("$value"));
	        }
	    }
	    return $xml->asXML();
	}
	
}