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

class JFormFieldMarkerTemplate extends FormField
{
    protected $type = 'markerTemplate';

    protected function getInput()
    {
        // Carichiamo i comportamenti: in Joomla 4 usiamo il modulo Bootstrap per i modali
        HTMLHelper::_('behavior.framework');
        HTMLHelper::_('bootstrap.modal'); // Sostituisce behavior.modal
        HTMLHelper::_('bootstrap.tooltip');

        $this->_addJs();

        // Costruiamo il link per il dialogo modale (rimane invariato)
        $link = 'index.php?option=com_geofactory&amp;view=markerset&amp;layout=placeHolders&amp;tmpl=component&amp;type=' . $this->form->getValue("typeList");
        $html = "\n" . '<div class="input-append"><a class="modal btn" title="' . Text::_('COM_GEOFACTORY_TEMPLATE_TOOLS') . '" href="' . $link . '" rel="{handler: \'iframe\', size: {x: 800, y: 450}}"><i class="icon-list hasTooltip" title="' . Text::_('COM_GEOFACTORY_TEMPLATE_TOOLS') . '"></i> ' . Text::_('JSELECT') . '</a></div>' . "\n";

        return $html;
    }

    protected function _addJs()
    {
        $js = [];
        $js[] = "function addCtrlInTpl(item, code) {";
        $js[] = "    if (code == '_b') { jInsertEditorText(item, 'jform_template_bubble'); }";
        $js[] = "    if (code == '_s') { jInsertEditorText(item, 'jform_template_sidebar'); }";
        // In Joomla 4 SqueezeBox non è più supportato; qui potresti sostituirlo con il metodo per chiudere il modale Bootstrap,
        // oppure se il tuo componente fornisce un wrapper, lascialo.
        $js[] = "    SqueezeBox.close();";
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
