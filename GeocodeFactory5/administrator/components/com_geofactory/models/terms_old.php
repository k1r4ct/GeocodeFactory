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

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\LegacyModel;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

class GeofactoryModelTerms extends LegacyModel
{
    public function setTerms($iEnable)
    {
        $config = ComponentHelper::getParams('com_geofactory');
        $config->set('showTerms', $iEnable);

        $componentid = ComponentHelper::getComponent('com_geofactory')->id;
        $table = Table::getInstance('extension');
        $table->load($componentid);
        $table->bind(array('params' => $config->toString()));

        // Controlla eventuali errori
        if (!$table->check()) {
            $this->setError('lastcreatedate: check: ' . $table->getError());
            return false;
        }

        // Salva sul database
        if (!$table->store()) {
            $this->setError('lastcreatedate: store: ' . $table->getError());
            return false;
        }

        // Pulisce la cache del componente.
        $this->cleanCache('com_geofactory');
    }
}
