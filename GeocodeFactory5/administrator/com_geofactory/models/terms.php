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
        $component = ComponentHelper::getComponent('com_geofactory');
        $componentid = $component->id;
        
        if (empty($componentid)) {
            $this->setError('Component not found');
            return false;
        }
        
        $table = Table::getInstance('extension');
        
        if (!$table->load($componentid)) {
            $this->setError('Failed to load extension: ' . $table->getError());
            return false;
        }
        
        $table->bind(array('params' => $config->toString()));

        // Controlla eventuali errori durante il check della tabella
        if (!$table->check()) {
            $this->setError('Table check failed: ' . $table->getError());
            return false;
        }

        // Salva le modifiche sul database
        if (!$table->store()) {
            $this->setError('Table store failed: ' . $table->getError());
            return false;
        }

        // Pulisce la cache del componente
        $this->cleanCache('com_geofactory');

        return true;
    }
    
    /**
     * Pulisce la cache per una sezione specifica.
     *
     * @param   string   $group   The cache group
     * @param   integer  $client  The ID of the client
     *
     * @return  void
     */
    public function cleanCache($group = null, $client = 0)
    {
        parent::cleanCache($group, $client);
    }
}