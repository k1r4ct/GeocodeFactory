<?php
/**
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Cédric Pelloquin aka Rick <info@myJoom.com>
 * @website     www.myJoom.com
 * @update      Daniele Bellante
 */
defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Log\Log;
use Joomla\Event\Event;
use Joomla\Event\DispatcherInterface;
use Joomla\Database\DatabaseInterface;

/**
 * Classe helper per i plugin di geocodefactory
 * 
 * @since  1.0
 */
class GeofactoryPluginHelper extends CMSPlugin
{
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

    /**
     * Funzione comune in sola lettura.
     *
     * @param   string  $type  Tipo di plugin
     * @param   bool    &$flag  Flag (passato per riferimento)
     * @return  void
     * @since   1.0
     */
    public function isIconAvatarEntrySupported($type, &$flag)
    {
        if (!$this->isInCurrentType($type)) {
            return;
        }
        $flag = $this->isSupportAvatar;
    }

    /**
     * Funzione comune in sola lettura.
     *
     * @param   string  $type  Tipo di plugin
     * @param   bool    &$flag  Flag (passato per riferimento)
     * @return  void
     * @since   1.0
     */
    public function isIconCategorySupported($type, &$flag)
    {
        if (!$this->isInCurrentType($type)) {
            return;
        }
        $flag = $this->isSupportCatIcon;
    }

    /**
     * Funzione comune in sola lettura.
     *
     * @param   string  $type  Tipo di plugin
     * @param   bool    &$flag  Flag (passato per riferimento)
     * @return  void
     * @since   1.0
     */
    public function getIsSingleGpsField($type, &$flag)
    {
        if (!$this->isInCurrentType($type)) {
            return;
        }
        $flag = $this->isSingleGpsField;
    }

    /**
     * Verifica il contesto corrente e definisce se è il tipo corretto.
     *
     * @param   string  $type  Tipo di plugin
     * @param   string  $ssType  Sottotipo
     * @param   bool    &$isOnCurItem  Flag (passato per riferimento)
     * @return  void
     * @since   1.0
     */
    public function isOnCurContext($type, $ssType, &$isOnCurItem)
    {
        if (!$this->isInCurrentType($type)) {
            return;
        }
        if ($this->gatewayCode != $ssType) {
            return;
        }
        $isOnCurItem = true;
    }

    /**
     * Verifica se il plugin è installato per il tipo dato.
     *
     * @param   string  $type  Tipo di plugin
     * @param   bool    &$flag  Flag (passato per riferimento)
     * @return  void
     * @since   1.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onIsPluginInstalled' => 'isPluginInstalled',
        ];
    }

     public function isPluginInstalled($event)
    {
        if (!$this->isInCurrentType($event->getArgument('type'))) {
            return;
        }
        return true;
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
}