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
require_once (JPATH_ADMINISTRATOR.'/components/com_j2store/library/prices.php');
require_once (JPATH_SITE.'/components/com_j2store/helpers/utilities.php');

class plgJ2StorePayment_sagepayform extends J2StorePaymentPlugin
{
	/**
	 * @var $_element  string  Should always correspond with the plugin's filename,
	 *                         forcing it to be unique
	 */
    var $_element    = 'payment_sagepayform';
    var $_isLog      = false;
    var $_j2version = null;
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
		$this->_j2version = $this->getVersion();
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
		$params = JComponentHelper::getParams('com_j2store');

        // prepare the payment form

        $vars = new JObject();
        $vars->order_id = $data['order_id'];
        $vars->orderpayment_id = $data['orderpayment_id'];
        JTable::addIncludePath(JPATH_ADMINISTRATOR.'/components/com_j2store/tables');
        $order = JTable::getInstance('Orders', 'Table');
        $order->load($data['orderpayment_id']);
        $currency_values= $this->getCurrency($order);

        $vars->currency_code =$currency_values['currency_code'];
        $vars->orderpayment_amount = $this->getAmount($order->orderpayment_amount, $currency_values['currency_code'], $currency_values['currency_value'], $currency_values['convert']);

        $vars->orderpayment_type = $this->_element;

        $vars->cart_session_id = JFactory::getSession()->getId();

        $vars->display_name = $this->params->get('display_name', 'PLG_J2STORE_PAYMENT_SAGEPAYFORM');
        $vars->onbeforepayment_text = $this->params->get('onbeforepayment', '');
        $vars->button_text = $this->params->get('button_text', 'J2STORE_PLACE_ORDER');


        $items = $order->getItems();

		$products = array();
        foreach ($items as $item)
        {
        	$desc = $item->orderitem_name;

        	//sku
        	if (!empty($item->orderitem_sku))
        	{
        		$desc .= ' | '.JText::_('J2STORE_SKU').' '.$item->orderitem_sku;
        	}

        	//productoptions
        	if (!empty($item->orderitem_attribute_names)) {
        		//first convert from JSON to array
        		if( version_compare( $this->_j2version, '2.5.0', 'lt' ) ) {
        			$desc .=' | '.$item->orderitem_attribute_names;
        		} else {
	        		$registry = new JRegistry;
	        		$registry->loadString(stripslashes($item->orderitem_attribute_names), 'JSON');
	        		$product_options = $registry->toObject();
	        		if(count($product_options) >0 ) {
	        			foreach ($product_options as $option) {
	        				$desc .=' | '.$option->name.' - '.$option->value;
	        			}
        			}
        		}
        	}
        	$products[]= array(
        			'name'		=>html_entity_decode($desc,ENT_QUOTES, 'UTF-8'),
        			'number' 	=>$item->product_id,
        			'quantity'	=>$item->orderitem_quantity,
        			'price'		=> $this->getAmount($item->orderitem_final_price / $item->orderitem_quantity, $currency_values['currency_code'], $currency_values['currency_value'], $currency_values['convert'])
        			);

        	$item->_description = $desc;
        }
        $vars->products = $products;
        $vars->tax_cart = $this->getAmount($order->order_tax, $currency_values['currency_code'], $currency_values['currency_value'], $currency_values['convert']);

        $handling_cost = $order->order_shipping + $order->order_shipping_tax + $order->order_surcharge;

        $vars->handling_cart = $this->getAmount($handling_cost, $currency_values['currency_code'], $currency_values['currency_value'], $currency_values['convert']);
        $vars->discount_amount_cart = $this->getAmount($order->order_discount, $currency_values['currency_code'], $currency_values['currency_value'], $currency_values['convert']);

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
        $vars->first_name   = $data['orderinfo']['billing_first_name'];
        $vars->last_name    = $data['orderinfo']['billing_last_name'];
        $vars->email        = $data['orderinfo']['user_email'];
        $vars->address_1    = $data['orderinfo']['billing_address_1'];
        $vars->address_2    = $data['orderinfo']['billing_address_2'];
        $vars->city         = $data['orderinfo']['billing_city'];
        $vars->country      = $this->getCountryCode($data['orderinfo']['billing_country_id'])->country_isocode_2;
        $vars->region       = substr($this->getZoneCode($data['orderinfo']['billing_zone_id'])->zone_code, 0, 2);
        $vars->zip = $data['orderinfo']['billing_zip'];
        $vars->phone = !empty($data['orderinfo']['billing_phone_1'])?$data['orderinfo']['billing_phone_1']:$data['orderinfo']['billing_phone_2'];

        if(isset($order->invoice_number) && $order->invoice_number > 0) {
        	$invoice_number = $order->invoice_prefix.$order->invoice_number;
        }else {
        	$invoice_number = $order->id;
        }
        $vars->invoice = $invoice_number;


