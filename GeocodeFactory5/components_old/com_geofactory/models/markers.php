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

// fonctions de tri des array de markers
function orderUser($a, $b)		{ return strnatcasecmp($a['rt'], $b['rt']); }
function orderUserDist($a, $b)	{ return $a['di'] > $b['di']; }

class GeofactoryModelMarkers extends JModelItem{
	protected $_context = 'com_geofactory.markers';
	protected $m_idCurUser	= 0 ;
	protected $m_idDyncat = -1 ;

	protected $m_vpMaps = null ;
	protected $m_vpCalc = null ;

	public function createfile($idMap, $out){
		$my = JFactory::getUser();
		$this->m_idCurUser = $my->id ;

		$app 	= JFactory::getApplication('site');
		$itemid = $app->input->get('Itemid', 0, 'int');
		if ($idMap<1)
			return JError::raiseError(404, JText::_('COM_GEOFACTORY_MAP_ERROR_ID'));

		$map 	= GeofactoryHelper::getMap($idMap);
		$ms 	= GeofactoryHelper::getArrayIdMs($idMap) ;
		$data 	= $this->_createDataFile($out, $ms, $map) ;

		// sauve le contenu dans un fichier ... si le cache est actif il n'y passe pas
		if ($map->cacheTime > 0){
			$fp = fopen(GeofactoryHelper::getCacheFileName($idMap, $itemid), 'w');
			fwrite($fp, $data);
			fclose($fp);
		}

		return $data ;
	}

	protected function _createDataFile($out, $ms, $map) {
		$data = null; 
		if (strtolower($out)=='json'){
			$this->_getDataFile($data, $ms, $map) ;
			$data = json_encode($data) ;
		}
		// xml ... ?

		return $data ;
	}

	protected function getIdMsFromName($selLists){
		$res = array() ;
		$selLists = explode(',', $selLists);
		foreach($selLists as $list){
			$tirets = explode('_', $list);
			$res[] = end($tirets);
		}

		return $res ;
	}

