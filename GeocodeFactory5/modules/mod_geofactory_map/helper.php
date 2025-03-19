<?php
/**
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Cédric Pelloquin aka Rick <info@myJoom.com>
 * @update      Daniele Bellante
 * @website     www.myJoom.com
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Helper class for mod_geofactory_map
 *
 * @since  1.0
 */
class ModGeofactoryMapHelper
{
    /**
     * Verifica le condizioni per mostrare la mappa basate sui parametri
     *
     * @param   object  $params  I parametri del modulo
     * @return  bool    True se la mappa dovrebbe essere mostrata
     * @since   1.0
     */
    public static function checkTasks($params): bool
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

        $ignoreTasks = explode(',', strtolower($params->get('taskToIgnore', '')));
        $forceTasks  = explode(',', strtolower($params->get('taskToForce', '')));

        if (is_array($forceTasks) && !empty($forceTasks)) {
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

        if (is_array($ignoreTasks) && !empty($ignoreTasks)) {
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

    /**
     * Aggiunge script necessari per la gestione delle schede
     *
     * @param   object  $params  I parametri del modulo
     * @return  void
     * @since   1.0
     */
    public static function addScript($params): void
    {
        $tab_id = $params->get('usetab_id', '');
        if (strlen($tab_id) < 1) {
            return;
        }
        
        // Script per gestire il ridimensionamento mappa quando la scheda viene attivata
        $js = "
            jQuery(function() {
                jQuery('#stabs').on('tabsactivate', function(event, ui) {
                    if (ui.newPanel.attr('id') == '{$tab_id}') {
                        // Ridimensiona la mappa quando la scheda diventa visibile
                        if (typeof mapVar !== 'undefined' && mapVar.map) {
                            google.maps.event.trigger(mapVar.map, 'resize');
                            if (typeof centerPointGFmap !== 'undefined') {
                                mapVar.map.setCenter(centerPointGFmap);
                            }
                        }
                    }
                });
            });
        ";

        // Usa WebAssetManager per aggiungere lo script inline
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->addInlineScript($js);
    }
}
