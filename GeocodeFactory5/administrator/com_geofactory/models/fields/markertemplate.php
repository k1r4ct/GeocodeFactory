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

class JFormFieldMarkerTemplate extends FormField
{
    protected $type = 'markerTemplate';

    protected function getInput()
    {
        // Carichiamo i comportamenti: in Joomla 4 usiamo il modulo Bootstrap per i modali
        HTMLHelper::_('bootstrap.framework');
        HTMLHelper::_('bootstrap.modal'); // Sostituisce behavior.modal
        HTMLHelper::_('bootstrap.tooltip');

        $this->_addJs();

        // Costruiamo il link per il dialogo modale
        $link = 'index.php?option=com_geofactory&amp;view=markerset&amp;layout=placeHolders&amp;tmpl=component&amp;type=' . $this->form->getValue("typeList");
        
        // Utilizziamo il sistema di modali Bootstrap di Joomla 4
        $html = "\n" . '<div class="input-append">
            <button type="button" class="btn" data-bs-toggle="modal" data-bs-target="#markerTemplateModal">
                <i class="icon-list" title="' . Text::_('COM_GEOFACTORY_TEMPLATE_TOOLS') . '"></i> 
                ' . Text::_('JSELECT') . '
            </button>
        </div>' . "\n";
        
        // Aggiungiamo il markup del modale Bootstrap
        $html .= '<div class="modal fade" id="markerTemplateModal" tabindex="-1" aria-labelledby="markerTemplateModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="markerTemplateModalLabel">' . Text::_('COM_GEOFACTORY_TEMPLATE_TOOLS') . '</h5>
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
        $js[] = "function addCtrlInTpl(item, code) {";
        $js[] = "    if (code == '_b') { jInsertEditorText(item, 'jform_template_bubble'); }";
        $js[] = "    if (code == '_s') { jInsertEditorText(item, 'jform_template_sidebar'); }";
        // In Joomla 4 utilizziamo Bootstrap per chiudere il modale
        $js[] = "    document.querySelector('#markerTemplateModal').addEventListener('hidden.bs.modal', function (event) {";
        $js[] = "        var modalElement = document.querySelector('#markerTemplateModal');";
        $js[] = "        var modal = bootstrap.Modal.getInstance(modalElement);";
        $js[] = "        modal.hide();";
        $js[] = "    });";
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
        
        // Richiama il metodo del helper amministrativo per caricare il codice JS
        GeofactoryHelperAdm::loadJsCode($js);
    }
}