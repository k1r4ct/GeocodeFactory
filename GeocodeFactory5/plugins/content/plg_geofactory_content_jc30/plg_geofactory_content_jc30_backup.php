<?php
/**
 * @name		Geocode Factory - Content plugin
 * @package		geoFactory
 * @copyright	Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later
 * @author		Cédric Pelloquin <info@myJoom.com>
 * @website		www.myJoom.com
 * 
 */

// ensure this file is being included by a parent file
defined( '_JEXEC' ) or die( 'Direct Access to this location is not allowed.' );
jimport('joomla.plugin.plugin');
require_once JPATH_ROOT	.'/components/com_geofactory/helpers/geofactory.php';
require_once JPATH_SITE.'/components/com_geofactory/helpers/externalMap.php';
require_once JPATH_SITE.'/components/com_geofactory/views/map/view.html.php';
require_once JPATH_ROOT	.'/administrator/components/com_geofactory/helpers/geofactory.php';
require_once JPATH_ROOT	.'/administrator/components/com_geofactory/models/geocodes.php';

JLoader::register('K2Plugin', JPATH_ADMINISTRATOR .  '/components/com_k2/lib/k2plugin.php');

// uncomment this for debugging
//ini_set("display_errors","on");	error_reporting(E_ALL);

class PlgContentPlg_geofactory_content_jc30 extends JPlugin{
	var $m_plgCode 	= 'myjoom_map';
	var $m_table	= '#__geofactory_contents';

	function plg_geofactory_content_jc30 ( &$subject, $params )
	{
		parent::__construct( $subject, $params );
	}



	function onContentAfterSave ($context, $row, $isNew)
	{
		$c_opt = 'com_content';
		$model = null;
		$idPattern = 0;
		$useJoomlaMeta = $this->params->get('useJoomlaMeta', 0);

		if (strpos($context, 'com_k2') !== false) {
			$c_opt 		= 'com_k2';
			$plugin 	= JPluginHelper::getPlugin('geocodefactory', 'plg_geofactory_gw_k2');
			$params 	= new JRegistry($plugin->params);

	    	$idPattern 	= $params->get('usedpattern');
	    	$geocodesave= $params->get('geocodesave');

	    	// tente un geocode silencieux
	    	if ($idPattern>0 AND $geocodesave>0){
				$idsPattern = $this->_K2_getListFieldPattern($idPattern);
				
				// si trouvé quelque chose (donc si pas SWmap)
				if ($idsPattern){
					$adresse 	= $this->_K2_getK2Addresse($row->id, $idsPattern);
					$model 		= new GeofactoryModelGeocodes;
					$ggUrl 		= $model->getGoogleGeocodeQuery($adresse);
					$coor 		= $model->geocodeItem($ggUrl);
					GeofactoryHelper::saveItemContentTale($row->id, $c_opt, $coor[0], $coor[1]);
				}
			}
		} elseif (strpos($context, 'com_contact') !== false) {
			$c_opt 		= 'com_contact';
			$plugin 	= JPluginHelper::getPlugin('geocodefactory', 'plg_geofactory_gw_jcontact10');
			$params 	= new JRegistry($plugin->params);
			
	    	$idPattern 	= $params->get('usedpattern');
	    	$geocodesave= $params->get('geocodesave');

	    	// tente un geocode silencieux
	    	if ($idPattern > 0 AND $geocodesave > 0) {
				$idsPattern = $this->getListFieldPattern('MS_JCT', $idPattern);

				// si trouvé quelque chose (donc si pas SWmap)
				if ($idsPattern) {
					$dispatcher = JEventDispatcher::getInstance();
					$pluginObj 	= JPluginHelper::importPlugin('geocodefactory', 'plg_geofactory_gw_jcontact10');
					$address 	= $dispatcher->trigger('getItemPostalAddressString', ['MS_JCT', $row->id, $idsPattern]);
					
					if (is_array($address) && !empty($address[0])) {
						$address = $address[0];
					} else {
						$address = null;
					}
					
					$model 		= new GeofactoryModelGeocodes;
					$ggUrl 		= $model->getGoogleGeocodeQuery($address);
					$coor 		= $model->geocodeItem($ggUrl);

					GeofactoryHelper::saveItemContentTale($row->id, $c_opt, $coor[0], $coor[1]);
				}
			}
		} elseif ((strpos($context, 'com_content') !== false) AND $useJoomlaMeta > 0) {
			$adresse = null;
			
			if ($useJoomlaMeta==1)		$adresse = trim($row->metakey);
			if ($useJoomlaMeta==2)		$adresse = trim($row->metadesc);	
			
			if (!empty($adresse)){
				$model 		= new GeofactoryModelGeocodes;
				$ggUrl 		= $model->getGoogleGeocodeQuery($adresse);
				$coor 		= $model->geocodeItem($ggUrl);
				GeofactoryHelper::saveItemContentTale($row->id, $c_opt, $coor[0], $coor[1]);
			}
			return;
		}


		// si nécessaire je sauve l'article qui n'avais pas de ID ...
		// oui si il édite 2 articles ca peut mal tourner
		$db = JFactory::getDBO();
		if ($isNew){
	 		// dans la session, y a t'il un ID temporaire ????
	 		$session    = JFactory::getSession();
		    $idTmp 		= $session->get('gf_temp_art_id',-1);

		    // 1427965360 // secondes depuis 1970
		    if ($idTmp<1 OR $row->id<1) 
		    	return;

	        $session->clear('gf_temp_art_id');

		    // si l'article correspondant existe ...
			$query = $db->getQuery(true);
			$query->select('COUNT(*)')->from($this->m_table)->where('id_content='.$idTmp.' AND type='.$db->Quote($c_opt));
			$db->setQuery($query);
			$ok = $db->loadResult();
		
			if ($ok<1)
				return;

			// OK, a ce stade on update avec le bon article
			$query = $db->getQuery(true);
			$query->update($this->m_table)->set('id_content='.$row->id)->where('id_content='.$idTmp.' AND type='.$db->Quote($c_opt));
			$db->setQuery($query);
			$db->execute();

			
			// j'en profite pour supprimer les articles plus vieux que 2 jours (ca veux dire que sur ce site ils utilisent cette methode, et que de temps en temps je clean)
			$vieux = time() - (3600*24*2); // time est le temps en secondes depuis 1970 ère unix
			$db->setQuery('delete from '.$this->m_table.' where type='.$db->Quote($c_opt) .' AND (id_content<'.$vieux.' AND id_content>1000000)');
			$db->query();
		}


		/* Comentado, não faz nada
		// on continue que avec k2
		if ($c_opt!='com_k2')
			return;
		*/
		/* permet de voir tous les enregisrements
		// maintenant on a un (ou plusieurs mais theoriquemnt pas) articles enregistrés pour k2 dans la table __geofactory_contents temporairement
		// car avec le bouton 'geocode' on travaille comme cela.
		$query = $db->getQuery(true);
		$query->select('*');
		$query->from($db->quoteName($this->m_table));
		//	$query->where("type='com_k2'");
		$db->setQuery($query);
		$res = $db->loadObjectList();

		var_dump($res);exit;*/
	}



