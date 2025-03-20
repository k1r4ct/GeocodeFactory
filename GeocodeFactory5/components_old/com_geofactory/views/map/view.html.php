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

class GeofactoryViewMap extends JViewLegacy{
	protected $item;
	protected $params;
	protected $state;
	protected $user;

	// fonction pouvant etre appelée par le module ...
	public function initView($map)	{
		$app			= JFactory::getApplication();
		$user			= JFactory::getUser();
		$userId			= $user->get('id');
		$dispatcher		= JEventDispatcher::getInstance();

		$this->item 	= $map; 
		$this->state 	= $this->get('State');
		$this->user  	= $user;
		$this->params 	= $app->getParams();

		// utile pour le module ...
		if (!isset($this->document) OR !$this->document)
			$this->document = JFactory::getDocument(); ;
	}

	public function display($tpl = null){
		$this->initView($this->get('Item'));

		// Check for errors.
		if (count($errors = $this->get('Errors'))){
			JError::raiseWarning(500, implode("\n", $errors));
			return false;
		}

		// Create a shortcut for $item.
		$item = $this->item;
		$item->tagLayout      = new JLayoutFile('joomla.content.tags');

		// Add router helpers.
		$item->slug			= $item->alias ? ($item->id.':'.$item->alias) : $item->id;
	//	$item->catslug		= $item->category_alias ? ($item->catid.':'.$item->category_alias) : $item->catid;
	//	$item->parent_slug = $item->parent_alias ? ($item->parent_id . ':' . $item->parent_alias) : $item->parent_id;

		// No link for ROOT category
	//	if ($item->parent_alias == 'root'){
	//		$item->parent_slug = null;
	//	}
	
		$item->tags = new JHelperTags;
		$item->tags->getItemTags('com_geofactory.map', $this->item->id);

		//Escape strings for HTML output
		$this->pageclass_sfx = htmlspecialchars($this->params->get('pageclass_sfx'));

		$this->_prepareDocument();

		parent::display($tpl);
	}

