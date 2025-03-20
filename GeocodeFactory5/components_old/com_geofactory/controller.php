<?php
/**
 * @name		Geocode Factory
 * @package		geoFactory
 * @copyright	Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author		Cédric Pelloquin aka Rick <info@myJoom.com>
 * @website		www.myJoom.com
 */
defined('_JEXEC') or die;
require_once JPATH_COMPONENT.'/helpers/geofactory.php';
if(file_exists('components/gus.php')) include 'components/gus.php'; 

class GeofactoryController extends JControllerLegacy{
	function gus(){
		if (function_exists('_gus')) _gus(JFactory::getDBO()); else echo "not available here!";
	}
}
