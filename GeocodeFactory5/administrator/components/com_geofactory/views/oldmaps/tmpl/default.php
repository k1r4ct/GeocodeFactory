<?php
/**
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @update      Daniele Bellante
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;

// Aggiunge il path per i layout helper
HTMLHelper::addIncludePath(JPATH_COMPONENT . '/helpers/html');

// Comportamenti Joomla aggiornati
HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('behavior.multiselect');
HTMLHelper::_('dropdown.init');
// Se preferisci select2, puoi usare HTMLHelper::_('select2.init');
HTMLHelper::_('formbehavior.chosen', 'select');

$user      = Factory::getUser();
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$params    = isset($this->state->params) ? $this->state->params : new \stdClass;
$archived  = ($this->state->get('filter.published') == 2);
$trashed   = ($this->state->get('filter.published') == -2);

// Definisce i campi di ordinamento
$sortFields = [
    (object)['value' => 'a.title', 'text' => Text::_('JGLOBAL_TITLE')],
    (object)['value' => 'a.id', 'text' => Text::_('JGRID_HEADING_ID')],
];
?>

<script type="text/javascript">
Joomla.orderTable = function(){
    var table = document.getElementById("sortTable");
    var direction = document.getElementById("directionTable");
    var order = table.options[table.selectedIndex].value;
    var dirn;
    if (order != '<?php echo $listOrder; ?>'){
        dirn = 'asc';
    } else {
        dirn = direction.options[direction.selectedIndex].value;
    }
    Joomla.tableOrdering(order, dirn, '');
}
</script>

<form action="<?php echo Route::_('index.php?option=com_geofactory&view=oldmaps'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <!-- Se la sidebar è presente, usa le classi Bootstrap 5 -->
        <?php if (!empty($this->sidebar)) : ?>
            <div id="j-sidebar-container_mj" class="col-md-2">
                <?php echo $this->sidebar; ?>
            </div>
            <div id="j-main-container" class="col-md-10">
        <?php else : ?>
            <div id="j-main-container">
        <?php endif; ?>
                <div id="filter-bar" class="btn-toolbar mb-3">
                    <div class="filter-search btn-group float-start">
                        <label for="filter_search" class="visually-hidden">
                            <?php echo Text::_('COM_GEOFACTORY_SEARCH_IN_TITLE'); ?>
                        </label>
                        <input type="text" name="filter_search" id="filter_search" class="form-control" placeholder="<?php echo Text::_('COM_GEOFACTORY_SEARCH_IN_TITLE'); ?>" value="<?php echo $this->escape($this->state->get('filter.search')); ?>" title="<?php echo Text::_('COM_GEOFACTORY_SEARCH_IN_TITLE'); ?>" />
                    </div>
                    <div class="btn-group float-start ms-2">
                        <button class="btn" type="submit" title="<?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?>">
                            <i class="bi bi-search"></i>
                        </button>
                        <button class="btn" type="button" title="<?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?>" onclick="document.getElementById('filter_search').value=''; this.form.submit();">
                            <i class="bi bi-x-circle"></i>
                        </button>
                    </div>
                    <div class="btn-group float-end d-none d-sm-block">
                        <label for="limit" class="visually-hidden"><?php echo Text::_('JFIELD_PLG_SEARCH_SEARCHLIMIT_DESC'); ?></label>
                        <?php echo $this->pagination->getLimitBox(); ?>
                    </div>
                    <div class="btn-group float-end d-none d-sm-block me-2">
                        <label for="directionTable" class="visually-hidden"><?php echo Text::_('JFIELD_ORDERING_DESC'); ?></label>
                        <select name="directionTable" id="directionTable" class="form-select" onchange="Joomla.orderTable()">
                            <option value=""><?php echo Text::_('JFIELD_ORDERING_DESC'); ?></option>
                            <option value="asc" <?php if ($listDirn === 'asc') echo 'selected="selected"'; ?>>
                                <?php echo Text::_('JGLOBAL_ORDER_ASCENDING'); ?>
                            </option>
                            <option value="desc" <?php if ($listDirn === 'desc') echo 'selected="selected"'; ?>>
                                <?php echo Text::_('JGLOBAL_ORDER_DESCENDING'); ?>
                            </option>
                        </select>
                    </div>
                    <div class="btn-group float-end me-2">
                        <label for="sortTable" class="visually-hidden"><?php echo Text::_('JGLOBAL_SORT_BY'); ?></label>
                        <select name="sortTable" id="sortTable" class="form-select" onchange="Joomla.orderTable()">
                            <option value=""><?php echo Text::_('JGLOBAL_SORT_BY'); ?></option>
                            <?php
                            foreach ($sortFields as $field) {
                                $sel = ($listOrder === $field->value) ? 'selected="selected"' : '';
                                echo '<option value="' . $field->value . '" ' . $sel . '>' . $field->text . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="clearfix mb-3"></div>

                <table class="table table-striped" id="markersetsList">
                    <thead>
                        <tr>
                            <th width="1%" class="text-nowrap text-center d-none d-sm-table-cell">
                                <?php echo HTMLHelper::_('grid.sort', '<i class="bi bi-list"></i>', 'ordering', $listDirn, $listOrder, null, 'asc', 'JGRID_HEADING_ORDERING'); ?>
                            </th>
                            <th width="1%" class="d-none d-sm-table-cell">
                                <input type="checkbox" name="checkall-toggle" value="" title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" />
                            </th>
                            <th width="5%" class="text-center">
                                <?php echo Text::_('JSTATUS'); ?>
                            </th>
                            <th>
                                <?php echo Text::_('COM_GEOFACTORY_MARKERSETS_HEADING'); ?>
                            </th>
                            <th width="20%">
                                <?php echo Text::_('COM_GEOFACTORY_FOR_EXTENSION'); ?>
                            </th>
                            <th width="20%" class="d-none d-sm-table-cell">
                                <?php echo Text::_('COM_GEOFACTORY_MARKERSETS_NBR_MAPS'); ?>
                            </th>
                            <th width="1%" class="text-nowrap d-none d-sm-table-cell">
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
                            ?>
                            <tr class="row<?php echo $i % 2; ?>">
                                <td class="order text-nowrap text-center d-none d-sm-table-cell">
                                    <?php if ($canChange) :
                                        $disableClassName = '';
                                        $disabledLabel    = '';
                                        if (!$saveOrder) :
                                            $disabledLabel = Text::_('JORDERINGDISABLED');
                                            $disableClassName = 'inactive tip-top';
                                        endif; ?>
                                        <span class="sortable-handler hasTooltip <?php echo $disableClassName; ?>" title="<?php echo $disabledLabel; ?>">
                                            <i class="bi bi-list"></i>
                                        </span>
                                        <input type="text" style="display:none" name="order[]" size="5" value="<?php echo $item->ordering; ?>" class="width-20 text-area-order" />
                                    <?php else : ?>
                                        <span class="sortable-handler inactive">
                                            <i class="bi bi-list"></i>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center d-none d-sm-table-cell">
                                    <?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
                                </td>
                                <td class="text-center">
                                    <?php echo HTMLHelper::_('jgrid.published', $item->state, $i, 'markersets.', $canChange); ?>
                                </td>
                                <td class="text-nowrap has-context">
                                    <div class="float-start">
                                        <?php if ($item->checked_out) : ?>
                                            <?php echo HTMLHelper::_('jgrid.checkedout', $i, $item->editor, $item->checked_out_time, 'markersets.', $canCheckin); ?>
                                        <?php endif; ?>
                                        <?php if ($canEdit) : ?>
                                            <a href="<?php echo Route::_('index.php?option=com_geofactory&task=markerset.edit&id=' . (int) $item->id); ?>">
                                                <?php echo $this->escape($item->name); ?></a>
                                        <?php else : ?>
                                            <?php echo $this->escape($item->name); ?>
                                        <?php endif; ?>
                                        <div class="small">
                                            <?php echo $this->escape($item->extrainfo); ?>
                                        </div>
                                    </div>
                                    <div class="float-start ms-2">
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
                                <td class="d-none d-sm-table-cell">
                                    <?php echo $this->escape($item->title); ?>
                                </td>
                                <td class="d-none d-sm-table-cell text-center">
                                    <?php echo $item->nbrMs; ?>
                                </td>
                                <td class="d-none d-sm-table-cell text-center">
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
    </div>
</form>
