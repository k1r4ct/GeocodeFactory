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

class GeofactoryModelAccueil extends AdminModel
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
        
        return $form;
    }

    protected function loadFormData()
    {
        // Potresti implementare il caricamento dei dati qui se necessario
    }

    protected function prepareTable($table)
    {
        // Preparazione della tabella prima del salvataggio, se necessario
    }
}
