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
 * Script di installazione per il plugin button
 */
class PlgEditorsXtdPlg_geofactory_btn_jc30InstallerScript extends InstallerScript
{
    /**
     * Nome del plugin
     *
     * @var string
     */
    protected $plgName = "plg_geofactory_btn_jc30";

    /**
     * Metodo eseguito durante l'aggiornamento
     *
     * @param   object  $parent  Oggetto installatore
     * @return  void
     */
    public function update($parent)
    {
        $this->install($parent);
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
        
        $tableExtensions = $db->quoteName("#__extensions");
        $columnElement   = $db->quoteName("element");
        $columnType      = $db->quoteName("type");
        $columnEnabled   = $db->quoteName("enabled");

        // Attiva automaticamente il plugin
        $query = $db->getQuery(true)
            ->update($tableExtensions)
            ->set($columnEnabled . ' = 1')
            ->where($columnElement . ' = ' . $db->quote($this->plgName))
            ->where($columnType . ' = ' . $db->quote('plugin'));
            
        $db->setQuery($query);
        $db->execute();

        // Mostra un messaggio di successo
        echo '<div class="alert alert-success">' . Text::sprintf('PLG_GEOFACTORY_PLUGIN_ENABLED', Text::_($this->plgName)) . 
             ' <a href="index.php?option=com_plugins&view=plugins&filter_folder=geocodefactory" class="btn btn-sm btn-primary">' . 
             Text::_('PLG_GEOFACTORY_MANAGE_PLUGINS') . '</a></div>';
    }
}