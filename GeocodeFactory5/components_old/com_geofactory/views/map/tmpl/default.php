<?php
/**
 * @name		Geocode Factory
 * @package		geoFactory
 * @copyright	Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author		Cédric Pelloquin aka Rick <info@myJoom.com>
 * @website		www.myJoom.com
 */
defined('_JEXEC') or die;
JHtml::addIncludePath(JPATH_COMPONENT . '/helpers');

// Create shortcuts to some parameters.
/*
$params  = $this->item->params;
$images  = json_decode($this->item->images);
$urls    = json_decode($this->item->urls);
$canEdit = $params->get('access-edit');
$user    = JFactory::getUser();
$info    = $params->get('info_block_position', 0);*/
JHtml::_('behavior.caption');
$map 	= $this->item;

?>



<div class="item-page<?php echo $this->params->get('pageclass_sfx') ;?>">
	<?php if ($this->params->get('show_page_heading') ) : ?>
		<div class="page-header">
			<h1> <?php echo $this->escape($this->params->get('page_heading')); ?> </h1>
		</div>
	<?php endif;?>
	<?php if ($this->params->get('show_title') || $this->params->get('show_author')) : ?>
		<div class="page-header">
			<h2>
				<?php if ($this->item->state == 0) : ?>
					<span class="label label-warning"><?php echo JText::_('JUNPUBLISHED'); ?></span>
				<?php endif; ?>
				<?php if ($this->params->get('show_title')) : ?>
					<?php echo $this->escape($this->item->title); ?>
				<?php endif; ?>
			</h2>
		</div>
	<?php endif; ?>
	<?php
		echo $map->formatedTemplate ;
	?>
</div>