	protected function _getDataFile(&$data, $ms, $map) {
		$start_timestamp = microtime(true);
		$data = array();
		$data['infos'] = array() ;
		$data['infos']['messages'] = array() ;

		// il peut aussi ne pas y en avoir...
		if (!is_array($ms) OR !count($ms)){
			$data['infos']['messages'] = array(JText::_('COM_GEOFACTORY_MS_NO_MS')) ;
			$data['infos']['elapsed'] = $this->_getElapsed($start_timestamp) ;
			return ;
		}
echo '<br> Avant: ------------------>'.$this->_getElapsed($start_timestamp).'<br>' ;

		JPluginHelper::importPlugin('geocodefactory');
		$config		= JComponentHelper::getParams('com_geofactory');
		$dsp = JDispatcher::getInstance();
		$app 			= JFactory::getApplication('site');
		$jsLat			= $app->input->getFloat('lat');
		$jsLng			= $app->input->getFloat('lng');
		$jsRadius		= $app->input->getFloat('rad');
		$curZoom		= $app->input->getInt('cz');
		$newMethod 		= GeofactoryHelper::useNewMethod($map);
		
		// list sélectionnées sur la carte
		$selLists		= $app->input->getString('lst', -1);
		$useSelLists	= ($selLists==-1 || $selLists=='')?false:true;
		$selLists		= $this->getIdMsFromName($selLists);

		// il y a un viewport ?
		$this->m_vpMaps	= $app->input->getString('bo', '');
		$this->m_vpMaps = explode(',', $this->m_vpMaps);

		// fait un reset sur ce qui doit l'être
		$dsp->trigger('resetBeforeGateway', array());

// on inverse l'ordre afin de commencer par le plus bas niveau de level
//		$ms = array_reverse($ms);

		// charge tous les MS car sinon je dois le faire 2 fois
		$arObjMs = array();
		foreach($ms as $idMs){
			if ($useSelLists && !in_array($idMs, $selLists))
				continue ;

			$objMs 			= GeofactoryHelper::getMs($idMs) ;
			$arObjMs[$idMs] = $objMs;
		}
echo '<br> Après chargement des MS: ------------------>'.$this->_getElapsed($start_timestamp).'<br>' ;
		// si en backend, sur un MS on veut un radius, et qu'on limite TOUS les MS, alors il faut faire ce job avant et on sauve tout ca en JS
		$this->_getForceRadiusCenterForAllMs($jsRadius, $jsLat, $jsLng, $arObjMs);

		$maxLevel = -1 ;
		$curLevel = 10 ; // 0-5
		$vInterset = array();
		$zIdx = 0;
		$msDbOk = array() ;
		$objMs = null ;
		$lastMsType = '' ;
		$vCheckArray = array() ;

		$useLevel = false ;

		foreach($ms as $idMs){
echo '<br> teste ce MS: ------------------>'.$idMs.'<br>' ;
			if ($useSelLists && !in_array($idMs, $selLists))
				continue ;

echo '<br> traite ce MS: ------------------>'.$idMs.'<br>' ;


echo '<br> Début boucle du MS '.$idMs.': ------------------>'.$this->_getElapsed($start_timestamp).'<br>' ;
echo "Ms courant:".$idMs;
			$objMs = $arObjMs[$idMs];
			if (!$objMs){
				$data['infos']['messages'][] = JText::_('COM_GEOFACTORY_MS_NO_PLUGIN').$idMs;
				continue ;
			}

			if (!$this->_checkUserLevel($objMs)){
				$data['infos']['messages'][] = JText::_('COM_GEOFACTORY_MS_LEVEL').$objMs->id ;
				continue ;
			}

			// pour chaque liste ajoute le l'index du zi
			$zIdx++ ;
			$queryForMsg = "" ;
			$msDb = $this->_getDataFromMsDb($objMs, $queryForMsg) ;

			// ajoute la requete avant envoi afin de pourvoir debuger en cas de soucis
			if (GeofactoryHelper::isDebugMode())
				$data['infos']['queries'][] = $queryForMsg ;
			if (!$msDb){
				$data['infos']['messages'][] = JText::_('COM_GEOFACTORY_MS_NO_DATA').$objMs->id ;
				continue ;
			}
			// determine le point centre DU markerset
			// par defaut le centre du rayon est toujours le point centre de la carte, au pire des cas il a au moins qqch si il y a un rayon
			$latRad = $map->centerlat ;
			$lngRad = $map->centerlng ;

			// récupère un point centre différent si disponible
			$this->_getRadiusCenterDirectory($latRad, $lngRad, $jsRadius, $jsLat, $jsLng, $objMs, $map);

			$arIdsMarkers = array() ;
			$isSpecialMs=false ;
			$dsp->trigger('isSpecialMs', array($objMs->typeList, &$isSpecialMs));
			if (!$isSpecialMs){
/* ON NE TRAITE PLUS LES LEVELS ICI MAIS EN JS
				// on connais le niveau de level, et je stock le plus grand level
				if ($curLevel != $objMs->mslevel){
					$curLevel = $objMs->mslevel;
					$maxLevel = ($maxLevel==-1)?$curLevel:$maxLevel;

					// changement de level, si nécessaire je purge, 
					if (count($vInterset)>0 ){
						$vCheckArray[$objMs->typeList] = array_intersect($vCheckArray[$objMs->typeList], $vInterset);
						$vInterset = array();
					}
				}
*/ if ($objMs->mslevel>0)				$useLevel = true;
				// traite ce markerset pour en extraire les markers
				$msDb = $this->_extractMarkersFrom($msDb, $objMs, $latRad, $lngRad, $jsRadius, $zIdx);

				if (! count($msDb)){
					$data['infos']['messages'][] = JText::_('COM_GEOFACTORY_MS_NO_MARKERS').$objMs->id ;
					continue ;
				}
				// verifie les doublons, s'assure que le type en cours est dans le vecteur de test
				if (!isset($vCheckArray[$objMs->typeList]))
					$vCheckArray[$objMs->typeList] = array() ;

				// nettoye le vecteur en cas de recherche par CB ou autre
				$dsp->trigger('cleanResultsFromPlugins', array($objMs->typeList, &$msDb, $objMs));

				//ajout des markers individuellement		
				foreach($msDb as $add){
					$idAdd = $add['id'];

					// Primo verifie les doublons, dans un vecteur de test et dans le vecteur 
					// 0>> ONE_STRICT	traité a la creation xml (ici) une seule occurence du marker par produit (SP) de MS 
					// 1>> ONE_NORMAL	un par ms et un pour all (traité en JS)
					// 2>> SHOW_ALL		on fait aucun test, il affiche tout aussi pour all
					if ($map->allowDbl==0){
						// ce marker est il deja dans UNE liste de ce type, oui ? alors on l'ignore
						if (in_array($idAdd, $vCheckArray[$objMs->typeList]))
							continue ;
					}

					// aujout au vecteur de test des doubles.
					//$vCheckArray[$objMs->typeList][] = $idAdd;
					$lastMsType = $objMs->typeList ;

/* ON NE TRAITE PLUS LES LEVELS ICI MAIS EN JS
					// si c'est le niveau max (ou pas de niveau) on ajoute, si utilise pas les levels, il passe dans le preimer cas
					if ($curLevel == $maxLevel){
						$msDbOk[] = $add;
						if (!$newMethod) $arIdsMarkers[] = $add['id'];
						$vCheckArray[$objMs->typeList][] = $idAdd;
					// si c'est un level suppérieur on teste.
					}else{
						// si le marker est pas dans un niveau inférieur, je le saute
						if (!in_array($idAdd, $vCheckArray[$objMs->typeList]))
							continue;

						// ok, je l'ajoute a intersect, afin de ne garder que ce qui sont de ce filtre
						$vInterset[] = $idAdd ;
					}
*/$msDbOk[] = $add;if (!$newMethod) $arIdsMarkers[] = $add['id'];
				}
			}else{
				// cas des MS speciaux pour lequels il y a un traitement spécial
				$data['spec'][] = $this->_getSpecial($objMs) ;
			}
			// créé les information de markerset (listes)
			$data['lists'][] = $this->_getListInfo($objMs, $arIdsMarkers, $map) ; 

		} 

echo "<br><br>".count($msDbOk) ;
//exit ;// fin boucle
		// purge finale. ---> pas sur que cela soit nécessaire, car on utilise plus, mais je laisse car si  ily a un problème avec le level top, ca devrai venir d'ici
//		if (count($vInterset)>0)
//			$vCheckArray[$lastMsType] = array_intersect($vCheckArray[$lastMsType], $vInterset);

		// fait un reset sur ce qui doit l'être
		$dsp->trigger('resetAfterGateway', array());

		// si vide
		if (! count($msDbOk)){
			$data['infos']['messages'][] = array(JText::_('COM_GEOFACTORY_MAP_NO_MARKERS_IN_MSS')) ;
			$data['infos']['elapsed'] = $this->_getElapsed($start_timestamp);


			// mode SUPER-debug
			if ($app->input->getInt('gf_debug', false)==2)
				exit() ;
			
			return ;
		}

		// 5.15.1010 -> cas spécial, si il ne veux que 1 marker ET qu'il y a un zoomMe (testé dans la fct), on ne garde que ce dernier
		if ($map->totalmarkers == 1)
	 		$msDbOk = $this->_purgeIfZoomMeAndUniqueMarker($msDbOk) ;

		// mélange le résultat
		if ($map->randomMarkers AND !$useLevel)
			shuffle($msDbOk) ;

		// 03.03.2014 je fait plus cela ici car c'est JS qui gère cela, sinon cela foire dans les vue de profile/entries  		// coupe ce qui dépasse 		if ($map->totalmarkers > 0 )			$msDbOk = array_slice($msDbOk, 0, $map->totalmarkers) ;

		// trie en ordre alpha ou distance si utilisée
		if (!$useLevel){
			if ($msDbOk[0]['di']!=-1)	usort($msDbOk, "orderUserDist");
			else						usort($msDbOk, "orderUser");
		}

		// retrie les vecteur qui reste, ca doit etre rapide
		if ( JString::strpos($map->template, "{sidelists_premium}") !== false )
			$msDbOk = $this->sortPremium($msDbOk, $map->id) ;

echo '<br> Avant clusters: ------------------>'.$this->_getElapsed($start_timestamp).'<br>' ;
		// ici, on a TOUS les markers a afficher, il me faut maintenant vérifier si je travaille avec SuperCluster...
		$useSuperCluster = ($map->useCluster==2 && $newMethod)?true:false;
		if ($useSuperCluster)
			$useSuperCluster = JPluginHelper::getPlugin('geocodefactory', 'plg_geofactory_cluster');

		// a partir de 50 markers je calcule.
		$minMarkers = 50 ; 
		if ($useSuperCluster)
			$dsp->trigger('howManyMarkerForCluster', array(&$minMarkers));

		// si mjoom_cluster ET zoom dans tolérance ET vpAll ET plus de 50/100 markers
		if ($useSuperCluster && $curZoom <= $map->clusterZoom && (count($msDbOk)>$minMarkers)){ 
			// vecteur de carrés
			$VPlimit = array() ;
			$dsp->trigger('getClusterCarres', 	array(&$VPlimit, &$this->m_vpCalc));

			// passe tous les markers en revue...
			$bigger = 0 ;
			$dsp->trigger('ventileMarkers', 	array(&$bigger, &$VPlimit, $msDbOk));

			// crée le json du cluster
			$cluster = null ;
			$dsp->trigger('genereJson', 		array(&$cluster, $VPlimit, $bigger));

			$data['cluster'] = $cluster ; 

echo '<br> Après clusters: ------------------>'.$this->_getElapsed($start_timestamp).'<br>' ;
		} else{
			// sinon ajoute les makers
			$data['markers'] = $this->_purgeNotNeededNodesForJs($msDbOk) ; 
			$data['plines'] = $this->_getPlines($msDbOk, $dsp, $map) ;
		}

echo "<br>nombre genere par xml: ";echo count($msDbOk);
echo '<br> Fin: ------------------>'.$this->_getElapsed($start_timestamp).'<br>' ;

		// mode SUPER-debug
		if ($app->input->getInt('gf_debug', false)==2)
			exit() ;

		// temps final
		$data['infos']['messages'] = array(JText::_('COM_GEOFACTORY_DATA_FILE_SUCCESS')) ;
		$data['infos']['elapsed'] = $this->_getElapsed($start_timestamp);
	}
	
	// permet de remettre les resultats dans l'ordre des listes, utiles pour la feature premium
	function sortPremium($vUid, $idMap) {

		// je recharge la liste orginale des listes (car elle est inversée plus haut)
		$oLists 	= GeofactoryHelper::getArrayIdMs($idMap) ;

		$res = array() ;
        foreach($oLists as $idMs){
        	echo "<br> test: ".$idMs ;
        	foreach($vUid as $uid){

        	echo "<br> test user ".$uid['id']."  pour liste ".$uid['idL'];
        		if ($uid['idL']==$idMs)
        			$res[] = $uid ;
        	}
		}

		return $res ;
	}

