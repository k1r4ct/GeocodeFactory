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

// langue pour les textes 
$lang = JFactory::getLanguage();
$lang->load('com_geofactory');

class GeofactoryExternalMapHelper{
	// $context 'm' = (m)odule, (cbp)rofile, ...
	public static function getMap($id, $context, $zoom=0){
		JModelLegacy::addIncludePath(JPATH_ROOT.'/components/com_geofactory/models', 'GeofactoryModel');

		$idMap			= (int) $id ;
		if ($idMap<1){
			echo "No selected map in settings ({$context})!" ;
			return null ;
		}

		$model 			= JModelLegacy::getInstance('Map', 'GeofactoryModel', array('ignore_request' => true));
		$model->setMapContext($context) ;
		$map 			= $model->getItem($idMap);
		$map->forceZoom = (int) $zoom ;

		// prépare une pseudo-view 
		$view = new GeofactoryViewMap() ;
		$view->initView($map);
		$view->_prepareDocument() ;

		return $map ;
	}

	// par defaut essaie de lire les coordonnées dans les champs, sinon, on essaie de géocoder
	static public function getProfileMap($fLat, $fLng, $mapvar,$idMap, $idU, $lib=false){
		JModelLegacy::addIncludePath(JPATH_ROOT.'/components/com_geofactory/models', 'GeofactoryModel');
		if ($idMap<1)
			return "@admin: no selected map in CB plugin settings!" ;

		$doc = JFactory::getDocument();

		// charge la carte objet map
		$model 			= JModelLegacy::getInstance('Map', 'GeofactoryModel', array('ignore_request' => true));
		$model->setMapContext('cbp') ;
		$map 			= $model->getItem($idMap);

		// parametres de la carte
		$width 	= strlen($map->mapwidth)>5?$map->mapwidth:"width:200px"; 
		$height	= strlen($map->mapheight)>5?$map->mapheight:"height:200px"; 
		$size = str_replace(' ', '', $width .";".$height.";");
		$center = $map->centerlat.','.$map->centerlng ;

		$zoom = ((int)$map->mapsZoom)>0?((int)$map->mapsZoom):17;

 		$type = 'google.maps.MapTypeId.ROADMAP';
		if (($map->mapTypeOnStart == "ROADMAP") OR ($map->mapTypeOnStart == "SATELLITE") OR ($map->mapTypeOnStart == "HYBRID") OR ($map->mapTypeOnStart == "TERRAIN"))
			$type = 'google.maps.MapTypeId.'.$map->mapTypeOnStart;

		// charge les MS attachés
		$idMs = GeofactoryHelper::getArrayIdMs($map->id) ;

		// et trouve le premier type CB
		$cbMs = null;
 		foreach($idMs as $ms){
 			$list = GeofactoryHelper::getMs($ms) ;
 			if (!$list)
 				continue ;

 			if ($list->typeList=='MS_CB'){
 				$cbMs = $list;
 				break ;
 			}
 		}


		$js = "
			var gf_sr = '{JURI::root()}';
			var {$mapvar};
			var marker_{$mapvar};
			var cm_{$mapvar} ;
			function init_{$mapvar}(){
				if (!jQuery('#{$fLat}')){
					alert('No coordinates in this profile (lat/long)! ');
					return ;
				}

				jQuery('#{$mapvar}').fadeTo( 'slow', 1 );
				var ula 		= '{$fLat}';
				var ulo 		= '{$fLng}';

				cm_{$mapvar} 	= new google.maps.LatLng(ula,ulo) ;
				if (ula==ulo){
					cm_{$mapvar} 	= new google.maps.LatLng({$center}) ;
				}

				var mo 			= {zoom:{$zoom},mapTypeId:{$type},center:cm_{$mapvar}};
				{$mapvar}  		= new google.maps.Map(document.getElementById('{$mapvar}'),mo);
				if (ula!=ulo){
					marker_{$mapvar}= new google.maps.Marker({map:{$mapvar},draggable:false,animation: google.maps.Animation.DROP,position:cm_{$mapvar}});
				}
				";

 		if ($cbMs){
			// charge l'icone
 			$icon = null ;
			if ($cbMs->markerIconType==1){
				// c'est l'icone define par le user (image joomla), dans ce cas le chemin de base vers les images joomla est commun pour toute la liste
				$icon = (strlen($cbMs->customimage)>3)?(JURI::root().$cbMs->customimage):null ;
			} 
			else if (($cbMs->markerIconType==2)AND(strlen($cbMs->mapicon)>3)){
				// c'est un mapicon depuis le rep d'install, dans ce cas le chemin de base vers les mapicon est commun pour toute la liste
				$icon = (strlen($cbMs->mapicon)>3)?(JURI::root().'media/com_geofactory/mapicons/'.$cbMs->mapicon):null ;

			}

			if ($icon)
				$js.= "	marker_{$mapvar}.setIcon('{$icon}') ;";

			// et la bubble 
			$js.= "
				google.maps.event.addListener(marker_{$mapvar}, 'click', function(){
					iw.open({$mapvar}, marker_{$mapvar});
					axGetBubble(iw,{$idU},{$cbMs->id},0,marker_{$mapvar}, {$mapvar}, false);
				});
			";
		}

		$js.= "}";


		$js.= "google.maps.event.addDomListener(window, 'load', init_{$mapvar});";

		$js = str_replace(array("\n","\t","  ","\r"),'',trim($js));


		//add API key to Profile Map View call
		//get configuration parameters
		$config = JComponentHelper::getParams('com_geofactory');

		$ggApikey =  strlen( $config->get('ggApikey') ) > 3 ? "&key=".$config->get('ggApikey') : "" ;
		$mapLang = (strlen($config->get('mapLang'))>1)?'&language='.$config->get('mapLang'):'' ;
		if ($lib){
			$http 	= $config->get('sslSite');
			if(empty($http)) $http = (isset($_SERVER['HTTPS']) AND ($_SERVER['HTTPS']))?"https://":"http://" ; 
			$doc->addScript($http.'maps.googleapis.com/maps/api/js?libraries=places'.$ggApikey.$mapLang);
			$doc->addScript(JURI::root().'components/com_geofactory/assets/js/header.js'); 
		}

		$doc->addStyleDeclaration("#{$mapvar} img{max-width:none!important;}");
		$doc->addScriptDeclaration($js);

		$html = array() ; 
		$html[] = '<div id="gf_admin_map_container" style="display:_none;padding: 3px 3px 4px 6px;">' ;
		$html[] = ' <div id="'.$mapvar.'" style="border:1px solid silver;'.$size.'" ></div>' ;
		$html[] = '</div>' ;

		return implode($html) ;
	}

	// par defaut essaie de lire les coordonnées dans les champs, sinon, on essaie de géocoder
	static public function getProfileEditMap($fLat, $fLng, $mapvar, $lib=false, $defAdress='', $checkBox=array(), $addressField=array(), $defCenter=null){
		$streetAndNumber= (isset($checkBox) AND (count($checkBox)==4) AND (($checkBox[3]==1)OR($checkBox[3]==3)))?"adr.rue = adr.rue + ' ' + adr.num ;":"adr.rue = adr.num + ' ' + adr.rue ;";

		$fieldCity 		= (isset($addressField['city']) 	&& strlen($addressField['city'])>2)?$addressField['city']:'';
		$fieldZip 		= (isset($addressField['zip']) 		&& strlen($addressField['zip'])>2)?$addressField['zip']:'';
		$fieldState 	= (isset($addressField['state']) 	&& strlen($addressField['state'])>2)?$addressField['state']:'';
		$fieldCountry 	= (isset($addressField['country'])	&& strlen($addressField['country'])>2)?$addressField['country']:'';
		$fieldStreet 	= (isset($addressField['street']) 	&& strlen($addressField['street'])>2)?$addressField['street']:'';

		if (!$defCenter OR strlen($defCenter)<2)
			$defCenter='46.947,7.444';

		$js = "
			var {$mapvar};
			var marker_{$mapvar};
			var cm_{$mapvar} ;
			function init_{$mapvar}(){
				if (!jQuery('#{$fLat}')){
					alert('Coordinates fields not loaded (lat/long)! ');
					return ;
				}

				jQuery('#{$mapvar}').fadeTo( 'slow', 1 );
				var ula 		= jQuery('#{$fLat}').val();
				var ulo 		= jQuery('#{$fLng}').val();
				var uzo 		= 13;
				cm_{$mapvar} 	= new google.maps.LatLng(ula,ulo) ;
				var def_cm 		= new google.maps.LatLng({$defCenter}) ;
				var mo 			= {zoom:parseInt(uzo),mapTypeId:google.maps.MapTypeId.ROADMAP,center:cm_{$mapvar}};
				{$mapvar}  		= new google.maps.Map(document.getElementById('{$mapvar}'),mo);
				marker_{$mapvar}= new google.maps.Marker({map:{$mapvar},draggable:true,animation: google.maps.Animation.DROP,position:cm_{$mapvar}});

				if ((ula.length<1) || (ulo.length<1)){
					jQuery('#{$fLat}').val(def_cm.lat());
					jQuery('#{$fLng}').val(def_cm.lng());
					{$mapvar}.panTo(def_cm) ;
					marker_{$mapvar}.setPosition(def_cm);
			
					if (navigator.geolocation) {
						navigator.geolocation.getCurrentPosition(
							function (position){
								new_cm = new google.maps.LatLng(position.coords.latitude,position.coords.longitude);
								fillAdrFromPos(new_cm, '');
								jQuery('#{$fLat}').val(new_cm.lat());
								jQuery('#{$fLng}').val(new_cm.lng());
								{$mapvar}.panTo(new_cm) ;
								marker_{$mapvar}.setPosition(new_cm);
								cm_{$mapvar} = new_cm ;
							}
						);
					}
					else if (google.gears) {
						var geo = google.gears.factory.create('beta.geolocation');
						geo.getCurrentPosition(
							function(position) {
								new_cm = new google.maps.LatLng(position.latitude,position.longitude);
								fillAdrFromPos(new_cm, '');
								jQuery('#{$fLat}').val(new_cm.lat());
								jQuery('#{$fLng}').val(new_cm.lng());
								{$mapvar}.panTo(new_cm) ;
								marker_{$mapvar}.setPosition(new_cm);
								cm_{$mapvar} = new_cm ;
							}
						);
					}
					else {
						alert('Geocode not supported by this browser.');
					}
				}
				
				var inp = document.getElementById('searchPos_{$mapvar}');
				var autocomplete = new google.maps.places.Autocomplete(inp);
				autocomplete.bindTo('bounds', {$mapvar});

				google.maps.event.addListener(marker_{$mapvar}, 'dragend', function(event) {
					jQuery('#{$fLat}').val(event.latLng.lat());
					jQuery('#{$fLng}').val(event.latLng.lng());
					{$mapvar}.panTo(event.latLng);

					fillAdrFromPos(event.latLng, 'searchPos_{$mapvar}');
				});	

				google.maps.event.addListener(autocomplete, 'place_changed', function() {
					marker_{$mapvar}.setVisible(false);
					var place = autocomplete.getPlace();
					if (!place.geometry) {
						return;
					}

					if (place.geometry.viewport) {
						{$mapvar}.fitBounds(place.geometry.viewport);
					} else {
						{$mapvar}.setCenter(place.geometry.location);
					}
					jQuery('#{$fLat}').val(place.geometry.location.lat());
					jQuery('#{$fLng}').val(place.geometry.location.lng());

					marker_{$mapvar}.setPosition(place.geometry.location);
					marker_{$mapvar}.setVisible(true);

					fillAddress(place.address_components);
				});
			}

			function fillAdrFromPos(new_cm, target){
				var addressInput = '';
				if (target.length>0){
					addressInput = document.getElementById(target);
				}

				var geocoder = new google.maps.Geocoder();
				if (geocoder) {
					geocoder.geocode({ 'latLng': new_cm}, function (results, status) {
						if (status == google.maps.GeocoderStatus.OK) {
							fillAddress(results[0].address_components);

							if (target.length>0){
								addressInput.placeholder = results[1].formatted_address;
							}

							return ;
						}
					});
				} 

				if (target.length>0){
					addressInput.placeholder = (Math.round(new_cm.lat()*100)/100) + ',' + (Math.round(new_cm.lng()*100)/100);
				}
			}

			function fillAddress(geoAdresse){
				if (! jQuery('#autofilladdress').prop('checked')){
					return ;
				}

				var adr = {};
				for (var i = 0; i < geoAdresse.length; i++){
					var city='';
					var types = geoAdresse[i].types.join(',');
					if (types == 'street_number'){
						adr.num = geoAdresse[i].long_name;
					}
					if (types == 'route' || types == 'point_of_interest,establishment'){
						adr.rue = geoAdresse[i].long_name;
					}
					if (types == 'sublocality,political' || types == 'locality,political' || types == 'neighborhood,political' || types == 'administrative_area_level_3,political'){
						adr.vil = (city == '' || types == 'locality,political') ? geoAdresse[i].long_name : city;
					}
					if (types == 'administrative_area_level_1,political'){
						adr.can = geoAdresse[i].short_name;
					}
					if (types == 'postal_code' || types == 'postal_code_prefix,postal_code'){
						adr.zip = geoAdresse[i].long_name;
					}
					if (types == 'country,political'){
						adr.pay = geoAdresse[i].long_name;
					}
				} 

				if (typeof(adr.num)=='undefined'){adr.num=''}
				if (typeof(adr.rue)=='undefined'){adr.rue=''}
				{$streetAndNumber}

				addAdressInfo(adr.vil, '{$fieldCity}') ;
				addAdressInfo(adr.zip, '{$fieldZip}') ;
				addAdressInfo(adr.can, '{$fieldState}') ;
				addAdressInfo(adr.pay, '{$fieldCountry}') ;
				addAdressInfo(adr.rue, '{$fieldStreet}') ;
			}

			function addAdressInfo(info, field){
				if (typeof(info)=='undefined')	{return;}
				if (typeof(info)=='')			{return;}
				if (typeof(info)==' ')			{return;}
				if (typeof(field)=='undefined')	{return;}
				if (field.length < 2)			{return;}
				if (jQuery('#'+field).length<1)	{return;}

				jQuery('#'+field).val(info);
			}

			google.maps.event.addDomListener(window, 'load', init_{$mapvar});
		";

		$js = str_replace(array("\n","\t","  ","\r"),'',trim($js));

		$doc = JFactory::getDocument();
		if ($lib){
			$config = JComponentHelper::getParams('com_geofactory');
			$http 	= $config->get('sslSite');
			if(empty($http)) $http = (isset($_SERVER['HTTPS']) AND ($_SERVER['HTTPS']))?"https://":"http://" ; 
			$config = JComponentHelper::getParams('com_geofactory');
			$ggApikey =  strlen( $config->get('ggApikey') ) > 3 ? "&key=".$config->get('ggApikey') : "" ;
			$mapLang = (strlen($config->get('mapLang'))>1)?'&language='.$config->get('mapLang'):'' ;

			$doc->addScript($http.'maps.googleapis.com/maps/api/js?libraries=places'.$ggApikey.$mapLang);
		}
		$doc->addStyleDeclaration("#{$mapvar} img{max-width:none!important;}");
		$doc->addScriptDeclaration($js);

		$html = array() ; 
		$html[] = '<div id="gf_admin_map_container" style="display:_none;padding: 3px 3px 4px 6px;">' ;
		$html[] = ' <label for="searchPos_'.$mapvar.'">'.JText::_('COM_GEOFACTORY_ENTER_AN_ADDRESS').'</label><input type="text" id="searchPos_'.$mapvar.'" value="'.$defAdress.'"/>' ;

		if (isset($checkBox) AND (count($checkBox)==4) AND ($checkBox[0]==true)){
			$checked = $checkBox[1]==true?'checked':'' ;
			$html[] = ' <div id="gf_autofilladdress"><input type="checkbox" id="autofilladdress" name="autofilladdress" value="1" '.$checked .'> '.$checkBox[2].'</div>' ;
		}

		$html[] = ' <div id="'.$mapvar.'" style="border:1px solid silver; max-width:100%!important; height:200px;" ></div>' ;
		$html[] = '</div>' ;

		return implode($html) ;
	}
}