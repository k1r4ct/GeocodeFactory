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

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Uri\Uri;

// Aggiunge il percorso per eventuali override degli helper HTML
HTMLHelper::addIncludePath(JPATH_COMPONENT . '/helpers/html');

// Carica il comportamento per la multiselezione
HTMLHelper::_('behavior.multiselect');

// Per i tooltip, usiamo il comportamento Bootstrap
HTMLHelper::_('bootstrap.tooltip');

$config      = ComponentHelper::getParams('com_geofactory');
$showMinimap = trim($config->get('showMinimap'));
$ggApikeySt  = trim($config->get('ggApikeySt'));
$ggApikey    = (strlen($ggApikeySt) > 4) ? '&key=' . $ggApikeySt : '';
$geocoded    = $this->escape($this->state->get('filter.geocoded'));

$http = $config->get('sslSite');
if (empty($http)) {
    // Verifica se HTTPS è abilitato
    $http = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
}
?>
<script type="text/javascript">
function loadAddress(id, type, gglink){
    const arData = {};
    arData['idCur'] = id;
    arData['type'] = type;
    arData['assign'] = document.getElementById('assign').value;
    arData['gglink'] = gglink;

    fetch('index.php?option=com_geofactory&task=geocodes.getaddress&' + new URLSearchParams(arData).toString(), {
        method: 'GET'
    })
    .then(response => response.text())
    .then(data => {
        if (gglink) {
            document.getElementById('gglink_' + id).innerHTML = data;
        } else {
            document.getElementById('address_' + id).innerHTML = data;
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

function geocodeItem(id, type){
    const urlAx = 'index.php?option=com_geofactory&task=geocodes.geocodeuniqueitem';
    const arData = {};
    arData['cur'] = id;
    arData['type'] = type;
    arData['assign'] = document.getElementById('assign').value;

    document.getElementById('gglink_' + id).innerHTML = '...geocoding...';
    
    fetch(urlAx + '&' + new URLSearchParams(arData).toString(), {
        method: 'GET'
    })
    .then(response => response.text())
    .then(data => {
        document.getElementById('gglink_' + id).innerHTML = data;
        document.getElementById('imagemap').style.display = 'none';
    })
    .catch(error => {
        console.error('Error:', error);
    });
}
</script>

<form action="<?php echo Route::_('index.php?option=com_geofactory&view=geocodes'); ?>"
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
                <div class="col-md-8">
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
                <div class="col-md-4">
                    <select name="filter_geocoded" id="filter_geocoded" class="form-select" onchange="this.form.submit();">
                        <option value="0"><?php echo Text::_('COM_GEOFACTORY_ALL'); ?></option>
                        <option value="1" <?php if ((int)$geocoded === 1) echo 'selected="selected"'; ?>>
                            <?php echo Text::_('COM_GEOFACTORY_FILTER_GEOCODED'); ?>
                        </option>
                        <option value="2" <?php if ((int)$geocoded === 2) echo 'selected="selected"'; ?>>
                            <?php echo Text::_('COM_GEOFACTORY_FILTER_GEOCODED_NOT'); ?>
                        </option>
                    </select>
                </div>
            </div>

            <?php
            // Se la query ha restituito un record fittizio zero, esci.
            if (count($this->items) == 1 && (empty($this->items[0]->item_id) || $this->items[0]->item_id == 0)) {
                echo '<div class="alert alert-info">' . Text::_('JGLOBAL_NO_MATCHING_RESULTS') . '</div>';
                return;
            }
            ?>

            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th class="w-1 text-center">
                            <?php echo HTMLHelper::_('grid.checkall'); ?>
                        </th>
                        <th class="w-30">
                            <?php echo Text::_('JFIELD_TITLE_DESC'); ?>
                        </th>
                        <th class="w-30">
                            <?php echo Text::_('COM_GEOFACTORY_ADDRESS'); ?>
                        </th>
                        <th class="w-30">
                            <?php echo Text::_('COM_GEOFACTORY_COORDINATES'); ?>
                        </th>
                        <th class="w-1 text-center">
                            <?php echo Text::_('JGRID_HEADING_ID'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->items as $i => $item) : ?>
                        <?php if (!isset($item->item_id) || $item->item_id == 0) continue; ?>
                        <tr>
                            <td class="text-center">
                                <?php echo HTMLHelper::_('grid.id', $i, $item->item_id); ?>
                            </td>
                            <td>
                                <?php echo $item->item_name; ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-primary btn-sm" 
                                       onclick="loadAddress('<?php echo $item->item_id; ?>','<?php echo $item->type_ms; ?>', 0);">
                                    Load address
                                </button>
                                <div id="address_<?php echo $item->item_id; ?>"></div>
                            </td>
                            <td>
                                <button type="button" class="btn btn-success btn-sm"
                                       onclick="geocodeItem('<?php echo $item->item_id; ?>','<?php echo $item->type_ms; ?>');">
                                    <?php echo Text::_('COM_GEOFACTORY_GEOCODE_THIS'); ?>
                                </button>
                                <?php if ((strlen($item->item_latitude) > 2) && ($item->item_latitude != 255)) : ?>
                                    <?php $cooGg = $item->item_latitude . ',' . $item->item_longitude; ?>
                                    <?php if ($showMinimap == 1) : ?>
                                        <br />
                                        <a href="<?php echo $http; ?>maps.google.com/maps?q=<?php echo $cooGg; ?>" target="_blank">
                                            <img id="imagemap"
                                                 src="<?php echo $http; ?>maps.googleapis.com/maps/api/staticmap?center=<?php echo $cooGg; ?>&zoom=15&size=250x150&markers=<?php echo $cooGg; ?><?php echo $ggApikey; ?>"
                                                 alt="Map" class="img-thumbnail" />
                                        </a>
                                    <?php else: ?>
                                        <br />
                                        <a href="<?php echo $http; ?>maps.google.com/maps?q=<?php echo $cooGg; ?>" 
                                           class="btn btn-info btn-sm" target="_blank">
                                            <?php echo Text::_('COM_GEOFACTORY_SEE_ON_GGMAP'); ?>
                                        </a>
                                    <?php endif; ?>
                                    <div id="gglink_<?php echo $item->item_id; ?>"></div>
                                <?php else: ?>
                                    <button type="button" class="btn btn-outline-info btn-sm"
                                           onclick="loadAddress('<?php echo $item->item_id; ?>','<?php echo $item->type_ms; ?>', 1);">
                                        <?php echo Text::_('COM_GEOFACTORY_SHOW_RESULT'); ?>
                                    </button>
                                    <div id="gglink_<?php echo $item->item_id; ?>"></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php echo $item->item_id; ?>
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
            <input type="hidden" id="assign" name="assign" value="<?php echo $this->escape($this->state->get('filter.assign')); ?>" />
            <input type="hidden" name="boxchecked" value="0" />
            <?php echo HTMLHelper::_('form.token'); ?>
        </div>
    </div>
</form>