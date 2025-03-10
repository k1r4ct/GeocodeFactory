<?php
/**
 * @name        Geocode Factory - Content plugin
 * @package     geoFactory
 * @copyright   Copyright © 2013
 * @license     GNU General Public License version 2 or later
 * @author      
 * @website     www.myJoom.com
 * @update      Daniele Bellante
 */

defined('_JEXEC') or die('Direct Access to this location is not allowed.');

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Loader\Loader;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\Event\Event;                       // <-- Import Joomla 4 Event class
use Joomla\Event\DispatcherInterface;

// Registra la classe K2Plugin, se necessaria
Loader::register('K2Plugin', JPATH_ADMINISTRATOR . '/components/com_k2/lib/k2plugin.php');

// Includiamo i file helper necessari
require_once JPATH_ROOT . '/components/com_geofactory/helpers/geofactory.php';
require_once JPATH_SITE . '/components/com_geofactory/helpers/externalMap.php';
require_once JPATH_SITE . '/components/com_geofactory/views/map/view.html.php';
require_once JPATH_ROOT . '/administrator/components/com_geofactory/helpers/geofactory.php';
require_once JPATH_ROOT . '/administrator/components/com_geofactory/models/geocodes.php';

class PlgContentPlg_geofactory_content_jc30 extends CMSPlugin
{
    protected $m_plgCode = 'myjoom_map';
    protected $m_table   = '#__geofactory_contents';

    public function __construct(&$subject, $params)
    {
        parent::__construct($subject, $params);
    }