	// permet de faire retourner que les markers qui sont déjà dans le vecteur parent
	protected function _intersectMs($msDbOk, $msCur){
		$vResult = array() ;
		$testArrayDbOk = array(); // contien un array de TEXT typeListe#idM 
		foreach($msDbOk as $curDbOk){
			$testArrayDbOk[] = $curDbOk['tl'].'#'.$curDbOk['id'];
		}

echo "----------------------- le DB parent";
var_dump($testArrayDbOk);

		// pour chaque nouveau on controle qu'il soit dans le parent, si oui, je l'ajoute
		foreach ($msCur as $curCur){
			$curTxt = $curCur['tl'].'#'.$curCur['id'];
			if (in_array($curTxt, $testArrayDbOk)){
				$vResult[] = $curCur;
				echo "ajouté ".$curTxt ;
			}
		}

		return $vResult ;
	}

	protected function _purgeIfZoomMeAndUniqueMarker($msDbOk){
		$res = array();
		foreach($msDbOk as $add){
			if ($add['zm']==1){
				$res[] = $add;
				break;
			}
		}

		// garde que le vourant
		if (count($res)==1)
			return $res ;

		// rien changé
		return $msDbOk;
	}

	protected function _purgeNotNeededNodesForJs($msDbOk){
		$res = array();
		foreach($msDbOk as $add){
			unset($add['ow']);
			unset($add['lfr']);
			unset($add['lma']);
			unset($add['low']);
			unset($add['lgu']);
			unset($add['pr']);
			unset($add['ev']);
	
			$res[] = $add;
		}
		return $res ;
	}

	protected function _getSpecial($oMs){
		$vInfo = array();
		$vInfo['idL']		= $oMs->id;
		$vInfo['tl']		= $oMs->typeList;
		$vInfo['rh']		= isset($oMs->avatarSizeH)?intval($oMs->avatarSizeH):"" ;
		$vInfo['rw']		= isset($oMs->avatarSizeW)?intval($oMs->avatarSizeW):"" ;
		$vInfo['mi'] 		= "" ;
		$vInfo['pt'] 		= $oMs->custom_list_1 ;
		$vInfo['op'] 		= $oMs->custom_radio_1 ;
		$vInfo['mx'] 		= $oMs->maxmarkers ;
		$vInfo['md'] 		= isset($oMs->custom_radio_2)?intval($oMs->custom_radio_2):0 ;

		if ($oMs->markerIconType==1){
			// c'est l'icone define par le user (image joomla), dans ce cas le chemin de base vers les images joomla est commun pour toute la liste
			$vInfo['mi'] = (strlen($oMs->customimage)>3)?$oMs->customimage:"" ;
		} 
		else if (($oMs->markerIconType==2)AND(strlen($oMs->mapicon)>3)){
			// c'est un mapicon depuis le rep d'install, dans ce cas le chemin de base vers les mapicon est commun pour toute la liste
			$vInfo['mi'] = (strlen($oMs->mapicon)>3)?$oMs->mapicon:"" ;
		}

		return $vInfo;
	}

	// - plines entre les markers du meme user (lie ses 2 ou 3 profiles)
	// - plines entre amis (lies les amis)
	// - plines entre users et entrées
	// - plines entre users et events
	protected function _getPlines($msDbOk, $dsp, $map){
		$vMarkersProfiles = array() ;
		$vMarkersWithOwner = array() ;
		$vMarkersEvent = array() ;

		// parcours la liste des markers afin d'en créer une liste que pour les profiles, et une liste de ceux qui on un owner
 		$drawFriends = $drawMyAdd = $drawEvents = $drawOwners = false ;
 		foreach($msDbOk as $ms){
			// au moins un alors on dessine les ms qui l'accepent
			if (!$drawFriends 	AND $ms['lfr']>0)		$drawFriends 	= true ;
			if (!$drawMyAdd 	AND $ms['lma']>0)		$drawMyAdd		= true ;
			if (!$drawOwners 	AND $ms['low']>0)		$drawOwners 	= true ;
			if (!$drawEvents 	AND $ms['lgu']>0)		$drawEvents		= true ;

			// ajoute chaque markers dans les listes
			if ($ms['pr']==1) 						$vMarkersProfiles[]		= $ms ;
			if ($ms['ev']==1 AND $ms['lgu']>0)		$vMarkersEvent[]		= $ms ;
			if ($ms['ow']>0 	AND $ms['low']>0)		$vMarkersWithOwner[]	= $ms ;
		}

		// je pars du principe qu'il ne peut y avoir qu'un type de community par map (CB ou JS ou ???) 
		// c'est a dire que si il a les deux plugins installés, il utilisera toujours la couleur du dernier
		// chargé pour par exemple "plineColFriends", pour les autres pas utile car on utilise le nom du 
		// plugin code pour la couleur  
		$dsp->trigger('getColorPline', array(&$vCol));

		// contient des vecteur de pline
		$vPlines = array() ;

		if ($drawMyAdd)			$this->_getPLinesMyAddresses(	$vPlines, $vCol, $vMarkersProfiles);
		if ($drawFriends)		$this->_getPLinesFriends(		$vPlines, $vCol, $vMarkersProfiles, $dsp);
		if ($drawEvents)		$this->_getPLinesEvents(		$vPlines, $vCol, $vMarkersProfiles, $vMarkersEvent, $dsp);
		if ($drawOwners)		$this->_getPLinesOwners(		$vPlines, $vCol, $vMarkersProfiles, $vMarkersWithOwner);

		return $vPlines ;
	}

	// plines entre markers du meme profile
	// c'est pas infaillible, c'est a lui d'etre sur de n'avoir qu'un user affiché
	// a la fois a la meme coordonnee (pas le meme user dans plusieurs MS ... (sauf 2 MS online et offline)
	protected function _getPLinesMyAddresses(&$vPlines, $vCol, $vMarkersProfiles){
		if (count($vMarkersProfiles)<1)
			return ;

		$vFait = array() ;
		foreach($vMarkersProfiles as $mp){
			// deja fait ?
			if (in_array($mp['id'], $vFait))
				continue ;
			$vFait[] = $mp['id'] ;

			// passe tous les users en revue
			foreach($vMarkersProfiles as $mpTst){
				// controle que ce ne soit bien pour le meme user ...
				if ($mp['id'] != $mpTst['id'])
					continue ;

				//... mais pas les memes coordonnées
				if (($mp['lat'] == $mpTst['lat']) AND ($mp['lng'] == $mpTst['lng']))
					continue ;

				// je l'ajoute
				$this->_createPlineArray($vPlines, $mp, $mpTst, $vCol, 'linesMyAddr');
			}
		}
	}

	// plines entre les amis ...
	protected function _getPLinesFriends(&$vPlines, $vCol, $vMarkersProfiles, $dsp){
		$vCon = null ;
		$dsp->trigger('getFriendsList', array(&$vCon));

		if (is_array($vCon) AND count($vCon)){
			$vPairsTxt = array () ;
			$vPairs = array () ;
			foreach($vCon as $con){
				$id1 = $con->moi ;
				$id2 = $con->ami ;
				$ms1 = null ;
				$ms2 = null ;

				if ($id1==$id2)
					continue ;

				// les amis (ami et moi) doivent etre dessinés 
				// Todo : si markers CB + JS, risque de confusion, car je pars du principe que un seul peut etre affiché
				foreach($vMarkersProfiles as $msp){
					if ($ms1 AND $ms2)
						break ;

					if ($msp['id']==$id1){
						$ms1 = $msp;
						continue ;
					}
					if ($msp['id']==$id2){
						$ms2 = $msp;
						continue ;
					}
				}

				if (!$this->_finalisePlinepairs($ms1, $ms2, $vPairsTxt, $id1, $id2))
					continue ;

				$this->_createPlineArray($vPlines, $ms1, $ms2, $vCol, 'linesFriends');
			}
		}
	}

