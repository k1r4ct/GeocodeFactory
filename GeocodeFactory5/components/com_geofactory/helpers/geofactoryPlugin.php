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

class GeofactoryPluginHelper extends CMSPlugin
{
    /**
     * Retourne les informations du plugin pour l'affichage dans les listes du backend.
     *
     * @return array
     * @since 1.0
     */
    public function getPlgInfo()
    {
        // Pour les TPC à multidirectoires, on peut fusionner ici les répertoires internes.
        $this->_mergeInternalDirectories();
        // Dans les cas standards, on retourne simplement $this->vGatewayInfo.
        return $this->vGatewayInfo;
    }

    /**
     * Fonction commune en lecture seule.
     *
     * @param string $type
     * @param bool   $flag (passé par référence)
     * @since 1.0
     */
    public function isProfile($type, &$flag)
    {
        if (!$this->_isInCurrentType($type)) {
            return;
        }
        $flag = $this->isProfileCom;
    }

    /**
     * Fonction commune en lecture seule.
     *
     * @param string $type
     * @param bool   $flag (passé par référence)
     * @since 1.0
     */
    public function isEvent($type, &$flag)
    {
        if (!$this->_isInCurrentType($type)) {
            return;
        }
        if (isset($this->isEventCom) && $this->isEventCom === true) {
            $flag = true;
        }
    }

    /**
     * Fonction commune en lecture seule.
     *
     * @param string $type
     * @param bool   $flag (passé par référence)
     * @since 1.0
     */
    public function isSpecialMs($type, &$flag)
    {
        if (!$this->_isInCurrentType($type)) {
            return;
        }
        if (isset($this->isSpecialMs) && $this->isSpecialMs === true) {
            $flag = true;
        }
    }

    /**
     * Fonction commune en lecture seule.
     *
     * @param string $type
     * @param bool   $flag (passé par référence)
     * @since 1.0
     */
    public function isIconAvatarEntrySupported($type, &$flag)
    {
        if (!$this->_isInCurrentType($type)) {
            return;
        }
        $flag = $this->isSupportAvatar;
    }

    /**
     * Fonction commune en lecture seule.
     *
     * @param string $type
     * @param bool   $flag (passé par référence)
     * @since 1.0
     */
    public function isIconCategorySupported($type, &$flag)
    {
        if (!$this->_isInCurrentType($type)) {
            return;
        }
        $flag = $this->isSupportCatIcon;
    }

    /**
     * Fonction commune en lecture seule.
     *
     * @param string $type
     * @param bool   $flag (passé par référence)
     * @since 1.0
     */
    public function getIsSingleGpsField($type, &$flag)
    {
        if (!$this->_isInCurrentType($type)) {
            return;
        }
        $flag = $this->isSingleGpsField;
    }

    /**
     * Vérifie le contexte courant et définit s'il s'agit du bon type.
     *
     * @param string $type
     * @param string $ssType
     * @param bool   $isOnCurItem (passé par référence)
     * @since 1.0
     */
    public function isOnCurContext($type, $ssType, &$isOnCurItem)
    {
        if (!$this->_isInCurrentType($type)) {
            return;
        }
        if ($this->gatewayCode != $ssType) {
            return;
        }
        $isOnCurItem = true;
    }

    /**
     * Vérifie si le plugin est installé pour le type donné.
     *
     * @param string $type
     * @param bool   $flag (passé par référence)
     * @since 1.0
     */
    public function isPluginInstalled($type, &$flag)
    {
        if (!$this->_isInCurrentType($type)) {
            return;
        }
        $flag = true;
    }

    /**
     * Retourne la liste des champs d'assignation.
     *
     * @param string $type
     * @return array
     * @since 1.0
     */
    public function getListFieldsAssign($type)
    {
        $listFields = [];
        if (!$this->_isInCurrentType($type)) {
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
     * INTERNAL - Retourne l'ID du sous-dossier à partir du typeList.
     *
     * @param string $typeList
     * @return int
     * @since 1.0
     */
    protected function _getSubDirIdFromTypeListe($typeList)
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
     * Vérifie si le "short name" de l'URL est utilisable par ce plugin.
     *
     * @param string $type
     * @param string $ext
     * @param bool   $ret (passé par référence)
     * @since 1.0
     */
    public function isMyShortName($type, $ext, &$ret)
    {
        if (!$this->_isInCurrentType($type)) {
            return;
        }
        if (!$this->_isInCurrentType($ext)) {
            return;
        }
        $ret = true;
    }

    /**
     * INTERNAL - Vérifie si le plugin doit s'exécuter pour le bon type.
     *
     * @param string $type
     * @return bool
     * @since 1.0
     */
    protected function _isInCurrentType($type)
    {
        $this->_mergeInternalDirectories();
        foreach ($this->vGatewayInfo as $gi) {
            if (strtolower($type) == strtolower($gi[0])) {
                return true;
            }
        }
        return false;
    }

    /**
     * INTERNAL - Retourne l'ID de l'élément de menu.
     *
     * @param int $itemid
     * @return int
     * @since 1.0
     */
    protected function _getMenuItemId($itemid = 0)
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
     * INTERNAL - Ajoute une condition WHERE pour tester la validité des coordonnées.
     *
     * @param string $fieldLat
     * @param string $fieldLng
     * @return string
     * @since 1.0
     */
    protected function _getValidCoordTest($fieldLat, $fieldLng)
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
     * INTERNAL - Finalise la requête de liste pour la géocodification.
     *
     * @param  object $query
     * @param  array  $filters
     * @return object
     * @since 1.0
     */
    protected function _finaliseGetListQueryBackGeocode($query, $filters)
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
     * INTERNAL - Génère une URL complète.
     *
     * @param string $href
     * @return string
     * @since 1.0
     */
    protected function _genericUrl($href)
    {
        $href = str_replace('&amp;', '&', $href);
        $uri = \Joomla\CMS\Uri\Uri::getInstance();
        $prefix = $uri->toString(array('scheme', 'host', 'port'));
        return $prefix . Route::_($href);
    }

    /**
     * Retourne tous les tags.
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
        $db->setQuery($query);
        $vCats = $db->loadObjectList();
        if ($db->getError()) {
            trigger_error("getAllTags: DB reports: " . $db->stderr(), E_USER_WARNING);
        }
    }

    /**
     * INTERNAL - Retourne les sous-catégories d'une liste de catégories.
     *
     * @param array  $categoryList
     * @param mixed  &$par
     * @param array  &$vRes
     * @param string $indent
     * @since 1.0
     */
    public static function _getChildCatOf($categoryList, &$par, &$vRes, $indent)
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
                    // Appel récursif
                    self::_getChildCatOf($categoryList, $category->catid, $vRes, $indent);
                }
            }
        }
    }
}
