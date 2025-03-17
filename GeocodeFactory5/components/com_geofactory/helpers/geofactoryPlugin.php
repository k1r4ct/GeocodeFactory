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

class GeofactoryPluginHelper extends CMSPlugin
{
    /**
     * Restituisce le informazioni del plugin per la visualizzazione nelle liste del backend.
     *
     * @return array
     * @since 1.0
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
     * @param string $type
     * @param bool   $flag (passato per riferimento)
     * @since 1.0
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
     * @param string $type
     * @param bool   $flag (passato per riferimento)
     * @since 1.0
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
     * @param string $type
     * @param bool   $flag (passato per riferimento)
     * @since 1.0
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
     * @param string $type
     * @param bool   $flag (passato per riferimento)
     * @since 1.0
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
     * @param string $type
     * @param bool   $flag (passato per riferimento)
     * @since 1.0
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
     * @param string $type
     * @param bool   $flag (passato per riferimento)
     * @since 1.0
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
     * @param string $type
     * @param string $ssType
     * @param bool   $isOnCurItem (passato per riferimento)
     * @since 1.0
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
     * @param string $type
     * @param bool   $flag (passato per riferimento)
     * @since 1.0
     */
    public function isPluginInstalled($type, &$flag)
    {
        if (!$this->isInCurrentType($type)) {
            return;
        }
        $flag = true;
    }

    /**
     * Restituisce la lista dei campi di assegnazione.
     *
     * @param string $type
     * @return array
     * @since 1.0
     */
    public function getListFieldsAssign($type)
    {
        $listFields = [];
        if (!$this->isInCurrentType($type)) {
            return [$this->gatewayCode, $listFields];
        }
        if ($this->custom_latitude) {
            $listFields[] = "field_latitude";
        }
        if ($this->custom_longitude) {
            $listFields[] = "field_longitude";
        }
        if ($this->custom_street) {
            $listFields[] = "field_street";
        }
        if ($this->custom_postal) {
            $listFields[] = "field_postal";
        }
        if ($this->custom_city) {
            $listFields[] = "field_city";
        }
        if ($this->custom_county) {
            $listFields[] = "field_county";
        }
        if ($this->custom_state) {
            $listFields[] = "field_state";
        }
        if ($this->custom_country) {
            $listFields[] = "field_country";
        }
        return [$type, $listFields];
    }

    /**
     * INTERNO - Restituisce l'ID della sottodirectory dal typeList.
     *
     * @param string $typeList
     * @return int
     * @since 1.0
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
        return $v[1];
    }

    /**
     * Verifica se il "nome breve" dell'URL è utilizzabile da questo plugin.
     *
     * @param string $type
     * @param string $ext
     * @param bool   $ret (passato per riferimento)
     * @since 1.0
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
     * INTERNO - Verifica se il plugin deve essere eseguito per il tipo corretto.
     *
     * @param string $type
     * @return bool
     * @since 1.0
     */
    protected function isInCurrentType($type)
    {
        $this->mergeInternalDirectories();
        foreach ($this->vGatewayInfo as $gi) {
            if (strtolower($type) == strtolower($gi[0])) {
                return true;
            }
        }
        return false;
    }

    /**
     * INTERNO - Restituisce l'ID dell'elemento di menu.
     *
     * @param int $itemid
     * @return int
     * @since 1.0
     */
    protected function getMenuItemId($itemid = 0)
    {
        if ($itemid > 0) {
            return $itemid;
        }
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
                    ->select('id')
                    ->from($db->quoteName('#__menu'))
                    ->where("link LIKE '%index.php?option={$this->gatewayOption}%' AND type='component' AND published='1'");
        $db->setQuery($query, 0, 1);
        return $db->loadResult();
    }

