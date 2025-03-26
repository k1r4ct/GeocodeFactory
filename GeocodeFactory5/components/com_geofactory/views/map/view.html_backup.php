<?php
/**
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Cédric Pelloquin aka Rick <info@myJoom.com>
 * @website     www.myJoom.com
 * @update      Daniele Bellante
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Helper\TagsHelper;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Document\Document;
use Joomla\CMS\Session\Session;

// Carica il helper di Geocode Factory
require_once JPATH_COMPONENT . '/helpers/geofactory.php';

/**
 * View per la mappa di Geocode Factory
 *
 * @since  1.0
 */
class GeofactoryViewMap extends HtmlView
{
    /**
     * L'oggetto mappa corrente
     *
     * @var    object|null
     * @since  1.0
     */
    protected ?object $item = null;

    /**
     * Parametri
     *
     * @var    object|null
     * @since  1.0
     */
    protected ?object $params = null;

    /**
     * Stato
     *
     * @var    object|null
     * @since  1.0
     */
    protected ?object $state = null;

    /**
     * Utente corrente
     *
     * @var    object|null
     * @since  1.0
     */
    protected ?object $user = null;

    /**
     * Suffisso classe pagina
     *
     * @var    string
     * @since  1.0
     */
    protected string $pageclass_sfx = '';

    /**
     * Inizializza la vista con i dati della mappa
     *
     * @param   object  $map  Oggetto mappa
     * 
     * @return  void
     * @since   1.0
     */
    public function initView(object $map): void
    {
        // Recupera l'app e l'utente
        $app  = Factory::getApplication();
        $user = Factory::getUser();

        // Assegna i dati recuperati
        $this->item   = $map;
        $this->state  = $this->get('State');
        $this->user   = $user;
        $this->params = $app->getParams();

        // In Joomla 4, $this->document dovrebbe essere già valorizzato
        if (!isset($this->document) || !$this->document) {
            $this->document = Factory::getDocument();
        }
    }

    /**
     * Visualizza la vista
     *
     * @param   string  $tpl  Nome del template da usare
     * 
     * @return  mixed
     * @since   1.0
     */
    public function display($tpl = null)
    {
        // Ricava l'oggetto "Item" dal Model, poi chiama la initView
        $this->initView($this->get('Item'));

        // Controlla eventuali errori
        $errors = $this->get('Errors');
        
        // Verifica che $errors sia un array prima di chiamare count()
        if (is_array($errors) && count($errors)) {
            Factory::getApplication()->enqueueMessage(implode("\n", $errors), 'warning');
            return false;
        }

        // Crea un alias più comodo
        $item = $this->item;

        // Usa FileLayout per i tag (adatto a Joomla 4)
        $item->tagLayout = new FileLayout('joomla.content.tags');

        // Costruisci lo slug
        $item->slug = $item->alias ? ($item->id . ':' . $item->alias) : $item->id;

        // Gestione Tag tramite TagsHelper (Joomla 4)
        $tagHelper = new TagsHelper;
        $item->tags = $tagHelper->getItemTags('com_geofactory.map', $this->item->id);

        // Escape strings
        $this->pageclass_sfx = htmlspecialchars($this->params->get('pageclass_sfx', ''), ENT_QUOTES, 'UTF-8');

        // Prepara il documento (metadati, js, css, ecc.)
        $this->prepareDocument();

        // Ora lascia che la vista venga caricata con layout standard
        return parent::display($tpl);
    }

