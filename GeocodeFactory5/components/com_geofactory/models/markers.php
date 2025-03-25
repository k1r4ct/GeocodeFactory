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
use Joomla\CMS\Log\Log;
use Joomla\Event\Event;
use Joomla\Event\DispatcherInterface;


// Funzioni di ordinamento per gli array di markers
if (!function_exists('orderUser')) {
    /**
     * Funzione di ordinamento per nome
     *
     * @param   array  $a  Primo elemento
     * @param   array  $b  Secondo elemento
     * @return  int
     */
    function orderUser($a, $b) {
        if (!isset($a['rt']) || !isset($b['rt'])) {
            return 0;
        }
        return strnatcasecmp($a['rt'], $b['rt']);
    }
}

if (!function_exists('orderUserDist')) {
    /**
     * Funzione di ordinamento per distanza
     *
     * @param   array  $a  Primo elemento
     * @param   array  $b  Secondo elemento
     * @return  bool
     */
    function orderUserDist($a, $b) {
        if (!isset($a['di']) || !isset($b['di'])) {
            return 0;
        }
        return $a['di'] > $b['di'];
    }
}

/**
 * Modello per la gestione dei markers
 *
 * @since  1.0
 */
class GeofactoryModelMarkers extends ItemModel
{
    /**
     * Contesto del modello
     *
     * @var    string
     * @since  1.0
     */
    protected $_context = 'com_geofactory.markers';
    
    /**
     * ID dell'utente corrente
     *
     * @var    integer
     * @since  1.0
     */
    protected $m_idCurUser = 0;
    
    /**
     * ID della categoria dinamica
     *
     * @var    integer
     * @since  1.0
     */
    protected $m_idDyncat = -1;
    
    /**
     * Viewport della mappa
     *
     * @var    array
     * @since  1.0
     */
    protected $m_vpMaps = null;
    
    /**
     * Calcolo del viewport
     *
     * @var    array
     * @since  1.0
     */
    protected $m_vpCalc = null;

    public function getItem($pk=null){
        
        return null;
    }

    /**
     * Crea il file dei markers (XML o JSON)
     *
     * @param   integer  $idMap  ID della mappa
     * @param   string   $out    Formato di output ('json' o altro)
     * @return  string
     * @throws  \Exception
     */
    public function createfile($idMap, $out)
    {
   
        // Ottieni l'utente corrente
        $my = Factory::getUser();
        $this->m_idCurUser = $my->id;

        // Recupera applicazione e altri oggetti
        $app = Factory::getApplication('site');
        $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);

        // Verifica parametri obbligatori
        $itemid = $app->input->getInt('Itemid', 0);
        if ($idMap < 1) {
            throw new \RuntimeException(Text::_('COM_GEOFACTORY_MAP_ERROR_ID'), 404);
        }

