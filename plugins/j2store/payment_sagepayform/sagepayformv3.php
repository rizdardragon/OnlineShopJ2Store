<?php
/*
 * --------------------------------------------------------------------------------
   Weblogicx India  - J2Store - SagePay Payment Plugin - Form Integration
 * --------------------------------------------------------------------------------
 * @package		Joomla! 2.5x
 * @subpackage	J2Store
 * @author    	Weblogicx India http://www.weblogicxindia.com
 * @copyright	Copyright (c) 2010 - 2015 Weblogicx India Ltd. All rights reserved.
 * @license		GNU/GPL license: http://www.gnu.org/copyleft/gpl.html
 * @link		http://j2store.org
 * --------------------------------------------------------------------------------
*/

/** ensure this file is being included by a parent file */
defined('_JEXEC') or die('Restricted access');

require_once (JPATH_ADMINISTRATOR.'/components/com_j2store/library/plugins/payment.php');

class plgJ2StorePayment_sagepayform extends J2StorePaymentPlugin
{
	/**
	 * @var $_element  string  Should always correspond with the plugin's filename,
	 *                         forcing it to be unique
	 */
    var $_element    = 'payment_sagepayform';
    var $_isLog      = false;
    private $vendor_name = '';
    private $encryption_key = '';


	/**
	 * Constructor
	 *
	 * For php4 compatability we must not use the __constructor as a constructor for plugins
	 * because func_get_args ( void ) returns a copy of all passed arguments NOT references.
	 * This causes problems with cross-referencing necessary for the observer design pattern.
	 *
	 * @param object $subject The object to observe
	 * @param 	array  $config  An array that holds the plugin configuration
	 * @since 1.5
	 */
	function __construct(& $subject, $config) {
		parent::__construct($subject, $config);
		$this->loadLanguage( '', JPATH_ADMINISTRATOR );
		$this->vendor_name = trim($this->_getParam('vendor_name'));
		$this->encryption_key = trim($this->_getParam('encryption_key'));

		if($this->params->get('debug', 0)) {
			$this->_isLog = true;
		}
	}