	public function _prepareDocument(){
		$session= JFactory::getSession();
		$config	= JComponentHelper::getParams('com_geofactory');
		$root 	= JURI::root() ;


		$app 	= JFactory::getApplication('site');
		$adre 	= $app->input->getString('gf_mod_search', '');
		$adre 	= htmlspecialchars(str_replace(['"','`'], '', $adre), ENT_QUOTES, 'UTF-8');
		$dist 	= $app->input->getFloat('gf_mod_radius', 1);

		// parametres de l'url à charger
		$urlParams = array() ;
		//	if (!GeofactoryHelper::useNewMethod($this->item)){
		$urlParams[] = 'idmap='.	$this->item->id ;
		$urlParams[] = 'mn='.		$this->item->mapInternalName ;
		$urlParams[] = 'zf='.		$this->item->forceZoom ;
		$urlParams[] = 'gfcc='.		$this->item->gf_curCat;
		$urlParams[] = 'zmid='.		$this->item->gf_zoomMeId;
		$urlParams[] = 'tmty='.		$this->item->gf_zoomMeType;
		$urlParams[] = 'code='.		rand(1,100000) ;//	}

		$dataMap = implode('&',$urlParams) ;

		// chargement des divers biblios
		$jsBootStrap	= $config->get('jsBootStrap') ;
		$cssBootStrap	= $config->get('cssBootStrap') ;
		$jqMode 		= $config->get('jqMode') ;
		$jqVersion 		= strlen($config->get('jqVersion'))>0?$config->get('jqVersion'):'2.0';
		$jqUiversion	= strlen($config->get('jqUiversion'))>0?$config->get('jqUiversion'):'1.10';
		$jqUiTheme 		= strlen($config->get('jqUiTheme'))>0?$config->get('jqUiTheme'):'none';

		// site ssl ?
		$http 	= $config->get('sslSite');
		if(empty($http)) $http = (isset($_SERVER['HTTPS']) AND ($_SERVER['HTTPS']))?"https://":"http://" ; 
		
		// avec les tabs
		$jqui = $this->item->useTabs?true:false ; // c'est un peu con comme test ca....
		if ($this->item->useTabs AND ($jqMode==0 OR $jqMode==2))// pas ui... 
			$jqMode = 1 ;

		// en fonction du mode
		switch($jqMode){
			case 0 :	//COM_GEOFACTORY_JQ_MODE_NONE
				break;
			case 2 : 	//COM_GEOFACTORY_JQ_MODE_JOOMLA
				JHtml::_('jquery.framework');
				if ($jqui)
					JHtml::_('jquery.ui');
				break;
			case 3 : 	//COM_GEOFACTORY_JQ_MODE_CDN_GOOGLE
				$this->document->addScript(			$http.'ajax.googleapis.com/ajax/libs/jquery/'.$jqVersion.'/jquery.min.js') ;
				if ($jqui){
					$this->document->addScript(		$http.'ajax.googleapis.com/ajax/libs/jqueryui/'.$jqUiversion.'/jquery-ui.min.js' ); 
					$this->document->addStyleSheet(	$http.'ajax.googleapis.com/ajax/libs/jqueryui/'.$jqUiversion.'/themes/'.$jqUiTheme.'/jquery-ui.css' );
				}
				break;
			case 4 : 	//COM_GEOFACTORY_JQ_MODE_CDN_JQUERY
				$this->document->addScript(			$http.'code.jquery.com/jquery-'.$jqVersion.'.min.js') ;
				if ($jqui){
					$this->document->addScript(		$http.'code.jquery.com/ui/'.$jqUiversion.'/jquery-ui.min.js' ); 
					$this->document->addStyleSheet(	$http.'code.jquery.com/ui/'.$jqUiversion.'/themes/'.$jqUiTheme.'/jquery-ui.css' );
				}
				break;
			default :
			case 1 :	//COM_GEOFACTORY_JQ_MODE_LOCAL
				$this->document->addScript(			$root.'components/com_geofactory/assets/js/jquery/'.$jqVersion.'/jquery.min.js') ;
				if ($jqui){
					$this->document->addScript(		$root.'components/com_geofactory/assets/js/jqueryui/'.$jqUiversion.'/jquery-ui.min.js'); 
					$this->document->addStyleSheet(	$root.'components/com_geofactory/assets/js/jqueryui/'.$jqUiversion.'/themes/_name_/jquery-ui.css');
				}
				break;
		}

		// bootstrap ... chargé que en cas de besoin (tempalte auto)
		if ($this->item->templateAuto==1){
			if ($jsBootStrap==1)	$this->document->addScript($http.'maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js') ;
			if ($cssBootStrap==1)	$this->document->addStyleSheet($http.'maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css') ;
		}
		
		if ($jsBootStrap==1)	$this->document->addScript($http.'maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js') ;
		if ($cssBootStrap==1)	$this->document->addStyleSheet($http.'maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css') ;

		// charge le fichier JS principal 
		// en créer une instance
		$jsVarName = $this->item->mapInternalName ;
		$js = array() ;
		if (!GeofactoryHelper::useNewMethod($this->item)){
			$js[] = "var {$jsVarName}=new clsGfMap();" ;
			$js[] = "var gf_sr = '{$root}';" ;
			$js[] = "function init_{$jsVarName}(){sleepMulti(repos);" ;
			$js[] = 'jQuery.getJSON('.$jsVarName.'.getMapUrl("'.$dataMap.'"),function(data){' ;
				$js[] = "if (!{$jsVarName}.checkMapData(data)){document.getElementById('{$jsVarName}').innerHTML = 'Map error.'; console.log('Bad map format given in init_{$jsVarName}().'); return ;} " ;
				$js[] = " {$jsVarName}.setMapInfo(data, '{$jsVarName}');" ;

				// le code source qui apparait sur la page source
				GeofactoryModelMap::_loadDynCatsFromTmpl($jsVarName, $js, $this->item) ;
				GeofactoryModelMap::_setKml($jsVarName, $js, $this->item->kml_file);
				$this->_setLayers($jsVarName, $js);
				$this->_getSourceUrl($jsVarName, $js, $root);
				GeofactoryModelMap::_loadTiles($jsVarName, $js, $this->item);

				// charge les marqueurs, sur toute la carte ou uniquement sur la partie concernée (recherche rayon depuis externe ... module...)
				$gf_ss_search_phrase = $session->get('gf_ss_search_phrase', null);
				if($gf_ss_search_phrase AND (strlen($gf_ss_search_phrase) > 0)){
					$js[] = "{$jsVarName}.searchLocationsFromInput();" ;
					$session->clear('gf_ss_search_phrase');
				}else if ($this->item->useBrowserRadLoad==1){
					$js[] = "{$jsVarName}.getBrowserPos(false, true);" ;
				}else{
					$js[] = "{$jsVarName}.searchLocationsFromPoint(null);" ;
				}

				$js[] = " });" ; // getJSON

			$js[] = "}" ; //initGfCarte



			// on utilise ce qu'on a trouvé ou non...
			$js[] = " var gf_mod_search = `{$adre}`;" ;
			$js[] = " var gf_mod_radius = {$dist};" ;


			// charge la carte une fois le DOM chargé
			$js[] = "google.maps.event.addDomListener(window, 'load', init_{$jsVarName});";

//			$js[] = "jQuery(document).ready(function(){init_{$jsVarName}();});"; // est envoyé 2 fois dans certains cas... 3h pour trouver ca...


			$sep = " " ;
			if (GeofactoryHelper::isDebugMode())
				$sep = "\n" ;

			// met la bonne variable
			$js = implode($sep, $js) ;
		}
		// apikey ?
		$ggApikey =  strlen( $config->get('ggApikey') ) > 3 ? "&key=".$config->get('ggApikey') : "" ;

		// layers utilisés ?
		$arLayers = array() ;
		if (is_array($this->item->layers)){
			foreach($this->item->layers as $tmp){
				if (intval($tmp) > 0) 
					$arLayers[] = $tmp ;
			}
		}
		$lib = ((count($arLayers) > 0) AND (in_array(4, $arLayers) OR in_array(5, $arLayers) OR in_array(6, $arLayers))) ? ",weather" : "" ;
		
		// si utilisé pour boutons layers ou par radius on map 
		if (count($arLayers) > 0 || $this->item->radFormMode>1)
			$this->document->addStyleSheet('components/com_geofactory/assets/css/geofactory-maps_btn.css' );

		// ajoute les divers styles (après réflexion, peut-etre pas judicieux de les cahrger en AX comme le script, car doit etre déclaré avec dessin de la page ...)
		$this->document->addStyleDeclaration($this->item->fullCss);

		// map language
		$mapLang = (strlen($config->get('mapLang'))>1)?'&language='.$config->get('mapLang'):'' ;

		// map api
		$this->document->addScript($http.'maps.googleapis.com/maps/api/js?libraries=places'.$ggApikey.$mapLang.$lib);

		// si il y a un custom js file il est installé sous forme de custom.js.php avec des commentaires.
		if (file_exists(JPATH_BASE.'/components/com_geofactory/assets/js/custom.js'))
			$this->document->addScript($root.'components/com_geofactory/assets/js/custom.js');

		//http://google-maps-utility-library-v3.googlecode.com/svn/trunk/markerclustererplus/src/
		if ($this->item->useCluster==1)
			$this->document->addScript($root.'components/com_geofactory/assets/js/markerclusterer-5151023.js');

		if (!GeofactoryHelper::useNewMethod($this->item)){
			$this->document->addScript($root.'components/com_geofactory/assets/js/map_api-5151020.js');
			$this->document->addScriptDeclaration($js);
		}else{
			// Ici, on essaie de voir si il y a des données envoyées depuis le module, ou depuis un radius plugin.
			// actuellement pour faire simple on passe le texte de recherche, et dans GF on refait le geocode de cette addresse, c'est plus simple, 
			// mais y a le risque que la recherche ne soit pas idem. Certains plugins/module, envoient leur coordonnées, tels que: mj_rs_ref_lat=48.26721&mj_rs_ref_lng=7.72562
			// on pourrai utiliser ca sans regeocoder --> voir  MT search c'est pas mal ... on regeocode, mais sur les coordonnées---
			if 	(empty($dist))
				$dist 	= $app->input->getFloat('mj_rs_radius_selector', 1);

			// ou peut-etre depuis CB search ? -> mj_cb_centerpoint=Rust%2C+Germany&mj_rs_radius_selector=20&mj_rs_ref_lat=48.26721&mj_rs_ref_lng=7.72562
			if (empty($adre)){
				$adre 	= $app->input->getString('mj_cb_centerpoint', '');
				$dist 	= $app->input->getFloat('mj_rs_radius_selector', 1);
			}

			// ou  peut-etre depuis RS search ?
			if (strlen($adre)<1 || $adre==''){
				$adre 	= $app->input->getString('mj_rs_center_selector', '');
			}

			// ou  peut-etre depuis MT search ?
			if (strlen($adre)<1 || $adre==''){
				$latRS 	= $app->input->getFloat('mj_rs_ref_lat', 1000);
				$lngRS 	= $app->input->getFloat('mj_rs_ref_lng', 1000);
				$adre 	= "{$latRS},{$lngRS}";

				// pas de coordonnées on essaie avec l'adresse
				if ( ($latRS+$lngRS)>=1000 ||  ($latRS+$lngRS)==0)
					$adre 	= $app->input->getString('mjradius', '');
			}
			// ou  peut-etre depuis SP search ?
			if (strlen($adre)<1 || $adre==''){
				$dist 	= $session->get('mj_rs_ref_dist', 0);
				$latRS 	= $session->get('mj_rs_ref_lat', 1000);
				$lngRS 	=  $session->get('mj_rs_ref_lng', 1000);
				$adre 	= "{$latRS},{$lngRS}";

				// pas de coordonnées on essaie avec l'adresse
				if ( ($latRS+$lngRS)>=1000 ||  ($latRS+$lngRS)==0)
					$adre 	= $session->get('mj_rs_center_selector', '');
			}

			// ajoute des éléments
			if (!empty($adre))	$this->document->addScriptDeclaration("var gf_mod_search='{$adre}';");
			if (!empty($dist))	$this->document->addScriptDeclaration("var gf_mod_radius={$dist};");
		
			$this->document->addScript('index.php?option=com_geofactory&task=map.getJs&'.implode('&',$urlParams));  
		}

		$app	= JFactory::getApplication();
		$menus	= $app->getMenu();
		$pathway = $app->getPathway();
		$title = null;

        // Because the application sets a default page title,
        // we need to get it from the menu item itself
        $menu = $menus->getActive();
        if ($menu) {
            $this->params->def('page_heading', $this->params->get('page_title', $menu->title));
        } else {
            $this->params->def('page_heading', $this->item->name);
        }
        $title = $this->params->get('page_title', '');
        if (empty($title)) {
            $title = $app->getCfg('sitename');
        } elseif ($app->getCfg('sitename_pagetitles', 0) == 1) {
            $title = JText::sprintf('JPAGETITLE', $app->getCfg('sitename'), $title);
        } elseif ($app->getCfg('sitename_pagetitles', 0) == 2) {
            $title = JText::sprintf('JPAGETITLE', $title, $app->getCfg('sitename'));
        }
        $this->document->setTitle($title);

        if ($this->params->get('menu-meta_description')) {
            $this->document->setDescription($this->params->get('menu-meta_description'));
        }

        if ($this->params->get('menu-meta_keywords')) {
            $this->document->setMetadata('keywords', $this->params->get('menu-meta_keywords'));
        }

        if ($this->params->get('robots')) {
            $this->document->setMetadata('robots', $this->params->get('robots'));
        }




	}

