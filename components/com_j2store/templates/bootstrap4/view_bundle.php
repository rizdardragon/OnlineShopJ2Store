<?php
/**
 * --------------------------------------------------------------------------------
 *  Bundle Products
 * --------------------------------------------------------------------------------
 * @package     Joomla 3.x
 * @subpackage  J2 Store
 * @author      Alagesan, J2Store <support@j2store.org>
 * @copyright   Copyright (c) 2018 J2Store . All rights reserved.
 * @license     GNU GPL v3 or later
 * @link        http://j2store.org
 * --------------------------------------------------------------------------------
 *
 * */
defined('_JEXEC') or die;
$bundleproducts = $this->product->params->get('bundleproduct',array());
?>
<?php if($bundleproducts):?>
	<div class="bundleproducts">
		<table class="table table-bordered table-striped">
			<thead>
			<tr>
				<th><?php echo JText::_ ( 'J2STORE_BUNDLE_PRODUCTS' )?></th>
			</tr>
			</thead>
			<tbody>
			<?php foreach ($bundleproducts as $bundleproduct):?>
				<tr>
					<td><?php echo isset( $bundleproduct->product_name ) ? $bundleproduct->product_name: '';?></td>
				</tr>
			<?php endforeach;?>
			</tbody>
		</table>
	</div>
<?php endif;?>