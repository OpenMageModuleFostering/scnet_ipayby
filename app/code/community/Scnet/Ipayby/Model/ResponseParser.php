<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category    
 * @package     Scnet_Ipayby
 * @copyright   www.scnet.com.au 
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
	class Scnet_Ipayby_Model_ResponseParser extends Varien_Simplexml_Element {
		
		public function isSuccessful() {
			$_result = (array)$this->children('http://schemas.xmlsoap.org/soap/envelope/');
			if(!isset($_result['Body'])) {
				return false;
			}
			$_error = $_result['Body']->Fault->children();
			return !count($_error);
		}
		
		public function getError() {
			$_ret = new Varien_Object();
			$_result = (array)$this->children('http://schemas.xmlsoap.org/soap/envelope/');
			if(isset($_result['Body'])) {
				$_ret->setData($_result['Body']->Fault->asArray());
			}
			return $_ret;
		}
		
		public function getPaymentResult() {
			$_res = new Varien_Object();
			foreach($this->children('http://schemas.xmlsoap.org/soap/envelope/') as $_child) {
				if('Body' == $_child->getName()) {
					foreach($_child->children('http://service.ipay.scnet/') as $_childchild) {
						$_tmp = $_childchild->asArray();
						if(isset($_tmp['return'])) {
							$_res->setData($_tmp['return']);
						}
					}
				}
			}
			return $_res;
		}
	}