    /**
     * @param $data     array       form post data
     * @return string   HTML to display
     */
    function _prePayment( $data )
    {
		// get component params
		$params = J2Store::config();
		$currency = J2Store::currency();
        // prepare the payment form

        $vars = new JObject();
        $vars->order_id = $data['order_id'];
        $vars->orderpayment_id = $data['orderpayment_id'];
        $order = F0FTable::getInstance('Order', 'J2StoreTable');
        $order->load ( array (
				'order_id' => $data ['order_id']
		) );

        $currency_values= $this->getCurrency($order);

        $vars->currency_code =$currency_values['currency_code'];
        $vars->orderpayment_amount = $currency->format($order->order_total, $currency_values['currency_code'], $currency_values['currency_value'], false);

        $vars->orderpayment_type = $this->_element;

        $vars->display_name = $this->params->get('display_name', 'PLG_J2STORE_PAYMENT_SAGEPAYFORM');
        $vars->onbeforepayment_text = $this->params->get('onbeforepayment', '');
        $vars->button_text = $this->params->get('button_text', 'J2STORE_PLACE_ORDER');



        $items = $order->getItems();


        $products = array();
        foreach ($items as $item)
        {
        	$desc = $item->orderitem_name;

        	//product options
        	$options=array();
        	if (isset($item->orderitemattributes) && count($item->orderitemattributes)) {
        		foreach ($item->orderitemattributes as $attribute) {
        			$options[] = array(
        					'name' => JText::_($attribute->orderitemattribute_name),
        					'value'=> $attribute->orderitemattribute_value
        			);

        		}
        	}
        	$desc = str_replace("'", '', $desc);
        	$products[]= array(
        			'name'		=>html_entity_decode($desc,ENT_QUOTES, 'UTF-8'),
        			'options' 	=>$options,
        			'number' 	=> !empty($item->orderitem_sku)? $item->orderitem_sku : $item->product_id,
        			'quantity'	=>$item->orderitem_quantity,
        			'price'		=> $currency->format($item->orderitem_finalprice_with_tax / $item->orderitem_quantity, $currency_values['currency_code'], $currency_values['currency_value'], false)
        	);

        	$item->_description = $desc;
        }

        $handling_cost = $order->order_shipping + $order->order_shipping_tax + $order->order_surcharge;
        $handling_cart = $currency->format($handling_cost, $currency_values['currency_code'], $currency_values['currency_value'], false);
        if($handling_cart > 0) {
        	$products[]= array(
        			'name'		=> JText::_('J2STORE_SHIPPING_AND_HANDLING'),
        			'options' 	=> array(),
        			'number'    => '',
        			'quantity'	=> 1,
        			'price'		=> $handling_cart
        	);
        }

        $vars->products = $products;



//        $vars->tax_cart = $this->getAmount($order->order_tax, $currency_values['currency_code'], $currency_values['currency_value'], $currency_values['convert']);

        $vars->discount_amount_cart = $currency->format($order->order_discount, $currency_values['currency_code'], $currency_values['currency_value'], false);

        $vars->order = $order;
        $vars->orderitems = $items;

        // set payment plugin variables
        $vars->vendor_name = $this->vendor_name;
        $vars->post_url = $this->_getPostUrl();


        $rootURL = rtrim(JURI::base(),'/');
        $subpathURL = JURI::base(true);
        if(!empty($subpathURL) && ($subpathURL != '/')) {
        	$rootURL = substr($rootURL, 0, -1 * strlen($subpathURL));
        }

        $vars->return_url = $rootURL.JRoute::_("index.php?option=com_j2store&view=checkout&task=confirmPayment&orderpayment_type=".$this->_element."&paction=process");

        // set variables for user info
		$orderinfo = $order->getOrderInformation();
		
        // set variables for user info
        $vars->first_name   = $orderinfo->billing_first_name;
        $vars->last_name    = $orderinfo->billing_last_name;
        $vars->email        = $order->user_email;
        $vars->address_1    = $orderinfo->billing_address_1;
        $vars->address_2    = $orderinfo->billing_address_2;
        $vars->city         = $orderinfo->billing_city;
        $vars->country      = $this->getCountryById($orderinfo->billing_country_id)->country_isocode_2;
        $vars->region       = substr($this->getZoneById($orderinfo->billing_zone_id)->zone_code, 0, 2);
        $vars->zip  = $orderinfo->billing_zip;
        $vars->phone = !empty($orderinfo->billing_phone_1)? $orderinfo->billing_phone_1:$orderinfo->billing_phone_2;

        $vars->invoice = $order->getInvoiceNumber();

        //shipping
        if($orderinfo->shipping_first_name) {
        	$vars->delivery_first_name   = $orderinfo->shipping_first_name;
        }else {
        	$vars->delivery_first_name   = $orderinfo->billing_first_name;
        }

        if($orderinfo->shipping_last_name) {
        	$vars->delivery_last_name    = $orderinfo->shipping_last_name;
        }else {
        	$vars->delivery_last_name    = $orderinfo->billing_last_name;
        }

        if($orderinfo->shipping_address_1) {
        	$vars->delivery_address_1    = $orderinfo->shipping_address_1;
        }else {
        	$vars->delivery_address_1    = $orderinfo->billing_address_1;
        }


        if($orderinfo->shipping_address_2) {
        	$vars->delivery_address_2    = $orderinfo->shipping_address_2;
        }else {
        	$vars->delivery_address_2    = $orderinfo->billing_address_2;
        }

        if($orderinfo->shipping_city) {
        	$vars->delivery_city         = $orderinfo->shipping_city;
        }else {
        	$vars->delivery_city         = $orderinfo->billing_city;
        }

        if($orderinfo->shipping_country_name) {
        	$vars->delivery_country      = $this->getCountryById($orderinfo->shipping_country_id)->country_isocode_2;
        }else {
        	$vars->delivery_country      = $vars->country;
        }

        if($orderinfo->shipping_zone_name) {
        	$vars->delivery_region      = substr($this->getZoneById($orderinfo->shipping_zone_id)->zone_code, 0, 2);
        }else {
        	$vars->delivery_region      = $vars->region;
        }

        if($orderinfo->shipping_zip) {
        	$vars->delivery_zip     = $orderinfo->shipping_zip;
        }else {
        	$vars->delivery_zip 	= $orderinfo->billing_zip;
        }

        if($orderinfo->shipping_phone_1) {
        	$vars->delivery_phone       = $orderinfo->shipping_phone_1;
        }else {
        	$vars->delivery_phone       = $orderinfo->billing_phone_1;
        }

        //in case we dont have phone_1

        if(empty($orderinfo->shipping_phone_1) && empty($orderinfo->billing_phone_1) ) {
        	if($orderinfo->shipping_phone_2) {
        		$vars->delivery_phone       = $orderinfo->shipping_phone_2;
        	}else {
        		$vars->delivery_phone       = $orderinfo->billing_phone_2;
        	}
        }



        $value = array();

        $value['VendorTxCode'] = $vars->order_id;
       //$value['ReferrerID'] = 'E511AF91-E4A0-42DE-80B0-09C981A3FB61';
        $value['Amount'] = $vars->orderpayment_amount;
        $value['Currency'] = $vars->currency_code;
        $value['Description'] = JText::sprintf('J2STORE_SAGEPAYFORM_ORDER_DESCRIPTION', $vars->invoice);
        $value['SuccessURL'] = str_replace('&amp;', '&', $vars->return_url);
        $value['FailureURL'] = str_replace('&amp;', '&', $vars->return_url);

        $value['CustomerName'] = $vars->first_name;
        $value['SendEMail'] = '1';
        $value['CustomerEMail'] = $vars->email;
        $value['VendorEMail'] = $this->params->get('vendor_email');

        $value['BillingFirstnames'] = $vars->first_name;
        $value['BillingSurname'] = $vars->last_name;
        $value['BillingAddress1'] = $vars->address_1;

        if ($vars->address_2) {
        	$value['BillingAddress2'] = $vars->address_2;
        }

        $value['BillingCity'] = $vars->city;
        $value['BillingPostCode'] = $vars->zip;
        $value['BillingCountry'] = $vars->country;

        if ($vars->country == 'US') {
        	$value['BillingState'] = $vars->region;
        }

        $value['BillingPhone'] = $vars->phone;


        	$value['DeliveryFirstnames'] = $vars->delivery_first_name;
        	$value['DeliverySurname'] = $vars->delivery_last_name;
        	$value['DeliveryAddress1'] = $vars->delivery_address_1;

        	if ($vars->delivery_address_2) {
        		$value['DeliveryAddress2'] = $vars->delivery_address_2;
        	}

        	$value['DeliveryCity'] = $vars->delivery_city;
        	$value['DeliveryPostCode'] = $vars->delivery_zip;
        	$value['DeliveryCountry'] = $vars->delivery_country;

        	if ($vars->delivery_country == 'US') {
        		$value['DeliveryState'] = $vars->delivery_region;
        	}

        	$value['DeliveryPhone'] = $vars->delivery_phone;

        	$basket ='';
        	$basket .= count($vars->products).':';
        	foreach($vars->products as $product) {
        		$basket .= $product['name'].':'.$product['quantity'].':'.$product['price'].'::'.$product['price'].':'.$product['price'].':';
        	}
        	$basket .= 'Discount:---:---:---:---:'.$vars->discount_amount_cart;
			//$value['Basket'] = $basket;
        	$value['AllowGiftAid'] = '0';
        	$value['Apply3DSecure'] = '0';
        	$value['VendorData'] = $invoice_number;
        	$this->_log($this->_getFormattedTransactionDetails($value), 'Payment Request');
        	$vars->crypt = $this->generateCrypt($value);

        $html = $this->_getLayout('prepayment', $vars);
        return $html;
    }

