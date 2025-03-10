<?php
/**
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      
 * @website     www.myJoom.com
 * @update      Daniele Bellante
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;

// Prima era "use Joomla\CMS\Registry\Registry;", ora in Joomla 4.4 usiamo il package Framework:
use Joomla\Registry\Registry;

require_once JPATH_ROOT . '/components/com_geofactory/helpers/geofactoryPlugin.php';

if (!class_exists('GeofactoryHelper')) {
    class GeofactoryHelper
    {
        // Carica i dati di base della mappa (soprattutto le coordinate)
        public static function getMap($id)
        {
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                        ->select('a.*')
                        ->from($db->quoteName('#__geofactory_ggmaps', 'a'))
                        ->where('id = ' . (int)$id . ' AND a.state = 1');
            $db->setQuery($query);
            $data = $db->loadObject();
            if (empty($data)) {
                return null;
            }

            // Converte i campi di parametri in oggetti Registry
            self::mergeRegistry($data, "params_map_mouse");
            self::mergeRegistry($data, "params_map_cluster");
            self::mergeRegistry($data, "params_map_radius");
            self::mergeRegistry($data, "params_additional_data");
            self::mergeRegistry($data, "params_map_types");
            self::mergeRegistry($data, "params_map_controls");
            self::mergeRegistry($data, "params_map_settings");
            self::mergeRegistry($data, "params_extra");

            if (!isset($data->kml_file))  { $data->kml_file = ""; }
            if (!isset($data->layers))    { $data->layers = "0"; }

            if (!isset($data->radFormMode))  { $data->radFormMode = 0; }
            if (!isset($data->templateAuto)) { $data->templateAuto = 0; }

            // Definisce i livelli di default
            if (!isset($data->level1) || strlen($data->level1) < 1) { $data->level1 = 'Level 1'; }
            if (!isset($data->level2) || strlen($data->level2) < 1) { $data->level2 = 'Level 2'; }
            if (!isset($data->level3) || strlen($data->level3) < 1) { $data->level3 = 'Level 3'; }
            if (!isset($data->level4) || strlen($data->level4) < 1) { $data->level4 = 'Level 4'; }
            if (!isset($data->level5) || strlen($data->level5) < 1) { $data->level5 = 'Level 5'; }
            if (!isset($data->level6) || strlen($data->level6) < 1) { $data->level6 = 'Level 6'; }

            return $data;
        }

        public static function getMs($id)
        {
            if ($id < 1) {
                throw new \RuntimeException(Text::_('COM_GEOFACTORY_MS_ERROR_ID'), 404);
            }
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                        ->select('a.*')
                        ->from($db->quoteName('#__geofactory_markersets', 'a'))
                        ->where('id = ' . (int)$id . ' AND a.state = 1');
            $db->setQuery($query);
            $data = $db->loadObject();
            if (empty($data) || !isset($data->typeList)) {
                return null;
            }

            PluginHelper::importPlugin('geocodefactory');
            // Utilizzo del dispatcher dell'applicazione
            $dsp = Factory::getApplication()->getDispatcher();
            $pluginOk = false;
            $dsp->trigger('isPluginInstalled', [$data->typeList, &$pluginOk]);
            if (!$pluginOk) {
                return null;
            }

            self::mergeRegistry($data, "params_markerset_settings");
            self::mergeRegistry($data, "params_markerset_radius");
            self::mergeRegistry($data, "params_markerset_icon");
            self::mergeRegistry($data, "params_markerset_type_setting");
            self::mergeRegistry($data, "params_extra");

            $data->name = Text::_($data->name);
            return $data;
        }

        public static function getCoordFields($idFieldAssign)
        {
            $table = Table::getInstance('Assign', 'GeofactoryTable');
            if ($idFieldAssign > 0 && $table->load($idFieldAssign)) {
                return ['lat' => $table->field_latitude, 'lng' => $table->field_longitude];
            }
            return ['lat' => 0, 'lng' => 0];
        }

        public static function getPatternType($idFieldAssign)
        {
            $table = Table::getInstance('Assign', 'GeofactoryTable');
            if ($table->load($idFieldAssign)) {
                return $table->typeList;
            }
            return null;
        }

        /**
         * Unisce i parametri serializzati nel campo $var all'oggetto $data.
         */
        public static function mergeRegistry(&$data, $var)
        {
            $registry = new Registry;
            $registry->loadString($data->$var);
            // Fonde i dati e rimuove la chiave originale
            $data = (object) array_merge((array)$data, $registry->toArray());
            unset($data->$var);
        }

        // Restituisce il nome del file di cache
        public static function getCacheFileName($idMap, $itemid)
        {
            return JPATH_CACHE . DIRECTORY_SEPARATOR . "_geoFactory_{$idMap}_{$itemid}.json";
        }

        // Restituisce un array di ID di markerset collegati alla mappa
        public static function getArrayIdMs($id)
        {
            if ($id < 1) {
                throw new \RuntimeException(Text::_('COM_GEOFACTORY_MAP_ERROR_ID'), 404);
            }

            $key2  = 'ordering';
            $config = ComponentHelper::getParams('com_geofactory');
            if ($config->get('msOrdering') == 1) {
                $key2 = 'name';
            }

            $data = [];
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                        ->select('DISTINCT lmm.id_ms')
                        ->from($db->quoteName('#__geofactory_link_map_ms', 'lmm'))
                        ->join('LEFT', $db->quoteName('#__geofactory_markersets', 'ms') . ' ON lmm.id_ms = ms.id')
                        ->where('id_map = ' . (int)$id)
                        ->where('ms.state = 1')
                        ->order('mslevel, ' . $key2);
            $db->setQuery($query);
            $res = $db->loadObjectList();

            if (!is_array($res) || !count($res)) {
                return $data;
            }
            foreach ($res as $v) {
                if ($v->id_ms < 1) {
                    continue;
                }
                $data[] = $v->id_ms;
            }
            return $data;
        }

        public static function _getSelectorImage($list)
        {
            // Usando Joomla\CMS\Uri\Uri
            $img = Uri::root() . 'media/com_geofactory/assets/baloon.png';
            if (!isset($list->markerIconType)) {
                return $img;
            }
            if (($list->markerIconType < 2) && (strlen($list->customimage) > 3)) {
                $img = Uri::root() . $list->customimage;
            } elseif (($list->markerIconType == 4) && (strlen($list->customimage) > 3)) {
                $img = Uri::root() . $list->customimage;
            } elseif (($list->markerIconType == 4) && (strlen($list->customimage) < 3)) {
                $img = Uri::root() . 'media/com_geofactory/assets/category.png';
            } elseif ($list->markerIconType == 2) {
                $img = Uri::root() . 'media/com_geofactory/mapicons/' . $list->mapicon;
            } elseif ($list->markerIconType == 3) {
                $img = Uri::root() . 'media/com_geofactory/assets/avatar.png';
            }
            return $img;
        }

        public static function saveItemContentTale($id, $type, $lat, $lng, $adr = '')
        {
            $db = Factory::getDbo();
            $cond = 'type = ' . $db->quote($type) . ' AND id_content = ' . (int)$id;

            $query = $db->getQuery(true)
                        ->select('COUNT(*)')
                        ->from($db->quoteName('#__geofactory_contents'))
                        ->where($cond);
            $db->setQuery($query);
            $update = $db->loadResult();
            $query->clear();

            if ((int)$update > 0) {
                $fields = [
                    'latitude = ' . (float)$lat,
                    'longitude = ' . (float)$lng,
                    'address = ' . $db->quote($adr)
                ];
                $query->update($db->quoteName('#__geofactory_contents'))
                      ->set($fields)
                      ->where($cond);
            } else {
                $values = [
                    $db->quote(''),
                    $db->quote($type),
                    (int)$id,
                    $db->quote($adr),
                    (float)$lat,
                    (float)$lng
                ];
                $query->insert($db->quoteName('#__geofactory_contents'))
                      ->values(implode(',', $values));
            }
            $db->setQuery($query);
            $db->execute();
        }

        public static function isDebugMode()
        {
            $config = ComponentHelper::getParams('com_geofactory');
            if ((bool)$config->get('isDebug')) {
                return true;
            }
            $app = Factory::getApplication('site');
            if ((bool)$app->input->getInt('gf_debug', false)) {
                return true;
            }
            return false;
        }

        public static function markerInArea($marker, $vp)
        {
            if ($marker['lat'] < $vp[0]) return false;
            if ($marker['lng'] < $vp[1]) return false;
            if ($marker['lat'] >= $vp[2]) return false;
            if ($marker['lng'] >= $vp[3]) return false;
            return true;
        }

        public static function useNewMethod($map)
        {
            $config = ComponentHelper::getParams('com_geofactory');
            if ((int)$config->get('newMethod') < 1) {
                return false;
            }
            if ((int)$config->get('newMethod') == 2) {
                return true;
            }
            if ((int)$map->centerUser > 0) {
                return false;
            }
            return true;
        }
    }
}
