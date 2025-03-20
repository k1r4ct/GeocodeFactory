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
require_once JPATH_ROOT.'/components/com_geofactory/helpers/geofactoryPlugin.php';

if (!class_exists('GeofactoryHelper')){
class GeofactoryHelper{
	// charge les données de base de la carte (surotut les coordonnées)
	public static function getMap($id){
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('a.* ');
		$query->from('#__geofactory_ggmaps AS a');
		$query->where("id={$id} AND a.state = 1");

		$db->setQuery($query);

		// récupère le premier (normalement le seul) 
		$data = $db->loadObject();
		if (empty($data))
			return null ;

		// Convert parameter fields to objects.
		GeofactoryHelper::mergeRegistry($data, "params_map_mouse");
		GeofactoryHelper::mergeRegistry($data, "params_map_cluster");
		GeofactoryHelper::mergeRegistry($data, "params_map_radius");
		GeofactoryHelper::mergeRegistry($data, "params_additional_data");
		GeofactoryHelper::mergeRegistry($data, "params_map_types");
		GeofactoryHelper::mergeRegistry($data, "params_map_controls");
		GeofactoryHelper::mergeRegistry($data, "params_map_settings");
		GeofactoryHelper::mergeRegistry($data, "params_extra");

		// certaines valeurs de registry peuvent ne pas être définie si elle n'ont pas de valeurs par defaut
		if (!isset($data->kml_file))		$data->kml_file = "" ;
		if (!isset($data->layers))			$data->layers = "0" ;

		// ou certaines sont nouvelles et risquent de planter chez le user (pour l'instant cela n'a pas de sens mais pour le futur oui)
		if (!isset($data->radFormMode))		$data->radFormMode = 0 ;
		if (!isset($data->templateAuto))	$data->templateAuto = 0 ;

		// charge les levels
		if ((!isset($data->level1))OR(strlen($data->level1)<1))	$data->level1 = 'Level 1' ;
		if ((!isset($data->level2))OR(strlen($data->level2)<1))	$data->level2 = 'Level 2' ;
		if ((!isset($data->level3))OR(strlen($data->level3)<1))	$data->level3 = 'Level 3' ;
		if ((!isset($data->level4))OR(strlen($data->level4)<1))	$data->level4 = 'Level 4' ;
		if ((!isset($data->level5))OR(strlen($data->level5)<1))	$data->level5 = 'Level 5' ;
		if ((!isset($data->level6))OR(strlen($data->level6)<1))	$data->level6 = 'Level 6' ;

		return $data ;
	}

	public static function getMs($id) {
		if ($id<1)
			return JError::raiseError(404, JText::_('COM_GEOFACTORY_MS_ERROR_ID'));

		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('a.* ');
		$query->from('#__geofactory_markersets AS a');
		$query->where("id={$id} AND a.state = 1");

		$db->setQuery($query);

		// récupère le premier (normalement le seul) 
		$data = $db->loadObject();
		if (empty($data) OR !isset($data->typeList))
			return null ;

		// si le plugin n'est pas présent ... tant pis
		JPluginHelper::importPlugin('geocodefactory');
		$dsp = JDispatcher::getInstance();
		$pluginOk = false ;
		$dsp->trigger('isPluginInstalled', array($data->typeList, &$pluginOk));
		if (!$pluginOk)
			return null ;

		GeofactoryHelper::mergeRegistry($data, "params_markerset_settings");
		GeofactoryHelper::mergeRegistry($data, "params_markerset_radius");
		GeofactoryHelper::mergeRegistry($data, "params_markerset_icon");
		GeofactoryHelper::mergeRegistry($data, "params_markerset_type_setting");
		GeofactoryHelper::mergeRegistry($data, "params_extra");

		// essaie de traduire le nom
		$data->name = JText::_($data->name);
		
		return $data ;
	}

	public static function getCoordFields($idFieldAssign){
		// Get a level row instance.
		$table = JTable::getInstance('Assign', 'GeofactoryTable');
		if ($idFieldAssign>0 AND $table->load($idFieldAssign))
			return array('lat'=>$table->field_latitude, 'lng'=>$table->field_longitude) ;

		// pas de table ? return default !
		return array('lat'=>0, 'lng'=>0) ;
	}

	public static function getPatternType($idFieldAssign){
		// Get a level row instance.
		$table = JTable::getInstance('Assign', 'GeofactoryTable');
		if ($table->load($idFieldAssign))
			return $table->typeList ;

		// pas de table ? return null !
		return  ;
	}

	public static function mergeRegistry(&$data, $var){
		$registry = new JRegistry;
		$registry->loadString($data->$var);
		$data = (object) array_merge((array) $data, (array) $registry->toArray());

		unset($data->$var);
	}

	// retourne les markersets attachés à la carte
	public static function getCacheFileName($idMap, $itemid) {
		return JPATH_CACHE.DIRECTORY_SEPARATOR."_geoFactory_{$idMap}_{$itemid}.json";
	}

	// retourne les markersets attachés à la carte
	public static function getArrayIdMs($id) {
		if ($id<1)
			return JError::raiseError(404, JText::_('COM_GEOFACTORY_MAP_ERROR_ID'));

		$key2 = 'ordering';
		$config = JComponentHelper::getParams('com_geofactory');
		if ($config->get('msOrdering') == 1)
			$key2 = 'name';


		$data = array() ;
		// recherche tous les MS liés
		$db = JFactory::getDbo();

		$query = $db->getQuery(true);
		$query->select('DISTINCT lmm.id_ms');
		$query->from('#__geofactory_link_map_ms AS lmm');
		$query->join('LEFT', '#__geofactory_markersets AS ms ON lmm.id_ms=ms.id');
		$query->where('id_map='.$id);
		$query->where('ms.state=1');
		$query->order('mslevel,'.$key2);

		$db->setQuery($query);
		$res = $db->loadObjectList();

		if (!is_array($res) OR !count($res))
			return $data;

		// charge et ajoute chaque MS 
		foreach ($res as $v) {
			if ($v->id_ms<1)
				continue ;

			$data[] = $v->id_ms ;
		}

		return $data ;
	}

	public static function _getSelectorImage($list){
		$img = JURI::root().'media/com_geofactory/assets/baloon.png' ;

		if (!isset($list->markerIconType))
			return $img;

		if 		(($list->markerIconType<2 ) && (strlen($list->customimage) > 3)){	$img = JURI::root().$list->customimage ;}
		else if (($list->markerIconType==4) && (strlen($list->customimage) > 3)){	$img = JURI::root().$list->customimage ;}
		else if (($list->markerIconType==4) && (strlen($list->customimage) < 3)){	$img = JURI::root().'media/com_geofactory/assets/category.png' ;}
		else if ($list->markerIconType==2){ 										$img = JURI::root().'media/com_geofactory/mapicons/'.$list->mapicon ;}
		else if ($list->markerIconType==3){ 										$img = JURI::root().'media/com_geofactory/assets/avatar.png' ;}

		return $img;
	}
	
	public static function saveItemContentTale($id, $type, $lat, $lng, $adr=''){
		$db 		= JFactory::getDBO();
		$cond 		= 'type='.$db->Quote($type).' AND id_content='.(int) $id; 
		$query 		= $db->getQuery(true);
		$query->select('COUNT(*)');
		$query->from('#__geofactory_contents');
		$query->where($cond);
		$db->setQuery($query);
		$update 	= $db->loadResult();

		// update or insert 
		$query->clear();
		if ((int) $update > 0){
 			$fields = array('latitude='.(float)$lat,'longitude='.(float)$lng,'address='.$db->quote($adr));
			$query->update($db->quoteName('#__geofactory_contents'))->set($fields)->where($cond);
		} else {
			$values = array($db->quote(''),$db->quote($type), (int)$id, $db->quote($adr), (float)$lat, (float)$lng);
			$query->insert($db->quoteName('#__geofactory_contents'))->values(implode(',', $values));
		}

		$db->setQuery($query);
	    $result = $db->execute();
	}

	public static function isDebugMode() {
		// primo si il a activé le mode...
		$config	= JComponentHelper::getParams('com_geofactory');
		if ((bool) $config->get('isDebug'))
			return true;

		// est-ce que JE force par l'url ?
		$app 	= JFactory::getApplication('site');
		if ((bool) $app->input->getInt('gf_debug', false))
			return true ;

		return false ;
	}

	public static function markerInArea($marker, $vp){
		// controle si il est dans le VP courant.
		if ($marker['lat'] < $vp[0])			return false ; 
		if ($marker['lng'] < $vp[1])			return false ;
		if ($marker['lat'] >= $vp[2])			return false ;
		if ($marker['lng'] >= $vp[3])			return false ;

		return true ;	
	}

	public static function useNewMethod($map){
		// primo si il a activé le mode...
		$config	= JComponentHelper::getParams('com_geofactory');
		if ((int)$config->get('newMethod')<1)
			return false ;

		if ((int)$config->get('newMethod')==2)
			return true ;

		// est-ce que je veux tous les markers ?
		if ((int)$map->centerUser>0)
			return false ;

		return true ;	
	}
}
}