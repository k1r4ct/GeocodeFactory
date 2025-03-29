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

require_once JPATH_SITE . '/components/com_geofactory/helpers/geofactoryPlugin.php';

// Carica il route helper di com_content
require_once JPATH_SITE . '/components/com_content/src/Helper/RouteHelper.php';

/**
 * Plugin main class
 */
class plggeocodefactoryPlg_geofactory_gw_jc30 extends GeofactoryPluginHelper
{
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

    // /**
    //  * Verifica se il plugin è installato per il tipo dato.
    //  *
    //  * @param   string  $type  Tipo di plugin
    //  * @param   bool    &$flag  Flag (passato per riferimento)
    //  * @return  void
    //  * @since   1.0
    //  */
    // public static function getSubscribedEvents(): array
    // {
    //     return [
    //         'onIsPluginInstalled' => 'isPluginInstalled',
    //         'onGetAllSubCats' => 'getAllSubCats',
    //     ];
    // }

    /**
     * Caratteristiche del plugin (campi e feature supportate).
     *
     * @param   string  $type
     * @return  array   [type, listFields]
     */
    public function getListFieldsMs(string $type): array
    {
        if (!$this->_isInCurrentType($type)) {
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
        if (!$this->_isInCurrentType($typeList)) {
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
        if (!$this->_isInCurrentType($type)) {
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
        $test   = $this->_getValidCoordTest($latSql, $lngSql);

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

    protected function _mergeInternalDirectories()
    {
        // Nessuna sottodirectory particolare per com_content
    }

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
        if (!$this->_isInCurrentType($type)) {
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
        if (!$this->_isInCurrentType($type)) {
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
        if (!$this->_isInCurrentType($type)) {
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
        if (!$this->_isInCurrentType($type)) {
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
        if (!$this->_isInCurrentType($type)) {
            return false;
        }
    }

    public function getRel_idEntry_idCat($type, &$listCatEntry)
    {
        if (!$this->_isInCurrentType($type)) {
            return false;
        }
    }

    public function getIconCommonPath($type, $markerIconType, &$iconPathDs)
    {
        if (!$this->_isInCurrentType($type)) {
            return false;
        }
    }

    // public function getAllSubCats($type, &$vCats, &$idTopCat)
    // {
    //     if (!$this->_isInCurrentType($type)) {
    //         return;
    //     }
    //     $idTopCat = $this->iTopCategory;

    //     $db = Factory::getContainer()->get(DatabaseDriver::class);
    //     $query = $db->getQuery(true)
    //         ->select('id AS catid, parent_id AS parentid, title AS title')
    //         ->from($db->quoteName('#__categories'))
    //         ->where('extension = ' . $db->quote('com_content'))
    //         ->order('parent_id');

    //     $db->setQuery($query);
    //     $vCats = $db->loadObjectList();
    // }

	public function test()
	{
		return 'testOK';
	}

    public function getAllSubCats($event)
    {

	var_dump($event);
	// die();
	if (!$this->_isInCurrentType($event->getArgument('typeList'))) {
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
        return [$vCats, $idTopCat];
    }

    public function customiseQuery($type, $params, &$sqlSelect, &$sqlJoin, &$sqlWhere)
    {
        if (!$this->_isInCurrentType($type)) {
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
        $sqlWhere[] = $this->_getValidCoordTest("O.latitude", "O.longitude");
    }

    public function getMainQuery($data, &$retQuery)
    {
        if (!$this->_isInCurrentType($data['type'])) {
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
        if (!$this->_isInCurrentType($type)) {
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
        if (!$this->_isInCurrentType($typeList)) {
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
        if (!$this->_isInCurrentType($objMarker->type)) {
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
        if (!$this->_isInCurrentType($typeList)) {
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
        if (!$this->_isInCurrentType($typeList)) {
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