	protected function _K2_getListFieldPattern ($id) 
	{
		$arrItems = array('field_latitude','field_street','field_postal','field_city','field_county','field_state','field_country');
		$db		= JFactory::getDBO();
		$query	= $db->getQuery(true);
		$query->select(implode(',',$arrItems));
		$query->from($db->quoteName('#__geofactory_assignation'));
		$query->where("typeList='MS_k2' AND id=".$id);
		$db->setQuery($query);
		$fields = $db->loadObject();

		// travaille avec SWmap
		if (isset($fields->field_latitude) AND $fields->field_latitude==-1)
			return;

		$idFields = array();
		foreach($arrItems as $ar){
			if (isset($fields->$ar) AND ($fields->$ar>0))	
				$idFields[] = $fields->$ar;
		}

		return $idFields;
	}
	

	protected function getListFieldPattern ($typeList, $id)
	{
		$arrItems = array('field_latitude','field_street','field_postal','field_city','field_county','field_state','field_country');
		$db		= JFactory::getDBO();
		$query	= $db->getQuery(true);
		$query->select(implode(',',$arrItems));
		$query->from($db->quoteName('#__geofactory_assignation'));
		$query->where("typeList=".$db->quote($typeList)." AND id=".$db->quote($id));
		$db->setQuery($query);
		$fields = $db->loadObject();

		// travaille avec SWmap
		if (isset($fields->field_latitude) AND $fields->field_latitude==-1)
			return;

		$idFields = array();
		foreach($arrItems as $ar){
			if (!empty($fields->$ar))	
				$idFields[] = $fields->$ar;
		}

		return $idFields;
	}



