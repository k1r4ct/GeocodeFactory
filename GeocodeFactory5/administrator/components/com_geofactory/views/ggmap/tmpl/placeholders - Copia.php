<?php
/**
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later
 * @author      Cédric Pelloquin aka Rick <info@myJoom.com>
 * @update      Daniele Bellante
 * @website     www.myJoom.com
 */

use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Dispatcher\Dispatcher;
use Joomla\CMS\HTML\HTMLHelper;

defined('_JEXEC') or die;

HTMLHelper::_('bootstrap.tooltip');

// Carica eventuali plugin che espongono code DynCat
PluginHelper::importPlugin('geocodefactory');
$dsp = Dispatcher::getInstance();

$available = [];
$dsp->trigger('getCodeDynCat', [&$available]);

// Se il plugin di level non è installato, si mostra una label
$buylevel = (!PluginHelper::getPlugin('geocodefactory', 'plg_geofactory_levels'))
    ? '<br />' . Text::_('COM_GEOFACTORY_BUY_LEVEL_PLUGIN')
    : '';

// Elenco segnaposto
$vCodes = [];
$vCodes['Content'] = [
    '{map}'     => Text::_('COM_GEOFACTORY_PH_VAR_MAP'),
    '{number}'  => Text::_('COM_GEOFACTORY_PH_VAR_NUMBER'),
    '{route}'   => Text::_('COM_GEOFACTORY_PH_VAR_ROUTE'),
];

$vCodes['Level plugin'] = [
    '{level_simple_check}'      => Text::_('COM_GEOFACTORY_PLG_LEVEL_CHECK') . $buylevel,
    '{level_icon_simple_check}' => Text::_('COM_GEOFACTORY_PLG_LEVEL_ICON_CHECK') . $buylevel,
];

$vCodes['Localisation'] = [
    '{radius_form}' => Text::_('COM_GEOFACTORY_PH_VAR_RADIUS'),
    '{reset_map}'    => Text::_('COM_GEOFACTORY_PH_VAR_RESET_MAP'),
    '{locate_me}'    => Text::_('COM_GEOFACTORY_PH_VAR_LOCATE'),
    '{near_me}'      => Text::_('COM_GEOFACTORY_PH_VAR_NEAR_ME'),
];

$vCodes['Markerset selector'] = [
    '{selector}'            => Text::_('COM_GEOFACTORY_PH_VAR_SELECTOR'),
    '{selector_1}'          => Text::_('COM_GEOFACTORY_PH_VAR_SELECTOR'),
    '{multi_selector}'      => Text::_('COM_GEOFACTORY_PH_VAR_MULTI_SELECTOR'),
    '{multi_selector_1}'    => Text::_('COM_GEOFACTORY_PH_VAR_MULTI_SELECTOR'),
    '{ullist_img}'          => Text::_('COM_GEOFACTORY_PH_VAR_TOGGLE_SELECTOR'),
    '{toggle_selector}'     => Text::_('COM_GEOFACTORY_PH_VAR_TOGGLE_SELECTOR'),
    '{toggle_selector_1}'   => Text::_('COM_GEOFACTORY_PH_VAR_TOGGLE_SELECTOR'),
    '{toggle_selector_icon_1}' => Text::_('COM_GEOFACTORY_PH_VAR_TOGGLE_SELECTOR'),
    '{toggle_selector_icon}'   => Text::_('COM_GEOFACTORY_PH_VAR_TOGGLE_SELECTOR'),
    '{sidelists_premium}'   => Text::_('COM_GEOFACTORY_PH_VAR_PREMIUM'),
];

// Se un plugin esterno ha aggiunto codici dynCat
if (!empty($available)) {
    foreach ($available as $av) {
        // ad es. {dyncat MS_xx#00}
        $vCodes['Markerset selector']["{dyncat $av#00}"] = Text::_('COM_GEOFACTORY_PH_VAR_DYN_CAT');
    }
}

$vCodes['Side content'] = [
    '{sidelists}' => Text::_('COM_GEOFACTORY_PH_VAR_SIDELISTS'),
    '{sidebar}'   => Text::_('COM_GEOFACTORY_PH_VAR_SIDEBAR'),
];
?>

