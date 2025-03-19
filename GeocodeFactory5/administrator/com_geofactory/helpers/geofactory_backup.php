<?php
/**
 * @name        Geocode Factory (Admin Helper)
 * @package     geoFactory
 * @copyright   Copyright © 2013
 * @license     GNU General Public License version 2 or later
 * @author      ...
 * @website     ...
 * @update      Daniele Bellante
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Filesystem\Path;
use Joomla\Database\DatabaseInterface;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Access\Rules;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\HTML\Sidebar;
use Joomla\CMS\Object\CMSObject;
use RuntimeException;

// Includiamo i file helper necessari
require_once JPATH_SITE . '/components/com_geofactory/helpers/geofactoryPlugin.php';
require_once JPATH_ADMINISTRATOR . '/components/com_geofactory/helpers/geofactoryUpdater.php';
require_once JPATH_ADMINISTRATOR . '/components/com_geofactory/tables/assign.php';

/**
 * Classe helper per la parte amministrativa di Geocode Factory
 */
class GeofactoryHelperAdm
{
    /**
     * Configura la sidebar amministrativa
     *
     * @param   string  $vName  Nome della view attiva
     */
    public static function addSubmenu($vName = 'geofactory')
    {
        Sidebar::addEntry(
            Text::_('COM_GEOFACTORY_MENU_CPANEL'),
            'index.php?option=com_geofactory&view=accueil',
            $vName === 'accueil'
        );
        Sidebar::addEntry(
            Text::_('COM_GEOFACTORY_MENU_MAPS_MANAGER'),
            'index.php?option=com_geofactory&view=ggmaps',
            $vName === 'ggmaps'
        );
        Sidebar::addEntry(
            Text::_('COM_GEOFACTORY_MENU_MARKERSETS_MANAGER'),
            'index.php?option=com_geofactory&view=markersets',
            $vName === 'markersets'
        );
        Sidebar::addEntry(
            Text::_('COM_GEOFACTORY_MENU_GEOCODING'),
            'index.php?option=com_geofactory&view=geocodes',
            $vName === 'geocodes'
        );
        Sidebar::addEntry(
            Text::_('COM_GEOFACTORY_MENU_ASSIGN_PATTERN'),
            'index.php?option=com_geofactory&view=assigns',
            $vName === 'assigns'
        );
    }

    /**
     * Ritorna link di shortcut per la modifica di oggetti
     */
    public static function getLinksEditShortCuts($items, $task)
    {
        $res = [];
        if (is_array($items) && count($items)) {
            foreach ($items as $item) {
                $res[] = '<a style="font-size:0.75em!important;" class="badge bg-info" '
                    . 'href="index.php?option=com_geofactory&task='
                    . $task . '.edit&id=' . $item->value . '">'
                    . $item->text . '</a>';
            }
        }
        return '<br /> ' . implode(' ', $res);
    }

    /**
     * Ottiene la lista di azioni ACL (core.manage, core.edit, ecc.)
     */
    public static function getActions()
    {
        $user = Factory::getUser();
        $assetName = 'com_geofactory';
        $result = new CMSObject;
        
        // Percorso del file access.xml
        $xmlFile = JPATH_ADMINISTRATOR . '/components/com_geofactory/access.xml';

        if (!file_exists($xmlFile))
        {
            return $result;
        }

        // Carica il file XML
        $xml = simplexml_load_file($xmlFile);
        if (!$xml)
        {
            return $result;
        }

        // Itera sulle sezioni (normalmente c'è una sezione "component")
        foreach ($xml->section as $section)
        {
            // Itera su ogni azione definita nella sezione
            foreach ($section->action as $action)
            {
                $actionName = (string) $action['name'];
                $result->set($actionName, $user->authorise($actionName, $assetName));
            }
        }

        return $result;
    }

    /**
     * Carica codice JS personalizzato nel documento
     *
     * @param  string|array  $js  Può essere una stringa o un array di righe
     */
    public static function loadJsCode($js)
    {
        $config = ComponentHelper::getParams('com_geofactory');

        // Se è un array, lo uniamo in una stringa
        if (!is_array($js)) {
            $js = [$js];
        }

        // Se isDebug=1, non minimizziamo; altrimenti rimuoviamo doppi spazi
        if ((int)$config->get('isDebug', 0) === 1) {
            $js = implode("\n", $js);
        } else {
            $js = str_replace('  ', '', implode($js));
        }

        $document = Factory::getDocument();
        $document->addScriptDeclaration($js);
    }

