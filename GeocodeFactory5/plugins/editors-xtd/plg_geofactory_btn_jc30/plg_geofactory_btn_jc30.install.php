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

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

class plgeditorsxtdplg_geofactory_btn_jc30InstallerScript
{
    protected $m_plgName = "plg_geofactory_btn_jc30";

    public function update($parent)
    {
        $this->install($parent);
    }

    public function install($parent)
    {
        $db = Factory::getDbo();
        $tableExtensions = $db->quoteName("#__extensions");
        $columnElement   = $db->quoteName("element");
        $columnType      = $db->quoteName("type");
        $columnEnabled   = $db->quoteName("enabled");

        $query = "UPDATE $tableExtensions 
                  SET $columnEnabled = 1 
                  WHERE $columnElement = '" . $this->m_plgName . "'
                  AND $columnType = 'plugin'";
        $db->setQuery($query);
        $db->execute();

        echo "<br /><p>" . Text::sprintf('PLG_GEOFACTORY_PLUGIN_ENABLED', $this->m_plgName) . " You can manage your <a href='index.php?option=com_plugins&view=plugins&filter_folder=geocodefactory'>Geocode Factory plugins here</a></p><br />";
    }
}
