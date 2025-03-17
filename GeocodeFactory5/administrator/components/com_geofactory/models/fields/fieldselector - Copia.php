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

class JFormFieldFieldSelector extends FormFieldList
{
    protected $type = 'fieldSelector';

    protected function getOptions()
    {
        $config = ComponentHelper::getParams('com_geofactory');
        $all = (bool) $config->get('useAllFields');
        $options = array();
        $typeList = $this->form->getValue("typeList");

        PluginHelper::importPlugin('geocodefactory');
        $dispatcher = JDispatcher::getInstance();

        if ($this->fieldname == "avatarImage") {
            $dispatcher->trigger('getCustomFieldsImages', array($typeList, &$options));
        }

        if (count($options) < 1) {
            $dispatcher->trigger('getCustomFields', array($typeList, &$options, $all));
        }
    
        if ($this->default == "username" && ($typeList == 'MS_CB' || $typeList == 'MS_JS')) {
            array_unshift($options, HTMLHelper::_('select.option', 'Username', 'Username'));
            array_unshift($options, HTMLHelper::_('select.option', 'Name', 'Name'));
        }
 
        array_unshift($options, HTMLHelper::_('select.option', '0', Text::_('JSELECT')));
        return $options;
    }
}
