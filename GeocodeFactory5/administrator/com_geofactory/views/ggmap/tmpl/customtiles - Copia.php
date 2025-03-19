<?php
/**
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Cédric Pelloquin aka Rick <info@myJoom.com>
 * @update      Daniele Bellante
 * @website     www.myJoom.com
 */

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;

defined('_JEXEC') or die;

HTMLHelper::addIncludePath(JPATH_COMPONENT . '/helpers/html');
HTMLHelper::_('bootstrap.tooltip');

// Questi sample sono solo un esempio predefinito di URL e settaggi
// che l'utente può poi personalizzare/incollare nel backend.
$vSamples = [
    "http://sourceUrl.org/#Z#/#X#/#Y#.png|Name|maxzoom|alt title|png|tileSize; ",
    "http://tile.openstreetmap.org/#Z#/#X#/#Y#.png|Mapnik|18|Open Streetmap Mapnik|true|256; ",
    "http://tile.xn--pnvkarte-m4a.de/tilegen/#Z#/#X#/#Y#.png|OPNV|18|Open Streetmap OPNV|true|256; ",
    "http://bing.com/aerial|Aerial|18|Bing! aerial|true|256; ",
    "http://bing.com/label|Labels|18|Bing! label|true|256; ",
    "http://bing.com/road|Roads|18|Bing! roads|true|256; ",
    "http://b.tile.opencyclemap.org/cycle/#Z#/#X#/#Y#.png|Bicycle|18|Open cycle|true|256; ",
    "http://b.tile2.opencyclemap.org/transport/#Z#/#X#/#Y#.png|Transport|18|Open transport|true|256; ",
    "http://b.tile3.opencyclemap.org/landscape/#Z#/#X#/#Y#.png|Landscape|18|Open landscape|true|256; "
];
?>

<fieldset>
    <?php echo HTMLHelper::_('bootstrap.startTabSet', 'myTab', ['active' => 'codes']); ?>

    <!-- TAB 1: TILES INSERT -->
    <?php echo HTMLHelper::_('bootstrap.addTab', 'myTab', 'codes', Text::_('COM_GEOFACTORY_TILES_INSERT')); ?>
    <table class="table table-striped table-condensed">
        <thead>
            <tr>
                <th width="33%" class="nowrap"><?php echo Text::_('COM_GEOFACTORY_TILES_ELEMENT'); ?></th>
                <th width="67%" class="nowrap"><?php echo Text::_('COM_GEOFACTORY_TILES_ELEMENT_DESC'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <input type="text" value="" id="gf_tile_name" placeholder="<?php echo Text::_('COM_GEOFACTORY_TILES_NAME'); ?>" />
                </td>
                <td><?php echo Text::_('COM_GEOFACTORY_TILES_NAME'); ?></td>
            </tr>
            <tr>
                <td>
                    <input type="text" value="" id="gf_tile_desc" placeholder="<?php echo Text::_('COM_GEOFACTORY_TILES_TOOTIPS'); ?>" />
                </td>
                <td><?php echo Text::_('COM_GEOFACTORY_TILES_TOOTIPS'); ?></td>
            </tr>
            <tr>
                <td>
                    <input type="text" value="" id="gf_tile_url" placeholder="<?php echo Text::_('COM_GEOFACTORY_TILES_URL'); ?>" />
                </td>
                <td><?php echo Text::_('COM_GEOFACTORY_TILES_URL'); ?></td>
            </tr>
            <tr>
                <td>
                    <input type="text" value="18" id="gf_tile_zoom" placeholder="<?php echo Text::_('COM_GEOFACTORY_TILES_ZOOM'); ?>" />
                </td>
                <td><?php echo Text::_('COM_GEOFACTORY_TILES_ZOOM'); ?></td>
            </tr>
            <tr>
                <td>
                    <select id="gf_tile_png">
                        <option value="true" selected="selected">Yes</option>
                        <option value="false">No</option>
                    </select>
                </td>
                <td><?php echo Text::_('COM_GEOFACTORY_TILES_ISPNG'); ?></td>
            </tr>
            <tr>
                <td>
                    <input type="text" value="256" id="gf_tile_size" placeholder="256" />
                </td>
                <td><?php echo Text::_('COM_GEOFACTORY_TILES_SIZE'); ?></td>
            </tr>
            <tr>
                <td>
                    <button class="btn btn-primary" style="width: 150px;"
                            onclick="if(window.parent) {
                                window.parent.insertNewTile(
                                    document.getElementById('gf_tile_url').value,
                                    document.getElementById('gf_tile_name').value,
                                    document.getElementById('gf_tile_zoom').value,
                                    document.getElementById('gf_tile_desc').value,
                                    document.getElementById('gf_tile_png').value,
                                    document.getElementById('gf_tile_size').value
                                );
                            }">
                        <?php echo Text::_('COM_GEOFACTORY_INSERT'); ?>
                    </button>
                </td>
                <td><?php echo Text::_('COM_GEOFACTORY_INSERT'); ?></td>
            </tr>
        </tbody>
    </table>
    <?php echo HTMLHelper::_('bootstrap.endTab'); ?>

    <!-- TAB 2: TILES SAMPLES -->
    <?php echo HTMLHelper::_('bootstrap.addTab', 'myTab', 'sample', Text::_('COM_GEOFACTORY_TILES_SAMPLES')); ?>
    <table class="table table-striped table-condensed">
        <thead>
            <tr>
                <th width="33%" class="nowrap"><?php echo Text::_('COM_GEOFACTORY_TILES_SAMPLE_INSERT'); ?></th>
                <th width="67%" class="nowrap"><?php echo Text::_('COM_GEOFACTORY_TILES_SAMPLE'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php $i = 0; ?>
            <?php foreach ($vSamples as $sample) : ?>
                <tr class="row<?php echo $i % 2; ?>">
                    <td>
                        <button class="btn btn-success" style="width: 150px;"
                                onclick="if(window.parent) {
                                    window.parent.insertSampleTile('<?php echo addslashes($sample); ?>');
                                }">
                            <?php echo Text::_('COM_GEOFACTORY_INSERT'); ?>
                        </button>
                    </td>
                    <td><?php echo $sample; ?></td>
                </tr>
                <?php $i++; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php echo HTMLHelper::_('bootstrap.endTab'); ?>

    <?php echo HTMLHelper::_('bootstrap.endTabSet'); ?>
</fieldset>
