<?php
/**
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Cédric Pelloquin aka Rick <info@myJoom.com>
 * @update		Daniele Bellante
 * @website     www.myJoom.com
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class ModGeofactoryMapHelper
{
    public static function checkTasks($params)
    {
        // Utilizziamo l'input di Joomla 4 al posto di JRequest
        $input   = Factory::getApplication()->input;
        $task    = $input->getString('task', '');
        $session = Factory::getApplication()->getSession();

        if (strcasecmp($task, "search.results") === 0) {
            $ssid = $input->cookie->getString('SPro_ssid', '');
            if (strlen($ssid) > 0) {
                $session->set('gf_sp_ssid', $ssid);
            }
        } else {
            $session->clear('gf_sp_ssid');
        }

        $ignoreTasks = explode(',', strtolower($params->get('taskToIgnore')));
        $forceTasks  = explode(',', strtolower($params->get('taskToForce')));

        if (!empty($forceTasks)) {
            foreach ($forceTasks as $pair) {
                $vPair = explode('=', trim($pair));
                if (count($vPair) !== 2) {
                    continue;
                }
                $taskValue = $input->getString(trim($vPair[0]), '');
                if (($vPair[1] === "?") && (strlen($taskValue) > 0)) {
                    return true;
                } elseif (($vPair[1] === "?") && (strlen($taskValue) < 1)) {
                    return false;
                }
                if (strlen($taskValue) > 0 && strtolower($taskValue) === strtolower(trim($vPair[1]))) {
                    return true;
                }
            }
        }

        if (!empty($ignoreTasks)) {
            foreach ($ignoreTasks as $pair) {
                $vPair = explode('=', trim($pair));
                if (count($vPair) !== 2) {
                    continue;
                }
                // Caso speciale per Sobipro
                if (($vPair[0] === 'task') && ($vPair[1] === 'entry.details')) {
                    $pid = $input->getInt('pid', 0);
                    $sid = $input->getInt('sid', 0);
                    if ($pid > 0 && $sid > 0) {
                        return false;
                    }
                }
                $taskValue = $input->getString(trim($vPair[0]), '');
                if (($vPair[1] === "?") && (strlen($taskValue) > 0)) {
                    return false;
                }
                if (strlen($taskValue) > 0 && strtolower($taskValue) === strtolower(trim($vPair[1]))) {
                    return false;
                }
            }
        }

        // Valore di default
        return true;
    }

    public static function addScript($params)
    {
        $tab_id = $params->get('usetab_id');
        if (strlen($tab_id) < 1) {
            return;
        }
        // Nota: la variabile {$mapVar} deve essere definita nel contesto in cui viene utilizzata.
        $js = "
            jQuery(function() {
                jQuery('#stabs').on('tabsshow', function(event, ui) {
                    if (ui.panel.id == '{$tab_id}') {
                        // Assicurarsi che la variabile 'mapVar' sia definita
                        google.maps.event.trigger(mapVar.map, 'resize');
                        if (centerPointGFmap) {
                            mapVar.map.setCenter(centerPointGFmap);
                        }
                    }
                });
            });
        ";

        $document = Factory::getDocument();
        $document->addScriptDeclaration($js);
    }
}
