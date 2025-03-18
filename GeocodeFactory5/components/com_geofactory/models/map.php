<?php
/**
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      
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
use Joomla\CMS\Session\Session;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Event\Event;
use Joomla\Event\DispatcherInterface;

class GeofactoryModelMap extends ItemModel
{
    protected $mapContext  = 'c';  // c=component, m=module, ...
    protected $m_idM       = 0;
    protected $m_loadFull  = false;

    public function set_loadFull($b)
    {
        $this->m_loadFull = $b;
    }

    public function getItem($idMap = null)
    {
        $app   = Factory::getApplication('site');
        $idMap = !empty($idMap) ? $idMap : (int) $app->input->getInt('id');
        $this->m_idM = $idMap;
        $map   = $this->_loadMap($idMap);

        // Variables di lavoro
        $map->forceZoom = 0;

        $this->_loadMapTemplate($map);
        $this->_cleanCss($map);
        $this->_defineContext($map);

        $this->_item[$idMap] = $map;
        return $this->_item[$idMap];
    }

    public function setMapContext($c)
    {
        $this->mapContext = $c;
    }

    public function createfile($idMap)
    {
        $map = $this->_loadMap($idMap, true);
        $map = (array) $map;
        return json_encode($map);
    }

    private function _loadMap($idMap, $full = false)
    {
        if ($idMap < 1) {
            throw new \Exception(Text::_('COM_GEOFACTORY_MAP_ERROR_ID'), 404);
        }

        if ($this->m_loadFull) {
            $full = true;
        }

        try {
            $map = GeofactoryHelper::getMap($idMap);
            if (!$map) {
                throw new \Exception(Text::_('COM_GEOFACTORY_MAP_ERROR_UNKNOW'), 404);
            }
            if ($full) {
                $this->_mergeAllDataToMap($map);
            }
        }
        catch (\Exception $e) {
            if ($e->getCode() == 404) {
                throw new \Exception($e->getMessage(), 404);
            } else {
                $this->setError($e);
                throw new \Exception($e->getMessage(), 404);
            }
            return null;
        }

        // Definisce il nome della variabile interna della mappa
        $map->mapInternalName = $this->mapContext . '_gf_' . $map->id;

        return $map;
    }

    private function _defineContext(&$map)
    {
        $app    = Factory::getApplication('site');
        $option = $app->input->getString('option', '');

        // Reset
        $map->gf_zoomMeId   = 0;
        $map->gf_zoomMeType = '';
        $map->gf_curCat     = -1;

        // Se non siamo in com_geofactory
        if (strtolower($option) != "com_geofactory") {
            PluginHelper::importPlugin('geocodefactory');
            $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);

            // Creazione evento per definire il contesto: "onDefineContext"
            $evDefContext = new Event('onDefineContext', [
                'option' => $option,
                'map'    => &$map
            ]);
            $dispatcher->dispatch('onDefineContext', $evDefContext);

            // Compatibilità ascendante: riprendi eventuali variabili dalla sessione
            $session = Factory::getSession();
            $sszmid  = $session->get('gf_zoomMeId');
            $sszmty  = $session->get('gf_zoomMeType');

            if (($sszmid > 0) && ($map->gf_zoomMeId == 0)) {
                $map->gf_zoomMeId = $sszmid;
            }
            if ((strlen($sszmty) > 0) && ($map->gf_zoomMeType == '')) {
                $map->gf_zoomMeType = $sszmty;
            }
        }
    }

    // Aggiunge i parametri utili alla mappa
    private function _mergeAllDataToMap(&$map)
    {
        $app        = Factory::getApplication('site');
        $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);
        $config     = ComponentHelper::getParams('com_geofactory');
        $session    = Factory::getSession();

        PluginHelper::importPlugin('geocodefactory');

        // Dati dall'URL
        $map->forceZoom = $app->input->getInt('zf', 0);
        $mapName = $app->input->getString('mn');
        $map->mapInternalName = (strlen($mapName) > 3) ? $mapName : $map->mapInternalName;

        $map->mapStyle = str_replace(array("\n", "\t", "  ", "\r"), '', trim($map->mapStyle));

        // Clustering
        $map->gridSize     = (intval($map->gridSize) > 0) ? intval($map->gridSize) : 60;
        $map->minClustSize = (intval($map->minimumClusterSize) > 0) ? intval($map->minimumClusterSize) : 2;
        $map->endZoom      = (intval($map->clusterZoom) > 0) ? intval($map->clusterZoom) : 10;
        $map->imagePath    = (strlen(trim($map->imagePath)) > 0)
            ? $map->imagePath
            : Uri::root() . 'media/com_geofactory/cluster/';
        $map->imageSizes   = (strlen(trim($map->imageSizes)) > 0) ? $map->imageSizes : "";

        // Dyn radius maximum
        $map->dynRadDistMax = (intval($map->dynRadDistMax) > 0) ? intval($map->dynRadDistMax) : 50;
        $map->colorSales    = $config->get('colorSalesArea', "red");
        $map->colorRadius   = $config->get('colorRadius', "red");

        // Percorso immagini comune
        $map->common_image_path = Uri::root() . 'media/com_geofactory/assets/';

        // Passa il giusto index.php
        $map->rootJoomla = Uri::root(true) . "/index.php";

        // Testi
        $map->idsNoStreetView   = Text::_('COM_GEOFACTORY_NO_STREEETVIEW_HERE');
        $map->dynRadTxtCenter   = Text::_('COM_GEOFACTORY_DYN_RAD_MOVE_CENTER');
        $map->dynRadTxtDrag     = Text::_('COM_GEOFACTORY_DYN_RAD_MOVE_SIZER');

        // Nota a piè di mappa
        $map->hideNote = $config->get('hideNote') ? 1 : 0;

        // Uso del metodo newMethod sperimentale
        $map->newMethod = (int)$config->get('newMethod');

        // gatewayPresent -> onGatewayPresent
        $gateways = [];
        $evGate = new Event('onGatewayPresent', [
            'gateways' => &$gateways
        ]);
        $dispatcher->dispatch('onGatewayPresent', $evGate);

        if (is_array($gateways) && count($gateways)) {
            foreach ($gateways as $k => $v) {
                $map->$k = $v;
            }
        }

        // Dati di sessione
        $map->ss_zoomMeId = $app->input->getInt('zmid');
        $map->ss_zoomMeTy = $app->input->getString('tmty');

        // isProfile -> onIsProfile
        $map->ss_zoomMeProf = 0;
        $evProf = new Event('onIsProfile', [
            'typeList' => $map->ss_zoomMeTy,
            'isProfile'=> &$map->ss_zoomMeProf
        ]);
        $dispatcher->dispatch('onIsProfile', $evProf);

        $map->ss_zoomMeProf = $map->ss_zoomMeProf ? 1 : 0;

        // Centrage per l'articolo
        $articleArray = $session->get('gf_article_map_center', []);
        $session->clear('gf_article_map_center');
        $map->ss_artLat = (is_array($articleArray) && count($articleArray) > 1)
            ? $this->_checkCoord($articleArray[0])
            : 255;
        $map->ss_artLng = (is_array($articleArray) && count($articleArray) > 1)
            ? $this->_checkCoord($articleArray[1])
            : 255;

        // Ricerca IP
        $map->centerlatPhpGc = 0;
        $map->centerlngPhpGc = 0;
        $noFetchUserIp = $config->get('noFetchUserIp', 1);
        if (function_exists('file_get_contents') && ($noFetchUserIp == 1)) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            if (strlen($ip) > 6) {
                $json = '';
                $url = "http://ipinfo.io/{$ip}/json";
                $opts = ['http' => ['timeout' => 1]];
                $context = stream_context_create($opts);
                $json = @file_get_contents($url, false, $context);
                if (strlen($json) > 2) {
                    $details = json_decode($json);
                    if ($details && isset($details->loc) && strlen($details->loc) > 3) {
                        $coor = explode(',', $details->loc);
                        if (is_array($coor) && (count($coor) == 2)) {
                            $map->centerlatPhpGc = $this->_checkCoord($coor[0]);
                            $map->centerlngPhpGc = $this->_checkCoord($coor[1]);
                        }
                    }
                }
            }
        }

        $map->centerlat = $this->_checkCoord($map->centerlat);
        $map->centerlng = $this->_checkCoord($map->centerlng);
    }

    private function _checkCoord($coord)
    {
        if (!$coord) return 0;
        if ($coord == "?") return 0;
        if ($coord == 255) return 0;
        if ($coord == "") return 0;
        if (!is_numeric($coord)) return 0;
        return $coord;
    }

    private function _loadMapTemplate(&$map)
    {
        $app        = Factory::getApplication('site');
        $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);
        $config     = ComponentHelper::getParams('com_geofactory');
        PluginHelper::importPlugin('geocodefactory');

        $template = htmlspecialchars_decode($map->template);
        $mapVar   = $map->mapInternalName;

        if (!isset($map->cssMap)) {
            $map->cssMap = '';
        }
        $map->fullCss = $map->cssMap;

        // --- La carta
        $width  = (strlen($map->mapwidth) > 5) ? $map->mapwidth : "width:200px";
        $height = (strlen($map->mapheight) > 5) ? $map->mapheight : "height:200px";
        $size   = str_replace(' ', '', $width . ";" . $height . ";");

        $carte  = '<div id="gf_waitZone"></div>';
        $carte .= '<div id="' . $mapVar . '" class="gf_map" style="' . $size . ' background-repeat:no-repeat;background-position:center center; background-image:url(' . Uri::root() . 'media/com_geofactory/assets/icon_wait.gif);"></div>';

        // Se non c'è {map} nel template...
        if (strpos($template, "{map}") === false) {
            $template .= '{map}';
        }

        // templateAuto
        if ($map->templateAuto == 1) {
            $carte .= '<div id="' . $mapVar . '" class="gf_map" style="max-width: none; background-repeat:no-repeat;background-position:center center; background-image:url(' . Uri::root() . 'media/com_geofactory/assets/icon_wait.gif);"></div>';
            $template = $this->_getSidePanel($map);
        }

        $template = str_replace('{map}', $carte, $template);

        // {number}
        $number = '<span id="gf_NbrMarkersPre">0</span>';
        $template = str_replace('{number}', $number, $template);

        // {sidebar}, {sidelists}, {sidelists_premium}
        $template = str_replace('{sidebar}', '<div id="gf_sidebar"></div>', $template);
        $template = str_replace('{sidelists}', '<div id="gf_sidelists"></div>', $template);
        $template = str_replace('{sidelists_premium}', '<div id="gf_sidelistPremium"></div>', $template);

        // Bottoni
        $locate_me = '<input id="gf_locate_me" type="button" value="' . Text::_('COM_GEOFACTORY_LOCATE_ME') . '" onClick="' . $mapVar . '.LMBTN();" />';
        $save_me = '';
        $reset_me = '';
        $near_me = '<input id="gf_near_me" type="button" value="' . Text::_('COM_GEOFACTORY_NEAR_ME') . '" onClick="' . $mapVar . '.NMBTN();" />';
        $save_me_full = '';
        $reset_map = '<input type="button" onclick="' . $mapVar . '.SLRES();" id="gf_reset_rad_btn" value="' . Text::_('COM_GEOFACTORY_RESET_MAP') . '"/>';
        
        // {layer_selector}
        $template = str_replace('{layer_selector}', $this->_getLayersSelector($map->layers, $map->mapInternalName), $template);
        
        // {route} se useRoutePlaner
        $route = $this->_getRouteDiv();
        if ((strpos($template, '{route}') === false) && isset($map->useRoutePlaner) && $map->useRoutePlaner) {
            $template .= '{route}';
        }
        
        // Selettore di liste
        $idMs = GeofactoryHelper::getArrayIdMs($map->id);
        $selector = "";
        $m_selector = "";
        $toggeler = "";
        $toggeler_map = "";
        $selector_1 = "";
        $m_selector_1 = "";
        $toggeler_1 = "";
        $toggeler_img = "";
        $ullist_img = "";
        $toggeler_img_1 = "";
        if (is_array($idMs) && count($idMs)) {
            $selector .= '<select id="gf_list_selector" onChange="' . $mapVar . '.SLFP();" style="width:100%;"><option selected="selected" value="0">' . Text::_('COM_GEOFACTORY_ALL') . '</option>';
            $m_selector .= '<select id="gf_multi_selector" onChange="' . $mapVar . '.SLFP();" style="width:100%;" multiple="multiple" size="' . count($idMs) . '" >';
            $ullist_img .= '<ul style="list-style-type:none" id="gf_toggeler">';
            $toggeler .= '<div id="gf_toggeler">';
            $toggeler_img .= $toggeler;
            $selector_1 .= '<select id="gf_list_selector" onChange="' . $mapVar . '.SLFP();" style="width:100%;">';
            $m_selector_1 .= '<select id="gf_multi_selector" onChange="' . $mapVar . '.SLFP();" style="width:100%;" multiple="multiple" size="' . count($idMs) . '" >';
            $toggeler_1 .= $toggeler;
            $toggeler_img_1 .= $toggeler;
        
            $first = true;
            $sel_1 = ' selected="selected" ';
            $chk_1 = ' checked="checked" ';
        
            foreach ($idMs as $ms) {
                $list = GeofactoryHelper::getMs($ms);
                if (!$list)
                    continue;
        
                $sel = ' selected="selected" ';
                $chk = ' checked="checked" ';
                if (isset($list->checked_loading) && ((int)$list->checked_loading < 1)) {
                    $sel = '';
                    $chk = '';
                }
        
                $map->fullCss .= (isset($list->cssMs) && strlen($list->cssMs) > 0) ? $list->cssMs : "";
        
                $img = GeofactoryHelper::_getSelectorImage($list);
        
                $selector .= '<option value="' . $list->typeList . '_' . $list->id . '">' . $list->name . '</option>';
                $m_selector .= '<option value="' . $list->typeList . '_' . $list->id . '" selected="selected">' . $list->name . '</option>';
                $toggeler .= '<div id="gf_toggeler_item_' . $list->id . '" style="margin-right:10px;float:left" class="gf_toggeler_item"><input type="checkbox" ' . $chk . ' name="' . $list->typeList . '_' . $list->id . '" onChange="' . $mapVar . '.SLFP();">' . $list->name . '</div>';
        
                $toggeler_map .= '<div class="gfMapControls" id="gf_toggeler_item_' . $list->id . '" style="height:28px!important;margin-top:3px!important;padding:2px!important;float:left;width:100%;"><input type="checkbox" ' . $chk . ' style="margin:0px!important;" name="' . $list->typeList . '_' . $list->id . '" onChange="switchPanel(\'gf_toggeler\', 0); ' . $mapVar . '.SLFP();"> ' . $list->name . '</div>';
        
                $ullist_img .= '<li class="gf_toggeler_item"><label class="checkbox inline">
                                    <input type="checkbox" id="cbType1" ' . $chk . ' name="' . $list->typeList . '_' . $list->id . '" onChange="' . $mapVar . '.SLFP();" />
                                    <img src="' . $img . '">
                                    ' . $list->name . '
                                </label></li>';
        
                $toggeler_img .= '<div class="gf_toggeler_item" style="margin-right:10px;float:left"><input type="checkbox" ' . $chk . ' name="' . $list->typeList . '_' . $list->id . '" onChange="' . $mapVar . '.SLFP();"><img src="' . $img . '"> ' . $list->name . '</div>';
                $toggeler_img_1 .= '<div class="gf_toggeler_item" style="margin-right:10px;float:left"><input type="checkbox" ' . $chk_1 . ' name="' . $list->typeList . '_' . $list->id . '" onChange="' . $mapVar . '.SLFP();"><img src="' . $img . '"> ' . $list->name . '</div>';
        
                $toggeler_1 .= '<div class="gf_toggeler_item" style="margin-right:10px;float:left"><input type="checkbox" ' . $chk_1 . ' name="' . $list->typeList . '_' . $list->id . '" onChange="' . $mapVar . '.SLFP();">' . $list->name . '</div>';
                $selector_1 .= '<option ' . $sel_1 . ' value="' . $list->typeList . '_' . $list->id . '">' . $list->name . '</option>';
                $m_selector_1 .= $selector_1;
        
                if ($first) {
                    $first = false;
                    $sel_1 = '';
                    $chk_1 = '';
                }
            }
            $selector .= '</select>';
            $m_selector .= '</select>';
            $toggeler_img .= '</div>';
            $ullist_img .= '</ul>';
            $toggeler .= '</div>';
            $toggeler_1 .= '</div>';
            $toggeler_img_1 .= '</div>';
            $selector_1 .= '</select>';
            $m_selector_1 .= '</select>';
        }
        
        // Dispatch evento: onBeforeParseMapTemplate
        $evBeforeParse = new Event('onBeforeParseMapTemplate', [
            'template'  => &$template,
            'idMs'      => $idMs,
            'map'       => $map,
            'mapVar'    => $mapVar
        ]);
        $dispatcher->dispatch('onBeforeParseMapTemplate', $evBeforeParse);
        
        // Radius form
        $rad_dist = '';
        $radius_form = '';
        $radius_form_hide = '';
        if ($map->templateAuto != 1) {
            $radius_form = $this->_getRadForm($map, $mapVar, $toggeler_map, is_array($idMs) ? count($idMs) : 0, $rad_dist);
            $radius_form_hide = '<div style="display:none;">' . $radius_form . '</div>';
            if ((strpos($template, '{radius_form}') === false) && (strpos($template, '{radius_form_hide}') === false)) {
                $template .= (isset($map->radFormMode) && $map->radFormMode == 2) ? '{radius_form}' : '{radius_form_hide}';
            }
        } else {
            $gf_mod_search = $app->input->getString('gf_mod_search', null);
            $gf_mod_search = htmlspecialchars(str_replace(['"', '`'], '', $gf_mod_search), ENT_QUOTES, 'UTF-8');
            $rad_dist = $this->_getRadiusDistances($map, $gf_mod_search);
        }
        
        $template = str_replace('{mapvar}', $mapVar, $template);
        $template = str_replace('{rad_distances}', $rad_dist, $template);
        $template = str_replace('{locate_me}', $locate_me, $template);
        $template = str_replace('{near_me}', $near_me, $template);
        $template = str_replace('{save_me_full}', $save_me_full, $template);
        $template = str_replace('{save_me}', $save_me, $template);
        $template = str_replace('{reset_me}', $reset_me, $template);
        $template = str_replace('{reset_map}', $reset_map, $template);
        $template = str_replace('{radius_form}', $radius_form, $template);
        $template = str_replace('{radius_form_hide}', $radius_form_hide, $template);
        $template = str_replace('{route}', $route, $template);
        $template = str_replace('{toggle_selector_icon_1}', $toggeler_img_1, $template);
        $template = str_replace('{toggle_selector_icon}', $toggeler_img, $template);
        $template = str_replace('{toggle_selector}', $toggeler, $template);
        $template = str_replace('{ullist_img}', $ullist_img, $template);
        $template = str_replace('{multi_selector}', $m_selector, $template);
        $template = str_replace('{selector}', $selector, $template);
        $template = str_replace('{toggle_selector_1}', $toggeler_1, $template);
        $template = str_replace('{multi_selector_1}', $m_selector_1, $template);
        $template = str_replace('{selector_1}', $selector_1, $template);
        
        // Debug
        $debug = "";
        if (GeofactoryHelper::isDebugMode()){
            $debug  = '<ul id="gf_debugmode">';
            $debug .= '  <li>Debug mode ON</li>';
            $debug .= '  <li>Map variable name : "' . $mapVar . '"</li>';
            $debug .= '  <li>Data file : <a id="gf_debugmode_xml"></a></li>';
            $debug .= '  <li>Last bubble : <a id="gf_debugmode_bubble"></a></li>';
            $debug .= '</ul>';
        }
        
        // Contenitore
        $template = '<div id="gf_template_container">' . $debug . $template . '</div>';
        
        // Dyncat
        $template = $this->_replaceDynCat($template);
        
        $map->formatedTemplate = $template;
    }
    
    protected function _cleanCss(&$map)
    {
        if (!isset($map->fullCss)) {
            $map->fullCss = '';
        }
        
        $css = ' #' . $map->mapInternalName . ' img{max-width:none!important;}';
        $css .= $map->fullCss;
        $css = str_replace(["\t", "\r\n"], "", $css);
        $css = str_replace(["    ", "   ", "  "], " ", $css);
        $css = str_replace("#", " #", $css);
        $css = str_replace("{ ", "{", $css);
        $css = str_replace("} ", "}", $css);
        $css = str_replace(" {", "{", $css);
        $css = str_replace(" }", "}", $css);
        $map->fullCss = (strlen(trim($css)) > 0) ? trim($css) : "";
    }
    
    protected function _getRadiusDistances($map, $gf_mod_search, $class = true)
    {
        $ses = Factory::getSession();
        $app = Factory::getApplication('site');
        $gf_mod_radius = $app->input->getFloat('gf_mod_radius', $ses->get('mj_rs_ref_dist', 0));
        
        if ($gf_mod_search && (strlen($gf_mod_search) > 0)) {
            $ses->set('gf_ss_search_phrase', $gf_mod_search);
            $ses->set('gf_ss_search_radius', $gf_mod_radius);
        }
        
        if (!isset($map->frontDistSelect)) {
            $map->frontDistSelect = "5,10,25,50,100";
        }
        
        $listVal = explode(',', $map->frontDistSelect);
        $find = false;
        $unit = Text::_('COM_GEOFACTORY_UNIT_KM');
        if (isset($map->fe_rad_unit)) {
            $unit = ($map->fe_rad_unit == 1) ? Text::_('COM_GEOFACTORY_UNIT_MI') : $unit;
            $unit = ($map->fe_rad_unit == 2) ? Text::_('COM_GEOFACTORY_UNIT_NM') : $unit;
        }
        $cls = $class ? 'class="gfMapControls"' : '';
        $radForm = '<select id="radiusSelect" style="width:100px;" ' . $cls . ' onChange="if (mj_radiuscenter){' . $map->mapInternalName . '.SLFI();}">';
        
        if (is_array($listVal)) {
            foreach ($listVal as $val) {
                $sel = ($val == $gf_mod_radius) ? ' selected="selected" ' : '';
                if ($sel != '') {
                    $find = true;
                }
                $radForm .= '<option value="' . $val . '" ' . $sel . '>' . $val . ' ' . $unit . '</option>';
            }
        }
        
        if (!$find && is_numeric($gf_mod_radius) && ($gf_mod_radius > 0)) {
            $radForm .= '<option value="' . $gf_mod_radius . '" selected="selected">' . $gf_mod_radius . '</option>';
        }
        $radForm .= '</select>';
        return $radForm;
    }
    
    protected function _replaceDynCat($text)
    {
        $regex = '/{dyncat\s+(.*?)}/i';
        if (strpos($text, "{dyncat ") === false)
            return $text;
        preg_match_all($regex, $text, $matches);
        $count = count($matches[0]);
        if ($count < 1)
            return $text;
        for ($i = 0; $i < $count; $i++) {
            $code = str_replace("{dyncat ", '', $matches[0][$i]);
            $code = str_replace("}", '', $code);
            $code = trim($code);
            $vCode = explode('#', $code);
            if ((count($vCode) < 1) || (strlen($vCode[1]) < 1))
                continue;
            $ext = $vCode[0];
            $idP = $vCode[1];
            // In questo caso, se vuoi aggiungere l'azione via JS, puoi inserirla nell'array $js
            // oppure gestirla diversamente
        }
        // Se necessario, si potrebbe fare un preg_replace per sostituire tutte le occorrenze.
        return preg_replace($regex, '', $text);
    }
    
    protected function _getRadForm($map, $mapVar, $toggeler_map, $nbrMs, &$dists)
    {
        $app = Factory::getApplication('site');
        $gf_mod_search = $app->input->getString('gf_mod_search', null);
        $gf_mod_search = htmlspecialchars(str_replace(['"', '`'], '', $gf_mod_search), ENT_QUOTES, 'UTF-8');
        $form_start = '<form id="gf_radius_form" onsubmit="' . $mapVar . '.SLFI();return false;">';
        $input = '<input type="text" id="addressInput" value="' . $gf_mod_search . '" class="gfMapControls"/>';
        $dists = $this->_getRadiusDistances($map, $gf_mod_search);
        $search_btn = '<input type="button" onclick="' . $mapVar . '.SLFI();" id="gf_search_rad_btn" value="' . Text::_('COM_GEOFACTORY_SEARCH') . '"/>';
        $divsOnMapGo = '<div id="gf_map_panel" style="padding-top:5px;display:none;"><div id="gf_radius_form" style="margin:0;padding:0;">';
        $radius_form = '';
        
        if (!isset($map->radFormMode)) {
            $map->radFormMode = 0;
        }
        
        if ($map->radFormMode == 0) {
            $radius_form .= $form_start;
            $radius_form .= '<label for="addressInput">' . Text::_('COM_GEOFACTORY_ADDRESS') . '</label>';
            $radius_form .= $input;
            $radius_form .= '<label for="radiusSelect">' . Text::_('COM_GEOFACTORY_RADIUS') . '</label>';
            $radius_form .= $dists;
            $radius_form .= $search_btn;
            $radius_form .= '</form>';
        }
        if ($map->radFormMode == 1) {
            $radius_form .= $form_start;
            $radius_form .= isset($map->radFormSnipet) ? $map->radFormSnipet : '';
            $radius_form = str_replace('[input_center]', $input, $radius_form);
            $radius_form = str_replace('[distance_sel]', $dists, $radius_form);
            $radius_form = str_replace('[search_btn]', $search_btn, $radius_form);
            $radius_form .= '</form>';
        }
        if ($map->radFormMode == 3 && $nbrMs && $nbrMs > 0)
            $map->radFormMode = 2;
        if ($map->radFormMode == 3) {
            $radius_form .= $divsOnMapGo;
            $radius_form .= $input;
            $radius_form .= $dists;
            $radius_form .= '<input type="button" class="gfMapControls" onclick="' . $mapVar . '.SLRES();" id="gf_reset_rad_btn" value="' . Text::_('COM_GEOFACTORY_RESET_MAP') . '"/>';
            $radius_form .= ' <input type="button" class="gfMapControls" value="Filters" onclick="switchPanel(\'gf_toggeler\', 0);" />';
            $radius_form .= '</div>';
            $radius_form .= '<div id="gf_toggeler" style="display:none;">';
            $radius_form .= $toggeler_map;
            $radius_form .= '</div>';
            $radius_form .= '</div>';
        }
        if ($map->radFormMode == 2) {
            $radius_form .= $divsOnMapGo;
            $radius_form .= $input;
            $radius_form .= $dists;
            $radius_form .= '<input type="button" onclick="' . $mapVar . '.SLFI();" id="gf_search_rad_btn" value="" style="display:none" />';
            $radius_form .= '<input type="button" onclick="' . $mapVar . '.SLRES();" id="gf_reset_rad_btn" value="" style="display:none" />';
            $radius_form .= '</div>';
            $radius_form .= '</div>';
        }
        return $radius_form;
    }
    
    protected function _getRouteDiv()
    {
        $route  = '<div id="gf_routecontainer">';
        $route .= '<select id="gf_transport">';
        $route .= '<option value="DRIVING">' . Text::_('COM_GEOFACTORY_DRIVING') . '</option>';
        $route .= '<option value="WALKING">' . Text::_('COM_GEOFACTORY_WALKING') . '</option>';
        $route .= '<option value="BICYCLING">' . Text::_('COM_GEOFACTORY_BICYCLING') . '</option>';
        $route .= '</select>';
        $route .= '<div id="gf_routepanel"></div>';
        $route .= '</div>';
        return $route;
    }
    
    protected function _getLayersSelector($arLayersTmp, $var)
    {
        if (!is_array($arLayersTmp) || !count($arLayersTmp)) {
            return '';
        }
        $arLayers = [];
        foreach ($arLayersTmp as $tmp) {
            if (intval($tmp) > 0) {
                $arLayers[] = $tmp;
            }
        }
        if (!is_array($arLayers) || count($arLayers) < 1) {
            return '';
        }
        $layers = [];
        $layers[] = '<h4>Layers</h4>';
        $layers[] = '<ul style="list-style-type:none" id="gf_layers_selector">';
        if (in_array(1, $arLayers)) {
            $layers[] = ' <li><label class="checkbox inline"><input type="checkbox" id="gf_l_traffic" onclick="' . $var . '.LAYSEL();"> ' . Text::_('COM_GEOFACTORY_TRAFFIC') . '</label></li>';
        }
        if (in_array(2, $arLayers)) {
            $layers[] = ' <li><label class="checkbox inline"><input type="checkbox" id="gf_l_transit" onclick="' . $var . '.LAYSEL();"> ' . Text::_('COM_GEOFACTORY_TRANSIT') . '</label></li>';
        }
        if (in_array(3, $arLayers)) {
            $layers[] = ' <li><label class="checkbox inline"><input type="checkbox" id="gf_l_bicycle" onclick="' . $var . '.LAYSEL();"> ' . Text::_('COM_GEOFACTORY_BICYCLE') . '</label></li>';
        }
        $layers[] = '</ul>';
        return implode('', $layers);
    }
    
    protected function _getSidePanel($map)
    {
        $app = Factory::getApplication('site');
        $gf_mod_search = $app->input->getString('gf_mod_search', null);
        $gf_mod_search = htmlspecialchars(str_replace(['"', '`'], '', $gf_mod_search), ENT_QUOTES, 'UTF-8');
        $route = isset($map->useRoutePlaner) && $map->useRoutePlaner
            ? '<br /><div class="alert alert-info" id="route_box"><h4>' . Text::_('COM_GEOFACTORY_MARKER_TO_REACH') . '</h4>{route}</div>'
            : '';
        $selector = '{ullist_img}';
        if (isset($map->niveaux) && ($map->niveaux == 1)) {
            $selector = '{level_icon_simple_check}';
        }
        return '
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-4" id="gf_sideTemplateCont">
                        <div id="gf_sideTemplateCtrl">
                            <div class="well">
                                <div id="gf_btn_superfull" style="display:none;" onclick="superFull(' . $map->mapInternalName . '.map);return false;">
                                    <a id="reset" href="#"><i class="glyphicon glyphicon-chevron-right"></i> ' . Text::_('COM_GEOFACTORY_REDUCE') . '</a>
                                </div>
                                <h4>' . Text::_('COM_GEOFACTORY_ADDRESS_SEARCH_NEAR') . ' <small>(<a id="find_me" href="#" onClick="' . $map->mapInternalName . '.LMBTN();">' . Text::_('COM_GEOFACTORY_ADDRESS_FIND_ME') . '</a>)</small></h4>
                                <p>
                                    <input type="text" id="addressInput" value="' . $gf_mod_search . '" class="gfMapControls" placeholder="' . Text::_('COM_GEOFACTORY_ENTER_ADDRESS_OR') . '" />
                                </p>
                                <p>
                                    <label>' . Text::_('COM_GEOFACTORY_WITHIN') . ' {rad_distances}</label>
                                </p>
                                <h4>' . Text::_('COM_GEOFACTORY_CATEGORIES_ON_MAP') . '</h4>
                                ' . $selector . '
                                <br />
                                <a class="btn btn-primary" id="search" href="#" onclick="' . $map->mapInternalName . '.SLFI();">
                                    <i class="glyphicon glyphicon-search"></i> ' . Text::_('COM_GEOFACTORY_SEARCH') . '
                                </a>
                                <a class="btn btn-default" id="reset" href="#" onclick="' . $map->mapInternalName . '.SLRES();">
                                    <i class="glyphicon glyphicon-repeat"></i> ' . Text::_('COM_GEOFACTORY_RESET_MAP') . '
                                </a>
                                {layer_selector}
                            </div>
                            <div class="alert alert-info" id="result_box"><h4>' . Text::_('COM_GEOFACTORY_RESULTS') . ' {number}</h4>{sidelists}</div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <noscript>
                            <div class="alert alert-info">
                                <h4>Your JavaScript is disabled</h4>
                                <p>Please enable JavaScript to view the map.</p>
                            </div>
                        </noscript>
                        {map}
                        ' . $route . '
                    </div>
                </div>
            </div>
            <div id="gf_panelback" style="cursor:pointer;float:right;display:none;position:fixed;width:20px;height:100%;top:0;right:0;z-index:100; background-color:silver!important; background: url(' . Uri::root() . 'media/com_geofactory/assets/arrow-left.png) no-repeat center" onclick="normalFull(' . $map->mapInternalName . '.map);"><div></div></div>
        ';
    }
    
    public static function _setKml($jsVarName, &$js, $kml_file)
    {
        $vKml = explode(';', $kml_file);
        if (!is_array($vKml) || count($vKml) < 1)
            return;
        foreach ($vKml as $kml) {
            $kml = trim($kml);
            if (strlen($kml) < 3)
                continue;
            $js[] = $jsVarName . ".addKmlLayer('{$kml}');";
        }
    }
    
    public static function _loadTiles($jsVarName, &$js, $map)
    {
        $vTypes = [];
        $vAvailableTypes = isset($map->mapTypeAvailable) ? $map->mapTypeAvailable : null;
        $ref = ["SATELLITE", "HYBRID", "TERRAIN", "ROADMAP"];

        if (!is_array($vAvailableTypes) || count($vAvailableTypes) == 0) {
            $vAvailableTypes = $ref;
        }

        foreach ($vAvailableTypes as $baseType) {
            if (in_array($baseType, $ref)) {
                $vTypes[] = "google.maps.MapTypeId.{$baseType}";
            }
        }

        $listTileTmp = explode(";", $map->tiles);
        $listTile = [];

        if (is_array($listTileTmp) && count($listTileTmp) > 0) {
            foreach ($listTileTmp as $ltmp) {
                if (strlen(trim($ltmp)) < 3) {
                    continue;
                }
                $listTile[] = $ltmp;
            }
        }

        if (is_array($listTile) && count($listTile) > 0) {
            $idx = 0;
            foreach ($listTile as $tile) {
                $tile = explode('|', $tile);
                $url  = (count($tile) > 0) ? trim($tile[0]) : "";
                $name = (count($tile) > 1) ? trim($tile[1]) : "Name ?";
                $maxZ = (count($tile) > 2) ? trim($tile[2]) : "18";
                $alt  = (count($tile) > 3) ? trim($tile[3]) : "";
                $isPng= (count($tile) > 4) ? trim($tile[4]) : "true";
                $size = (count($tile) > 5) ? trim($tile[5]) : "256";

                if (!in_array($name, $vAvailableTypes)) {
                    continue;
                }
                if (strlen($url) < 1) {
                    continue;
                }

                $idx++;
                $vTypes[] = "'{$name}'";

                $bing = false;
                $jarry = false;
                if ($url == "http://bing.com/aerial") { 
                    $url = "http://ecn.t3.tiles.virtualearth.net/tiles/a";
                    $bing = true; 
                }
                if ($url == "http://bing.com/label") { 
                    $url = "http://ecn.t3.tiles.virtualearth.net/tiles/h";
                    $bing = true; 
                }
                if ($url == "http://bing.com/road") { 
                    $url = "http://ecn.t3.tiles.virtualearth.net/tiles/r";
                    $bing = true; 
                }
                if ($url == "http://jarrypro.com") { 
                    $jarry = true; 
                }

                $url = str_replace("#X#", "' + X + '", $url);
                $url = str_replace("#Y#", "' + ll.y + '", $url);
                $url = str_replace("#Z#", "' + z + '", $url);

                $js[] = "var otTile = new clsTile('{$name}', {$size}, {$isPng}, {$maxZ}, '{$alt}');";
                if ($bing) {
                    $js[] = "otTile.fct = function(ll, z){ return otTile.getBingUrl('{$url}', ll, z);};";
                } elseif ($jarry) {
                    $js[] = "otTile.fct = function(ll, z){ var ymax = 1 << z; var y = ymax - ll.y - 1; return 'http://jarrypro.com/images/gmap_tiles/' + z + '/' + ll.x + '/' + y + '.jpg';};";
                } else {
                    $js[] = "otTile.fct = function(ll, z){ var X = ll.x % (1 << z); return '{$url}';};";
                }
                $js[] = "otTile.createTile({$jsVarName}.map);";
            }
        }

        if (count($vTypes) > 0) {
            $js[] = "var optionsUpdate = {mapTypeControlOptions: {mapTypeIds: [".implode(',', $vTypes)."], style: google.maps.MapTypeControlStyle.{$map->mapTypeBar}}}; {$jsVarName}.map.setOptions(optionsUpdate);";
            if (in_array($map->mapTypeOnStart, ["ROADMAP", "SATELLITE", "HYBRID", "TERRAIN"])) {
                $js[] = $jsVarName . ".map.setMapTypeId(google.maps.MapTypeId.{$map->mapTypeOnStart});";
            } else {
                $js[] = $jsVarName . ".map.setMapTypeId('{$map->mapTypeOnStart}');";
            }
        }
        return;
    }
    
    public static function _loadDynCatsFromTmpl($jsVarName, &$js, $map)
    {
        $regex = '/{dyncat\s+(.*?)}/i';
        $text = $map->template;
        if (strpos($text, "{dyncat ") === false) {
            return;
        }
        preg_match_all($regex, $text, $matches);
        $count = is_array($matches[0]) ? count($matches[0]) : 0;
        if ($count < 1) {
            return;
        }
        for ($i = 0; $i < $count; $i++) {
            $code = str_replace("{dyncat ", '', $matches[0][$i]);
            $code = str_replace("}", '', $code);
            $code = trim($code);
            $vCode = explode('#', $code);
            if ((count($vCode) < 1) || (strlen($vCode[1]) < 1))
                continue;
            $js[] = "{$jsVarName}.loadDynCat('{$vCode[0]}', {$vCode[1]}, 'gf_dyncat_{$vCode[0]}_{$vCode[1]}', '{$jsVarName}');";
        }
    }
}
