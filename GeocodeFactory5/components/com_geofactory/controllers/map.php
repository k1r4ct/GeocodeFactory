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

    public function getJson()
    {
        // Aggiungi log iniziale
        error_log('GeocodeFactory Debug: Chiamata a getJson()');
        
        try {
            // Recupera l'applicazione
            $app = Factory::getApplication();
            $idMap = $app->input->getInt('idmap', -1);
            
            error_log('GeocodeFactory Debug: getJson richiesto per la mappa ID=' . $idMap);
            
            // Ottieni il modello "Map"
            error_log('GeocodeFactory Debug: Tentativo di caricamento del modello Map');
            $model = $this->getModel('Map');
            error_log('GeocodeFactory Debug: Modello Map caricato correttamente');
            
            error_log('GeocodeFactory Debug: Chiamata al metodo createfile()');
            $json = $model->createfile($idMap);
            error_log('GeocodeFactory Debug: Metodo createfile() completato con successo');
            
            error_log('GeocodeFactory Debug: JSON generato con successo, lunghezza=' . strlen($json));
            
            // Output del JSON in modo compatibile con Joomla 4
            error_log('GeocodeFactory Debug: Impostazione header Content-Type');
            $app->setHeader('Content-Type', 'application/json');
            error_log('GeocodeFactory Debug: Impostazione body');
            $app->setBody($json);
            error_log('GeocodeFactory Debug: Chiusura applicazione');
            $app->close();
            error_log('GeocodeFactory Debug: Applicazione chiusa');
        } catch (Exception $e) {
            error_log('GeocodeFactory Debug: Errore in getJson(): ' . $e->getMessage());
            throw $e;
        }
    }

    public function geocodearticle()
    {
        error_log('GeocodeFactory Debug: Chiamata a geocodearticle()');
        
        $app   = Factory::getApplication();
        $id    = $app->input->getInt('idArt', -1);
        $lat   = $app->input->getFloat('lat');
        $lng   = $app->input->getFloat('lng');
        $adr   = $app->input->getString('adr');
        $c_opt = $app->input->getString('c_opt', 'com_content');

        error_log('GeocodeFactory Debug: Geocoding articolo ID=' . $id . ', lat=' . $lat . ', lng=' . $lng);
        
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
        }

        $db->setQuery($query);
        $db->execute();

        // Risposta di successo
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json');
        $app->setBody(json_encode(['success' => true]));
        error_log('GeocodeFactory Debug: Geocoding articolo completato con successo');
        $app->close();
    }

    public function getJs()
    {
        error_log('GeocodeFactory Debug: Chiamata a getJs()');
        
        $app   = Factory::getApplication();
        $idMap = $app->input->getInt('idmap', -1);

        error_log('GeocodeFactory Debug: getJs richiesto per la mappa ID=' . $idMap);
        
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

        foreach ($files as $file) {
            $filePath = JPATH_ROOT . '/' . $file;
            if (is_file($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) === 'js') {
                $js[] = "/* {$file} */";
                $js[] = file_get_contents($filePath);
            }
        }

        // Aggiunge l'inizializzazione al caricamento della pagina
        $js[] = "console.log('GeocodeFactory Debug: Configurazione event listener');";
        $js[] = "google.maps.event.addDomListener(window, 'load', init2_{$jsVarName});";
        $js[] = "console.log('GeocodeFactory Debug: Configurazione JS completata');";

        $sep = GeofactoryHelper::isDebugMode() ? "\n" : "";
        $jsContent = implode($sep, $js);

        error_log('GeocodeFactory Debug: JS generato con successo, lunghezza=' . strlen($jsContent));
        
        // Output JavaScript in modo compatibile con Joomla 4
        $app->setHeader('Content-Type', 'application/javascript; charset=utf-8');
        $app->setBody($jsContent);
        $app->close();
    }
}