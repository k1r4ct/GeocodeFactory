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

require_once JPATH_COMPONENT . '/helpers/geofactory.php';

use Joomla\CMS\Table\Table;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

class GeofactoryTableMarkerset extends Table
{
    // Variabile di lavoro, non salvata in questa tabella ma nella tabella di link
    public $idmaps = null;

    public function __construct(&$db)
    {
        $this->checked_out_time = $db->getNullDate();
        parent::__construct('#__geofactory_markersets', 'id', $db);
    }

    public function bind($array, $ignore = '')
    {
        // Variabile supplementare di lavoro, se presente nel form
        if (isset($array['idmaps'])) {
            $this->idmaps = $array['idmaps'];
        }

        if (isset($array['params_markerset_settings']) && is_array($array['params_markerset_settings'])) {
            $registry = new Registry;
            $registry->loadArray($array['params_markerset_settings']);
            $array['params_markerset_settings'] = (string) $registry;
        }
 
        if (isset($array['params_markerset_radius']) && is_array($array['params_markerset_radius'])) {
            $registry = new Registry;
            $registry->loadArray($array['params_markerset_radius']);
            $array['params_markerset_radius'] = (string) $registry;
        }
 
        if (isset($array['params_markerset_icon']) && is_array($array['params_markerset_icon'])) {
            $registry = new Registry;
            $registry->loadArray($array['params_markerset_icon']);
            $array['params_markerset_icon'] = (string) $registry;
        }
 
        if (isset($array['params_markerset_type_setting']) && is_array($array['params_markerset_type_setting'])) {
            $registry = new Registry;
            $registry->loadArray($array['params_markerset_type_setting']);
            $array['params_markerset_type_setting'] = (string) $registry;
        }

        if (isset($array['params_extra']) && is_array($array['params_extra'])) {
            $registry = new Registry;
            $registry->loadArray($array['params_extra']);
            $array['params_extra'] = (string) $registry;
        }

        return parent::bind($array, $ignore);
    }

    public function check()
    {
        $this->name = htmlspecialchars_decode($this->name, ENT_QUOTES);

        // Ordering
        if ($this->state < 0) {
            // Se archiviato o in cestino, ordering a 0
            $this->ordering = 0;
        } elseif (empty($this->ordering)) {
            $this->ordering = self::getNextOrder($this->_db->quoteName('id') . '=' . $this->_db->quote($this->id) . ' AND state>=0');
        }

        return true;
    }

    // Salva e aggiunge le carte nella tabella di link
    public function store($updateNulls = false)
    {
        parent::store($updateNulls);
        $this->_addMarkersetToLink();
        return count($this->getErrors()) == 0;
    }

    protected function _addMarkersetToLink()
    {
        // Se chiamato dal form; altrimenti (es. ordering) non intervenire
        if (!isset($this->idmaps))
            return;

        if (!is_array($this->idmaps) || !count($this->idmaps))
            return;

        $vals = array();
        $this->_deleteLink();

        foreach ($this->idmaps as $id) {
            if ($id < 1)
                continue;
            $vals[] = "({$this->id},{$id})";
        }

        if (!count($vals))
            return;

        $this->_db->setQuery("INSERT INTO #__geofactory_link_map_ms (id_ms,id_map) VALUES " . implode(',', $vals));
        $this->_db->execute();
    }

    protected function _deleteLink()
    {
        $this->_db->setQuery("DELETE FROM #__geofactory_link_map_ms WHERE id_ms={$this->id}");
        $this->_db->execute();
    }

    public function delete($pk = null)
    {
        $this->_deleteLink();
        return parent::delete($pk);
    }

