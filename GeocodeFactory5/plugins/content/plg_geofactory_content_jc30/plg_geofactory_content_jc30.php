<?php
/**
 * @name        Geocode Factory - Content plugin
 * @package     geoFactory
 * @copyright   Copyright © 2013-2023
 * @license     GNU General Public License version 2 or later
 * @author      
 * @website     www.myJoom.com
 * @update      Daniele Bellante, Aggiornato per Joomla 4.4.10
 */

defined('_JEXEC') or die('Direct Access to this location is not allowed.');

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Loader\Loader;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\Event\Event;
use Joomla\Event\DispatcherInterface;
use Joomla\Database\DatabaseInterface;

// Registra la classe K2Plugin, se necessaria
if (file_exists(JPATH_ADMINISTRATOR . '/components/com_k2/lib/k2plugin.php')) {
    Loader::register('K2Plugin', JPATH_ADMINISTRATOR . '/components/com_k2/lib/k2plugin.php');
}

// Includiamo i file helper necessari
require_once JPATH_ROOT . '/components/com_geofactory/helpers/geofactory.php';
require_once JPATH_SITE . '/components/com_geofactory/helpers/externalMap.php';
require_once JPATH_SITE . '/components/com_geofactory/views/map/view.html.php';
require_once JPATH_ROOT . '/administrator/components/com_geofactory/helpers/geofactory.php';
require_once JPATH_ROOT . '/administrator/components/com_geofactory/models/geocodes.php';

class PlgContentPlg_geofactory_content_jc30 extends CMSPlugin
{
    /**
     * @var string  Il codice del plugin per i pattern di sostituzione
     */
    protected $m_plgCode = 'myjoom_map';
    
    /**
     * @var string  La tabella del database usata per salvare le coordinate
     */
    protected $m_table   = '#__geofactory_contents';

