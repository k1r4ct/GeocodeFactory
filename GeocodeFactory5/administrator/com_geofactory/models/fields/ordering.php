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

defined('JPATH_BASE') or die;

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;

class JFormFieldOrdering extends ListField
{
    protected $type = 'Ordering';

    protected function getInput()
    {
        $html = array();
        $attr = '';
        $attr .= isset($this->element['class']) ? ' class="' . (string)$this->element['class'] . '"' : '';
        $attr .= ((string)$this->element['disabled'] == 'true') ? ' disabled="disabled"' : '';
        $attr .= isset($this->element['size']) ? ' size="' . (int)$this->element['size'] . '"' : '';
        $attr .= isset($this->element['onchange']) ? ' onchange="' . (string)$this->element['onchange'] . '"' : '';

        $bannerId = (int)$this->form->getValue('id');
        $categoryId = (int)$this->form->getValue('catid');

        $query = 'SELECT ordering AS value, name AS text'
               . ' FROM #__banners'
               . ' WHERE catid = ' . (int)$categoryId
               . ' ORDER BY ordering';

        // Otteniamo una istanza del database di Joomla 4
        $db = Factory::getDbo();
        $db->setQuery($query);
        
        // Otteniamo i risultati come array associativo
        $items = $db->loadObjectList();
        
        if ((string)$this->element['readonly'] == 'true') {
            // Crea la lista di ordinamento per elementi in sola lettura
            $html[] = HTMLHelper::_('select.genericlist', $items, '', trim($attr), 'value', 'text', $this->value, $this->id);
            $html[] = '<input type="hidden" name="' . $this->name . '" value="' . $this->value . '"/>';
        } else {
            // Crea la lista di ordinamento interattiva
            $html[] = HTMLHelper::_('select.genericlist', $items, $this->name, trim($attr), 'value', 'text', $this->value, $this->id);
        }

        return implode('', $html);
    }
}