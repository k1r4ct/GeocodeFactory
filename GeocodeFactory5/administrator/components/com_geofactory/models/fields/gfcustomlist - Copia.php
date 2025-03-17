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

JFormHelper::loadFieldClass('list');

class JFormFieldgfCustomList extends FormFieldList
{
    protected $type = 'gfCustomList';

    protected function getOptions()
    {
        $config = ComponentHelper::getParams('com_geofactory');
        $options = array();
        $typeList = $this->form->getValue("typeList");
        PluginHelper::importPlugin('geocodefactory');
        $dispatcher = JDispatcher::getInstance();

        if ($this->fieldname == "custom_list_1") {
            $lab = null;
            $dispatcher->trigger('getCustomList_1', array($typeList, &$options, &$lab));
            if ($lab) {
                $this->element['label'] = $lab;
            }
        }
 
        return $options;
    }
}