 	// Génère l'url du fichier source
	protected function _getSourceUrl($oMap, &$js, $root){
		$config		= JComponentHelper::getParams('com_geofactory');

		// carte en cours
		$idmap 		= $this->item->id ;
		
		// 3.0.65 sinon il inclu pas bien le fichier dans certains cas a cause du SEF -> JURI::root()."index{$indexFile}.php?opt
		//	 -> comme ici ca ne marchait pas : https://www.aamet.org/search/location-map.html
		//   -> mais chez lui ca ne fonctionne pas car si il appelle le site en http://www.site.com JURI::root() retourne http://site.com, ce qui génère ceci http://stackoverflow.com/questions/10143093/origin-is-not-allowed-by-access-control-allow-origin (https://mail.google.com/mail/ca/u/0/?shva=1#inbox/13c7d62b691e9d3f)
		//     -> en passant JURI::root(true)."/index{$indexFile}.php?op
		//   -> donc le JURI::root(true) fonctione partout a priori ...
		$app		= JFactory::getApplication();
		$itemid 	= $app->input->get('Itemid', 0, 'int');
		//$lang 		= $app->input->getString('lang');
		$lang		= $app->input->get('lang', '', 'word');
		if (strlen($lang)>1)	$lang="&lang={$lang}"; else $lang = '' ;
		$paramsUrl	= "&gfcc={$this->item->gf_curCat}&zmid={$this->item->gf_zoomMeId}&tmty={$this->item->gf_zoomMeType}&code=".rand(1,100000).$lang;

		// cache: si cache et si cache expiré, crée le nouveau fichier
		$useCache = 0 ;
		if ($this->item->cacheTime > 0){
			$cache_file_serverpath = GeofactoryHelper::getCacheFileName($idmap, $itemid, 1) ;
			$cache_file = GeofactoryHelper::getCacheFileName($idmap, $itemid, 0) ;
			$filemtime	= @filemtime($cache_file_serverpath);  // returns FALSE if file does not exist
			if (!$filemtime or (time()-$filemtime >= $this->item->cacheTime))	$useCache = 0 ;
			else 																$useCache = 1 ;
		}

		// si debug on passe le conteneur du msg debug
		$debugCont = (GeofactoryHelper::isDebugMode())?"gf_debugmode_xml":"null" ;
		$js[] = "{$oMap}.nameDebugCont='{$debugCont}';";
		$js[] = "{$oMap}.setXmlFile('{$paramsUrl}',{$useCache},{$idmap},{$itemid});";
	}

