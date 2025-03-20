<?php
/**
 * @name		Geocode Factory
 * @package		geoFactory
 * @copyright	Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author		Cédric Pelloquin aka Rick <info@myJoom.com>
 * @website		www.myJoom.com
 */
defined('JPATH_BASE') or die;
JFormHelper::loadFieldClass('list');

// inclu le field du backend
require_once JPATH_SITE.'/administrator/components/com_geofactory/helpers/geofactory.php';
require_once(JPATH_SITE.'/administrator/components/com_geofactory/models/fields/listmaps.php');

