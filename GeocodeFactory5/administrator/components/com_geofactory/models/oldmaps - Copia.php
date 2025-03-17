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

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Language\Text;
use Joomla\Utilities\ArrayHelper;

class GeofactoryModelOldmaps extends ListModel
{
    /**
     * Build an SQL query to load the list data.
     *
     * @return  \JDatabaseQuery
     */
    protected function getListQuery()
    {
        $app    = Factory::getApplication();
        $prefix = $app->getCfg('dbprefix');

        $db = $this->getDbo();

        // Prova a connettersi a un'altra database
        $config = ComponentHelper::getParams('com_geofactory');
        $extDb  = $config->get('import-database');
        if (strlen($extDb) > 0) {
            $prefix = $config->get('import-prefix');
            $db = GeofactoryHelperAdm::loadExternalDb();
            parent::setDbo($db);
        }

        $tables = $db->getTableList();

        if (!in_array($prefix . "geocode_factory_maps", $tables)) {
            $app->enqueueMessage(Text::_('COM_GEOFACTORY_IMP_NO_OLD_TABLES'), 'error');
        }

        $query = $db->getQuery(true);

        $query->select(
            $this->getState(
                'list.select',
                'a.id AS id,' .
                'a.title AS title'
            )
        );

        $query->from($db->quoteName('#__geocode_factory_maps') . ' AS a');

        // Join per contare i markersets
        $query->select('COUNT(ms.id) as nbrMs');
        $query->join('LEFT', $db->quoteName('#__geocode_factory_markersets') . ' AS ms ON a.id = ms.id_map');

        $query->group('a.id, a.title');

        // echo nl2br(str_replace('#__','pec30_',$query));
        return $query;
    }

    public function import()
    {
        $app = Factory::getApplication();
        $ids = $app->input->post->get('cid', array(), 'array');
        ArrayHelper::toInteger($ids);
        if (!is_array($ids)) {
            $ids = array($ids);
        }

        foreach ($ids as $id) {
            $this->_importMap($id);
        }
    }

    protected function _importMap($idMap)
    {
        $config = ComponentHelper::getParams('com_geofactory');
        $prefix = trim($config->get('prefix_old', ''));

        // Carica l'ancienne mappa
        $old = Table::getInstance('Oldmap', 'GeofactoryTable');
        $old->load($idMap);
        if ($old->id < 1) {
            $this->setError(Text::sprintf('COM_GEOFACTORY_IMP_ERR_LOAD_MAP', $idMap));
            return;
        }

        // Crea una nuova mappa
        $new = Table::getInstance('Ggmap', 'GeofactoryTable');
        $new->importFromOldGF($old);
        $new->name = (strlen($prefix) > 0) ? $prefix . '_' . $new->name : $new->name;

        $new->check();
        if (!$new->store()) {
            // Correzione: usa $new->getError() anziché $table->getError()
            $new->setError($new->getError());
        }
        $newMapId = $new->id;

        // Recupera la lista dei markersets collegati all'ancienne mappa
        $idsMs = $this->_getOldIdsMs($idMap);
        ArrayHelper::toInteger($idsMs);
        if (!is_array($idsMs)) {
            $idsMs = array($idsMs);
        }
        if (count($idsMs) < 1) {
            return;
        }

        foreach ($idsMs as $idMs) {
            if ($idMs < 1) {
                continue;
            }
            $this->_importMs($idMs, $idMap, $prefix, $newMapId);
        }
    }

    // Ricerca gli ID degli old markersets per la mappa
    protected function _getOldIdsMs($idM)
    {
        $db = Factory::getDbo();

        $config = ComponentHelper::getParams('com_geofactory');
        $extDb  = $config->get('import-database');
        if (strlen($extDb) > 0) {
            $db = GeofactoryHelperAdm::loadExternalDb();
            parent::setDbo($db);
        }

        $query = $db->getQuery(true);
        $query->select('id');
        $query->from($db->quoteName('#__geocode_factory_markersets'));
        $query->where('id_map = ' . (int)$idM);
        $query->order($db->escape('ordering') . ' ' . $db->escape('ASC'));
        $db->setQuery($query);

        $res = $db->loadObjectList();
        $olds = array();
        foreach ($res as $r) {
            $olds[] = $r->id;
        }
        return $olds;
    }

    protected function _importMs($idMs, $idMap, $prefix, $newMapId)
    {
        // Carica l'ancien markerset
        $old = Table::getInstance('OldMarkerset', 'GeofactoryTable');
        $old->load($idMs);
        $old->idmaps = GeofactoryHelperAdm::getArrayMapsFromMs($idMs);

        if ($old->id < 1) {
            $this->setError(Text::sprintf('COM_GEOFACTORY_IMP_ERR_LOAD_MS', $idMs));
            return;
        }

        // Crea un nuovo markerset
        $new = Table::getInstance('Markerset', 'GeofactoryTable');
        $new->importFromOldMS($old, $idMap, $newMapId);
        $new->name = (strlen($prefix) > 0) ? $prefix . '_' . $new->name : $new->name;
        $new->check();
        if (!$new->store()) {
            $new->setError($new->getError());
        }

        $new->bindMapMarkerset($newMapId);
    }
}
