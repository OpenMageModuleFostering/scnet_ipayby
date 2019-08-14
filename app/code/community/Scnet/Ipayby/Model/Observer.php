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
	class Scnet_Ipayby_Model_Observer {
		
		public function paymentSpecificInformation($observer) {
			$_transport = $observer->getTransport();
			if($observer->getPayment()->getLastTransId()) {
				$_transport->setData('TID', $observer->getPayment()->getLastTransId());
			}
		}
	}
