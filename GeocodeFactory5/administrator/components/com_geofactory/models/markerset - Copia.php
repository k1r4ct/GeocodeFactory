<?php
/**
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Cédric Pelloquin aka Rick <info@myJoom.com>
 * @update		Daniele Bellante
 * @website     www.myJoom.com
 */
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

class GeofactoryModelMarkerset extends AdminModel
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
    public function getTable($type = 'Markerset', $prefix = 'GeofactoryTable', $config = array())
    {
        return Table::getInstance($type, $prefix, $config);
    }

    // Method to get the record form.
    public function getForm($data = array(), $loadData = true)
    {
        $form = $this->loadForm('com_geofactory.markerset', 'markerset', array('control' => 'jform', 'load_data' => $loadData));
        if (empty($form)) {
            return false;
        }

        $typeliste = $form->getValue("typeList");
        PluginHelper::importPlugin('geocodefactory');
        $dispatcher = JDispatcher::getInstance();
        $vFields = $dispatcher->trigger('getListFieldsMs', array($typeliste));

        $usefields = null;
        foreach ($vFields as $fs) {
            if (count($fs) != 2) {
                continue;
            }
            if (strtolower($fs[0]) == strtolower($typeliste)) {
                $usefields = $fs[1];
                break;
            }
        }

        $allfields = array();
        if (is_array($usefields)) {
            // Aggiunge alcuni campi comuni
            $usefields[] = "customimage";
            $usefields[] = "avatarSizeW";
            $usefields[] = "avatarSizeH";
            $usefields[] = "mapicon";
            $usefields[] = "maxmarkers";

            foreach ($form->getFieldset("markerset-icon") as $field) {
                $allfields[] = $field->fieldname;
            }
            foreach ($form->getFieldset("markerset-type-settings") as $field) {
                $allfields[] = $field->fieldname;
            }
            foreach ($allfields as $af) {
                if (!in_array($af, $usefields)) {
                    if (!$form->removeField($af, "params_markerset_type_setting"))
                        echo "Unable to hide : {$af}<br />";
                }
            }
        }
        return $form;
    }

    // Method to get the data for the form.
    protected function loadFormData()
    {
        $data = Factory::getApplication()->getUserState('com_geofactory.edit.markerset.data', array());
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

    // Method to get a single record.
    public function getItem($pk = null)
    {
        if ($item = parent::getItem($pk)) {
            $registry = new Registry;
            $registry->loadString($item->params_markerset_settings);
            $item->params_markerset_settings = $registry->toArray();

            $registry = new Registry;
            $registry->loadString($item->params_markerset_radius);
            $item->params_markerset_radius = $registry->toArray();

            $registry = new Registry;
            $registry->loadString($item->params_markerset_icon);
            $item->params_markerset_icon = $registry->toArray();

            $registry = new Registry;
            $registry->loadString($item->params_markerset_type_setting);
            $item->params_markerset_type_setting = $registry->toArray();
        }
        // Carica le mappe (da un'altra tabella)
        $item->idmaps = GeofactoryHelperAdm::getArrayMapsFromMs($item->id);
        return $item;
    }

    // Method to save the form data.
    public function save($data)
    {
        GeofactoryHelperAdm::delCacheFiles($data['id']);
        return parent::save($data);
    }
}
