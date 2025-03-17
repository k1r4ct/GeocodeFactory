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

use Joomla\CMS\Form\FormField;
use Joomla\CMS\HTML\HTMLHelper;

class JFormFieldOrdering extends FormField
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

        if ((string)$this->element['readonly'] == 'true') {
            $html[] = HTMLHelper::_('list.ordering', '', $query, trim($attr), $this->value, $bannerId ? 0 : 1);
            $html[] = '<input type="hidden" name="' . $this->name . '" value="' . $this->value . '"/>';
        } else {
            $html[] = HTMLHelper::_('list.ordering', $this->name, $query, trim($attr), $this->value, $bannerId ? 0 : 1);
        }

        return implode('', $html);
    }
}