	// know limitation : work only with basic text fields... 
	protected function _K2_getK2Addresse ($id, $idsPattern)
	{
		$db		= JFactory::getDBO();
		$query	= $db->getQuery(true);
		$query->select('extra_fields');
		$query->from($db->quoteName('#__k2_items'));
		$query->where('id='.$id);
		$db->setQuery($query);
		$ef = $db->loadResult();
		$ef = json_decode($ef);

		$adre = array();
		foreach ($ef as $f){
			if (in_array($f->id, $idsPattern))
				$adre[] = $f->value;
		}

		return $adre;
	}
	
	
	
	protected function _JoomlaContact_getAddress ($id, $idsPattern)
	{
		$db		= JFactory::getDBO();
		$query	= $db->getQuery(true);
		$query->select('extra_fields');
		$query->from($db->quoteName('#__contact_details'));
		$query->where('id='.$id);
		$db->setQuery($query);
		$ef = $db->loadResult();
		$ef = json_decode($ef);

		$adre = array();
		foreach ($ef as $f){
			if (in_array($f->id, $idsPattern))
				$adre[] = $f->value;
		}

		return $adre;
	}



	// dessine la carte ou au moins supprime le code {}	
	public function onContentPrepare ($context, &$article, &$params, $limitstart = 0)
	{
		// Don't run this plugin when the content is being indexed
		if ($context == 'com_finder.indexer') {
			return true;
		}

		// si la session est détruite après dessin de la carte dans tous les cas 
		if ($context == 'com_mtree.category') {

			// AJOUT 2016 ne doit pas être envoyée dans les catégories, jsute dans la recherche
			// http://www.myjoom.com/admin/ticket-admin?view=admin_support&layout=ticket&ticketid=938
			$view  		= strtolower(JRequest::getString("view"));
			$task  		= strtolower(JRequest::getString("task"));

			$listcats = false;
			if ($view == 'listcats' OR $task == 'listcats')
				$listcats = true;
		
			// on est en train d'afficher un article 
			// dans MT ce trigger est envoyé a chaque dessin de l'entrée AUSSI dans les catégories,
			// c'est a dire, si dans la liste il y a 5 entrées ce trigger sera lancé 5x
			if (isset($article->link_id) AND !$listcats){
				// récupère dans la session la bonne variable
				$session 	= JFactory::getSession();
				$links = $session->get('gf_mt_links');

				// initialise le vecteur si encore vide
				if (!isset($links))
					$links = array();

				// je vérifie que tous les items de ce vecteur viennt bien de l'affichage qui est en cours
				// (car je n'arrive pas bien a virer les session avec mon resetAfterGateway), pour cela je n'ai
				// pas trouvé une solution avec un de ces trigger (https://docs.joomla.org/Plugin/Events/fr), ils 
				// vident pas la session (ou pas au bon moment ou il y a du cache, ou je sais pas). Bref, je n'ai
				// pas trouvé mieux que de m'assurer que les items du vecteurs soient tous issus de la meme 
				// seconde (il y a pas avec un humain plus d'une requete secondes). En d'autre termes quand il
				// affiche la categorie il dessin les vcards, et a chaque vcard, il enregistre l'id + time()
				$reftime = time();
				if (count($links)>0){
					foreach ($links as $time => &$row) {
						$rowtime = explode(' ',$time); // var_dump(microtime());       // string(21) "0.89115400 1283846202"

						if (count($rowtime)!=2)
							unset($links[$time]);

					    if (($reftime-$rowtime[1])>1) {
					        unset($links[$time]);
					    }
					}
				}

				// ajoute l'entrée en cours si pas déjà
				if (!in_array($article->link_id, $links))
					$links[microtime()] = $article->link_id;

				// sauve la session
				$session->set('gf_mt_links', $links);
			}
		}

		if(!is_object($article) OR !isset($article->id)) 
			return false;

		$c_opt = 'com_content';
		if (strpos($context, 'com_k2')!==false)
			$c_opt 		= 'com_k2';

		//http://joomlacode.org/gf/project/joomla/tracker/?action=TrackerItemEdit&tracker_item_id=26421
		//$article->text = $article->introtext;
		$article->text	= $this->_prepareArticle($article->text, $article->id, $c_opt);
	}



