<?php
/**
 * --------------------------------------------------------------------------------
 *  Group Products
 * --------------------------------------------------------------------------------
 * @package     Joomla 3.x
 * @subpackage  J2 Store
 * @author      Alagesan, J2Store <support@j2store.org>
 * @copyright   Copyright (c) 2016 J2Store . All rights reserved.
 * @license     GNU GPL v3 or later
 * @link        http://j2store.org
 * --------------------------------------------------------------------------------
 *
 * */
defined('_JEXEC') or die;
$document = JFactory::getDocument();
$document->addScript(JUri::root(true).'/plugins/j2store/app_groupproducts/app_groupproducts/js/groupproduct.js');
$show_main_price = $this->product->app_params->get('show_main_price',0);
$show_checkbox = $this->product->app_params->get('show_checkbox',0);
?>
<?php
if(isset($this->product->product_list) && count ( $this->product->product_list )):
    ?>
    <table class="table table-striped table-bordered">
        <tr>
            <?php if($show_checkbox):?>
                <th><?php echo JText::_('J2STORE_ADD_TO_CART');?></th>
            <?php endif;?>
            <th><?php echo JText::_('J2STORE_PRODUCT_NAME');?></th>
            <?php if($this->params->get('list_show_product_sku', 1)) : ?>
                <th><?php echo JText::_('J2STORE_SKU');?></th>
            <?php endif;?>
            <?php if($this->params->get('list_show_product_base_price', 1) || $this->params->get('list_show_product_special_price', 1)): ?>
                <th><?php echo JText::_('J2STORE_PRODUCT_PRICE');?></th>
            <?php endif;?>
            <?php if($this->params->get('list_show_discount_percentage', 1)): ?>
                <th><?php echo JText::_('J2STORE_DISCOUNT_SETTINGS');?></th>
            <?php endif;?>
            <?php if($this->params->get('show_qty_field', 1)): ?>
                <th><?php echo JText::_('J2STORE_QUANTITY');?></th>
            <?php endif;?>
        </tr>
        <?php

        foreach($this->product->product_list as $sub_product):
            ?>
            <tr>
                <?php if($show_checkbox):?>
                    <td>
                        <input type="checkbox" name="subproduct[<?php echo $sub_product->j2store_product_id;?>][groupcheck]" value="1"/>
                    </td>
                <?php else: ?>
                    <input style="display: none" type="checkbox" name="subproduct[<?php echo $sub_product->j2store_product_id;?>][groupcheck]" checked="checked" value="1"/>
                <?php endif; ?>
                <td>
                    <?php echo $sub_product->product_name; ?>
                </td>
                <?php if($this->params->get('list_show_product_sku', 1)) : ?>
                    <td>
                        <?php if(!empty($sub_product->variant->sku)) : ?>
                            <div class="product-sku">
                                <span class="sku-text"><?php echo JText::_('J2STORE_SKU')?></span>
                                <span class="sku"> <?php echo $sub_product->variant->sku; ?> </span>
                            </div>
                        <?php endif; ?>
                    </td>
                <?php endif; ?>
                <?php if($this->params->get('list_show_product_base_price', 1) || $this->params->get('list_show_product_special_price', 1)): ?>
                    <td>
                        <?php echo J2Store::plugin()->eventWithHtml('BeforeRenderingProductPrice', array($sub_product)); ?>
                        <div class="product-price-container">
                            <?php if($this->params->get('list_show_product_base_price', 1) && $sub_product->pricing->base_price != $sub_product->pricing->price): ?>
                                <?php $class='';?>
                                <?php if(isset($sub_product->pricing->is_discount_pricing_available)) $class='strike'; ?>
                                <div class="base-price <?php echo $class?>">
                                    <?php echo J2Store::product()->displayPrice($sub_product->pricing->base_price, $sub_product, $this->params);?>
                                </div>
                            <?php endif; ?>

                            <?php if($this->params->get('list_show_product_special_price', 1)): ?>
                                <div class="sale-price">
                                    <?php echo J2Store::product()->displayPrice($sub_product->pricing->price, $sub_product, $this->params);?>
                                </div>
                            <?php endif; ?>

                            <?php if($this->params->get('display_price_with_tax_info', 0) ): ?>
                                <div class="tax-text">
                                    <?php echo J2Store::product()->get_tax_text(); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php echo J2Store::plugin()->eventWithHtml('AfterRenderingProductPrice', array($sub_product)); ?>
                    </td>
                <?php endif; ?>
                <?php if($this->params->get('list_show_discount_percentage', 1)): ?>
                    <td>
                        <?php if(isset($sub_product->pricing->is_discount_pricing_available) && !empty($sub_product->pricing->base_price)):?>
                            <?php $discount =(1 - ($sub_product->pricing->price / $sub_product->pricing->base_price) ) * 100; ?>
                            <?php if($discount > 0): ?>
                                <div class="discount-percentage-<?php echo $sub_product->j2store_product_id;?>">
                                    <?php  echo round($discount).' % '.JText::_('J2STORE_PRODUCT_OFFER');?>
                                </div>
                            <?php endif; ?>
                        <?php endif;?>
                    </td>
                <?php endif; ?>
                <?php if($this->params->get('show_qty_field', 1)): ?>
                    <td>
                        <input id="subproduct_qty_<?php echo $sub_product->j2store_product_id;?>" type="number" min="0" name="subproduct[<?php echo $sub_product->j2store_product_id;?>][quantity]" onkeyup="doAjaxGroupPrice('<?php echo $this->product->j2store_product_id?>','#subproduct_qty_<?php echo $sub_product->j2store_product_id;?>');" onchange="doAjaxGroupPrice('<?php echo $this->product->j2store_product_id?>','#subproduct_qty_<?php echo $sub_product->j2store_product_id;?>');" class="input-mini" value="<?php echo $sub_product->quantity;?>">
                    </td>
                <?php else: ?>
                    <input id="subproduct_qty_<?php echo $sub_product->j2store_product_id;?>" type="hidden" min="0" name="subproduct[<?php echo $sub_product->j2store_product_id;?>][quantity]" onkeyup="doAjaxGroupPrice('<?php echo $this->product->j2store_product_id?>','#subproduct_qty_<?php echo $sub_product->j2store_product_id;?>');" onchange="doAjaxGroupPrice('<?php echo $this->product->j2store_product_id?>','#subproduct_qty_<?php echo $sub_product->j2store_product_id;?>');" class="input-mini" value="<?php echo $sub_product->quantity;?>">
                <?php endif; ?>
            </tr>
            <?php
        endforeach;
        ?>
        <?php if($show_main_price):?>
            <span class="total-price-<?php echo $this->product->j2store_product_id;?>" style="font-size: 1.4em"><strong><?php echo $this->currency->format($this->product->total_price);?></strong></span>
        <?php endif;?>
    </table>
    <?php
endif;