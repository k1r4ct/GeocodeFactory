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
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;

// Aggiunge path per i layout helper
HTMLHelper::addIncludePath(JPATH_COMPONENT . '/helpers/html');

// Comportamento Joomla
HTMLHelper::_('behavior.tooltip');
HTMLHelper::_('behavior.multiselect');
HTMLHelper::_('dropdown.init');

$user   = Factory::getUser();
$userId = $user->get('id');

// Questi dati vengono da ->state e ->pagination
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$params    = isset($this->state->params) ? $this->state->params : null; // era new JObject
$archived  = ($this->state->get('filter.published') == 2);
$trashed   = ($this->state->get('filter.published') == -2);

// SortFields potrebbe dover essere definito altrove
$sortFields = array(
    (object) ['value' => 'a.title', 'text' => Text::_('JGLOBAL_TITLE')],
    (object) ['value' => 'a.id', 'text' => Text::_('JGRID_HEADING_ID')],
);
?>

<form action="<?php echo Route::_('index.php?option=com_geofactory&view=oldmaps'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row-fluid">
        <!-- Begin Content -->
        <div class="span10">
            <div id="filter-bar" class="btn-toolbar">
                <div class="filter-search btn-group pull-left">
                    <label for="filter_search" class="element-invisible">
                        <?php echo Text::_('COM_GEOFACTORY_SEARCH_IN_TITLE'); ?>
                    </label>
                    <input type="text" name="filter_search" id="filter_search"
                           placeholder="<?php echo Text::_('COM_GEOFACTORY_SEARCH_IN_TITLE'); ?>"
                           value="<?php echo $this->escape($this->state->get('filter.search')); ?>"
                           title="<?php echo Text::_('COM_GEOFACTORY_SEARCH_IN_TITLE'); ?>" />
                </div>
                <div class="btn-group pull-left">
                    <button class="btn" type="submit"
                            title="<?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?>">
                        <i class="icon-search"></i>
                    </button>
                    <button class="btn" type="button"
                            title="<?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?>"
                            onclick="document.getElementById('filter_search').value=''; this.form.submit();">
                        <i class="icon-remove"></i>
                    </button>
                </div>
                <div class="btn-group pull-right hidden-phone">
                    <label for="limit" class="element-invisible">
                        <?php echo Text::_('JFIELD_PLG_SEARCH_SEARCHLIMIT_DESC'); ?>
                    </label>
                    <?php echo $this->pagination->getLimitBox(); ?>
                </div>
                <div class="btn-group pull-right hidden-phone">
                    <label for="directionTable" class="element-invisible">
                        <?php echo Text::_('JFIELD_ORDERING_DESC'); ?>
                    </label>
                    <select name="directionTable" id="directionTable" class="input-small" onchange="Joomla.orderTable()">
                        <option value="">
                            <?php echo Text::_('JFIELD_ORDERING_DESC'); ?>
                        </option>
                        <option value="asc" <?php if ($listDirn === 'asc') echo 'selected="selected"'; ?>>
                            <?php echo Text::_('JGLOBAL_ORDER_ASCENDING'); ?>
                        </option>
                        <option value="desc" <?php if ($listDirn === 'desc') echo 'selected="selected"'; ?>>
                            <?php echo Text::_('JGLOBAL_ORDER_DESCENDING'); ?>
                        </option>
                    </select>
                </div>
                <div class="btn-group pull-right">
                    <label for="sortTable" class="element-invisible">
                        <?php echo Text::_('JGLOBAL_SORT_BY'); ?>
                    </label>
                    <select name="sortTable" id="sortTable" class="input-medium" onchange="Joomla.orderTable()">
                        <option value="">
                            <?php echo Text::_('JGLOBAL_SORT_BY'); ?>
                        </option>
                        <?php
                        // Creazione opzioni
                        foreach ($sortFields as $field) {
                            $sel = ($listOrder === $field->value) ? 'selected="selected"' : '';
                            echo '<option value="' . $field->value . '" ' . $sel . '>' . $field->text . '</option>';
                        }
                        ?>
                    </select>
                </div>
                fin
            </div>
            <div class="clearfix"></div>

            <table class="table table-striped">
                <thead>
                    <tr>
                        <th width="1%" class="hidden-phone">
                            <input type="checkbox" name="checkall-toggle" value=""
                                   title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>"
                                   onclick="Joomla.checkAll(this)" />
                        </th>
                        <th>
                            <?php echo Text::_('COM_GEOFACTORY_MAPS_HEADING'); ?>
                        </th>
                        <th width="5%" class="hidden-phone">
                            <?php echo Text::_('COM_GEOFACTORY_MAPS_NBR_MS'); ?>
                        </th>
                        <th width="1%" class="nowrap hidden-phone">
                            <?php echo Text::_('JGRID_HEADING_ID'); ?>
                        </th>
                    </tr>
                </thead>
                <tfoot>
                    <tr>
                        <td colspan="8">
                            <?php echo $this->pagination->getListFooter(); ?>
                        </td>
                    </tr>
                </tfoot>
                <tbody>
                <?php foreach ($this->items as $i => $item) : ?>
                    <tr class="row<?php echo $i % 2; ?>">
                        <td class="center hidden-phone">
                            <?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
                        </td>
                        <td class="nowrap has-context">
                            <div class="pull-left">
                                <?php echo $this->escape($item->title); ?>
                            </div>
                        </td>
                        <td class="center hidden-phone">
                            <?php echo $item->nbrMs; ?>
                        </td>
                        <td class="center hidden-phone">
                            <?php echo $item->id; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div>
                <input type="hidden" name="task" value="" />
                <input type="hidden" name="boxchecked" value="0" />
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
        <!-- End Content -->
    </div>
</form>
