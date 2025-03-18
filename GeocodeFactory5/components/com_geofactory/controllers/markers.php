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
use Joomla\CMS\Response\JsonResponse;

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
        // Ottieni i dati essenziali
        $app   = Factory::getApplication();
        $idMap = $app->input->getInt('idmap', -1);
        
        // Verifica che l'ID mappa sia valido
        if ($idMap < 1) {
            $this->sendJsonError('ID mappa non valido', 400);
            return;
        }
        
        // Recupera il modello e genera il JSON
        try {
            $model = $this->getModel('Markers');
            $json  = $model->createfile($idMap, 'json');
            
            // Verifica che il risultato sia valido
            if (empty($json)) {
                $this->sendJsonError('Nessun dato disponibile per questa mappa', 404);
                return;
            }
            
            // Assicura che sia un JSON valido
            if (!$this->isValidJson($json)) {
                $this->sendJsonError('Errore nella generazione dei dati JSON', 500);
                return;
            }
            
            // Invia la risposta JSON
            $app->setHeader('Content-Type', 'application/json');
            $app->setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
            $app->setBody($json);
            $app->close();
            
        } catch (\Exception $e) {
            $this->sendJsonError($e->getMessage());
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
            $this->sendHtmlError('Parametri mancanti o non validi');
            return;
        }
        
        try {
            $model  = $this->getModel('Markers');
            $select = $model->getCategorySelect($ext, $idP, $mapVar);
            
            if (empty($select)) {
                $this->sendHtmlError('Nessuna categoria disponibile');
                return;
            }
            
            // Output HTML in modo compatibile con Joomla 4
            $app->setHeader('Content-Type', 'text/html; charset=utf-8');
            $app->setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
            $app->setBody($select);
            $app->close();
            
        } catch (\Exception $e) {
            $this->sendHtmlError($e->getMessage());
        }
    }
    
    /**
     * Invia un errore in formato JSON
     *
     * @param   string  $message  Messaggio di errore
     * @param   int     $code     Codice HTTP (default 500)
     * @return  void
     * @since   1.0
     */
    protected function sendJsonError($message, $code = 500)
    {
        $app = Factory::getApplication();
        
        // Log dell'errore
        Log::add($message, Log::ERROR, 'geofactory');
        
        // Imposta l'header HTTP
        $app->setHeader('status', $code);
        $app->setHeader('Content-Type', 'application/json');
        
        // Prepara la risposta
        $response = new JsonResponse(null, $message, true, $code);
        $app->setBody($response);
        $app->close();
    }
    
    /**
     * Invia un errore in formato HTML
     *
     * @param   string  $message  Messaggio di errore
     * @param   int     $code     Codice HTTP (default 400)
     * @return  void
     * @since   1.0
     */
    protected function sendHtmlError($message, $code = 400)
    {
        $app = Factory::getApplication();
        
        // Log dell'errore
        Log::add($message, Log::ERROR, 'geofactory');
        
        // Imposta gli header
        $app->setHeader('status', $code);
        $app->setHeader('Content-Type', 'text/html; charset=utf-8');
        
        // Prepara un messaggio di errore leggibile
        $html = '<div class="alert alert-danger">' . Text::_('JERROR_LAYOUT_ERROR_HAS_OCCURRED') . ': ' . 
                htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</div>';
        
        $app->setBody($html);
        $app->close();
    }
    
    /**
     * Verifica se una stringa è un JSON valido
     *
     * @param   string  $string  Stringa da verificare
     * @return  bool
     * @since   1.0
     */
    protected function isValidJson($string)
    {
        if (!is_string($string) || empty($string)) {
            return false;
        }
        
        json_decode($string);
        return (json_last_error() === JSON_ERROR_NONE);
    }
}