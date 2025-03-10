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

defined('JPATH_BASE') or die;

use Joomla\CMS\Form\FormField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

class JFormFieldCustomtiles extends FormField
{
    protected $type = 'customTiles';

    protected function getInput()
    {
        $js = array();
        $js[] = "function insertNewTile(url, name, zoom, desc, png, size) {
                var sep = '|';
                if (name.length < 1) { alert('Please enter a tile name !'); return; }
                if (url.length < 1) { alert('Please enter a tile url !'); return; }
                if (desc.length < 1) { desc = name; }
                var res = url + sep + name + sep + zoom + sep + desc + sep + png + sep + size + '; ';
                jQuery('#{$this->id}').val(jQuery('#{$this->id}').val() + res);
                SqueezeBox.close();
            }
            function insertSampleTile(tile) {
                jQuery('#{$this->id}').val(jQuery('#{$this->id}').val() + tile);
                SqueezeBox.close();
            }
            ";
        GeofactoryHelperAdm::loadJsCode($js);

        $link = 'index.php?option=com_geofactory&amp;view=ggmap&amp;layout=customtiles&amp;tmpl=component';

        $ret = "";
        $ret .= '<textarea name="' . $this->name . '" id="' . $this->id . '" style="float:left!important;width:50%;height:75px;">';
        $ret .= $this->value;
        $ret .= '</textarea>';
        $ret .= '<div class="input-append">';
        $ret .= ' <a class="modal btn" title="' . Text::_('COM_GEOFACTORY_TEMPLATE_TOOLS') . '" href="' . $link . '" rel="{handler: \'iframe\', size: {x: 800, y: 450}}"><i class="icon-list hasTooltip" title="' . Text::_('COM_GEOFACTORY_TEMPLATE_TOOLS') . '"></i> ' . Text::_('JSELECT') . '</a>';
        $ret .= '</div>';
        return $ret;
    }
}
