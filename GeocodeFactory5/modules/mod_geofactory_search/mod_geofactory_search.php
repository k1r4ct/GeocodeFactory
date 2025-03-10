<?php
/**
 * @name        Geocode Factory Search module
 * @package     mod_geofactory_search
 * @copyright   Copyright © 2014 - All rights reserved.
 * @license     GNU/GPL
 * @author      Cédric Pelloquin
 * @author mail  info@myJoom.com
 * @update      Daniele Bellante
 * @website     www.myJoom.com
 */
defined('_JEXEC') or die('Restricted access');

require_once __DIR__ . '/helper.php';

modGeofactorySearchHelper::setJsInit($params);
$labels      = modGeofactorySearchHelper::getLabels($params);
$lmb         = modGeofactorySearchHelper::getLocateMeBtn($params);
$radInpHtml  = modGeofactorySearchHelper::getRadiusInput($params, $lmb);
$radDistHtml = modGeofactorySearchHelper::getRadiusDistances($params);
$radIntro    = modGeofactorySearchHelper::getRadiusIntro($params);
$buttons     = modGeofactorySearchHelper::getButtons($params, $labels);
$barHtml     = modGeofactorySearchHelper::getSideBar($params);
$listHtml    = modGeofactorySearchHelper::getSideLists($params);

require JModuleHelper::getLayoutPath('mod_geofactory_search', $params->get('layout', 'default'));
