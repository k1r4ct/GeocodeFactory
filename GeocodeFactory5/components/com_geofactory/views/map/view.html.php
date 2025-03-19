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

// Se esiste, carica il helper (o altri file necessari)
require_once JPATH_COMPONENT . '/helpers/geofactory.php';

class GeofactoryViewMap extends HtmlView
{
    protected $item;
    protected $params;
    protected $state;
    protected $user;
    protected $pageclass_sfx;

    /**
     * Metodo (ereditato) che esegue la preparazione dati del View
     * e la lettura dei parametri principali da Joomla.
     */
    public function initView($map)
    {
        // Recupera l'app, l'utente
        $app   = Factory::getApplication();
        $user  = Factory::getUser();

        // Assegna i dati recuperati dal Controller/Model
        $this->item  = $map;
        $this->state = $this->get('State');
        $this->user  = $user;
        $this->params = $app->getParams();

        // In Joomla 4, $this->document dovrebbe essere già valorizzato;
        // in caso contrario lo si forza:
        if (!isset($this->document) || !$this->document) {
            $this->document = Factory::getDocument();
        }
    }

    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the template file to parse
     * @return  mixed
     */
    public function display($tpl = null)
    {
        // Ricava l'oggetto "Item" dal Model, poi chiama la initView
        $this->initView($this->get('Item'));

        // Controlla eventuali errori
        $errors = $this->get('Errors');
        if (count($errors)) {
            Factory::getApplication()->enqueueMessage(implode("\n", $errors), 'warning');
            return false;
        }

        // Crea un alias più comodo
        $item = $this->item;

        // In Joomla 3 usavi: $item->tagLayout = new JLayoutFile('joomla.content.tags');
        // In Joomla 4 -> FileLayout
        $item->tagLayout = new FileLayout('joomla.content.tags');

        // Costruisci lo slug
        $item->slug = $item->alias ? ($item->id . ':' . $item->alias) : $item->id;

        // Gestione Tag tramite TagsHelper (Joomla 4)
        // Utilizza il metodo statico invece dell'istanza diretta
        // $item->tags = TagsHelper::getItemTags('com_geofactory.map', $this->item->id);

        $tagHelper = new TagsHelper;
        $item->tags = $tagHelper->getItemTags('com_geofactory.map', $this->item->id);

        // Escape strings
        $this->pageclass_sfx = htmlspecialchars($this->params->get('pageclass_sfx'), ENT_QUOTES, 'UTF-8');

        // Prepara il documento (metadati, js, css, ecc.)
        $this->_prepareDocument();

        // Ora lascia che la view venga caricata con layout standard
        return parent::display($tpl);
    }