        //shipping
        if($data['orderinfo']['shipping_first_name']) {
        	$vars->delivery_first_name   = $data['orderinfo']['shipping_first_name'];
        }else {
        	$vars->delivery_first_name   = $data['orderinfo']['billing_first_name'];
        }

        if($data['orderinfo']['shipping_last_name']) {
        	$vars->delivery_last_name    = $data['orderinfo']['shipping_last_name'];
        }else {
        	$vars->delivery_last_name    = $data['orderinfo']['billing_last_name'];
        }

        if($data['orderinfo']['shipping_address_1']) {
        	$vars->delivery_address_1    = $data['orderinfo']['shipping_address_1'];
        }else {
        	$vars->delivery_address_1    = $data['orderinfo']['billing_address_1'];
        }


        if($data['orderinfo']['shipping_address_2']) {
        	$vars->delivery_address_2    = $data['orderinfo']['shipping_address_2'];
        }else {
        	$vars->delivery_address_2    = $data['orderinfo']['billing_address_2'];
        }

        if($data['orderinfo']['shipping_city']) {
        	$vars->delivery_city         = $data['orderinfo']['shipping_city'];
        }else {
        	$vars->delivery_city         = $data['orderinfo']['billing_city'];
        }

        if($data['orderinfo']['shipping_country_name']) {
        	$vars->delivery_country      = $this->getCountryCode($data['orderinfo']['shipping_country_id'])->country_isocode_2;
        }else {
        	$vars->delivery_country      = $vars->country;
        }

        if($data['orderinfo']['shipping_zone_name']) {
        	$vars->delivery_region      = substr($this->getZoneCode($data['orderinfo']['shipping_zone_id'])->zone_code, 0, 2);
        }else {
        	$vars->delivery_region      = $vars->region;
        }

        if($data['orderinfo']['shipping_zip']) {
        	$vars->delivery_zip     = $data['orderinfo']['shipping_zip'];
        }else {
        	$vars->delivery_zip 	= $data['orderinfo']['billing_zip'];
        }

        if($data['orderinfo']['shipping_phone_1']) {
        	$vars->delivery_phone       = $data['orderinfo']['shipping_phone_1'];
        }else {
        	$vars->delivery_phone       = $data['orderinfo']['billing_phone_1'];
        }

        //in case we dont have phone_1