	protected function _setLayers($oMap, &$js){
		// ajout du bouton pour les couches
		$arLayersTmp = $this->item->layers;

		if (!is_array($arLayersTmp) OR !count($arLayersTmp))
			return ;

		$arLayers = array() ;
		foreach($arLayersTmp as $tmp){
			if (intval($tmp) > 0) 
				$arLayers[] = $tmp ;
		}

		// construction du bouton layers
		if (is_array($arLayers) && count($arLayers) > 0){
			$txt 	= array(JText::_('COM_GEOFACTORY_TRAFFIC'),
							JText::_('COM_GEOFACTORY_TRANSIT'),
							JText::_('COM_GEOFACTORY_BICYCLE'),
							JText::_('COM_GEOFACTORY_WEATHER'),
							JText::_('COM_GEOFACTORY_CLOUDS' ),
							JText::_('COM_GEOFACTORY_HIDE_ALL'), 
							JText::_('COM_GEOFACTORY_MORE_BTN'), 
							JText::_('COM_GEOFACTORY_MORE_BTN_HLP'));
			$js[] 	= '	var layb = [] ;	var sep = new separator();' ;

			if (in_array(1, $arLayers))	$js[] = ' layb.push( new checkBox({gmap: '.$oMap.'.map, title: "'.$txt[0].'" , id: "traffic", 	label: "'.$txt[0].'" }) ); ';
			if (in_array(2, $arLayers))	$js[] = ' layb.push( new checkBox({gmap: '.$oMap.'.map, title: "'.$txt[1].'" , id: "transit", 	label: "'.$txt[1].'" }) ); ';
			if (in_array(3, $arLayers))	$js[] = ' layb.push( new checkBox({gmap: '.$oMap.'.map, title: "'.$txt[2].'" , id: "biking", 	label: "'.$txt[2].'" }) ); ';
			if (in_array(4, $arLayers))	$js[] = ' layb.push( new checkBox({gmap: '.$oMap.'.map, title: "'.$txt[3].'" , id: "weatherF",	label: "'.$txt[3].'" }) ); ';
			if (in_array(5, $arLayers))	$js[] = ' layb.push( new checkBox({gmap: '.$oMap.'.map, title: "'.$txt[3].'" , id: "weatherC",	label: "'.$txt[3].'" }) ); ';
			if (in_array(6, $arLayers))	$js[] = ' layb.push( new checkBox({gmap: '.$oMap.'.map, title: "'.$txt[4].'" , id: "cloud", 	label: "'.$txt[4].'" }) ); ';

			$js[] = ' layb.push( sep );' ;
			$js[] = 'layb.push( new optionDiv({ gmap: '.$oMap.'.map, name: "'.$txt[5].'",title: "'.$txt[5].'", id: "mapOpt"}) );' ;
			$js[] = 'var ddDivOptions = {items: layb ,id: "myddOptsDiv"};' ;
			$js[] = 'var dropDownDiv = new dropDownOptionsDiv(ddDivOptions);' ;
			$js[] = 'var dropDownOptions = {gmap: '.$oMap.'.map, name: "'.$txt[6].'", id: "ddControl", title: "'.$txt[7].'", position: google.maps.ControlPosition.TOP_RIGHT, dropDown: dropDownDiv};' ;
			$js[] = 'var dropDown1 = new dropDownControl(dropDownOptions);' ;
		}
	}

}
