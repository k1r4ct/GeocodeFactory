<?php
/**
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Cédric Pelloquin aka Rick <info@myJoom.com>
 * @update		Daniele Bellante
 * @website     www.myJoom.com
 */

defined('_JEXEC') or die;

require_once __DIR__ . '/helper.php';
require_once JPATH_ROOT . '/components/com_geofactory/helpers/geofactory.php';
require_once JPATH_ROOT . '/components/com_geofactory/helpers/externalMap.php';
require_once JPATH_ROOT . '/components/com_geofactory/views/map/view.html.php';

$idMap = (int) $params->get('cid');
$zoom   = (int) $params->get('zoom');
$show   = ModGeofactoryMapHelper::checkTasks($params);
$map    = GeofactoryExternalMapHelper::getMap($idMap, 'm', $zoom);

if (!$show) {
    return;
}

if ($show && $map) {
    ModGeofactoryMapHelper::addScript($params);
    $moduleclass_sfx = htmlspecialchars($params->get('moduleclass_sfx'));
    require JModuleHelper::getLayoutPath('mod_geofactory_map', $params->get('layout', 'default'));
}
