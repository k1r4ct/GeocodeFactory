<?php
/**
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      
 * @update      Daniele Bellante
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Factory;

HTMLHelper::addIncludePath(JPATH_COMPONENT . '/helpers/html');
HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('behavior.multiselect');
HTMLHelper::_('dropdown.init');
HTMLHelper::_('formbehavior.chosen', 'select');

$user      = Factory::getUser();
$userId    = $user->get('id');
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$params    = (isset($this->state->params)) ? $this->state->params : new \stdClass;
$archived  = ($this->state->get('filter.published') == 2);
$trashed   = ($this->state->get('filter.published') == -2);
$saveOrder = ($listOrder == 'ordering');

if ($saveOrder)
{
    $saveOrderingUrl = 'index.php?option=com_geofactory&task=markersets.saveOrderAjax&tmpl=component';
    HTMLHelper::_('sortablelist.sortable', 'markersetsList', 'adminForm', strtolower($listDirn), $saveOrderingUrl);
}

$sortFields = $this->getSortFields();
?>
<script type="text/javascript">
    Joomla.orderTable = function(){
        var table = document.getElementById("sortTable");
        var direction = document.getElementById("directionTable");
        var order = table.options[table.selectedIndex].value;
        var dirn;
        if (order != '<?php echo $listOrder; ?>'){
            dirn = 'asc';
        }
        else{
            dirn = direction.options[direction.selectedIndex].value;
        }
        Joomla.tableOrdering(order, dirn, '');
    }
</script>
<form action="<?php echo Route::_('index.php?option=com_geofactory&view=markersets'); ?>" method="post" name="adminForm" id="adminForm">
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
                <label for="filter_search" class="element-invisible"><?php echo Text::_('COM_GEOFACTORY_SEARCH_IN_TITLE'); ?></label>
                <input type="text" name="filter_search" id="filter_search" placeholder="<?php echo Text::_('COM_GEOFACTORY_SEARCH_IN_TITLE'); ?>" value="<?php echo $this->escape($this->state->get('filter.search')); ?>" title="<?php echo Text::_('COM_GEOFACTORY_SEARCH_IN_TITLE'); ?>" />
            </div>
            <div class="btn-group pull-left">
                <button class="btn hasTooltip" type="submit" title="<?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?>"><i class="icon-search"></i></button>
                <button class="btn hasTooltip" type="button" title="<?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?>" onclick="document.getElementById('filter_search').value='';this.form.submit();"><i class="icon-remove"></i></button>
            </div>
            <div class="btn-group pull-right hidden-phone">
                <label for="limit" class="element-invisible"><?php echo Text::_('JFIELD_PLG_SEARCH_SEARCHLIMIT_DESC'); ?></label>
                <?php echo $this->pagination->getLimitBox(); ?>
            </div>
            <div class="btn-group pull-right hidden-phone">
                <label for="directionTable" class="element-invisible"><?php echo Text::_('JFIELD_ORDERING_DESC'); ?></label>
                <select name="directionTable" id="directionTable" class="input-medium" onchange="Joomla.orderTable()">
                    <option value=""><?php echo Text::_('JFIELD_ORDERING_DESC'); ?></option>
                    <option value="asc" <?php if ($listDirn == 'asc') echo 'selected="selected"'; ?>><?php echo Text::_('JGLOBAL_ORDER_ASCENDING'); ?></option>
                    <option value="desc" <?php if ($listDirn == 'desc') echo 'selected="selected"'; ?>><?php echo Text::_('JGLOBAL_ORDER_DESCENDING'); ?></option>
                </select>
            </div>
            <div class="btn-group pull-right">
                <label for="sortTable" class="element-invisible"><?php echo Text::_('JGLOBAL_SORT_BY'); ?></label>
                <select name="sortTable" id="sortTable" class="input-medium" onchange="Joomla.orderTable()">
                    <option value=""><?php echo Text::_('JGLOBAL_SORT_BY'); ?></option>
                    <?php echo HTMLHelper::_('select.options', $sortFields, 'value', 'text', $listOrder); ?>
                </select>
            </div>
        </div>
        <div class="clearfix"></div>
        <table class="table table-striped" id="markersetsList">
            <thead>
                <tr>
                    <th width="1%" class="nowrap center hidden-phone">
                        <?php echo HTMLHelper::_('grid.sort', '<i class="icon-menu-2"></i>', 'ordering', $listDirn, $listOrder, null, 'asc', 'JGRID_HEADING_ORDERING'); ?>
                    </th>
                    <th width="1%" class="hidden-phone">
                        <input type="checkbox" name="checkall-toggle" value="" title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" />
                    </th>
                    <th width="5%" class="center">
                        <?php echo Text::_('JSTATUS'); ?>
                    </th>
                    <th>
                        <?php echo Text::_('COM_GEOFACTORY_MARKERSETS_HEADING'); ?>
                    </th>
                    <th width="20%">
                        <?php echo Text::_('COM_GEOFACTORY_FOR_EXTENSION'); ?>
                    </th>
                    <th width="20%" class="hidden-phone">
                        <?php echo Text::_('COM_GEOFACTORY_MARKERSETS_NBR_MAPS'); ?>
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
            <?php foreach ($this->items as $i => $item) :
                $ordering   = ($listOrder == 'ordering');
                $canCreate  = $user->authorise('core.create', 'com_geofactory');
                $canEdit    = $user->authorise('core.edit', 'com_geofactory');
                $canCheckin = $user->authorise('core.manage', 'com_checkin') || $item->checked_out == $user->get('id') || $item->checked_out == 0;
                $canChange  = $user->authorise('core.edit.state', 'com_geofactory') && $canCheckin;
                $typeListe  = $this->_getTypeListeName($item->typeList);
                $canEdit    = ($canEdit && $typeListe != '?') ? true : false;
                ?>
                <tr class="row<?php echo $i % 2; ?>">
                    <td class="order nowrap center hidden-phone">
                        <?php if ($canChange) :
                            $disableClassName = '';
                            $disabledLabel    = '';
                            if (!$saveOrder) :
                                $disabledLabel = Text::_('JORDERINGDISABLED');
                                $disableClassName = 'inactive tip-top';
                            endif; ?>
                            <span class="sortable-handler hasTooltip <?php echo $disableClassName; ?>" title="<?php echo $disabledLabel; ?>">
                                <i class="icon-menu"></i>
                            </span>
                            <input type="text" style="display:none" name="order[]" size="5" value="<?php echo $item->ordering; ?>" class="width-20 text-area-order" />
                        <?php else : ?>
                            <span class="sortable-handler inactive">
                                <i class="icon-menu"></i>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="center hidden-phone">
                        <?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
                    </td>
                    <td class="center">
                        <?php echo HTMLHelper::_('jgrid.published', $item->state, $i, 'markersets.', $canChange); ?>
                    </td>
                    <td class="nowrap has-context">
                        <div class="pull-left">
                            <?php if ($item->checked_out) : ?>
                                <?php echo HTMLHelper::_('jgrid.checkedout', $i, $item->editor, $item->checked_out_time, 'markersets.', $canCheckin); ?>
                            <?php endif; ?>
                            <?php if ($canEdit) : ?>
                                <a href="<?php echo Route::_('index.php?option=com_geofactory&task=markerset.edit&id=' . (int) $item->id); ?>">
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
                                HTMLHelper::_('dropdown.edit', $item->id, 'markerset.');
                                HTMLHelper::_('dropdown.divider');
                                if ($item->state) :
                                    HTMLHelper::_('dropdown.unpublish', 'cb' . $i, 'markersets.');
                                else :
                                    HTMLHelper::_('dropdown.publish', 'cb' . $i, 'markersets.');
                                endif;
                                HTMLHelper::_('dropdown.divider');
                                if ($archived) :
                                    HTMLHelper::_('dropdown.unarchive', 'cb' . $i, 'markersets.');
                                else :
                                    HTMLHelper::_('dropdown.archive', 'cb' . $i, 'markersets.');
                                endif;
                                if ($item->checked_out) :
                                    HTMLHelper::_('dropdown.checkin', 'cb' . $i, 'markersets.');
                                endif;
                                if ($trashed) :
                                    HTMLHelper::_('dropdown.untrash', 'cb' . $i, 'markersets.');
                                else :
                                    HTMLHelper::_('dropdown.trash', 'cb' . $i, 'markersets.');
                                endif;
                                echo HTMLHelper::_('dropdown.render');
                            ?>
                        </div>
                    </td>
                    <td class="hidden-phone">
                        <?php echo ($typeListe != '?') ? $typeListe : Text::_('COM_GEOCODE_WARNING_MISSING_PLUGIN') . ((!is_numeric($item->typeList)) ? $item->typeList : 'unknown'); ?>
                    </td>
                    <td class="hidden-phone">
                        <?php echo $item->nbrMaps; ?>
                    </td>
                    <td class="hidden-phone">
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
    <!-- End Content -->
    </div>
</form>
