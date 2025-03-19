<?php
/**
 * @name		Sobipro Radius Search module
 * @package		mod_sobiproRadiusSearch
 * @copyright	Copyright © 2012 - All rights reserved.
 * @license		GNU/GPL
 * @author		Cédric Pelloquin
 * @author mail	info@myJoom.com
 * @website		www.myJoom.com
 */ 
class modsobiproRadiusSearchHelper{
	public static function setJsSrcipt($params, $def_loc, $def_rad, $apiKey){
		$exclGeoShort = substr($params->m_exclusionValueGoogle, 0,1)=="-"?0:1 ; // si 1 alors short
		$exclGeoValue = $exclGeoShort==0?substr($params->m_exclusionValueGoogle, 1):$params->m_exclusionValueGoogle ; // pour enlever le "-"
		$js = "";

		$js.= 'function getGeoExclMod(arComp) {';
		$js.= '	var res = "" ;';
		
		if (strlen($exclGeoValue)>3){
			$js.= '	jQuery.each(arComp, function(i, adComp){';
			$js.= '		if(adComp.types[0]=="'.$exclGeoValue.'"){';

			if ($exclGeoShort==1)	$js.= 'res = adComp.short_name ;';
			else 					$js.= 'res = adComp.long_name ;';

			$js.= '		return ;'; // sort de la boucle ... elle envoie une fonction 
			$js.= '		}';
			$js.= '	});';
		} 

		$js.= '	return res ;';				
		$js.= '}';

		if (isset($params->m_geocodeMode) AND $params->m_geocodeMode!=1){
			$bound = "" ;
			$param = "" ;
			if (isset($params->m_restricpt2) && (strlen($params->m_restricpt1)>2)&&(strlen($params->m_restricpt2)>2)){
				$bound = "var restricted = new google.maps.LatLngBounds(
				new google.maps.LatLng({$params->m_restricpt1}),
				new google.maps.LatLng({$params->m_restricpt2}));";
				$param = ",bounds:restricted";
			}
	
			$acTypes = "[]" ; 
			if (isset($params->m_acTypes) && strlen($params->m_acTypes)>0)
				$acTypes = $params->m_acTypes ;

			$country = "" ;
			if (isset($params->m_acCountry) && strlen($params->m_acCountry)==2)
				$country = ",componentRestrictions: {country: '{$params->m_acCountry}'} ";
			
			$js.= '	function initModRSA() {'.$bound.'
						var input = document.getElementById("mj_rs_mod_center_selector");
						var options = {types:'.$acTypes.$param.$country.'};
						var ac = new google.maps.places.Autocomplete(input, options);
						google.maps.event.addListener(ac, "place_changed", function() {
							var pl = ac.getPlace();
							document.getElementById("mj_rs_mod_ref_lat").value = pl.geometry.location.lat() ;
							document.getElementById("mj_rs_mod_ref_lng").value = pl.geometry.location.lng() ;
							document.getElementById("mj_rs_mod_ref_excl").value = getGeoExclMod(pl.address_components) ;
							
							if (document.getElementById("mj_rs_ref_lat")){document.getElementById("mj_rs_ref_lat").value = pl.geometry.location.lat() ;}
							if (document.getElementById("mj_rs_ref_lng")){document.getElementById("mj_rs_ref_lng").value = pl.geometry.location.lat() ;}
							if (document.getElementById("mj_rs_ref_excl")){document.getElementById("mj_rs_ref_excl").value = document.getElementById("mj_rs_mod_ref_excl").value ;}
						});
					}
					google.maps.event.addDomListener(window, "load", initModRSA);';
		}

		if (isset($params->m_geocodeMode) && $params->m_geocodeMode>0){
			$ifEmpty = '' ;
			$country = '' ;
			if (strlen($def_loc)>3) 			$ifEmpty.= ' entry = "'.$def_loc.'"; ' ;
			if (strlen($def_rad)>3) 			$ifEmpty.= ' document.getElementById("mj_rs_mod_radius_selector").value='.$def_rad.'; ' ;
			if (strlen($ifEmpty)<1)				$ifEmpty =  'document.getElementById("spSearchForm").submit() ; return ;' ;  //' return ; ' ;
			if (strlen($params->m_acCountry)==2)$country = ",region: '{$params->m_acCountry}' ";

			$js.= '
				function _manGeocodeMod(){
					var entry = document.getElementById("mj_rs_mod_center_selector").value ;
					if (entry.length < 2){'.$ifEmpty.'}
					geocoder = new google.maps.Geocoder();
					geocoder.geocode({address:entry'.$country.'},function(results,status) {
						if (status == google.maps.GeocoderStatus.OK) {
							document.getElementById("mj_rs_mod_ref_lat").value 			= results[0].geometry.location.lat();
							document.getElementById("mj_rs_mod_ref_lng").value			= results[0].geometry.location.lng();
							document.getElementById("mj_rs_mod_center_selector").value	= results[0].formatted_address;
							document.getElementById("mj_rs_mod_ref_excl").value 		= getGeoExclMod(results[0].address_components) ;

							document.getElementById("spSearchForm").submit() ;
						} else {
							document.getElementById("mj_rs_mod_center_selector").value	= "Geocode error : " + status;
							document.getElementById("mj_rs_mod_ref_excl").value 		= "" ;
						}
					});
				};';
		}

		if (($params->m_locateStart) || ($params->m_uselocateme)){
			$js.= ' function userPosMod(){
						var gc = new google.maps.Geocoder();
						if (navigator.geolocation) {
							navigator.geolocation.getCurrentPosition(function (po) {
								gc.geocode({"latLng":  new google.maps.LatLng(po.coords.latitude, po.coords.longitude) }, function(results, status) {
									if(status == google.maps.GeocoderStatus.OK) {
										document.getElementById("mj_rs_mod_ref_lat").value 			= po.coords.latitude ;
										document.getElementById("mj_rs_mod_ref_lng").value			= po.coords.longitude ;
										document.getElementById("mj_rs_mod_center_selector").value	= results[0]["formatted_address"];
										document.getElementById("mj_rs_mod_ref_excl").value 		= getGeoExclMod(results[0].address_components) ;
									} else {
										alert("Address error : " + status);
									}
								});
							});
						}
						else{
							alert("Your browser dont allow to geocode.");
						}
					}';
		}
		
		if ($params->m_locateStart)
			$js.= ' google.maps.event.addDomListener(window, "load", userPosMod);';
		
		$http=(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443?"https":"http";
		//$http=(isset($_SERVER['HTTPS']) AND ($_SERVER['HTTPS']))?"https":"http";
		$doc=& JFactory::getDocument();
		$doc->addScript($http."://maps.googleapis.com/maps/api/js?libraries=places&key=".$apiKey);
		$doc->addScriptDeclaration(str_replace(array("\n","\t","  "),'', $js));
		$doc->addScriptDeclaration($js);
	}

	public static  function getLocateMeBtn($params, $txt){
		if ($params->m_uselocateme<1)
			return "" ;
		
		if (strlen($txt)<2)
			$txt = JText::_('PEC_M_SPRS_LOCATEME_BTN') ;

		return '<input type="button" name="mod_sprs_locateme_btn" id="mod_sprs_locateme_btn"  onClick="userPosMod();" value="'.$txt.'" />' ;
	}

	public static function getDistanceList($params,$show){
		$unit = "km" ;
		$dist = null ;

		if (isset($params->m_unit)){
			if 		($params->m_unit == 2)	$unit = "mi";
			else if	($params->m_unit == 3)	$unit = "nm";
		}
		
		if (isset($params->m_distances))
			$dist = explode(',', $params->m_distances);

		if (!$dist OR !count($dist) OR $dist[0]==0)
			$dist = array(10,25,50,100,250,500);

		$ret = "" ;
		if ($show == 0){
			$arOpt = array() ;
			foreach ( $dist as $d ) {
				$unitAff = $unit ;
				$distAff = $d*1 ;
				
				if (($params->m_unit==4)AND($d<1)) {
					$distAff = $d*1000 ;
					$unitAff = "m" ;
				}
			
				$arOpt[] = JHTML::_('select.option', $d, $distAff.$unitAff) ;
			}
			$ret = JHTML::_('select.genericlist', $arOpt, "mj_rs_mod_radius_selector", 'class="inputbox" size="1" ', 'value', 'text');
		}else{
			$idx = $show-1 ; 
			if ($idx > (count($dist)-1))
				$idx = count($dist)-1 ;
				
				$ret = '<input type="hidden" name="mj_rs_mod_radius_selector" value="'.$dist[$idx].'"/>' ;
		}
		
		return $ret ;
	}

	public static function getKeywordMode($mode){
		$ret = "" ;

		// ok il veut le form
		if(($mode==1) OR ($mode==2)){
			$ret =	'<input type="radio" name="spsearchphrase" id="spsearchphrase_all" value="all" checked="checked" />
				<label for="spsearchphrase_all">'.JText::_('PEC_M_SPRS_SEARCH_ALL').'</label>
				<input type="radio" name="spsearchphrase" id="spsearchphrase_any" value="any" />
				<label for="spsearchphrase_any">'.JText::_('PEC_M_SPRS_SEARCH_ANY').'</label>
				<input type="radio" name="spsearchphrase" id="spsearchphrase_exact" value="exact" />
				<label for="spsearchphrase_exact">'.JText::_('PEC_M_SPRS_SEARCH_EXACT').'</label>';
		}
		
		// en plus le label
		if($mode==1){
			$ret =	'<div class="spsearch_label">'.JText::_('PEC_M_SPRS_SEARCH_MATCH').'</div>'.$ret ;
		}
		
		// a non il veut cacher mais un mode different de default (any word)
		if($mode==3){
			$ret =	'<input type="hidden" name="spsearchphrase" id="spsearchphrase_all" value="all" />';
		}

		if($mode==4){
			$ret =	'<input type="hidden" name="spsearchphrase" id="spsearchphrase_exact" value="exact" />';
		}
		
		return $ret ;		
	}
	
	public static function getSubmitBtn($txt, $params){
		if (strlen($txt)<2)
			$txt = JText::_('PEC_M_SPRS_SEARCH_BTN') ;

		if (isset($params->m_geocodeMode) && $params->m_geocodeMode>0)
			return '<input type="button" name="mod_sprs_search_btn" id="mod_sprs_search_btn" onclick="_manGeocodeMod();" value="'.$txt.'"/>' ;
		else
			return '<input type="submit" name="mod_sprs_search_btn" id="mod_sprs_search_btn" value="'.$txt.'"/>' ;
	}
	
	public static function getTemplate($useTmpl, $tmplCode, $kwshow, $keyw, $rad, $btn, $lmb){
		if (!$useTmpl)
			return null ;
			
		$tmplCode = str_replace('[KEYWORD]',		$kwshow,	$tmplCode);
		$tmplCode = str_replace('[MATCH]',			$keyw,		$tmplCode);
		$tmplCode = str_replace('[RADIUS_START]',	$rad,		$tmplCode);
		$tmplCode = str_replace('[SEARCH_BTN]',		$btn,		$tmplCode);
		$tmplCode = str_replace('[LOCATE_ME]',		$lmb,		$tmplCode);
		
		return $tmplCode ;			
	}

	public static function getKeywordInput($mode){
		$ret = "" ;
		if($mode==1){
			$ret =	'<div class="spsearch_label">'. JText::_('PEC_M_SPRS_SEARCH_LAB').'</div>
					<input type="text" name="sp_search_for" value=""/>';
		}
		if($mode==2){
			$ret =	'<input type="text" name="sp_search_for" value=""/>';
		}
		
		return $ret ;		
	}	

	public static function getRadiusSearchForm($params, $dist, $useTmpl, $prevEnt){
		$ret = "" ;
		if (isset($params->m_enabled) && isset($params->m_mjrslic) && ($params->m_enabled > 0) && (strlen($params->m_mjrslic) > 5)){		
			$attr = "" ;
			
			// si nécessaire géocode à la volée
//			if (isset($params->m_geocodeMode) && $params->m_geocodeMode>0)
//				$attr.= ' onblur="_manGeocodeMod();" ' ;

			if (isset($params->m_inputText) && strlen($params->m_inputText)>0)
				$attr.= ' placeholder="'.$params->m_inputText.'" ' ; 

			$lab = JText::_('PEC_M_SPRS_RADIUS') ;
			if (isset($params->m_label) && strlen($params->m_label)>0)
				$lab = $params->m_label ; 
			
			$ret = "" ;
			// si template je retourne le raw...
			if ($useTmpl==0)
				$ret.= '<div class="spsearch_label">'.$lab.'</div>' ;

			$onKP = $prevEnt?'onkeypress="return event.keyCode!=13"':'';
			$ret.= '<input type="text" id="mj_rs_mod_center_selector" '.$attr.' name="mj_rs_mod_center_selector" value="" '.$onKP.' /> ';
		}
		return $ret.$dist ;
	}
	
	public static function setGetSpParam($sps){

        SPFactory::registry()->loadDBSection( 'mjradius' );
        $settings = Sobi::Reg( 'mjradius.settings.params' );
        if ( strlen( $settings ) )
            $settings = SPConfig::unserialize( $settings );

		if (is_array($settings) && isset($settings[$sps]))
			return (object) $settings[$sps] ;

		return null ;
	}
}
?>