    public function onContentAfterSave($context, $row, $isNew)
    {
        $c_opt = 'com_content';
        $model = null;
        $idPattern = 0;
        $useJoomlaMeta = $this->params->get('useJoomlaMeta', 0);

        if (strpos($context, 'com_k2') !== false) {
            $c_opt = 'com_k2';
            $plugin = PluginHelper::getPlugin('geocodefactory', 'plg_geofactory_gw_k2');
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
        } elseif (strpos($context, 'com_contact') !== false) {
            $c_opt = 'com_contact';
            $plugin = PluginHelper::getPlugin('geocodefactory', 'plg_geofactory_gw_jcontact10');
            $params = new Registry($plugin->params);
            $idPattern = $params->get('usedpattern');
            $geocodesave = $params->get('geocodesave');

            // Tenta un geocode silenzioso
            if ($idPattern > 0 && $geocodesave > 0) {
                $idsPattern = $this->getListFieldPattern('MS_JCT', $idPattern);
                if ($idsPattern) {
                    // getItemPostalAddressString (evento plugin -> Joomla 4: onGetItemPostalAddressString)
                    $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);
                    $event = new Event('onGetItemPostalAddressString', [
                        // Nel plugin dovrai usare $event->getArgument() per leggere questi param
                        'args' => ['MS_JCT', $row->id, $idsPattern],
                        // Eventualmente un array in cui i plugin aggiungono i risultati
                        'results' => []
                    ]);
                    $event = $dispatcher->dispatch($event);

                    // Recupera i risultati “aggregati”
                    $allResults = $event->getArgument('results', []);
                    $address = (is_array($allResults) && !empty($allResults[0])) ? $allResults[0] : null;

                    $model = new GeofactoryModelGeocodes;
                    $ggUrl = $model->getGoogleGeocodeQuery($address);
                    $coor = $model->geocodeItem($ggUrl);
                    GeofactoryHelper::saveItemContentTale($row->id, $c_opt, $coor[0], $coor[1]);
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
            return;
        }

        // Se l'articolo è nuovo e manca l'ID, gestiamo il salvataggio temporaneo
        $db = Factory::getDbo();
        if ($isNew) {
            $session = Factory::getSession();
            $idTmp = $session->get('gf_temp_art_id', -1);
            if ($idTmp < 1 || $row->id < 1) {
                return;
            }
            $session->clear('gf_temp_art_id');

            $query = $db->getQuery(true)
                        ->select('COUNT(*)')
                        ->from($this->m_table)
                        ->where('id_content='.$idTmp.' AND type='.$db->quote($c_opt));
            $db->setQuery($query);
            $ok = $db->loadResult();
            if ($ok < 1) {
                return;
            }
            $query->clear();
            $query->update($this->m_table)
                  ->set('id_content='.$row->id)
                  ->where('id_content='.$idTmp.' AND type='.$db->quote($c_opt));
            $db->setQuery($query);
            $db->execute();

            // Pulizia dei record troppo vecchi
            $vieux = time() - (3600*24*2);
            $db->setQuery('DELETE FROM '.$this->m_table.' WHERE type='.$db->quote($c_opt).' AND (id_content<'.$vieux.' AND id_content>1000000)');
            $db->execute();
        }
    }

    protected function _K2_getListFieldPattern($id)
    {
        $arrItems = ['field_latitude', 'field_street', 'field_postal', 'field_city', 'field_county', 'field_state', 'field_country'];
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
                    ->select(implode(',', $arrItems))
                    ->from($db->quoteName('#__geofactory_assignation'))
                    ->where("typeList='MS_k2' AND id=".(int)$id);
        $db->setQuery($query);
        $fields = $db->loadObject();

        if (isset($fields->field_latitude) && $fields->field_latitude == -1) {
            return;
        }
        $idFields = [];
        foreach ($arrItems as $ar) {
            if (isset($fields->$ar) && ($fields->$ar > 0)) {
                $idFields[] = $fields->$ar;
            }
        }
        return $idFields;
    }

    protected function getListFieldPattern($typeList, $id)
    {
        $arrItems = ['field_latitude', 'field_street', 'field_postal', 'field_city', 'field_county', 'field_state', 'field_country'];
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
                    ->select(implode(',', $arrItems))
                    ->from($db->quoteName('#__geofactory_assignation'))
                    ->where("typeList=".$db->quote($typeList)." AND id=".$db->quote($id));
        $db->setQuery($query);
        $fields = $db->loadObject();
        if (isset($fields->field_latitude) && $fields->field_latitude == -1) {
            return;
        }
        $idFields = [];
        foreach ($arrItems as $ar) {
            if (!empty($fields->$ar)) {
                $idFields[] = $fields->$ar;
            }
        }
        return $idFields;
    }

    protected function _K2_getK2Addresse($id, $idsPattern)
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
                    ->select('extra_fields')
                    ->from($db->quoteName('#__k2_items'))
                    ->where('id='.(int)$id);
        $db->setQuery($query);
        $ef = $db->loadResult();
        $ef = json_decode($ef);
        $adre = [];
        foreach ($ef as $f) {
            if (in_array($f->id, $idsPattern)) {
                $adre[] = $f->value;
            }
        }
        return $adre;
    }

