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

// Uso dei namespace di Joomla 4
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

// Includi eventuali helper
require_once JPATH_COMPONENT . '/helpers/geofactory.php';

/**
 * Controller per la mappa di Geocode Factory
 */
class GeofactoryControllerMap extends BaseController
{
    protected $text_prefix = 'COM_GEOFACTORY';
    protected $log_file = JPATH_ROOT . '/logs/map_debug.log';

    /**
     * Scrive un messaggio nel file di log
     *
     * @param string $message Il messaggio da loggare
     */
    protected function logMessage($message)
    {
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($this->log_file, "[{$timestamp}] {$message}\n", FILE_APPEND);
    }

    public function getJson()
    {
        try {
            $app = Factory::getApplication();
            $idMap = $app->input->getInt('idmap', -1);
            
            $this->logMessage("Richiesta getJson per idMap={$idMap}");
            
            $model = $this->getModel('Map');
            $json = $model->createfile($idMap);
            $this->logMessage("JSON generato con successo (dimensione: " . strlen($json) . " bytes)");
            
            // Invia output diretto
            header('Content-Type: application/json');
            echo $json;
            exit;
            
        } catch (Exception $e) {
            $this->logMessage("ERRORE in getJson(): " . $e->getMessage());
            
            header('HTTP/1.1 500 Internal Server Error');
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
    }

    public function geocodearticle()
    {
        $app   = Factory::getApplication();
        $id    = $app->input->getInt('idArt', -1);
        $lat   = $app->input->getFloat('lat');
        $lng   = $app->input->getFloat('lng');
        $adr   = $app->input->getString('adr');
        $c_opt = $app->input->getString('c_opt', 'com_content');

        $this->logMessage("Geocodearticle per ID={$id}, lat={$lat}, lng={$lng}, tipo={$c_opt}");
        
        $db    = Factory::getDbo();
        $cond  = 'type=' . $db->quote($c_opt) . ' AND id_content=' . (int) $id;

        // Verifica se esiste già un record
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__geofactory_contents'))
            ->where($cond);
        $db->setQuery($query);
        $update = $db->loadResult();

        // Pulisce la query
        $query->clear();

        if ((int) $update > 0) {
            // Aggiornamento record esistente
            $fields = [
                'latitude=' . (float) $lat,
                'longitude=' . (float) $lng,
                'address=' . $db->quote($adr)
            ];
            $query->update($db->quoteName('#__geofactory_contents'))
                ->set($fields)
                ->where($cond);
                
            $this->logMessage("Aggiornamento coordinate per articolo esistente");
        } else {
            // Inserimento nuovo record
            $values = [
                $db->quote(''),
                $db->quote($c_opt),
                (int) $id,
                $db->quote($adr),
                (float) $lat,
                (float) $lng
            ];
            $query->insert($db->quoteName('#__geofactory_contents'))
                ->values(implode(',', $values));
                
            $this->logMessage("Inserimento nuovo record per articolo");
        }

        $db->setQuery($query);
        $db->execute();

        // Risposta di successo usando l'approccio diretto
        $this->logMessage("Geocoding articolo completato con successo");
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    public function getJs()
    {
        $app   = Factory::getApplication();
        $idMap = $app->input->getInt('idmap', -1);

        $this->logMessage("Richiesta getJs per mappa ID={$idMap}");
        
        $model = $this->getModel('Map');
        $model->set_loadFull(true);
        $map   = $model->getItem($idMap);

        // Recupera l'URL base
        $root  = Uri::root();

        // Legge parametri extra
        $map->mapInternalName = $app->input->getString('mn');
        $map->forceZoom       = $app->input->getInt('zf');
        $map->gf_curCat       = $app->input->getInt('gfcc');
        $map->gf_zoomMeId     = $app->input->getInt('zmid');
        $map->gf_zoomMeType   = $app->input->getString('tmty');

        $urlParams = [];
        $urlParams[] = 'idmap=' . $map->id;
        $urlParams[] = 'mn='    . $map->mapInternalName;
        $urlParams[] = 'zf='    . $map->forceZoom;
        $urlParams[] = 'gfcc='  . $map->gf_curCat;
        $urlParams[] = 'zmid='  . $map->gf_zoomMeId;
        $urlParams[] = 'tmty='  . $map->gf_zoomMeType;
        $urlParams[] = 'code='  . rand(1, 100000);

        $dataMap = implode('&', $urlParams);
        $this->logMessage("Parametri URL: {$dataMap}");

        $js = [];
        $jsVarName = $map->mapInternalName;
        $js[] = "console.log('GeocodeFactory Debug: Inizializzazione JS dinamico per mappa " . $jsVarName . "');";
        $js[] = "var {$jsVarName} = new clsGfMap2();";
        $js[] = "var gf_sr = '{$root}';";
        $js[] = "function init2_{$jsVarName}() {";
        $js[] = "  console.log('GeocodeFactory Debug: Esecuzione init2_" . $jsVarName . "()');";
        $js[] = "  jQuery.getJSON({$jsVarName}.getMapUrl('{$dataMap}'), function(data) {";
        $js[] = "    console.log('GeocodeFactory Debug: getJSON callback eseguito');";
        $js[] = "    if (!{$jsVarName}.checkMapData(data)) {";
        $js[] = "      document.getElementById('{$jsVarName}').innerHTML = 'Map error.';";
        $js[] = "      console.error('Bad map format.');";
        $js[] = "      return;";
        $js[] = "    }";
        $js[] = "    console.log('GeocodeFactory Debug: Dati mappa validati');";
        $js[] = "    {$jsVarName}.setMapInfo(data, '{$jsVarName}');";
        $js[] = "    console.log('GeocodeFactory Debug: Informazioni mappa impostate');";

        // Richiama i metodi statici interni
        GeofactoryModelMap::_setKml($jsVarName, $js, $map->kml_file);
        GeofactoryModelMap::_loadTiles($jsVarName, $js, $map);
        GeofactoryModelMap::_loadDynCatsFromTmpl($jsVarName, $js, $map);

        $js[] = "    console.log('GeocodeFactory Debug: Configurazioni aggiuntive completate');";
        $js[] = "  });";
        $js[] = "}";

        // Parametri di ricerca per il raggio
        $adre = $app->input->getString('gf_mod_search', '');
        $adre = htmlspecialchars(str_replace(['"', ''], '', $adre), ENT_QUOTES, 'UTF-8');
        $dist = $app->input->getFloat('gf_mod_radius', 1);

        $js[] = "var gf_mod_search = '{$adre}';";
        $js[] = "var gf_mod_radius = {$dist};";
        $js[] = "var gfcc = {$map->gf_curCat};";
        $js[] = "var zmid = {$map->gf_zoomMeId};";
        $js[] = "var tmty = '{$map->gf_zoomMeType}';";

        // Aggiunge altri file JS - usando pathinfo per verificare l'estensione
        $files = [
            'components/com_geofactory/assets/js/header.js',
            'components/com_geofactory/assets/js/cls.dynRad.js',
            'components/com_geofactory/assets/js/cls.label.js',
            'components/com_geofactory/assets/js/cls.places.js',
            'components/com_geofactory/assets/js/cls.objects.js',
            'components/com_geofactory/assets/js/cls.layers.js',
            'components/com_geofactory/assets/js/cls.controller.js',
        ];

        $filesLoaded = 0;
        foreach ($files as $file) {
            $filePath = JPATH_ROOT . '/' . $file;
            if (is_file($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) === 'js') {
                $js[] = "/* {$file} */";
                $js[] = file_get_contents($filePath);
                $filesLoaded++;
            }
        }
        $this->logMessage("File JS caricati: {$filesLoaded}");

        // Aggiunge l'inizializzazione al caricamento della pagina
        $js[] = "console.log('GeocodeFactory Debug: Configurazione event listener');";
        $js[] = "google.maps.event.addDomListener(window, 'load', init2_{$jsVarName});";
        $js[] = "console.log('GeocodeFactory Debug: Configurazione JS completata');";

        $sep = GeofactoryHelper::isDebugMode() ? "\n" : "";
        $jsContent = implode($sep, $js);

        $this->logMessage("JS generato con successo (dimensione: " . strlen($jsContent) . " bytes)");
        
        // Output JavaScript usando l'approccio diretto
        header('Content-Type: application/javascript; charset=utf-8');
        echo $jsContent;
        exit;
    }
}