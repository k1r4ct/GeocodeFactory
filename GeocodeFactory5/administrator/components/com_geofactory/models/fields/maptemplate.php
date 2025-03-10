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

class JFormFieldMapTemplate extends FormField
{
    protected $type = 'mapTemplate';
    protected $target = 'jform_template';

    protected function getInput()
    {
        // Carichiamo i comportamenti necessari: framework, modali (Bootstrap) e tooltip
        HTMLHelper::_('behavior.framework');
        HTMLHelper::_('bootstrap.modal'); // Utilizza il sistema modali Bootstrap di Joomla 4
        HTMLHelper::_('bootstrap.tooltip');

        $this->_addJs();

        $link = 'index.php?option=com_geofactory&amp;view=ggmap&amp;layout=placeholders&amp;tmpl=component';
        $html = "\n" . '<div class="input-append"><a class="modal btn" title="' . Text::_('COM_GEOFACTORY_TEMPLATE_TOOLS') . '" href="' . $link . '" rel="{handler: \'iframe\', size: {x: 800, y: 450}}"><i class="icon-list hasTooltip" title="' . Text::_('COM_GEOFACTORY_TEMPLATE_TOOLS') . '"></i> ' . Text::_('JSELECT') . '</a></div>' . "\n";

        return $html;
    }

    protected function _addJs()
    {
        $js = [];
        $js[] = "function addCtrlInTpl(item) {";
        $js[] = "    window.parent.jInsertEditorText(item, '{$this->target}');";
        // Anche qui, SqueezeBox è deprecato in Joomla 4; valuta se sostituirlo con il metodo di chiusura dei modali Bootstrap
        $js[] = "    window.parent.SqueezeBox.close();";
        $js[] = "}";
        $js[] = "jQuery(document).ready(function() {";
        $js[] = "    jQuery('#pan_buttons').hide();";
        $js[] = "    jQuery('#tog_buttons').click(function() {";
        $js[] = "        jQuery('#pan_buttons').slideToggle();";
        $js[] = "    });";
        $js[] = "    jQuery('#pan_sample').hide();";
        $js[] = "    jQuery('#tog_sample').click(function() {";
        $js[] = "        jQuery('#pan_sample').slideToggle();";
        $js[] = "    });";
        $js[] = "});";
        GeofactoryHelperAdm::loadJsCode($js);
    }
}
