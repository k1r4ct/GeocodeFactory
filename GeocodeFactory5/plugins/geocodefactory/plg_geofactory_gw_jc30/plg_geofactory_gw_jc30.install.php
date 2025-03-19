<?php
/**
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013-2023 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Cédric Pelloquin <info@myJoom.com>
 * @website     www.myJoom.com
 * @update      Daniele Bellante, Aggiornato per Joomla 4.4.10
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Installer\InstallerScript;
use Joomla\Database\DatabaseInterface;

/**
 * Script di installazione per il plugin Geocode Factory gateway
 */
class PlgGeocodefactoryPlg_geofactory_gw_jc30InstallerScript extends InstallerScript
{
    /**
     * Nome del plugin
     *
     * @var string
     */
    protected $plgName = "plg_geofactory_gw_jc30";

    /**
     * Metodo eseguito durante l'aggiornamento
     *
     * @param   object  $parent  Oggetto installatore
     * @return  void
     */
    public function update($parent)
    {
        // Esegue la procedura di installazione per garantire l'attivazione del plugin
        $this->install($parent);

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        // Verifica se esistono record da convertire
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__geofactory_contents'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('joomla_content'));

        $db->setQuery($query);
        $nbr = $db->loadResult();

        // Se ci sono record da convertire
        if ($nbr > 0) {
            // Aggiorna i record vecchi al nuovo formato
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__geofactory_contents'))
                ->set($db->quoteName('type') . ' = ' . $db->quote('com_content'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('joomla_content'));
            
            $db->setQuery($query);
            $db->execute();
            
            // Mostra messaggio di conversione
            echo '<div class="alert alert-info">' . Text::_('PLG_GEOFACTORY_GW_JC30_MIGRATION_COMPLETE') . '</div>';
        }
    }

    /**
     * Metodo eseguito durante l'installazione
     *
     * @param   object  $parent  Oggetto installatore
     * @return  void
     */
    public function install($parent)
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        
        $tableExtensions = $db->quoteName('#__extensions');
        $columnElement   = $db->quoteName('element');
        $columnType      = $db->quoteName('type');
        $columnEnabled   = $db->quoteName('enabled');

        // Attiva automaticamente il plugin
        $query = $db->getQuery(true)
            ->update($tableExtensions)
            ->set($columnEnabled . ' = 1')
            ->where($columnElement . ' = ' . $db->quote($this->plgName))
            ->where($columnType . ' = ' . $db->quote('plugin'));
        
        $db->setQuery($query);
        $db->execute();

        echo '<div class="alert alert-success">' . Text::sprintf('PLG_GEOFACTORY_PLUGIN_ENABLED', Text::_($this->plgName)) . '</div>';
    }
}