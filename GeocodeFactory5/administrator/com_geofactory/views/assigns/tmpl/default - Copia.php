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

HTMLHelper::addIncludePath(JPATH_COMPONENT . '/helpers/html');
HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('behavior.multiselect');
HTMLHelper::_('dropdown.init');
HTMLHelper::_('formbehavior.chosen', 'select');

$user      = Factory::getUser();
$userId    = $user->get('id');
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$params    = isset($this->state->params) ? $this->state->params : null;
$archived  = ($this->state->get('filter.published') == 2);
$trashed   = ($this->state->get('filter.published') == -2);
$sortFields= $this->getSortFields();
?>
<script type="text/javascript">
Joomla.orderTable = function() {
    var table     = document.getElementById("sortTable");
    var direction = document.getElementById("directionTable");
    var order     = table.options[table.selectedIndex].value;
    var dirn      = 'asc';
    if (order == '<?php echo $listOrder; ?>') {
        dirn = direction.options[direction.selectedIndex].value;
    }
    Joomla.tableOrdering(order, dirn, '');
}
</script>

<form action="<?php echo Route::_('index.php?option=com_geofactory&view=assigns'); ?>"
      method="post"
      name="adminForm"
      id="adminForm">

    <?php if (!empty($this->sidebar)) : ?>
        <div id="j-sidebar-container_mj" class="span2">
            <?php echo $this->sidebar; ?>
        </div>
        <div id="j-main-container" class="span10">
    <?php else : ?>
        <div id="j-main-container">
    <?php endif; ?>

        <div id="filter-bar" class="btn-toolbar">
            <div class="filter-search btn-group pull-left">
                <label for="filter_search" class="element-invisible">
                    <?php echo Text::_('COM_GEOFACTORY_SEARCH_IN_TITLE'); ?>
                </label>
                <input type="text"
                       name="filter_search"
                       id="filter_search"
                       placeholder="<?php echo Text::_('COM_GEOFACTORY_SEARCH_IN_TITLE'); ?>"
                       value="<?php echo $this->escape($this->state->get('filter.search')); ?>"
                       title="<?php echo Text::_('COM_GEOFACTORY_SEARCH_IN_TITLE'); ?>" />
            </div>
            <div class="btn-group pull-left">
                <button class="btn hasTooltip" type="submit"
                        title="<?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?>">
                    <i class="icon-search"></i>
                </button>
                <button class="btn hasTooltip" type="button"
                        title="<?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?>"
                        onclick="document.getElementById('filter_search').value='';this.form.submit();">
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
                <select name="directionTable"
                        id="directionTable"
                        class="input-medium"
                        onchange="Joomla.orderTable()">
                    <option value="">
                        <?php echo Text::_('JFIELD_ORDERING_DESC'); ?>
                    </option>
                    <option value="asc" <?php if ($listDirn == 'asc')  echo 'selected="selected"'; ?>>
                        <?php echo Text::_('JGLOBAL_ORDER_ASCENDING'); ?>
                    </option>
                    <option value="desc" <?php if ($listDirn == 'desc') echo 'selected="selected"'; ?>>
                        <?php echo Text::_('JGLOBAL_ORDER_DESCENDING'); ?>
                    </option>
                </select>
            </div>
            <div class="btn-group pull-right">
                <label for="sortTable" class="element-invisible">
                    <?php echo Text::_('JGLOBAL_SORT_BY'); ?>
                </label>
                <select name="sortTable"
                        id="sortTable"
                        class="input-medium"
                        onchange="Joomla.orderTable()">
                    <option value="">
                        <?php echo Text::_('JGLOBAL_SORT_BY'); ?>
                    </option>
                    <?php echo HTMLHelper::_('select.options', $sortFields, 'value', 'text', $listOrder); ?>
                </select>
            </div>
        </div>

        <div class="clearfix"> </div>

        <table class="table table-striped">
            <thead>
                <tr>
                    <th width="1%" class="hidden-phone">
                        <input type="checkbox" name="checkall-toggle" value=""
                               title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>"
                               onclick="Joomla.checkAll(this)" />
                    </th>
                    <th width="5%" class="center">
                        <?php echo Text::_('JSTATUS'); ?>
                    </th>
                    <th>
                        <?php echo Text::_('COM_GEOFACTORY_PATTERN_NAME'); ?>
                    </th>
                    <th width="20%" class="hidden-phone">
                        <?php echo Text::_('COM_GEOFACTORY_PATTERN_FOR'); ?>
                    </th>
                    <th width="5%" class="center">
                        ID
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
            <?php foreach ($this->items as $i => $item) :
                $canCreate  = $user->authorise('core.create',     'com_geofactory');
                $canEdit    = $user->authorise('core.edit',       'com_geofactory');
                $canCheckin = $user->authorise('core.manage',     'com_checkin')
                              || $item->checked_out == $user->get('id')
                              || $item->checked_out == 0;
                $canChange  = $user->authorise('core.edit.state', 'com_geofactory') && $canCheckin;
                ?>
                <tr class="row<?php echo $i % 2; ?>">
                    <td class="center hidden-phone">
                        <?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
                    </td>
                    <td class="center">
                        <?php echo HTMLHelper::_('jgrid.published', $item->state, $i, 'assigns.', $canChange); ?>
                    </td>
                    <td class="nowrap has-context">
                        <div class="pull-left">
                            <?php if ($item->checked_out) : ?>
                                <?php echo HTMLHelper::_('jgrid.checkedout', $i, $item->editor, $item->checked_out_time, 'assigns.', $canCheckin); ?>
                            <?php endif; ?>

                            <?php if ($canEdit) : ?>
                                <a href="<?php echo Route::_('index.php?option=com_geofactory&task=assign.edit&id='.(int)$item->id); ?>">
                                    <?php echo $this->escape($item->name); ?>
                                </a>
                            <?php else : ?>
                                <?php echo $this->escape($item->name); ?>
                            <?php endif; ?>

                            <div class="small">
                                <?php echo $this->escape($item->extrainfo); ?>
                            </div>
                        </div>
                        <div class="pull-left">
                            <?php
                            // Dropdown
                            HTMLHelper::_('dropdown.edit', $item->id, 'assigns.');
                            HTMLHelper::_('dropdown.divider');
                            if ($item->state) {
                                HTMLHelper::_('dropdown.unpublish', 'cb' . $i, 'assigns.');
                            } else {
                                HTMLHelper::_('dropdown.publish', 'cb' . $i, 'assigns.');
                            }
                            HTMLHelper::_('dropdown.divider');

                            if ($archived) {
                                HTMLHelper::_('dropdown.unarchive', 'cb' . $i, 'assigns.');
                            } else {
                                HTMLHelper::_('dropdown.archive', 'cb' . $i, 'assigns.');
                            }
                            if ($item->checked_out) {
                                HTMLHelper::_('dropdown.checkin', 'cb' . $i, 'assigns.');
                            }
                            if ($trashed) {
                                HTMLHelper::_('dropdown.untrash', 'cb' . $i, 'assigns.');
                            } else {
                                HTMLHelper::_('dropdown.trash', 'cb' . $i, 'assigns.');
                            }

                            echo HTMLHelper::_('dropdown.render');
                            ?>
                        </div>
                    </td>
                    <td class="small hidden-phone">
                        <?php echo $item->typeList; ?>
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
            <input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>" />
            <input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>" />
            <?php echo HTMLHelper::_('form.token'); ?>
        </div>
    </div>
</form>
