<?php
/**
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Cédric Pelloquin aka Rick <info@myJoom.com>
 * @update      Daniele Bellante
 * @website     www.myJoom.com
 */
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseQuery;

class GeofactoryModelMarkersets extends ListModel
{
    public function __construct($config = array())
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = array(
                'id', 'a.id',
                'name', 'a.name',
                'state', 'a.state',
                'ordering', 'a.ordering',
                'checked_out', 'a.checked_out',
                'checked_out_time', 'a.checked_out_time',
                'nbrMaps'
            );
        }
        parent::__construct($config);
    }

    /**
     * Method to auto-populate the model state.
     *
     * Note: Calling getState in this method may result in recursion.
     *
     * @since   1.6
     */
    protected function populateState($ordering = null, $direction = null)
    {
        $app = Factory::getApplication('administrator');

        $search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
        $this->setState('filter.search', $search);

        $state = $this->getUserStateFromRequest($this->context . '.filter.state', 'filter_state', '', 'string');
        $this->setState('filter.state', $state);

        $mapId = $this->getUserStateFromRequest($this->context . '.filter.map_id', 'filter_map_id', '');
        $this->setState('filter.map_id', $mapId);

        $ext = $this->getUserStateFromRequest($this->context . '.filter.extension', 'filter_extension', '');
        $this->setState('filter.extension', $ext);

        $params = ComponentHelper::getParams('com_geofactory');
        $this->setState('params', $params);

        parent::populateState('a.name', 'asc');
    }

    /**
     * Method to get a store id based on model configuration state.
     *
     * @param   string  $id  A prefix for the store id.
     * @return  string  A store id.
     */
    protected function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.access');
        $id .= ':' . $this->getState('filter.state');

        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  DatabaseQuery  The database query object.
     */
    protected function getListQuery()
    {
        $db = $this->getDbo();
        $query = $db->getQuery(true);

        $query->select(
            $this->getState(
                'list.select',
                'a.id AS id,' .
                'a.name AS name,' .
                'a.extrainfo AS extrainfo,' .
                'a.ordering AS ordering,' .
                'a.typeList AS typeList,' .
                'a.checked_out AS checked_out,' .
                'a.checked_out_time AS checked_out_time, ' .
                'a.state AS state'
            )
        );

        $query->from($db->quoteName('#__geofactory_markersets') . ' AS a');

        $query->select('COUNT(lm.id_ms) as nbrMaps');
        $query->join('LEFT', '#__geofactory_link_map_ms AS lm ON a.id = lm.id_ms');

        $query->select('uc.name AS editor');
        $query->join('LEFT', '#__users AS uc ON uc.id = a.checked_out');

        $mapId = $this->getState('filter.map_id');
        if (is_numeric($mapId)) {
            $query->where('lm.id_map=' . (int) $mapId);
        }

        $ext = $this->getState('filter.extension');
        if (strlen($ext) > 3) {
            $query->where('a.typeList=' . $db->Quote($ext));
        }

        $published = $this->getState('filter.state');
        if (is_numeric($published)) {
            $query->where('a.state = ' . (int) $published);
        } elseif ($published === '') {
            $query->where('(a.state IN (0, 1))');
        }

        // In MySQL 8 e modalità strict SQL, tutte le colonne SELECT devono essere presenti in GROUP BY
        $query->group('a.id, a.name, a.extrainfo, a.ordering, a.typeList, a.checked_out, a.checked_out_time, a.state, editor');

        $search = $this->getState('filter.search');
        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $query->where('a.id = ' . (int) substr($search, 3));
            } else {
                $search = $db->Quote('%' . $db->escape($search, true) . '%');
                $query->where('a.name LIKE ' . $search);
            }
        }
        
        $orderCol = $this->state->get('list.ordering', 'a.name');
        $orderDirn = $this->state->get('list.direction', 'ASC');
        
        // Gestisce il caso speciale di ordinamento per il campo 'ordering'
        if ($orderCol == 'a.ordering') {
            $orderCol = $orderDirn . ', a.ordering';
        }
        
        $query->order($db->escape($orderCol . ' ' . $orderDirn));

        return $query;
    }
}