    /**
     * Ritorna un array di oggetti (value, text) dal plugin geocodefactory.
     */
    public static function getArrayObjTypeListe($unique = false)
    {
        // Carica i plugin di tipo "geocodefactory"
        PluginHelper::importPlugin('geocodefactory');

        $app = Factory::getApplication();
        $vvNames = $app->triggerEvent('getPlgInfo', []);

        $options = [];
        $added = [];

        if (is_array($vvNames)) {
            foreach ($vvNames as $vNames) {
                if (!is_array($vNames)) {
                    continue;
                }
                foreach ($vNames as $id => $name) {
                    if (count($name) !== 2) {
                        continue;
                    }
                    $text = $name[1];
                    $textPieces = explode(' - ', $text);
                    if (count($textPieces) > 0) {
                        $text = $textPieces[0];
                    }
                    if ($unique) {
                        if (in_array($text, $added)) {
                            continue;
                        }
                    } else {
                        $text = $name[1];
                    }
                    $tmp = new \stdClass;
                    $tmp->value = $name[0];
                    $tmp->text = $text;
                    $options[] = $tmp;
                    $added[] = $text;
                }
            }
        }
        return $options;
    }

    /**
     * Elementi "avanzati" da nascondere in modalità basic (per la mappa)
     */
    public static function getExpertMap()
    {
        return [
            'allowDbl',
            'totalmarkers',
            'minZoom',
            'maxZoom',
            'pegman',
            'scaleControl',
            'rotateControl',
            'overviewMapControl',
            'useRoutePlaner',
            'useTabs',
            'cacheTime',
            'mapStyle',
            'mapTypeAvailable',
            'maptypeavailable',
            'mapTypeOnStart',
            'tiles',
            'kml_file',
            'radFormMode',
            'radFormSnipet',
            'acTypes',
            'useBrowserRadLoad',
            'gridSize',
            'imagePath',
            'imageSizes',
            'minimumClusterSize'
        ];
    }

    /**
     * Elementi "avanzati" da nascondere in modalità basic (per markerset)
     */
    public static function getExpertMarkerset()
    {
        return ['j_menu_id', 'accuracy', 'bubblewidth'];
    }

    /**
     * Ritorna la lista degli ID delle mappe collegate a un markerset
     */
    public static function getArrayMapsFromMs($id)
    {
        if ($id < 1) {
            return;
        }
        $maps = [];
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('id_map')
            ->from('#__geofactory_link_map_ms')
            ->where('id_ms=' . (int)$id);
        $db->setQuery($query);
        $res = $db->loadObjectList();
        if (!is_array($res) || !count($res)) {
            return;
        }
        foreach ($res as $v) {
            if ($v->id_map < 1) {
                continue;
            }
            $maps[] = $v->id_map;
        }
        return $maps;
    }

    /**
     * Ritorna un array di "assignation pattern"
     */
    public static function getArrayObjAssign($curType = null)
    {
        $options = [];
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('id AS value, name AS text')
            ->from('#__geofactory_assignation AS a');
        if ($curType) {
            $query->where('a.typeList=' . $db->quote($curType));
        }
        $query->order('a.name');
        $db->setQuery($query);
        try {
            $options = $db->loadObjectList();
        } catch (RuntimeException $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');
        }
        return $options;
    }

    /**
     * Ritorna un array di mappe (attive)
     */
    public static function getArrayListMaps()
    {
        $options = [];
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('id AS value, name AS text')
            ->from('#__geofactory_ggmaps AS a')
            ->order('a.name')
            ->where('state=1');
        $db->setQuery($query);
        try {
            $options = $db->loadObjectList();
        } catch (RuntimeException $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');
        }
        return $options;
    }

    public static function getMapsOptions($type = 0)
    {
        $options = self::getArrayListMaps();
        if ($type == 1) {
            array_unshift(
                $options,
                HTMLHelper::_('select.option', '0', Text::_('COM_GEOFACTORY_NO_MAPS'))
            );
        }
        return $options;
    }

    /**
     * Ritorna un array di markersets
     */
    public static function getArrayListMarkersets()
    {
        $options = [];
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('id AS value, name AS text')
            ->from('#__geofactory_markersets AS a')
            ->order('a.name');
        $db->setQuery($query);
        try {
            $options = $db->loadObjectList();
        } catch (RuntimeException $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');
        }
        return $options;
    }