        if(empty($data['orderinfo']['shipping_phone_1']) && empty($data['orderinfo']['billing_phone_1']) ) {
        	if($data['orderinfo']['shipping_phone_2']) {
        		$vars->delivery_phone       = $data['orderinfo']['shipping_phone_2'];
        	}else {
        		$vars->delivery_phone       = $data['orderinfo']['billing_phone_2'];
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
        	$basket .= 'Tax:---:---:---:---:'.$vars->tax_cart.':';
        	$basket .= 'Shipping and Handling:---:---:---:---:'.$vars->handling_cart.':';
        	$basket .= 'Discount:---:---:---:---:'.$vars->discount_amount_cart;
			//$value['Basket'] = $basket;
        	$value['AllowGiftAid'] = '0';
        	$value['Apply3DSecure'] = '0';
        	$value['VendorData'] = $invoice_number;
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
        JTable::addIncludePath( JPATH_ADMINISTRATOR.'/components/com_j2store/tables' );
        $orderpayment = JTable::getInstance('Orders', 'Table');

        $orderpayment->load(array('order_id'=>$order_id) );
        if($orderpayment->order_id == $order_id && !empty($order_id)) {
			$orderpayment->transaction_details  = $transaction_details;
	        $orderpayment->transaction_id       = $data['VPSTxId'];
	        $orderpayment->transaction_status   = $data['Status'].$data['StatusDetail'];

	        if(strtoupper($data['Status']) == 'OK' && $orderpayment->order_state_id == 1 ) {
	        	//order status has already been updated. So return;
	        	return JText::_($this->params->get('onafterpayment', ''));
	        }

	        if($orderpayment->order_state_id == 1 || $orderpayment->order_state_id == 7) {
	        	return JText::_($this->params->get('onafterpayment', ''));
	        }

			$sendEmail = false;
			$error = '';
			switch($data['Status']) {

				case 'OK':
					$orderpayment->order_state = trim(JText::_('J2STORE_CONFIRMED')); // CONFIRMED
					$orderpayment->order_state_id = 1; //CONFIRMED
					$sendEmail = true;
					JLoader::register( 'J2StoreHelperCart', JPATH_SITE.'/components/com_j2store/helpers/cart.php');
					$session = JFactory::getSession();
	           		$session->set('j2store_cart', array());
				break;

				case 'PENDING':
					$orderpayment->order_state = trim(JText::_('J2STORE_PENDING'));
					$orderpayment->order_state_id = 4;
				break;

				case 'NOTAUTHED':
				case 'MALFORMED':
				case 'INVALID':
				case 'REJECTED':
				case 'AUTHENTICATED':
				case 'REGISTERED':
				case 'ERROR':
					$orderpayment->order_state = trim(JText::_('J2STORE_FAILED'));
					$orderpayment->order_state_id = 3;
				break;

				case 'ABORT':
					return JText::_($this->params->get('oncancelpayment', ''));
				break;
			}

	        // save the orderpayment
	        if($orderpayment->save())
	        {
				if($orderpayment->order_state_id == 1) {
					// let us inform the user that the payment is successful
					require_once (JPATH_SITE.'/components/com_j2store/helpers/orders.php');
					J2StoreOrdersHelper::sendUserEmail($orderpayment->user_id, $orderpayment->order_id, $orderpayment->order_state, $orderpayment->order_state, $orderpayment->order_state_id);
				}
			} else {
	        	$error = $orderpayment->getError();
	        }

		}else {
			$error = JText::_('J2STORE_SAGEPAYFORM_MESSAGE_INVALID_ORDERPAYMENTID');
		}

        if ($error) {
            // send an emails to site's administrators with error messages
            $this->_sendErrorEmails($error, $transaction_details);
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
     * Sends error messages to site administrators
     *
     * @param string $message
     * @param string $paymentData
     * @return boolean
     * @access protected
     */
    function _sendErrorEmails($message, $paymentData)
    {
        $mainframe = JFactory::getApplication();

        // grab config settings for sender name and email
        $config     = JComponentHelper::getParams('com_j2store');
        $mailfrom   = $config->get( 'emails_defaultemail', $mainframe->getCfg('mailfrom') );
        $fromname   = $config->get( 'emails_defaultname', $mainframe->getCfg('fromname') );
        $sitename   = $config->get( 'sitename', $mainframe->getCfg('sitename') );
        $siteurl    = $config->get( 'siteurl', JURI::root() );

        $recipients = $this->_getAdmins();
        $mailer = JFactory::getMailer();

        $subject = JText::sprintf('J2STORE_SAGEPAYFORM_EMAIL_PAYMENT_NOT_VALIDATED_SUBJECT', $sitename);

        foreach ($recipients as $recipient)
        {
            $mailer = JFactory::getMailer();
            $mailer->addRecipient( $recipient->email );

            $mailer->setSubject( $subject );
            $mailer->setBody( JText::sprintf('J2STORE_SAGEPAYFORM_EMAIL_PAYMENT_FAILED_BODY', $recipient->name, $sitename, $siteurl, $message, $paymentData) );
            $mailer->setSender(array( $mailfrom, $fromname ));
            $sent = $mailer->send();
        }

        return true;
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

    function getCurrency($order) {
		$results = array();
		$convert = false;
		$params = JComponentHelper::getParams('com_j2store');

    	if( version_compare( $this->_j2version, '2.6.7', 'lt' ) ) {
    		$currency_code = $params->get('currency_code', 'GBP');
    		$currency_value = 1;
    	} else {

    		include_once (JPATH_ADMINISTRATOR.'/components/com_j2store/library/base.php');
    		$currencyObject = J2StoreFactory::getCurrencyObject();

    		$currency_code = $order->currency_code;
    		$currency_value = $order->currency_value;
    	}
    	$results['currency_code'] = $currency_code;
    	$results['currency_value'] = $currency_value;
    	$results['convert'] = $convert;

    	return $results;
    }

    function getAmount($value, $currency_code, $currency_value, $convert=false) {

    	if( version_compare( $this->_j2version, '2.6.7', 'lt' ) ) {
    		return J2StoreUtilities::number( $value, array( 'thousands'=>'', 'num_decimals'=>'2', 'decimal'=>'.') );
    	} else {
    		include_once (JPATH_ADMINISTRATOR.'/components/com_j2store/library/base.php');
    		$currencyObject = J2StoreFactory::getCurrencyObject();
    		$amount = $currencyObject->format($value, $currency_code, $currency_value, false);
    		return $amount;
    	}

    }

    function getVersion() {

    	if(is_null($this->_j2version)) {
    		$xmlfile = JPATH_ADMINISTRATOR.'/components/com_j2store/manifest.xml';
    		$xml = JFactory::getXML($xmlfile);
    		$this->_j2version=(string)$xml->version;
    	}
    	return $this->_j2version;
    }


    function getOrderPaymentID($order_id) {

    	$db = JFactory::getDbo();
    	$query = $db->getQuery(true)->select('id')->from('#__j2store_orders')->where('order_id='.$db->q($order_id));
    	$db->setQuery($query);
    	return $db->loadResult();

    }

    function getCountryCode($country_id) {

    	$db = JFactory::getDbo();
    	$query = $db->getQuery(true);
    	$query->select('*')->from('#__j2store_countries')->where('country_id='.$db->q($country_id));
    	$db->setQuery($query);
    	return $db->loadObject();
    }

    function getZoneCode($zone_id) {
    	$db = JFactory::getDbo();
    	$query = $db->getQuery(true);
    	$query->select('*')->from('#__j2store_zones')->where('zone_id='.$db->q($zone_id));
    	$db->setQuery($query);
    	return $db->loadObject();
    }
}
