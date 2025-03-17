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
use Joomla\CMS\Layout\LayoutHelper;

HTMLHelper::_('bootstrap.framework');
HTMLHelper::_('behavior.multiselect');
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
    const table = document.getElementById("sortTable");
    const direction = document.getElementById("directionTable");
    const order = table.options[table.selectedIndex].value;
    let dirn = 'asc';
    
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

    <div class="row">
        <?php if (!empty($this->sidebar)) : ?>
            <div id="j-sidebar-container" class="col-md-2">
                <?php echo $this->sidebar; ?>
            </div>
        <?php endif; ?>
        
        <div id="j-main-container" class="col-md-<?php echo !empty($this->sidebar) ? '10' : '12'; ?>">
            <div class="row mb-3">
                <div class="col-md-12">
                    <div class="input-group">
                        <input type="text" name="filter_search"
                               id="filter_search"
                               placeholder="<?php echo Text::_('COM_GEOFACTORY_SEARCH_IN_TITLE'); ?>"
                               value="<?php echo $this->escape($this->state->get('filter.search')); ?>"
                               class="form-control"
                               title="<?php echo Text::_('COM_GEOFACTORY_SEARCH_IN_TITLE'); ?>" />
                        <button type="submit" class="btn btn-primary">
                            <span class="icon-search" aria-hidden="true"></span>
                            <span class="visually-hidden"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></span>
                        </button>
                        <button type="button" class="btn btn-secondary"
                                onclick="document.getElementById('filter_search').value='';this.form.submit();">
                            <span class="icon-x" aria-hidden="true"></span>
                            <span class="visually-hidden"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-12 d-flex justify-content-end">
                    <div class="me-2">
                        <select name="directionTable" id="directionTable" class="form-select" onchange="Joomla.orderTable()">
                            <option value=""><?php echo Text::_('JFIELD_ORDERING_DESC'); ?></option>
                            <option value="asc" <?php if ($listDirn == 'asc')  echo 'selected="selected"'; ?>>
                                <?php echo Text::_('JGLOBAL_ORDER_ASCENDING'); ?>
                            </option>
                            <option value="desc" <?php if ($listDirn == 'desc') echo 'selected="selected"'; ?>>
                                <?php echo Text::_('JGLOBAL_ORDER_DESCENDING'); ?>
                            </option>
                        </select>
                    </div>
                    <div class="me-2">
                        <select name="sortTable" id="sortTable" class="form-select" onchange="Joomla.orderTable()">
                            <option value=""><?php echo Text::_('JGLOBAL_SORT_BY'); ?></option>
                            <?php echo HTMLHelper::_('select.options', $sortFields, 'value', 'text', $listOrder); ?>
                        </select>
                    </div>
                    <div>
                        <?php echo $this->pagination->getLimitBox(); ?>
                    </div>
                </div>
            </div>

            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th class="w-1 text-center">
                            <?php echo HTMLHelper::_('grid.checkall'); ?>
                        </th>
                        <th class="w-5 text-center">
                            <?php echo Text::_('JSTATUS'); ?>
                        </th>
                        <th>
                            <?php echo Text::_('COM_GEOFACTORY_PATTERN_NAME'); ?>
                        </th>
                        <th class="w-20 d-none d-md-table-cell">
                            <?php echo Text::_('COM_GEOFACTORY_PATTERN_FOR'); ?>
                        </th>
                        <th class="w-5 text-center">
                            ID
                        </th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($this->items as $i => $item) :
                    $canCreate  = $user->authorise('core.create',     'com_geofactory');
                    $canEdit    = $user->authorise('core.edit',       'com_geofactory');
                    $canCheckin = $user->authorise('core.manage',     'com_checkin')
                                  || $item->checked_out == $user->get('id')
                                  || $item->checked_out == 0;
                    $canChange  = $user->authorise('core.edit.state', 'com_geofactory') && $canCheckin;
                    ?>
                    <tr>
                        <td class="text-center">
                            <?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
                        </td>
                        <td class="text-center">
                            <?php echo HTMLHelper::_('jgrid.published', $item->state, $i, 'assigns.', $canChange); ?>
                        </td>
                        <td>
                            <div class="d-flex">
                                <div>
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

                                <div class="ms-auto">
                                    <?php if ($canEdit) : ?>
                                        <a class="btn btn-sm btn-primary" 
                                           href="<?php echo Route::_('index.php?option=com_geofactory&task=assign.edit&id='.(int)$item->id); ?>"
                                           title="<?php echo Text::_('JACTION_EDIT'); ?>">
                                            <span class="icon-edit" aria-hidden="true"></span>
                                        </a>
                                    <?php endif; ?>

                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                            <span class="icon-ellipsis-h" aria-hidden="true"></span>
                                            <span class="visually-hidden"><?php echo Text::_('JACTION_OPTIONS'); ?></span>
                                        </button>
                                        
                                        <ul class="dropdown-menu">
                                            <?php if ($canChange && $item->state) : ?>
                                                <li>
                                                    <a class="dropdown-item" href="javascript:void(0);"
                                                       onclick="return Joomla.listItemTask('cb<?php echo $i; ?>','assigns.unpublish')">
                                                        <span class="icon-unpublish" aria-hidden="true"></span>
                                                        <?php echo Text::_('JTOOLBAR_UNPUBLISH'); ?>
                                                    </a>
                                                </li>
                                            <?php elseif ($canChange) : ?>
                                                <li>
                                                    <a class="dropdown-item" href="javascript:void(0);"
                                                       onclick="return Joomla.listItemTask('cb<?php echo $i; ?>','assigns.publish')">
                                                        <span class="icon-publish" aria-hidden="true"></span>
                                                        <?php echo Text::_('JTOOLBAR_PUBLISH'); ?>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php if ($canChange) : ?>
                                                <li>
                                                    <?php if ($archived) : ?>
                                                        <a class="dropdown-item" href="javascript:void(0);"
                                                           onclick="return Joomla.listItemTask('cb<?php echo $i; ?>','assigns.unarchive')">
                                                            <span class="icon-folder" aria-hidden="true"></span>
                                                            <?php echo Text::_('JTOOLBAR_UNARCHIVE'); ?>
                                                        </a>
                                                    <?php else : ?>
                                                        <a class="dropdown-item" href="javascript:void(0);"
                                                           onclick="return Joomla.listItemTask('cb<?php echo $i; ?>','assigns.archive')">
                                                            <span class="icon-archive" aria-hidden="true"></span>
                                                            <?php echo Text::_('JTOOLBAR_ARCHIVE'); ?>
                                                        </a>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php if ($canCheckin && $item->checked_out) : ?>
                                                <li>
                                                    <a class="dropdown-item" href="javascript:void(0);"
                                                       onclick="return Joomla.listItemTask('cb<?php echo $i; ?>','assigns.checkin')">
                                                        <span class="icon-checkin" aria-hidden="true"></span>
                                                        <?php echo Text::_('JTOOLBAR_CHECKIN'); ?>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php if ($canChange) : ?>
                                                <li>
                                                    <?php if ($trashed) : ?>
                                                        <a class="dropdown-item" href="javascript:void(0);"
                                                           onclick="return Joomla.listItemTask('cb<?php echo $i; ?>','assigns.untrash')">
                                                            <span class="icon-trash-restore" aria-hidden="true"></span>
                                                            <?php echo Text::_('JTOOLBAR_UNTRASH'); ?>
                                                        </a>
                                                    <?php else : ?>
                                                        <a class="dropdown-item" href="javascript:void(0);"
                                                           onclick="return Joomla.listItemTask('cb<?php echo $i; ?>','assigns.trash')">
                                                            <span class="icon-trash" aria-hidden="true"></span>
                                                            <?php echo Text::_('JTOOLBAR_TRASH'); ?>
                                                        </a>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="d-none d-md-table-cell">
                            <?php echo $item->typeList; ?>
                        </td>
                        <td class="text-center">
                            <?php echo $item->id; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5">
                            <?php echo $this->pagination->getListFooter(); ?>
                        </td>
                    </tr>
                </tfoot>
            </table>

            <input type="hidden" name="task" value="" />
            <input type="hidden" name="boxchecked" value="0" />
            <input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>" />
            <input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>" />
            <?php echo HTMLHelper::_('form.token'); ?>
        </div>
    </div>
</form>