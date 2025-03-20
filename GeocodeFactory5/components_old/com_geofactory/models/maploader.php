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

class GeofactoryModelMaploader extends JModelItem {
	
	public function getItem($pk = null){
		$app = JFactory::getApplication('site');

		// recherche l'idmap passé par JS (et pas par le menu Joomla !!!)
		$idMap		= $app->input->getInt('idMap', null);

		$map = GeofactoryGgmapHelper::getMap($idMap);
		$ms = GeofactoryGgmapHelper::getArrayIdMs($idMap) ;
		$this->_createDataFile($out, $idsMs, $map) ;

		$app->close();
	}
}
