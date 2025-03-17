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
use Joomla\CMS\Factory;

class JFormFieldCustomtiles extends FormField
{
    protected $type = 'customTiles';

    protected function getInput()
    {
        $js = array();
        $js[] = "function insertNewTile(url, name, zoom, desc, png, size) {";
        $js[] = "    var sep = '|';";
        $js[] = "    if (name.length < 1) { alert('Please enter a tile name !'); return; }";
        $js[] = "    if (url.length < 1) { alert('Please enter a tile url !'); return; }";
        $js[] = "    if (desc.length < 1) { desc = name; }";
        $js[] = "    var res = url + sep + name + sep + zoom + sep + desc + sep + png + sep + size + '; ';";
        $js[] = "    jQuery('#{$this->id}').val(jQuery('#{$this->id}').val() + res);";
        $js[] = "    // Chiude il modale Bootstrap";
        $js[] = "    var modalElement = document.querySelector('#customTilesModal');";
        $js[] = "    var modal = bootstrap.Modal.getInstance(modalElement);";
        $js[] = "    if (modal) {";
        $js[] = "        modal.hide();";
        $js[] = "    }";
        $js[] = "}";
        $js[] = "function insertSampleTile(tile) {";
        $js[] = "    jQuery('#{$this->id}').val(jQuery('#{$this->id}').val() + tile);";
        $js[] = "    // Chiude il modale Bootstrap";
        $js[] = "    var modalElement = document.querySelector('#customTilesModal');";
        $js[] = "    var modal = bootstrap.Modal.getInstance(modalElement);";
        $js[] = "    if (modal) {";
        $js[] = "        modal.hide();";
        $js[] = "    }";
        $js[] = "}";
        
        GeofactoryHelperAdm::loadJsCode($js);

        $link = 'index.php?option=com_geofactory&amp;view=ggmap&amp;layout=customtiles&amp;tmpl=component';

        $ret = "";
        $ret .= '<textarea name="' . $this->name . '" id="' . $this->id . '" style="float:left!important;width:50%;height:75px;">';
        $ret .= $this->value;
        $ret .= '</textarea>';
        
        // Utilizziamo il sistema di modali Bootstrap di Joomla 4
        $ret .= '<div class="input-append">
            <button type="button" class="btn" data-bs-toggle="modal" data-bs-target="#customTilesModal">
                <i class="icon-list" title="' . Text::_('COM_GEOFACTORY_TEMPLATE_TOOLS') . '"></i> 
                ' . Text::_('JSELECT') . '
            </button>
        </div>';
        
        // Aggiungiamo il markup del modale Bootstrap
        $ret .= '<div class="modal fade" id="customTilesModal" tabindex="-1" aria-labelledby="customTilesModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="customTilesModalLabel">' . Text::_('COM_GEOFACTORY_TEMPLATE_TOOLS') . '</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <iframe src="' . $link . '" width="100%" height="400" frameborder="0"></iframe>
                    </div>
                </div>
            </div>
        </div>';
        
        return $ret;
    }
}