<?php
/**
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Cédric Pelloquin <info@myJoom.com>
 * @website     www.myJoom.com
 * @update      Daniele Bellante
 *
 * Geocode Factory gateway plugin for Joomla contents (articles)
 * 
 * This plugin is designed to link Geocode Factory with a particular third party component.
 * Any developer is free to develop his own plugin, sending a copy to MyJoom is recommended.
 * 
 * The first thing to do is define all member default values according to your third 
 * party component.
 *
 * Notes:
 *  - Comments with '-->' are specifically for the current third party component.
 *  - TPC = third party component = the component for which this plugin is written.
 *  - The functions with 'COMMON' are typical for all TPC-based plugins.
 */

defined('_JEXEC') or die('Restricted Access');

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\DatabaseDriver;
use Joomla\Component\Content\Site\Helper\RouteHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\Event\Event;

// Carica il route helper di com_content
require_once JPATH_SITE . '/components/com_content/src/Helper/RouteHelper.php';

/**
 * Plugin main class
 */
class PlgGeocodefactoryGwjc40 extends CMSPlugin implements SubscriberInterface
{
    
    /**
	 * Load the language file on instantiation
	 *
	 * @var    boolean
	 * @since  3.1
	 */
	protected $autoloadLanguage = true;
    
    // Identificazione del gateway
    protected $gatewayName    = "Joomla Content 3.0";  // nome leggibile
    protected $gatewayCode    = "MS_JC";              // codice (inizia con "MS_")
    protected $gatewayOption  = "com_content";        // option=com_content

    // Informazioni sul componente (TPC)
    protected $isCategorised    = true;
    protected $isProfileCom     = false;
    protected $isSupportAvatar  = false;
    protected $isSupportCatIcon = false;
    protected $isSingleGpsField = false;
    protected $defColorPline    = "red";

    // Campi custom per latitudine/longitudine
    protected $custom_latitude  = true;
    protected $custom_longitude = false;

    // Campi custom per l’indirizzo
    protected $custom_street    = false;
    protected $custom_postal    = false;
    protected $custom_city      = false;
    protected $custom_county    = false;
    protected $custom_state     = false;
    protected $custom_country   = false;

    // Coordinate “vuote” di default
    protected $defEmptyLat      = 255;
    protected $defEmptyLng      = 255;

    protected $vGatewayInfo     = array();
    protected $arBubbleFields   = array();
    protected $plgTable         = '#__geofactory_contents';
    protected $iTopCategory     = 1; // Categoria radice

    /**
     * Costruttore
     *
     * @param   object  &$subject
     * @param   array   $config
     */
    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->vGatewayInfo[] = array($this->gatewayCode, $this->gatewayName);

