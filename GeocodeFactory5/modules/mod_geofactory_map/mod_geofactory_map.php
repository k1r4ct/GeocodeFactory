<?php
/**
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013-2023 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Cédric Pelloquin aka Rick <info@myJoom.com>
 * @update      Daniele Bellante, Aggiornato per Joomla 4.4.10
 * @website     www.myJoom.com
 */

defined('_JEXEC') or die;

use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

require_once __DIR__ . '/helper.php';
require_once JPATH_ROOT . '/components/com_geofactory/helpers/geofactory.php';
require_once JPATH_ROOT . '/components/com_geofactory/helpers/externalMap.php';
require_once JPATH_ROOT . '/components/com_geofactory/views/map/view.html.php';

// Ottieni i parametri
$idMap = (int) $params->get('cid');
$zoom  = (int) $params->get('zoom');

// Verifica se mostrare la mappa
$show = ModGeofactoryMapHelper::checkTasks($params);

if (!$show) {
    return;
}

// Ottieni la mappa
try {
    $map = GeofactoryExternalMapHelper::getMap($idMap, 'm', $zoom);

    if ($map) {
        // Aggiungi gli script necessari
        ModGeofactoryMapHelper::addScript($params);
        
        // Prepara il suffisso classe per il template
        $moduleclass_sfx = htmlspecialchars($params->get('moduleclass_sfx', ''), ENT_QUOTES, 'UTF-8');
        
        // Carica il template
        require ModuleHelper::getLayoutPath('mod_geofactory_map', $params->get('layout', 'default'));
    } else {
        if (Factory::getApplication()->get('debug', 0)) {
            echo Text::sprintf('MOD_GEOFACTORY_MAP_NOT_FOUND', $idMap);
        }
    }
} catch (\Exception $e) {
    if (Factory::getApplication()->get('debug', 0)) {
        echo Text::sprintf('MOD_GEOFACTORY_MAP_ERROR', $e->getMessage());
    }
}