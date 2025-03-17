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

use Joomla\CMS\Form\FormFieldList;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormHelper;

FormHelper::loadFieldClass('list');

class JFormFieldgfCustomRadio extends FormFieldList
{
    protected $type = 'gfCustomRadio';

    protected function getOptions()
    {
        $config = ComponentHelper::getParams('com_geofactory');
        $options = array();
        $typeList = $this->form->getValue("typeList");
        
        PluginHelper::importPlugin('geocodefactory');
        $app = Factory::getApplication();

        if ($this->fieldname == "custom_radio_1") {
            $lab = null;
            $app->triggerEvent('onGetCustomRadio_1', array($typeList, &$options, &$lab));
            if ($lab) {
                $this->element['label'] = $lab;
            }
        }

        if ($this->fieldname == "custom_radio_2") {
            $lab = null;
            $app->triggerEvent('onGetCustomRadio_2', array($typeList, &$options, &$lab));
            if ($lab) {
                $this->element['label'] = $lab;
            }
        }
 
        return $options;
    }
}