    public function importFromOldMS($old, $idM)
    {
        // Base data
        $this->name = $old->setname;
        $this->template_bubble = $old->bubble_template;
        $this->template_bubble = str_replace('[', '{', $this->template_bubble);
        $this->template_bubble = str_replace(']', '}', $this->template_bubble);
        $this->template_bubble = str_replace('&lt;', '<', $this->template_bubble);
        $this->template_bubble = str_replace('&gt;', '>', $this->template_bubble);
        $this->template_bubble = str_replace('&quot;', '"', $this->template_bubble);

        $this->template_sidebar = $old->sidebar_template;
        $this->template_sidebar = str_replace('[', '{', $this->template_sidebar);
        $this->template_sidebar = str_replace(']', '}', $this->template_sidebar);
        $this->template_sidebar = str_replace('&lt;', '<', $this->template_sidebar);
        $this->template_sidebar = str_replace('&gt;', '>', $this->template_sidebar);
        $this->template_sidebar = str_replace('&quot;', '"', $this->template_sidebar);

        $this->state = 1;
        $this->extrainfo = "Backend markerset description ...";
        $this->ordering = $old->ordering;

        // Cerca di trovare il markerset corrispondente
        $this->typeList = (isset($old->section) && $old->section > 0) ? $old->typeList . "-" . $old->section : $old->typeList;

        // Prova a trovare l'assign di default
        $vType = GeofactoryHelperAdm::getArrayObjAssign($this->typeList);
        $assign = 0;
        if (is_array($vType) && count($vType) > 0)
            $assign = $vType[0]->value;

        // Cambio di valori per ottenere il valore di accuratezza corretto
        $acc = 0;
        switch ($old->accuracy) {
            case 3: $acc = 5; break;
            case 1: $acc = 25; break;
            case 2: $acc = 75; break;
            case 3: $acc = 150; break;
        }

        $params_markerset_settings = array(
            'allow_groups' => $old->allow_groups,
            'accuracy' => $acc,
            'j_menu_id' => $old->j_menu_id,
            'field_assignation' => $assign,
            'bubblewidth' => $old->bubblewidth
        );

        $params_markerset_radius = array(
            'rad_distance' => $old->rad_distance,
            'rad_unit' => $old->rad_unit,
            'rad_mode' => $old->rad_mode
        );

        $params_markerset_icon = array(
            'markerIconType' => $old->markerIconType,
            'customimage' => $old->marker,
            'avatarSizeW' => $old->avatarSizeW,
            'avatarSizeH' => $old->avatarSizeH,
            'mapicon' => $old->mapicon
        );

        $params_markerset_type_setting = array(
            'filter' => $old->filter
        );

        // Specifico per le varie estensioni
        if ($this->typeList == "MS_CB") {
            $params_markerset_type_setting['field_title'] = $old->field_title;
            $params_markerset_type_setting['include_groups'] = $old->include_groups;
            $params_markerset_type_setting['salesRadField'] = $old->salesRadField;
            $params_markerset_type_setting['onlyOnline'] = $old->onlyOnline;
            $params_markerset_type_setting['onlineTmp'] = $old->onlineTmp;
            $params_markerset_type_setting['offlineTmp'] = $old->offlineTmp;
        }
        if ($this->typeList == "MS_JS") {
            $params_markerset_type_setting['field_title'] = $old->field_title;
            $params_markerset_type_setting['include_groups'] = $old->include_groups;
            $params_markerset_type_setting['salesRadField'] = $old->salesRadField;
            $params_markerset_type_setting['onlyOnline'] = $old->onlyOnline;
            $params_markerset_type_setting['onlineTmp'] = $old->onlineTmp;
            $params_markerset_type_setting['offlineTmp'] = $old->offlineTmp;
        }
        if ($this->typeList == "MS_S2") {
            $params_markerset_type_setting['include_categories'] = $old->include_categories;
            $params_markerset_type_setting['linesOwners'] = $old->pline;
            $params_markerset_type_setting['salesRadField'] = $old->salesRadField;
            $params_markerset_type_setting['catAuto'] = $old->catAuto;
        }
        if ($this->typeList == "MS_MT") {
            $params_markerset_type_setting['include_categories'] = $old->include_categories;
            $params_markerset_type_setting['linesOwners'] = $old->pline;
            $params_markerset_type_setting['salesRadField'] = $old->salesRadField;
            $params_markerset_type_setting['catAuto'] = $old->catAuto;
        }
        if ($this->typeList == "MS_JSEV") {
            $params_markerset_type_setting['include_categories'] = $old->include_categories;
            $params_markerset_type_setting['linesOwners'] = $old->pline;
        }
        if ($this->typeList == "MS_SP") {
            $params_markerset_icon['avatarImage'] = $old->avatarImage;
            $params_markerset_icon['section'] = $old->section;
            $params_markerset_type_setting['include_categories'] = $old->include_categories;
            $params_markerset_type_setting['linesOwners'] = $old->pline;
            $params_markerset_type_setting['salesRadField'] = $old->salesRadField;
            $params_markerset_type_setting['catAuto'] = $old->catAuto;
            $params_markerset_type_setting['filter_opt'] = $old->filter_opt;
        }
        if ($this->typeList == "MS_AM") {
            $params_markerset_type_setting['include_categories'] = $old->include_categories;
            $params_markerset_type_setting['linesOwners'] = $old->pline;
            $params_markerset_type_setting['salesRadField'] = $old->salesRadField;
            $params_markerset_type_setting['catAuto'] = $old->catAuto;
        }
        if ($this->typeList == "MS_JE") {
            $params_markerset_type_setting['include_categories'] = $old->include_categories;
            $params_markerset_type_setting['linesOwners'] = $old->pline;
            $params_markerset_type_setting['dateFormat'] = $old->dateFormat;
            $params_markerset_type_setting['allEvents'] = $old->allEvents;
        }
        if ($this->typeList == "MS_JC") {
            $params_markerset_type_setting['include_categories'] = $old->include_categories;
            $params_markerset_type_setting['linesOwners'] = $old->pline;
            $params_markerset_type_setting['catAuto'] = $old->catAuto;
        }
        if ($this->typeList == "MS_GT") {
            $params_markerset_type_setting['include_categories'] = $old->include_categories;
            $params_markerset_type_setting['linesOwners'] = $old->pline;
            $params_markerset_type_setting['dateFormat'] = $old->dateFormat;
        }

        $registry = new Registry;
        $registry->loadArray($params_markerset_settings);
        $this->params_markerset_settings = (string) $registry;

        $registry = new Registry;
        $registry->loadArray($params_markerset_radius);
        $this->params_markerset_radius = (string) $registry;
        
        $registry = new Registry;
        $registry->loadArray($params_markerset_icon);
        $this->params_markerset_icon = (string) $registry;

        $registry = new Registry;
        $registry->loadArray($params_markerset_type_setting);
        $this->params_markerset_type_setting = (string) $registry;
    }

