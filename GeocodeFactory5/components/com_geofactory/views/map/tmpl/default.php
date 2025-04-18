<?php
/**
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Cédric Pelloquin aka Rick <info@myJoom.com>
 * @website     www.myJoom.com
 * @update      Daniele Bellante
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

HTMLHelper::addIncludePath(JPATH_COMPONENT . '/helpers');

// Create shortcuts to some parameters.
/*
$params  = $this->item->params;
$images  = json_decode($this->item->images);
$urls    = json_decode($this->item->urls);
$canEdit = $params->get('access-edit');
$user    = Factory::getUser();
$info    = $params->get('info_block_position', 0);
*/
// HTMLHelper::_('behavior.caption');
$map = $this->item;

// Aggiungi console.log per verificare il caricamento della pagina
Factory::getDocument()->addScriptDeclaration('
    console.log("GeocodeFactory Debug: Caricamento template default.php");
    console.log("GeocodeFactory Debug: ID mappa: ' . $map->id . '");
    console.log("GeocodeFactory Debug: Nome mappa: ' . addslashes($map->name) . '");
    document.addEventListener("DOMContentLoaded", function() {
        console.log("GeocodeFactory Debug: DOM completamente caricato");
        if (document.querySelector(".item-page")) {
            console.log("GeocodeFactory Debug: Container mappa trovato");
        }
    });
');
?>

<div class="item-page<?php echo $this->params->get('pageclass_sfx'); ?>">
    <?php if ($this->params->get('show_page_heading')) : ?>
        <div class="page-header">
            <h1><?php echo $this->escape($this->params->get('page_heading')); ?></h1>
        </div>
    <?php endif; ?>

    <?php if ($this->params->get('show_title') || $this->params->get('show_author')) : ?>
        <div class="page-header">
            <h2>
                <?php if ($this->item->state == 0) : ?>
                    <span class="badge bg-warning text-dark"><?php echo Text::_('JUNPUBLISHED'); ?></span>
                <?php endif; ?>
                <?php if ($this->params->get('show_title')) : ?>
                    <?php echo $this->escape($this->item->title); ?>
                <?php endif; ?>
            </h2>
        </div>
    <?php endif; ?>

    <?php
        echo $map->formatedTemplate;
    ?>
</div>
