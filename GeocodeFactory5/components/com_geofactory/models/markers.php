<?php
/**
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Rick Pelloquin
 * @update      Daniele Bellante
 * @website     www.myJoom.com
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ItemModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Event\Event;
use Joomla\Event\DispatcherInterface;

// Funzioni di ordinamento per gli array di markers
if (!function_exists('orderUser')) {
    function orderUser($a, $b) {
        return strnatcasecmp($a['rt'], $b['rt']);
    }
}
if (!function_exists('orderUserDist')) {
    function orderUserDist($a, $b) {
        return $a['di'] > $b['di'];
    }
}

class GeofactoryModelMarkers extends ItemModel
{
    protected $_context = 'com_geofactory.markers';
    protected $m_idCurUser = 0;
    protected $m_idDyncat = -1;

    protected $m_vpMaps = null;
    protected $m_vpCalc = null;

    public function createfile($idMap, $out)
    {
        $my = Factory::getUser();
        $this->m_idCurUser = $my->id;

        // Recuperiamo application e dispatcher
        $app        = Factory::getApplication('site');
        $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);

        $itemid = $app->input->getInt('Itemid', 0);
        if ($idMap < 1) {
            throw new \Exception(Text::_('COM_GEOFACTORY_MAP_ERROR_ID'), 404);
        }

        $map = GeofactoryHelper::getMap($idMap);
        $ms  = GeofactoryHelper::getArrayIdMs($idMap);
        $data = $this->_createDataFile($out, $ms, $map);

        // Salva il contenuto in un file se il caching è attivo
        if ($map->cacheTime > 0) {
            $cacheFile = GeofactoryHelper::getCacheFileName($idMap, $itemid);
            $fp = fopen($cacheFile, 'w');
            fwrite($fp, $data);
            fclose($fp);
        }

        return $data;
    }

    protected function _createDataFile($out, $ms, $map)
    {
        $start_timestamp = microtime(true);
        $data = [];
        $data['infos'] = [];
        $data['infos']['messages'] = [];

        // Se non ci sono markers...
        if (!is_array($ms) || !count($ms)) {
            $data['infos']['messages'] = [Text::_('COM_GEOFACTORY_MS_NO_MS')];
            $data['infos']['elapsed'] = $this->_getElapsed($start_timestamp);
            return;
        }
        echo '<br> Avant: ------------------>'.$this->_getElapsed($start_timestamp).'<br>';

        // Dispatcher e plugin
        $app        = Factory::getApplication('site');
        $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);
        PluginHelper::importPlugin('geocodefactory');

        $config = ComponentHelper::getParams('com_geofactory');

        // Esegui eventi in modo aggiornato per Joomla 4
        $dispatcher->dispatch(new Event('onResetBeforeGateway', ['args'=>[]]));

        $jsLat = $app->input->getFloat('lat');
        $jsLng = $app->input->getFloat('lng');
        $jsRadius = $app->input->getFloat('rad');
        $curZoom = $app->input->getInt('cz');
        $newMethod = GeofactoryHelper::useNewMethod($map);

        // Lista selezionata sulla mappa
        $selLists = $app->input->getString('lst', -1);
        $useSelLists = ($selLists == -1 || $selLists == '') ? false : true;
        $selLists = $this->getIdMsFromName($selLists);

        // Se esiste un viewport
        $this->m_vpMaps = $app->input->getString('bo', '');
        $this->m_vpMaps = explode(',', $this->m_vpMaps);

        $zIdx = 0;
        $msDbOk = [];
        $objMs = null;
        $lastMsType = [];
        $vCheckArray = [];

        if (is_array($ms) && count($ms) > 0) {
            foreach ($ms as $idMs) {
                if ($useSelLists && !in_array($idMs, $selLists))
                    continue;

                $objMs = GeofactoryHelper::getMs($idMs);
                if (!$objMs) {
                    $data['infos']['messages'][] = Text::_('COM_GEOFACTORY_MS_NO_PLUGIN').$idMs;
                    continue;
                }

                if (!$this->_checkUserLevel($objMs)) {
                    $data['infos']['messages'][] = Text::_('COM_GEOFACTORY_MS_LEVEL').$objMs->id;
                    continue;
                }

                $zIdx++;
                $queryForMsg = "";
                $msDb = $this->_getDataFromMsDb($objMs, $queryForMsg);

                if (GeofactoryHelper::isDebugMode()) {
                    $data['infos']['queries'][] = $queryForMsg;
                }
                if (!$msDb) {
                    $data['infos']['messages'][] = Text::_('COM_GEOFACTORY_MS_NO_DATA').$objMs->id;
                    continue;
                }

                $latRad = $map->centerlat;
                $lngRad = $map->centerlng;

                $this->_getRadiusCenterDirectory($latRad, $lngRad, $jsRadius, $jsLat, $jsLng, $objMs, $map);

                $arIdsMarkers = [];
                $isSpecialMs = false;
                
                // Aggiornato per Joomla 4: isSpecialMs event
                $evIsSpec = new Event('onIsSpecialMs', [
                    'typeList' => $objMs->typeList,
                    'isSpecialMs' => &$isSpecialMs
                ]);
                $dispatcher->dispatch($evIsSpec);

                if (!$isSpecialMs) {
                    $msDb = $this->_extractMarkersFrom($msDb, $objMs, $latRad, $lngRad, $jsRadius, $zIdx);
                    if (!is_array($msDb) || !count($msDb)) {
                        $data['infos']['messages'][] = Text::_('COM_GEOFACTORY_MS_NO_MARKERS').$objMs->id;
                        continue;
                    }
                    if (!isset($vCheckArray[$objMs->typeList])) {
                        $vCheckArray[$objMs->typeList] = [];
                    }

                    // Aggiornato per Joomla 4: cleanResultsFromPlugins event
                    $evClean = new Event('onCleanResultsFromPlugins', [
                        'typeList' => $objMs->typeList,
                        'msDb'     => &$msDb,
                        'objMs'    => $objMs
                    ]);
                    $dispatcher->dispatch($evClean);

                    if (is_array($msDb)) {
                        foreach ($msDb as $add) {
                            $idAdd = $add['id'];
                            if ($map->allowDbl == 0) {
                                if (is_array($vCheckArray[$objMs->typeList]) && in_array($idAdd, $vCheckArray[$objMs->typeList])) {
                                    continue;
                                }
                            }
                            $lastMsType = $objMs->typeList;
                            $msDbOk[] = $add;
                            if (!$newMethod) {
                                $arIdsMarkers[] = $add['id'];
                            }
                        }
                    }
                } else {
                    $data['spec'][] = $this->_getSpecial($objMs);
                }
                $data['lists'][] = $this->_getListInfo($objMs, $arIdsMarkers, $map);
            }
        }

        echo "<br><br>".count($msDbOk);
        if (!is_array($msDbOk) || count($msDbOk) < 1) {
            $data['infos']['messages'][] = [Text::_('COM_GEOFACTORY_MAP_NO_MARKERS_IN_MSS')];
            $data['infos']['elapsed'] = $this->_getElapsed($start_timestamp);
            if ($app->input->getInt('gf_debug', false) == 2) {
                exit();
            }
            return;
        }

        if ($map->totalmarkers == 1) {
            $msDbOk = $this->_purgeIfZoomMeAndUniqueMarker($msDbOk);
        }

        if ($map->randomMarkers) {
            shuffle($msDbOk);
        }

        if (isset($msDbOk[0]['di']) && $msDbOk[0]['di'] != -1) {
            usort($msDbOk, "orderUserDist");
        } else {
            usort($msDbOk, "orderUser");
        }

        if (strpos($map->template, "{sidelists_premium}") !== false) {
            $msDbOk = $this->sortPremium($msDbOk, $map->id);
        }

        echo '<br> Avant clusters: ------------------>'.$this->_getElapsed($start_timestamp).'<br>';
        $useSuperCluster = ($map->useCluster == 2 && $newMethod) ? true : false;
        if ($useSuperCluster) {
            // Se esiste il plugin per i cluster
            $plg = PluginHelper::getPlugin('geocodefactory', 'plg_geofactory_cluster');
            $useSuperCluster = $plg ? true : false;
        }
        $minMarkers = 50;
        if ($useSuperCluster) {
            // Aggiornato per Joomla 4: howManyMarkerForCluster event
            $evHowMany = new Event('onHowManyMarkerForCluster', [
                'minMarkers' => &$minMarkers
            ]);
            $dispatcher->dispatch($evHowMany);
        }
        if ($useSuperCluster && $curZoom <= $map->clusterZoom && is_array($msDbOk) && (count($msDbOk) > $minMarkers)) {
            $VPlimit = [];

            // Aggiornato per Joomla 4: getClusterCarres event
            $evCarres = new Event('onGetClusterCarres', [
                'VPlimit' => &$VPlimit,
                'vpCalc'  => &$this->m_vpCalc
            ]);
            $dispatcher->dispatch($evCarres);

            $bigger = 0;
            // Aggiornato per Joomla 4: ventileMarkers event
            $evVent = new Event('onVentileMarkers', [
                'bigger'   => &$bigger,
                'VPlimit'  => &$VPlimit,
                'msDbOk'   => $msDbOk
            ]);
            $dispatcher->dispatch($evVent);

            $cluster = null;
            // Aggiornato per Joomla 4: genereJson event
            $evGenJson = new Event('onGenereJson', [
                'cluster' => &$cluster,
                'VPlimit' => $VPlimit,
                'bigger'  => $bigger
            ]);
            $dispatcher->dispatch($evGenJson);

            $data['cluster'] = $cluster;
            echo '<br> Après clusters: ------------------>'.$this->_getElapsed($start_timestamp).'<br>';
        } else {
            $data['markers'] = $this->_purgeNotNeededNodesForJs($msDbOk);
            $data['plines'] = $this->_getPlines($msDbOk, $app, $map);
        }

        echo "<br>nombre genere par xml: ".count($msDbOk);
        echo '<br> Fin: ------------------>'.$this->_getElapsed($start_timestamp).'<br>';

        if ($app->input->getInt('gf_debug', false) == 2) {
            exit();
        }

        $data['infos']['messages'] = [Text::_('COM_GEOFACTORY_DATA_FILE_SUCCESS')];
        $data['infos']['elapsed'] = $this->_getElapsed($start_timestamp);
        
        return json_encode($data);
    }

    function sortPremium($vUid, $idMap)
    {
        $oLists = GeofactoryHelper::getArrayIdMs($idMap);
        $res = [];
        if (is_array($oLists) && is_array($vUid)) {
            foreach ($oLists as $idMs) {
                foreach ($vUid as $uid) {
                    if ($uid['idL'] == $idMs) {
                        $res[] = $uid;
                    }
                }
            }
        }
        return $res;
    }

    protected function _purgeIfZoomMeAndUniqueMarker($msDbOk)
    {
        $res = [];
        if (is_array($msDbOk)) {
            foreach ($msDbOk as $add) {
                if ($add['zm'] == 1) {
                    $res[] = $add;
                    break;
                }
            }
        }
        if (is_array($res) && count($res) == 1) {
            return $res;
        }
        return $msDbOk;
    }

    protected function _purgeNotNeededNodesForJs($msDbOk)
    {
        $res = [];
        if (is_array($msDbOk)) {
            foreach ($msDbOk as $add) {
                unset($add['ow']);
                unset($add['lfr']);
                unset($add['lma']);
                unset($add['low']);
                unset($add['lgu']);
                unset($add['pr']);
                unset($add['ev']);
                $res[] = $add;
            }
        }
        return $res;
    }

    protected function _getSpecial($oMs)
    {
        $vInfo = [];
        $vInfo['idL'] = $oMs->id;
        $vInfo['tl'] = $oMs->typeList;
        $vInfo['rh'] = isset($oMs->avatarSizeH) ? intval($oMs->avatarSizeH) : "";
        $vInfo['rw'] = isset($oMs->avatarSizeW) ? intval($oMs->avatarSizeW) : "";
        $vInfo['mi'] = "";
        $vInfo['pt'] = $oMs->custom_list_1;
        $vInfo['op'] = $oMs->custom_radio_1;
        $vInfo['mx'] = $oMs->maxmarkers;
        $vInfo['md'] = isset($oMs->custom_radio_2) ? intval($oMs->custom_radio_2) : 0;

        if ($oMs->markerIconType == 1) {
            $vInfo['mi'] = (strlen($oMs->customimage) > 3) ? $oMs->customimage : "";
        } else if (($oMs->markerIconType == 2) && (strlen($oMs->mapicon) > 3)) {
            $vInfo['mi'] = (strlen($oMs->mapicon) > 3) ? $oMs->mapicon : "";
        }

        return $vInfo;
    }

    protected function _getPlines($msDbOk, $app, $map)
    {
        $vMarkersProfiles = [];
        $vMarkersWithOwner = [];
        $vMarkersEvent = [];

        $drawFriends = false;
        $drawMyAdd   = false;
        $drawEvents  = false;
        $drawOwners  = false;

        if (is_array($msDbOk)) {
            foreach ($msDbOk as $ms) {
                if (!$drawFriends && isset($ms['lfr']) && $ms['lfr'] > 0) {
                    $drawFriends = true;
                }
                if (!$drawMyAdd && isset($ms['lma']) && $ms['lma'] > 0) {
                    $drawMyAdd = true;
                }
                if (!$drawOwners && isset($ms['low']) && $ms['low'] > 0) {
                    $drawOwners = true;
                }
                if (!$drawEvents && isset($ms['lgu']) && $ms['lgu'] > 0) {
                    $drawEvents = true;
                }

                if (isset($ms['pr']) && $ms['pr'] == 1) {
                    $vMarkersProfiles[] = $ms;
                }
                if (isset($ms['ev']) && $ms['ev'] == 1 && isset($ms['lgu']) && $ms['lgu'] > 0) {
                    $vMarkersEvent[] = $ms;
                }
                if (isset($ms['ow']) && $ms['ow'] > 0 && isset($ms['low']) && $ms['low'] > 0) {
                    $vMarkersWithOwner[] = $ms;
                }
            }
        }

        $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);
        $vCol = [];
        
        // Aggiornato per Joomla 4: getColorPline event
        $evColor = new Event('onGetColorPline', [
            'vCol' => &$vCol
        ]);
        $dispatcher->dispatch($evColor);

        $vPlines = [];
        if ($drawMyAdd) {
            $this->_getPLinesMyAddresses($vPlines, $vCol, $vMarkersProfiles);
        }
        if ($drawFriends) {
            $this->_getPLinesFriends($vPlines, $vCol, $vMarkersProfiles, $app);
        }
        if ($drawEvents) {
            $this->_getPLinesEvents($vPlines, $vCol, $vMarkersProfiles, $vMarkersEvent, $app);
        }
        if ($drawOwners) {
            $this->_getPLinesOwners($vPlines, $vCol, $vMarkersProfiles, $vMarkersWithOwner);
        }

        return $vPlines;
    }

    protected function _getPLinesMyAddresses(&$vPlines, $vCol, $vMarkersProfiles)
    {
        if (!is_array($vMarkersProfiles) || count($vMarkersProfiles) < 1) {
            return;
        }
        $vFait = [];
        foreach ($vMarkersProfiles as $mp) {
            if (in_array($mp['id'], $vFait)) {
                continue;
            }
            $vFait[] = $mp['id'];
            foreach ($vMarkersProfiles as $mpTst) {
                if ($mp['id'] != $mpTst['id']) {
                    continue;
                }
                if (($mp['lat'] == $mpTst['lat']) && ($mp['lng'] == $mpTst['lng'])) {
                    continue;
                }
                $this->_createPlineArray($vPlines, $mp, $mpTst, $vCol, 'linesMyAddr');
            }
        }
    }

    protected function _getPLinesFriends(&$vPlines, $vCol, $vMarkersProfiles, $app)
    {
        $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);
        $vCon = null;

        // Aggiornato per Joomla 4: getFriendsList event
        $evFriends = new Event('onGetFriendsList', [
            'vCon' => &$vCon
        ]);
        $dispatcher->dispatch($evFriends);

        if (is_array($vCon) && count($vCon)) {
            $vPairsTxt = [];
            foreach ($vCon as $con) {
                $id1 = $con->moi;
                $id2 = $con->ami;
                if ($id1 == $id2) {
                    continue;
                }
                $ms1 = null;
                $ms2 = null;
                foreach ($vMarkersProfiles as $msp) {
                    if ($ms1 && $ms2) {
                        break;
                    }
                    if ($msp['id'] == $id1) {
                        $ms1 = $msp;
                        continue;
                    }
                    if ($msp['id'] == $id2) {
                        $ms2 = $msp;
                        continue;
                    }
                }
                if (!$this->_finalisePlinepairs($ms1, $ms2, $vPairsTxt, $id1, $id2)) {
                    continue;
                }
                $this->_createPlineArray($vPlines, $ms1, $ms2, $vCol, 'linesFriends');
            }
        }
    }

    protected function _getPLinesEvents(&$vPlines, $vCol, $vMarkersProfiles, $vMarkersEvent, $app)
    {
        $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);
        $vCon = null;

        // Aggiornato per Joomla 4: getGuestList event
        $evGuest = new Event('onGetGuestList', [
            'vCon' => &$vCon
        ]);
        $dispatcher->dispatch($evGuest);

        if (is_array($vCon) && count($vCon)) {
            $vPairsTxt = [];
            foreach ($vCon as $con) {
                $id1 = $con->event_id;
                $id2 = $con->guest_id;
                $ms1 = null;
                foreach ($vMarkersEvent as $mse) {
                    if ($ms1) {
                        break;
                    }
                    if ($mse['id'] == $id1) {
                        $ms1 = $mse;
                        continue;
                    }
                }
                $ms2 = null;
                foreach ($vMarkersProfiles as $msp) {
                    if ($ms2) {
                        break;
                    }
                    if ($msp['id'] == $id2) {
                        $ms2 = $msp;
                        continue;
                    }
                }
                if (!$this->_finalisePlinepairs($ms1, $ms2, $vPairsTxt, $id1, $id2)) {
                    continue;
                }
                $this->_createPlineArray($vPlines, $ms1, $ms2, $vCol, 'linesGuests');
            }
        }
    }

    protected function _finalisePlinepairs(&$ms1, &$ms2, &$vPairsTxt, $id1, $id2)
    {
        if (!$ms1 || !$ms2)
            return false;
        if ($id1 > $id2) {
            $mst = $ms2;
            $ms2 = $ms1;
            $ms1 = $mst;
        }
        $pair = "{$id1};{$id2}";
        if (in_array($pair, $vPairsTxt))
            return false;
        $vPairsTxt[] = $pair;
        return true;
    }

    protected function _getPLinesOwners(&$vPlines, $vCol, $vMarkersProfiles, $vMarkersWithOwner)
    {
        if (is_array($vMarkersWithOwner) && is_array($vMarkersProfiles)) {
            foreach ($vMarkersWithOwner as $mo) {
                foreach ($vMarkersProfiles as $mpTst) {
                    if (isset($mo['ow']) && isset($mpTst['id']) && $mo['ow'] != $mpTst['id'])
                        continue;
                    $this->_createPlineArray($vPlines, $mo, $mpTst, $vCol, 'linesOwners');
                }
            }
        }
    }

    protected function _createPlineArray(&$vPlines, $m1, $m2, $vCol, $colItem)
    {
        if (!is_array($m1) || !is_array($m2) || count($m1) < 4 || count($m2) < 4)
            return;

        if (!isset($m1['tl']) || !isset($m1['id']) || !isset($m1['lat']) || !isset($m1['lng']) ||
            !isset($m2['id']) || !isset($m2['lat']) || !isset($m2['lng'])) {
            return;
        }

        $colItem = $m1['tl'].$colItem;
        $col = isset($vCol[$colItem]) ? $vCol[$colItem] : "red";
        $vPlines[] = [
            "id1" => $m1['id'],
            "x1"  => $m1['lat'],
            "y1"  => $m1['lng'],
            "id2" => $m2['id'],
            "x2"  => $m2['lat'],
            "y2"  => $m2['lng'],
            "col" => $col
        ];
    }

    protected function _extractMarkersFrom($msDb, $oMs, $latCenterRad, $lngCenterRad, $distRadius, $zIdx)
    {
        // Se necessario
        $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);
        PluginHelper::importPlugin('geocodefactory');
        $app = Factory::getApplication('site');

        $ss_zoomMeId = $app->input->getInt('zmid');
        $ss_zoomMeTy = $app->input->getString('tmty');
        $allM = $app->input->getInt('allM');

        $isUserPlg = false;
        // Aggiornato per Joomla 4: isProfile event
        $evProf = new Event('onIsProfile', [
            'typeList' => $oMs->typeList,
            'isUserPlg'=> &$isUserPlg
        ]);
        $dispatcher->dispatch($evProf);

        $isEventPlg = false;
        // Aggiornato per Joomla 4: isEvent event
        $evEvt = new Event('onIsEvent', [
            'typeList'   => $oMs->typeList,
            'isEventPlg' => &$isEventPlg
        ]);
        $dispatcher->dispatch($evEvt);

        $isOnCurContext = false;
        // Aggiornato per Joomla 4: isOnCurContext event
        $evCont = new Event('onIsOnCurContext', [
            'typeList'      => $oMs->typeList,
            'ss_zoomMeTy'   => $ss_zoomMeTy,
            'isOnCurContext'=> &$isOnCurContext
        ]);
        $dispatcher->dispatch($evCont);

        $listCatIcon = null;
        $listCatEntry = null;
        if ($oMs->markerIconType == 4) {
            // Aggiornato per Joomla 4: getRel_idCat_iconPath event
            $evCatIcon = new Event('onGetRel_idCat_iconPath', [
                'typeList'   => $oMs->typeList,
                'listCatIcon'=> &$listCatIcon,
                'm_idDyncat' => $this->m_idDyncat
            ]);
            $dispatcher->dispatch($evCatIcon);

            // Aggiornato per Joomla 4: getRel_idEntry_idCat event
            $evCatEntry = new Event('onGetRel_idEntry_idCat', [
                'typeList'    => $oMs->typeList,
                'listCatEntry'=> &$listCatEntry
            ]);
            $dispatcher->dispatch($evCatEntry);
        }

        $voUserDetail = [];
        $tmp = new markerObject();
        $tmp->setCommon($oMs, $listCatIcon, $listCatEntry, $this->m_idDyncat);
        $count = 0;
        if (is_array($msDb)) {
            foreach ($msDb as $m) {
                if (isset($oMs->maxmarkers) && ($oMs->maxmarkers > 0) && ($count == $oMs->maxmarkers)) {
                    break;
                }
                $tmp->initialise($m, $zIdx);
                if (!$tmp->baseValues()) {
                    continue;
                }
                if (!$tmp->inRadius($latCenterRad, $lngCenterRad, $distRadius)) {
                    continue;
                }
                if ($allM != 1) {
                    if (!$tmp->inViewport($this->m_vpMaps, $this->m_vpCalc)) {
                        continue;
                    }
                }
                if (!$tmp->setMarkerIcon($app)) {
                    continue;
                }
                $tmp->setAsCurrent($app, $this->m_idCurUser, $isUserPlg, $isEventPlg, $isOnCurContext, $ss_zoomMeId);
                $voUserDetail[] = $tmp->getResult();
                $count++;
            }
        }
        return $voUserDetail;
    }

    protected function _getDataFromMsDb($oMs, &$queryForMsg)
    {
        $query = $this->_getQueryForMs($oMs);
        $brut = $this->_getQueryResult($query, $oMs);
        $queryForMsg = $query;
        return $brut;
    }

    protected function _getQueryResult(&$query, $oMs)
    {
        $db = Factory::getDbo();
        $config = ComponentHelper::getParams('com_geofactory');
        $bigSelect = $config->get('useBigSelect', 1);
        if ($bigSelect > 0) {
            $db->setQuery("SET SQL_BIG_SELECTS=1");
            $db->execute();
        }
        try {
            $db->setQuery($query);
            $oU = $db->loadObjectList();
        } catch (\RuntimeException $e) {
            trigger_error(Text::_('COM_GEOFACTORY_DATA_FILE_QUERY_ERROR') . ': ' . $e->getMessage());
            exit();
        }
        $query = $db->getQuery();

        if (!is_array($oU) || !count($oU)) {
            return null;
        }
        $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);
        PluginHelper::importPlugin('geocodefactory');

        // Aggiornato per Joomla 4: checkMainQueryResults event
        $evCheck = new Event('onCheckMainQueryResults', [
            'typeList' => $oMs->typeList,
            'results'  => &$oU
        ]);
        $dispatcher->dispatch($evCheck);

        return $oU;
    }

    protected function _getQueryForMs($oMs)
    {
        $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);
        PluginHelper::importPlugin('geocodefactory');
        $app = Factory::getApplication('site');
        $curCat = $app->input->getInt('gfcc', -1);
        $this->m_idDyncat = $this->_getDyncatId($app, $oMs->typeList);

        $inCats = $this->_getSecureObjMsVal($oMs, 'include_categories', "");
        if (!is_array($inCats))
            $inCats = explode(',', $inCats);

        $vTmp = [];
        $idTopParent = -1;
        if (isset($oMs->childCats) && $oMs->childCats == 1 && is_array($inCats) && (count($inCats) > 0)) {
            // Aggiornato per Joomla 4: getAllSubCats event
            $evAllSub = new Event('onGetAllSubCats', [
                'typeList'  => $oMs->typeList,
                'catList'   => &$vTmp,
                'idTopParent' => &$idTopParent
            ]);
            $dispatcher->dispatch($evAllSub);

            $childs = [];
            foreach ($inCats as $catPar) {
                $childs[] = $catPar;
                if (is_array($vTmp) && sizeof($vTmp) > 0) {
                    GeofactoryPluginHelper::_getChildCatOf($vTmp, $catPar, $childs, null);
                }
            }
            $inCats = array_unique($childs);
        }
        $inCats = is_array($inCats) ? implode(',', $inCats) : "";

        if ((isset($oMs->catAuto) && ($oMs->catAuto == 1) && ($curCat >= 0)) || ($this->m_idDyncat != -1)) {
            if (!is_array($vTmp) || !count($vTmp)) {
                // Ricarichiamo se serve
                $evAllSub2 = new Event('onGetAllSubCats', [
                    'typeList'  => $oMs->typeList,
                    'catList'   => &$vTmp,
                    'idTopParent' => &$idTopParent
                ]);
                $dispatcher->dispatch($evAllSub2);
            }
            $vRes = [$curCat];
            if (is_array($vTmp) && sizeof($vTmp) > 0) {
                GeofactoryPluginHelper::_getChildCatOf($vTmp, $curCat, $vRes, null);
            }
            $allowedCats = explode(',', $inCats);
            $inCats = [];
            foreach ($vRes as $autoCatCurOrChild) {
                if (!is_array($allowedCats) || (count($allowedCats) < 1) || $allowedCats[0] == 0) {
                    $inCats[] = $autoCatCurOrChild;
                    continue;
                }
                if (in_array($autoCatCurOrChild, $allowedCats)) {
                    $inCats[] = $autoCatCurOrChild;
                }
            }
            if (!is_array($inCats) || count($inCats) < 1) {
                $inCats[] = -1;
            }
            $inCats = implode(',', $inCats);
        }
        $inCats = $this->_getAllowedCats($oMs, $inCats);

        $sqlSelect = ["'{$oMs->typeList}' AS typeList"];
        $sqlJoin = [];
        $sqlWhere = [];

        $params = [];
        $params['linesOwners']   = (isset($oMs->linesOwners) && $oMs->linesOwners > 0) ? 1 : 0;
        $params['useAvatar']     = $this->_getUseAvatar($oMs);
        $params['useSalesArea']  = $this->_getUseSalesArea($oMs);
        $params['field_avatar']  = (isset($oMs->avatarImage) && ($oMs->avatarImage != 0 || $oMs->avatarImage != '')) ? $oMs->avatarImage : 0;
        $params['field_salesArea']= (isset($oMs->salesRadField)) ? $oMs->salesRadField : 0;
        $params['field_title']   = (isset($oMs->field_title)) ? $oMs->field_title : 0;
        $params['onlyPublished'] = (isset($oMs->onlyPublished)) ? $oMs->onlyPublished : 0;
        $params['onlyOnline']    = (isset($oMs->onlyOnline)) ? $oMs->onlyOnline : 0;
        $params['allEvents']     = (isset($oMs->allEvents)) ? $oMs->allEvents : 0;
        $params['fields_coor']   = GeofactoryHelper::getCoordFields((isset($oMs->field_assignation)) ? $oMs->field_assignation : 0);
        $params['inCats']        = $inCats;
        $params['type']          = $oMs->typeList;

        $include_groups = (isset($oMs->include_groups) && is_array($oMs->include_groups)) ? array_filter($oMs->include_groups, 'strlen') : [];
        $params['include_groups'] = count($include_groups) > 0 ? implode(',', $include_groups) : '';

        $params['tags'] = null;
        if (isset($oMs->tags) && is_array($oMs->tags) && count($oMs->tags)) {
            if (isset($oMs->childTags) && ($oMs->childTags == 1)) {
                $vTmp = [];
                GeofactoryPluginHelper::getAllTags(1, $vTmp);
                $vRes = [];
                foreach ($oMs->tags as $papatag) {
                    $vRes[] = $papatag;
                    if (!is_array($vTmp) || !count($vTmp)) {
                        continue;
                    }
                    GeofactoryPluginHelper::_getChildCatOf($vTmp, $papatag, $vRes, null);
                }
                $params['tags'] = is_array($vRes) ? implode(',', array_unique($vRes)) : "";
            } else {
                $params['tags'] = implode(',', $oMs->tags);
            }
        }

        // Aggiornato per Joomla 4: customiseQuery event
        $evCustom = new Event('onCustomiseQuery', [
            'typeList'   => $oMs->typeList,
            'params'     => $params,
            'sqlSelect'  => &$sqlSelect,
            'sqlJoin'    => &$sqlJoin,
            'sqlWhere'   => &$sqlWhere
        ]);
        $dispatcher->dispatch($evCustom);

        // Aggiornato per Joomla 4: setMainQueryFilters event
        $evSetMain = new Event('onSetMainQueryFilters', [
            'typeList' => $oMs->typeList,
            'oMs'      => $oMs,
            'sqlSelect'=> &$sqlSelect,
            'sqlJoin'  => &$sqlJoin,
            'sqlWhere' => &$sqlWhere
        ]);
        $dispatcher->dispatch($evSetMain);

        $query = "";
        $sqlSelect = (is_array($sqlSelect) && count($sqlSelect) > 0) ? implode(',', $sqlSelect) : "";
        $sqlJoin = (is_array($sqlJoin) && count($sqlJoin) > 0) ? ' '.implode(' ', $sqlJoin) : "";
        $sqlWhere = (is_array($sqlWhere) && count($sqlWhere) > 0) ? ' WHERE '.implode(' AND ', $sqlWhere) : "";
        $data = [
            'type'      => $oMs->typeList,
            'sqlSelect' => $sqlSelect,
            'sqlJoin'   => $sqlJoin,
            'sqlWhere'  => $sqlWhere,
            'oMs'       => $oMs
        ];

        // Aggiornato per Joomla 4: getMainQuery event
        $evMainQ = new Event('onGetMainQuery', [
            'data'   => $data,
            'query'  => &$query
        ]);
        $dispatcher->dispatch($evMainQ);

        $query = "SELECT DISTINCT ".$query;
        return $query;
    }

    protected function _checkUserLevel($oMs)
    {
        $user = Factory::getUser();
        $groups = $user->getAuthorisedViewLevels();
        $allow_groups = isset($oMs->allow_groups) ? $oMs->allow_groups : [];
        if (!is_array($allow_groups))
            $allow_groups = [$allow_groups];
        if (count($allow_groups) == 1 && $allow_groups[0] == "")
            return true;
        foreach ($allow_groups as $allow) {
            if (in_array((int)$allow, $groups))
                return true;
        }
        return false;
    }

    protected function _getListInfo($objMs, $vidMakers, $map)
    {
        $config = ComponentHelper::getParams('com_geofactory');
        $sidemode = $config->get('sidemode', 1);
        $data = [];
        $data['id'] = $objMs->id;
        $data['name'] = $objMs->name;
        $data['type'] = $objMs->typeList;
        $data['level'] = $objMs->mslevel;
        $data['bubblewidth'] = (isset($objMs->bubblewidth) && $objMs->bubblewidth > 0) ? $objMs->bubblewidth : 200;
        $data['useSide'] = (strlen(trim($objMs->template_sidebar)) > 3) ? $sidemode : 0;

        $path = "";
        if ($objMs->markerIconType == 1) {
            $path = Uri::root();
        } else if ($objMs->markerIconType == 2) {
            $path = Uri::root().'media/com_geofactory/mapicons/';
        } else if ($objMs->markerIconType == 3 || $objMs->markerIconType == 4) {
            $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);
            
            // Aggiornato per Joomla 4: getIconCommonPath event
            $evIconPath = new Event('onGetIconCommonPath', [
                'typeList'       => $objMs->typeList,
                'markerIconType' => $objMs->markerIconType,
                'path'           => &$path
            ]);
            $dispatcher->dispatch($evIconPath);
        }
        if (!GeofactoryHelper::useNewMethod($map)) {
            sort($vidMakers);
            $data['markers'] = implode(',', $vidMakers);
        }
        $data['commonIconPath'] = $path;
        return $data;
    }

    protected function _getAllowedCats($oMs, $inCats)
    {
        if (strlen($inCats) > 0)
            return $inCats;
        if (!isset($oMs->section) || !is_int($oMs->section) || $oMs->section < 0)
            return "";
        $vRes = $this->_getChildCats($oMs->section);
        return implode(',', $vRes);
    }

    protected function _getUseAvatar($oMs)
    {
        if ((int)$oMs->markerIconType == 3)
            return true;
        return false;
    }

    protected function _getUseSalesArea($oMs)
    {
        if (isset($oMs->salesRadField) && strlen($oMs->salesRadField) > 0)
            return true;
        return false;
    }

    function getCategorySelect($ext, $par, $mapVar)
    {
        $idM = explode('_', $mapVar);
        $lang = '*';
        if (count($idM) > 0) {
            $idM = end($idM);
            $idM = (int)$idM;
            if ($idM > 0) {
                $map = GeofactoryHelper::getMap($idM);
                $lang = (strlen($map->language) > 1) ? $map->language : '*';
            }
        }
        $categoryList = [];
        $idTopParent = -1;

        $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);
        PluginHelper::importPlugin('geocodefactory');

        // Aggiornato per Joomla 4: getAllSubCats event
        $evAllSub = new Event('onGetAllSubCats', [
            'typeList'   => $ext,
            'catList'    => &$categoryList,
            'idTopParent'=> &$idTopParent,
            'lang'       => $lang
        ]);
        $dispatcher->dispatch($evAllSub);

        $vRes = [];
        $indent = "";
        $vRes[] = HTMLHelper::_('select.option', '', Text::_('COM_GEOFACTORY_ALL'));
        if (is_array($categoryList) && sizeof($categoryList) > 0) {
            GeofactoryPluginHelper::_getChildCatOf($categoryList, $par, $vRes, $indent);
        }
        return HTMLHelper::_('select.genericlist', $vRes, "gf_dyncat_sel_{$ext}_{$par}", 'class="gf_dyncat_sel" size="1" onChange="'.$mapVar.'.SLFDYN(this, \''.$ext.'\');" ', 'value', 'text');
    }

    protected function _getDyncatId($app, $typeList)
    {
        $dynCatUsedId = $app->input->get('fc', -1, 'INT');
        if ($dynCatUsedId < 0) {
            return -1;
        }
        $dynCatFromExt = $app->input->get('ext', null, 'STRING');
        $dynCat = false;

        $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);
        
        // Aggiornato per Joomla 4: isMyShortName event
        $evShort = new Event('onIsMyShortName', [
            'typeList'  => $typeList,
            'dynCatFromExt' => $dynCatFromExt,
            'dynCat'    => &$dynCat
        ]);
        $dispatcher->dispatch($evShort);

        if (!$dynCat) {
            return -1;
        }
        return (int)$dynCatUsedId;
    }

    protected function _getSecureObjMsVal($oMs, $prop, $def = null)
    {
        if (!isset($oMs->$prop))
            return $def;
        return $oMs->$prop;
    }

    protected function _getChildCats($section)
    {
        // Da implementare o esistente altrove
        return [$section];
    }

    protected function _getForceRadiusCenterForAllMs(&$jsRadius, &$jsLat, &$jsLng, $arObjMs)
    {
        if ((int)$jsRadius > 0)
            return;
        if (is_array($arObjMs)) {
            foreach ($arObjMs as $idMs => $objMs) {
                if (isset($objMs->rad_allms) && ($objMs->rad_allms < 1))
                    continue;
                if ($objMs->rad_mode != 2)
                    continue;
                if ($objMs->rad_distance <= 0)
                    continue;
                $coor = $this->_getCurrentViewCoordinates($objMs);
                if (!is_array($coor) || (count($coor) != 2) || (($coor[0] + $coor[1]) == 510))
                    continue;
                $jsLat = $coor[0];
                $jsLng = $coor[1];
                $jsRadius = $objMs->rad_distance;
                return;
            }
        }
    }

    protected function _getRadiusCenterDirectory(&$latRad, &$lngRad, $jsRadius, $jsLat, $jsLng, $oMarkerSet, $mapParams)
    {
        $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);

        if ($jsRadius > 0) {
            $latRad = $jsLat;
            $lngRad = $jsLng;
        } else if ($oMarkerSet->rad_distance > 0) {
            if ($oMarkerSet->rad_mode == 0) {
                // Aggiornato per Joomla 4: getCurrentUserProfileCoordinates event
                $coor = [];
                $evProfCoor = new Event('onGetCurrentUserProfileCoordinates', [
                    'typeList' => $oMarkerSet->typeList,
                    'coor'     => &$coor
                ]);
                $dispatcher->dispatch($evProfCoor);

                if (is_array($coor) && count($coor) == 2) {
                    $latRad = $coor[0];
                    $lngRad = $coor[1];
                } else {
                    $oMarkerSet->rad_mode = 1;
                }
            } else if ($oMarkerSet->rad_mode == 2) {
                $coor = $this->_getCurrentViewCoordinates($oMarkerSet);
                if (is_array($coor) && count($coor) == 2) {
                    $latRad = $coor[0];
                    $lngRad = $coor[1];
                } else {
                    $oMarkerSet->rad_mode = 1;
                }
            } else if ($oMarkerSet->rad_mode == 1) {
                $latRad = $mapParams->centerlat;
                $lngRad = $mapParams->centerlng;
            }
        }
    }

    protected function _getCurrentViewCoordinates($oMs)
    {
        if (!$oMs->current_view_center_pattern || $oMs->current_view_center_pattern < 1) {
            return;
        }
        $params = [];
        $params['fields_coor']  = GeofactoryHelper::getCoordFields($oMs->current_view_center_pattern);
        $params['pattern_type'] = GeofactoryHelper::getPatternType($oMs->current_view_center_pattern);
        if (strlen($params['pattern_type']) < 1) {
            return;
        }
        $app = Factory::getApplication('site');
        $curId = $app->input->getInt('zmid');
        if ($curId < 1) {
            return;
        }
        $coor = [];

        $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);
        
        // Aggiornato per Joomla 4: getItemCoordinates event
        $evItemCoor = new Event('onGetItemCoordinates', [
            'patternType' => $params['pattern_type'],
            'curId'       => $curId,
            'coor'        => &$coor,
            'params'      => $params
        ]);
        $dispatcher->dispatch($evItemCoor);

        if (!is_array($coor) || count($coor) < 1 || !isset($coor[$curId])) {
            return;
        }
        return $coor[$curId];
    }

    protected function _getElapsed($start_timestamp)
    {
        $end_timestamp = microtime(true);
        $duration = $end_timestamp - $start_timestamp;
        return $duration . " seconds.";
    }

    public function getIdMsFromName($selLists)
    {
        if ($selLists == -1 || $selLists == '') {
            return [];
        }
        $arr = explode(',', $selLists);
        $res = [];
        foreach ($arr as $a) {
            $a = (int)$a;
            if ($a > 0) {
                $res[] = $a;
            }
        }
        return $res;
    }
}

// Lasciamo invariata la classe markerObject, ma se necessario potremmo aggiornarla
class markerObject
{
    protected $m_vMarker = [];
    protected $m_dbMarker = null;
    protected $m_oMs = null;
    protected $m_idDyncat = -1;
    protected $m_listCatIcon = null;
    protected $m_listCatEntry = null;

    public function setCommon($oMs, $listCatIcon, $listCatEntry, $idDyncat)
    {
        $this->m_oMs = $oMs;
        $this->m_listCatIcon = $listCatIcon;
        $this->m_listCatEntry = $listCatEntry;
        $this->m_idDyncat = $idDyncat;
    }

    public function initialise($m, $zi)
    {
        $this->m_dbMarker = $m;
        $this->m_vMarker = [
            'id'  => 0,
            'idL' => 0,
            'tl'  => null,
            'lat' => null,
            'lng' => null,
            'rt'  => "",
            'sa'  => null,
            'rh'  => null,
            'rw'  => null,
            'di'  => -1,
            'mi'  => null,
            'om'  => 0,
            'tr'  => "",
            'zm'  => 0,
            'cu'  => 0,
            'zi'  => $zi,
            'mi'  => "",
            'ow'  => -1,
            'lfr' => 0,
            'lma' => 0,
            'low' => 0,
            'lgu' => 0,
            'pr'  => 0,
            'ev'  => 0,
            'lv'  => 0,
        ];
    }

    public function baseValues()
    {
        if (!isset($this->m_dbMarker) || !isset($this->m_oMs)) {
            return false;
        }
        
        $oMs = $this->m_oMs;
        $this->m_vMarker['lat'] = $this->_getCoord(isset($this->m_dbMarker->latitude) ? $this->m_dbMarker->latitude : null, 'lat');
        $this->m_vMarker['lng'] = $this->_getCoord(isset($this->m_dbMarker->longitude) ? $this->m_dbMarker->longitude : null, 'lng');
        if (!$this->m_vMarker['lat'] || !$this->m_vMarker['lng'])
            return false;
        $this->m_vMarker['id'] = isset($this->m_dbMarker->id) ? $this->m_dbMarker->id : 0;
        $this->m_vMarker['idL'] = $oMs->id;
        $this->m_vMarker['tl'] = $oMs->typeList;
        $this->m_vMarker['lv'] = $oMs->mslevel;
        $this->m_vMarker['rt'] = isset($this->m_dbMarker->title) ? $this->m_dbMarker->title : "";
        $this->m_vMarker['sa'] = (isset($this->m_dbMarker->sales) && $this->m_dbMarker->sales > 0) ? ($this->m_dbMarker->sales * 1) : 0;
        $this->m_vMarker['rh'] = isset($oMs->avatarSizeH) ? intval($oMs->avatarSizeH) : "";
        $this->m_vMarker['rw'] = isset($oMs->avatarSizeW) ? intval($oMs->avatarSizeW) : "";
        $this->m_vMarker['ow'] = isset($this->m_dbMarker->owner) ? intval($this->m_dbMarker->owner) : -1;
        $this->m_vMarker['tr'] = isset($this->m_dbMarker->trace) ? $this->m_dbMarker->trace : "";

        if (isset($oMs->linesFriends))
            $this->m_vMarker['lma'] = intval($oMs->linesFriends);
        if (isset($oMs->linesMyAddr))
            $this->m_vMarker['lma'] = intval($oMs->linesMyAddr);
        if (isset($oMs->linesOwners))
            $this->m_vMarker['low'] = intval($oMs->linesOwners);
        if (isset($oMs->linesGuests))
            $this->m_vMarker['lgu'] = intval($oMs->linesGuests);

        return true;
    }

    public function inRadius($latRad, $lngRad, $rad = null)
    {
        if (!$rad)
            $rad = $this->m_oMs->rad_distance;
        if ((!is_numeric($rad)) || (!$rad > 0))
            return true;
        $km = 6371;
        if ($this->m_oMs->rad_unit == 1)
            $km = 3959; 
        else if ($this->m_oMs->rad_unit == 2)
            $km = 3440;

        if ((!$latRad) || (!$lngRad) || ($latRad == "") || ($lngRad == ""))
            return false;
        $dist = $this->_getDistance($latRad, $lngRad, $this->m_vMarker['lat'], $this->m_vMarker['lng'], $km);
        $dist = $dist - $this->m_vMarker['sa'];
        if ($dist > $rad)
            return false;
        $this->m_vMarker['di'] = round($dist, 2);
        return true;
    }

    protected function _getCoord($val, $type)
    {
        if (!$val) return null;
        return floatval($val);
    }

    protected function _getDistance($la1, $lo1, $la2, $lo2, $e)
    {
        $dla = deg2rad($la2 - $la1);
        $dlo = deg2rad($lo2 - $lo1);
        $a = sin($dla / 2) * sin($dla / 2) +
             cos(deg2rad($la1)) * cos(deg2rad($la2)) *
             sin($dlo / 2) * sin($dlo / 2);
        $c = 2 * asin(sqrt($a));
        $d = $e * $c;
        return $d;
    }

    public function setMarkerIcon($app)
    {
        $this->m_vMarker['mi'] = "";
        if ($this->m_oMs->markerIconType == 1) {
            $this->m_vMarker['mi'] = (strlen($this->m_oMs->customimage) > 3) ? $this->m_oMs->customimage : "";
        } else if (($this->m_oMs->markerIconType == 2) && (strlen($this->m_oMs->mapicon) > 3)) {
            $this->m_vMarker['mi'] = (strlen($this->m_oMs->mapicon) > 3) ? $this->m_oMs->mapicon : "";
        } else if ($this->m_oMs->markerIconType == 3) {
            $fieldImg = isset($this->m_dbMarker->avatar) ? $this->m_dbMarker->avatar : '';
            
            // Aggiornato per Joomla 4: getIconPathFromBrutDbValue event
            $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);
            $evIcon = new Event('onGetIconPathFromBrutDbValue', [
                'typeList' => $this->m_oMs->typeList,
                'fieldImg' => &$fieldImg,
                'itemId'   => $this->m_dbMarker->id
            ]);
            $dispatcher->dispatch($evIcon);

            if (strlen($fieldImg) > 3) {
                $this->m_vMarker['mi'] = $fieldImg;
                $this->m_vMarker['om'] = 1;
            }
        } else if ($this->m_oMs->markerIconType == 4) {
            $this->_setCatIcon();
        }
        return true;
    }

    function _setCatIcon()
    {
        if (!is_array($this->m_listCatEntry) || !is_array($this->m_listCatIcon))
            return;
        $idCur = $this->m_vMarker['id'];
        if ($idCur < 1)
            return;
        if (!array_key_exists($idCur, $this->m_listCatEntry))
            return;
        $myCat = $this->m_listCatEntry[$idCur];
        if ($this->m_idDyncat > 0)
            $myCat = $this->m_idDyncat;
        if (!array_key_exists($myCat, $this->m_listCatIcon))
            return;
        $this->m_vMarker['mi'] = $this->m_listCatIcon[$myCat];
    }

    public function setAsCurrent($app, $idCurUser, $isUserPlg, $isEventPlg, $isOnCurContext, $ss_zoomMeId)
    {
        $isOnCurItem = false;
        if ($this->m_vMarker['id'] == $ss_zoomMeId) {
            $isOnCurItem = true;
        }
        if ($isOnCurItem) {
            $this->m_vMarker['zm'] = 1;
        }
        if ($isUserPlg && ($this->m_vMarker['id'] == $idCurUser)) {
            $this->m_vMarker['cu'] = 1;
        }
        $this->m_vMarker['pr'] = $isUserPlg ? 1 : 0;
        $this->m_vMarker['ev'] = $isEventPlg ? 1 : 0;
    }

    public function getResult()
    {
        return $this->m_vMarker;
    }

    public function inViewport($vp, &$vpAll)
    {
        if (!is_array($vp) || count($vp) != 4) {
            if (!is_array($vpAll) || count($vpAll) != 4) {
                $vpAll[0] = $this->m_vMarker['lat'];
                $vpAll[1] = $this->m_vMarker['lng'];
                $vpAll[2] = $this->m_vMarker['lat'];
                $vpAll[3] = $this->m_vMarker['lng'];
                return true;
            }
            if ($this->m_vMarker['lat'] < $vpAll[0]) {
                $vpAll[0] = $this->m_vMarker['lat'];
            }
            if ($this->m_vMarker['lng'] < $vpAll[1]) {
                $vpAll[1] = $this->m_vMarker['lng'];
            }
            if ($this->m_vMarker['lat'] >= $vpAll[2]) {
                $vpAll[2] = $this->m_vMarker['lat'];
            }
            if ($this->m_vMarker['lng'] >= $vpAll[3]) {
                $vpAll[3] = $this->m_vMarker['lng'];
            }
            return true;
        }
        $vpAll = $vp;
        if (!GeofactoryHelper::markerInArea($this->m_vMarker, $vp)) {
            return false;
        }
        return true;
    }
}
