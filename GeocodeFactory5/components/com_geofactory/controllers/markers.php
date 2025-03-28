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
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;

require_once JPATH_ROOT . '/components/com_geofactory/helpers/geofactory.php';

/**
 * Controller per la gestione dei marker
 *
 * @since  1.0
 */
class GeofactoryControllerMarkers extends BaseController
{
    /**
     * Prefisso dei messaggi di testo
     *
     * @var    string
     * @since  1.0
     */
    protected $text_prefix = 'COM_GEOFACTORY';
    

    /**
     * Restituisce i dati dei marker in formato JSON
     *
     * @return  void
     * @since   1.0
     */
    public function getJson()
    {
        try {
        // Ottieni i dati essenziali
        $app   = Factory::getApplication();
        $idMap = $app->input->getInt('idmap', -1);
        
        // Log della richiesta
        $request_uri = $_SERVER['REQUEST_URI'] ?? 'Richiesta senza URI';
        $lkasdj = gettype($idMap);
        $ofdisf = $idMap < 1;
        $oaiduosud = gettype($ofdisf);
       
       
        
        // Verifica che l'ID mappa sia valido
        if ($idMap < 1) {
            header('HTTP/1.1 400 Bad Request');
            header('Content-Type: application/json');
            echo json_encode(['error' => 'ID mappa non valido']);
            exit;
        }
        
        // Recupera il modello e genera il JSON
        //try {
            $model = $this->getModel('Markers');
            $json  = $model->createfile($idMap, 'json');
            // Verifica che il risultato sia valido
            if (empty($json)) {
                header('HTTP/1.1 404 Not Found');
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Nessun dato disponibile per questa mappa']);
                exit;
            }
            
            // Invia la risposta JSON
            header('Content-Type: application/json');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            echo $json;
            exit;
            
            
        } catch (\Exception $e) {
            header('HTTP/1.1 500 Internal Server Error');
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
    }
    

    /**
     * Restituisce il selettore di categorie
     *
     * @return  void
     * @since   1.0
     */
    public function dyncat()
    {
        $app = Factory::getApplication();
        
        // Recupera i parametri necessari
        $idMap  = $app->input->getInt('idmap', -1);
        $idP    = $app->input->getInt('idP', 0);
        $ext    = $app->input->getString('ext', '');
        $mapVar = $app->input->getString('mapVar', '');
                
        // Verifica parametri obbligatori
        if (empty($ext) || $idP < 1) {
            header('HTTP/1.1 400 Bad Request');
            header('Content-Type: text/html; charset=utf-8');
            echo '<div class="alert alert-danger">Parametri mancanti o non validi</div>';
            exit;
        }
        
        try {
            $model  = $this->getModel('Markers');
            $select = $model->getCategorySelect($ext, $idP, $mapVar);
            
            if (empty($select)) {
                header('HTTP/1.1 404 Not Found');
                header('Content-Type: text/html; charset=utf-8');
                echo '<div class="alert alert-warning">Nessuna categoria disponibile</div>';
                exit;
            }
                        
            // Output HTML diretto
            header('Content-Type: text/html; charset=utf-8');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            echo $select;
            exit;
            
        } catch (\Exception $e) {
            // Log dell'errore
            header('HTTP/1.1 500 Internal Server Error');
            header('Content-Type: text/html; charset=utf-8');
            echo '<div class="alert alert-danger">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>';
            exit;
        }
    }
}