    /**
     * Prepara il documento aggiungendo Script, CSS, Title, Meta, ecc.
     * (Erede della vecchia _prepareDocument in Joomla 3)
     */
    protected function _prepareDocument()
    {
        // Debug - verifica dei parametri iniziali
        error_log('Geocode Factory Debug: Inizializzazione mappa - ID=' . $this->item->id);
        // Preleva le info principali
        $app     = Factory::getApplication();
        $session = Factory::getSession();
        $config  = ComponentHelper::getParams('com_geofactory');
        $root    = Uri::root();

        // Leggi eventuali parametri dalla richiesta
        $adre = $app->input->getString('gf_mod_search', '');
        $adre = htmlspecialchars(str_replace(['"', ''], '', $adre), ENT_QUOTES, 'UTF-8');
        $dist = $app->input->getFloat('gf_mod_radius', 1);

        // Costruisci parametri url
        $urlParams = [];
        $urlParams[] = 'idmap=' . $this->item->id;
        $urlParams[] = 'mn=' . $this->item->mapInternalName;
        $urlParams[] = 'zf=' . $this->item->forceZoom;
        $urlParams[] = 'gfcc=' . $this->item->gf_curCat;
        $urlParams[] = 'zmid=' . $this->item->gf_zoomMeId;
        $urlParams[] = 'tmty=' . $this->item->gf_zoomMeType;
        $urlParams[] = 'code=' . rand(1, 100000);

        $dataMap = implode('&', $urlParams);

        // Gestione di jQuery e Bootstrap in Joomla 4
        $jsBootStrap  = $config->get('jsBootStrap');
        $cssBootStrap = $config->get('cssBootStrap');
        $jqMode       = $config->get('jqMode');
        $jqVersion    = strlen($config->get('jqVersion')) ? $config->get('jqVersion') : '2.0';
        $jqUiversion  = strlen($config->get('jqUiversion')) ? $config->get('jqUiversion') : '1.10';
        $jqUiTheme    = strlen($config->get('jqUiTheme'))   ? $config->get('jqUiTheme')   : 'none';

        // Verifica se il sito è SSL
        $http = $config->get('sslSite');
        if (empty($http)) {
            $http = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        }

        // Gestione tabs
        $jqui = $this->item->useTabs ? true : false;
        if ($this->item->useTabs && ($jqMode == 0 || $jqMode == 2)) {
            $jqMode = 1;
        }

        // Caricamento jQuery in base al "jqMode"
        switch ($jqMode) {
            case 0: // nessun caricamento jQuery
                break;
            case 2: // Joomla
                HTMLHelper::_('jquery.framework', false);
                if ($jqui) {
                    HTMLHelper::_('jquery.ui', ['core', 'sortable', 'draggable', 'droppable']);
                }
                break;
            case 3: // CDN Google
                $this->document->addScript($http . 'ajax.googleapis.com/ajax/libs/jquery/' . $jqVersion . '/jquery.min.js');
                if ($jqui) {
                    $this->document->addScript($http . 'ajax.googleapis.com/ajax/libs/jqueryui/' . $jqUiversion . '/jquery-ui.min.js');
                    $this->document->addStyleSheet($http . 'ajax.googleapis.com/ajax/libs/jqueryui/' . $jqUiversion . '/themes/' . $jqUiTheme . '/jquery-ui.css');
                }
                break;
            case 4: // code.jquery.com
                $this->document->addScript($http . 'code.jquery.com/jquery-' . $jqVersion . '.min.js');
                if ($jqui) {
                    $this->document->addScript($http . 'code.jquery.com/ui/' . $jqUiversion . '/jquery-ui.min.js');
                    $this->document->addStyleSheet($http . 'code.jquery.com/ui/' . $jqUiversion . '/themes/' . $jqUiTheme . '/jquery-ui.css');
                }
                break;
            default:
            case 1: // Local
                $this->document->addScript($root . 'components/com_geofactory/assets/js/jquery/' . $jqVersion . '/jquery.min.js');
                if ($jqui) {
                    $this->document->addScript($root . 'components/com_geofactory/assets/js/jqueryui/' . $jqUiversion . '/jquery-ui.min.js');
                    $this->document->addStyleSheet($root . 'components/com_geofactory/assets/js/jqueryui/' . $jqUiversion . '/themes/_name_/jquery-ui.css');
                }
                break;
        }

        // Caricamento Bootstrap aggiornato a Bootstrap 5 (senza duplicazioni)
        if ($jsBootStrap == 1 || $cssBootStrap == 1) {
            $bootstrapCss = $http . 'cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css';
            $bootstrapJs  = $http . 'cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js';
            if ($cssBootStrap == 1) {
                $this->document->addStyleSheet($bootstrapCss);
            }
            if ($jsBootStrap == 1) {
                $this->document->addScript($bootstrapJs);
            }
        }

        // Istanza JavaScript principale
        $jsVarName = $this->item->mapInternalName;
        $js        = [];

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
            $this->_setLayers($jsVarName, $js);
            $this->_getSourceUrl($jsVarName, $js, $root);
            GeofactoryModelMap::_loadTiles($jsVarName, $js, $this->item);

            // Ricerca / caricamento marker
            $gf_ss_search_phrase = $session->get('gf_ss_search_phrase', null);
            if ($gf_ss_search_phrase && strlen($gf_ss_search_phrase) > 0) {
                $js[] = "    {$jsVarName}.searchLocationsFromInput();";
                $session->clear('gf_ss_search_phrase');
            } elseif ($this->item->useBrowserRadLoad == 1) {
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
            $js  = implode($sep, $js);
        }

        // Google API Key
        $ggApikey = strlen($config->get('ggApikey')) > 3 ? "&key=" . $config->get('ggApikey') : "";

        // Verifica se servono librerie "weather"
        $arLayers = [];
        if (is_array($this->item->layers)) {
            foreach ($this->item->layers as $tmp) {
                if (intval($tmp) > 0) {
                    $arLayers[] = $tmp;
                }
            }
        }
        $lib = ((count($arLayers) > 0) && (in_array(4, $arLayers) || in_array(5, $arLayers) || in_array(6, $arLayers))) ? ",weather" : "";

        // Se ci sono "layers" o "radFormMode"
        if (count($arLayers) > 0 || $this->item->radFormMode > 1) {
            $this->document->addStyleSheet('components/com_geofactory/assets/css/geofactory-maps_btn.css');
        }

        // Full CSS custom
        $this->document->addStyleDeclaration($this->item->fullCss);

        // Caricamento script Google Maps
        $mapLang = (strlen($config->get('mapLang')) > 1) ? '&language=' . $config->get('mapLang') : '';
        $this->document->addScript($http . 'maps.googleapis.com/maps/api/js?libraries=places' . $ggApikey . $mapLang . $lib);

        // Se presente un file custom
        if (file_exists(JPATH_BASE . '/components/com_geofactory/assets/js/custom.js')) {
            $this->document->addScript($root . 'components/com_geofactory/assets/js/custom.js');
        }

        // Cluster
        if ($this->item->useCluster == 1) {
            $this->document->addScript($root . 'components/com_geofactory/assets/js/markerclusterer-5151023.js');
        }

        if (!GeofactoryHelper::useNewMethod($this->item)) {
            $this->document->addScript($root . 'components/com_geofactory/assets/js/map_api-5151020.js');
            $this->document->addScriptDeclaration($js);
        } else {
            // Gestione "nuovo" con script generato in modo differente
            if (empty($dist)) {
                $dist = $app->input->getFloat('mj_rs_radius_selector', 1);
            }
            $this->document->addScript('index.php?option=com_geofactory&task=map.getJs&' . implode('&', $urlParams));
        }

        // Impostazioni del titolo e meta (classico Joomla)
        $menus   = $app->getMenu();
        $menu    = $menus->getActive();
        $title   = null;

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
     */
    protected function _getSourceUrl($oMap, &$js, $root)
    {
        $config = ComponentHelper::getParams('com_geofactory');
        $idmap  = $this->item->id;

        $app    = Factory::getApplication();
        $itemid = $app->input->getInt('Itemid', 0);
        $lang   = $app->input->get('lang', '', 'word');
        if (strlen($lang) > 1) {
            $lang = "&lang={$lang}";
        }

        $paramsUrl = "&gfcc={$this->item->gf_curCat}&zmid={$this->item->gf_zoomMeId}&tmty={$this->item->gf_zoomMeType}&code=" . rand(1, 100000) . $lang;

        // Gestione cache
        $useCache = 0;
        if ($this->item->cacheTime > 0) {
            $cache_file_serverpath = GeofactoryHelper::getCacheFileName($idmap, $itemid, 1);
            $filemtime             = @filemtime($cache_file_serverpath);
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
     */
    protected function _setLayers($oMap, &$js)
    {
        $arLayersTmp = $this->item->layers;
        if (!is_array($arLayersTmp) || !count($arLayersTmp)) {
            return;
        }

        // Filtra i layers validi
        $arLayers = [];
        foreach ($arLayersTmp as $tmp) {
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
}
