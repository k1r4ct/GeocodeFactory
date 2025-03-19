<?php
/**
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Cédric Pelloquin aka Rick <info@myJoom.com>
 * @update      Daniele Bellante
 * @website     www.myJoom.com
 */

defined('_JEXEC') or die;

use Joomla\CMS\Helper\ModuleHelper;

require_once __DIR__ . '/helper.php';
require_once JPATH_ROOT . '/components/com_geofactory/helpers/geofactory.php';
require_once JPATH_ROOT . '/components/com_geofactory/helpers/externalMap.php';
require_once JPATH_ROOT . '/components/com_geofactory/views/map/view.html.php';

$idMap = (int) $params->get('cid');
$zoom  = (int) $params->get('zoom');
$show  = ModGeofactoryMapHelper::checkTasks($params);

if (!$show) {
    return;
}

$map = GeofactoryExternalMapHelper::getMap($idMap, 'm', $zoom);

if ($map) {
    ModGeofactoryMapHelper::addScript($params);
    $moduleclass_sfx = htmlspecialchars($params->get('moduleclass_sfx'), ENT_QUOTES, 'UTF-8');
    require ModuleHelper::getLayoutPath('mod_geofactory_map', $params->get('layout', 'default'));
}
