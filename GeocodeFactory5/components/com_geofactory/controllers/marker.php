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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Component\ComponentHelper;

class GeofactoryControllerMarker extends BaseController
{
    protected $text_prefix = 'COM_GEOFACTORY';

    // Metodo per la visualizzazione della "bolla" (bubble)
    public function bubble()
    {
        $app  = Factory::getApplication();
        $idM  = $app->input->getInt('idU', -1);
        $idMs = $app->input->getInt('idL', -1);
        $dist = $app->input->getFloat('dist', -1);

        $model = $this->getModel('Marker');
        $vids  = [$idM];
        $vDist = [$dist];

        $model->init($vids, $idMs, $vDist, 1);
        $content = $model->loadTemplate();

        // Output in modo compatibile con Joomla 4
        $app->setHeader('Content-Type', 'text/html; charset=utf-8');
        $app->setBody($content);
        $app->close();
    }

    // Visualizza la bolla per i nuovi Google Place
    public function bubblePl()
    {
        $app  = Factory::getApplication();
        $dist = $app->input->getFloat('dist', -1);
        $idMs = $app->input->getInt('idL', -1);

        $model = $this->getModel('Marker');
        $model->initLt($idMs);
        $content = $model->loadTemplate();

        // Output in modo compatibile con Joomla 4
        $app->setHeader('Content-Type', 'text/html; charset=utf-8');
        $app->setBody($content);
        $app->close();
    }

    // Visualizza un singolo marker
    public function side()
    {
        $app  = Factory::getApplication();
        $idM  = $app->input->getInt('idU', -1);
        $idMs = $app->input->getInt('idL', -1);
        $dist = $app->input->getFloat('dist', -1);

        $model = $this->getModel('Marker');
        $vids  = [$idM];
        $vDist = [$dist];

        $model->init($vids, $idMs, $vDist, 2);
        $content = $model->loadTemplate();

        // Output in modo compatibile con Joomla 4
        $app->setHeader('Content-Type', 'text/html; charset=utf-8');
        $app->setBody($content);
        $app->close();
    }

    // Visualizza tutti i markers in una volta
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

        if (is_array($brutes) && count($brutes) > 1 && count($brutes) % 2 == 0) {
            $max = count($brutes);
            for ($i = 0; $i < $max; $i++) {
                $vIds[]  = (int)$brutes[$i];
                $i++;
                $vDist[] = (float)$brutes[$i];
            }

            $model->init($vIds, $idMs, $vDist, 2);
            $content = $model->loadTemplate();
        }

        // Output in modo compatibile con Joomla 4
        $app->setHeader('Content-Type', 'text/html; charset=utf-8');
        $app->setBody($content);
        $app->close();
    }

    // Visualizza più markers nella stessa posizione (multi-bubble)
    public function bubbleMulti()
    {
        $app = Factory::getApplication();

        $json = $app->input->get('idsDists', '', 'STRING');
        $idMs = $app->input->getInt('idL', -1);
        $model = $this->getModel('Marker');

        $config = ComponentHelper::getParams('com_geofactory');
        if ($config->get('multibubble_json') == 1) {
            $brutes = json_decode($json, true);
        } else {
            $brutes = $json;
        }

        $brutes = explode(',', $brutes);
        $vIds  = [];
        $vDist = [];

        $start = Text::_('COM_GEOFACTORY_AROUND_MULTI_BUBBLE_1');
        $end   = Text::_('COM_GEOFACTORY_AROUND_MULTI_BUBBLE_2');

        $content = "";
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
        }

        $titre = Text::_('COM_GEOFACTORY_THEREIS_X_ENTRIES_HERE');
        if (strlen($titre) > 2) {
            $titre = sprintf($titre, count($vIds));
            $content = $titre . $content;
        }

        // Output in modo compatibile con Joomla 4
        $app->setHeader('Content-Type', 'text/html; charset=utf-8');
        $app->setBody($content);
        $app->close();
    }
}