<?php
/**
 *
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
use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;

class JFormFieldFilterGenerator extends FormField
{
    protected $type = 'filterGenerator';

    protected function getInput()
    {
        $fieldsOpt = $this->_getFilterFieldsOptions();
        $operators = $this->_getFilterOperatorsOptions(array('=', '<>', '<', '>', 'LIKE'));
        $attr = 'class="input-medium"';
        $typeList = $this->form->getValue("typeList");

        $title = 'Filter generator help';
        $txt = "The Geocode Factory query will be build with your optional filter, like this sample (your filter in bold) : <br />";
        $jsPlugin = '';
        $config = ComponentHelper::getParams('com_geofactory');
        PluginHelper::importPlugin('geocodefactory');
        
        // Utilizzo del sistema di eventi Joomla 4
        $app = Factory::getApplication();
        
        // Evento getFilterGenerator con prefisso 'on'
        $results = $app->triggerEvent('onGetFilterGenerator', array($typeList, &$jsPlugin, &$txt));
        
        $this->addJs($jsPlugin);

        $html = array();
        $html[] = '<div class="input-prepend input-append">';
        $html[] = HTMLHelper::_('select.genericlist', $fieldsOpt, "gf_filter_generator_fields", $attr, 'value', 'text');
        $html[] = HTMLHelper::_('select.genericlist', $operators, "gf_filter_generator_operator", $attr, 'value', 'text');
        $html[] = '<input type="text" value="value_to_test" class="btn input" id="gf_filter_generator_value"> ';
        $html[] = '<input type="button" value="insert" class="btn input" name="" onClick="insertFilter();"> ';
        $html[] = '</div><br />';
        $html[] = '<div id="gf_filter_generator_help" class="alert alert-info" style="display:none;width:500px;"><h4>' . $title . '</h4><p>' . $txt . '</p></div>';
        $html[] = '<textarea name="' . $this->name . '" id="' . $this->id . '" style="float:left!important;width:500px;height:75px;">';
        $html[] = $this->value;
        $html[] = '</textarea>';
        return implode('', $html);
    }

    protected function _getFilterFieldsOptions()
    {
        $config = ComponentHelper::getParams('com_geofactory');
        $all = true;
        $options = array();
        $typeList = $this->form->getValue("typeList");

        PluginHelper::importPlugin('geocodefactory');
        $app = Factory::getApplication();
        
        // Evento getCustomFieldsForFilter con prefisso 'on'
        $app->triggerEvent('onGetCustomFieldsForFilter', array($typeList, &$options, $all));

        if (count($options) < 1) {
            // Evento getCustomFields con prefisso 'on'
            $app->triggerEvent('onGetCustomFields', array($typeList, &$options, $all));
        }
 
        array_unshift($options, HTMLHelper::_('select.option', '0', Text::_('JSELECT')));
        return $options;
    }

    protected function _getFilterOperatorsOptions($ar)
    {
        $res = array();
        foreach ($ar as $op) {
            $temp = new stdClass();
            $temp->value = $op;
            $temp->text = $op;
            $res[] = $temp;
        }
        return $res;
    }

    protected function addJs($jsPlugin)
    {
        $js = array();
        $js[] = "function insertFilter(){ ";
        $js[] = "    jQuery('#gf_filter_generator_help').show();";
        $js[] = "    var field = jQuery('#gf_filter_generator_fields').val();";
        $js[] = "    var cond = jQuery('#gf_filter_generator_operator').val();";
        $js[] = "    var value = jQuery('#gf_filter_generator_value').val();";
        $js[] = "    var result = '?';";
        $js[] = "    var like = '';";
        $js[] = "    if(cond=='LIKE'){ cond=' LIKE '; like='%'; }";
        $js[] = "    if(field=='0') {alert('Select a value.'); return;}";
        $js[] = $jsPlugin;
        $js[] = "    jQuery('#" . $this->id . "').val(jQuery('#" . $this->id . "').val() + ' ' + result);";
        $js[] = "}";
        
        // Assumiamo che GeofactoryHelperAdm sia disponibile e configurato per Joomla 4
        if (class_exists('GeofactoryHelperAdm') && method_exists('GeofactoryHelperAdm', 'loadJsCode')) {
            GeofactoryHelperAdm::loadJsCode($js);
        } else {
            // Fallback se il metodo non è disponibile
            $document = Factory::getApplication()->getDocument();
            $document->addScriptDeclaration(implode("\n", $js));
        }
    }
}