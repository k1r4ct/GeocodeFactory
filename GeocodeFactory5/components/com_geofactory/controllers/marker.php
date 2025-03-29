<?php
/**
 * CONTROLLER
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Cédric Pelloquin aka Rick <info@myJoom.com>
 * @website     www.myJoom.com
 * @update      Daniele Bellante
 */

defined('_JEXEC') or die;

require_once JPATH_COMPONENT . '/helpers/geofactory.php';
require_once JPATH_COMPONENT . '/helpers/GeofactoryHelperPlus.php';

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Language\Text;

class GeofactoryControllerMarker extends BaseController
{
    protected $text_prefix = 'COM_GEOFACTORY';
    // protected $log_file = JPATH_ROOT . '/logs/marker_debug.log';

    // /**
    //  * Scrive un messaggio nel file di log
    //  *
    //  * @param string $message Il messaggio da loggare
    //  */
    // protected function logMessage($message)
    // {
    //     $timestamp = date('Y-m-d H:i:s');
    //     file_put_contents($this->log_file, "[{$timestamp}] {$message}\n", FILE_APPEND);
    // }

    /**
     * Metodo per la visualizzazione della "bolla" (bubble)
     * 
     * @return void
     * @since 1.0
     */
    public function bubble()
    {
        $app  = Factory::getApplication();
        $idM  = $app->input->getInt('idU', -1);
        $idMs = $app->input->getInt('idL', -1);
        $dist = $app->input->getFloat('dist', -1);

        // // $this->logMessage("Richiesta bubble per marker ID={$idM}, set={$idMs}, dist={$dist}");

        $model = $this->getModel('Marker');
        $vids  = [$idM];
        $vDist = [$dist];

        $model->init($vids, $idMs, $vDist, 1);
        $content = $model->loadTemplate();

        // // $this->logMessage("Bubble generato con successo (dimensione: " . strlen($content) . " bytes)");

        // Output diretto
        header('Content-Type: text/html; charset=utf-8');
        echo $content;
        exit;

    }

    /**
     * Visualizza la bolla per i nuovi Google Place
     * 
     * @return void
     * @since 1.0
     */
    public function bubblePl()
    {
        $app  = Factory::getApplication();
        $dist = $app->input->getFloat('dist', -1);
        $idMs = $app->input->getInt('idL', -1);

        // // $this->logMessage("Richiesta bubblePl per set={$idMs}, dist={$dist}");

        $model = $this->getModel('Marker');
        $model->initLt($idMs);
        $content = $model->loadTemplate();

        // $this->logMessage("BubblePl generato con successo (dimensione: " . strlen($content) . " bytes)");

        // Output diretto
        header('Content-Type: text/html; charset=utf-8');
        echo $content;
        exit;
    }

    /**
     * Visualizza un singolo marker
     * 
     * @return void
     * @since 1.0
     */
    public function side()
    {
        $app  = Factory::getApplication();
        $idM  = $app->input->getInt('idU', -1);
        $idMs = $app->input->getInt('idL', -1);
        $dist = $app->input->getFloat('dist', -1);

        // $this->logMessage("Richiesta side per marker ID={$idM}, set={$idMs}, dist={$dist}");

        $model = $this->getModel('Marker');
        $vids  = [$idM];
        $vDist = [$dist];

        $model->init($vids, $idMs, $vDist, 2);
        $content = $model->loadTemplate();

        // $this->logMessage("Side marker generato con successo (dimensione: " . strlen($content) . " bytes)");

        // Output diretto
        header('Content-Type: text/html; charset=utf-8');
        echo $content;
        exit;
    }

    /**
     * Visualizza tutti i markers in una volta
     * 
     * @return void
     * @since 1.0
     */
    public function fullSide()
    {
        $app  = Factory::getApplication();
        $json = $app->input->get('idsDists', '', 'STRING');
        $idMs = $app->input->getInt('idL', -1);
        
        
        $model  = $this->getModel('Marker');
        $brutes = json_decode($json, true);
        
        $content = "";
        if (is_string($brutes)) {
            $brutes = explode(',', $brutes);
        }
        $vIds  = [];
        $vDist = [];
        
        // Verifica che $brutes sia un array prima di chiamare count()
        if (is_array($brutes) && count($brutes) > 1 && count($brutes) % 2 == 0) {
            $max = count($brutes);
            for ($i = 0; $i < $max; $i++) {
                $vIds[]  = (int)$brutes[$i];
                $i++;
                $vDist[] = (float)$brutes[$i];
            }
            
            $model->init($vIds, $idMs, $vDist, 2);
            $content = $model->loadTemplate();
            // var_dump('kkk');
            // die();
            // $this->logMessage("FullSide generato con " . count($vIds) . " markers");
        } else {
            // $this->logMessage("FullSide: formato dati non valido");
        }

        // Output diretto
        header('Content-Type: text/html; charset=utf-8');
        echo $content;
        exit;

    }

    /**
     * Visualizza più markers nella stessa posizione (multi-bubble)
     * 
     * @return void
     * @since 1.0
     */
    public function bubbleMulti()
    {
        $app = Factory::getApplication();

        $json = $app->input->get('idsDists', '', 'STRING');
        $idMs = $app->input->getInt('idL', -1);
        
        // $this->logMessage("Richiesta bubbleMulti per set={$idMs}, idsDists={$json}");
        
        $model = $this->getModel('Marker');

        $config = \Joomla\CMS\Component\ComponentHelper::getParams('com_geofactory');
        if ($config->get('multibubble_json') == 1) {
            $brutes = json_decode($json, true);
            // Verifica che json_decode non abbia fallito, altrimenti fallback a stringa
            if ($brutes === null) {
                $brutes = $json;
                // $this->logMessage("JSON decode fallito, usando stringa originale");
            }
        } else {
            $brutes = $json;
        }

        // Se $brutes è ancora una stringa, facciamo explode
        if (is_string($brutes)) {
            $brutes = explode(',', $brutes);
        }
        
        $vIds  = [];
        $vDist = [];

        $start = Text::_('COM_GEOFACTORY_AROUND_MULTI_BUBBLE_1');
        $end   = Text::_('COM_GEOFACTORY_AROUND_MULTI_BUBBLE_2');

        $content = "";
        // Verifica che $brutes sia un array prima di chiamare count()
        if (is_array($brutes) && count($brutes) > 1 && count($brutes) % 2 == 0) {
            $max = count($brutes);
            for ($i = 0; $i < $max; $i++) {
                $vIds[]  = (int)$brutes[$i];
                $i++;
                $vDist[] = (float)$brutes[$i];
            }

            $model->init($vIds, $idMs, $vDist, 1);
            $model->initBubbleMulti($start, $end);
            $content = $model->loadTemplate();
            // $this->logMessage("BubbleMulti generato con " . count($vIds) . " markers");
        } else {
            // $this->logMessage("BubbleMulti: formato dati non valido");
        }

        $titre = Text::_('COM_GEOFACTORY_THEREIS_X_ENTRIES_HERE');
        if (strlen($titre) > 2) {
            // Garantisce che $vIds sia un array prima di chiamare count()
            $titre = sprintf($titre, is_array($vIds) ? count($vIds) : 0);
            $content = $titre . $content;
        }

        // Output diretto
        header('Content-Type: text/html; charset=utf-8');
        echo $content;
        exit;
    }
}