	// plines entre event et participants 
	protected function _getPLinesEvents(&$vPlines, $vCol, $vMarkersProfiles, $vMarkersEvent, $dsp){
		$vCon = null ;
		$dsp->trigger('getGuestList', array(&$vCon));
		
		if (is_array($vCon) AND count($vCon)){
			$vPairsTxt = array () ;
			$vPairs = array () ;

			foreach($vCon as $con){
				$id1 = $con->event_id ;
				$id2 = $con->guest_id ;
				$ms1 = null ;

				// Todo : si markers de plusieur events comp, risque de confusion, car je pars du principe que un seul peut etre affiché
				foreach($vMarkersEvent as $mse){
					if ($ms1)
						break ;

					if ($mse['id']==$id1){
						$ms1 = $mse;
						continue ;
					}
				}
				$ms2 = null ;
				foreach($vMarkersProfiles as $msp){
					if ($ms2)
						break ;

					if ($msp['id']==$id2){
						$ms2 = $msp;
						continue ;
					}
				}

				if (!$this->_finalisePlinepairs($ms1, $ms2, $vPairsTxt, $id1, $id2))
					continue ;

				$this->_createPlineArray($vPlines, $ms1, $ms2, $vCol, 'linesGuests');
			}
		}
	}

	protected function _finalisePlinepairs(&$ms1, &$ms2, &$vPairsTxt, $id1, $id2){
		if (!$ms1 OR !$ms2)
			return false ;

		// comme ca je peux l'insérer dans le vecteurs réel toujours croissant et aussi pour l'astuce ci-dessous
		if ($id1>$id2){
			$mst = $ms2 ;
			$ms2 = $ms1 ;
			$ms1 = $mst ;
		}

		// test la présence de la paire, l'astuce consiste a en faire un texte, que je place dans un arrray temp...
		$pair = "{$id1};{$id2}";
		if (in_array($pair, $vPairsTxt))
			return false ;

		$vPairsTxt[] = $pair ;

		return true ;
	}

	// plines entre users et leur entrées/events
	protected function _getPLinesOwners(&$vPlines, $vCol, $vMarkersProfiles, $vMarkersWithOwner){
		foreach($vMarkersWithOwner as $mo){
			foreach($vMarkersProfiles as $mpTst) {
				// l'id user n'est pas forcéement présent en raison de filtres, ou autre (limitation du nombre de MS)
				if ($mo['ow']!=$mpTst['id'])
					continue ;

				// et l'id user peut aussi etre plusieur fois, si le gars a plusieurs adresses
				// ici je pourai encore boucler sur $vMsProfile (un vecteur retiré mais qui stokais le tmp dans la boucle) pour verifier les coordonnées (voir si il y a pas plusieurs profils A LA MEME PLACE)
				$this->_createPlineArray($vPlines, $mo, $mpTst, $vCol, 'linesOwners');
			}
		}
	}

	// return un array(id1, x1, y1, id2, x2, y2, color)
	protected function _createPlineArray(&$vPlines, $m1, $m2, $vCol, $colItem){
		// petit test de base au moins la longeur ou je fait des tests...
		if (!is_array($m1) OR !is_array($m2) OR count($m1)<4 OR count($m2)<4)
			return;

		$colItem 	= $m1['tl'].$colItem;
		$col 		= isset($vCol[$colItem])?$vCol[$colItem]:"red" ;
		$vPlines[] 	= array("id1"=>$m1['id'],	"x1"=>$m1['lat'],	"y1"=>$m1['lng'],
							"id2"=>$m2['id'],	"x2"=>$m2['lat'],	"y2"=>$m2['lng'],
							"col"=>$col);
	}

	protected function _extractMarkersFrom($msDb, $oMs, $latCenterRad, $lngCenterRad, $distRadius, $zIdx){
		JPluginHelper::importPlugin('geocodefactory');
		$dsp 		= JDispatcher::getInstance();
		$app 		= JFactory::getApplication('site');

		// si je suis le user connecté ?
		$isUserPlg=false ;
		$dsp->trigger('isProfile', array($oMs->typeList, &$isUserPlg));

		$isEventPlg=false ;
		$dsp->trigger('isEvent', array($oMs->typeList, &$isEventPlg));

		// je suis dans la bonne app ?
		$isOnCurContext = false ;
		$ss_zoomMeId	= $app->input->getInt('zmid');
		$ss_zoomMeTy	= $app->input->getString('tmty');	
		$allM			= $app->input->getInt('allM');	

		$dsp->trigger('isOnCurContext', array($oMs->typeList, $ss_zoomMeTy, &$isOnCurContext));

		// prepare la liste des icons de catégories et relation entrée-catgéorie
		$listCatIcon = null ;
		$listCatEntry = null ;
		if ($oMs->markerIconType==4){
			// certains plugins (notement ceux des profils) n'ont pas cette function, c'est normal
			$dsp->trigger('getRel_idCat_iconPath', array($oMs->typeList, &$listCatIcon, $this->m_idDyncat));
			$dsp->trigger('getRel_idEntry_idCat', array($oMs->typeList, &$listCatEntry));
		}

		$voUserDetail 	= array() ;
		$tmp 			= new markerObject();
		$tmp->setCommon($oMs, $listCatIcon, $listCatEntry, $this->m_idDyncat);
		$count 			= 0 ;
		foreach($msDb as $m){
			if (isset($oMs->maxmarkers) AND ($oMs->maxmarkers>0) AND ($count == $oMs->maxmarkers))
				break;

			$tmp->initialise($m, $zIdx);
			if (!$tmp->baseValues())
				continue ;

			if (!$tmp->inRadius($latCenterRad, $lngCenterRad, $distRadius))
				continue ;
			
			// doit etre après le radius...
			// si c'est le premier load avec all markers, on charge tout.
			if ($allM!=1){
				if (!$tmp->inViewport($this->m_vpMaps, $this->m_vpCalc))
					continue ;
			}

			if (!$tmp->setMarkerIcon($dsp))
				continue ;
			$tmp->setAsCurrent($dsp, $this->m_idCurUser, $isUserPlg, $isEventPlg,  $isOnCurContext, $ss_zoomMeId) ;
			$voUserDetail[] = $tmp->getResult() ;
			$count++ ;
		}

		return $voUserDetail ;
	}

	protected function _getDataFromMsDb($oMs, &$queryForMsg) {
		$query			= $this->_getQueryForMs($oMs) ;
		$brut			= $this->_getQueryResult($query, $oMs) ;

		$queryForMsg 	= $query ;
		//echo $queryForMsg; 

		return $brut ; 
	}

	protected function _getQueryResult(&$query, $oMs) {
	    $db = JFactory::getDBO();

		$config	= JComponentHelper::getParams('com_geofactory');
		$bigSelect =  $config->get('useBigSelect', 1) ; 
		if ($bigSelect>0){
			$db->setQuery("SET SQL_BIG_SELECTS=1");
			$db->execute();
		}

		$db->setQuery($query);
	    $oU 	= $db->loadObjectList(); 
	    $query 	= $db->getQuery() ;

		if( $db->getErrorNum()) { trigger_error(JText::_('COM_GEOFACTORY_DATA_FILE_QUERY_ERROR').$db->stderr()); exit(); }
		if (!count($oU))
			return null ;

		// applique des changements sur les resultats par application (comme le trainement du titre de Sobipro)
		JPluginHelper::importPlugin('geocodefactory');
		$dsp = JDispatcher::getInstance();
		$dsp->trigger('checkMainQueryResults', array($oMs->typeList, &$oU));

		return $oU ;
	}