    public static function getMarkersetsOptions($type = 0)
    {
        $options = self::getArrayListMarkersets();
        if ($type == 1) {
            array_unshift(
                $options,
                HTMLHelper::_('select.option', '0', Text::_('COM_GEOFACTORY_NO_MS'))
            );
        }
        return $options;
    }

    /**
     * Usato per import (carica parametri personalizzati da #__geocode_factory_parametres)
     */
    public static function loadMultiParamFor($listVal, $typ, $id, &$obj)
    {
        $opm = null;
        $where = '1';

        if ($typ == 1) {
            $where = "id_map='{$id}'";
        } elseif ($typ == 2) {
            $where = "id_markerset='{$id}'";
        }

        $db = Factory::getDbo();
        $config = ComponentHelper::getParams('com_geofactory');
        $extDb = $config->get('import-database');

        if (strlen($extDb) > 0) {
            $db = self::loadExternalDb();
        }

        $query = $db->getQuery(true)
            ->select('keynom, valeur')
            ->from('#__geocode_factory_parametres')
            ->where($where);

        $db->setQuery($query);
        $opm = $db->loadObjectList();

        if (!is_array($opm)) {
            $opm = [0];
        }

        if (!$obj) {
            $obj = new \stdClass();
        }

        // Imposta valori di default
        foreach ($listVal as $k => $v) {
            $obj->$k = $v;
        }

        // Sovrascrive con i valori dal DB
        foreach ($opm as $dbVal) {
            if (!isset($dbVal->keynom)) {
                continue;
            }
            $varCur = $dbVal->keynom;
            if (isset($dbVal->valeur) && isset($obj->$varCur)) {
                $obj->$varCur = $dbVal->valeur;
            }
        }

        // Adatta markerIconType
        if (isset($obj->markerIconType)) {
            $obj->markerIconType = 0;
            if (!empty($obj->marker) && strlen($obj->marker) > 3) {
                $obj->markerIconType = 1;
            } elseif (!empty($obj->avatarAsIcon) && $obj->avatarAsIcon == 1) {
                $obj->markerIconType = 3;
            } elseif (!empty($obj->catAuto) && $obj->catAuto == 1) {
                $obj->markerIconType = 4;
            }
        }
    }

    /**
     * Elimina file di cache di geocode factory
     */
    public static function delCacheFiles($idMap)
    {
        $pattern = JPATH_CACHE . DIRECTORY_SEPARATOR . "_geocodeFactory_{$idMap}*.xml";
        foreach (glob($pattern) as $filename) {
            File::delete($filename);
        }
    }

    /**
     * Ritorna un array (campo => fid) per la tabella #__geofactory_assignation
     */
    public static function getAssignArray($id)
    {
        $t = Table::getInstance('Assign', 'GeofactoryTable');
        $t->load($id);

        $vRet = [];
        $fields = [
            'field_latitude',
            'field_longitude',
            'field_street',
            'field_postal',
            'field_city',
            'field_county',
            'field_state',
            'field_country'
        ];
        foreach ($fields as $f) {
            if (isset($t->$f) && $t->$f != '0') {
                $vRet[$f] = $t->$f;
            }
        }
        return $vRet;
    }

    /**
     * Verifica se l'editor "codemirror" è abilitato
     */
    public static function isCodeMirrorEnabled()
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from('#__extensions AS a')
            ->where('(a.name=' . $db->quote('plg_editors_codemirror') . ' AND a.enabled = 1)');
        $db->setQuery($query);
        $state = (int)$db->loadResult();
        return ($state > 0);
    }

    /**
     * Verifica se l'editor "none" è abilitato
     */
    public static function isEditorNoneEnabled()
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from('#__extensions AS a')
            ->where('(a.name=' . $db->quote('plg_editors_none') . ' AND a.enabled = 1)');
        $db->setQuery($query);
        $state = (int)$db->loadResult();
        return ($state > 0);
    }

    /**
     * Carica un DB esterno, se definito
     */
    public static function loadExternalDb()
    {
        $config = ComponentHelper::getParams('com_geofactory');
        $option = [];
        $option['driver']   = $config->get('import-driver');
        $option['host']     = $config->get('import-host');
        $option['user']     = $config->get('import-user');
        $option['password'] = $config->get('import-password');
        $option['database'] = $config->get('import-database');
        $option['prefix']   = $config->get('import-prefix');

        // In Joomla 4 preferiamo:
        $db = \Joomla\Database\DatabaseFactory::getInstance($option);
        return $db;
    }
}