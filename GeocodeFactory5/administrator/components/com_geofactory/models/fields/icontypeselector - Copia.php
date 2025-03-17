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
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

JFormHelper::loadFieldClass('list');

class JFormFieldIconTypeSelector extends FormFieldList
{
    protected $type = 'iconTypeSelector';

    protected function getOptions()
    {
        $options = array();
        $typeList = $this->form->getValue("typeList");

        PluginHelper::importPlugin('geocodefactory');
        $dispatcher = JDispatcher::getInstance();

        array_push($options, HTMLHelper::_('select.option', '0', Text::_('COM_GEOFACTORY_ICON_DEFAULT')));
        array_push($options, HTMLHelper::_('select.option', '1', Text::_('COM_GEOFACTORY_ICON_IMAGE')));
        array_push($options, HTMLHelper::_('select.option', '2', Text::_('COM_GEOFACTORY_ICON_MAPICON')));

        $avat = false;
        $cat = false;
        $dispatcher->trigger('isIconAvatarEntrySupported', array($typeList, &$avat));
        $dispatcher->trigger('isIconCategorySupported', array($typeList, &$cat));

        if ($avat) {
            array_push($options, HTMLHelper::_('select.option', '3', Text::_('COM_GEOFACTORY_ICON_AVATAR')));
        }
        if ($cat) {
            array_push($options, HTMLHelper::_('select.option', '4', Text::_('COM_GEOFACTORY_ICON_CATEGORY')));
        }
    
        return $options;
    }
}
