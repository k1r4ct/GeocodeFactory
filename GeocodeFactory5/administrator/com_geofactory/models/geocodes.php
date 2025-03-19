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

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Application\ApplicationHelper;

class GeofactoryModelGeocodes extends ListModel
{
    /**
     * @since   1.6
     */
    protected $basename;
    protected $geocodeQuery = "";

    /**
     * Constructor.
     *
     * @param   array   $config  An optional associative array of configuration settings.
     * @see     JController
     * @since   1.6
     */
    public function __construct($config = array())
    {
        parent::__construct($config);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  mixed   The SQL query string.
     * @since   1.6
     */
    protected function getListQuery()
    {
        // Recupera il tipo di lista e il pattern scelti e crea la query se necessario
        if (strlen($this->geocodeQuery) < 3) {
            $this->geocodeQuery = $this->getGeocodeQuery($this->getState('filter.typeliste'));
        }
        return $this->geocodeQuery;
    }

    // Ritorna la lista degli ID delle entrate da geocodificare (senza LIMIT)
    public function getListIdsToGeocode()
    {
        if (strlen($this->geocodeQuery) < 3) {
            $app  = Factory::getApplication();
            $type = $app->input->get('typeliste', -1);
            $this->geocodeQuery = $this->getGeocodeQuery($type);
        }

        $db = $this->getDbo();
        $db->setQuery($this->geocodeQuery);
        try {
            $res = $db->loadObjectList();
            if (!is_array($res) || count($res) < 1) {
                return [];
            }

            $vRes = array();
            foreach ($res as $r) {
                $vRes[] = $r->item_id;
            }

            return $vRes;
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            return [];
        }
    }

    protected function getGeocodeQuery($typeListe)
    {
        $query = "SELECT '0' as id";
        if (!$typeListe) {
            return $query;
        }

        $app = Factory::getApplication();
        $assign = $app->input->get('assign', -1);
        if ($assign < 1) {
            return $query;
        }

        $vAssign = GeofactoryHelperAdm::getAssignArray($assign);
        PluginHelper::importPlugin('geocodefactory');

        $filters = array(
            $app->input->get('filter_search'),
            $app->input->get('list_direction'),
            $app->input->get('filter_geocoded')
        );
        
        $queries = $app->triggerEvent('getListQueryBackGeocode', array($typeListe, $filters, $this, $vAssign));

        $ok = false;
        foreach ($queries as $q) {
            if (count($q) != 2) {
                continue;
            }
            if (strtolower($q[0]) != strtolower($typeListe)) {
                continue;
            }
            if (!$q[1]) {
                continue;
            }
            $ok = true;
            $query = $q[1];
            break;
        }
 
        if (!$ok) {
            return $query;
        }
        
        return $query;
    }

    public function getAdress($id, $type, $vAssign)
    {
        PluginHelper::importPlugin('geocodefactory');
        $app = Factory::getApplication();
        $results = $app->triggerEvent('getItemPostalAddress', array($type, $id, $vAssign));

        foreach ($results as $r) {
            // Cerca il risultato del plugin corretto
            if (count($r) != 2) {
                continue;
            }
            if (strtolower($r[0]) != strtolower($type)) {
                continue;
            }
            return $r[1];
        }
        return array("Error");
    }

    public function getGoogleGeocodeQuery($vAdd)
    {
        if (!is_array($vAdd)) {
            $vAdd = array($vAdd);
        }
        if (!count($vAdd)) {
            return '';
        }

        $config = ComponentHelper::getParams('com_geofactory');
        $region = trim($config->get('ggRegion', ''));
        $ggSeparator = trim($config->get('ggSeparator'), '+');
        $ggSeparator = (strlen($ggSeparator) < 1) ? '+' : $ggSeparator;

        // API key...
        $ggApikey = trim($config->get('ggApikey'));
        $ggApikey = (strlen($ggApikey) > 4) ? "&key=" . $ggApikey : '';
        $ggApikeyGc = trim($config->get('ggApikeyGc'));
        $ggApikey = (strlen($ggApikeyGc) > 4) ? "&key=" . $ggApikeyGc : $ggApikey;

        $http = $config->get('sslSite');
        if (empty($http)) {
            $http = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        }
        if (strlen($region) == 2) {
            $region = "&region={$region}";
        }

        $adresse = implode($ggSeparator, $vAdd);
        // Prepariamo l'URL in un modo più robusto
        $server = "https://maps.googleapis.com/maps/api/geocode/xml?{$region}{$ggApikey}";
        $urlRequest = $server . "&address=" . urlencode($adresse);

        return $urlRequest;
    }

    /**
     * Method to auto-populate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     *
     * @since   1.6
     */
    protected function populateState($ordering = null, $direction = null)
    {
        $app = Factory::getApplication('administrator');

        $curType = $app->input->get('typeliste', -1);
        $this->setState('filter.typeliste', $curType);

        $assign = $app->input->get('assign', -1);
        $this->setState('filter.assign', $assign);

        $search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
        $this->setState('filter.search', $search);

        $geocoded = $this->getUserStateFromRequest($this->context . '.filter.geocoded', 'filter_geocoded');
        $this->setState('filter.geocoded', $geocoded);

        $params = ComponentHelper::getParams('com_geofactory');
        $this->setState('params', $params);

        parent::populateState('a.name', 'asc');
    }

    public function saveCoord($id, $coor, $type, $vAssign)
    {
        $id = (int) $id;
        if ($id < 1) {
            return Text::_('COM_GEOFACTORY_GEOCODE_SAVE_ERROR_BAD_ID');
        }

        // Dati non validi
        if (!is_array($coor) || count($coor) < 2) {
            return Text::_('COM_GEOFACTORY_GEOCODE_SAVE_ERROR_BAD_COORD');
        }

        PluginHelper::importPlugin('geocodefactory');
        $app = Factory::getApplication();
        $results = $app->triggerEvent('setItemCoordinates', array($type, $id, $coor, $vAssign));

        foreach ($results as $r) {
            if (count($r) != 2) {
                continue;
            }
            if (strtolower($r[0]) != strtolower($type)) {
                continue;
            }
            return $r[1];
        }

        return Text::_('COM_GEOFACTORY_GEOCODE_SAVE_ERROR');
    }

    public function htmlResult($cur, $max, $adr, $save, $progress = true)
    {
        $html = array();
        if ($progress) {
            if ($cur == -999) {
                $cur = $max;
                $save = Text::_('COM_GEOFACTORY_GEOCODE_DONE');
            }
            $cur += 1;
            $pc = ($cur * 100) / $max;
            $step = Text::sprintf('COM_GEOFACTORY_CUR_MAX_X_PC_DONE', $pc, $cur, $max);
            $html[] = '<span style="font-weight:bold;">' . $step . ' </span><div style="height:20px;width:100%;border:1px black solid;"><div style="height:20px;width:' . $pc . '%;background-color:#66CC66;"></div></div>';
        }

        if (is_array($adr)) {
            $html[] = Text::_('COM_GEOFACTORY_GEOCODE_NOW') . " : " . implode(",", $adr);
        }
        $html[] = Text::_('COM_GEOFACTORY_GEOCODE_MESSAGE') . " : " . $save;
        return implode("<br />", $html);
    }

    public function geocodeItem($urlRequest)
    {
        $config = ComponentHelper::getParams('com_geofactory');
        $delay = 333333; // 1/3 di secondo in microsecondi
        $coor = array(255, 255, Text::_('COM_GEOFACTORY_GEOCODE_ERR_UNKNOWN'));
        $geocodePending = true;

        if ((int) $config->get('isDebug', 0) == 1) {
            Factory::getApplication()->enqueueMessage('<a href="' . $urlRequest . '">debug</a><br>');
        }

        if (strlen($urlRequest) < 3) {
            $coor[2] = 'No address to geocode';
            Log::add('NO_ADDRESS :: ' . $coor[2], Log::INFO, 'com_geofactory.geocode');
            return $coor;
        }

        while ($geocodePending) {
            if ($delay > 15000000) {
                $coor[2] = 'Geocode time out, more than 15 secondes to geocode';
                Log::add('TIME_OUT :: ' . $coor[2], Log::INFO, 'com_geofactory.geocode');
                return $coor;
            }

            usleep($delay);

            try {
                $http = HttpFactory::getHttp();
                $response = $http->get($urlRequest);
                
                if ($response->code != 200) {
                    $coor[2] = Text::_('COM_GEOFACTORY_GEOCODE_ERR_SERVER');
                    Log::add('BAD_SERVER_RESPONSE :: ' . $coor[2], Log::INFO, 'com_geofactory.geocode');
                    return $coor;
                }

                $xml = simplexml_load_string($response->body);
                if (!$xml) {
                    $coor[2] = 'Invalid XML response';
                    Log::add('INVALID_XML :: ' . $coor[2], Log::INFO, 'com_geofactory.geocode');
                    return $coor;
                }
                
                $status = $xml->status;

                if (strcmp($status, "OK") == 0) {
                    $geocodePending = false;
                    Log::add((string)$status, Log::INFO, 'com_geofactory.geocode');
                    return array(
                        (double) $xml->result->geometry->location->lat,
                        (double) $xml->result->geometry->location->lng,
                        "Successfull geocoded"
                    );
                } else if (strcmp($status, "ZERO_RESULTS") == 0) {
                    $geocodePending = false;
                    $coor[2] = Text::_('COM_GEOFACTORY_GEOCODE_ERR_NO_RESULT');
                    Log::add($status . ' :: ' . $coor[2], Log::INFO, 'com_geofactory.geocode');
                    return $coor;
                } else if (strcmp($status, "OVER_QUERY_LIMIT") == 0) {
                    $geocodePending = false;
                    $coor[2] = Text::_('COM_GEOFACTORY_GEOCODE_ERR_OVER_LIMIT');
                    Log::add($status . ' :: ' . $coor[2], Log::INFO, 'com_geofactory.geocode');
                    return $coor;
                } else if (strcmp($status, "REQUEST_DENIED") == 0) {
                    $coor[2] = Text::_('COM_GEOFACTORY_GEOCODE_ERR_DENIED');
                    Log::add($status . ' :: ' . $coor[2], Log::INFO, 'com_geofactory.geocode');
                    return $coor;
                } else if (strcmp($status, "INVALID_REQUEST") == 0) {
                    $coor[2] = Text::_('COM_GEOFACTORY_GEOCODE_ERR_INVALID');
                    Log::add($status . ' :: ' . $coor[2], Log::INFO, 'com_geofactory.geocode');
                    return $coor;
                } else {
                    $delay += 100000;
                }
            } catch (\Exception $e) {
                $coor[2] = 'HTTP request error: ' . $e->getMessage();
                Log::add('HTTP_ERROR :: ' . $coor[2], Log::ERROR, 'com_geofactory.geocode');
                return $coor;
            }
        }
        return $coor;
    }

    public function getCurGeocodeId($query, $cur)
    {
        $db = $this->getDbo();
        try {
            $db->setQuery($query, $cur, 1);
            $res = $db->loadObjectList();

            if (count($res) < 1) {
                return -1;
            }

            $r = $res[0];
            return $r->item_id;
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            return -1;
        }
    }

    public function deleteFromGFTable($type)
    {
        $app = Factory::getApplication();
        $ids = $app->input->get('cid', array(), 'array');
        
        if (!is_array($ids)) {
            $ids = array($ids);
        }
        
        // Assicuriamoci che tutti gli ID siano interi
        foreach ($ids as &$id) {
            $id = (int)$id;
        }
        
        if (count($ids) < 1) {
            return;
        }

        $db = $this->getDbo();
        try {
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__geofactory_contents'))
                ->where($db->quoteName('type') . ' = ' . $db->quote($type))
                ->where('(' . $db->quoteName('id') . ' IN (' . implode(',', $ids) . ') OR (' 
                      . $db->quoteName('id_content') . ' > 100000000))');
            
            $db->setQuery($query);
            $db->execute();
            
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('COM_GEOFACTORY_DELETE_POSITIONS_SUCCESS', count($ids)),
                'success'
            );
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
        }
    }
}