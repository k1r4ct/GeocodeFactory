<?php
/**
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Cédric Pelloquin <info@myJoom.com>
 * @website     www.myJoom.com
 * @update      Daniele Bellante
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseDriver;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerScript;
use Joomla\CMS\Language\Text;

class plggeocodefactoryPlg_geofactory_gw_jc30InstallerScript extends InstallerScript
{
    protected $m_plgName = 'plg_geofactory_gw_jc30';

    public function update($parent)
    {
        // Esegue la procedura di installazione per garantire l'attivazione del plugin
        $this->install($parent);

        /** @var DatabaseDriver $db */
        $db = Factory::getContainer()->get(DatabaseDriver::class);

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__geofactory_contents'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('joomla_content'));

        $db->setQuery($query);
        $nbr = $db->loadResult();

        if ($nbr > 0) {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__geofactory_contents'))
                ->set($db->quoteName('type') . ' = ' . $db->quote('com_content'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('joomla_content'));
            
            $db->setQuery($query);
            $db->execute();
        }
    }

    public function install($parent)
    {
        /** @var DatabaseDriver $db */
        $db = Factory::getContainer()->get(DatabaseDriver::class);
        
        $tableExtensions = $db->quoteName('#__extensions');
        $columnElement   = $db->quoteName('element');
        $columnType      = $db->quoteName('type');
        $columnEnabled   = $db->quoteName('enabled');

        $query = $db->getQuery(true)
            ->update($tableExtensions)
            ->set($columnEnabled . ' = 1')
            ->where($columnElement . ' = ' . $db->quote($this->m_plgName))
            ->where($columnType . ' = ' . $db->quote('plugin'));
        
        $db->setQuery($query);
        $db->execute();

        echo Text::sprintf('PLG_GEOFACTORY_PLUGIN_ENABLED', Text::_($this->m_plgName));
    }
}
