<?php
/**
 *
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Cédric Pelloquin <info@myJoom.com>
 * @website     www.myJoom.com
 *
 */
// No direct access to this file
defined('_JEXEC') or die('Restricted access');

class plgcontentplg_geofactory_content_jc30InstallerScript{
    var $m_plgName = "plg_geofactory_content_jc30" ;

    function update($parent) { 
        $this->install($parent);
    }

    function install($parent) { 
        $db = JFactory::getDbo();
        $tableExtensions = $db->quoteName("#__extensions");
        $columnElement   = $db->quoteName("element");
        $columnType      = $db->quoteName("type");
        $columnEnabled   = $db->quoteName("enabled");

        // Enable plugin
        $db->setQuery("UPDATE   $tableExtensions 
                        SET     $columnEnabled=1 
                        WHERE   $columnElement='{$this->m_plgName}' 
                        AND     $columnType='plugin'");
        $db->execute();

        echo JText::sprintf('PLG_GEOFACTORY_PLUGIN_ENABLED', JText::_($this->m_plgName));
    } 
}
?>