    /**
     * Prepara il documento aggiungendo Script, CSS, Title, Meta, ecc.
     *
     * @return  void
     * @since   1.0
     */
    protected function prepareDocument(): void
    {
        // Preleva le info principali
        $app     = Factory::getApplication();
        $session = Factory::getSession();
        $config  = ComponentHelper::getParams('com_geofactory');
        $root    = Uri::root();

        // Ottieni il WebAssetManager
        $wa = $this->document->getWebAssetManager();

        // Carica Bootstrap Icons (moderno sostituto di Glyphicons)
        $wa->registerAndUseStyle('bootstrap-icons', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css');

        // Leggi eventuali parametri dalla richiesta
        $adre = $app->input->getString('gf_mod_search', '');
        $adre = htmlspecialchars(str_replace(['"', '`'], '', $adre), ENT_QUOTES, 'UTF-8');
        $dist = $app->input->getFloat('gf_mod_radius', 1);

        // Costruisci parametri url
        $urlParams = [
            'idmap=' . $this->item->id,
            'mn=' . $this->item->mapInternalName,
            'zf=' . $this->item->forceZoom,
            'gfcc=' . $this->item->gf_curCat,
            'zmid=' . $this->item->gf_zoomMeId,
            'tmty=' . $this->item->gf_zoomMeType,
            'code=' . random_int(1, 100000)
        ];

        $dataMap = implode('&', $urlParams);

        // Gestione di jQuery e Bootstrap in Joomla 4
        $jsBootStrap  = (int)$config->get('jsBootStrap', 0);
        $cssBootStrap = (int)$config->get('cssBootStrap', 0);
        $jqMode       = (int)$config->get('jqMode', 1);
        $jqVersion    = $config->get('jqVersion') ? $config->get('jqVersion') : '3.6.4';
        $jqUiversion  = $config->get('jqUiversion') ? $config->get('jqUiversion') : '1.13.2';
        $jqUiTheme    = $config->get('jqUiTheme') ? $config->get('jqUiTheme') : 'base';

        // Verifica se il sito è SSL
        $http = $config->get('sslSite', '');
        if (empty($http)) {
            $http = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        }

        // Gestione tabs
        $jqui = !empty($this->item->useTabs);
        if (!empty($this->item->useTabs) && ($jqMode == 0 || $jqMode == 2)) {
            $jqMode = 1;
        }

        // Caricamento jQuery in base al "jqMode"
        switch ($jqMode) {
            case 0: // nessun caricamento jQuery
                break;
            case 2: // Joomla
                $wa->useScript('jquery');
                if ($jqui) {
                    $wa->useScript('jquery-ui');
                }
                break;
            case 3: // CDN Google
                $wa->registerAndUseScript(
                    'jquery-cdn',
                    $http . 'ajax.googleapis.com/ajax/libs/jquery/' . $jqVersion . '/jquery.min.js',
                    ['attributes' => ['defer' => true]]
                );
                if ($jqui) {
                    $wa->registerAndUseScript(
                        'jquery-ui-cdn',
                        $http . 'ajax.googleapis.com/ajax/libs/jqueryui/' . $jqUiversion . '/jquery-ui.min.js',
                        ['attributes' => ['defer' => true]]
                    );
                    $wa->registerAndUseStyle(
                        'jquery-ui-theme',
                        $http . 'ajax.googleapis.com/ajax/libs/jqueryui/' . $jqUiversion . '/themes/' . $jqUiTheme . '/jquery-ui.css'
                    );
                }
                break;
            case 4: // code.jquery.com
                $wa->registerAndUseScript(
                    'jquery-code',
                    $http . 'code.jquery.com/jquery-' . $jqVersion . '.min.js',
                    ['attributes' => ['defer' => true]]
                );
                if ($jqui) {
                    $wa->registerAndUseScript(
                        'jquery-ui-code',
                        $http . 'code.jquery.com/ui/' . $jqUiversion . '/jquery-ui.min.js',
                        ['attributes' => ['defer' => true]]
                    );
                    $wa->registerAndUseStyle(
                        'jquery-ui-theme-code',
                        $http . 'code.jquery.com/ui/' . $jqUiversion . '/themes/' . $jqUiTheme . '/jquery-ui.css'
                    );
                }
                break;
            default:
            case 1: // Local
                $wa->registerAndUseScript(
                    'jquery-local',
                    $root . 'components/com_geofactory/assets/js/jquery/' . $jqVersion . '/jquery.min.js',
                    ['attributes' => ['defer' => true]]
                );
                if ($jqui) {
                    $wa->registerAndUseScript(
                        'jquery-ui-local',
                        $root . 'components/com_geofactory/assets/js/jqueryui/' . $jqUiversion . '/jquery-ui.min.js',
                        ['attributes' => ['defer' => true]]
                    );
                    $wa->registerAndUseStyle(
                        'jquery-ui-theme-local',
                        $root . 'components/com_geofactory/assets/js/jqueryui/' . $jqUiversion . '/themes/' . $jqUiTheme . '/jquery-ui.css'
                    );
                }
                break;
        }

        // Caricamento Bootstrap 5
        if ($jsBootStrap == 1 || $cssBootStrap == 1) {
            if ($cssBootStrap == 1) {
                $wa->registerAndUseStyle(
                    'bootstrap-css',
                    $http . 'cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css'
                );
            }
            if ($jsBootStrap == 1) {
                $wa->registerAndUseScript(
                    'bootstrap-js',
                    $http . 'cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
                    ['attributes' => ['defer' => true]]
                );
            }
        }

        // Istanza JavaScript principale
        $jsVarName = $this->item->mapInternalName;
        $js = [];

        if (!GeofactoryHelper::useNewMethod($this->item)) {
            $js[] = "var {$jsVarName} = new clsGfMap();";
            $js[] = "var gf_sr = '{$root}';";
            $js[] = "function init_{$jsVarName}(){ sleepMulti(repos);";
            $js[] = "  jQuery.getJSON({$jsVarName}.getMapUrl('{$dataMap}'), function(data){";
            $js[] = "    if (!{$jsVarName}.checkMapData(data)) {";
            $js[] = "      document.getElementById('{$jsVarName}').innerHTML = 'Map error.';";
            $js[] = "      console.log('Bad map format in init_{$jsVarName}()');";
            $js[] = "      return;";
            $js[] = "    }";
            $js[] = "    {$jsVarName}.setMapInfo(data, '{$jsVarName}');";

            // Caricamenti dinamici (kml, tiles, layers, ecc.)
            GeofactoryModelMap::_loadDynCatsFromTmpl($jsVarName, $js, $this->item);
            GeofactoryModelMap::_setKml($jsVarName, $js, $this->item->kml_file);
            $this->setLayers($jsVarName, $js);
            $this->getSourceUrl($jsVarName, $js, $root);
            GeofactoryModelMap::_loadTiles($jsVarName, $js, $this->item);

            // Ricerca / caricamento marker
            $gf_ss_search_phrase = $session->get('gf_ss_search_phrase', null);
            if ($gf_ss_search_phrase && strlen($gf_ss_search_phrase) > 0) {
                $js[] = "    {$jsVarName}.searchLocationsFromInput();";
                $session->clear('gf_ss_search_phrase');
            } elseif (!empty($this->item->useBrowserRadLoad)) {
                $js[] = "    {$jsVarName}.getBrowserPos(false, true);";
            } else {
                $js[] = "    {$jsVarName}.searchLocationsFromPoint(null);";
            }

            $js[] = "  });"; // Fine getJSON
            $js[] = "}";     // Fine init_{$jsVarName}

            // Variabili globali da modulo (aggiunta di apici per la stringa)
            $js[] = "var gf_mod_search = '{$adre}';";
            $js[] = "var gf_mod_radius = {$dist};";

            // Carica la mappa al load
            $js[] = "google.maps.event.addDomListener(window, 'load', init_{$jsVarName});";

            // Se in debug
            $sep = GeofactoryHelper::isDebugMode() ? "\n" : " ";
            $wa->addInlineScript(implode($sep, $js));
        }

        // Google API Key
        $ggApikey = strlen($config->get('ggApikey', '')) > 3 ? "&key=" . $config->get('ggApikey') : "";

        // Verifica se servono librerie "weather"
        $arLayers = [];
        if (isset($this->item->layers) && is_array($this->item->layers)) {
            foreach ($this->item->layers as $tmp) {
                if (intval($tmp) > 0) {
                    $arLayers[] = $tmp;
                }
            }
        }
        $lib = ((count($arLayers) > 0) && (in_array(4, $arLayers) || in_array(5, $arLayers) || in_array(6, $arLayers))) ? ",weather" : "";

        // Se ci sono "layers" o "radFormMode"
        if ((count($arLayers) > 0) || (!empty($this->item->radFormMode) && $this->item->radFormMode > 1)) {
            $wa->registerAndUseStyle('geofactory-maps-btn', 'components/com_geofactory/assets/css/geofactory-maps_btn.css');
        }

        // Full CSS custom
        if (!empty($this->item->fullCss)) {
            $wa->addInlineStyle($this->item->fullCss);
        }

        // Caricamento script Google Maps
        $mapLang = (strlen($config->get('mapLang', '')) > 1) ? '&language=' . $config->get('mapLang') : '';
        $wa->registerAndUseScript(
            'googlemaps-api', 
            $http . 'maps.googleapis.com/maps/api/js?libraries=places' . $ggApikey . $mapLang . $lib,
            ['attributes' => ['defer' => true]]
        );

        // Se presente un file custom
        if (file_exists(JPATH_BASE . '/components/com_geofactory/assets/js/custom.js')) {
            $wa->registerAndUseScript(
                'geofactory-custom', 
                $root . 'components/com_geofactory/assets/js/custom.js',
                ['attributes' => ['defer' => true]]
            );
        }

        // Cluster
        if (!empty($this->item->useCluster) && $this->item->useCluster == 1) {
            $wa->registerAndUseScript(
                'geofactory-markerclusterer', 
                $root . 'components/com_geofactory/assets/js/markerclusterer-5151023.js',
                ['attributes' => ['defer' => true]]
            );
        }

        if (!GeofactoryHelper::useNewMethod($this->item)) {
            $wa->registerAndUseScript(
                'geofactory-map-api', 
                $root . 'components/com_geofactory/assets/js/map_api-5151020.js',
                ['attributes' => ['defer' => true]]
            );
        } else {
            // Gestione "nuovo" con script generato in modo differente
            if (empty($dist)) {
                $dist = $app->input->getFloat('mj_rs_radius_selector', 1);
            }
            $wa->registerAndUseScript(
                'geofactory-map-js', 
                'index.php?option=com_geofactory&task=map.getJs&' . implode('&', $urlParams),
                ['attributes' => ['defer' => true]]
            );
        }

        // Impostazioni del titolo e meta (classico Joomla)
        $menus = $app->getMenu();
        $menu  = $menus->getActive();
        $title = null;

        if ($menu) {
            $this->params->def('page_heading', $this->params->get('page_title', $menu->title));
        } else {
            $this->params->def('page_heading', $this->item->name);
        }

        $title = $this->params->get('page_title', '');
        if (empty($title)) {
            $title = $app->get('sitename');
        } elseif ($app->get('sitename_pagetitles', 0) == 1) {
            $title = Text::sprintf('JPAGETITLE', $app->get('sitename'), $title);
        } elseif ($app->get('sitename_pagetitles', 0) == 2) {
            $title = Text::sprintf('JPAGETITLE', $title, $app->get('sitename'));
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

    /**
     * Genera l'URL del file sorgente XML/JSON
     *
     * @param   string  $oMap  Nome variabile mappa
     * @param   array   &$js   Array JavaScript
     * @param   string  $root  URL root sito
     * 
     * @return  void
     * @since   1.0
     */
    protected function getSourceUrl(string $oMap, array &$js, string $root): void
    {
        $config = ComponentHelper::getParams('com_geofactory');
        $idmap  = $this->item->id;

        $app    = Factory::getApplication();
        $itemid = $app->input->getInt('Itemid', 0);
        $lang   = $app->input->get('lang', '', 'word');
        
        $langParam = '';
        if (strlen($lang) > 1) {
            $langParam = "&lang={$lang}";
        }

        $paramsUrl = "&gfcc={$this->item->gf_curCat}&zmid={$this->item->gf_zoomMeId}&tmty={$this->item->gf_zoomMeType}&code=" . random_int(1, 100000) . $langParam;

        // Gestione cache
        $useCache = 0;
        if (!empty($this->item->cacheTime) && $this->item->cacheTime > 0) {
            $cache_file_serverpath = GeofactoryHelper::getCacheFileName($idmap, $itemid, 1);
            $filemtime = @filemtime($cache_file_serverpath);
            if (!$filemtime || (time() - $filemtime >= $this->item->cacheTime)) {
                $useCache = 0;
            } else {
                $useCache = 1;
            }
        }

        // Debug
        $debugCont = GeofactoryHelper::isDebugMode() ? "gf_debugmode_xml" : "null";
        $js[] = "{$oMap}.nameDebugCont='{$debugCont}';";
        $js[] = "{$oMap}.setXmlFile('{$paramsUrl}', {$useCache}, {$idmap}, {$itemid});";
    }

    /**
     * Imposta i layers (traffico, meteo, ecc.)
     *
     * @param   string  $oMap  Nome variabile mappa
     * @param   array   &$js   Array JavaScript
     * 
     * @return  void
     * @since   1.0
     */
    protected function setLayers(string $oMap, array &$js): void
    {
        if (empty($this->item->layers) || !is_array($this->item->layers) || !count($this->item->layers)) {
            return;
        }

        // Filtra i layers validi
        $arLayers = [];
        foreach ($this->item->layers as $tmp) {
            if (intval($tmp) > 0) {
                $arLayers[] = $tmp;
            }
        }

        if (count($arLayers) > 0) {
            $txt = [
                Text::_('COM_GEOFACTORY_TRAFFIC'),
                Text::_('COM_GEOFACTORY_TRANSIT'),
                Text::_('COM_GEOFACTORY_BICYCLE'),
                Text::_('COM_GEOFACTORY_WEATHER'),
                Text::_('COM_GEOFACTORY_CLOUDS'),
                Text::_('COM_GEOFACTORY_HIDE_ALL'),
                Text::_('COM_GEOFACTORY_MORE_BTN'),
                Text::_('COM_GEOFACTORY_MORE_BTN_HLP')
            ];

            $js[] = 'var layb = []; var sep = new separator();';

            if (in_array(1, $arLayers)) {
                $js[] = " layb.push( new checkBox({gmap: {$oMap}.map, title: '{$txt[0]}', id: 'traffic', label: '{$txt[0]}'}) ); ";
            }
            if (in_array(2, $arLayers)) {
                $js[] = " layb.push( new checkBox({gmap: {$oMap}.map, title: '{$txt[1]}', id: 'transit', label: '{$txt[1]}'}) ); ";
            }
            if (in_array(3, $arLayers)) {
                $js[] = " layb.push( new checkBox({gmap: {$oMap}.map, title: '{$txt[2]}', id: 'biking', label: '{$txt[2]}'}) ); ";
            }
            if (in_array(4, $arLayers)) {
                $js[] = " layb.push( new checkBox({gmap: {$oMap}.map, title: '{$txt[3]}', id: 'weatherF', label: '{$txt[3]}'}) ); ";
            }
            if (in_array(5, $arLayers)) {
                $js[] = " layb.push( new checkBox({gmap: {$oMap}.map, title: '{$txt[3]}', id: 'weatherC', label: '{$txt[3]}'}) ); ";
            }
            if (in_array(6, $arLayers)) {
                $js[] = " layb.push( new checkBox({gmap: {$oMap}.map, title: '{$txt[4]}', id: 'cloud', label: '{$txt[4]}'}) ); ";
            }

            $js[] = ' layb.push( sep );';
            $js[] = " layb.push( new optionDiv({gmap: {$oMap}.map, name: '{$txt[5]}', title: '{$txt[5]}', id: 'mapOpt'}) );";
            $js[] = ' var ddDivOptions = {items: layb, id: "myddOptsDiv"};';
            $js[] = ' var dropDownDiv = new dropDownOptionsDiv(ddDivOptions);';
            $js[] = " var dropDownOptions = {gmap: {$oMap}.map, name: '{$txt[6]}', id: 'ddControl', title: '{$txt[7]}', position: google.maps.ControlPosition.TOP_RIGHT, dropDown: dropDownDiv};";
            $js[] = ' var dropDown1 = new dropDownControl(dropDownOptions);';
        }
    }
    
    /**
     * Genera il selettore dei livelli (layers)
     *
     * @param   mixed   $arLayersTmp  Array di livelli
     * @param   string  $var          Nome variabile
     * 
     * @return  string
     * @since   1.0
     */
    protected function getLayersSelector($arLayersTmp, string $var): string
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
        $layers[] = '<ul class="list-unstyled" id="gf_layers_selector">'; // Aggiornato per Bootstrap 5
        
        if (in_array(1, $arLayers)) {
            $layers[] = ' <li><div class="form-check"><input class="form-check-input" type="checkbox" id="gf_l_traffic" onclick="' . $var . '.LAYSEL();"><label class="form-check-label" for="gf_l_traffic">' . Text::_('COM_GEOFACTORY_TRAFFIC') . '</label></div></li>';
        }
        if (in_array(2, $arLayers)) {
            $layers[] = ' <li><div class="form-check"><input class="form-check-input" type="checkbox" id="gf_l_transit" onclick="' . $var . '.LAYSEL();"><label class="form-check-label" for="gf_l_transit">' . Text::_('COM_GEOFACTORY_TRANSIT') . '</label></div></li>';
        }
        if (in_array(3, $arLayers)) {
            $layers[] = ' <li><div class="form-check"><input class="form-check-input" type="checkbox" id="gf_l_bicycle" onclick="' . $var . '.LAYSEL();"><label class="form-check-label" for="gf_l_bicycle">' . Text::_('COM_GEOFACTORY_BICYCLE') . '</label></div></li>';
        }
        
        $layers[] = '</ul>';
        return implode('', $layers);
    }
    
    /**
     * Genera il pannello laterale della mappa
     *
     * @param   object  $map  Oggetto mappa
     * 
     * @return  string
     * @since   1.0
     */
    protected function getSidePanel(object $map): string
    {
        $app = Factory::getApplication('site');
        $gf_mod_search = $app->input->getString('gf_mod_search', null);
        $gf_mod_search = htmlspecialchars(str_replace(['"', '`'], '', $gf_mod_search), ENT_QUOTES, 'UTF-8');
        
        $route = (!empty($map->useRoutePlaner) && $map->useRoutePlaner)
            ? '<br /><div class="alert alert-info" id="route_box"><h4>' . Text::_('COM_GEOFACTORY_MARKER_TO_REACH') . '</h4>{route}</div>'
            : '';
            
        $selector = '{ullist_img}';
        if (!empty($map->niveaux) && $map->niveaux == 1) {
            $selector = '{level_icon_simple_check}';
        }
        
        return '
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-4" id="gf_sideTemplateCont">
                        <div id="gf_sideTemplateCtrl">
                            <div class="card p-3">
                                <div id="gf_btn_superfull" style="display:none;" onclick="superFull(' . $map->mapInternalName . '.map);return false;">
                                    <a id="reset" href="#"><i class="bi bi-chevron-right"></i> ' . Text::_('COM_GEOFACTORY_REDUCE') . '</a>
                                </div>
                                <h4>' . Text::_('COM_GEOFACTORY_ADDRESS_SEARCH_NEAR') . ' <small>(<a id="find_me" href="#" onClick="' . $map->mapInternalName . '.LMBTN();">' . Text::_('COM_GEOFACTORY_ADDRESS_FIND_ME') . '</a>)</small></h4>
                                <div class="mb-3">
                                    <input type="text" id="addressInput" value="' . $gf_mod_search . '" class="form-control gfMapControls" placeholder="' . Text::_('COM_GEOFACTORY_ENTER_ADDRESS_OR') . '" />
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">' . Text::_('COM_GEOFACTORY_WITHIN') . ' {rad_distances}</label>
                                </div>
                                <h4>' . Text::_('COM_GEOFACTORY_CATEGORIES_ON_MAP') . '</h4>
                                ' . $selector . '
                                <div class="d-grid gap-2 d-md-flex mt-3">
                                    <button class="btn btn-primary me-md-2" type="button" id="search" onclick="' . $map->mapInternalName . '.SLFI();">
                                        <i class="bi bi-search"></i> ' . Text::_('COM_GEOFACTORY_SEARCH') . '
                                    </button>
                                    <button class="btn btn-secondary" type="button" id="reset" onclick="' . $map->mapInternalName . '.SLRES();">
                                        <i class="bi bi-arrow-repeat"></i> ' . Text::_('COM_GEOFACTORY_RESET_MAP') . '
                                    </button>
                                </div>
                                {layer_selector}
                            </div>
                            <div class="alert alert-info mt-3" id="result_box"><h4>' . Text::_('COM_GEOFACTORY_RESULTS') . ' {number}</h4>{sidelists}</div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <noscript>
                            <div class="alert alert-info">
                                <h4>' . Text::_('JGLOBAL_WARNJAVASCRIPT') . '</h4>
                                <p>' . Text::_('JGLOBAL_WARNJAVASCRIPT_SUPPORT_ENABLED') . '</p>
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
    
    /**
     * Genera il div per la pianificazione del percorso
     *
     * @return  string
     * @since   1.0
     */
    protected function getRouteDiv(): string
    {
        $route  = '<div id="gf_routecontainer">';
        $route .= '<select id="gf_transport" class="form-select mb-3">';
        $route .= '<option value="DRIVING">' . Text::_('COM_GEOFACTORY_DRIVING') . '</option>';
        $route .= '<option value="WALKING">' . Text::_('COM_GEOFACTORY_WALKING') . '</option>';
        $route .= '<option value="BICYCLING">' . Text::_('COM_GEOFACTORY_BICYCLING') . '</option>';
        $route .= '</select>';
        $route .= '<div id="gf_routepanel"></div>';
        $route .= '</div>';
        return $route;
    }
    
    /**
     * Genera il form del radius per la ricerca
     *
     * @param   object  $map           Oggetto mappa
     * @param   string  $mapVar        Nome variabile mappa
     * @param   string  $toggeler_map  HTML del toggle
     * @param   int     $nbrMs         Numero di marker set
     * @param   string  &$dists        Output distanze
     * 
     * @return  string
     * @since   1.0
     */
    protected function getRadForm(object $map, string $mapVar, string $toggeler_map, int $nbrMs, string &$dists): string
    {
        $app = Factory::getApplication('site');
        $gf_mod_search = $app->input->getString('gf_mod_search', null);
        $gf_mod_search = htmlspecialchars(str_replace(['"', '`'], '', $gf_mod_search), ENT_QUOTES, 'UTF-8');
        
        $form_start = '<form id="gf_radius_form" onsubmit="' . $mapVar . '.SLFI();return false;">';
        $input = '<input type="text" id="addressInput" value="' . $gf_mod_search . '" class="form-control gfMapControls"/>';
        $dists = $this->getRadiusDistances($map, $gf_mod_search);
        $search_btn = '<button type="button" onclick="' . $mapVar . '.SLFI();" id="gf_search_rad_btn" class="btn btn-primary">' . Text::_('COM_GEOFACTORY_SEARCH') . '</button>';
        $divsOnMapGo = '<div id="gf_map_panel" style="padding-top:5px;display:none;"><div id="gf_radius_form" style="margin:0;padding:0;">';
        $radius_form = '';
        
        $radFormMode = isset($map->radFormMode) ? $map->radFormMode : 0;
        
        if ($radFormMode == 0) {
            $radius_form .= $form_start;
            $radius_form .= '<div class="mb-3">';
            $radius_form .= '<label for="addressInput" class="form-label">' . Text::_('COM_GEOFACTORY_ADDRESS') . '</label>';
            $radius_form .= $input;
            $radius_form .= '</div>';
            $radius_form .= '<div class="mb-3">';
            $radius_form .= '<label for="radiusSelect" class="form-label">' . Text::_('COM_GEOFACTORY_RADIUS') . '</label>';
            $radius_form .= $dists;
            $radius_form .= '</div>';
            $radius_form .= '<div class="mb-3">';
            $radius_form .= $search_btn;
            $radius_form .= '</div>';
            $radius_form .= '</form>';
        } elseif ($radFormMode == 1) {
            $radius_form .= $form_start;
            $radius_form .= isset($map->radFormSnipet) ? $map->radFormSnipet : '';
            $radius_form = str_replace('[input_center]', $input, $radius_form);
            $radius_form = str_replace('[distance_sel]', $dists, $radius_form);
            $radius_form = str_replace('[search_btn]', $search_btn, $radius_form);
            $radius_form .= '</form>';
        } elseif ($radFormMode == 3 && $nbrMs > 0) {
            $radius_form .= $divsOnMapGo;
            $radius_form .= $input;
            $radius_form .= $dists;
            $radius_form .= '<button type="button" class="btn btn-secondary gfMapControls" onclick="' . $mapVar . '.SLRES();" id="gf_reset_rad_btn">' . Text::_('COM_GEOFACTORY_RESET_MAP') . '</button>';
            $radius_form .= ' <button type="button" class="btn btn-info gfMapControls" onclick="switchPanel(\'gf_toggeler\', 0);">Filters</button>';
            $radius_form .= '</div>';
            $radius_form .= '<div id="gf_toggeler" style="display:none;">';
            $radius_form .= $toggeler_map;
            $radius_form .= '</div>';
            $radius_form .= '</div>';
        } elseif ($radFormMode == 2 || ($radFormMode == 3 && $nbrMs <= 0)) {
            $radius_form .= $divsOnMapGo;
            $radius_form .= $input;
            $radius_form .= $dists;
            $radius_form .= '<button type="button" onclick="' . $mapVar . '.SLFI();" id="gf_search_rad_btn" style="display:none"></button>';
            $radius_form .= '<button type="button" onclick="' . $mapVar . '.SLRES();" id="gf_reset_rad_btn" style="display:none"></button>';
            $radius_form .= '</div>';
            $radius_form .= '</div>';
        }
        
        return $radius_form;
    }
    
    /**
     * Genera il selettore di distanze per il radius
     *
     * @param   object  $map            Oggetto mappa
     * @param   string  $gf_mod_search  Valore ricerca
     * @param   bool    $class          Se aggiungere classe
     * 
     * @return  string
     * @since   1.0
     */
    protected function getRadiusDistances(object $map, string $gf_mod_search, bool $class = true): string
    {
        $ses = Factory::getSession();
        $app = Factory::getApplication('site');
        $gf_mod_radius = $app->input->getFloat('gf_mod_radius', $ses->get('mj_rs_ref_dist', 0));
        
        if ($gf_mod_search && (strlen($gf_mod_search) > 0)) {
            $ses->set('gf_ss_search_phrase', $gf_mod_search);
            $ses->set('gf_ss_search_radius', $gf_mod_radius);
        }
        
        $frontDistSelect = isset($map->frontDistSelect) ? $map->frontDistSelect : "5,10,25,50,100";
        $listVal = explode(',', $frontDistSelect);
        $find = false;
        
        $unit = Text::_('COM_GEOFACTORY_UNIT_KM');
        if (!empty($map->fe_rad_unit)) {
            $unit = ($map->fe_rad_unit == 1) ? Text::_('COM_GEOFACTORY_UNIT_MI') : $unit;
            $unit = ($map->fe_rad_unit == 2) ? Text::_('COM_GEOFACTORY_UNIT_NM') : $unit;
        }
        
        $cls = $class ? 'class="form-select gfMapControls"' : 'class="form-select"';
        $radForm = '<select id="radiusSelect" ' . $cls . ' onChange="if (typeof mj_radiuscenter !== \'undefined\' && mj_radiuscenter){' . $map->mapInternalName . '.SLFI();}">';
        
        if (is_array($listVal)) {
            foreach ($listVal as $val) {
                $val = trim($val);
                if (!is_numeric($val)) {
                    continue;
                }
                
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
    
    /**
     * Sostituisce i tag dyncat nel template
     *
     * @param   string  $text  Testo template
     * 
     * @return  string
     * @since   1.0
     */
    protected function replaceDynCat(string $text): string
    {
        $regex = '/{dyncat\s+(.*?)}/i';
        
        if (strpos($text, "{dyncat ") === false) {
            return $text;
        }
        
        preg_match_all($regex, $text, $matches);
        $count = is_array($matches[0]) ? count($matches[0]) : 0;
        
        if ($count < 1) {
            return $text;
        }
        
        for ($i = 0; $i < $count; $i++) {
            $code = str_replace("{dyncat ", '', $matches[0][$i]);
            $code = str_replace("}", '', $code);
            $code = trim($code);
            $vCode = explode('#', $code);
            
            if ((count($vCode) < 1) || (strlen($vCode[1]) < 1)) {
                continue;
            }
            
            // Qui si possono aggiungere azioni JS se necessario
        }
        
        return preg_replace($regex, '', $text);
    }
}