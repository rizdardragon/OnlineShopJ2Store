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
?>
<div class="product-<?php echo $this->product->j2store_product_id; ?> <?php echo $this->product->product_type; ?>-product">
    <div class="row-fluid">
        <div class="span6">
            <?php $images = $this->loadTemplate('images');
            J2Store::plugin()->event('BeforeDisplayImages', array(&$images, $this, 'com_j2store.products.view.bootstrap'));
            echo $images;
            ?>
        </div>
        <div class="span6">
            <?php echo $this->loadTemplate('title'); ?>
            <?php if(isset($this->product->source->event->afterDisplayTitle)) : ?>
                <?php echo $this->product->source->event->afterDisplayTitle; ?>
            <?php endif;?>


            <?php if($this->params->get('catalog_mode', 0) == 0): ?>

                <form action="<?php echo $this->product->cart_form_action; ?>"
                      method="post" class="j2store-addtocart-form"
                      id="j2store-addtocart-form-<?php echo $this->product->j2store_product_id; ?>"
                      name="j2store-addtocart-form-<?php echo $this->product->j2store_product_id; ?>"
                      data-product_id="<?php echo $this->product->j2store_product_id; ?>"
                      data-product_type="<?php echo $this->product->product_type; ?>"
                      enctype="multipart/form-data">

                    <?php echo $this->loadTemplate('groups'); ?>
                    <?php echo $this->loadTemplate('groupcart'); ?>
                    <?php //echo $this->loadTemplate('cart'); ?>

                </form>
            <?php endif; ?>
            <div class="price-sku-brand-container row-fluid">
                <div class="span6">
                    <?php if(isset($this->product->event->beforeDisplayContent)) : ?>
                        <?php echo $this->product->event->beforeDisplayContent; ?>
                    <?php endif;?>
                </div>
            </div>
        </div>
    </div>

    <?php if($this->params->get('item_use_tabs', 1)): ?>
        <?php echo $this->loadTemplate('tabs'); ?>
    <?php else: ?>
        <?php echo $this->loadTemplate('notabs'); ?>
    <?php endif; ?>

    <?php if(isset($this->product->source->event->afterDisplayContent)) : ?>
        <?php echo $this->product->source->event->afterDisplayContent; ?>
    <?php endif;?>
</div>
<?php if($this->params->get('item_show_product_upsells', 0) && count($this->up_sells)): ?>
    <?php echo $this->loadTemplate('upsells'); ?>
<?php endif;?>

<?php if($this->params->get('item_show_product_cross_sells', 0) && count($this->cross_sells)): ?>
    <?php echo $this->loadTemplate('crosssells'); ?>
<?php endif;?>
