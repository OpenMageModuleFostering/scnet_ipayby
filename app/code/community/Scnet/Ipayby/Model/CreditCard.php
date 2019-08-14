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
	class Scnet_Ipayby_Model_CreditCard extends Mage_Payment_Model_Method_Cc {
		
		protected $_code = 'scnet_ipayby';
                
		protected $_isGateway                   = true;
		protected $_canOrder                    = false;
		protected $_canAuthorize                = true;
		protected $_canCapture                  = true;
		protected $_canCapturePartial           = false;
		protected $_canRefund                   = false;
		protected $_canRefundInvoicePartial     = false;
		protected $_canVoid                     = false;
		protected $_canUseInternal              = true;
		protected $_canUseCheckout              = true;
		protected $_canUseForMultishipping      = true;
		protected $_isInitializeNeeded          = false;
		protected $_canFetchTransactionInfo     = false;
		protected $_canReviewPayment            = false;
		protected $_canCreateBillingAgreement   = false;
		protected $_canManageRecurringProfiles  = false;
		
		// do not save the credit cart
		protected $_canSaveCc     = false;
		
		public function capture(Varien_Object $payment, $amount) {
			
			$_url = $this->_getServerUrl();
			$_xml = $this->_generatePaymentXml($this->_prepareParams($amount));
                       
			try {
                             
				$_res = $this->_connectServer($_url, $_xml);
                              
			}catch(Exception $e) {
                                
				throw Mage::Exception('Mage_Payment_Model_Info', $this->getConfigData('error_message'));
			}
			
			if(!$_res->isSuccessful()) {
                                
                                 //TODO
                                 $current .= "\n6" . 'Error';
                                 file_put_contents($file, $current);
                                 //TODO
				throw Mage::Exception('Mage_Payment_Model_Info', $this->getConfigData('error_message'));
			}
			
			$_result = $_res->getPaymentResult();
			
			if('Approved' != $_result->getData('summaryResponseCode')) {
				$_error = $_result->getData('summaryResponseCode') . ' - The transaction has failed with - ' . $_result->getData('responseCode');
				throw Mage::Exception('Mage_Payment_Model_Info', $_error);
			}
			
			$payment->setStatus(self::STATUS_APPROVED)
				->setStatusDescription($_result->getData('responseText'))
				->setTransactionId($_result->getData('orderNumber'))
				->setIsTransactionClosed(0)
				->setSuTransactionId($_result->getData('orderNumber'))
				->setLastTransId($_result->getData('orderNumber'))
			;
			       
            		   
			return $this;
		}
		
		protected function _getServerUrl() {
			if($this->getConfigData('live_mode') == 1) {
				return $this->getConfigData('live_server_url');
			}
			return $this->getConfigData('test_server_url');
		}
		
		protected function _prepareParams($amount) {
			$_billAdd  = $this->_getBillingAddress();
			$_street   = $_billAdd->getStreet();
			
			$_allItems = Mage::getSingleton('checkout/session')->getQuote()->getAllVisibleItems();
			$_tmpNames = array();
			foreach($_allItems as $_item) {
				$_tmpNames[] = $_item->getProduct()->getName() . '(' . $_item->getTotalQty() . ')';
			}

            $_countryName = Mage::getModel('directory/country')->load($_billAdd->getCountry())->getName(); 
			
			$_params  = array(
				'arg0'  => $this->getConfigData('merchant_id'),
				'arg1'  => $this->getConfigData('password'),
				'arg2'  => $this->_getCcNumber(),
				'arg3'  => str_pad($this->_getCcExpMonth(), 2, 0, STR_PAD_LEFT) . substr($this->_getCcExpYear(), -2),
				'arg4'  => $this->_getCcCid(),
				'arg5'  => $amount,
				'arg6'  => $this->_getCcOwner(),
				'arg7'  => $this->_getCurrencyCode(),
				'arg8'  => $this->_getOrderId(),
				'arg9'  => NULL,
				'arg10' => NULL,
				'arg11' => Mage::app()->getStore()->getFrontendName(),
				'arg12' => implode(', ', $_tmpNames),
				'arg13' => implode(' ', array($_billAdd->getFirstname(), $_billAdd->getLastname())),
				'arg14' => $_street[0],
				'arg15' => $_street[1],
				'arg16' => $_billAdd->getPostcode(),
				'arg17' => $_billAdd->getTelephone(),
				'arg18' => NULL,
				'arg19' => NULL,
				'arg20' => $this->getConfigData('merchat_abn'),
				'arg21' => 'N',
				'arg22' => $_billAdd->getCity(),
				'arg23' => $_countryName,
			//	'arg24' => NULL,
			//	'arg25' => NULL,
			//	'arg26' => NULL,
				'arg27' => $this->_getRealIpAddr()
			);
			if($this->getConfigData('merchant_notification') == 1 && $this->getConfigData('merchant_email')) {
				$_params['arg9']  = $this->getConfigData('merchant_email');
			}
			if($this->getConfigData('customer_notification') == 1) {
				$_params['arg10'] = $this->_getCustomerEmail();
                        }

			return $_params;
		}
		
		protected function _generatePaymentXml($elements) {
			$_xml = '<?xml version="1.0" encoding="utf-8"?>
			<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="http://service.ipay.scnet/" xmlns:scn="https://www.scnet.com.au/ipayby/ipaybyws">
				<soapenv:Header />
				<soapenv:Body>
					<ser:performTransaction>' . $this->_toSimpleFlatXml($elements) . '</ser:performTransaction>
				</soapenv:Body>
			</soapenv:Envelope>'
			;
			return $_xml;
		}
		
		protected function _connectServer($_url, $_xml) {


			$_headers = array(
				'Content-type: text/xml; charset=utf-8', 
				'Content-length: ' . strlen($_xml),
			);
			$_httpClient = new Varien_Http_Client($_url);
			$_httpClient->setHeaders($_headers);
			$_httpClient->setRawData($_xml);

			
			$this->_debug(array('requestedUrl'=>$_url, 'requestedXml'=>$_xml));
			
			try {
				$_response = $_httpClient->request(Varien_Http_Client::POST);

			}catch(Exception $e) {

				$this->_debug(array('Error' => $e->getMessage()));
				throw $e;
			}
			
			$this->_debug(array('responsedXml'=>$_response->getRawBody()));
			
			return $this->_getXmlObject($_response->getRawBody());
		}
		
		protected function _getXmlObject($_xml) {
			try {
				$_xmlObject = Mage::getModel('Scnet_Ipayby/ResponseParser', $_xml);
			}catch(Exception $e) {
				$this->_debug(array('Error'=>$e->getMessage()));
				throw $e;
			}
			return $_xmlObject;
		}
		
		protected function _isPublicIP($value) {
			return (count(explode('.', $value)) == 4 && !preg_match('~^((0|10|172\.16|192\.168|169\.254|255|127\.0)\.)~', $value));
		}
		
		protected function _getRealIpAddr() {
			$_check = array(
				'HTTP_X_FORWARDED_FOR',
				'HTTP_CLIENT_IP',
				'HTTP_X_CLUSTER_CLIENT_IP',
				'HTTP_FORWARDED_FOR',
			);
			foreach($_check as $_key) {
				if(isset($_SERVER[$_key])) {
					$ips = explode(',', $_SERVER[$_key]);
					if(isset($ips[0]) && $this->_isPublicIP($ips[0])) {
						return $ips[0];
					}
				}
			}
			return $_SERVER['REMOTE_ADDR'];
		}
		
		protected function _toSimpleFlatXml($elements){
			$_xml = NULL;
			foreach($elements as $k => $v) {
				$v = is_array($v) ? $this->_toSimpleFlatXml($v) : $this->_xmlspecialchars($v);
				$_xml .= '<' . $k . '>' . $v . '</' . $k . '>';
			}
			return $_xml;
		}
		
		protected function _xmlspecialchars($text) {
			return str_replace('&#039;', '&apos;', htmlspecialchars($text, ENT_QUOTES));
		}
		
		protected function _getCcOwner() {
			$info = $this->getInfoInstance();
			if ($this->_isPlaceOrder()) {
				return $info->getOrder()->getPayment()->getCcOwner();
			} else {
				return $info->getCcOwner();
			}
		}
		
		protected function _getCcNumber() {
			$info = $this->getInfoInstance();
			if ($this->_isPlaceOrder()) {
				return $info->getOrder()->getPayment()->getCcNumber();
			} else {
				return $info->getCcNumber();
			}
		}
		
		protected function _getCcExpMonth() {
			$info = $this->getInfoInstance();
			if ($this->_isPlaceOrder()) {
				return $info->getOrder()->getPayment()->getCcExpMonth();
			} else {
				return $info->getCcExpMonth();
			}
		}
		
		protected function _getCcExpYear() {
			$info = $this->getInfoInstance();
			if ($this->_isPlaceOrder()) {
				return $info->getOrder()->getPayment()->getCcExpYear();
			} else {
				return $info->getCcExpYear();
			}
		}
		
		protected function _getCcCid() {
			$info = $this->getInfoInstance();
			if ($this->_isPlaceOrder()) {
				return $info->getOrder()->getPayment()->getCcCid();
			} else {
				return $info->getCcCid();
			}
		}
		
		protected function _getOrderId(){
			$info = $this->getInfoInstance();
			if ($this->_isPlaceOrder()) {
				return $info->getOrder()->getIncrementId();
			} else {
				if (!$info->getQuote()->getReservedOrderId()) {
					$info->getQuote()->reserveOrderId();
				}
				return $info->getQuote()->getReservedOrderId();
			}
		}
		
		protected function _getAmount() {
			$info = $this->getInfoInstance();
			if ($this->_isPlaceOrder()) {
				return (double)$info->getOrder()->getQuoteBaseGrandTotal();
			} else {
				return (double)$info->getQuote()->getBaseGrandTotal();
			}
		}
		
		protected function _getCustomerEmail() {
			$info = $this->getInfoInstance();
			if ($this->_isPlaceOrder()) {
				return $info->getOrder()->getCustomerEmail();
			} else {
				return $info->getQuote()->getCustomerEmail();
			}
		}
		
		protected function _getBillingAddress() {
			$info = $this->getInfoInstance();
			if ($this->_isPlaceOrder()) {
				return $info->getOrder()->getBillingAddress();
			} else {
				return $info->getQuote()->getBillingAddress();
			}
		}
		
		protected function _getBillingCountryCode() {
			$info = $this->getInfoInstance();
			if ($this->_isPlaceOrder()) {
				return $info->getOrder()->getBillingAddress()->getCountryId();
			} else {
				return $info->getQuote()->getBillingAddress()->getCountryId();
			}
		}
		
		protected function _getCurrencyCode(){
			$info = $this->getInfoInstance();
			if ($this->_isPlaceOrder()) {
				return $info->getOrder()->getBaseCurrencyCode();
			} else {
				return $info->getQuote()->getBaseCurrencyCode();
			}
		}
		
		protected function _isPlaceOrder() {
			$info = $this->getInfoInstance();
			if ($info instanceof Mage_Sales_Model_Quote_Payment) {
				return false;
			} elseif ($info instanceof Mage_Sales_Model_Order_Payment) {
				return true;
			}
		}
	}
