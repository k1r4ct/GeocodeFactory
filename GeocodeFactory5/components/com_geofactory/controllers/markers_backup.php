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
require_once JPATH_COMPONENT . '/helpers/geofactory.php';

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Log\Log;

class GeofactoryControllerMarkers extends BaseController
{
    protected $text_prefix = 'COM_GEOFACTORY';

    public function getJson()
    {
        $app   = Factory::getApplication();
        $idMap = $app->input->getInt('idmap', -1);
        $model = $this->getModel('Markers');
        $json  = $model->createfile($idMap, 'json');
        $config = ComponentHelper::getParams('com_geofactory');
        
        // Nota: impostazioni di memoria rimosse per sicurezza
        // Le seguenti righe sono state rimosse per compatibilità con ambienti restrittivi:
        // ini_set("memory_limit", $mem . "M");
        // if ((int)$mem > 128) { set_time_limit(0); }
        
        // In caso di elaborazione di grandi moli di dati, è preferibile ottimizzare
        // l'algoritmo piuttosto che aumentare i limiti di memoria
        try {
            // Output JSON in modo compatibile con Joomla 4
            $app->setHeader('Content-Type', 'application/json');
            $app->setBody($json);
            $app->close();
        } catch (\Exception $e) {
            // Log dell'errore per fini diagnostici
            Log::add('Errore durante la generazione JSON: ' . $e->getMessage(), Log::ERROR, 'geofactory');
            $app->setHeader('Content-Type', 'application/json');
            $app->setBody(json_encode(['error' => $e->getMessage()]));
            $app->close();
        }
    }

    public function dyncat()
    {
        $app   = Factory::getApplication();
        $idMap = $app->input->getInt('idmap', -1);
        $model = $this->getModel('Markers');

        $idP    = $app->input->getInt('idP', 0);
        $ext    = $app->input->getString('ext', '');
        $mapVar = $app->input->getString('mapVar', '');
        $select = $model->getCategorySelect($ext, $idP, $mapVar);

        // Output HTML in modo compatibile con Joomla 4
        $app->setHeader('Content-Type', 'text/html; charset=utf-8');
        $app->setBody($select);
        $app->close();
    }
}