	private function _prepareArticle($text, $id, $c_opt)
	{

		// corrige juste visuellement l'ancien code on peut pas sauver ici
		if (JString::strpos($text, '{myjoom_gf') !== false ){
			$regex = '/{myjoom_gf\s+(.*?)}/i';
			$new = '{'.$this->m_plgCode.'}';
			$text = preg_replace($regex, $new, $text);

			$replace = '{myjoom_gf}';
			$text = str_replace($replace,  $new, $text);
		}

		// simple performance check
		if ( JString::strpos($text, $this->m_plgCode) === false ) 
			return $text;

		$regex = '/{myjoom_map}/i';
 
		// plugin pas actif, je supprime mon code de l'article
		if ( !$this->params->get( 'enabled', 1 ) ) {
			return preg_replace( $regex, '', $text);
		}

	 	// find all instances of plugin and put in $matches
		preg_match_all( $regex, $text, $matches);
	 	$count = count( $matches[0] );		

		if ( $count ) {
			$lat = 255;
			$lng = 255;

			// seul moyen pour l'id de l'article  ???
			$this->_loadCoord($id, $lat, $lng, $c_opt); 

			// ici effacer le plugin si pas de coordonnées...
			if (($lat + $lng) == 510) {
				return preg_replace($regex, '', $text );
			}

			return $this->_replaceMap($text, $count, $regex, $lat, $lng);
		}

		return $text;
	}




	function _loadCoord ($id, &$lat, &$lng, $c_opt)
	{
		$db = JFactory::getDBO();
		$query = $db->getQuery(true);
		$query->select('latitude,longitude');
		$query->from($db->quoteName($this->m_table));
		$query->where('id_content='.(int) $id.' AND type='.$db->Quote($c_opt));
		$db->setQuery($query, 0, 1);

        $gps = $db->loadObjectList();
        if( $db->getErrorNum()) { trigger_error("_loadCoord error  :".$db->stderr()); exit(); }
		if (count($gps)<1)
			return;

		$lat = $gps[0]->latitude;
		$lng = $gps[0]->longitude;
	}



	// je ne remplace que la premiere map, les autres on les remplace par un vide
	function _replaceMap ($text, $count, $regex, $lat, $lng)
	{
		// si pas de map, je mets done a true 
		$noMap 	= $this->params->get('showMap', 0);;
		$done 	= $noMap==0?true:false;

		for ($i=0; $i < $count; $i++ ){
			if ($done>0){
				$text = preg_replace( $regex, '', $text );
				continue;
			}
			$idMap	= $this->params->get('idMap', 0);
			$zoom 	= $this->params->get('staticZoom', 0);				
			$done 	= true; 
			$res 	= "";
			if ($noMap==1){
				$map 		= GeofactoryExternalMapHelper::getMap($idMap, 'jp', $zoom);//eventuellement???, $lat, $lng);
				$res 		= $map->formatedTemplate;
			}
			else if ($noMap==2){
				if (($zoom > 17) OR ($zoom < 1))
					$zoom = 5;

				$config = JComponentHelper::getParams('com_geofactory');
				$ggApikey =  strlen( $config->get('ggApikey') ) > 3 ? "&key=".$config->get('ggApikey') : "";

				$width 	= $this->params->get('staticWidth', 200);
				$height = $this->params->get('staticHeight', 200);
				$res = "http://maps.googleapis.com/maps/api/staticmap?center={$lat},{$lng}&zoom={$zoom}&size={$width}x{$height}&markers={$lat},{$lng}{$ggApikey}";//&maptype={$maptype};
				$res = '<img src="'.$res.'" >';
	
			}

			$text = preg_replace($regex, $res, $text, 1);
		}
		
		return $text;
	}


	// je remplace l'ancien code {myjoom_gf addresse ville} par le nouveau {myjoom_map}
	 function onContentBeforeSave ($context, $article, $isNew) 
	 {
		if ( strpos($context, 'com_easyblog') === false) {
			return true;
		}

		// simple performance check	
		if ( JString::strpos($article->fulltext.$article->introtext, '{myjoom_gf') === false )
			return true;

		$regex = '/{myjoom_gf\s+(.*?)}/i';
		$new = '{'.$this->m_plgCode.'}';
		$article->introtext = preg_replace($regex, $new, $article->introtext);
		$article->fulltext = preg_replace($regex, $new, $article->fulltext);

		$replace = '{myjoom_gf}';
		$article->introtext = str_replace($replace,  $new, $article->introtext);
		$article->fulltext = str_replace($replace,  $new, $article->fulltext);

		return true;
 	}
}