        // Campi disponibili nella bubble (placeholder)
        $this->arBubbleFields = array(
            "introtext", "introtextraw", "fulltext", "fulltextraw",
            "catid", "category_title", "created_by", "modified_by",
            "metakey", "metadesc", "hits", "author",
            "image_intro", "image_fulltext",
            // esempi di fields specifici
            "field_7", "field_8", "field_9", "field_10"
        );
    }

    /**
     * Restituisce le informazioni del plugin per la visualizzazione nelle liste del backend.
     *
     * @return  array
     * @since   1.0
     */
    public function getPlgInfo()
    {
        // Per i TPC a multidirectory, possiamo unire qui le directory interne.
        $this->mergeInternalDirectories();
        // Nei casi standard, restituiamo semplicemente $this->vGatewayInfo.
        return $this->vGatewayInfo;
    }

    /**
     * Funzione comune in sola lettura.
     *
     * @param   string  $type  Tipo di plugin
     * @param   bool    &$flag  Flag (passato per riferimento)
     * @return  void
     * @since   1.0
     */
    public function isProfile($type, &$flag)
    {
        if (!$this->isInCurrentType($type)) {
            return;
        }
        $flag = $this->isProfileCom;
    }

    /**
     * Funzione comune in sola lettura.
     *
     * @param   string  $type  Tipo di plugin
     * @param   bool    &$flag  Flag (passato per riferimento)
     * @return  void
     * @since   1.0
     */
    public function isEvent($type, &$flag)
    {
        if (!$this->isInCurrentType($type)) {
            return;
        }
        if (isset($this->isEventCom) && $this->isEventCom === true) {
            $flag = true;
        }
    }

    /**
     * Funzione comune in sola lettura.
     *
     * @param   string  $type  Tipo di plugin
     * @param   bool    &$flag  Flag (passato per riferimento)
     * @return  void
     * @since   1.0
     */
    public function isSpecialMs($type, &$flag)
    {
        if (!$this->isInCurrentType($type)) {
            return;
        }
        if (isset($this->isSpecialMs) && $this->isSpecialMs === true) {
            $flag = true;
        }
    }

    public static function getSubscribedEvents(): array
    {
        //kkk
        return [
            'onIsPluginInstalled' => 'isPluginInstalled',
            'onGetAllSubCats' => 'getAllSubCats',
            'onDefineContext' => 'defineContext',
            'onIsProfile' => 'isProfile',
            'onMarkerTemplateAndPlaceholder' => 'markerTemplateAndPlaceholder',
            'onContentPrepare' => 'onContentPrepare',
            'onIsSpecialMs' => 'isSpecialMs',
            'onCleanResultsFromPlugins' => 'cleanResultsFromPlugins',
            'onIsEvent' => 'isEvent',
            'onGetRel_idCat_iconPath' => 'getRel_idCat_iconPath',
            'onGetRel_idEntry_idCat' => 'getRel_idEntry_idCat',
            'onCustomiseQuery' => 'customiseQuery',
            'onSetMainQueryFilters' => 'setMainQueryFilters',
            'onGetMainQuery' => 'getMainQuery',
            'onGetIconCommonPath' => 'getIconCommonPath',
            'onIsMyShortName' => 'isMyShortName',
            'onGetItemCoordinates' => 'getItemCoordinates',
            'onGetIconPathFromBrutDbValue' => 'getIconPathFromBrutDbValue'
        ];
    }


     public function isPluginInstalled(Event $event)
    {

	// var_dump("---------------------");
	// die();
        // if (!$this->isInCurrentType($event->getArgument('type'))) {
        //     return;
        // }
        // return true;
        $result = false;
        if (!$this->isInCurrentType($event->getArgument('type'))) {
            $result = true;
        }
        $event->setArgument("result", $result);
    }

    public function getAllSubCats(Event $event)
    {

        var_dump('>>>>>>>>>>>>>>>>>>>>>>>>>>>>>child');
        $event->setArgument("result",">>>>>>>>>>>>>>>>>>>>>>>>>>>>>child");
        die();
        if (!$this->isInCurrentType($event->getArgument('typeList'))) {
            return;
        }
        $idTopCat = $event->getArgument('idTopCategory');
        $db = Factory::getContainer()->get(DatabaseDriver::class);


        $query = $db->getQuery(true)
            ->select('id AS catid, parent_id AS parentid, title AS title')
            ->from($db->quoteName('#__categories'))
            ->where('extension = ' . $db->quote('com_content'))
            ->order('parent_id');
        $db->setQuery($query);
        $vCats = $db->loadObjectList();
        $event->setArgument("catLists", $vCats);
        $event->setArgument("idTopCategory", $idTopCat);
    }

    /**
     * Restituisce la lista dei campi di assegnazione.
     *
     * @param   string  $type  Tipo di plugin
     * @return  array
     * @since   1.0
     */
    public function getListFieldsAssign($type)
    {
        $listFields = [];
        if (!$this->isInCurrentType($type)) {
            return [$this->gatewayCode, $listFields];
        }
        if (!empty($this->custom_latitude)) {
            $listFields[] = "field_latitude";
        }
        if (!empty($this->custom_longitude)) {
            $listFields[] = "field_longitude";
        } 
        if (!empty($this->custom_street)) {
            $listFields[] = "field_street";
        }
        if (!empty($this->custom_postal)) {
            $listFields[] = "field_postal";
        }
        if (!empty($this->custom_city)) {
            $listFields[] = "field_city";
        }
        if (!empty($this->custom_county)) {
            $listFields[] = "field_county";
        }
        if (!empty($this->custom_state)) {
            $listFields[] = "field_state";
        }
        if (!empty($this->custom_country)) {
            $listFields[] = "field_country";
        }
        return [$type, $listFields];
    }

    /**
     * Restituisce l'ID della sottodirectory dal typeList.
     *
     * @param   string  $typeList  Tipo di lista
     * @return  int
     * @since   1.0
     */
    protected function getSubDirIdFromTypeListe($typeList)
    {
        if (!is_string($typeList)) {
            return -1;
        }
        $v = explode('-', $typeList);
        if (count($v) < 2) {
            return -1;
        }
        return (int)$v[1];
    }

    /**
     * Verifica se il "nome breve" dell'URL è utilizzabile da questo plugin.
     *
     * @param   string  $type  Tipo di plugin
     * @param   string  $ext  Estensione
     * @param   bool    &$ret  Risultato (passato per riferimento)
     * @return  void
     * @since   1.0
     */
    public function isMyShortName($type, $ext, &$ret)
    {
        if (!$this->isInCurrentType($type)) {
            return;
        }
        if (!$this->isInCurrentType($ext)) {
            return;
        }
        $ret = true;
    }

    /**
     * Verifica se il plugin deve essere eseguito per il tipo corretto.
     *
     * @param   string  $type  Tipo di plugin
     * @return  bool
     * @since   1.0
     */
    protected function isInCurrentType($type)
    {
        $this->mergeInternalDirectories();
        
        if (!isset($this->vGatewayInfo) || !is_array($this->vGatewayInfo)) {
            return false;
        }
        
        foreach ($this->vGatewayInfo as $gi) {
            if (is_array($gi) && count($gi) > 0 && strtolower($type) == strtolower($gi[0])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Restituisce l'ID dell'elemento di menu.
     *
     * @param   int  $itemid  ID elemento di menu
     * @return  int
     * @since   1.0
     */
    protected function getMenuItemId($itemid = 0)
    {
        if ($itemid > 0) {
            return $itemid;
        }
        
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
                    ->select('id')
                    ->from($db->quoteName('#__menu'))
                    ->where($db->quoteName('link') . ' LIKE ' . $db->quote('%index.php?option=' . $this->gatewayOption . '%'))
                    ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                    ->where($db->quoteName('published') . ' = 1');
        
        $db->setQuery($query, 0, 1);
        
        try {
            return (int)$db->loadResult();
        } catch (\Exception $e) {
            Log::add('getMenuItemId: ' . $e->getMessage(), Log::ERROR, 'geofactory');
            return 0;
        }
    }

    /**
     * Aggiunge una condizione WHERE per testare la validità delle coordinate.
     *
     * @param   string  $fieldLat  Campo latitudine
     * @param   string  $fieldLng  Campo longitudine
     * @return  string
     * @since   1.0
     */
    protected function getValidCoordTest($fieldLat, $fieldLng)
    {
        $app = Factory::getApplication('site');
        $vp = $app->input->getString('bo', '');
        $allM = $app->input->getInt('allM');
        
        $vp = explode(',', $vp);
        if (!is_array($vp) || count($vp) != 4 || $allM == 1) {
            $vp = null;
        }
        
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $t = "(";
        $t .= "({$fieldLat} <> " . $db->quote('');
        
        if ($vp) {
            $t .= " AND ({$fieldLat} BETWEEN " . $db->escape($vp[0]) . " AND " . $db->escape($vp[2]) . ")";
        }
        
        $t .= " AND {$fieldLat} IS NOT NULL ";
        $t .= " AND {$fieldLat} <> 0 ";
        $t .= " AND {$fieldLat} <> " . $this->defEmptyLat . ")";
        $t .= " OR ";
        $t .= "({$fieldLng} <> " . $db->quote('');
        
        if ($vp) {
            $t .= " AND ({$fieldLng} BETWEEN " . $db->escape($vp[1]) . " AND " . $db->escape($vp[3]) . ")";
        }
        
        $t .= " AND {$fieldLng} IS NOT NULL ";
        $t .= " AND {$fieldLng} <> 0 ";
        $t .= " AND {$fieldLng} <> " . $this->defEmptyLng . ")";
        $t .= ")";
        
        return str_replace(array('\t', '   ', '  '), ' ', $t);
    }

    /**
     * Finalizza la query di lista per la geocodifica.
     *
     * @param   object  $query    Oggetto query
     * @param   array   $filters  Filtri
     * @return  object
     * @since   1.0
     */
    protected function finaliseGetListQueryBackGeocode($query, $filters)
    {
        if (empty($filters) || !is_array($filters) || count($filters) < 3) {
            return $query;
        }
        
        $filterSearch = $filters[0];
        $filterGeocoded = $filters[2];
        $listDirection = $filters[1];
        
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query->group($db->quoteName(['item_id', 'item_name', 'c_status']));
        
        if (!empty($filterSearch)) {
            if (stripos($filterSearch, 'id:') === 0) {
                $query->having($db->quoteName('item_id') . ' = ' . (int)substr($filterSearch, 3));
            } else {
                $filterSearch = $db->quote('%' . $db->escape($filterSearch, true) . '%');
                $query->having($db->quoteName('item_name') . ' LIKE ' . $filterSearch);
            }
        }
        
        if ($filterGeocoded == 1) {
            $query->having($db->quoteName('c_status') . ' = 1');
        } elseif ($filterGeocoded == 2) {
            $query->having($db->quoteName('c_status') . ' = 0');
        }
        
        $listDirection = strtoupper($listDirection) === 'DESC' ? 'DESC' : 'ASC';
        $query->order($db->quoteName('item_name') . ' ' . $listDirection);
        
        return $query;
    }

    /**
     * Genera un URL completo.
     *
     * @param   string  $href  URL relativo
     * @return  string
     * @since   1.0
     */
    protected function genericUrl($href)
    {
        $href = str_replace('&amp;', '&', $href);
        $uri = \Joomla\CMS\Uri\Uri::getInstance();
        $prefix = $uri->toString(['scheme', 'host', 'port']);
        return $prefix . Route::_($href);
    }

    /**
     * Restituisce tutti i tag.
     *
     * @param   int     $idTopCat  ID categoria superiore
     * @param   array   &$vCats    Array categorie (passato per riferimento)
     * @return  void
     * @since   1.0
     */
    public function getAllTags($idTopCat, &$vCats)
    {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                        ->select([
                            $db->quoteName('id', 'catid'),
                            $db->quoteName('parent_id', 'parentid'),
                            $db->quoteName('title')
                        ])
                        ->from($db->quoteName('#__tags'))
                        ->order($db->quoteName('title'));
                        
            $db->setQuery($query);
            $vCats = $db->loadObjectList();
        } catch (\Exception $e) {
            Log::add('getAllTags: Errore nel database: ' . $e->getMessage(), Log::ERROR, 'geofactory');
            $vCats = [];
        }
    }

    /**
     * Restituisce le sottocategorie di una lista di categorie.
     *
     * @param   array   $categoryList  Lista categorie
     * @param   mixed   &$par          ID genitore (passato per riferimento)
     * @param   array   &$vRes         Risultati (passato per riferimento)
     * @param   string  $indent        Indentazione
     * @return  void
     * @since   1.0
     */
    public static function getChildCatOf($categoryList, &$par, &$vRes, $indent)
    {
        if (is_string($indent)) {
            $indent .= "- ";
        }
        
        if (is_array($categoryList) && count($categoryList) > 0) {
            foreach ($categoryList as $category) {
                if (isset($category->parentid) && $category->parentid == $par) {
                    $vRes[] = is_string($indent)
                        ? HTMLHelper::_('select.option', $category->catid, $indent . htmlspecialchars($category->title, ENT_QUOTES, 'UTF-8'))
                        : $category->catid;
                    
                    // Chiamata ricorsiva
                    self::getChildCatOf($categoryList, $category->catid, $vRes, $indent);
                }
            }
        }
    }
    
    /**
     * Unisce le directory interne.
     * Questo metodo deve essere implementato nelle classi derivate se necessario.
     * 
     * @return  void
     * @since   1.0
     */
    protected function mergeInternalDirectories()
    {
        // Metodo vuoto che deve essere sovrascritto nelle classi figlie
        // Sostituisce il vecchio _mergeInternalDirectories()
    }





    /**
     * Caratteristiche del plugin (campi e feature supportate).
     *
     * @param   string  $type
     * @return  array   [type, listFields]
     */
    public function getListFieldsMs(string $type): array
    {
        if (!$this->isInCurrentType($type)) {
            return array($this->gatewayCode, array());
        }

        // Elenco delle feature supportate
        $listFields = array(
            "include_categories",
            "childCats",
            "linesOwners",
            "catAuto",
            "filter",
            "onlyPublished",
            "tags",
            "childTags",
        );
        return array($type, $listFields);
    }

    /**
     * Elenca i possibili campi custom del TPC (es. Joomla fields).
     *
     * @param   string  $typeList
     * @param   array   &$ar
     * @param   bool    $all
     */
    public function getCustomFields($typeList, &$ar, $all)
    {
        if (!$this->isInCurrentType($typeList)) {
            return;
        }
        // Esempio semplice: un solo campo “Default”.
        $obj = new \stdClass();
        $obj->text  = "Default";
        $obj->value = 0;
        $ar = array($obj);
    }

    /**
     * Query di back-end per la geocodifica (lista di articoli).
     *
     * @param   string  $type
     * @param   array   $filters
     * @param   object  $papa
     * @param   array   $vAssign
     * @return  array   [type, query]
     */
    public function getListQueryBackGeocode($type, $filters, $papa, $vAssign)
    {
        if (!$this->isInCurrentType($type)) {
            return array($this->gatewayCode, null);
        }

        /** @var DatabaseInterface $db */
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        // Importa gli articoli che non sono ancora nella tabella #__geofactory_contents
        $query = $db->getQuery(true)
            ->select('id')
            ->from($db->quoteName('#__content'))
            ->where('id NOT IN (SELECT id_content FROM ' . $db->quoteName($this->plgTable) . ' WHERE type = ' . $db->quote($this->gatewayOption) . ')');

        $db->setQuery($query);
        $res = $db->loadObjectList();

        if (is_array($res) && count($res) > 0) {
            $query->clear()
                ->insert($db->quoteName($this->plgTable));
            foreach ($res as $art) {
                $vals = array(
                    $db->quote(''),
                    $db->quote($this->gatewayOption),
                    (int)$art->id,
                    $db->quote(''),
                    (float)$this->defEmptyLat,
                    (float)$this->defEmptyLng
                );
                $query->values(implode(',', $vals));
            }
            $db->setQuery($query);
            $db->execute();
        }

        $latSql = 'a.latitude';
        $lngSql = 'a.longitude';
        $test   = $this->getValidCoordTest($latSql, $lngSql);

        $query->clear()
            ->select(
                $papa->getState(
                    'list.select',
                    'a.id AS item_id,' .
                    'j.title AS item_name,' .
                    $latSql . ' AS item_latitude,' .
                    $lngSql . ' AS item_longitude,' .
                    $db->quote($type) . ' AS type_ms,' .
                    'IF(' . $test . ', 1, 0) AS c_status'
                )
            )
            ->from($db->quoteName($this->plgTable) . ' AS a')
            ->join('LEFT', '#__content AS j ON j.id = a.id_content')
            ->where('type = ' . $db->quote($this->gatewayOption));

        $query = $this->_finaliseGetListQueryBackGeocode($query, $filters);

        return array($type, $query);
    }

    // protected function _mergeInternalDirectories()
    // {
    //     // Nessuna sottodirectory particolare per com_content
    // }

    /**
     * Salva le coordinate (latitudine e longitudine) per un articolo nella tabella #__geofactory_contents.
     *
     * @param   string  $type
     * @param   int     $id
     * @param   array   $vCoord
     * @param   array   $vAssign
     * @return  array
     */
    public function setItemCoordinates(string $type, int $id, array $vCoord, array $vAssign): array
    {
        if (!$this->isInCurrentType($type)) {
            return array($this->gatewayCode);
        }

        /** @var DatabaseInterface $db */
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true);
        $fields = array(
            'latitude = ' . (float)$vCoord[0],
            'longitude = ' . (float)$vCoord[1]
        );

        $query->update($db->quoteName($this->plgTable))
              ->set($fields)
              ->where('id = ' . (int)$id);

        try {
            $db->setQuery($query);
            $db->execute();
        } catch (\Exception $e) {
            return array($type, "Unknown error saving coordinates:" . $e->getMessage());
        }

        return array($type, "Coordinates properly saved (" . implode(' ', $vCoord) . ").");
    }

    /**
     * Ottiene le coordinate di un articolo
     *
     * @param   string       $type     Tipo di contenuto
     * @param   int|array    $ids      ID o array di ID
     * @param   array        &$vCoord  Array di coordinate da riempire
     * @param   array        $params   Parametri
     * @return  void
     */
    public function getItemCoordinates(string $type, $ids, array &$vCoord, array $params): void
    {
        if (!$this->isInCurrentType($type)) {
            return;
        }

        $vCoord = array($this->defEmptyLat, $this->defEmptyLng);

        if (!is_array($ids) && is_int($ids)) {
            $ids = array($ids);
        }
        if (!is_array($ids) || count($ids) < 1) {
            return;
        }

        /** @var DatabaseInterface $db */
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select('id_content, latitude, longitude')
            ->from($db->quoteName($this->plgTable))
            ->where('id_content IN (' . implode(',', $ids) . ') AND type = ' . $db->quote($this->gatewayOption));

        $db->setQuery($query);
        $res = $db->loadObjectList();

        if (!is_array($res) || count($res) < 1) {
            return;
        }

        foreach ($res as $coor) {
            $vCoord[$coor->id_content] = array($coor->latitude, $coor->longitude);
        }
    }

    public function getItemPostalAddress($type, $id, $vAssign)
    {
        $add = array();
        if (!$this->isInCurrentType($type)) {
            return array($this->gatewayCode, $add);
        }

        if (isset($vAssign["field_latitude"]) && $vAssign["field_latitude"] == -2) {
            $adresse = $this->_getItemFromArticle($id, 'metakey');
            if (strlen(trim($adresse)) < 1) {
                return array($type, $add);
            }
            $add["field_city"] = $adresse;
        } elseif (isset($vAssign["field_latitude"]) && $vAssign["field_latitude"] == -3) {
            $adresse = $this->_getItemFromArticle($id, 'metadesc');
            if (strlen(trim($adresse)) < 1) {
                return array($type, $add);
            }
            $add["field_city"] = $adresse;
        } else {
            $db = Factory::getContainer()->get(DatabaseDriver::class);
            $query = $db->getQuery(true)
                ->select('address')
                ->from($db->quoteName($this->plgTable))
                ->where('id = ' . (int)$id);
            $db->setQuery($query);
            $res = $db->loadObject();
            if (!$res) {
                return array($type, $add);
            }
            $add["field_city"] = $res->address;
        }

        return array($type, $add);
    }

    public function setItemAddress($type, $id, $vAssign, $vAddress)
    {
        if (!$this->isInCurrentType($type)) {
            return array($this->gatewayCode);
        }
        if (!$id || !count($vAddress)) {
            return false;
        }

        $add = implode(";", $vAddress);

        $db = Factory::getContainer()->get(DatabaseDriver::class);
        $vals = array("address = " . $db->quote($add));
        if (!count($vals)) {
            return array($type, "Error saving address, nothing to save!");
        }

        $query = $db->getQuery(true)
            ->update($db->quoteName($this->plgTable))
            ->set($vals)
            ->where("id = " . (int)$id);

        try {
            $db->setQuery($query);
            $db->execute();
        } catch (\Exception $e) {
            return array($type, "Unknown error saving address: " . $e->getMessage());
        }

        return array($type, "Address properly saved (" . implode(' ', $vAddress) . ").");
    }

    public function getRel_idCat_iconPath($type, &$listCatIcon)
    {
        if (!$this->isInCurrentType($type)) {
            return false;
        }
    }

    public function getRel_idEntry_idCat($type, &$listCatEntry)
    {
        if (!$this->isInCurrentType($type)) {
            return false;
        }
    }

    public function getIconCommonPath($type, $markerIconType, &$iconPathDs)
    {
        if (!$this->isInCurrentType($type)) {
            return false;
        }
    }

    public function customiseQuery($type, $params, &$sqlSelect, &$sqlJoin, &$sqlWhere)
    {
        if (!$this->isInCurrentType($type)) {
            return;
        }
        $sqlSelect[] = "O.id_content AS id, C.title, O.latitude, O.longitude";
        $sqlJoin[]   = "LEFT JOIN #__content AS C ON C.id = O.id_content";
        $sqlWhere[]  = "O.type = 'com_content'";

        if (!empty($params['linesOwners']) && $params['linesOwners'] == 1) {
            $sqlSelect[] = "C.created_by AS owner";
        }
        if (!empty($params['tags'])) {
            $sqlJoin[]  = "LEFT JOIN #__contentitem_tag_map AS T ON C.id = T.content_item_id AND type_alias = 'com_content.article'";
            $sqlWhere[] = "T.tag_id IN (" . $params['tags'] . ")";
        }
        if (!empty($params['inCats'])) {
            $sqlWhere[] = "C.catid IN (" . $params['inCats'] . ")";
        }
        $sqlWhere[] = $this->_getPublishedState(isset($params['onlyPublished']) ? $params['onlyPublished'] : 0);
        $sqlWhere[] = $this->getValidCoordTest("O.latitude", "O.longitude");
    }

    public function getMainQuery($data, &$retQuery)
    {
        if (!$this->isInCurrentType($data['type'])) {
            return;
        }
        $parts = array();
        $parts[] = $data['sqlSelect'];
        $parts[] = "FROM {$this->plgTable} O";
        $parts[] = $data['sqlJoin'];
        $parts[] = $data['sqlWhere'];

        $retQuery = implode(" ", $parts);
    }

    public function setMainQueryFilters($type, $oMs, &$sqlSelect, &$sqlJoin, &$sqlWhere)
    {
        if (!$this->isInCurrentType($type)) {
            return;
        }
        if (isset($oMs->filter) && strlen($oMs->filter) > 0) {
            $sqlWhere[] = $oMs->filter;
        }
    }

    public function getIconPathFromBrutDbValue($type, &$fieldImg)
    {
    }

    public function cleanResultsFromPlugins($type, &$vUid)
    {
    }

    public function getColorPline(&$vCol)
    {
        $col = $this->params->get('linesOwners');
        $vCol[$this->gatewayCode . 'linesOwners'] = (strlen($col) > 2) ? $col : $this->defColorPline;
    }

    public function defineContext($option, &$map)
    {
        if (strtolower($option) != strtolower($this->gatewayOption)) {
            return;
        }

        $app = Factory::getApplication();
        $view = $app->input->getString('view', '');
        $zoomMe = 0;

        if (strtolower($view) === 'article') {
            $zoomMe = $app->input->getInt('id', 0);
        }

        $map->gf_zoomMeId = $zoomMe;
        $map->gf_zoomMeType = $this->gatewayCode;
    }

    public function getCustomFieldsCoord($typeList, &$options)
    {
        if (!$this->isInCurrentType($typeList)) {
            return;
        }

        $tmp = new \stdClass();
        $tmp->value = '';
        $tmp->text  = "Users manually saved address with button";
        $options[]  = $tmp;

        $tmp = new \stdClass();
        $tmp->value = -2;
        $tmp->text  = "Use metadata (keywords) as address";
        $options[]  = $tmp;

        $tmp = new \stdClass();
        $tmp->value = -3;
        $tmp->text  = "Use metadata (description) as address";
        $options[]  = $tmp;
    }

    public function getCodeDynCat(&$resCode)
    {
        if (!$this->isCategorised) {
            return;
        }
        $resCode[] = $this->gatewayCode;
    }

    public function markerTemplateAndPlaceholder(&$objMarker, $params)
    {
        if (!$this->isInCurrentType($objMarker->type)) {
            return;
        }

        $article = Table::getInstance('content');
        $article->load($objMarker->id);

        $article->image_intro    = Uri::root() . 'media/com_geofactory/assets/blank.png';
        $article->image_fulltext = Uri::root() . 'media/com_geofactory/assets/blank.png';

        if (strlen($article->images) > 5) {
            $images = json_decode($article->images);
            if (!empty($images->image_intro)) {
                $article->image_intro = Uri::root() . $images->image_intro;
            }
            if (!empty($images->image_fulltext)) {
                $article->image_fulltext = Uri::root() . $images->image_fulltext;
            }
        }

        $slug    = $article->id . ':' . $article->alias;
        $catslug = $article->catid;
        $objMarker->link = Route::_(RouteHelper::getArticleRoute($slug, $catslug));
        $objMarker->rawTitle= $article->title;

        foreach ($objMarker->replace as $k => $v) {
            $objMarker->search[] = $k;
        }
    }

    public function getPlaceHoldersTemplate($typeList, &$placeHolders)
    {
        if (!$this->isInCurrentType($typeList)) {
            return;
        }
        $placeHolders = array();
        $familyKey = "Joomla content special";
        $placeHolders[$familyKey] = array();

        foreach ($this->arBubbleFields as $fName) {
            $placeHolders[$familyKey][$fName] = '{' . $fName . '}';
        }
    }

    public function getFilterGenerator($typeList, &$jsPlugin, &$txt)
    {
        if (!$this->isInCurrentType($typeList)) {
            return;
        }
        $jsPlugin .= 'result = "( " + field + cond + " \'" + like + value + like + "\' )";';
        $txt .= "&nbsp;&nbsp;SELECT values FROM articles_table WHERE internal_conditions AND <strong>(your_query)</strong>";
        $txt .= "</br></br>With Joomla Content you can use multiple conditions like :</br>";
        $txt .= "&nbsp;&nbsp;SELECT values FROM article_table WHERE internal_conditions AND <strong>((your_query_A) AND/OR (your_query_B))</strong>";
    }

    public function _getPublishedState($state)
    {
        if ($state == 1) {
            return 'C.state = 0';
        }
        return 'C.state > 0';
    }

    public function _getItemFromArticle($id, $field, $purejoomla = false)
    {
        /** @var DatabaseInterface $db */
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        if (strpos($field, 'field') !== false) {
            $parts = explode('_', $field);
            $id_field = array_pop($parts);
            $query = $db->getQuery(true)
                ->select('j.value AS ' . $field)
                ->from($db->quoteName($this->plgTable) . ' AS a')
                ->join('LEFT', '#__fields_values AS j ON j.item_id = a.id_content')
                ->where("j.field_id = " . (int)$id_field . " AND a.id = " . (int)$id);
        } else {
            $query = $db->getQuery(true)
                ->select('j.' . $field)
                ->from($db->quoteName($this->plgTable) . ' AS a')
                ->join('LEFT', '#__content AS j ON j.id = a.id_content')
                ->where("a.id = " . (int)$id);
        }

        $db->setQuery($query, 0, 1);
        $res = $db->loadObject();
        if (!$res) {
            return '';
        }
        return $res->$field ?? '';
    }

    public function _JoomlaContact_getAddress($id, $idsPattern)
    {
        $db = Factory::getContainer()->get(DatabaseDriver::class);
        $query = $db->getQuery(true)
                    ->select('extra_fields')
                    ->from($db->quoteName('#__contact_details'))
                    ->where('id=' . (int)$id);
        $db->setQuery($query);
        $ef = $db->loadResult();
        $ef = json_decode($ef);
        $adre = array();
        foreach ($ef as $f) {
            if (in_array($f->id, $idsPattern)) {
                $adre[] = $f->value;
            }
        }
        return $adre;
    }

    public function onContentPrepare($context, &$article, &$params, $limitstart = 0): bool
    {
        if ($context == 'com_finder.indexer') {
            return true;
        }

        if ($context == 'com_mtree.category') {
            $view = strtolower(Factory::getApplication()->input->getString("view"));
            $task = strtolower(Factory::getApplication()->input->getString("task"));

            $listcats = false;
            if ($view == 'listcats' || $task == 'listcats') {
                $listcats = true;
            }

            if (isset($article->link_id) && !$listcats) {
                $session = Factory::getSession();
                $links = $session->get('gf_mt_links', array());

                $reftime = time();
                if (count($links) > 0) {
                    foreach ($links as $time => &$row) {
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
            $new = '{' . $this->m_plgCode . '}';
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
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                        ->select('latitude,longitude')
                        ->from($db->quoteName($this->plgTable))
                        ->where('id_content=' . (int)$id . ' AND type=' . $db->quote($c_opt));
            $db->setQuery($query, 0, 1);
            
            $gps = $db->loadObject();
            if ($gps) {
                $lat = $gps->latitude;
                $lng = $gps->longitude;
            }
        } catch (\Exception $e) {
            // Log dell'errore
            Factory::getApplication()->enqueueMessage('_loadCoord error: ' . $e->getMessage(), 'error');
        }
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
                $ggApikey = (strlen($config->get('ggApikey')) > 3) ? "&key=" . $config->get('ggApikey') : "";
                $width = $this->params->get('staticWidth', 200);
                $height = $this->params->get('staticHeight', 200);
                $res = "https://maps.googleapis.com/maps/api/staticmap?center={$lat},{$lng}&zoom={$zoom}&size={$width}x{$height}&markers={$lat},{$lng}{$ggApikey}";
                $res = '<img src="' . $res . '" class="img-fluid" alt="Map">';
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
        if (strpos($article->fulltext . $article->introtext, '{myjoom_gf') === false) {
            return true;
        }
        $regex = '/{myjoom_gf\s+(.*?)}/i';
        $new = '{' . $this->m_plgCode . '}';
        $article->introtext = preg_replace($regex, $new, $article->introtext);
        $article->fulltext  = preg_replace($regex, $new, $article->fulltext);
        $replace = '{myjoom_gf}';
        $article->introtext = str_replace($replace, $new, $article->introtext);
        $article->fulltext  = str_replace($replace, $new, $article->fulltext);
        return true;
    }

    /**
     * Aggiorna le classi CSS per la compatibilità con Bootstrap 5
     *
     * @param   string  $html  HTML da aggiornare
     * @return  string  HTML aggiornato
     */
    protected function _updateBootstrapClasses(string $html): string
    {
        $replacements = [
            'btn-default' => 'btn-secondary',
            'input-group-addon' => 'input-group-text',
            'img-responsive' => 'img-fluid',
        ];
        
        foreach ($replacements as $old => $new) {
            $html = str_replace($old, $new, $html);
        }
        
        return $html;
    }
}
