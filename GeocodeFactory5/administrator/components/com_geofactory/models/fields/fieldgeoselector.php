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

use Joomla\CMS\Form\FormFieldList;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

JFormHelper::loadFieldClass('list');

class JFormFieldfieldGeoSelector extends FormFieldList
{
    protected $type = 'fieldGeoSelector';

    protected function getOptions()
    {
        $options = array();
        $typeList = $this->form->getValue("typeList");
        $singelGps = false;

        $config = ComponentHelper::getParams('com_geofactory');
        $all = (bool) $config->get('useAllFields');

        PluginHelper::importPlugin('geocodefactory');
        $dispatcher = JDispatcher::getInstance();
        $dispatcher->trigger('getIsSingleGpsField', array($typeList, &$singleGps));

        if ($singleGps && $this->fieldname == "field_longitude") {
            array_unshift($options, HTMLHelper::_('select.option', '-1', Text::_('COM_GEOFACTORY_NOT_NEEDED')));
            return $options;
        }

        $dispatcher->trigger('getCustomFieldsCoord', array($typeList, &$options, $all));
 
        return $options;
    }
}