	protected function _getQueryForMs($oMs) {
		JPluginHelper::importPlugin('geocodefactory');
		$dsp = JDispatcher::getInstance();

		// rechercher la catégorie courante, Attention avant par defaut c'était 0 mais
		// il peut y avoir des composant avec 0 comme cat par def
		$app 		= JFactory::getApplication('site');
		$curCat 	= $app->input->getInt('gfcc',-1);

		// Si dyncat (listbox frontend) est plus grand que -1 elle a priorité
		$this->m_idDyncat = $this->_getDyncatId($dsp, $oMs->typeList) ;
echo "<br>dynCat: {$this->m_idDyncat}---";
		$curCat = ($this->m_idDyncat>=0)?$this->m_idDyncat:$curCat;

		// recherche les categories a forcer 
		$inCats = $this->_getSecureObjMsVal($oMs, 'include_categories', "") ;
		if (!is_array($inCats))
			$inCats = explode(',', $inCats);
echo "<br>Dans quelle cat(s) il doit etre (cat forcée du MS) ---";var_dump($inCats);
		// definit globalement car je vais pas le calculer 2 foix si j'en ai beasoin
		$vTmp = array();
		$idTopParent = -1 ;
		
		// si nécessaire checherche les catégories enfant
		if ( isset($oMs->childCats) AND $oMs->childCats==1 AND (count($inCats)>0)){
			if (!count($vTmp))
				$dsp->trigger('getAllSubCats', array($oMs->typeList, &$vTmp, &$idTopParent));

			$childs = array() ; 
			// pour chaque categorie parente mets les enfants dans child
			foreach($inCats as $catPar){
				$childs[] = $catPar ;
		    	if(sizeof($vTmp) > 0)
					GeofactoryPluginHelper::_getChildCatOf($vTmp, $catPar, $childs, null);
			}
echo "<br>enfants de cat forcée SI il faut prendre les childs ---";var_dump($childs);

			$inCats = array_unique($childs);
		}
		// transforme mon array en string
		$inCats = implode(',', $inCats);

echo "<br>categorie courante ---";var_dump($curCat);
echo "<br>Categories ou il doit etre  ---";var_dump($inCats);

		// on cherche les categories dans lesquels l'entrées
		if ((isset($oMs->catAuto) AND($oMs->catAuto==1) AND ($curCat>=0)) OR ($this->m_idDyncat!=-1)){
			if (!count($vTmp))
				$dsp->trigger('getAllSubCats', array($oMs->typeList, &$vTmp, &$idTopParent));
echo "<br>Section SP---";var_dump($idTopParent);

			$vRes = array($curCat);
echo "<br>Catégorie courante : ---";var_dump($vRes);
	    	if(sizeof($vTmp) > 0)
				GeofactoryPluginHelper::_getChildCatOf($vTmp, $curCat, $vRes, null);

echo "<br>Enfants de la cat courante  : ---";var_dump($vRes);
			// pour chaque catégorie ou les entrees peuvent etre (la courante et ses enfants), on verifie qu'elle soit autorisée
			// en fait c'est le cas compliqué ou il a activé autocat sur plusieurs markerset et que en plus il a forcé le MS pour certaines categories....
			$allowedCats = explode(',',$inCats);
			$inCats = array() ;

var_dump($allowedCats); 
			foreach($vRes as $autoCatCurOrChild){
echo "<br>teste {$autoCatCurOrChild}"; 
				// il a pas choisi de categorie, mais il est dans une categorie
				if (!is_array($allowedCats) OR (count($allowedCats)<1) OR $allowedCats[0]==0){
					echo "add";
					$inCats[] = $autoCatCurOrChild ;
					continue ; 
				}

				if (in_array($autoCatCurOrChild, $allowedCats))
					$inCats[] = $autoCatCurOrChild ;
			}

			// ajouté aucun alors il est pas compatible
			if (count($inCats)<1)
				$inCats[] = -1;

			$inCats = implode(',',$inCats) ;

echo "<br>?---";var_dump($inCats);
		}

		// si pas déjà une cat forcée et pour les composant qui le supportent, choisi la section / cat parente (Sobipro, Mosets, ...)
		$inCats = $this->_getAllowedCats($oMs, $inCats);

echo "<br>?---";var_dump($inCats);

		// affine la requete
		$sqlSelect 	= array("'{$oMs->typeList}' AS typeList");
		$sqlJoin 	= array() ;
		$sqlWhere 	= array() ;
		$params 	= array() ;
		$params['linesOwners']		= (isset($oMs->linesOwners) AND $oMs->linesOwners>0)?1:0;
		//$params['linesFriends']		= (isset($oMs->lfr) AND $oMs->lfr>0)?1:0; pas utile ici car pas utile dans la requete, car c'est plus tard qu'on trouve les friends, myadresses et quests
		//$params['linesMyAddr']		= (isset($oMs->lma) AND $oMs->lma>0)?1:0; 	"
		//$params['linesGuests']		= (isset($oMs->lgu) AND $oMs->lgu>0)?1:0;	"
		$params['useAvatar']		= $this->_getUseAvatar($oMs) ;
		$params['useSalesArea'] 	= $this->_getUseSalesArea($oMs) ;
		$params['field_avatar'] 	= (isset($oMs->avatarImage) AND ($oMs->avatarImage!=0 OR $oMs->avatarImage!=''))?$oMs->avatarImage:0;
		$params['field_salesArea'] 	= (isset($oMs->salesRadField))?$oMs->salesRadField:0;
		$params['field_title'] 		= (isset($oMs->field_title))?$oMs->field_title:0;
		$params['onlyPublished'] 	= (isset($oMs->onlyPublished))?$oMs->onlyPublished:0;
		$params['onlyOnline'] 		= (isset($oMs->onlyOnline))?$oMs->onlyOnline:0;
		$params['allEvents'] 		= (isset($oMs->allEvents))?$oMs->allEvents:0;
		$params['fields_coor'] 		= GeofactoryHelper::getCoordFields((isset($oMs->field_assignation))?$oMs->field_assignation:0);
		$params['inCats'] 			= $inCats ;
		$params['type'] 			= $oMs->typeList ;
		$include_groups				= (isset($oMs->include_groups) AND is_array($oMs->include_groups))?array_filter($oMs->include_groups, 'strlen')/*removes all NULL, FALSE and Empty Strings but leaves 0 values*/:array() ;
		$params['include_groups']	= count($include_groups)>0?implode(',',$include_groups):'';

		// gestion des tags, ... ou pas
		$params['tags'] = null ;
		if (isset($oMs->tags) && is_array($oMs->tags) && count($oMs->tags)){
			// si il faut inclure les enfants....
			if (isset($oMs->childTags) && ($oMs->childTags==1)){

				// récupère la liste de tous les tags
				$vTmp = array();
				GeofactoryPluginHelper::getAllTags(1, $vTmp);

				// ajoute les enfants de chaque tag
				$vRes = array();
				foreach($oMs->tags as $papatag){
					$vRes[] = $papatag;
			    	if(!is_array($vTmp) && !count($vTmp))
			    		continue;

					GeofactoryPluginHelper::_getChildCatOf($vTmp, $papatag, $vRes, null);
				}

				$params['tags'] = implode(',', array_unique($vRes)) ;
			}else{
				$params['tags'] = implode(',', $oMs->tags) ;
			}
		}

		$dsp->trigger('customiseQuery', array($oMs->typeList, $params, &$sqlSelect, &$sqlJoin, &$sqlWhere));

		// filtres
		$dsp->trigger('setMainQueryFilters', array($oMs->typeList, $oMs, &$sqlSelect, &$sqlJoin, &$sqlWhere));

		// prépare la requete principale
		$query 		= "" ;
		$sqlSelect 	= is_array($sqlSelect) && (count($sqlSelect)>0)? implode(',',$sqlSelect):"";
		$sqlJoin 	= (count($sqlJoin)>0)?' '.implode(' ',$sqlJoin):"";
		$sqlWhere 	= (count($sqlWhere)>0)?' WHERE '.implode(' AND ',$sqlWhere):"";
		$data 		= array('type'=>$oMs->typeList, 'sqlSelect'=>$sqlSelect, 'sqlJoin'=>$sqlJoin, 'sqlWhere'=>$sqlWhere, 'oMs'=>$oMs) ;
		$dsp->trigger('getMainQuery', array($data, &$query));

		$query = "SELECT DISTINCT ".$query ;

		echo $query . "<br><br>";

		return $query ;
	}

