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

class JFormFieldMapTemplate extends FormField
{
    protected $type = 'mapTemplate';
    protected $target = 'jform_template';

    protected function getInput()
    {
        // Carichiamo i comportamenti necessari: framework, modali (Bootstrap) e tooltip
        HTMLHelper::_('bootstrap.framework');
        HTMLHelper::_('bootstrap.modal'); // Utilizza il sistema modali Bootstrap di Joomla 4
        HTMLHelper::_('bootstrap.tooltip');

        $this->_addJs();

        $link = 'index.php?option=com_geofactory&amp;view=ggmap&amp;layout=placeholders&amp;tmpl=component';
        
        // Utilizziamo il sistema di modali Bootstrap di Joomla 4
        $html = "\n" . '<div class="input-append">
            <button type="button" class="btn" data-bs-toggle="modal" data-bs-target="#mapTemplateModal">
                <i class="icon-list" title="' . Text::_('COM_GEOFACTORY_TEMPLATE_TOOLS') . '"></i> 
                ' . Text::_('JSELECT') . '
            </button>
        </div>' . "\n";
        
        // Aggiungiamo il markup del modale Bootstrap
        $html .= '<div class="modal fade" id="mapTemplateModal" tabindex="-1" aria-labelledby="mapTemplateModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="mapTemplateModalLabel">' . Text::_('COM_GEOFACTORY_TEMPLATE_TOOLS') . '</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <iframe src="' . $link . '" width="100%" height="400" frameborder="0"></iframe>
                    </div>
                </div>
            </div>
        </div>';

        return $html;
    }

    protected function _addJs()
    {
        $js = [];
        $js[] = "function addCtrlInTpl(item) {";
        $js[] = "    window.parent.jInsertEditorText(item, '{$this->target}');";
        // Utilizziamo il sistema di modali Bootstrap di Joomla 4
        $js[] = "    var modalElement = document.querySelector('#mapTemplateModal');";
        $js[] = "    var modal = bootstrap.Modal.getInstance(modalElement);";
        $js[] = "    if (modal) {";
        $js[] = "        modal.hide();";
        $js[] = "    }";
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