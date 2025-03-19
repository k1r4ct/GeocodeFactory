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

use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Language\Text;

class GeofactoryModelAssign extends AdminModel
{
    public function getTable($type = 'Assign', $prefix = 'GeofactoryTable', $config = array())
    {
        return Table::getInstance($type, $prefix, $config);
    }

    public function getForm($data = array(), $loadData = true)
    {
        // Get the form.
        $form = $this->loadForm('com_geofactory.assign', 'assign', array('control' => 'jform', 'load_data' => $loadData));
        if (empty($form)) {
            return false;
        }
        
        $typeliste = $form->getValue("typeList");
        // Se non si sta caricando i dati ma si sta salvando
        if (!$loadData) {
            $typeliste = $data["typeList"];
        }

        // Importa i plugin del gruppo 'geocodefactory'
        PluginHelper::importPlugin('geocodefactory');
        $app = Factory::getApplication();
        $vFields = $app->triggerEvent('getListFieldsAssign', array($typeliste));

        // Prepara il form in base al tipo di lista
        $usefields = null;
        foreach ($vFields as $fs) {
            if (count($fs) != 2) {
                continue;
            }
            if (strtolower($fs[0]) == strtolower($typeliste)) {
                // Trovati i campi utilizzabili
                $usefields = $fs[1];
                break;
            }
        }

        $allfields = array();
        if (is_array($usefields)) {
            // Ricerca tutti i campi disponibili nel fieldset "assign-address"
            foreach ($form->getFieldset("assign-address") as $field) {
                $allfields[] = $field->fieldname;
            }
            // Rimuove dal form i campi non utilizzati
            foreach ($allfields as $af) {
                if (!in_array($af, $usefields)) {
                    $form->removeField($af, "assign-address");
                }
            }
        }

        return $form;
    }

    protected function loadFormData()
    {
        // Controlla la sessione per eventuali dati inseriti precedentemente nel form.
        $data = Factory::getApplication()->getUserState('com_geofactory.edit.assign.data', array());
        if (empty($data)) {
            $data = $this->getItem();
        }
        return $data;
    }

    protected function prepareTable($table)
    {
        $table->name = htmlspecialchars_decode($table->name, ENT_QUOTES);
    }
}