	protected function _checkUserLevel($oMs){
		$user = JFactory::getUser();
		$groups = $user->getAuthorisedViewLevels();
		$allow_groups = $oMs->allow_groups;

		if (!is_array($allow_groups))
			$allow_groups = array($allow_groups);

		// cas d'aucune sélection... donc tout
		if (count($allow_groups)==1 AND $allow_groups[0]=="")
			return true ;

		foreach($allow_groups as $allow){
			if (in_array((int)$allow, $groups))
				return true ;
		}

		return false ;
	}

	protected function _getListInfo($objMs, $vidMakers, $map) {
		$config	= JComponentHelper::getParams('com_geofactory');
		$sidemode =  $config->get('sidemode', 1) ; 

		$data = array() ;
		$data['id'] 		= $objMs->id ;
		$data['name'] 		= $objMs->name ;
		$data['type'] 		= $objMs->typeList ;
		$data['level'] 		= $objMs->mslevel ;
		$data['bubblewidth']= (isset($objMs->bubblewidth) AND $objMs->bubblewidth>0)?($objMs->bubblewidth):200;
		$data['useSide'] 	= (strlen(trim($objMs->template_sidebar))>3)?$sidemode:0;

		$path = "" ;
		if ($objMs->markerIconType==1){
			$path = JURI::root(); 
		} 
		else if ($objMs->markerIconType==2){	// mapicon
			$path = JURI::root().'media/com_geofactory/mapicons/' ; 
		}
		else if ($objMs->markerIconType==3){
			JPluginHelper::importPlugin('geocodefactory');
			$dsp = JDispatcher::getInstance();
			$dsp->trigger('getIconCommonPath', array($objMs->typeList, $objMs->markerIconType, &$path));
		}
		else if ($objMs->markerIconType==4){
			JPluginHelper::importPlugin('geocodefactory');
			$dsp = JDispatcher::getInstance();
			$dsp->trigger('getIconCommonPath', array($objMs->typeList, $objMs->markerIconType, &$path));
		}
		
		// gardé pour compatibilité
		if(!GeofactoryHelper::useNewMethod($map)){
			sort($vidMakers);
			$data['markers'] = implode(',', $vidMakers);
		}

		$data['commonIconPath'] = $path ;

		return $data ;
	}

	protected function _getAllowedCats($oMs, $inCats){
		if (strlen($inCats)>0)
			return $inCats ;

		if (!isset($oMs->section) OR !is_int($oMs->section) OR $oMs->section<0)
			return "";

///// BIZARRE _getChildCats N'EXISTE PAS ....
		$vRes = $this->_getChildCats($oMs->section) ;
		return implode(',',$vRes) ;
	}

	protected function _getUseAvatar($oMs) {
		if ((int)$oMs->markerIconType==3)
			return true ;

		return false ;
	}

	protected function _getUseSalesArea($oMs){
		if (isset($oMs->salesRadField) AND strlen($oMs->salesRadField)>0 )
			return true ;

		return false ;
	}

	function getCategorySelect($ext, $par, $mapVar){
		// retouve l'id map (par $mapVar = m_gf_5) car il nous faut la lanque car sp et bientot plus utilise la langue
		$idM = explode('_', $mapVar);
		$lang = '*';
		if (count($idM)>0){
			$idM = end($idM); // le dernier
			$idM = (int) $idM;

			if ($idM>0){
				$map 	= GeofactoryHelper::getMap($idM) ;
				$lang 	= (strlen($map->language)>1)?$map->language:'*';
			}
		}

		$categoryList = array();
		$idTopParent = -1 ;
		JPluginHelper::importPlugin('geocodefactory');
		$dsp = JDispatcher::getInstance();
		$dsp->trigger('getAllSubCats', array($ext, &$categoryList, &$idTopParent, $lang));

    	$vRes = array();
		$indent =  ""  ;
		$vRes[] = JHTML::_('select.option', '', JText::_('COM_GEOFACTORY_ALL')) ;
    	if(sizeof($categoryList) > 0) {
			GeofactoryPluginHelper::_getChildCatOf($categoryList, $par, $vRes, $indent);
		}
		return JHTML::_('select.genericlist', $vRes, "gf_dyncat_sel_{$ext}_{$par}", 'class="gf_dyncat_sel" size="1" onChange="'.$mapVar.'.SLFDYN(this, \''.$ext.'\');" ', 'value', 'text');
	}

	// certains MS n'ont pas toutes les propriétés
	protected function _getSecureObjMsVal($oMs, $prop, $def=null){
		if (!isset($oMs->$prop))
			return $def ;

		return $oMs->$prop ;
	}

	protected function _getDyncatId($dsp, $typeList) {
		// recherche l'id de cat en cours
		$dynCatUsedId = JRequest::getVar('fc', -1) ;
		if ($dynCatUsedId<0)
			return -1 ;

		// recherche l extension qui envoie un dyncat
		$dynCatFromExt = JRequest::getVar('ext', null) ;
		// si je pass un type=MS_SP etx=sp, et que je suis dans une map pour AM, il ne retournera jamais true,
		// car il passera type=MS_SP etx=sp == FALSE, type=MS_AM etx=sp == FALSE, type=MS_CM etx=sp== FALSE
		$dynCat = false ;
		$dsp->trigger('isMyShortName', array($typeList, $dynCatFromExt, &$dynCat));
		if (!$dynCat)
			return -1 ;

		return (int) $dynCatUsedId ;
	}

	// si en backend, sur un MS on veut un radius, et qu'on limite TOUS les MS, alors il faut faire ce job avant et on sauve tout ca en JS
	protected function _getForceRadiusCenterForAllMs(&$jsRadius, &$jsLat, &$jsLng, $arObjMs){
		// deja un radius defini en JS (par appel du moodule par exemple)
		if ((int) $jsRadius > 0)
			return; 

		foreach ($arObjMs as $idMs => $objMs) {
			// on veut appliquer le rayon a tous?
			if (isset($objMs->rad_allms) AND ($objMs->rad_allms<1))
				continue ;

			if ($objMs->rad_mode!=2)
				continue ;

			if ($objMs->rad_distance<=0)
				continue ;

			// cherche les coordonnées
			$coor = $this->_getCurrentViewCoordinates($objMs) ;
			if (!is_array($coor) OR (count($coor)!=2) OR (($coor[0]+$coor[1])==510))
				continue ;

			// bon on a trouvé des coordonnées, donc je les gardes
			$jsLat = $coor[0];
			$jsLng = $coor[1];
			$jsRadius = $objMs->rad_distance ;

			// je prends que le preimer en compte
			return ;
		}
	}

