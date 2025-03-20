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

class GeofactoryControllerMarkers extends JControllerLegacy{
	protected $text_prefix = 'COM_GEOFACTORY';
	public function getJson(){
		$app 		= JFactory::getApplication();
		$idMap 		= $app->input->getInt('idmap', -1);
		$model 		= $this->getModel('Markers');
		$json 		= $model->createfile($idMap, 'json') ;
		$config		= JComponentHelper::getParams('com_geofactory');
		$mem 		=  $config->get('largeMarkers', 64) ;

		// prépare la mémoire
		ini_set("memory_limit", $mem."M");
		if ((int) $mem>128)
			set_time_limit(0) ; // illimité !

		// vide le output
		ob_clean();flush();
		echo $json ;
		$app->close();
	}

	function dyncat(){
		$app 		= JFactory::getApplication();
		$idMap 		= $app->input->getInt('idmap', -1);
		$model 		= $this->getModel('Markers');

		$idP		= $app->input->getInt('idP', null);
		$ext		= $app->input->getString('ext', null);
		$mapVar		= $app->input->getString('mapVar', null);
		$model 		= $this->getModel('Markers');

		$select		= $model->getCategorySelect($ext, $idP, $mapVar);

		// vide le output
		ob_clean();flush();
		echo $select ;
		$app->close();
	}
}
