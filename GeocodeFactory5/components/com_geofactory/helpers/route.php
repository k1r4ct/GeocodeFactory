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

use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Categories\Categories;
use Joomla\CMS\Categories\CategoryNode;

abstract class GeoFactoryHelperRoute
{
    protected static $lookup;

    public static function getMapRoute($catid, $language = 0)
    {
        if ($catid instanceof CategoryNode) {
            $id = $catid->id;
            $category = $catid;
        } else {
            $id = (int)$catid;
            // Nota: Per Joomla 4, adattiamo la gestione delle categorie.
            $category = Categories::getInstance('Weblinks')->get($id);
        }

        if ($id < 1) {
            $link = '';
        } else {
            $link = 'index.php?option=com_weblinks&view=category&id=' . $id;
            $needles = [
                'category' => [$id]
            ];

            if ($language && $language != "*" && Multilanguage::isEnabled()) {
                $db = Factory::getDbo();
                $query = $db->getQuery(true)
                    ->select('a.sef AS sef, a.lang_code AS lang_code')
                    ->from($db->quoteName('#__languages', 'a'));
                $db->setQuery($query);
                $langs = $db->loadObjectList();
                foreach ($langs as $lang) {
                    if ($language == $lang->lang_code) {
                        $link .= '&lang=' . $lang->sef;
                        $needles['language'] = $language;
                    }
                }
            }

            if ($item = self::findItem($needles)) {
                $link .= '&Itemid=' . $item;
            } else {
                if ($category) {
                    $catids = array_reverse($category->getPath());
                    $needles = [
                        'category'   => $catids,
                        'categories' => $catids
                    ];
                    if ($item = self::findItem($needles)) {
                        $link .= '&Itemid=' . $item;
                    } elseif ($item = self::findItem()) {
                        $link .= '&Itemid=' . $item;
                    }
                }
            }
        }
        return $link;
    }

    /**
     * Cerca un elemento di menu corrispondente ai parametri dati.
     * 
     * @param array|null $needles Array di parametri di ricerca
     * @return int|null ID dell'elemento di menu o null se non trovato
     */
    protected static function findItem($needles = null)
    {
        $app = Factory::getApplication();
        $menus = $app->getMenu('site');
        $language = isset($needles['language']) ? $needles['language'] : '*';

        if (!isset(self::$lookup[$language])) {
            self::$lookup[$language] = [];
            $component = ComponentHelper::getComponent('com_weblinks');
            $attributes = ['component_id'];
            $values = [$component->id];
            if ($language != '*') {
                $attributes[] = 'language';
                $values[] = [$needles['language'], '*'];
            }
            $items = $menus->getItems($attributes, $values);
            if ($items) {
                foreach ($items as $item) {
                    if (isset($item->query) && isset($item->query['view'])) {
                        $view = $item->query['view'];
                        if (!isset(self::$lookup[$language][$view])) {
                            self::$lookup[$language][$view] = [];
                        }
                        if (isset($item->query['id'])) {
                            if (!isset(self::$lookup[$language][$view][(int)$item->query['id']]) || $item->language != '*') {
                                self::$lookup[$language][$view][(int)$item->query['id']] = $item->id;
                            }
                        }
                    }
                }
            }
        }

        if ($needles) {
            foreach ($needles as $view => $ids) {
                if (isset(self::$lookup[$language][$view])) {
                    foreach ($ids as $id) {
                        if (isset(self::$lookup[$language][$view][(int)$id])) {
                            return self::$lookup[$language][$view][(int)$id];
                        }
                    }
                }
            }
        }

        $active = $menus->getActive();
        if ($active && ($active->language == '*' || !Multilanguage::isEnabled())) {
            return $active->id;
        }

        $default = $menus->getDefault($language);
        return !empty($default->id) ? $default->id : null;
    }
}