    /**
     * Constructor
     *
     * @param   object  &$subject  The object to observe
     * @param   array   $config    An optional associative array of configuration settings.
     */
    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        
        // Carica il linguaggio
        $this->loadLanguage();
    }

    /**
     * Metodo eseguito dopo il salvataggio di un contenuto.
     *
     * @param   string   $context  Il contesto del contenuto
     * @param   object   $row      L'oggetto contenuto
     * @param   boolean  $isNew    Se è un nuovo contenuto
     * @return  boolean  Sempre true
     */
    public function onContentAfterSave($context, $row, $isNew)
    {
        $c_opt = 'com_content';
        $model = null;
        $idPattern = 0;
        $useJoomlaMeta = $this->params->get('useJoomlaMeta', 0);

        if (strpos($context, 'com_k2') !== false) {
            $c_opt = 'com_k2';
            $plugin = PluginHelper::getPlugin('geocodefactory', 'plg_geofactory_gw_k2');
            if ($plugin) {
                $params = new Registry($plugin->params);
                $idPattern = $params->get('usedpattern');
                $geocodesave = $params->get('geocodesave');

                // Tenta un geocode silenzioso
                if ($idPattern > 0 && $geocodesave > 0) {
                    $idsPattern = $this->_K2_getListFieldPattern($idPattern);
                    if ($idsPattern) {
                        $adresse = $this->_K2_getK2Addresse($row->id, $idsPattern);
                        $model = new GeofactoryModelGeocodes;
                        $ggUrl = $model->getGoogleGeocodeQuery($adresse);
                        $coor = $model->geocodeItem($ggUrl);
                        GeofactoryHelper::saveItemContentTale($row->id, $c_opt, $coor[0], $coor[1]);
                    }
                }
            }
        } elseif (strpos($context, 'com_contact') !== false) {
            $c_opt = 'com_contact';
            $plugin = PluginHelper::getPlugin('geocodefactory', 'plg_geofactory_gw_jcontact10');
            if ($plugin) {
                $params = new Registry($plugin->params);
                $idPattern = $params->get('usedpattern');
                $geocodesave = $params->get('geocodesave');

                // Tenta un geocode silenzioso
                if ($idPattern > 0 && $geocodesave > 0) {
                    $idsPattern = $this->getListFieldPattern('MS_JCT', $idPattern);
                    if ($idsPattern) {
                        // getItemPostalAddressString (evento plugin -> Joomla 4: onGetItemPostalAddressString)
                        $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);
                        $results = [];
                        
                        $event = new Event('onGetItemPostalAddressString', [
                            'args' => ['MS_JCT', $row->id, $idsPattern],
                            'results' => &$results
                        ]);
                        
                        $dispatcher->dispatch($event->getName(), $event);

                        // Recupera i risultati
                        $address = (is_array($results) && !empty($results[0])) ? $results[0] : null;

                        if ($address) {
                            $model = new GeofactoryModelGeocodes;
                            $ggUrl = $model->getGoogleGeocodeQuery($address);
                            $coor = $model->geocodeItem($ggUrl);
                            GeofactoryHelper::saveItemContentTale($row->id, $c_opt, $coor[0], $coor[1]);
                        }
                    }
                }
            }
        } elseif ((strpos($context, 'com_content') !== false) && $useJoomlaMeta > 0) {
            $adresse = null;
            if ($useJoomlaMeta == 1) {
                $adresse = trim($row->metakey);
            }
            if ($useJoomlaMeta == 2) {
                $adresse = trim($row->metadesc);
            }
            if (!empty($adresse)) {
                $model = new GeofactoryModelGeocodes;
                $ggUrl = $model->getGoogleGeocodeQuery($adresse);
                $coor = $model->geocodeItem($ggUrl);
                GeofactoryHelper::saveItemContentTale($row->id, $c_opt, $coor[0], $coor[1]);
            }
            return true;
        }

        // Se l'articolo è nuovo e manca l'ID, gestiamo il salvataggio temporaneo
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        if ($isNew) {
            $session = Factory::getApplication()->getSession();
            $idTmp = $session->get('gf_temp_art_id', -1);
            if ($idTmp < 1 || $row->id < 1) {
                return true;
            }
            $session->clear('gf_temp_art_id');

            $query = $db->getQuery(true)
                        ->select('COUNT(*)')
                        ->from($this->m_table)
                        ->where('id_content = ' . (int)$idTmp . ' AND type = ' . $db->quote($c_opt));
            $db->setQuery($query);
            $ok = $db->loadResult();
            if ($ok < 1) {
                return true;
            }
            
            $query->clear()
                  ->update($this->m_table)
                  ->set('id_content = ' . (int)$row->id)
                  ->where('id_content = ' . (int)$idTmp . ' AND type = ' . $db->quote($c_opt));
            $db->setQuery($query);
            $db->execute();

            // Pulizia dei record troppo vecchi
            $vieux = time() - (3600*24*2);
            $query->clear()
                  ->delete($this->m_table)
                  ->where('type = ' . $db->quote($c_opt) . ' AND (id_content < ' . $vieux . ' AND id_content > 1000000)');
            $db->setQuery($query);
            $db->execute();
        }
        
        return true;
    }

    /**
     * Ottiene la lista dei campi del pattern K2
     *
     * @param   int  $id  ID del pattern
     * @return  array|null  Array di ID dei campi o null se non trovato
     */
    protected function _K2_getListFieldPattern($id)
    {
        $arrItems = ['field_latitude', 'field_street', 'field_postal', 'field_city', 'field_county', 'field_state', 'field_country'];
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
                    ->select(implode(',', $arrItems))
                    ->from($db->quoteName('#__geofactory_assignation'))
                    ->where("typeList = 'MS_k2' AND id = " . (int)$id);
        $db->setQuery($query);
        $fields = $db->loadObject();

        if (!$fields || (isset($fields->field_latitude) && $fields->field_latitude == -1)) {
            return null;
        }
        
        $idFields = [];
        foreach ($arrItems as $ar) {
            if (isset($fields->$ar) && ($fields->$ar > 0)) {
                $idFields[] = $fields->$ar;
            }
        }
        
        return !empty($idFields) ? $idFields : null;
    }

    /**
     * Ottiene la lista dei campi del pattern per un tipo specificato
     *
     * @param   string  $typeList  Tipo di lista
     * @param   int     $id        ID del pattern
     * @return  array|null  Array di ID dei campi o null se non trovato
     */
    protected function getListFieldPattern($typeList, $id)
    {
        $arrItems = ['field_latitude', 'field_street', 'field_postal', 'field_city', 'field_county', 'field_state', 'field_country'];
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
                    ->select(implode(',', $arrItems))
                    ->from($db->quoteName('#__geofactory_assignation'))
                    ->where("typeList = " . $db->quote($typeList) . " AND id = " . $db->quote($id));
        $db->setQuery($query);
        $fields = $db->loadObject();
        
        if (!$fields || (isset($fields->field_latitude) && $fields->field_latitude == -1)) {
            return null;
        }
        
        $idFields = [];
        foreach ($arrItems as $ar) {
            if (!empty($fields->$ar)) {
                $idFields[] = $fields->$ar;
            }
        }
        
        return !empty($idFields) ? $idFields : null;
    }

    /**
     * Ottiene l'indirizzo da un elemento K2
     *
     * @param   int    $id          ID dell'elemento
     * @param   array  $idsPattern  Array di ID dei campi
     * @return  array  Array di parti dell'indirizzo
     */
    protected function _K2_getK2Addresse($id, $idsPattern)
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
                    ->select('extra_fields')
                    ->from($db->quoteName('#__k2_items'))
                    ->where('id = ' . (int)$id);
        $db->setQuery($query);
        $ef = $db->loadResult();
        
        if (!$ef) {
            return [];
        }
        
        $ef = json_decode($ef);
        $adre = [];
        
        if (!is_array($ef)) {
            return $adre;
        }
        
        foreach ($ef as $f) {
            if (in_array($f->id, $idsPattern)) {
                $adre[] = $f->value;
            }
        }
        
        return $adre;
    }

    /**
     * Ottiene l'indirizzo da un contatto Joomla
     *
     * @param   int    $id          ID del contatto
     * @param   array  $idsPattern  Array di ID dei campi
     * @return  array  Array di parti dell'indirizzo
     */
    protected function _JoomlaContact_getAddress($id, $idsPattern)
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
                    ->select('extra_fields')
                    ->from($db->quoteName('#__contact_details'))
                    ->where('id = ' . (int)$id);
        $db->setQuery($query);
        $ef = $db->loadResult();
        
        if (!$ef) {
            return [];
        }
        
        $ef = json_decode($ef);
        $adre = [];
        
        if (!is_array($ef)) {
            return $adre;
        }
        
        foreach ($ef as $f) {
            if (in_array($f->id, $idsPattern)) {
                $adre[] = $f->value;
            }
        }
        
        return $adre;
    }

    /**
     * Evento triggerato durante la preparazione del contenuto
     *
     * @param   string   $context   Il contesto del contenuto
     * @param   object   &$article  L'oggetto articolo
     * @param   object   &$params   I parametri dell'articolo
     * @param   integer  $limitstart  Offset per la paginazione
     * @return  boolean|string  True per continuare o il testo modificato
     */
    public function onContentPrepare($context, &$article, &$params, $limitstart = 0)
    {
        // Non esegue quando si indicizza
        if ($context === 'com_finder.indexer') {
            return true;
        }

        // Gestione del contesto speciale com_mtree
        if ($context === 'com_mtree.category') {
            $app = Factory::getApplication();
            $view = strtolower($app->input->getString("view"));
            $task = strtolower($app->input->getString("task"));

            $listcats = ($view === 'listcats' || $task === 'listcats');

            if (isset($article->link_id) && !$listcats) {
                $session = Factory::getSession();
                $links = $session->get('gf_mt_links', []);
                $reftime = time();
                
                if (count($links) > 0) {
                    foreach ($links as $time => &$row2) {
                        $rowtime = explode(' ', $time);
                        if (count($rowtime) != 2) {
                            unset($links[$time]);
                            continue;
                        }
                        if (($reftime - $rowtime[1]) > 1) {
                            unset($links[$time]);
                        }
                    }
                }
                
                if (!in_array($article->link_id, $links)) {
                    $links[microtime()] = $article->link_id;
                }
                
                $session->set('gf_mt_links', $links);
            }
        }

        // Verifica che l'articolo sia un oggetto con id
        if (!is_object($article) || !isset($article->id)) {
            return false;
        }

        // Determina il componente
        $c_opt = 'com_content';
        if (strpos($context, 'com_k2') !== false) {
            $c_opt = 'com_k2';
        }

        // Gestisce la sostituzione per le versioni precedenti del tag
        if (isset($article->text) && strpos($article->text, '{myjoom_gf') !== false) {
            $regex = '/{myjoom_gf\s+(.*?)}/i';
            $new = '{' . $this->m_plgCode . '}';
            $article->text = preg_replace($regex, $new, $article->text);
            $replace = '{myjoom_gf}';
            $article->text = str_replace($replace, $new, $article->text);
        }

        // Verifica se c'è il codice nel testo
        if (!isset($article->text) || strpos($article->text, $this->m_plgCode) === false) {
            return true;
        }

        // Pattern per trovare il codice
        $regex = '/{myjoom_map}/i';

        // Se il plugin è disabilitato, rimuove il tag
        if (!$this->params->get('enabled', 1)) {
            $article->text = preg_replace($regex, '', $article->text);
            return true;
        }

        // Cerca le occorrenze
        preg_match_all($regex, $article->text, $matches);
        $count = count($matches[0]);
        
        if ($count) {
            $lat = 255;
            $lng = 255;
            $this->_loadCoord($article->id, $lat, $lng, $c_opt);
            
            if (($lat + $lng) == 510) {
                $article->text = preg_replace($regex, '', $article->text);
                return true;
            }
            
            $article->text = $this->_replaceMap($article->text, $count, $regex, $lat, $lng);
        }
        
        return true;
    }

    /**
     * Carica le coordinate per un articolo
     *
     * @param   int     $id     ID dell'articolo
     * @param   float   &$lat   Latitudine (referenza)
     * @param   float   &$lng   Longitudine (referenza)
     * @param   string  $c_opt  Opzione componente
     * @return  void
     */
    protected function _loadCoord($id, &$lat, &$lng, $c_opt)
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
                    ->select('latitude, longitude')
                    ->from($db->quoteName($this->m_table))
                    ->where('id_content = ' . (int)$id . ' AND type = ' . $db->quote($c_opt));
        $db->setQuery($query, 0, 1);
        
        try {
            $gps = $db->loadObject();
            
            if ($gps) {
                $lat = $gps->latitude;
                $lng = $gps->longitude;
            }
        } catch (\Exception $e) {
            // Log dell'errore
            $app = Factory::getApplication();
            $app->enqueueMessage('_loadCoord error: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Sostituisce il tag map con il codice HTML della mappa
     *
     * @param   string  $text    Testo dell'articolo
     * @param   int     $count   Numero di occorrenze
     * @param   string  $regex   Pattern regex
     * @param   float   $lat     Latitudine
     * @param   float   $lng     Longitudine
     * @return  string  Testo con le mappe sostituite
     */
    protected function _replaceMap($text, $count, $regex, $lat, $lng)
    {
        $noMap = $this->params->get('showMap', 0);
        $done = ($noMap == 0);
        
        for ($i = 0; $i < $count; $i++) {
            if ($done) {
                $text = preg_replace($regex, '', $text);
                continue;
            }
            
            $idMap = $this->params->get('idMap', 0);
            $zoom = $this->params->get('staticZoom', 0);
            $done = true;
            $res = "";
            
            if ($noMap == 1) {
                $map = GeofactoryExternalMapHelper::getMap($idMap, 'jp', $zoom);
                if ($map) {
                    $res = $map->formatedTemplate;
                }
            } elseif ($noMap == 2) {
                if (($zoom > 17) || ($zoom < 1)) {
                    $zoom = 5;
                }
                
                $config = ComponentHelper::getParams('com_geofactory');
                $ggApikey = (strlen($config->get('ggApikey')) > 3) ? "&key=" . $config->get('ggApikey') : "";
                $width = $this->params->get('staticWidth', 200);
                $height = $this->params->get('staticHeight', 200);
                $res = "https://maps.googleapis.com/maps/api/staticmap?center={$lat},{$lng}&zoom={$zoom}&size={$width}x{$height}&markers={$lat},{$lng}{$ggApikey}";
                $res = '<img src="' . $res . '" alt="Map" class="img-fluid">';
            }
            
            $text = preg_replace($regex, $res, $text, 1);
        }
        
        return $text;
    }

    /**
     * Evento prima del salvataggio del contenuto per EasyBlog
     *
     * @param   string   $context  Il contesto del contenuto
     * @param   object   $article  L'oggetto articolo
     * @param   boolean  $isNew    Se è un nuovo articolo
     * @return  boolean  True per continuare
     */
    public function onContentBeforeSave($context, $article, $isNew)
    {
        if (strpos($context, 'com_easyblog') === false) {
            return true;
        }
        
        // Verifica se il tag è presente nel testo
        $fulltext = isset($article->fulltext) ? $article->fulltext : '';
        $introtext = isset($article->introtext) ? $article->introtext : '';
        
        if (strpos($fulltext . $introtext, '{myjoom_gf') === false) {
            return true;
        }
        
        // Sostituisci i tag vecchi con i nuovi
        $regex = '/{myjoom_gf\s+(.*?)}/i';
        $new = '{' . $this->m_plgCode . '}';
        $article->introtext = preg_replace($regex, $new, $introtext);
        $article->fulltext = preg_replace($regex, $new, $fulltext);
        
        $replace = '{myjoom_gf}';
        $article->introtext = str_replace($replace, $new, $article->introtext);
        $article->fulltext = str_replace($replace, $new, $article->fulltext);
        
        return true;
    }
}