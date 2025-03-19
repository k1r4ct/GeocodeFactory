<?php
/**
 * @name        Geocode Factory Installer Script
 * @package     geoFactory
 * @copyright   Copyright © 2013
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      ...
 * @website     www.myJoom.com
 * @update      Daniele Bellante
 */

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;

class com_geoFactoryInstallerScript
{
    public function postflight($route, $adapter)
    {
        // Leggi parametri esistenti
        $config = ComponentHelper::getParams('com_geofactory');
        $config->set('showTerms', 1);

        // Aggiorna la voce in #__extensions
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('params') . ' = ' . $db->quote((string) $config))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_geofactory'));

        $db->setQuery($query);
        $db->execute();
    }

    public function install($adapter)
    {
        $db = Factory::getDbo();
        $db->setQuery('CREATE TABLE IF NOT EXISTS ' . $db->quoteName('#__geofactory_contents') . ' (
            id INTEGER NOT NULL AUTO_INCREMENT,
            type varchar(255) NOT NULL default "",
            id_content int(11) NOT NULL,
            address TEXT default NULL,
            latitude varchar(255) NOT NULL default "",
            longitude varchar(255) NOT NULL default "",
            PRIMARY KEY (id)
        ) CHARSET=utf8;');
        $db->execute();
    }

    public function update($adapter)
    {
        $this->addDbField('language', '#__geofactory_markersets', 'VARCHAR(255)', "NOT NULL DEFAULT '*'");
        $this->addDbField('language', '#__geofactory_ggmaps', 'VARCHAR(255)', "NOT NULL DEFAULT '*'");
        $this->addDbField('mslevel', '#__geofactory_markersets', 'int(11)', "NOT NULL DEFAULT '0'");

        self::deleteOldJs('map_api');
        self::deleteOldJs('markerclusterer');

        echo '<p>Update successfully!</p>';
    }

    protected static function deleteOldJs($baseName)
    {
        $pathJs = JPATH_SITE . '/components/com_geofactory/assets/js/';
        $files  = glob($pathJs . "{$baseName}*.js");

        if (!$files) {
            return;
        }

        // Trova la versione "più grande"
        $bigger = '';
        foreach ($files as $filename) {
            $cur = substr($filename, -10); // es: map_api-5150215.js
            if ($filename === $pathJs . "{$baseName}.js") {
                continue;
            }
            if (strlen($cur) !== 10) {
                continue;
            }
            $curVal = (int) substr($cur, 0, 7);
            if ($bigger === '') {
                $bigger = $curVal;
            }
            if ($curVal >= $bigger) {
                $bigger = $curVal;
            }
        }

        // Elimina tutte le versioni meno recenti
        foreach ($files as $filename) {
            if ($filename === $pathJs . "{$baseName}-{$bigger}.js") {
                continue;
            }
            File::delete($filename);
        }
    }

    protected function addDbField($field, $table, $type, $default)
    {
        $db = Factory::getDbo();
        $db->setQuery("SHOW COLUMNS FROM {$table} LIKE " . $db->quote($field));
        $exists = $db->loadObjectList();

        if (!count($exists)) {
            $db->setQuery("ALTER TABLE {$table} ADD `{$field}` {$type} {$default}");
            $db->execute();
        }
    }
}
