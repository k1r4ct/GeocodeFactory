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
 * Any developer is free to develop his own plugin, sending a copy to myjoom will be recommended
 * In the comments when you read "third party component" this describe the component for witch the
 * plugin is designed 
 *
 * The first thing to do is define all members values with default values according your third 
 * party component
 *
 *	Notes : -	Comments starting with '-->' or into the functions are specifically write for the 
 *				current third party component.   
 *			-	Typing convention : TPC = third party component = the component for witch this this
 *   			plugin is write to work with Geocode Factory
 *			-	The function with the 'COMMON' tag dont need to be modified by you, they are common
 *				for all TPC
 *			- 	All comment in italian are internal 
 *
 */

// Evita accessi diretti.
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Helper\ContentHelperRoute;
use Joomla\CMS\HTML\HTMLHelper;

require_once JPATH_SITE . '/components/com_geofactory/helpers/geofactoryPlugin.php';

// Carica il contesto di com_content
$com_path = JPATH_SITE . '/components/com_content/';
require_once $com_path . 'router.php';
require_once $com_path . 'helpers/route.php';
Table::addIncludePath($com_path . '/models', 'ContentModel');

class plggeocodefactoryPlg_geofactory_gw_jc30 extends GeofactoryPluginHelper
{
	// Membri – identificazione del componente di terze parti
	protected $gatewayName      = "Joomla Content 3.0";   // Nome leggibile del gateway (eventualmente aggiungere versione)
	protected $gatewayCode      = "MS_JC";                // Codice identificativo (inizia con "MS_")
	protected $gatewayOption    = "com_content";          // Valore della variabile URL "option" (es. option=com_content)

	// Informazioni sul TPC (third party component)
	protected $isCategorised    = true;                 // Il componente possiede categorie
	protected $isProfileCom     = false;                // Non è un componente di profili (es. CB, Jomsocial)
	protected $isSupportAvatar  = false;                // Non supporta immagini/avatar
	protected $isSupportCatIcon = false;                // Non supporta icone per categorie
	protected $isSingleGpsField = false;                // Non usa un unico campo “Geo” per le coordinate
	protected $defColorPline    = "red";                // Colore di default per le linee (modificabile in backend)

	// Campi custom per le coordinate (true se modificabili)
	protected $custom_latitude  = true;
	protected $custom_longitude = false;

	// Campi custom per l'indirizzo (false se fissi)
	protected $custom_street    = false;
	protected $custom_postal    = false;
	protected $custom_city      = false;
	protected $custom_county    = false;
	protected $custom_state     = false;
	protected $custom_country   = false;

	// Valori di default per coordinate non impostate (ad es. 255)
	protected $defEmptyLat      = 255;
	protected $defEmptyLng      = 255;

	// Variabile interna per le informazioni sul gateway
	protected $vGatewayInfo     = array();

	// Array dei campi utilizzati per il “bubble” dei marker
	protected $arBubbleFields   = array(
		"introtext", "introtextraw", "fulltext", "fulltextraw",
		"catid", "category_title", "created_by", "modified_by",
		"metakey", "metadesc", "hits", "author", 
		"image_intro", "image_fulltext", "field_7", "field_8", "field_9", "field_10"
	);

	protected $plgTable         = '#__geofactory_contents';
	protected $iTopCategory     = 1; // Categoria radice fissa

