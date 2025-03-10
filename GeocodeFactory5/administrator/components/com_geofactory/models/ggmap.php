<?php
/**
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Cédric Pelloquin aka Rick <info@myJoom.com>
 * @update      Daniele Bellante
 * @website     www.myJoom.com
 */
defined('_JEXEC') or die;

require_once JPATH_COMPONENT . '/helpers/geofactory.php';

use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;

class GeofactoryModelGgmap extends AdminModel
{
    protected function canDelete($record)
    {
        if (!empty($record->id)) {
            if ($record->state != -2) {
                return;
            }
            $user = Factory::getUser();
            return $user->authorise('core.delete', 'com_geofactory');
        }
    }

    protected function canEditState($record)
    {
        $user = Factory::getUser();
        return $user->authorise('core.edit.state', 'com_geofactory');
    }

    // Returns a reference to the Table object.
    public function getTable($type = 'Ggmap', $prefix = 'GeofactoryTable', $config = array())
    {
        return Table::getInstance($type, $prefix, $config);
    }

    // Method to get the record form.
    public function getForm($data = array(), $loadData = true)
    {    
        $form = $this->loadForm('com_geofactory.ggmap', 'ggmap', array('control' => 'jform', 'load_data' => $loadData));
        if (empty($form)) {
            return false;
        }
        return $form;
    }

    // Method to get a single record.
    public function getItem($pk = null)
    {
        if ($item = parent::getItem($pk)) {
            $registry = new Registry;
            $registry->loadString($item->params_map_cluster);
            $item->params_map_cluster = $registry->toArray();

            $registry = new Registry;
            $registry->loadString($item->params_map_radius);
            $item->params_map_radius = $registry->toArray();

            $registry = new Registry;
            $registry->loadString($item->params_additional_data);
            $item->params_additional_data = $registry->toArray();

            $registry = new Registry;
            $registry->loadString($item->params_map_types);
            $item->params_map_types = $registry->toArray();

            $registry = new Registry;
            $registry->loadString($item->params_map_controls);
            $item->params_map_controls = $registry->toArray();

            $registry = new Registry;
            $registry->loadString($item->params_map_settings);
            $item->params_map_settings = $registry->toArray();

            $registry = new Registry;
            $registry->loadString($item->params_map_mouse);
            $item->params_map_mouse = $registry->toArray();
        }
        return $item;
    }

    // Method to get the data for the form.
    protected function loadFormData()
    {
        $data = Factory::getApplication()->getUserState('com_geofactory.edit.ggmap.data', array());
        if (empty($data)) {
            $data = $this->getItem();
        }
        return $data;
    }

    // Prepare and sanitize the table data prior to saving.
    protected function prepareTable($table)
    {
        $table->name = htmlspecialchars_decode($table->name, ENT_QUOTES);
    }

    // Method to save the form data.
    public function save($data)
    {
        GeofactoryHelperAdm::delCacheFiles($data['id']);
        return parent::save($data);
    }
}