    protected function _JoomlaContact_getAddress($id, $idsPattern)
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
                    ->select('extra_fields')
                    ->from($db->quoteName('#__contact_details'))
                    ->where('id='.(int)$id);
        $db->setQuery($query);
        $ef = $db->loadResult();
        $ef = json_decode($ef);
        $adre = [];
        foreach ($ef as $f) {
            if (in_array($f->id, $idsPattern)) {
                $adre[] = $f->value;
            }
        }
        return $adre;
    }

    public function onContentPrepare($context, &$article, &$params, $limitstart = 0)
    {
        if ($context == 'com_finder.indexer') {
            return true;
        }

        if ($context == 'com_mtree.category') {
            $app = Factory::getApplication();
            $view = strtolower($app->input->getString("view"));
            $task = strtolower($app->input->getString("task"));

            $listcats = false;
            if ($view == 'listcats' || $task == 'listcats') {
                $listcats = true;
            }

            if (isset($article->link_id) && !$listcats) {
                $session = Factory::getSession();
                $links = $session->get('gf_mt_links', []);
                $reftime = time();
                if (count($links) > 0) {
                    foreach ($links as $time => &$row2) {
                        $rowtime = explode(' ', $time);
                        if (count($rowtime) != 2) {
                            unset($links[$time]);
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

        if (!is_object($article) || !isset($article->id)) {
            return false;
        }

        $c_opt = 'com_content';
        if (strpos($context, 'com_k2') !== false) {
            $c_opt = 'com_k2';
        }

        if (strpos($article->text, '{myjoom_gf') !== false) {
            $regex = '/{myjoom_gf\s+(.*?)}/i';
            $new = '{'.$this->m_plgCode.'}';
            $article->text = preg_replace($regex, $new, $article->text);
            $replace = '{myjoom_gf}';
            $article->text = str_replace($replace, $new, $article->text);
        }

        if (strpos($article->text, $this->m_plgCode) === false) {
            return $article->text;
        }

        $regex = '/{myjoom_map}/i';
        if (!$this->params->get('enabled', 1)) {
            return preg_replace($regex, '', $article->text);
        }

        preg_match_all($regex, $article->text, $matches);
        $count = count($matches[0]);
        if ($count) {
            $lat = 255;
            $lng = 255;
            $this->_loadCoord($article->id, $lat, $lng, $c_opt);
            if (($lat + $lng) == 510) {
                return preg_replace($regex, '', $article->text);
            }
            return $this->_replaceMap($article->text, $count, $regex, $lat, $lng);
        }
        return $article->text;
    }

    protected function _loadCoord($id, &$lat, &$lng, $c_opt)
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
                    ->select('latitude,longitude')
                    ->from($db->quoteName($this->m_table))
                    ->where('id_content='.(int)$id.' AND type='.$db->quote($c_opt));
        $db->setQuery($query, 0, 1);
        $gps = $db->loadObjectList();
        if ($db->getError()) {
            trigger_error("_loadCoord error  :".$db->stderr());
            exit();
        }
        if (count($gps) < 1) {
            return;
        }
        $lat = $gps[0]->latitude;
        $lng = $gps[0]->longitude;
    }

    protected function _replaceMap($text, $count, $regex, $lat, $lng)
    {
        $noMap = $this->params->get('showMap', 0);
        $done = ($noMap == 0) ? true : false;
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
                $res = $map->formatedTemplate;
            } elseif ($noMap == 2) {
                if (($zoom > 17) || ($zoom < 1)) {
                    $zoom = 5;
                }
                $config = ComponentHelper::getParams('com_geofactory');
                $ggApikey = (strlen($config->get('ggApikey')) > 3) ? "&key=".$config->get('ggApikey') : "";
                $width = $this->params->get('staticWidth', 200);
                $height = $this->params->get('staticHeight', 200);
                $res = "http://maps.googleapis.com/maps/api/staticmap?center={$lat},{$lng}&zoom={$zoom}&size={$width}x{$height}&markers={$lat},{$lng}{$ggApikey}";
                $res = '<img src="'.$res.'" >';
            }
            $text = preg_replace($regex, $res, $text, 1);
        }
        return $text;
    }

    public function onContentBeforeSave($context, $article, $isNew)
    {
        if (strpos($context, 'com_easyblog') === false) {
            return true;
        }
        if (strpos($article->fulltext.$article->introtext, '{myjoom_gf') === false) {
            return true;
        }
        $regex = '/{myjoom_gf\s+(.*?)}/i';
        $new = '{'.$this->m_plgCode.'}';
        $article->introtext = preg_replace($regex, $new, $article->introtext);
        $article->fulltext  = preg_replace($regex, $new, $article->fulltext);
        $replace = '{myjoom_gf}';
        $article->introtext = str_replace($replace, $new, $article->introtext);
        $article->fulltext  = str_replace($replace, $new, $article->fulltext);
        return true;
    }
}

