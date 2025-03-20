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
use Joomla\CMS\Cache\Cache;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\Event;
use Joomla\Registry\Registry;

require_once JPATH_ROOT . '/components/com_geofactory/helpers/geofactoryPlugin.php';

if (!class_exists('GeofactoryHelper')) {
    //error_log('GeofactoryHelper: Inizializzazione classe helper');
    
    class GeofactoryHelper
    {
        /**
         * Carica i dati di base della mappa (soprattutto le coordinate)
         *
         * @param   int  $id  ID della mappa
         * @return  object|null  Oggetto mappa o null se non trovato
         * @since   1.0
         */
        public static function getMap(int $id): ?object
        {
            //error_log('GeofactoryHelper: getMap chiamato con ID=' . $id);
            
            try {
                $db = Factory::getContainer()->get(DatabaseInterface::class);
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

                //error_log('GeofactoryHelper: getMap - Completato con successo');
                return $data;
            } catch (\Exception $e) {
                //error_log('GeofactoryHelper: getMap - ERRORE: ' . $e->getMessage());
                return null;
            }
        }

        /**
         * Recupera un markerset per ID
         *
         * @param   int  $id  ID del markerset
         * @return  object|null  Oggetto markerset o null se non trovato
         * @since   1.0
         * @throws  \RuntimeException
         */
        public static function getMs(int $id): ?object
        {
            //error_log('GeofactoryHelper: getMs chiamato con ID=' . $id);
            
            if ($id < 1) {
                throw new \RuntimeException(Text::_('COM_GEOFACTORY_MS_ERROR_ID'), 404);
            }
            
            try {
                $db = Factory::getContainer()->get(DatabaseInterface::class);
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
                $app = Factory::getApplication();
                $dispatcher = $app->getDispatcher();
                $pluginOk = false;
                
                // Creazione dell'evento con passaggio per riferimento del parametro pluginOk
                $event = new Event('onIsPluginInstalled', [
                    'typeList' => $data->typeList,
                    'pluginOk' => &$pluginOk
                ]);
                
                // Dispatch dell'evento
                $results = $dispatcher->dispatch('onIsPluginInstalled', $event);
                
                // Verifica se pluginOk è stato modificato a true dai plugin
                if (!$pluginOk) {
                    return null;
                }

                self::mergeRegistry($data, "params_markerset_settings");
                self::mergeRegistry($data, "params_markerset_radius");
                self::mergeRegistry($data, "params_markerset_icon");
                self::mergeRegistry($data, "params_markerset_type_setting");
                self::mergeRegistry($data, "params_extra");

                $data->name = Text::_($data->name);
                
                //error_log('GeofactoryHelper: getMs - Completato con successo');
                return $data;
            } catch (\Exception $e) {
                //error_log('GeofactoryHelper: getMs - ERRORE: ' . $e->getMessage());
                throw $e;
            }
        }

        /**
         * Recupera i campi di coordinate da un campo di assegnazione
         *
         * @param   int  $idFieldAssign  ID del campo di assegnazione
         * @return  array  Array con i campi latitudine e longitudine
         * @since   1.0
         */
        public static function getCoordFields(int $idFieldAssign): array
        {
            //error_log('GeofactoryHelper: getCoordFields chiamato con ID=' . $idFieldAssign);
            
            // Prima cerchiamo di usare il namespace completo
            $table = null;
            
            // Se fallisce, usiamo l'approccio classico
            try {
                $table = Table::getInstance('Assign', '\\Geofactory\\Component\\Geofactory\\Administrator\\Table\\');
            } catch (\Exception $e) {
                $table = Table::getInstance('Assign', 'GeofactoryTable');
            }
            
            if ($table && $idFieldAssign > 0 && $table->load($idFieldAssign)) {
                //error_log('GeofactoryHelper: getCoordFields - Completato con successo');
                return ['lat' => $table->field_latitude, 'lng' => $table->field_longitude];
            }
            
            //error_log('GeofactoryHelper: getCoordFields - Completato senza dati');
            return ['lat' => 0, 'lng' => 0];
        }

        /**
         * Recupera il tipo di pattern da un campo di assegnazione
         *
         * @param   int  $idFieldAssign  ID del campo di assegnazione
         * @return  string|null  Tipo di pattern o null se non trovato
         * @since   1.0
         */
        public static function getPatternType(int $idFieldAssign): ?string
        {
            //error_log('GeofactoryHelper: getPatternType chiamato con ID=' . $idFieldAssign);
            
            $table = null;
            try {
                $table = Table::getInstance('Assign', '\\Geofactory\\Component\\Geofactory\\Administrator\\Table\\');
            } catch (\Exception $e) {
                $table = Table::getInstance('Assign', 'GeofactoryTable');
            }
            
            if ($table && $table->load($idFieldAssign)) {
                //error_log('GeofactoryHelper: getPatternType - Completato con successo');
                return $table->typeList;
            }
            
            //error_log('GeofactoryHelper: getPatternType - Completato senza dati');
            return null;
        }

        /**
         * Unisce i parametri serializzati nel campo $var all'oggetto $data.
         *
         * @param   object  &$data  Oggetto a cui aggiungere i parametri
         * @param   string  $var    Nome del campo che contiene i parametri serializzati
         * @return  void
         * @since   1.0
         */
        public static function mergeRegistry(object &$data, string $var): void
        {
            //error_log('GeofactoryHelper: mergeRegistry chiamato per campo ' . $var);
            
            try {
                $registry = new Registry;
                
                // Verifica che il campo esista e non sia null
                if (property_exists($data, $var) && $data->$var !== null) {
                    try {
                        $registry->loadString($data->$var);
                        
                        // Fonde i dati e rimuove la chiave originale
                        $registryArray = $registry->toArray();
                        
                        $data = (object) array_merge((array)$data, $registryArray);
                        unset($data->$var);
                    } catch (\Exception $e) {
                        //error_log('GeofactoryHelper: mergeRegistry - ERRORE durante loadString: ' . $e->getMessage());
                    }
                }
            } catch (\Exception $e) {
                //error_log('GeofactoryHelper: mergeRegistry - ERRORE GENERALE: ' . $e->getMessage());
            }
            
            //error_log('GeofactoryHelper: mergeRegistry - Completato');
        }

        /**
         * Restituisce il nome del file di cache
         *
         * @param   int  $idMap   ID della mappa
         * @param   int  $itemid  ID della voce di menu
         * @param   int  $type    Tipo del file (1 = JSON)
         * @return  string  Percorso completo del file di cache
         * @since   1.0
         */
        public static function getCacheFileName(int $idMap, int $itemid, int $type = 0): string
        {
            //error_log('GeofactoryHelper: getCacheFileName chiamato');
            
            $ext = ($type === 1) ? 'json' : 'xml';
            $cacheFile = JPATH_CACHE . DIRECTORY_SEPARATOR . "_geofFactory_{$idMap}_{$itemid}.{$ext}";
            
            //error_log('GeofactoryHelper: getCacheFileName - Completato');
            return $cacheFile;
        }

        /**
         * Restituisce un array di ID di markerset collegati alla mappa
         *
         * @param   int  $id  ID della mappa
         * @return  array  Array di ID markerset
         * @since   1.0
         * @throws  \RuntimeException
         */
        public static function getArrayIdMs(int $id): array
        {
            //error_log('GeofactoryHelper: getArrayIdMs chiamato con ID=' . $id);
            
            if ($id < 1) {
                throw new \RuntimeException(Text::_('COM_GEOFACTORY_MAP_ERROR_ID'), 404);
            }

            try {
                $key2  = 'ordering';
                $config = ComponentHelper::getParams('com_geofactory');
                if ($config->get('msOrdering') == 1) {
                    $key2 = 'name';
                }

                $data = [];
                $db = Factory::getContainer()->get(DatabaseInterface::class);
                $query = $db->getQuery(true)
                            ->select('DISTINCT lmm.id_ms')
                            ->from($db->quoteName('#__geofactory_link_map_ms', 'lmm'))
                            ->join('LEFT', $db->quoteName('#__geofactory_markersets', 'ms') . ' ON lmm.id_ms = ms.id')
                            ->where('id_map = ' . (int)$id)
                            ->where('ms.state = 1')
                            ->order('mslevel, ' . $key2);
                
                $db->setQuery($query);
                $res = $db->loadObjectList();

                if (!is_array($res) || count($res) < 1) {
                    //error_log('GeofactoryHelper: getArrayIdMs - Completato senza risultati');
                    return $data;
                }
                
                foreach ($res as $v) {
                    if (!isset($v->id_ms) || $v->id_ms < 1) {
                        continue;
                    }
                    $data[] = $v->id_ms;
                }
                
                //error_log('GeofactoryHelper: getArrayIdMs - Completato con ' . count($data) . ' risultati');
                return $data;
            } catch (\Exception $e) {
                //error_log('GeofactoryHelper: getArrayIdMs - ERRORE: ' . $e->getMessage());
                return [];
            }
        }

        /**
         * Restituisce l'immagine selettore per un markerset
         *
         * @param   object  $list  Oggetto markerset
         * @return  string  URL dell'immagine
         * @since   1.0
         */
        public static function _getSelectorImage(object $list): string
        {
            //error_log('GeofactoryHelper: _getSelectorImage chiamato');
            
            // Usando Joomla\CMS\Uri\Uri
            $img = Uri::root() . 'media/com_geofactory/assets/baloon.png';
            
            if (!isset($list->markerIconType)) {
                //error_log('GeofactoryHelper: _getSelectorImage - Completato con default');
                return $img;
            }
            
            if (($list->markerIconType < 2) && (isset($list->customimage) && strlen($list->customimage) > 3)) {
                $img = Uri::root() . $list->customimage;
            } elseif (($list->markerIconType == 4) && (isset($list->customimage) && strlen($list->customimage) > 3)) {
                $img = Uri::root() . $list->customimage;
            } elseif (($list->markerIconType == 4) && (!isset($list->customimage) || strlen($list->customimage) < 3)) {
                $img = Uri::root() . 'media/com_geofactory/assets/category.png';
            } elseif ($list->markerIconType == 2 && isset($list->mapicon)) {
                $img = Uri::root() . 'media/com_geofactory/mapicons/' . $list->mapicon;
            } elseif ($list->markerIconType == 3) {
                $img = Uri::root() . 'media/com_geofactory/assets/avatar.png';
            }
            
            //error_log('GeofactoryHelper: _getSelectorImage - Completato');
            return $img;
        }

        /**
         * Salva le coordinate di un contenuto
         *
         * @param   int     $id     ID del contenuto
         * @param   string  $type   Tipo di contenuto
         * @param   float   $lat    Latitudine
         * @param   float   $lng    Longitudine
         * @param   string  $adr    Indirizzo (opzionale)
         * @return  void
         * @since   1.0
         */
        public static function saveItemContentTale(int $id, string $type, float $lat, float $lng, string $adr = ''): void
        {
            //error_log('GeofactoryHelper: saveItemContentTale chiamato');
            
            $db = Factory::getContainer()->get(DatabaseInterface::class);
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
                $columns = ['uid', 'type', 'id_content', 'address', 'latitude', 'longitude'];
                $values = [
                    $db->quote(''),
                    $db->quote($type),
                    (int)$id,
                    $db->quote($adr),
                    (float)$lat,
                    (float)$lng
                ];
                $query->insert($db->quoteName('#__geofactory_contents'))
                      ->columns($db->quoteName($columns))
                      ->values(implode(',', $values));
            }
            $db->setQuery($query);
            $db->execute();
            
            //error_log('GeofactoryHelper: saveItemContentTale - Completato');
        }

        /**
         * Verifica se la modalità debug è attiva
         *
         * @return  bool  True se la modalità debug è attiva
         * @since   1.0
         */
        public static function isDebugMode(): bool
        {
            //error_log('GeofactoryHelper: isDebugMode chiamato');
            
            try {
                $config = ComponentHelper::getParams('com_geofactory');
                $configDebug = (bool)$config->get('isDebug');
                
                if ($configDebug) {
                    //error_log('GeofactoryHelper: isDebugMode - Completato (true da config)');
                    return true;
                }
                
                $app = Factory::getApplication();
                $inputDebug = (bool)$app->input->getInt('gf_debug', false);
                
                if ($inputDebug) {
                    //error_log('GeofactoryHelper: isDebugMode - Completato (true da URL)');
                    return true;
                }
                
                //error_log('GeofactoryHelper: isDebugMode - Completato (false)');
                return false;
            } catch (\Exception $e) {
                //error_log('GeofactoryHelper: isDebugMode - ERRORE: ' . $e->getMessage());
                return false;
            }
        }

        /**
         * Verifica se un marker è all'interno di un'area
         *
         * @param   array  $marker  Dati del marker
         * @param   array  $vp      Coordinate dell'area (viewport)
         * @return  bool   True se il marker è nell'area
         * @since   1.0
         */
        public static function markerInArea(array $marker, array $vp): bool
        {
            //error_log('GeofactoryHelper: markerInArea chiamato');
            
            // Verifichiamo che tutte le chiavi necessarie esistano nel marker
            if (!isset($marker['lat']) || !isset($marker['lng'])) {
                //error_log('GeofactoryHelper: markerInArea - Completato (false, marker invalido)');
                return false;
            }
            
            // Verifichiamo che l'array $vp abbia almeno 4 elementi
            if (count($vp) < 4) {
                //error_log('GeofactoryHelper: markerInArea - Completato (false, viewport invalido)');
                return false;
            }
            
            if ($marker['lat'] < $vp[0]) {
                //error_log('GeofactoryHelper: markerInArea - Completato (false)');
                return false;
            }
            if ($marker['lng'] < $vp[1]) {
                //error_log('GeofactoryHelper: markerInArea - Completato (false)');
                return false;
            }
            if ($marker['lat'] >= $vp[2]) {
                //error_log('GeofactoryHelper: markerInArea - Completato (false)');
                return false;
            }
            if ($marker['lng'] >= $vp[3]) {
                //error_log('GeofactoryHelper: markerInArea - Completato (false)');
                return false;
            }
            
            //error_log('GeofactoryHelper: markerInArea - Completato (true)');
            return true;
        }

        /**
         * Verifica se usare il nuovo metodo
         *
         * @param   object  $map  Oggetto mappa
         * @return  bool    True se usare il nuovo metodo
         * @since   1.0
         */
        public static function useNewMethod(object $map): bool
        {
            //error_log('GeofactoryHelper: useNewMethod chiamato');
            
            try {
                $config = ComponentHelper::getParams('com_geofactory');
                $newMethodSetting = (int)$config->get('newMethod');
                
                if ($newMethodSetting < 1) {
                    //error_log('GeofactoryHelper: useNewMethod - Completato (false, disabilitato in config)');
                    return false;
                }
                
                if ($newMethodSetting == 2) {
                    //error_log('GeofactoryHelper: useNewMethod - Completato (true, forzato in config)');
                    return true;
                }
                
                $centerUserValue = isset($map->centerUser) ? (int)$map->centerUser : 0;
                
                if ($centerUserValue > 0) {
                    //error_log('GeofactoryHelper: useNewMethod - Completato (false, centerUser attivo)');
                    return false;
                }
                
                //error_log('GeofactoryHelper: useNewMethod - Completato (true, default)');
                return true;
            } catch (\Exception $e) {
                //error_log('GeofactoryHelper: useNewMethod - ERRORE: ' . $e->getMessage());
                return false;
            }
            return true;
        }
    }
    
    //error_log('GeofactoryHelper: Classe helper definita con successo');
}