        // Carica la mappa e i markerset collegati
        try {
            
            $map = GeofactoryHelper::getMap($idMap);
            if (!$map) {
                throw new \RuntimeException(Text::_('COM_GEOFACTORY_MAP_NOT_FOUND'), 404);
            }
            
            
            $ms = GeofactoryHelper::getArrayIdMs($idMap);
            $data = $this->_createDataFile($out, $ms, $map);
            
            // Se il caching è attivo, salva il file
            if (property_exists($map, 'cacheTime') && $map->cacheTime > 0) {
                $cacheFile = GeofactoryHelper::getCacheFileName($idMap, $itemid);
                if ($cacheFile) {
                    try {
                        $fp = fopen($cacheFile, 'w');
                        if ($fp) {
                            fwrite($fp, $data);
                            fclose($fp);
                        }
                    } catch (\Exception $e) {
                        Log::add('Errore nel salvataggio del file cache: ' . $e->getMessage(), Log::WARNING, 'geofactory');
                    }
                }
            }
            
            return $data;
            
        } catch (\Exception $e) {
            Log::add('Errore in createfile: ' . $e->getMessage(), Log::ERROR, 'geofactory');
            throw $e;
        }
    }

    /**
     * Crea i dati dei markers
     *
     * @param   string    $out    Formato di output
     * @param   array     $ms     Array di markerset
     * @param   object    $map    Oggetto mappa
     * @return  string    Dati in formato JSON o XML
     */
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
            return json_encode($data);
        }

        // Dispatcher e plugin
        $app = Factory::getApplication('site');
        $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);
        PluginHelper::importPlugin('geocodefactory');

        // Esegui eventi in modo aggiornato per Joomla 4
        $event = new Event('onResetBeforeGateway', []);
        $dispatcher->dispatch('onResetBeforeGateway', $event);

        // Parametri dalla request
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

        // Elaborazione di ogni markerset
        if (is_array($ms) && count($ms) > 0) {
            foreach ($ms as $idMs) {
                // Skip se non nella selezione
                if ($useSelLists && !in_array($idMs, $selLists)) {
                    continue;
                }

                // Carica il markerset
                $objMs = GeofactoryHelper::getMs($idMs);
                
                if (!$objMs) {
                    $data['infos']['messages'][] = Text::_('COM_GEOFACTORY_MS_NO_PLUGIN') . $idMs;
                    continue;
                }

                // Verifica il livello utente
                if (!$this->_checkUserLevel($objMs)) {
                    $data['infos']['messages'][] = Text::_('COM_GEOFACTORY_MS_LEVEL') . $objMs->id;
                    continue;
                }

                $zIdx++;
                $queryForMsg = "";
                $msDb = $this->_getDataFromMsDb($objMs, $queryForMsg);


                // Debug info
                if (GeofactoryHelper::isDebugMode()) {
                    $data['infos']['queries'][] = $queryForMsg;
                }
                
                // Verifica dati
                if (!$msDb) {
                    $data['infos']['messages'][] = Text::_('COM_GEOFACTORY_MS_NO_DATA') . $objMs->id;
                    continue;
                }

                // Parametri raggio
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
                $dispatcher->dispatch('onIsSpecialMs', $evIsSpec);

                // Elaborazione markerset normale o speciale
                if (!$isSpecialMs) {
                    $msDb = $this->_extractMarkersFrom($msDb, $objMs, $latRad, $lngRad, $jsRadius, $zIdx);
                    
                    if (!is_array($msDb) || !count($msDb)) {
                        $data['infos']['messages'][] = Text::_('COM_GEOFACTORY_MS_NO_MARKERS') . $objMs->id;
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
                    $dispatcher->dispatch('onCleanResultsFromPlugins', $evClean);

                    // Filtra e aggiungi i markers
                    if (is_array($msDb)) {
                        foreach ($msDb as $add) {
                            if (!isset($add['id'])) {
                                continue;
                            }
                            
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
                
                // Informazioni lista
                $data['lists'][] = $this->_getListInfo($objMs, $arIdsMarkers, $map);
            }
        }

        // Verifica che ci siano markers
        if (!is_array($msDbOk) || count($msDbOk) < 1) {
            $data['infos']['messages'][] = Text::_('COM_GEOFACTORY_MAP_NO_MARKERS_IN_MSS');
            $data['infos']['elapsed'] = $this->_getElapsed($start_timestamp);
            return json_encode($data);
        }

        // Filtraggi finali
        if ($map->totalmarkers == 1) {
            $msDbOk = $this->_purgeIfZoomMeAndUniqueMarker($msDbOk);
        }

        // Randomizzazione
        if (isset($map->randomMarkers) && $map->randomMarkers) {
            shuffle($msDbOk);
        }

        // Ordinamento
        if (isset($msDbOk[0]['di']) && $msDbOk[0]['di'] != -1) {
            usort($msDbOk, "orderUserDist");
        } else {
            usort($msDbOk, "orderUser");
        }

        // Premium
        if (property_exists($map, 'template') && strpos($map->template, "{sidelists_premium}") !== false) {
            $msDbOk = $this->sortPremium($msDbOk, $map->id);
        }

        // Clustering
        $useSuperCluster = (isset($map->useCluster) && $map->useCluster == 2 && $newMethod) ? true : false;
        if ($useSuperCluster) {
            // Se esiste il plugin per i cluster
            $plg = PluginHelper::getPlugin('geocodefactory', 'plg_geofactory_cluster');
            $useSuperCluster = $plg ? true : false;
        }
        
        $minMarkers = 50;
        if ($useSuperCluster) {
            // Evento per determinare numero minimo markers per cluster
            $evHowMany = new Event('onHowManyMarkerForCluster', [
                'minMarkers' => &$minMarkers
            ]);
            $dispatcher->dispatch('onHowManyMarkerForCluster', $evHowMany);
        }
        
        // Elaborazione cluster se necessario
        if ($useSuperCluster && $curZoom <= $map->clusterZoom && is_array($msDbOk) && (count($msDbOk) > $minMarkers)) {
            $VPlimit = [];

            // Evento per ottenere i riquadri del cluster
            $evCarres = new Event('onGetClusterCarres', [
                'VPlimit' => &$VPlimit,
                'vpCalc'  => &$this->m_vpCalc
            ]);
            $dispatcher->dispatch('onGetClusterCarres', $evCarres);

            $bigger = 0;
            // Evento per distribuire i markers
            $evVent = new Event('onVentileMarkers', [
                'bigger'   => &$bigger,
                'VPlimit'  => &$VPlimit,
                'msDbOk'   => $msDbOk
            ]);
            $dispatcher->dispatch('onVentileMarkers', $evVent);

            $cluster = null;
            // Evento per generare il JSON del cluster
            $evGenJson = new Event('onGenereJson', [
                'cluster' => &$cluster,
                'VPlimit' => $VPlimit,
                'bigger'  => $bigger
            ]);
            $dispatcher->dispatch('onGenereJson', $evGenJson);

            $data['cluster'] = $cluster;
        } else {
            // Markers standard
            $data['markers'] = $this->_purgeNotNeededNodesForJs($msDbOk);
            $data['plines'] = $this->_getPlines($msDbOk, $app, $map);
        }

        // Finalizzazione
        $data['infos']['messages'] = [Text::_('COM_GEOFACTORY_DATA_FILE_SUCCESS')];
        $data['infos']['elapsed'] = $this->_getElapsed($start_timestamp);
        
        return json_encode($data);
    }

    /**
     * Ordina i markerset in base alla lista premium
     *
     * @param   array    $vUid   Array di marker
     * @param   integer  $idMap  ID della mappa
     * @return  array    Array ordinato
     */
    public function sortPremium($vUid, $idMap)
    {
        $oLists = GeofactoryHelper::getArrayIdMs($idMap);
        $res = [];
        
        if (is_array($oLists) && is_array($vUid)) {
            foreach ($oLists as $idMs) {
                foreach ($vUid as $uid) {
                    if (isset($uid['idL']) && $uid['idL'] == $idMs) {
                        $res[] = $uid;
                    }
                }
            }
        }
        
        return $res;
    }

    /**
     * Purge dei markers se è richiesto solo lo zoom su uno specifico
     *
     * @param   array  $msDbOk  Array di markers
     * @return  array  Array filtrato
     */
    protected function _purgeIfZoomMeAndUniqueMarker($msDbOk)
    {
        $res = [];
        
        if (is_array($msDbOk)) {
            foreach ($msDbOk as $add) {
                if (isset($add['zm']) && $add['zm'] == 1) {
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

    /**
     * Rimuove i nodi non necessari per il JavaScript
     *
     * @param   array  $msDbOk  Array di markers
     * @return  array  Array pulito
     */
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

    /**
     * Ottiene informazioni speciali per il markerset
     *
     * @param   object  $oMs  Oggetto markerset
     * @return  array   Informazioni speciali
     */
    protected function _getSpecial($oMs)
    {
        $vInfo = [];
        $vInfo['idL'] = $oMs->id;
        $vInfo['tl'] = $oMs->typeList;
        $vInfo['rh'] = isset($oMs->avatarSizeH) ? intval($oMs->avatarSizeH) : "";
        $vInfo['rw'] = isset($oMs->avatarSizeW) ? intval($oMs->avatarSizeW) : "";
        $vInfo['mi'] = "";
        $vInfo['pt'] = isset($oMs->custom_list_1) ? $oMs->custom_list_1 : "";
        $vInfo['op'] = isset($oMs->custom_radio_1) ? $oMs->custom_radio_1 : "";
        $vInfo['mx'] = isset($oMs->maxmarkers) ? $oMs->maxmarkers : 0;
        $vInfo['md'] = isset($oMs->custom_radio_2) ? intval($oMs->custom_radio_2) : 0;

        if (isset($oMs->markerIconType) && $oMs->markerIconType == 1) {
            $vInfo['mi'] = (isset($oMs->customimage) && strlen($oMs->customimage) > 3) ? $oMs->customimage : "";
        } else if (isset($oMs->markerIconType) && $oMs->markerIconType == 2 && isset($oMs->mapicon) && strlen($oMs->mapicon) > 3) {
            $vInfo['mi'] = $oMs->mapicon;
        }

        return $vInfo;
    }

    /**
     * Ottiene le polilinee da visualizzare sulla mappa
     *
     * @param   array   $msDbOk  Array di markers
     * @param   object  $app     Oggetto applicazione
     * @param   object  $map     Oggetto mappa
     * @return  array   Array di polilinee
     */
    protected function _getPlines($msDbOk, $app, $map)
    {
        $vMarkersProfiles = [];
        $vMarkersWithOwner = [];
        $vMarkersEvent = [];

        $drawFriends = false;
        $drawMyAdd   = false;
        $drawEvents  = false;
        $drawOwners  = false;

        // Analisi dei markers
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

        // Ottiene i colori per le linee
        $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);
        $vCol = [];
        
        $evColor = new Event('onGetColorPline', [
            'vCol' => &$vCol
        ]);
        $dispatcher->dispatch('onGetColorPline', $evColor);

        // Inizializzazione array risultato
        $vPlines = [];
        
        // Creazione polilinee per i diversi tipi
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

    /**
     * Restituisce le polilinee per i miei indirizzi
     *
     * @param   array  &$vPlines         Array polilinee
     * @param   array  $vCol             Array colori
     * @param   array  $vMarkersProfiles Array markers dei profili
     * @return  void
     */
    protected function _getPLinesMyAddresses(&$vPlines, $vCol, $vMarkersProfiles)
    {
        if (!is_array($vMarkersProfiles) || count($vMarkersProfiles) < 1) {
            return;
        }
        
        $vFait = [];
        
        foreach ($vMarkersProfiles as $mp) {
            if (!isset($mp['id'])) {
                continue;
            }
            
            if (in_array($mp['id'], $vFait)) {
                continue;
            }
            
            $vFait[] = $mp['id'];
            
            foreach ($vMarkersProfiles as $mpTst) {
                if (!isset($mpTst['id']) || $mp['id'] != $mpTst['id']) {
                    continue;
                }
                
                if (isset($mp['lat'], $mp['lng'], $mpTst['lat'], $mpTst['lng']) && 
                    ($mp['lat'] == $mpTst['lat']) && ($mp['lng'] == $mpTst['lng'])) {
                    continue;
                }
                
                $this->_createPlineArray($vPlines, $mp, $mpTst, $vCol, 'linesMyAddr');
            }
        }
    }

    /**
     * Restituisce le polilinee per gli amici
     *
     * @param   array   &$vPlines         Array polilinee
     * @param   array   $vCol             Array colori
     * @param   array   $vMarkersProfiles Array markers dei profili
     * @param   object  $app              Oggetto applicazione
     * @return  void
     */
    protected function _getPLinesFriends(&$vPlines, $vCol, $vMarkersProfiles, $app)
    {
        $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);
        $vCon = null;

        // Evento per ottenere la lista amici
        $evFriends = new Event('onGetFriendsList', [
            'vCon' => &$vCon
        ]);
        $dispatcher->dispatch('onGetFriendsList', $evFriends);

        if (is_array($vCon) && count($vCon)) {
            $vPairsTxt = [];
            
            foreach ($vCon as $con) {
                if (!isset($con->moi) || !isset($con->ami)) {
                    continue;
                }
                
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
                    
                    if (isset($msp['id'])) {
                        if ($msp['id'] == $id1) {
                            $ms1 = $msp;
                            continue;
                        }
                        if ($msp['id'] == $id2) {
                            $ms2 = $msp;
                            continue;
                        }
                    }
                }
                
                if (!$this->_finalisePlinepairs($ms1, $ms2, $vPairsTxt, $id1, $id2)) {
                    continue;
                }
                
                $this->_createPlineArray($vPlines, $ms1, $ms2, $vCol, 'linesFriends');
            }
        }
    }

    /**
     * Restituisce le polilinee per gli eventi
     *
     * @param   array   &$vPlines         Array polilinee
     * @param   array   $vCol             Array colori
     * @param   array   $vMarkersProfiles Array markers dei profili
     * @param   array   $vMarkersEvent    Array markers degli eventi
     * @param   object  $app              Oggetto applicazione
     * @return  void
     */
    protected function _getPLinesEvents(&$vPlines, $vCol, $vMarkersProfiles, $vMarkersEvent, $app)
    {
        $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);
        $vCon = null;

        // Evento per ottenere la lista ospiti
        $evGuest = new Event('onGetGuestList', [
            'vCon' => &$vCon
        ]);
        $dispatcher->dispatch('onGetGuestList', $evGuest);

        if (is_array($vCon) && count($vCon)) {
            $vPairsTxt = [];
            
            foreach ($vCon as $con) {
                if (!isset($con->event_id) || !isset($con->guest_id)) {
                    continue;
                }
                
                $id1 = $con->event_id;
                $id2 = $con->guest_id;
                
                $ms1 = null;
                foreach ($vMarkersEvent as $mse) {
                    if ($ms1) {
                        break;
                    }
                    
                    if (isset($mse['id']) && $mse['id'] == $id1) {
                        $ms1 = $mse;
                        continue;
                    }
                }
                
                $ms2 = null;
                foreach ($vMarkersProfiles as $msp) {
                    if ($ms2) {
                        break;
                    }
                    
                    if (isset($msp['id']) && $msp['id'] == $id2) {
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

    /**
     * Finalizza le coppie di polilinee
     *
     * @param   array   &$ms1       Primo marker
     * @param   array   &$ms2       Secondo marker
     * @param   array   &$vPairsTxt Array di coppie già elaborate
     * @param   int     $id1        ID primo marker
     * @param   int     $id2        ID secondo marker
     * @return  bool                True se la coppia può essere usata
     */
    protected function _finalisePlinepairs(&$ms1, &$ms2, &$vPairsTxt, $id1, $id2)
    {
        if (!$ms1 || !$ms2) {
            return false;
        }
        
        // Ordina gli ID per evitare duplicati
        if ($id1 > $id2) {
            $mst = $ms2;
            $ms2 = $ms1;
            $ms1 = $mst;
        }
        
        $pair = "{$id1};{$id2}";
        
        // Salta se la coppia è già stata elaborata
        if (in_array($pair, $vPairsTxt)) {
            return false;
        }
        
        $vPairsTxt[] = $pair;
        return true;
    }

    /**
     * Restituisce le polilinee per gli owner
     *
     * @param   array  &$vPlines           Array polilinee
     * @param   array  $vCol               Array colori
     * @param   array  $vMarkersProfiles   Array markers dei profili
     * @param   array  $vMarkersWithOwner  Array markers con proprietario
     * @return  void
     */
    protected function _getPLinesOwners(&$vPlines, $vCol, $vMarkersProfiles, $vMarkersWithOwner)
    {
        if (is_array($vMarkersWithOwner) && is_array($vMarkersProfiles)) {
            foreach ($vMarkersWithOwner as $mo) {
                if (!isset($mo['ow'])) {
                    continue;
                }
                
                foreach ($vMarkersProfiles as $mpTst) {
                    if (!isset($mpTst['id']) || $mo['ow'] != $mpTst['id']) {
                        continue;
                    }
                    
                    $this->_createPlineArray($vPlines, $mo, $mpTst, $vCol, 'linesOwners');
                }
            }
        }
    }

    /**
     * Crea l'array per una polilinea
     *
     * @param   array   &$vPlines  Array polilinee
     * @param   array   $m1        Primo marker
     * @param   array   $m2        Secondo marker
     * @param   array   $vCol      Array colori
     * @param   string  $colItem   Chiave colore
     * @return  void
     */
    protected function _createPlineArray(&$vPlines, $m1, $m2, $vCol, $colItem)
    {
        // Verifica che i markers siano completi
        if (!is_array($m1) || !is_array($m2) || count($m1) < 4 || count($m2) < 4) {
            return;
        }

        // Verifica che ci siano tutte le chiavi necessarie
        if (!isset($m1['tl']) || !isset($m1['id']) || !isset($m1['lat']) || !isset($m1['lng']) ||
            !isset($m2['id']) || !isset($m2['lat']) || !isset($m2['lng'])) {
            return;
        }

        // Costruisci la chiave per il colore
        $colItem = $m1['tl'] . $colItem;
        $col = isset($vCol[$colItem]) ? $vCol[$colItem] : "red";
        
        // Aggiungi la polilinea
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

    /**
     * Estrae i markers dal database
     *
     * @param   array    $msDb         Array markers dal DB
     * @param   object   $oMs          Oggetto markerset
     * @param   float    $latCenterRad Latitudine centro
     * @param   float    $lngCenterRad Longitudine centro
     * @param   float    $distRadius   Raggio distanza
     * @param   integer  $zIdx         Indice z-index
     * @return  array    Array markers elaborati
     */
    protected function _extractMarkersFrom($msDb, $oMs, $latCenterRad, $lngCenterRad, $distRadius, $zIdx)
    {
        // Dispatcher e plugin
        $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);
        PluginHelper::importPlugin('geocodefactory');
        $app = Factory::getApplication('site');

        // Parametri
        $ss_zoomMeId = $app->input->getInt('zmid');
        $ss_zoomMeTy = $app->input->getString('tmty');
        $allM = $app->input->getInt('allM');

        // Flag plugin
        $isUserPlg = false;
        $evProf = new Event('onIsProfile', [
            'typeList' => $oMs->typeList,
            'isUserPlg'=> &$isUserPlg
        ]);
        $dispatcher->dispatch('onIsProfile', $evProf);

        $isEventPlg = false;
        $evEvt = new Event('onIsEvent', [
            'typeList'   => $oMs->typeList,
            'isEventPlg' => &$isEventPlg
        ]);
        $dispatcher->dispatch('onIsEvent', $evEvt);

        $isOnCurContext = false;
        $evCont = new Event('onIsOnCurContext', [
            'typeList'      => $oMs->typeList,
            'ss_zoomMeTy'   => $ss_zoomMeTy,
            'isOnCurContext'=> &$isOnCurContext
        ]);
        $dispatcher->dispatch('onIsOnCurContext', $evCont);

        // Gestione icone per categoria
        $listCatIcon = null;
        $listCatEntry = null;
        if (isset($oMs->markerIconType) && $oMs->markerIconType == 4) {
            $evCatIcon = new Event('onGetRel_idCat_iconPath', [
                'typeList'   => $oMs->typeList,
                'listCatIcon'=> &$listCatIcon,
                'm_idDyncat' => $this->m_idDyncat
            ]);
            $dispatcher->dispatch('onGetRel_idCat_iconPath', $evCatIcon);

            $evCatEntry = new Event('onGetRel_idEntry_idCat', [
                'typeList'    => $oMs->typeList,
                'listCatEntry'=> &$listCatEntry
            ]);
            $dispatcher->dispatch('onGetRel_idEntry_idCat', $evCatEntry);
        }

        // Inizializzazione
        $voUserDetail = [];
        $tmp = new markerObject();
        $tmp->setCommon($oMs, $listCatIcon, $listCatEntry, $this->m_idDyncat);
        $count = 0;
        
        // Elaborazione markers
        if (is_array($msDb)) {
            foreach ($msDb as $m) {
                // Limite massimo raggiunto
                if (isset($oMs->maxmarkers) && ($oMs->maxmarkers > 0) && ($count == $oMs->maxmarkers)) {
                    break;
                }
                
                // Inizializza e verifica
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
                
                // Imposta marker corrente
                $tmp->setAsCurrent($app, $this->m_idCurUser, $isUserPlg, $isEventPlg, $isOnCurContext, $ss_zoomMeId);
                $voUserDetail[] = $tmp->getResult();
                $count++;
            }
        }
        
        return $voUserDetail;
    }

    /**
     * Ottiene i dati dal database per un markerset
     *
     * @param   object   $oMs          Oggetto markerset
     * @param   string   &$queryForMsg Query per debug
     * @return  array    Array risultati dal database
     */
    protected function _getDataFromMsDb($oMs, &$queryForMsg)
    {
        $query = $this->_getQueryForMs($oMs);
        $brut = $this->_getQueryResult($query, $oMs);
        $queryForMsg = $query;
        return $brut;
    }

    /**
     * Esegue la query e restituisce i risultati
     *
     * @param   string   &$query   Query SQL
     * @param   object   $oMs      Oggetto markerset
     * @return  array    Array risultati dal database
     */
    protected function _getQueryResult(&$query, $oMs)
    {
        $db = Factory::getDbo();
        $config = ComponentHelper::getParams('com_geofactory');
        
        // Impostazioni per query grandi
        $bigSelect = $config->get('useBigSelect', 1);
        if ($bigSelect > 0) {
            try {
                $db->setQuery("SET SQL_BIG_SELECTS=1");
                $db->execute();
            } catch (\Exception $e) {
                Log::add('Errore SET SQL_BIG_SELECTS: ' . $e->getMessage(), Log::WARNING, 'geofactory');
            }
        }
        
        // Esegui la query
        try {
            $db->setQuery($query);
            $oU = $db->loadObjectList();
            $query = $db->getQuery(true);
            $query = (string)$query;
        } catch (\Exception $e) {
            Log::add('Errore query: ' . $e->getMessage(), Log::ERROR, 'geofactory');
            return null;
        }

        // Verifica risultati
        if (!is_array($oU) || !count($oU)) {
            return null;
        }
        
        // Evento per verificare i risultati
        $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);
        $evCheck = new Event('onCheckMainQueryResults', [
            'typeList' => $oMs->typeList,
            'results'  => &$oU
        ]);
        $dispatcher->dispatch('onCheckMainQueryResults', $evCheck);

        return $oU;
    }

    /**
     * Crea la query per un markerset
     *
     * @param   object   $oMs      Oggetto markerset
     * @return  string   Query SQL
     */
    protected function _getQueryForMs($oMs)
    {
        $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);
        PluginHelper::importPlugin('geocodefactory');
        $app = Factory::getApplication('site');
        
        // Recupera categoria corrente
        $curCat = $app->input->getInt('gfcc', -1);
        $this->m_idDyncat = $this->_getDyncatId($app, $oMs->typeList);

        // Categorie incluse
        $inCats = $this->_getSecureObjMsVal($oMs, 'include_categories', "");
        if (!is_array($inCats)) {
            $inCats = explode(',', $inCats);
        }

        // Sottocategorie
        $vTmp = [];
        $idTopParent = -1;
        if (isset($oMs->childCats) && $oMs->childCats == 1 && is_array($inCats) && (count($inCats) > 0)) {
            // Evento per ottenere le sottocategorie
            $evAllSub = new Event('onGetAllSubCats', [
                'typeList'    => $oMs->typeList,
                'catList'     => &$vTmp,
                'idTopCategory' => &$idTopParent
            ]);
            $dispatcher->dispatch('onGetAllSubCats', $evAllSub);

            // Aggiunge le sottocategorie
            $childs = [];
            foreach ($inCats as $catPar) {
                $childs[] = $catPar;
                if (is_array($vTmp) && count($vTmp) > 0) {
                    GeofactoryPluginHelper::getChildCatOf($vTmp, $catPar, $childs, null);
                }
            }
            $inCats = array_unique($childs);
        }
        
        // Converti in stringa
        $inCats = is_array($inCats) ? implode(',', $inCats) : "";

        // Categoria auto
        if ((isset($oMs->catAuto) && ($oMs->catAuto == 1) && ($curCat >= 0)) || ($this->m_idDyncat != -1)) {
            if (!is_array($vTmp) || !count($vTmp)) {
                // Ricarica le categorie se necessario
                $evAllSub2 = new Event('onGetAllSubCats', [
                    'typeList'    => $oMs->typeList,
                    'catList'     => &$vTmp,
                    'idTopParent' => &$idTopParent
                ]);
                $dispatcher->dispatch('onGetAllSubCats', $evAllSub2);
            }
            
            // Filtra per categorie permesse
            $vRes = [$curCat];
            if (is_array($vTmp) && count($vTmp) > 0) {
                GeofactoryPluginHelper::getChildCatOf($vTmp, $curCat, $vRes, null);
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
        
        // Filtro finale categorie
        $inCats = $this->_getAllowedCats($oMs, $inCats);

        // Inizializzazione array per query
        $sqlSelect = ["'{$oMs->typeList}' AS typeList"];
        $sqlJoin = [];
        $sqlWhere = [];

        // Parametri per i plugin
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

        // Gruppi
        $include_groups = (isset($oMs->include_groups) && is_array($oMs->include_groups)) 
            ? array_filter($oMs->include_groups, 'strlen') 
            : [];
        $params['include_groups'] = count($include_groups) > 0 ? implode(',', $include_groups) : '';

        // Tag
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
                    GeofactoryPluginHelper::getChildCatOf($vTmp, $papatag, $vRes, null);
                }
                
                $params['tags'] = is_array($vRes) ? implode(',', array_unique($vRes)) : "";
            } else {
                $params['tags'] = implode(',', $oMs->tags);
            }
        }

        // Eventi per customizzare la query
        $evCustom = new Event('onCustomiseQuery', [
            'typeList'   => $oMs->typeList,
            'params'     => $params,
            'sqlSelect'  => &$sqlSelect,
            'sqlJoin'    => &$sqlJoin,
            'sqlWhere'   => &$sqlWhere
        ]);
        $dispatcher->dispatch('onCustomiseQuery', $evCustom);

        // Filtri aggiuntivi
        $evSetMain = new Event('onSetMainQueryFilters', [
            'typeList'  => $oMs->typeList,
            'oMs'       => $oMs,
            'sqlSelect' => &$sqlSelect,
            'sqlJoin'   => &$sqlJoin,
            'sqlWhere'  => &$sqlWhere
        ]);
        $dispatcher->dispatch('onSetMainQueryFilters', $evSetMain);

        // Costruzione query finale
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

        // Evento per la query finale
        $evMainQ = new Event('onGetMainQuery', [
            'data'   => $data,
            'query'  => &$query
        ]);
        $dispatcher->dispatch('onGetMainQuery', $evMainQ);

        // Aggiunta DISTINCT e return
        $query = "SELECT DISTINCT " . $query;
        return $query;
    }

    /**
     * Verifica il livello utente per un markerset
     *
     * @param   object  $oMs  Oggetto markerset
     * @return  bool    True se l'utente ha accesso
     */
    protected function _checkUserLevel($oMs)
    {
        $user = Factory::getUser();
        $groups = $user->getAuthorisedViewLevels();
        
        $allow_groups = isset($oMs->allow_groups) ? $oMs->allow_groups : [];
        if (!is_array($allow_groups)) {
            $allow_groups = [$allow_groups];
        }
        
        if (count($allow_groups) == 1 && $allow_groups[0] == "") {
            return true;
        }
        
        foreach ($allow_groups as $allow) {
            if (in_array((int)$allow, $groups)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Ottiene informazioni sulla lista per un markerset
     *
     * @param   object   $objMs       Oggetto markerset
     * @param   array    $vidMakers   Array ID markers
     * @param   object   $map         Oggetto mappa
     * @return  array    Informazioni lista
     */
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
        $data['useSide'] = (isset($objMs->template_sidebar) && strlen(trim($objMs->template_sidebar)) > 3) ? $sidemode : 0;

        // Path icone
        $path = "";
        if (isset($objMs->markerIconType)) {
            if ($objMs->markerIconType == 1) {
                $path = Uri::root();
            } else if ($objMs->markerIconType == 2) {
                $path = Uri::root() . 'media/com_geofactory/mapicons/';
            } else if ($objMs->markerIconType == 3 || $objMs->markerIconType == 4) {
                $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);
                
                $evIconPath = new Event('onGetIconCommonPath', [
                    'typeList'       => $objMs->typeList,
                    'markerIconType' => $objMs->markerIconType,
                    'path'           => &$path
                ]);
                $dispatcher->dispatch('onGetIconCommonPath', $evIconPath);
            }
        }
        
        // Aggiungi lista markers se non si usa il nuovo metodo
        if (!GeofactoryHelper::useNewMethod($map)) {
            $vidMakers = is_array($vidMakers) ? $vidMakers : [];
            sort($vidMakers);
            $data['markers'] = implode(',', $vidMakers);
        }
        
        $data['commonIconPath'] = $path;
        return $data;
    }

    /**
     * Ottiene le categorie permesse
     *
     * @param   object   $oMs     Oggetto markerset
     * @param   string   $inCats  Categorie incluse
     * @return  string   Categorie permesse
     */
    protected function _getAllowedCats($oMs, $inCats)
    {
        if (strlen($inCats) > 0) {
            return $inCats;
        }
        
        if (!isset($oMs->section) || !is_numeric($oMs->section) || $oMs->section < 0) {
            return "";
        }
        
        $vRes = $this->_getChildCats($oMs->section);
        return implode(',', $vRes);
    }

    /**
     * Verifica se usare l'avatar come icona
     *
     * @param   object   $oMs     Oggetto markerset
     * @return  bool     True se usare avatar
     */
    protected function _getUseAvatar($oMs)
    {
        return (isset($oMs->markerIconType) && (int)$oMs->markerIconType == 3);
    }

    /**
     * Verifica se usare l'area di vendita
     *
     * @param   object   $oMs     Oggetto markerset
     * @return  bool     True se usare area vendita
     */
    protected function _getUseSalesArea($oMs)
    {
        return (isset($oMs->salesRadField) && strlen($oMs->salesRadField) > 0);
    }

    /**
     * Restituisce il selettore di categorie
     *
     * @param   string   $ext     Estensione
     * @param   int      $par     ID genitore
     * @param   string   $mapVar  Nome variabile mappa
     * @return  string   HTML del selettore
     */
    public function getCategorySelect($ext, $par, $mapVar)
    {
        $idM = explode('_', $mapVar);
        $lang = '*';
        
        if (count($idM) > 0) {
            $idM = end($idM);
            $idM = (int)$idM;
            
            if ($idM > 0) {
                $map = GeofactoryHelper::getMap($idM);
                $lang = (property_exists($map, 'language') && strlen($map->language) > 1) ? $map->language : '*';
            }
        }
        
        $categoryList = [];
        $idTopParent = -1;

        $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);
        PluginHelper::importPlugin('geocodefactory');

        // Evento per ottenere le sottocategorie
        $evAllSub = new Event('onGetAllSubCats', [
            'typeList'   => $ext,
            'catList'    => &$categoryList,
            'idTopParent'=> &$idTopParent,
            'lang'       => $lang
        ]);
        $dispatcher->dispatch('onGetAllSubCats', $evAllSub);

        // Crea il selector
        $vRes = [];
        $indent = "";
        $vRes[] = HTMLHelper::_('select.option', '', Text::_('COM_GEOFACTORY_ALL'));
        
        if (is_array($categoryList) && count($categoryList) > 0) {
            GeofactoryPluginHelper::getChildCatOf($categoryList, $par, $vRes, $indent);
        }
        
        return HTMLHelper::_('select.genericlist', $vRes, "gf_dyncat_sel_{$ext}_{$par}", 'class="gf_dyncat_sel" size="1" onChange="'.$mapVar.'.SLFDYN(this, \''.$ext.'\');" ', 'value', 'text');
    }

    /**
     * Ottiene l'ID della categoria dinamica
     *
     * @param   object   $app       Oggetto applicazione
     * @param   string   $typeList  Tipo lista
     * @return  int      ID categoria dinamica
     */
    protected function _getDyncatId($app, $typeList)
    {
        $dynCatUsedId = $app->input->getInt('fc', -1);
        if ($dynCatUsedId < 0) {
            return -1;
        }
        
        $dynCatFromExt = $app->input->getString('ext', null);
        $dynCat = false;

        $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);
        
        // Evento per verificare il nome breve
        $evShort = new Event('onIsMyShortName', [
            'typeList'  => $typeList,
            'dynCatFromExt' => $dynCatFromExt,
            'dynCat'    => &$dynCat
        ]);
        $dispatcher->dispatch('onIsMyShortName', $evShort);

        if (!$dynCat) {
            return -1;
        }
        
        return (int)$dynCatUsedId;
    }

    /**
     * Ottiene un valore sicuro da un oggetto markerset
     *
     * @param   object   $oMs  Oggetto markerset
     * @param   string   $prop Proprietà
     * @param   mixed    $def  Valore default
     * @return  mixed    Valore sicuro
     */
    protected function _getSecureObjMsVal($oMs, $prop, $def = null)
    {
        if (!isset($oMs->$prop)) {
            return $def;
        }
        
        return $oMs->$prop;
    }

    /**
     * Ottiene le categorie figlie
     *
     * @param   int     $section  ID sezione
     * @return  array   Array categorie figlie
     */
    protected function _getChildCats($section)
    {
        // Da implementare o esistente altrove
        return [$section];
    }

    /**
     * Imposta il centro del raggio per tutti i markerset
     *
     * @param   float    &$jsRadius  Raggio
     * @param   float    &$jsLat     Latitudine
     * @param   float    &$jsLng     Longitudine
     * @param   array    $arObjMs    Array oggetti markerset
     * @return  void
     */
    protected function _getForceRadiusCenterForAllMs(&$jsRadius, &$jsLat, &$jsLng, $arObjMs)
    {
        if ((int)$jsRadius > 0) {
            return;
        }
        
        if (is_array($arObjMs)) {
            foreach ($arObjMs as $idMs => $objMs) {
                if (isset($objMs->rad_allms) && ($objMs->rad_allms < 1)) {
                    continue;
                }
                
                if (!isset($objMs->rad_mode) || $objMs->rad_mode != 2) {
                    continue;
                }
                
                if (!isset($objMs->rad_distance) || $objMs->rad_distance <= 0) {
                    continue;
                }
                
                $coor = $this->_getCurrentViewCoordinates($objMs);
                if (!is_array($coor) || (count($coor) != 2) || (($coor[0] + $coor[1]) == 510)) {
                    continue;
                }
                
                $jsLat = $coor[0];
                $jsLng = $coor[1];
                $jsRadius = $objMs->rad_distance;
                return;
            }
        }
    }

    /**
     * Ottiene il centro del raggio per una directory
     *
     * @param   float    &$latRad      Latitudine
     * @param   float    &$lngRad      Longitudine
     * @param   float    $jsRadius     Raggio JavaScript
     * @param   float    $jsLat        Latitudine JavaScript
     * @param   float    $jsLng        Longitudine JavaScript
     * @param   object   $oMarkerSet   Oggetto markerset
     * @param   object   $mapParams    Parametri mappa
     * @return  void
     */
    protected function _getRadiusCenterDirectory(&$latRad, &$lngRad, $jsRadius, $jsLat, $jsLng, $oMarkerSet, $mapParams)
    {
        $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);

        if ($jsRadius > 0) {
            $latRad = $jsLat;
            $lngRad = $jsLng;
        } else if (isset($oMarkerSet->rad_distance) && $oMarkerSet->rad_distance > 0) {
            if (isset($oMarkerSet->rad_mode) && $oMarkerSet->rad_mode == 0) {
                // Coordinate profilo utente corrente
                $coor = [];
                $evProfCoor = new Event('onGetCurrentUserProfileCoordinates', [
                    'typeList' => $oMarkerSet->typeList,
                    'coor'     => &$coor
                ]);
                $dispatcher->dispatch('onGetCurrentUserProfileCoordinates', $evProfCoor);

                if (is_array($coor) && count($coor) == 2) {
                    $latRad = $coor[0];
                    $lngRad = $coor[1];
                } else {
                    $oMarkerSet->rad_mode = 1;
                }
            } else if (isset($oMarkerSet->rad_mode) && $oMarkerSet->rad_mode == 2) {
                // Coordinate dalla vista corrente
                $coor = $this->_getCurrentViewCoordinates($oMarkerSet);
                if (is_array($coor) && count($coor) == 2) {
                    $latRad = $coor[0];
                    $lngRad = $coor[1];
                } else {
                    $oMarkerSet->rad_mode = 1;
                }
            } else if (isset($oMarkerSet->rad_mode) && $oMarkerSet->rad_mode == 1) {
                // Centro mappa
                $latRad = $mapParams->centerlat;
                $lngRad = $mapParams->centerlng;
            }
        }
    }

    /**
     * Ottiene coordinate dalla vista corrente
     *
     * @param   object   $oMs  Oggetto markerset
     * @return  array|null  Coordinate o null
     */
    protected function _getCurrentViewCoordinates($oMs)
    {
        if (!isset($oMs->current_view_center_pattern) || $oMs->current_view_center_pattern < 1) {
            return null;
        }
        
        $params = [];
        $params['fields_coor']  = GeofactoryHelper::getCoordFields($oMs->current_view_center_pattern);
        $params['pattern_type'] = GeofactoryHelper::getPatternType($oMs->current_view_center_pattern);
        
        if (strlen($params['pattern_type']) < 1) {
            return null;
        }
        
        $app = Factory::getApplication('site');
        $curId = $app->input->getInt('zmid');
        
        if ($curId < 1) {
            return null;
        }
        
        $coor = [];
        $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);
        
        // Evento per ottenere coordinate di un item
        $evItemCoor = new Event('onGetItemCoordinates', [
            'patternType' => $params['pattern_type'],
            'curId'       => $curId,
            'coor'        => &$coor,
            'params'      => $params
        ]);
        $dispatcher->dispatch('onGetItemCoordinates', $evItemCoor);

        if (!is_array($coor) || count($coor) < 1 || !isset($coor[$curId])) {
            return null;
        }
        
        return $coor[$curId];
    }

    /**
     * Calcola il tempo trascorso
     *
     * @param   float    $start_timestamp  Timestamp iniziale
     * @return  string   Tempo trascorso formattato
     */
    protected function _getElapsed($start_timestamp)
    {
        $end_timestamp = microtime(true);
        $duration = $end_timestamp - $start_timestamp;
        return number_format($duration, 4) . " seconds";
    }

    /**
     * Ottiene gli ID markerset da un nome
     *
     * @param   string   $selLists  Lista di ID o nomi
     * @return  array    Array di ID markerset
     */
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

/**
 * Classe per gestire un marker
 *
 * @since  1.0
 */
class markerObject
{
    /**
     * Marker
     *
     * @var  array
     */
    protected $m_vMarker = [];
    
    /**
     * Marker dal DB
     *
     * @var  object
     */
    protected $m_dbMarker = null;
    
    /**
     * Oggetto markerset
     *
     * @var  object
     */
    protected $m_oMs = null;
    
    /**
     * ID categoria dinamica
     *
     * @var  integer
     */
    protected $m_idDyncat = -1;
    
    /**
     * Lista icone categorie
     *
     * @var  array
     */
    protected $m_listCatIcon = null;
    
    /**
     * Lista entries categorie
     *
     * @var  array
     */
    protected $m_listCatEntry = null;

    /**
     * Imposta i parametri comuni
     *
     * @param  object  $oMs           Oggetto markerset
     * @param  array   $listCatIcon   Lista icone categorie
     * @param  array   $listCatEntry  Lista entries categorie
     * @param  int     $idDyncat      ID categoria dinamica
     */
    public function setCommon($oMs, $listCatIcon, $listCatEntry, $idDyncat)
    {
        $this->m_oMs = $oMs;
        $this->m_listCatIcon = $listCatIcon;
        $this->m_listCatEntry = $listCatEntry;
        $this->m_idDyncat = $idDyncat;
    }

    /**
     * Inizializza il marker
     *
     * @param  object  $m   Marker dal DB
     * @param  int     $zi  Z-index
     */
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

    /**
     * Imposta i valori base del marker
     *
     * @return  bool  True se marker valido
     */
    public function baseValues()
    {
        if (!isset($this->m_dbMarker) || !isset($this->m_oMs)) {
            return false;
        }
        
        $oMs = $this->m_oMs;
        $this->m_vMarker['lat'] = $this->_getCoord(isset($this->m_dbMarker->latitude) ? $this->m_dbMarker->latitude : null, 'lat');
        $this->m_vMarker['lng'] = $this->_getCoord(isset($this->m_dbMarker->longitude) ? $this->m_dbMarker->longitude : null, 'lng');
        
        if (!$this->m_vMarker['lat'] || !$this->m_vMarker['lng']) {
            return false;
        }
        
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

        // Flags per linee
        if (isset($oMs->linesFriends)) {
            $this->m_vMarker['lma'] = intval($oMs->linesFriends);
        }
        if (isset($oMs->linesMyAddr)) {
            $this->m_vMarker['lma'] = intval($oMs->linesMyAddr);
        }
        if (isset($oMs->linesOwners)) {
            $this->m_vMarker['low'] = intval($oMs->linesOwners);
        }
        if (isset($oMs->linesGuests)) {
            $this->m_vMarker['lgu'] = intval($oMs->linesGuests);
        }

        return true;
    }

    /**
     * Verifica se il marker è nel raggio
     *
     * @param   float   $latRad  Latitudine centro
     * @param   float   $lngRad  Longitudine centro
     * @param   float   $rad     Raggio
     * @return  bool    True se nel raggio
     */
    public function inRadius($latRad, $lngRad, $rad = null)
    {
        if (!$rad && isset($this->m_oMs->rad_distance)) {
            $rad = $this->m_oMs->rad_distance;
        }
        
        if ((!is_numeric($rad)) || (!$rad > 0)) {
            return true;
        }
        
        $km = 6371; // Terra
        if (isset($this->m_oMs->rad_unit)) {
            if ($this->m_oMs->rad_unit == 1) {
                $km = 3959; // Miglia
            } else if ($this->m_oMs->rad_unit == 2) {
                $km = 3440; // Miglia nautiche
            }
        }

        if ((!$latRad) || (!$lngRad) || ($latRad == "") || ($lngRad == "")) {
            return false;
        }
        
        $dist = $this->_getDistance($latRad, $lngRad, $this->m_vMarker['lat'], $this->m_vMarker['lng'], $km);
        $dist = $dist - $this->m_vMarker['sa'];
        
        if ($dist > $rad) {
            return false;
        }
        
        $this->m_vMarker['di'] = round($dist, 2);
        return true;
    }

    /**
     * Ottiene una coordinata valida
     *
     * @param   mixed   $val   Valore
     * @param   string  $type  Tipo (lat/lng)
     * @return  float|null  Coordinata o null
     */
    protected function _getCoord($val, $type)
    {
        if (!$val) {
            return null;
        }
        
        return floatval($val);
    }

    /**
     * Calcola la distanza tra due punti
     *
     * @param   float   $la1  Latitudine punto 1
     * @param   float   $lo1  Longitudine punto 1
     * @param   float   $la2  Latitudine punto 2
     * @param   float   $lo2  Longitudine punto 2
     * @param   float   $e    Raggio terrestre
     * @return  float   Distanza
     */
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

    /**
     * Imposta l'icona del marker
     *
     * @param   object  $app  Applicazione
     * @return  bool    True se icona impostata
     */
    public function setMarkerIcon($app)
    {
        $this->m_vMarker['mi'] = "";
        
        if (isset($this->m_oMs->markerIconType)) {
            if ($this->m_oMs->markerIconType == 1) {
                $this->m_vMarker['mi'] = (isset($this->m_oMs->customimage) && strlen($this->m_oMs->customimage) > 3) ? $this->m_oMs->customimage : "";
            } else if (($this->m_oMs->markerIconType == 2) && (isset($this->m_oMs->mapicon) && strlen($this->m_oMs->mapicon) > 3)) {
                $this->m_vMarker['mi'] = $this->m_oMs->mapicon;
            } else if ($this->m_oMs->markerIconType == 3) {
                $fieldImg = isset($this->m_dbMarker->avatar) ? $this->m_dbMarker->avatar : '';
                
                // Evento per ottenere path icona
                $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);
                $evIcon = new Event('onGetIconPathFromBrutDbValue', [
                    'typeList' => $this->m_oMs->typeList,
                    'fieldImg' => &$fieldImg,
                    'itemId'   => isset($this->m_dbMarker->id) ? $this->m_dbMarker->id : 0
                ]);
                $dispatcher->dispatch('onGetIconPathFromBrutDbValue', $evIcon);

                if (strlen($fieldImg) > 3) {
                    $this->m_vMarker['mi'] = $fieldImg;
                    $this->m_vMarker['om'] = 1;
                }
            } else if ($this->m_oMs->markerIconType == 4) {
                $this->_setCatIcon();
            }
        }
        
        return true;
    }

    /**
     * Imposta l'icona per categoria
     */
    protected function _setCatIcon()
    {
        if (!is_array($this->m_listCatEntry) || !is_array($this->m_listCatIcon)) {
            return;
        }
        
        $idCur = $this->m_vMarker['id'];
        if ($idCur < 1) {
            return;
        }
        
        if (!array_key_exists($idCur, $this->m_listCatEntry)) {
            return;
        }
        
        $myCat = $this->m_listCatEntry[$idCur];
        if ($this->m_idDyncat > 0) {
            $myCat = $this->m_idDyncat;
        }
        
        if (!array_key_exists($myCat, $this->m_listCatIcon)) {
            return;
        }
        
        $this->m_vMarker['mi'] = $this->m_listCatIcon[$myCat];
    }

    /**
     * Imposta il marker come corrente
     *
     * @param   object   $app          Applicazione
     * @param   int      $idCurUser    ID utente corrente
     * @param   bool     $isUserPlg    Flag plugin utente
     * @param   bool     $isEventPlg   Flag plugin evento
     * @param   bool     $isOnCurContext  Flag contesto corrente
     * @param   int      $ss_zoomMeId  ID zoom
     */
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

    /**
     * Ottiene il risultato finale
     *
     * @return  array  Marker finale
     */
    public function getResult()
    {
        return $this->m_vMarker;
    }

    /**
     * Verifica se il marker è nel viewport
     *
     * @param   array   $vp     Viewport
     * @param   array   &$vpAll Viewport globale
     * @return  bool    True se nel viewport
     */
    public function inViewport($vp, &$vpAll)
    {
        if (!is_array($vp) || count($vp) != 4) {
            if (!is_array($vpAll) || count($vpAll) != 4) {
                $vpAll = [
                    0 => $this->m_vMarker['lat'],
                    1 => $this->m_vMarker['lng'],
                    2 => $this->m_vMarker['lat'],
                    3 => $this->m_vMarker['lng']
                ];
                return true;
            }
            
            // Aggiorna viewport globale
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
        
        // Usa il metodo statico
        return GeofactoryHelper::markerInArea($this->m_vMarker, $vp);
    }
}