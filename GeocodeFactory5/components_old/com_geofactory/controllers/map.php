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

class GeofactoryControllerMap extends JControllerLegacy{
	protected $text_prefix = 'COM_GEOFACTORY';

	public function getJson(){
		$app 		= JFactory::getApplication();
		$idMap 		= $app->input->getInt('idmap', -1);
		$model 		= $this->getModel('Map');
		$json 		= $model->createfile($idMap) ;

		// vide le output
		ob_clean();flush();
		echo $json ;
		$app->close();
	}

	public function geocodearticle(){
		$app 		= JFactory::getApplication();
		$id 		= $app->input->getInt('idArt', -1) ;
		$lat 		= $app->input->getFloat('lat') ;
		$lng 		= $app->input->getFloat('lng') ;
		$adr 		= $app->input->getString('adr') ;
		$c_opt 		= $app->input->getString('c_opt','com_content') ;

		$db 		= JFactory::getDBO();
		$cond 		= 'type='.$db->Quote($c_opt).' AND id_content='.(int) $id;
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
			$values = array($db->quote(''),$db->quote($c_opt), (int)$id, $db->quote($adr), (float)$lat, (float)$lng);
			$query->insert($db->quoteName('#__geofactory_contents'))->values(implode(',', $values));
		}

		$db->setQuery($query);
	    $result = $db->execute();
		$app->close();
	}

	public function getJs(){
		$app 		= JFactory::getApplication();
		$idMap 		= $app->input->getInt('idmap', -1);
		$model 		= $this->getModel('Map');
		$model->set_loadFull(true);
		$map 		= $model->getItem($idMap);
		$root 		= JURI::root() ;

		// peut etre différent et defini selon contexte de la carte (par exemple, pour CB profile, il est définit dans le plugin idem pour module)
		$map->mapInternalName 	= $app->input->getString('mn');
		$map->forceZoom   		= $app->input->getInt('zf');
		$map->gf_curCat			= $app->input->getInt('gfcc');
		$map->gf_zoomMeId		= $app->input->getInt('zmid');
		$map->gf_zoomMeType 	= $app->input->getString('tmty');

		// parametres de l'url à charger
		$urlParams = array() ;
		$urlParams[] = 'idmap='.	$map->id ;
		$urlParams[] = 'mn='.		$map->mapInternalName ;
		$urlParams[] = 'zf='.		$map->forceZoom ;
		$urlParams[] = 'gfcc='.		$map->gf_curCat;
		$urlParams[] = 'zmid='.		$map->gf_zoomMeId;
		$urlParams[] = 'tmty='.		$map->gf_zoomMeType;
		$urlParams[] = 'code='.		rand(1,100000) ;

		$dataMap = implode('&',$urlParams) ;

		$js			= array('');
		$js[] 		= '';
		$jsVarName 	= $map->mapInternalName ;
		$js[] = "var {$jsVarName}=new clsGfMap2();" ;

		$js[] = " var gf_sr = '{$root}';" ;
		$js[] = "function init2_{$jsVarName}(){";
//		$js[] = "sleepMulti(repos);" ;
		$js[] = 'jQuery.getJSON('.$jsVarName.'.getMapUrl("'.$dataMap.'"),function(data){' ;
			$js[] = "if (!{$jsVarName}.checkMapData(data)){document.getElementById('{$jsVarName}').innerHTML = 'Map error.'; console.log('Bad map format given in init_{$jsVarName}().'); return ;} " ;
			$js[] = " {$jsVarName}.setMapInfo(data, '{$jsVarName}');" ;

			GeofactoryModelMap::_setKml($jsVarName, $js, $map->kml_file);
			GeofactoryModelMap::_loadTiles($jsVarName, $js, $map);
			GeofactoryModelMap::_loadDynCatsFromTmpl($jsVarName, $js, $map) ;

		$js[] = " });" ; // getJSON
		$js[] = "}" ; //initGfCarte

		// est-ce que j'ai des infos de radius (depuis les module, radius search, ...)

		$app 	= JFactory::getApplication('site');
		$adre 	= $app->input->getString('gf_mod_search', '');
		$adre 	= htmlspecialchars(str_replace(['"','`'], '', $adre), ENT_QUOTES, 'UTF-8');
		$dist 	= $app->input->getFloat('gf_mod_radius', 1);

		// on utilise ce qu'on a trouvé ou non...
		$js[] = " var gf_mod_search = '{$adre}';" ;
		$js[] = " var gf_mod_radius = {$dist};" ;

		// données uniques plus besoins de passer par URL ???
		$js[] = " var gfcc = {$map->gf_curCat};" ;
		$js[] = " var zmid = {$map->gf_zoomMeId};" ;
		$js[] = " var tmty = '{$map->gf_zoomMeType}';" ;


		// a mettre dans helper
		$files = array(	'components/com_geofactory/assets/js/header.js',
						'components/com_geofactory/assets/js/cls.dynRad.js',
						'components/com_geofactory/assets/js/cls.label.js',
						'components/com_geofactory/assets/js/cls.places.js',
						'components/com_geofactory/assets/js/cls.objects.js',
						'components/com_geofactory/assets/js/cls.layers.js',
						'components/com_geofactory/assets/js/cls.controller.js',
						);

		foreach ($files as $file){
			if (file_exists(JPATH_ROOT . '/' . $file)){
				$times[] = filemtime(JPATH_ROOT . '/' . $file);

				$js[] = '/* '.$file.' */' ;
				$js[] = file_get_contents(JPATH_ROOT . '/' . $file);
			}
		}

	//	$js[] = "jQuery(document).ready(function() {init2_{$jsVarName}();});" ;
		$js[] = "google.maps.event.addDomListener(window, 'load', init2_{$jsVarName});" ;

		$sep = "" ;
		if (GeofactoryHelper::isDebugMode())
			$sep = "\n" ;

		// vide le output
		ob_end_clean();
		header("Content-type: application/javascript; charset: utf-8");

		echo implode($sep, $js) ;

		$app->close();
	}
}
