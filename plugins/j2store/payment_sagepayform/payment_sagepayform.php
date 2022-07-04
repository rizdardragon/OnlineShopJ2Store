<?php
/*
 * --------------------------------------------------------------------------------
 *  J2Store - SagePay Payment Plugin - Form Integration
 * --------------------------------------------------------------------------------
 * @package		Joomla! 2.5x
 * @subpackage	J2Store
 * @author    	Alagesan http://www.j2store.org
 * @copyright	Copyright (c) 2010 - 2015 J2Store.org. All rights reserved.
 * @license		GNU/GPL license: http://www.gnu.org/copyleft/gpl.html
 * @link		http://j2store.org
 * --------------------------------------------------------------------------------
*/

/** ensure this file is being included by a parent file */
defined('_JEXEC') or die('Restricted access');

require_once (JPATH_ADMINISTRATOR.'/components/com_j2store/library/plugins/payment.php');
require_once (JPATH_ADMINISTRATOR.'/components/com_j2store/version.php');

if(version_compare(J2STORE_VERSION, '3.0.0', 'ge') && version_compare(J2STORE_VERSION, '3.2.0', 'lt')) {
	require_once (JPATH_SITE . '/plugins/j2store/payment_sagepayform/sagepayformv3.php');
} elseif(version_compare(J2STORE_VERSION, '3.2.0', 'ge')) {	
	require_once (JPATH_SITE . '/plugins/j2store/payment_sagepayform/sagepayformv32.php');
} else {
	//our old version
	require_once (JPATH_SITE . '/plugins/j2store/payment_sagepayform/sagepayformv2.php');
}