    function generateCrypt($data) {

    	$crypt_data = array();
    	foreach($data as $key => $value){
    		$crypt_data[] = $key . '=' . $value;
    	}
    	$strIn = utf8_decode(implode('&', $crypt_data));

		$strIn = $this->pkcs5_pad($strIn, 16);
		return "@".bin2hex(mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $this->encryption_key, $strIn, MCRYPT_MODE_CBC, $this->encryption_key));
    }

    	protected function pkcs5_pad($text, $blocksize)	{
			$pad = $blocksize - (strlen($text) % $blocksize);
			return $text . str_repeat(chr($pad), $pad);
		}


    /**
     * Processes the payment form
     * and returns HTML to be displayed to the user
     * generally with a success/failed message
     *
     * @param $data     array       form post data
     * @return string   HTML to display
     */
    function _postPayment( $data )
    {

		$app = JFactory::getApplication();
        // Process the payment
        $paction = $app->input->getString('paction');

        $vars = new JObject();

        switch ($paction)
        {
            case "process":
            	$vars->message = $this->_process();
                $html = $this->_getLayout('message', $vars);
                $html .= $this->_displayArticle();

              break;
            case "cancel":
                $vars->message = JText::_($this->params->get('oncancelpayment', ''));
                $html = $this->_getLayout('message', $vars);
              break;
            default:
                $vars->message = JText::_($this->params->get('onerrorpayment', ''));
                $html = $this->_getLayout('message', $vars);
              break;
        }

        return $html;
    }

    /**
     * Prepares variables for the payment form
     *
     * @return unknown_type
     */
    function _renderForm( $data )
    {
        $user = JFactory::getUser();
        $vars = new JObject();
        $vars->onselection_text = $this->params->get('onselection', '');
        $html = $this->_getLayout('form', $vars);

        return $html;
    }

    /************************************
     * Note to 3pd:
     *
     * The methods between here
     * and the next comment block are
     * specific to this payment plugin
     *
     ************************************/

    /**
     * Gets the Paypal gateway URL
     *
     * @param boolean $full
     * @return string
     * @access protected
     */
    function _getPostUrl($full = true)
    {
        $url = $this->params->get('sandbox') ? 'test.sagepay.com' : 'live.sagepay.com';

        if ($full)
        {
            $url = 'https://' . $url . '/gateway/service/vspform-register.vsp';
        }

        return $url;
    }


    /**
     * Gets the value for the Paypal variable
     *
     * @param string $name
     * @return string
     * @access protected
     */
    function _getParam( $name, $default='' )
    {
    	$return = $this->params->get($name, $default);

    	$sandbox_param = "sandbox_$name";
    	$sb_value = $this->params->get($sandbox_param);
        if ($this->params->get('sandbox') && !empty($sb_value))
        {
            $return = $this->params->get($sandbox_param, $default);
        }

        return $return;
    }


	/**
	 *
	 * @return HTML
	 */
	function _process()
	{
		$app = JFactory::getApplication();
		$raw_data = $app->input->getArray($_REQUEST);
		$this->_log($this->_getFormattedTransactionDetails($raw_data), 'Payment Raw Response');
		$decrypt_data ='';
		if(array_key_exists('crypt', $raw_data)) {
			$this->_log($raw_data['crypt'], 'crypt string');
			$data = $this->getDecryptedResponse($raw_data['crypt']);
		} else {
			return JText::_($this->params->get('onerrorpayment', ''));
		}
		$error = '';       	
       	$order_id = $data['VendorTxCode'];
		$transaction_details = $this->_getFormattedTransactionDetails( $data );
		$this->_log($transaction_details, 'payment response');
        // load the orderpayment record and set some values
        F0FTable::addIncludePath( JPATH_ADMINISTRATOR.'/components/com_j2store/tables' );
        $orderpayment = F0FTable::getInstance('Order', 'J2StoreTable');

        $orderpayment->load(array('order_id'=>$order_id) );
        if($orderpayment->order_id == $order_id && !empty($order_id)) {
			$orderpayment->transaction_details  = $transaction_details;
	        $orderpayment->transaction_id       = $data['VPSTxId'];
	        $orderpayment->transaction_status   = $data['Status'].$data['StatusDetail'];

	        if(strtoupper($data['Status']) == 'OK' && $orderpayment->order_state_id == 1 ) {
	        	//order status has already been updated. So return;
	        	return JText::_($this->params->get('onafterpayment', ''));
	        }

			$error = '';
			switch($data['Status']) {

				case 'OK':
					$orderpayment->payment_complete();
					$orderpayment->empty_cart();

				break;

				case 'PENDING':

					// set order to pending. Also notify the customer that it is pending
					$orderpayment->update_status ( 4, true );
					// reduce the order stock. Because the status is pending.
					$orderpayment->reduce_order_stock ();

				break;

				case 'NOTAUTHED':
				case 'MALFORMED':
				case 'INVALID':
				case 'REJECTED':
				case 'AUTHENTICATED':
				case 'REGISTERED':
				case 'ERROR':

					$orderpayment->update_status ( 3, true );

				break;

				case 'ABORT':
					return JText::_($this->params->get('oncancelpayment', ''));
				break;
			}

	        // save the orderpayment
	        if(!$orderpayment->store())
	        {
		    	$error = $orderpayment->getError();
	        }

		}else {
			$error = JText::_('J2STORE_SAGEPAYFORM_MESSAGE_INVALID_ORDERPAYMENTID');
		}

        if ($error) {

        	if(version_compare(JVERSION, '3.0', 'ge')) {
        		$sitename = $config->get('sitename');
        	} else {
        		$sitename = $config->getValue('config.sitename');
        	}
        	//send error notification to the administrators
        	$subject = JText::sprintf('J2STORE_SAGEPAYFORM_EMAIL_PAYMENT_NOT_VALIDATED_SUBJECT', $sitename);
        	$body = JText::sprintf('J2STORE_SAGEPAYFORM_EMAIL_PAYMENT_FAILED_BODY', 'Administrator', $sitename, JURI::root(), $error, $transaction_details);
        	$receivers = $this->_getAdmins();
        	J2Store::email()->_sendErrorEmails($receivers, $subject, $body);
            return JText::_($this->params->get('onerrorpayment', ''));
        }

		return JText::_($this->params->get('onafterpayment', ''));
	}


	private function getDecryptedResponse($string) {
		$strIn = substr($string, 1);
		$strIn = pack('H*', $strIn);
		$decrypt_data = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $this->encryption_key, $strIn, MCRYPT_MODE_CBC, $this->encryption_key);

		//now process data
		parse_str($decrypt_data, $output);
		return $output;
	}


    /**
     * Formatts the payment data for storing
     *
     * @param array $data
     * @return string
     */
    function _getFormattedTransactionDetails( $data )
    {
        $separator = "\n";
        $formatted = array();

        foreach ($data as $key => $value)
        {
            if ($key != 'view' && $key != 'layout')
            {
                $formatted[] = $key . ' = ' . $value;
            }
        }

        return count($formatted) ? implode("\n", $formatted) : '';
    }

    /**
     * Simple logger
     *
     * @param string $text
     * @param string $type
     * @return void
     */
    function _log($text, $type = 'message')
    {
    	if ($this->_isLog) {
    		$file = JPATH_ROOT . "/cache/{$this->_element}.log";
    		$date = JFactory::getDate();

    		$f = fopen($file, 'a');
    		fwrite($f, "\n\n" . $date->format('Y-m-d H:i:s'));
    		fwrite($f, "\n" . $type . ': ' . $text);
    		fclose($f);
    	}
    }

    /**
     * Gets admins data
     *
     * @return array|boolean
     * @access protected
     */
    function _getAdmins()
    {
        $db = JFactory::getDBO();
        $query = $db->getQuery(true);
        $query->select('u.name,u.email');
        $query->from('#__users AS u');
        $query->join('LEFT', '#__user_usergroup_map AS ug ON u.id=ug.user_id');
        $query->where('u.sendEmail = 1');
        $query->where('ug.group_id = 8');

        $db->setQuery($query);
        $admins = $db->loadObjectList();
        if ($error = $db->getErrorMsg()) {
            JError::raiseError(500, $error);
            return false;
        }

        return $admins;
    }
}