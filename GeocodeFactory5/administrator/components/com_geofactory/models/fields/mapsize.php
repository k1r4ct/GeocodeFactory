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

class JFormFieldmapSize extends FormField
{
    protected $type = 'mapSize';

    protected function getInput()
    {
        if ($this->fieldname == "mapwidth") {
            $this->value = (strlen($this->value) > 5) ? $this->value : "width: 200 px";
        } else {
            $this->value = (strlen($this->value) > 5) ? $this->value : "height: 200 px";
        }

        $this->addJs();
        $html = array();
        $html[] = '<div class="input-prepend input-append">';
        if ($this->fieldname == "mapwidth") {
            $html[] = $this->_getSizeSel(array("px", "em", "%", "auto"), array("width:", "max-width:"));
        } else {
            $html[] = $this->_getSizeSel(array("px", "em", "%", "auto"), array("height:", "max-height:"));
        }
        $html[] = '<br /><input readonly="readonly" class="btn-success readonly" type="text" name="' . $this->name . '" id="' . $this->id . '" size="9" value="' . $this->value . '" />';
        $html[] = '</div>';
        return implode('', $html);
    }

    protected function _getSizeSel($units, $modes)
    {
        $vDef = explode(' ', $this->value);
        $vDef = (count($vDef) == 3) ? $vDef : array($modes[0], 200, $units[0]);

        $attr = 'onchange="updateValue(\'' . $this->id . '\');"';
        $attr .= ' class="btn"';
        $attr .= ' style="width:100px;"';

        $options = array();
        foreach ($modes as $mode) {
            $options[] = HTMLHelper::_('select.option', (string)$mode, trim((string)$mode), 'value', 'text');
        }
        $mode = HTMLHelper::_('select.genericlist', $options, $this->id . "_mode", trim($attr), 'value', 'text', $vDef[0], $this->id . "_mode");

        $options = array();
        foreach ($units as $unit) {
            $options[] = HTMLHelper::_('select.option', (string)$unit, trim((string)$unit), 'value', 'text');
        }
        $unit = HTMLHelper::_('select.genericlist', $options, $this->id . "_unit", trim($attr), 'value', 'text', $vDef[2], $this->id . "_unit");

        $attr = 'oninput="updateValue(\'' . $this->id . '\');" onblur="updateValue(\'' . $this->id . '\');"';
        $html = array();
        $html[] = '<div class="btn-group">';
        $html[] = $mode;
        $html[] = '<input type="text" value="' . $vDef[1] . '" class="btn input-mini validate-numeric" name="' . $this->id . '_val" id="' . $this->id . '_val" ' . $attr . '> ';
        $html[] = $unit;
        $html[] = '</div>';
        return implode('', $html);
    }

    protected function addJs()
    {
        $js = array();
        $js[] = "function updateValue(item){";
        $js[] = "    var m = jQuery('#' + item + '_mode').val();";
        $js[] = "    var v = jQuery('#' + item + '_val').val();";
        $js[] = "    var u = jQuery('#' + item + '_unit').val();";
        $js[] = "    if(u=='auto'){ v=''; jQuery('#' + item + '_val').val(''); }";
        $js[] = "    jQuery('#' + item).val(m + ' ' + v + ' ' + u);";
        $js[] = "}";
        GeofactoryHelperAdm::loadJsCode($js);
    }
}