	/**
	 * Constructor.
	 *
	 * @param   object  $subject  L'oggetto da osservare.
	 * @param   array   $config   La configurazione del plugin.
	 * @since   1.0
	 */
	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);
		$this->vGatewayInfo[] = array($this->gatewayCode, $this->gatewayName);
	}

	/**
	 * Definisce la lista dei campi (features) supportati.
	 *
	 * @param   string  $type
	 * @return  array
	 */
	public function getListFieldsMs($type)
	{
		if (!$this->_isInCurrentType($type))
		{
			return array($this->gatewayCode, array());
		}

		$listFields = array(
			// "avatarImage",
			// "section",
			// "field_title",
			// "salesRadField",
			"include_categories",
			"childCats",
			// "include_groups",
			// "linesFriends",
			// "linesMyAddr",
			"linesOwners",
			// "linesGuests",
			"catAuto",
			// "filter_opt",
			"filter",
			"onlyPublished",
			"tags",
			"childTags",
		);
		return array($type, $listFields);
	}

	/**
	 * Restituisce la lista dei campi custom disponibili.
	 *
	 * @param   string  $typeList
	 * @param   array   &$ar  (output: array di oggetti con proprietà text e value)
	 * @param   bool    $all  Se includere tutti i campi o solo quelli base.
	 */
	public function getCustomFields($typeList, &$ar, $all)
	{
		if (!$this->_isInCurrentType($typeList))
			return;

		$obj = new \stdClass();
		$obj->text  = "Default";
		$obj->value = 0;
		$ar = array($obj);
	}

	/**
	 * Costruisce la query per elencare gli articoli/profili già (o da) geocodificati.
	 *
	 * La query restituisce id, titolo, latitudine, longitudine, e altri campi interni.
	 *
	 * @param   string  $type      Rappresenta il tipo di coordinate.
	 * @param   array   $filters   Array di filtri impostati dall'utente.
	 * @param   object  $papa      Oggetto che gestisce lo stato della query.
	 * @param   array   $vAssign   Array associativo per i campi custom.
	 * @return  array   Array con il tipo e la query.
	 */
	public function getListQueryBackGeocode($type, $filters, $papa, $vAssign)
	{
		if (!$this->_isInCurrentType($type))
		{
			return array($this->gatewayCode, null);
		}

		$db = Factory::getDbo();

		// Importa gli articoli non ancora presenti nella tabella GF.
		$query = $db->getQuery(true);
		$query->select('id')
		      ->from($db->quoteName('#__content'))
		      ->where('id NOT IN (SELECT id_content FROM ' . $db->quoteName($this->plgTable) . ' WHERE type=' . $db->quote($this->gatewayOption) . ')');
		$db->setQuery($query);
		$res = $db->loadObjectList();

		if (is_array($res) && count($res) > 0)
		{
			$query->clear();
			$query->insert($db->quoteName('#__geofactory_contents'));
			foreach ($res as $art)
			{
				$query->values(implode(',', array(
					$db->quote(''),
					$db->quote($this->gatewayOption),
					(int)$art->id,
					$db->quote(''),
					(float)$this->defEmptyLat,
					(float)$this->defEmptyLng
				)));
			}
			$db->setQuery($query);
			$db->execute();
		}

		// Costruisci la query per gli articoli già geocodificati.
		$query = $db->getQuery(true);
		$latSql = 'a.latitude';
		$lngSql = 'a.longitude';
		$test = $this->_getValidCoordTest($latSql, $lngSql);

		$query->select(
			$papa->getState(
				'list.select',
				'a.id AS item_id, ' .
				'j.title AS item_name, ' .
				$latSql . ' AS item_latitude, ' .
				$lngSql . ' AS item_longitude, ' .
				$db->quote($type) . ' AS type_ms, ' .
				'IF(' . $test . ',1,0) AS c_status'
			)
		);
		$query->from($db->quoteName($this->plgTable) . ' AS a');
		$query->join('LEFT', '#__content AS j ON j.id = a.id_content');
		$query->where('type = ' . $db->quote($this->gatewayOption));
		$query = $this->_finaliseGetListQueryBackGeocode($query, $filters);

		return array($type, $query);
	}

	/**
	 * Salva le coordinate per un dato articolo/profilo.
	 *
	 * @param   string  $type     Tipo di coordinate.
	 * @param   int     $id       ID dell'elemento.
	 * @param   array   $vCoord   Array con latitudine e longitudine.
	 * @param   array   $vAssign  Array associativo per i campi custom.
	 * @return  array   Array con il tipo e un messaggio di esito.
	 */
	public function setItemCoordinates($type, $id, $vCoord, $vAssign)
	{
		if (!$this->_isInCurrentType($type))
		{
			return array($this->gatewayCode);
		}

		$db = Factory::getDbo();
		$query = $db->getQuery(true);
		$fields = array(
			"latitude = " . (float)$vCoord[0],
			"longitude = " . (float)$vCoord[1]
		);
		$query->update($db->quoteName($this->plgTable))
		      ->set(implode(', ', $fields))
		      ->where("id = " . (int)$id);
		$db->setQuery($query);

		try {
			$db->execute();
		} catch (\Exception $e) {
			return array($type, "Unknown error saving coordinates: " . $e->getMessage());
		}
		return array($type, "Coordinates properly saved (" . implode(' ', $vCoord) . ").");
	}

	/**
	 * Carica le coordinate per uno o più articoli/profili.
	 *
	 * @param   string  $type    Tipo.
	 * @param   array   $ids     ID degli elementi.
	 * @param   array   &$vCoord Array che verrà popolato con le coordinate.
	 * @param   array   $params  Array associativo dei campi custom.
	 */
	public function getItemCoordinates($type, $ids, &$vCoord, $params)
	{
		if (!$this->_isInCurrentType($type))
			return;

		$vCoord = array($this->defEmptyLat, $this->defEmptyLng);
		if (!is_array($ids) && is_int($ids))
			$ids = array($ids);
		if (!is_array($ids) || count($ids) < 1)
			return;

		$db = Factory::getDbo();
		$query = $db->getQuery(true);
		$query->select("id_content, latitude, longitude")
		      ->from($db->quoteName($this->plgTable))
		      ->where('id_content IN (' . implode(',', $ids) . ') AND type = ' . $db->quote($this->gatewayOption));
		$db->setQuery($query);
		$res = $db->loadObjectList();
		if ($db->getError()) {
			trigger_error("getItemCoordinates: DB reports: " . $db->stderr(), E_USER_WARNING);
		}
		if (!is_array($res) || count($res) < 1)
			return;

		foreach ($res as $coor) {
			$vCoord[$coor->id_content] = array($coor->latitude, $coor->longitude);
		}
	}

	/**
	 * Restituisce l'indirizzo postale di un elemento.
	 *
	 * @param   string  $type
	 * @param   int     $id
	 * @param   array   $vAssign  Array associativo per i campi custom.
	 * @return  array   Array contenente il tipo e l'indirizzo (ad es. field_city).
	 */
	public function getItemPostalAddress($type, $id, $vAssign)
	{
		$add = array();
		if (!$this->_isInCurrentType($type))
		{
			return array($this->gatewayCode, $add);
		}

		if (isset($vAssign["field_latitude"]) && $vAssign["field_latitude"] == -2)
		{
			$adresse = $this->_getItemFromArticle($id, 'metakey');
			if (strlen(trim($adresse)) < 1)
				return array($type, $add);
			$add["field_city"] = $adresse;
		}
		else if (isset($vAssign["field_latitude"]) && $vAssign["field_latitude"] == -3)
		{
			$adresse = $this->_getItemFromArticle($id, 'metadesc');
			if (strlen(trim($adresse)) < 1)
				return array($type, $add);
			$add["field_city"] = $adresse;
		}
		else
		{
			$db = Factory::getDbo();
			$query = $db->getQuery(true);
			$query->select("address")
			      ->from($db->quoteName($this->plgTable))
			      ->where("id = " . (int)$id);
			$db->setQuery($query, 0, 1);
			$res = $db->loadObjectList();
			if ($db->getError()) {
				trigger_error("getItemPostalAddress: DB reports: " . $db->stderr(), E_USER_WARNING);
			}
			if (!count($res))
				return array($type, $add);
			$res = $res[0];
			$add["field_city"] = $res->address;
		}

		return array($type, $add);
	}

	/**
	 * Salva l'indirizzo postale per un elemento.
	 *
	 * @param   string  $type
	 * @param   int     $id
	 * @param   array   $vAssign
	 * @param   array   $vAddress   Array con i dati dell'indirizzo.
	 * @return  array   Array contenente il tipo e un messaggio di esito.
	 */
	public function setItemAddress($type, $id, $vAssign, $vAddress)
	{
		if (!$this->_isInCurrentType($type))
		{
			return array($this->gatewayCode);
		}
		if (!$id || !count($vAddress))
			return false;

		// Utilizza l'intera stringa dell'indirizzo
		$add = implode(";", $vAddress);
		$db = Factory::getDbo();
		$value = trim($add);
		$vals = array("address = " . $db->quote($value));
		if (!count($vals))
			return array($type, "Error saving address, nothing to save!");
		$query = $db->getQuery(true);
		$query->update($db->quoteName($this->plgTable))
		      ->set(implode(',', $vals))
		      ->where("id = " . (int)$id);
		$db->setQuery($query);
		try {
			$db->execute();
		} catch (\Exception $e) {
			return array($type, "Unknown error saving address: " . $e->getMessage());
		}
		return array($type, "Address properly saved (" . implode(' ', $vAddress) . ").");
	}

	/**
	 * (Non implementato) Ottiene il percorso completo dell'icona di una categoria.
	 *
	 * @param   string  $type
	 * @param   array   &$listCatIcon
	 */
	public function getRel_idCat_iconPath($type, &$listCatIcon)
	{
		if (!$this->_isInCurrentType($type))
			return false;
	}

	/**
	 * (Non implementato) Restituisce la relazione tra entry e categoria.
	 *
	 * @param   string  $type
	 * @param   array   &$listCatEntry
	 */
	public function getRel_idEntry_idCat($type, &$listCatEntry)
	{
		if (!$this->_isInCurrentType($type))
			return false;
	}

	/**
	 * Restituisce il percorso comune per le icone dei marker.
	 *
	 * @param   string  $type
	 * @param   int     $markerIconType
	 * @param   string  &$iconPathDs
	 */
	public function getIconCommonPath($type, $markerIconType, &$iconPathDs)
	{
		if (!$this->_isInCurrentType($type))
			return false;
		if ($markerIconType == 3)
			return;
		if ($markerIconType == 4)
			return;
	}

	/**
	 * Restituisce tutte le sotto-categorie.
	 *
	 * @param   string  $type
	 * @param   object  &$vCats    Risultato della query (lista di categorie).
	 * @param   int     &$idTopCat ID della categoria radice.
	 */
	public function getAllSubCats($type, &$vCats, &$idTopCat)
	{
		if (!$this->_isInCurrentType($type))
			return;
		$idTopCat = $this->iTopCategory;
		$db = Factory::getDbo();
		$query = $db->getQuery(true);
		$query->select('id as catid, parent_id as parentid, title as title')
		      ->from($db->quoteName('#__categories'))
		      ->where('extension = ' . $db->quote('com_content'))
		      ->order('parent_id');
		$db->setQuery($query);
		$vCats = $db->loadObjectList();
		if ($db->getError()) {
			trigger_error("getAllSubCats: DB reports: " . $db->stderr(), E_USER_WARNING);
		}
	}

	/**
	 * Personalizza la query principale per ottenere i marker.
	 *
	 * @param   string  $type
	 * @param   array   $params     Array con le opzioni (filtri, ecc.)
	 * @param   array   &$sqlSelect Array di SELECT.
	 * @param   array   &$sqlJoin   Array di JOIN.
	 * @param   array   &$sqlWhere  Array di WHERE.
	 */
	public function customiseQuery($type, $params, &$sqlSelect, &$sqlJoin, &$sqlWhere)
	{
		if (!$this->_isInCurrentType($type))
			return;
		$sqlSelect[] = "O.id_content AS id, C.title, O.latitude, O.longitude";
		$sqlJoin[] = "LEFT JOIN #__content AS C ON C.id = O.id_content";
		$sqlWhere[] = "O.type = 'com_content'";
		if (isset($params['linesOwners']) && $params['linesOwners'] == 1)
		{
			$sqlSelect[] = "C.created_by AS owner";
		}
		if (isset($params['tags']) && $params['tags'])
		{
			$sqlJoin[] = "LEFT JOIN #__contentitem_tag_map AS T ON C.id = T.content_item_id AND type_alias = 'com_content.article'";
			$sqlWhere[] = "T.tag_id IN (" . $params['tags'] . ")";
		}
		if (isset($params['inCats']) && strlen($params['inCats']) > 0)
		{
			$sqlWhere[] = "C.catid IN (" . $params['inCats'] . ")";
		}
		$sqlWhere[] = $this->_getPublishedState($params['onlyPublished']);
		$sqlWhere[] = $this->_getValidCoordTest("O.latitude", "O.longitude");
	}

	/**
	 * Costruisce la query principale.
	 *
	 * @param   array   $data      Array con le parti della query già formattate.
	 * @param   string  &$retQuery Query finale.
	 */
	public function getMainQuery($data, &$retQuery)
	{
		if (!$this->_isInCurrentType($data['type']))
			return;
		$queryParts = array();
		$queryParts[] = $data['sqlSelect'];
		$queryParts[] = "FROM " . $this->plgTable . " O";
		$queryParts[] = $data['sqlJoin'];
		$queryParts[] = $data['sqlWhere'];
		$retQuery = implode(" ", $queryParts);
	}

	/**
	 * Imposta i filtri nella query principale.
	 *
	 * @param   string  $type
	 * @param   object  $oMs       Oggetto contenente i filtri.
	 * @param   array   &$sqlSelect
	 * @param   array   &$sqlJoin
	 * @param   array   &$sqlWhere
	 */
	public function setMainQueryFilters($type, $oMs, &$sqlSelect, &$sqlJoin, &$sqlWhere)
	{
		if (!$this->_isInCurrentType($type))
			return;
		if (isset($oMs->filter) && strlen($oMs->filter) > 0)
		{
			$sqlWhere[] = $oMs->filter;
		}
	}

	/**
	 * (Sobipro only) Pulizia del campo immagine – implementazione specifica se necessaria.
	 *
	 * @param   string  $type
	 * @param   string  &$fieldImg
	 */
	public function getIconPathFromBrutDbValue($type, &$fieldImg)
	{
		// Implementare se necessario.
	}

	/**
	 * (CB only) Pulisce i risultati provenienti da altri plugin – implementare se necessario.
	 *
	 * @param   string  $type
	 * @param   string  &$vUid
	 */
	public function cleanResultsFromPlugins($type, &$vUid)
	{
		// Implementare se necessario.
	}

	/**
	 * Imposta il colore della linea (pline) in base alle impostazioni.
	 *
	 * @param   array   &$vCol  Array in cui inserire il colore.
	 */
	public function getColorPline(&$vCol)
	{
		$col = $this->params->get('linesOwners');
		$vCol[$this->gatewayCode . 'linesOwners'] = (strlen($col) > 2) ? $col : $this->defColorPline;
	}

	/**
	 * Recupera un campo da un articolo.
	 *
	 * @param   int     $id         ID dell'elemento.
	 * @param   string  $field      Nome del campo (o "metakey"/"metadesc").
	 * @param   bool    $purejoomla (Opzionale) Se usare il metodo puro di Joomla.
	 * @return  string  Valore del campo.
	 */
	public function _getItemFromArticle($id, $field, $purejoomla = false)
	{
		$db = Factory::getDbo();
		if (strpos($field, 'field') !== false)
		{
			$parts = explode('_', $field);
			$id_field = array_pop($parts);
			$query = $db->getQuery(true);
			$query->select('j.value AS ' . $field)
			      ->from($db->quoteName($this->plgTable) . ' AS a')
			      ->join('LEFT', '#__fields_values AS j ON j.item_id = a.id_content')
			      ->where("j.field_id = " . (int)$id_field . " AND a.id = " . (int)$id);
			$db->setQuery($query, 0, 1);
		}
		else
		{
			$query = $db->getQuery(true);
			$query->select('j.' . $field)
			      ->from($db->quoteName($this->plgTable) . ' AS a')
			      ->join('LEFT', '#__content AS j ON j.id = a.id_content')
			      ->where("a.id = " . (int)$id);
			$db->setQuery($query, 0, 1);
		}
		$res = $db->loadObjectList();
		if (!count($res))
			return '';
		$res = $res[0];
		return $res->$field;
	}

	/**
	 * Costruisce il template del marker (bubble) e prepara i placeholder.
	 *
	 * @param   object  &$objMarker  Oggetto marker da arricchire.
	 * @param   array   $params      Parametri (es. menuId).
	 */
	public function markerTemplateAndPlaceholder(&$objMarker, $params)
	{
		if (!$this->_isInCurrentType($objMarker->type))
			return;

		$menuId = $this->_getMenuItemId(isset($params['menuId']) ? $params['menuId'] : 0);
		$article = Table::getInstance('Content');
		$article->load($objMarker->id);

		// Prepara le immagini: usa un'immagine di placeholder se non presenti.
		$article->image_intro = \JUri::root() . 'media/com_geofactory/assets/blank.png';
		$article->image_fulltext = \JUri::root() . 'media/com_geofactory/assets/blank.png';
		if (strlen($article->images) > 5)
		{
			$images = json_decode($article->images);
			if (isset($images->image_intro) && strlen($images->image_intro) > 3)
			{
				$article->image_intro = \JUri::root() . $images->image_intro;
			}
			if (isset($images->image_fulltext) && strlen($images->image_fulltext) > 3)
			{
				$article->image_fulltext = \JUri::root() . $images->image_fulltext;
			}
		}

		$slug = $article->id . ':' . $article->alias;
		$catslug = $article->catid;
		$objMarker->link = \JRoute::_(ContentHelperRoute::getArticleRoute($slug, $catslug));
		$objMarker->rawTitle = $article->title;

		// Cicla sui campi definiti per la bubble.
		foreach ($this->arBubbleFields as $fName)
		{
			$dispo = "{" . $fName . "}";
			if (stripos($objMarker->template, $dispo) === false)
				continue;
			if ($dispo == '{introtext}')
			{
				$objMarker->replace['{introtext}'] = HTMLHelper::_('content.prepare', $article->introtext);
				continue;
			}
			if ($dispo == '{introtextraw}')
			{
				$objMarker->replace['{introtextraw}'] = HTMLHelper::_('string.truncate', $article->introtext, 0, true, false);
				continue;
			}
			if ($dispo == '{fulltext}')
			{
				$objMarker->replace['{fulltext}'] = HTMLHelper::_('content.prepare', $article->fulltext);
				continue;
			}
			if ($dispo == '{fulltextraw}')
			{
				$objMarker->replace['{fulltextraw}'] = HTMLHelper::_('string.truncate', $article->fulltext, 0, true, false);
				continue;
			}
			if (strpos($dispo, 'field') !== false)
			{
				$db = Factory::getDbo();
				$parts = explode('_', $dispo);
				$id_field = array_pop($parts);
				$query = $db->getQuery(true);
				$query->select('value')
				      ->from($db->quoteName('#__fields_values'))
				      ->where("field_id = " . (int)$id_field . " AND item_id = " . (int)$article->id);
				$db->setQuery($query, 0, 1);
				$res = $db->loadObjectList();
				$objMarker->replace[$dispo] = HTMLHelper::_('content.prepare', $res[0]->value);
				continue;
			}
			$objMarker->replace[$dispo] = $article->$fName;
		}
		// Popola l'array di ricerca con le chiavi dei placeholder.
		foreach ($objMarker->replace as $k => $v)
		{
			$objMarker->search[] = $k;
		}
	}

	/**
	 * Crea un array di placeholder per il template builder della bubble.
	 *
	 * @param   string  $typeList
	 * @param   array   &$placeHolders   Array associativo dei placeholder.
	 */
	public function getPlaceHoldersTemplate($typeList, &$placeHolders)
	{
		if (!$this->_isInCurrentType($typeList))
			return;
		$placeHolders = array();
		$onlyMe = "Joomla content special";
		if (!isset($placeHolders[$onlyMe]))
			$placeHolders[$onlyMe] = array();
		foreach ($this->arBubbleFields as $fDispo)
		{
			$placeHolders[$onlyMe][$fDispo] = '{' . $fDispo . '}';
		}
	}

	/**
	 * Costruisce il generatore di filtri per la query.
	 *
	 * @param   string  $typeList
	 * @param   string  &$jsPlugin   Codice JavaScript per il generatore.
	 * @param   string  &$txt        Testo esplicativo per l'utente.
	 */
	public function getFilterGenerator($typeList, &$jsPlugin, &$txt)
	{
		if (!$this->_isInCurrentType($typeList))
			return;
		$jsPlugin .= 'result = "( " + field + cond + " \'" + like + value + like + "\' )";';
		$txt .= "&nbsp;&nbsp;SELECT values FROM articles_table WHERE internal_conditions AND <strong>(your_query)</strong>";
		$txt .= "</br></br>With Joomla Content you can use multiple conditions like :</br>";
		$txt .= "&nbsp;&nbsp;SELECT values FROM article_table WHERE internal_conditions AND <strong>((your_query_A) AND/OR (your_query_B))</strong>";
	}

	/**
	 * Aggiunge la condizione sullo stato pubblicato nella query.
	 *
	 * @param   int  $state  0 = pubblicato, 1 = non pubblicato.
	 * @return  string
	 */
	public function _getPublishedState($state)
	{
		if ($state == 1)
			return 'C.state = 0';
		return 'C.state > 0';
	}

	/**
	 * Recupera un valore da un articolo (usato per metakey, metadesc, o campi custom).
	 *
	 * @param   int     $id
	 * @param   string  $field
	 * @param   bool    $purejoomla (Opzionale)
	 * @return  string
	 */
	public function _getItemFromArticle($id, $field, $purejoomla = false)
	{
		$db = Factory::getDbo();
		if (strpos($field, 'field') !== false)
		{
			$parts = explode('_', $field);
			$id_field = array_pop($parts);
			$query = $db->getQuery(true);
			$query->select('j.value AS ' . $field)
			      ->from($db->quoteName($this->plgTable) . ' AS a')
			      ->join('LEFT', '#__fields_values AS j ON j.item_id = a.id_content')
			      ->where("j.field_id = " . (int)$id_field . " AND a.id = " . (int)$id);
			$db->setQuery($query, 0, 1);
		}
		else
		{
			$query = $db->getQuery(true);
			$query->select('j.' . $field)
			      ->from($db->quoteName($this->plgTable) . ' AS a')
			      ->join('LEFT', '#__content AS j ON j.id = a.id_content')
			      ->where("a.id = " . (int)$id);
			$db->setQuery($query, 0, 1);
		}
		$res = $db->loadObjectList();
		if (!count($res))
			return '';
		$res = $res[0];
		return $res->$field;
	}
}
