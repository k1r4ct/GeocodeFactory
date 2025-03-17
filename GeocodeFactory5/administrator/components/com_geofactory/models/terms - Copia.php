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

use Joomla\CMS\MVC\Model\BaseModel;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

class GeofactoryModelTerms extends BaseModel
{
    /**
     * Imposta il valore per la visualizzazione dei termini.
     *
     * @param   integer  $iEnable  Stato da impostare per i termini (es. 0 o 1)
     * @return  boolean  True se tutto è andato a buon fine, false in caso contrario.
     */
    public function setTerms($iEnable)
    {
        // Recupera i parametri del componente e imposta il valore di 'showTerms'
        $config = ComponentHelper::getParams('com_geofactory');
        $config->set('showTerms', $iEnable);

        // Recupera l'ID del componente e carica la configurazione nell'estensione
        $componentid = ComponentHelper::getComponent('com_geofactory')->id;
        $table = Table::getInstance('extension');
        $table->load($componentid);
        $table->bind(array('params' => $config->toString()));

        // Controlla eventuali errori durante il check della tabella
        if (!$table->check()) {
            $this->setError('lastcreatedate: check: ' . $table->getError());
            return false;
        }

        // Salva le modifiche sul database
        if (!$table->store()) {
            $this->setError('lastcreatedate: store: ' . $table->getError());
            return false;
        }

        // Pulisce la cache del componente
        $this->cleanCache('com_geofactory');

        return true;
    }
}
