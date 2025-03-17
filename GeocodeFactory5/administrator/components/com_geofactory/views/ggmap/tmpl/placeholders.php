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
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;

defined('_JEXEC') or die;

class GeofactoryPlaceholders
{
    private CMSApplication $app;

    public function __construct()
    {
        $this->app = Factory::getApplication();
        HTMLHelper::_('bootstrap.tooltip');
    }

    /**
     * Recupera i codici disponibili per i segnaposto
     * 
     * @return array
     */
    private function getPlaceholderCodes(): array
    {
        // Carica eventuali plugin che espongono code DynCat
        PluginHelper::importPlugin('geocodefactory');
        $available = [];
        $this->app->triggerEvent('getCodeDynCat', [&$available]);

        // Controllo plugin di level
        $buylevel = (!PluginHelper::getPlugin('geocodefactory', 'plg_geofactory_levels'))
            ? '<br />' . Text::_('COM_GEOFACTORY_BUY_LEVEL_PLUGIN')
            : '';

        // Elenco segnaposto
        $vCodes = [
            'Content' => [
                '{map}'     => Text::_('COM_GEOFACTORY_PH_VAR_MAP'),
                '{number}'  => Text::_('COM_GEOFACTORY_PH_VAR_NUMBER'),
                '{route}'   => Text::_('COM_GEOFACTORY_PH_VAR_ROUTE'),
            ],
            'Level plugin' => [
                '{level_simple_check}'      => Text::_('COM_GEOFACTORY_PLG_LEVEL_CHECK') . $buylevel,
                '{level_icon_simple_check}' => Text::_('COM_GEOFACTORY_PLG_LEVEL_ICON_CHECK') . $buylevel,
            ],
            'Localisation' => [
                '{radius_form}' => Text::_('COM_GEOFACTORY_PH_VAR_RADIUS'),
                '{reset_map}'    => Text::_('COM_GEOFACTORY_PH_VAR_RESET_MAP'),
                '{locate_me}'    => Text::_('COM_GEOFACTORY_PH_VAR_LOCATE'),
                '{near_me}'      => Text::_('COM_GEOFACTORY_PH_VAR_NEAR_ME'),
            ],
            'Markerset selector' => $this->getMarkersetSelectorCodes($available, $buylevel),
            'Side content' => [
                '{sidelists}' => Text::_('COM_GEOFACTORY_PH_VAR_SIDELISTS'),
                '{sidebar}'   => Text::_('COM_GEOFACTORY_PH_VAR_SIDEBAR'),
            ]
        ];

        return $vCodes;
    }

    /**
     * Recupera i codici per il selettore di markerset
     * 
     * @param array $available
     * @param string $buylevel
     * @return array
     */
    private function getMarkersetSelectorCodes(array $available, string $buylevel): array
    {
        $codes = [
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

        // Aggiunge codici dynCat da plugin esterni
        if (!empty($available)) {
            foreach ($available as $av) {
                $codes["{dyncat $av#00}"] = Text::_('COM_GEOFACTORY_PH_VAR_DYN_CAT');
            }
        }

        return $codes;
    }

    /**
     * Renderizza l'HTML per i segnaposto
     * 
     * @return string
     */
    public function render(): string
    {
        $vCodes = $this->getPlaceholderCodes();

        ob_start();
        ?>
        <fieldset>
            <?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => 'codes']); ?>

            <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'codes', Text::_('COM_GEOFACTORY_PLACEHOLDERS')); ?>
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th class="w-33 text-nowrap"><?php echo Text::_('COM_GEOFACTORY_PLACEHOLDERS_INSERT_CODE_TEMPLATE'); ?></th>
                        <th class="w-67 text-nowrap"><?php echo Text::_('COM_GEOFACTORY_PLACEHOLDERS_HELP'); ?></th>
                    </tr>
                </thead>

                <?php foreach ($vCodes as $title => $curCodes): ?>
                    <thead>
                        <tr>
                            <td colspan="2"><h3><?php echo $this->escape($title); ?></h3></td>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $i = 0;
                        foreach ($curCodes as $code => $help):
                        ?>
                            <tr class="row<?php echo $i % 2; ?>">
                                <td>
                                    <button type="button" 
                                            class="btn btn-info" 
                                            style="width: 150px;"
                                            onclick="window.parent?.addCtrlInTpl('<?php echo addslashes($code); ?>')">
                                        <?php echo $this->escape($code); ?>
                                    </button>
                                </td>
                                <td><?php echo $help; ?></td>
                            </tr>
                        <?php 
                        $i++; 
                        endforeach; 
                        ?>
                    </tbody>
                <?php endforeach; ?>
            </table>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>

            <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'sample', Text::_('COM_GEOFACTORY_SAMPLE_TEMPLATE')); ?>
            <textarea class="form-control" rows="18">
<div class="container-fluid">
    <div class="row">
        <div class="col-md-4" id="gf_sideTemplateCont">
            <div id="gf_sideTemplateCtrl">
                <div class="card mb-3">
                    <div class="card-body">
                        <div id="gf_btn_superfull" class="d-none" onclick="superFull({mapvar}.map);return false;">
                            <a id="reset" href="#" class="text-decoration-none">
                                <i class="bi bi-chevron-right"></i> Reduce
                            </a>
                        </div>
                        <h4 class="card-title">Search near 
                            <small>(<a id="find_me" href="#" onClick="{mapvar}.LMBTN();">Find me</a>)</small>
                        </h4>
                        <div class="mb-3">
                            <input type="text" id="addressInput" class="form-control" placeholder="Enter an address or intersection" />
                        </div>
                        <div class="mb-3">
                            <label>Within {rad_distances}</label>
                        </div>
                        <h4>Categories</h4>
                        {ullist_img}
                        <div class="mt-3">
                            <a class="btn btn-primary me-2" id="search" href="#" onclick="{mapvar}.SLFI();">
                                <i class="bi bi-search"></i> Search
                            </a>
                            <a class="btn btn-secondary" id="reset" href="#" onclick="{mapvar}.SLRES();">
                                <i class="bi bi-arrow-repeat"></i> Reset
                            </a>
                        </div>
                        {layer_selector}
                    </div>
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
            <div class="alert alert-info mt-3" id="route_box">
                <h4>Click a marker to reach</h4>
                {route}
            </div>
        </div>
    </div>
</div>
<div id="gf_panelback" 
     class="position-fixed end-0 top-0 bottom-0 d-none" 
     style="width: 20px; z-index: 1050; background: url(/media/com_geofactory/assets/arrow-left.png) no-repeat center; cursor: pointer;"
     onclick="normalFull({mapvar}.map);">
</div>
            </textarea>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>
            <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
        </fieldset>
        <?php
        return ob_get_clean();
    }

    /**
     * Helper per l'escape di testo
     * 
     * @param string $text
     * @return string
     */
    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

// Istanzia e renderizza
$placeholders = new GeofactoryPlaceholders();
echo $placeholders->render();