<fieldset>
    <?php echo HTMLHelper::_('bootstrap.startTabSet', 'myTab', ['active' => 'codes']); ?>

    <?php echo HTMLHelper::_('bootstrap.addTab', 'myTab', 'codes', Text::_('COM_GEOFACTORY_PLACEHOLDERS')); ?>
    <table class="table table-striped table-condensed">
        <thead>
            <tr>
                <th width="33%" class="nowrap"><?php echo Text::_('COM_GEOFACTORY_PLACEHOLDERS_INSERT_CODE_TEMPLATE'); ?></th>
                <th width="67%" class="nowrap"><?php echo Text::_('COM_GEOFACTORY_PLACEHOLDERS_HELP'); ?></th>
            </tr>
        </thead>

        <?php foreach ($vCodes as $title => $curCodes): ?>
            <thead>
                <tr>
                    <td colspan="2"><h3><?php echo $title; ?></h3></td>
                </tr>
            </thead>
            <tbody>
                <?php
                $i=0;
                foreach ($curCodes as $code => $help):
                ?>
                    <tr class="row<?php echo $i % 2; ?>">
                        <td>
                            <button class="btn btn-info"
                                    style="width: 150px;"
                                    onclick="if(window.parent) window.parent.addCtrlInTpl('<?php echo addslashes($code); ?>');">
                                <?php echo htmlspecialchars($code); ?>
                            </button>
                        </td>
                        <td><?php echo $help; ?></td>
                    </tr>
                <?php $i++; endforeach; ?>
            </tbody>
        <?php endforeach; ?>
    </table>
    <?php echo HTMLHelper::_('bootstrap.endTab'); ?>

    <?php echo HTMLHelper::_('bootstrap.addTab', 'myTab', 'sample', Text::_('COM_GEOFACTORY_SAMPLE_TEMPLATE')); ?>
    <textarea class="field span12" rows="18">
<div class="container-fluid">
    <div class="row">
        <div class="col-md-4" id="gf_sideTemplateCont">
            <div id="gf_sideTemplateCtrl">
                <div class="well">
                    <div id="gf_btn_superfull" style="display:none;" onclick="superFull({mapvar}.map);return false;">
                        <a id="reset" href="#"><i class="glyphicon glyphicon-chevron-right"></i> Reduce</a>
                    </div>
                    <h4>Search near<small> (<a id="find_me" href="#" onClick="{mapvar}.LMBTN();">Find me</a>)</small></h4>
                    <p><input type="text" id="addressInput" value="" class="gfMapControls" placeholder="Enter an address or intersection" /></p>
                    <p>
                        <label>Within {rad_distances}</label>
                    </p>
                    <h4>Categories</h4>
                    {ullist_img}
                    <br />
                    <a class="btn btn-primary" id="search" href="#" onclick="{mapvar}.SLFI();">
                        <i class="glyphicon glyphicon-search"></i> Search
                    </a>
                    <a class="btn btn-default" id="reset" href="#" onclick="{mapvar}.SLRES();">
                        <i class="glyphicon glyphicon-repeat"></i> Reset
                    </a>
                    {layer_selector}
                </div>
                <div class="alert alert-info" id="result_box">
                    <h4>Result {number}</h4>
                    {sidelists}
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <noscript>
                <div class="alert alert-info">
                    <h4>Your JavaScript is disabled</h4>
                    <p>Please enable JavaScript to view the map.</p>
                </div>
            </noscript>
            {map}
            <br />
            <div class="alert alert-info" id="route_box">
                <h4>Click a marker to reach</h4>
                {route}
            </div>
        </div>
    </div>
</div>
<div id="gf_panelback"
     style="cursor:pointer;float:right;display:none;position:fixed;width:20px;height:100%;top:0;right:0;z-index:100; background-color:silver!important; background: url(/media/com_geofactory/assets/arrow-left.png) no-repeat center"
     onclick="normalFull({mapvar}.map);">
     <div></div>
</div>
    </textarea>
    <?php echo HTMLHelper::_('bootstrap.endTab'); ?>
    <?php echo HTMLHelper::_('bootstrap.endTabSet'); ?>
</fieldset>
