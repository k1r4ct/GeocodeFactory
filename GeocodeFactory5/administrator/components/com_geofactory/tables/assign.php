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

use Joomla\CMS\Table\Table;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

class GeofactoryTableAssign extends Table
{
    public function __construct(&$db)
    {
        $this->checked_out_time = $db->getNullDate();
        parent::__construct('#__geofactory_assignation', 'id', $db);
    }

    public function check()
    {
        $this->name = htmlspecialchars_decode($this->name, ENT_QUOTES);
        return true;
    }

    public function _existDefault($code, $name)
    {
        $name = "Default - " . $name;

        $db = Factory::getDbo();
        $query = $db->getQuery(true);
        $query->select('COUNT(id)')
              ->from($db->quoteName($this->getTableName()))
              ->where($db->quoteName('typeList') . ' = ' . $db->quote(strtoupper($code)) . ' AND ' . $db->quoteName('name') . ' = ' . $db->quote($name));
        $db->setQuery($query);

        try {
            $count = $db->loadResult();
        } catch (\RuntimeException $e) {
            $this->setError($e->getMessage());
            return 0;
        }

        return $count;
    }

    public function _createDefault($code, $name)
    {
        $nameFormated = "Default - " . $name;
        $new = Table::getInstance('Assign', 'GeofactoryTable', array('dbo' => Factory::getDbo()));
        $new->name = $nameFormated;
        $new->typeList = $code;
        $new->extrainfo = Text::sprintf('COM_GEOFACTORY_DEFAULT_ASSIGN_FOR', $name);
        $new->state = 1;
        $new->check();
        if (!$new->store()) {
            $new->setError($new->getError());
        }
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

        // Sanitize input.
        ArrayHelper::toInteger($pks);
        $userId = (int) $userId;
        $state  = (int) $state;

        // If there are no primary keys set, check to see if the instance key is set.
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

        // Build the WHERE clause for the primary keys.
        $where = $k . '=' . implode(' OR ' . $k . '=', $pks);

        // Determine if there is checkin support for the table.
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

        // If checkin is supported and all rows were adjusted, check them in.
        if ($checkin && (count($pks) == $db->getAffectedRows()))
        {
            foreach ($pks as $pk)
            {
                $this->checkin($pk);
            }
        }

        // If the JTable instance value is in the list of primary keys that were set, set the instance.
        if (in_array($this->$k, $pks))
        {
            $this->state = $state;
        }

        $this->setError('');
        return true;
    }
}