    /**
     * INTERNO - Aggiunge una condizione WHERE per testare la validità delle coordinate.
     *
     * @param string $fieldLat
     * @param string $fieldLng
     * @return string
     * @since 1.0
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
        $db = Factory::getDbo();
        $t = "(";
        $t .= "({$fieldLat} <> " . $db->quote("");
        if ($vp) {
            $t .= " AND ({$fieldLat} BETWEEN {$vp[0]} AND {$vp[2]})";
        }
        $t .= " AND {$fieldLat} IS NOT NULL ";
        $t .= " AND {$fieldLat} <> 0 ";
        $t .= " AND {$fieldLat} <> " . $this->defEmptyLat . ")";
        $t .= " OR ";
        $t .= "({$fieldLng} <> " . $db->quote("");
        if ($vp) {
            $t .= " AND ({$fieldLng} BETWEEN {$vp[1]} AND {$vp[3]})";
        }
        $t .= " AND {$fieldLng} IS NOT NULL ";
        $t .= " AND {$fieldLng} <> 0 ";
        $t .= " AND {$fieldLng} <> " . $this->defEmptyLng . ")";
        $t .= ")";
        $t = str_replace(array('\t', '   ', '  '), ' ', $t);
        return $t;
    }

    /**
     * INTERNO - Finalizza la query di lista per la geocodifica.
     *
     * @param  object $query
     * @param  array  $filters
     * @return object
     * @since 1.0
     */
    protected function finaliseGetListQueryBackGeocode($query, $filters)
    {
        $filterSearch = $filters[0];
        $filterGeocoded = $filters[2];
        $listDirection = $filters[1];
        $db = Factory::getDbo();
        $query->group('item_id, item_name, c_status');
        if (!empty($filterSearch)) {
            if (stripos($filterSearch, 'id:') === 0) {
                $query->having('item_id = ' . (int)substr($filterSearch, 3));
            } else {
                $filterSearch = $db->quote('%' . $db->escape($filterSearch, true) . '%');
                $query->having('item_name LIKE ' . $filterSearch);
            }
        }
        if ($filterGeocoded == 1) {
            $query->having('c_status = 1');
        } else if ($filterGeocoded == 2) {
            $query->having('c_status = 0');
        }
        $query->order($db->escape('item_name') . ' ' . $db->escape($listDirection));
        return $query;
    }

    /**
     * INTERNO - Genera un URL completo.
     *
     * @param string $href
     * @return string
     * @since 1.0
     */
    protected function genericUrl($href)
    {
        $href = str_replace('&amp;', '&', $href);
        $uri = \Joomla\CMS\Uri\Uri::getInstance();
        $prefix = $uri->toString(array('scheme', 'host', 'port'));
        return $prefix . Route::_($href);
    }

    /**
     * Restituisce tutti i tag.
     *
     * @param int   $idTopCat
     * @param array &$vCats
     * @since 1.0
     */
    public function getAllTags($idTopCat, &$vCats)
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
                    ->select('id as catid, parent_id as parentid, title')
                    ->from($db->quoteName('#__tags'))
                    ->order('title');
        try {
            $db->setQuery($query);
            $vCats = $db->loadObjectList();
        } catch (\Exception $e) {
            // Utilizziamo il sistema di log di Joomla invece di trigger_error
            Log::add('getAllTags: Errore nel database: ' . $e->getMessage(), Log::ERROR, 'geofactory');
        }
    }

    /**
     * INTERNO - Restituisce le sottocategorie di una lista di categorie.
     *
     * @param array  $categoryList
     * @param mixed  &$par
     * @param array  &$vRes
     * @param string $indent
     * @since 1.0
     */
    public static function getChildCatOf($categoryList, &$par, &$vRes, $indent)
    {
        if (is_string($indent)) {
            $indent .= "- ";
        }
        if (sizeof($categoryList) > 0) {
            foreach ($categoryList as $category) {
                if ($category->parentid == $par) {
                    $vRes[] = is_string($indent)
                        ? HTMLHelper::_('select.option', $category->catid, $indent . stripcslashes(stripslashes(stripslashes($category->title))))
                        : $category->catid;
                    // Chiamata ricorsiva
                    self::getChildCatOf($categoryList, $category->catid, $vRes, $indent);
                }
            }
        }
    }
    
    /**
     * INTERNO - Unisce le directory interne.
     * 
     * Questo metodo è un alias rinominato da _mergeInternalDirectories per seguire
     * le convenzioni di Joomla 4 che evitano i metodi con underscore iniziale.
     * 
     * @since 1.0
     */
    protected function mergeInternalDirectories()
    {
        // Implementare se necessario
        // Questo metodo sostituisce _mergeInternalDirectories
    }
}