    public function bindMapMarkerset($newMapId)
    {
        $db = $this->getDbo();
        $db->setQuery("INSERT INTO #__geofactory_link_map_ms (id_ms,id_map) VALUES ({$this->id},{$newMapId})");
        $db->execute();
    }

    /**
     * Method to set the publishing state for a row or list of rows in the database table.
     *
     * @param   array|null  $pks     An optional array of primary key values to update.
     * @param   integer     $state   The publishing state. eg. [0 = unpublished, 1 = published]
     * @param   integer     $userId  The user id of the user performing the operation.
     * @return  boolean     True on success.
     * @since   1.0.4
     */
    public function publish($pks = null, $state = 1, $userId = 0)
    {
        $k = $this->_tbl_key;
        ArrayHelper::toInteger($pks);
        $userId = (int) $userId;
        $state = (int) $state;

        if (empty($pks))
        {
            if ($this->$k)
            {
                $pks = array($this->$k);
            }
            else {
                $this->setError(Text::_('JLIB_DATABASE_ERROR_NO_ROWS_SELECTED'));
                return false;
            }
        }

        $where = $k . '=' . implode(' OR ' . $k . '=', $pks);

        if (!property_exists($this, 'checked_out') || !property_exists($this, 'checked_out_time'))
        {
            $checkin = '';
        }
        else
        {
            $checkin = ' AND (checked_out = 0 OR checked_out = ' . (int) $userId . ')';
        }

        $db = $this->_db;
        $db->setQuery(
            'UPDATE ' . $db->quoteName($this->_tbl) .
            ' SET ' . $db->quoteName('state') . ' = ' . (int) $state .
            ' WHERE (' . $where . ')' . $checkin
        );

        try {
            $db->execute();
        }
        catch (\RuntimeException $e) {
            $this->setError($e->getMessage());
            return false;
        }

        if ($checkin && (count($pks) == $db->getAffectedRows()))
        {
            foreach ($pks as $pk)
            {
                $this->checkin($pk);
            }
        }

        if (in_array($this->$k, $pks))
        {
            $this->state = $state;
        }
        $this->setError('');
        return true;
    }
}