	protected function _getRadiusCenterDirectory(&$latRad, &$lngRad, $jsRadius, $jsLat, $jsLng, $oMarkerSet, $mapParams){
		$userFieldLat = null ;
		$userFieldLng = null ;
		// - rayon frontend (pické un point ou entré une location de geocode ou browser center
		if ($jsRadius>0){
			$latRad = $jsLat ;
			$lngRad = $jsLng ;
		}
		// - rayon de backend
		else if ($oMarkerSet->rad_distance>0){
			// position du user
			if ($oMarkerSet->rad_mode==0){
				// recherche les coodonnées du profile connecté en cours. 
				// si pas de profile (ou pas CB/JS/... installé), alors on prends le centre de carte
				$coor = null ;
				JPluginHelper::importPlugin('geocodefactory');
				$dsp = JDispatcher::getInstance();
				$dsp->trigger('getCurrentUserProfileCoordinates', array($oMarkerSet->typeList, &$coor));

				if (is_array($coor) AND count($coor)==2){
					$latRad = $coor[0] ;
					$lngRad = $coor[1] ;
				} else {
					$oMarkerSet->rad_mode = 1 ;
				}
			}
			// profile ou entrée courante
			else if ($oMarkerSet->rad_mode==2){
				// pour chaque MS on cherche le point centre choisi par le user (car il peut pour un MS de profile CB choisir le charger que les user vers l'entrée SP en cours vue en detail )
				$coor = $this->_getCurrentViewCoordinates($oMarkerSet) ;

				// si dans une entrée... sinon centre de la carte
				if (is_array($coor) AND (count($coor)==2)){
					$latRad = $coor[0];
					$lngRad = $coor[1];
				}else {
					$oMarkerSet->rad_mode = 1 ;
				}
			}
			// point centre de la carte (rien a faire car deja valeurs par defaut mais on laisse pour faciliter la lecture du code)
			else if ($oMarkerSet->rad_mode==1){
				$latRad = $mapParams->centerlat ;
				$lngRad = $mapParams->centerlng ;	
			}
		}
	}

	// cherche les coordonnées de l'entrée/profile vu basée sur la pattern sélectionnée en backend dans le ms
	protected function _getCurrentViewCoordinates($oMs){
		// pattern choisie, sinon on sort

		if (!$oMs->current_view_center_pattern OR $oMs->current_view_center_pattern < 1)
			return ;

		// cherche les coordonnées et le type de pattern
		$params 					= array();
		$params['fields_coor'] 		= GeofactoryHelper::getCoordFields($oMs->current_view_center_pattern);
		$params['pattern_type'] 	= GeofactoryHelper::getPatternType($oMs->current_view_center_pattern);
		if (strlen($params['pattern_type']) < 1 )
			return ;

		// si je suis en train d'afficher le profile d'un user depuis le plugin CB / JS
		$app 		= JFactory::getApplication('site');
		$curId		= $app->input->getInt('zmid');
		if ($curId <1)
			return ;
		JPluginHelper::importPlugin( 'geocodefactory');
		$dispatcher = JDispatcher::getInstance();
		$coor = array() ;
		$dispatcher->trigger( 'getItemCoordinates', array($params['pattern_type'], $curId, &$coor, $params));

		if (!is_array($coor) OR count($coor)<1 OR !isset($coor[$curId])) 
			return ;

		return $coor[$curId] ;	
	}

	protected function _getElapsed($start_timestamp) {
		$end_timestamp = microtime(true);
		$duration = $end_timestamp - $start_timestamp;
		return $duration." seconds.";
	}
}

class markerObject {
	protected $m_vMarker = array();
	protected $m_dbMarker = null ;
	protected $m_oMs = null ;
	protected $m_idDyncat = -1 ;

	public function setCommon($oMs, $listCatIcon, $listCatEntry, $idDyncat){
				$this->m_oMs = $oMs ;
				$this->m_listCatIcon = $listCatIcon ;
				$this->m_listCatEntry = $listCatEntry ;
				$this->m_idDyncat = $idDyncat ;
	}

	public function initialise($m, $zi){
		$this->m_dbMarker = $m ;	// item de base de données de SP, CB ...
		$this->m_vMarker = array(
			'id'=>0,
			'idL'=>0,
			'tl'=>null,
			'lat'=>null,
			'lng'=>null,
			'rt'=>"",			
			'sa'=>null,
			'rh'=>null,
			'rw'=>null,
			'di'=>-1,			// - 1 si pas utilisé (utile pour savoir comment trier les entrées), contrairement a 0 si c'est l'entrée en cours
			'mi'=>null,
			'om'=>0,
			'tr'=>"",
			'zm'=>0,
			'cu'=>0,
			'zi'=>$zi,
			'mi'=>"",
			// ci dessus pas besoin dans JS
			'ow'=>-1,	
			'lfr'=>0,
			'lma'=>0,
			'low'=>0,
			'lgu'=>0,
			'pr'=>0,
			'ev'=>0,
			'lv'=>0,
		) ;
	}

	public function baseValues(){
		// raccourcis
		$oMs = $this->m_oMs ;
		$this->m_vMarker['lat']			= $this->_getCoord($this->m_dbMarker->latitude, 'lat') ;
		$this->m_vMarker['lng']			= $this->_getCoord($this->m_dbMarker->longitude, 'lng') ;
		if (!$this->m_vMarker['lat'] OR !$this->m_vMarker['lng'])
			return false ;

		$this->m_vMarker['id']		= $this->m_dbMarker->id ;
		$this->m_vMarker['idL']		= $oMs->id;
		$this->m_vMarker['tl']		= $oMs->typeList;
		$this->m_vMarker['lv']		= $oMs->mslevel;
		$this->m_vMarker['rt']		= isset($this->m_dbMarker->title)?$this->m_dbMarker->title:'' ;

		$this->m_vMarker['sa']		= (isset($this->m_dbMarker->sales) AND $this->m_dbMarker->sales>0)?($this->m_dbMarker->sales*1):0;
		$this->m_vMarker['rh']		= isset($oMs->avatarSizeH)?intval($oMs->avatarSizeH):"" ;
		$this->m_vMarker['rw']		= isset($oMs->avatarSizeW)?intval($oMs->avatarSizeW):"" ;
		$this->m_vMarker['ow']		= isset($this->m_dbMarker->owner)?intval($this->m_dbMarker->owner):-1 ;
		$this->m_vMarker['tr']		= isset($this->m_dbMarker->trace)?$this->m_dbMarker->trace:"" ;	

		// je pourrai appliquer cette sytaxe aux élément ci dessus, vu que la valeur par defaut est mise dans inistialise
		if (isset($oMs->linesFriends))		$this->m_vMarker['lma'] = intval($oMs->linesFriends) ;
		if (isset($oMs->linesMyAddr))		$this->m_vMarker['lma'] = intval($oMs->linesMyAddr) ;
		if (isset($oMs->linesOwners))		$this->m_vMarker['low'] = intval($oMs->linesOwners) ;
		if (isset($oMs->linesGuests))		$this->m_vMarker['lgu'] = intval($oMs->linesGuests) ;

		return true ;
	}

	// test si la valeur est dans le rayon http://en.wikipedia.org/wiki/Haversine_formula
	public function inRadius($latRad, $lngRad, $rad=null){
		// si radius depuis le frontend, on l'utilise
		if (!$rad)
			$rad = $this->m_oMs->rad_distance ;

		// pas de rayon, pas de test ...
		if ((! is_numeric($rad)) OR (!$rad>0))
			return true ;

		// rayon de la terre en km/miles
		$km = 6371 ;
		if 		($this->m_oMs->rad_unit==1)	$km = 3959 ; // miles
		else if ($this->m_oMs->rad_unit==2)	$km = 3440 ; // miles marin
		
		// pas de coordonnées, pas dans le rayon
		if ((!$latRad) OR (!$lngRad) OR ($latRad=="") OR ($lngRad==""))
			return false ;

		// distance plus grande que limite, pas dans le rayon
		$dist = $this->_getDistance($latRad, $lngRad, $this->m_vMarker['lat'], $this->m_vMarker['lng'], $km);
		
		// ajoute encore le rayon d'action
		$dist = $dist - $this->m_vMarker['sa'] ;
		if ($dist>$rad)
			return false ;

		// arrondi la distance, et ajouté
		$this->m_vMarker['di'] = round($dist, 2) ;
		return true ;
	}

	public function setMarkerIcon($dsp){
		// default
		$this->m_vMarker['mi'] = "" ;

		if ($this->m_oMs->markerIconType==1){
			// c'est l'icone define par le user (image joomla), dans ce cas le chemin de base vers les images joomla est commun pour toute la liste
			$this->m_vMarker['mi'] = (strlen($this->m_oMs->customimage)>3)?$this->m_oMs->customimage:"" ;
		} 
		else if (($this->m_oMs->markerIconType==2)AND(strlen($this->m_oMs->mapicon)>3)){
			// c'est un mapicon depuis le rep d'install, dans ce cas le chemin de base vers les mapicon est commun pour toute la liste
			$this->m_vMarker['mi'] = (strlen($this->m_oMs->mapicon)>3)?$this->m_oMs->mapicon:"" ;
		}
		else if (($this->m_oMs->markerIconType==3) ){
			// utilise l'image d'avatar
			$fieldImg = (isset($this->m_dbMarker->avatar))?$this->m_dbMarker->avatar:'' ;
			$dsp->trigger('getIconPathFromBrutDbValue', array($this->m_oMs->typeList, &$fieldImg,$this->m_dbMarker->id));

			// ombre ajoutée directement en JS dans les options du marker
			if (strlen($fieldImg)>3){
				$this->m_vMarker['mi'] = $fieldImg ;
				$this->m_vMarker['om'] = 1 ;
			}
		}
		else if ($this->m_oMs->markerIconType==4){
			$this->_setCatIcon() ;
		}
	
		return true ;
	}

	function _setCatIcon() {
		if ((!is_array($this->m_listCatEntry)) OR (!is_array($this->m_listCatIcon)))
			return ;

		$idCur = $this->m_vMarker['id'] ;
		if ($idCur<1)
			return ;

		if (!key_exists($idCur, $this->m_listCatEntry))
			return ;

		$myCat = $this->m_listCatEntry[$idCur] ;
		if ($this->m_idDyncat>0)
			$myCat = $this->m_idDyncat ;

		if (!key_exists($myCat, $this->m_listCatIcon))
			return ;

		$this->m_vMarker['mi'] = $this->m_listCatIcon[$myCat] ;
	}

	public function getResult(){
		return $this->m_vMarker ;
	}

	//http://www.codecodex.com/wiki/Calculate_Distance_Between_Two_Points_on_a_Globe
	protected function _getDistance($la1, $lo1, $la2, $lo2, $e) {
        $dla = deg2rad($la2-$la1);
        $dlo = deg2rad($lo2-$lo1);
        $a = sin($dla/2)*sin($dla/2)+cos(deg2rad($la1))*cos(deg2rad($la2))*sin($dlo/2)*sin($dlo/2);
        $c = 2*asin(sqrt($a));
        $d = $e*$c;
        return $d;
	}
	
	protected function _getCoord($coor, $latlng){
		// pas de coordonnées ?
		if (!$coor OR $coor=="?" OR $coor==255 OR !is_numeric($coor))
			return null ;

		// dans la tolérance des coordonnées
		if ($latlng=='lat' && (($coor < -85) || ($coor > 85)))
			return null ;

		if ($latlng=='lng' && (($coor < -185) || ($coor > 185)))
			return null ;

		if ($this->m_oMs->accuracy > 0)
			$coor += (rand(-$this->m_oMs->accuracy,$this->m_oMs->accuracy)/10000); ;

		return (float) $coor ; 
	}

	public function setAsCurrent($dsp, $idCurUser, $isUserPlugin, $isEventPlg, $isOnCurContext, $ss_zoomMeId) {
		// si je suis sur l'entrée courante ou le profil courant
		$isOnCurItem=false ;
		if ($this->m_vMarker['id']==$ss_zoomMeId)
			$isOnCurItem = true ;

		if ($isOnCurItem)
			$this->m_vMarker['zm'] = 1;

		if ($isUserPlugin AND ($this->m_vMarker['id'] == $idCurUser)){
			$this->m_vMarker['cu'] = 1;
		}

		$this->m_vMarker['pr'] = $isUserPlugin?1:0;
		$this->m_vMarker['ev'] = $isEventPlg?1:0;
	}

	// si il y a un VP passé je controle que les markers y soient,
	// si il n'y a pas de VP passé depuis le front, je recherche le viewport 
	// bo=48.210048,7.605456,48.324316,7.845782 
	// "lat_lo,lng_lo,lat_hi,lng_hi", "lo" corresponds to the southwest corner of the box, "hi" corresponds to the northeast corner of that box.
	public function inViewport($vp, &$vpAll){//, &$vpMax){
/*
		// le vpmax fait le meme calcul MAIS dans tous les cas... 
		// en d'autre mots, il part de 0 est agrandi la zone.
		// le premier est le point centre		
		if (!is_array($vpMax) or count($vpMax)!=4){
			$vpMax[0] = $this->m_vMarker['lat'] ;
			$vpMax[1] = $this->m_vMarker['lng'] ;
			$vpMax[2] = $this->m_vMarker['lat'] ;
			$vpMax[3] = $this->m_vMarker['lng'] ;
		}else{
		// pour les suivants on test et on agrandi si nécessaire
			if ($this->m_vMarker['lat'] < $vpMax[0])	$vpMax[0] = $this->m_vMarker['lat'];
			if ($this->m_vMarker['lng'] < $vpMax[1])	$vpMax[1] = $this->m_vMarker['lng'];
			if ($this->m_vMarker['lat'] >= $vpMax[2])	$vpMax[2] = $this->m_vMarker['lat'];
			if ($this->m_vMarker['lng'] >= $vpMax[3])	$vpMax[3] = $this->m_vMarker['lng'];
		}*/

		// pas de view vp, on controle rien
		if (!is_array($vp) or count($vp)!=4){
			// c'est le premier ?
			if (!is_array($vpAll) or count($vpAll)!=4){
				$vpAll[0] = $this->m_vMarker['lat'] ;
				$vpAll[1] = $this->m_vMarker['lng'] ;
				$vpAll[2] = $this->m_vMarker['lat'] ;
				$vpAll[3] = $this->m_vMarker['lng'] ;

				return true ;
			}

			// pour les suivants on test
			if ($this->m_vMarker['lat'] < $vpAll[0])	$vpAll[0] = $this->m_vMarker['lat'];
			if ($this->m_vMarker['lng'] < $vpAll[1])	$vpAll[1] = $this->m_vMarker['lng'];
			if ($this->m_vMarker['lat'] >= $vpAll[2])	$vpAll[2] = $this->m_vMarker['lat'];
			if ($this->m_vMarker['lng'] >= $vpAll[3])	$vpAll[3] = $this->m_vMarker['lng'];

			return true;
		}

		// controle si il est dans le VP courant
		$vpAll = $vp ; // il y a un VP, alors on l'utilise
		if (! GeofactoryHelper::markerInArea($this->m_vMarker, $vp)) 		return false ;

		return true ;
	}
}