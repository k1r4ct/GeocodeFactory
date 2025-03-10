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
use Joomla\CMS\Factory;

// In Joomla 4 usiamo i modali di Bootstrap 5:
HTMLHelper::_('bootstrap.modal');


// Funzione di utilità per disegnare link rapidi (stile Joomla 3 aggiornato a Bootstrap 5)
function drawJ3Item($iconClass, $text, $link = null)
{
    ?>
    <div class="row mb-2">
        <div class="col-12">
            <?php if ($link) : ?>
                <a href="<?php echo $link; ?>" class="d-flex align-items-center text-decoration-none">
                    <i class="bi bi-<?php echo $iconClass; ?>"></i>
                    <span class="ms-2"><?php echo $text; ?></span>
                </a>
            <?php else : ?>
                <div class="d-flex align-items-center">
                    <i class="bi bi-<?php echo $iconClass; ?>"></i>
                    <span class="ms-2"><?php echo $text; ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
?>
<form enctype="multipart/form-data"
      action="<?php echo Route::_('index.php?option=com_geofactory&view=accueil'); ?>"
      method="post"
      name="adminForm"
      id="adminForm"
      class="needs-validation" novalidate>

    <div class="col-md-10">
        <ul class="nav nav-tabs" id="accueilTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="welcome-tab" data-bs-toggle="tab" data-bs-target="#welcome" type="button" role="tab" aria-controls="welcome" aria-selected="true">
                    <?php echo Text::_('COM_GEOFACTORY_MAIN_MENU'); ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="changelog-tab" data-bs-toggle="tab" data-bs-target="#changelog" type="button" role="tab" aria-controls="changelog" aria-selected="false">
                    <?php echo Text::_('COM_GEOFACTORY_CHANGE_LOG'); ?>
                </button>
            </li>
        </ul>

        <div class="tab-content mt-3">
            <div class="tab-pane fade show active" id="welcome" role="tabpanel" aria-labelledby="welcome-tab">
                <div class="row">
                    <!-- Sezione sinistra -->
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header">Quick Links</div>
                            <div class="card-body">
                                <?php
                                $maps    = GeofactoryHelperAdm::getLinksEditShortCuts(
                                    GeofactoryHelperAdm::getArrayListMaps(),
                                    'ggmap'
                                );
                                $markset = GeofactoryHelperAdm::getLinksEditShortCuts(
                                    GeofactoryHelperAdm::getArrayListMarkersets(),
                                    'markerset'
                                );
                                drawJ3Item("file-add", Text::_('COM_GEOFACTORY_CPANEL_CREATE_MAP'),
                                    "index.php?option=com_geofactory&view=ggmap&layout=edit");
                                drawJ3Item("file-add", Text::_('COM_GEOFACTORY_CPANEL_CREATE_MS'),
                                    "index.php?option=com_geofactory&view=markersets&layout=edit");
                                drawJ3Item("list-view", Text::_('COM_GEOFACTORY_MENU_ASSIGN_PATTERN'),
                                    "index.php?option=com_geofactory&view=assigns");
                                drawJ3Item("list-view", Text::_('COM_GEOFACTORY_MENU_MAPS_MANAGER') . $maps,
                                    "index.php?option=com_geofactory&view=ggmaps");
                                drawJ3Item("list-view", Text::_('COM_GEOFACTORY_MENU_MARKERSETS_MANAGER') . $markset,
                                    "index.php?option=com_geofactory&view=markersets");
                                drawJ3Item("flag", Text::_('COM_GEOFACTORY_MENU_GEOCODING'),
                                    "index.php?option=com_geofactory&view=geocodes");
                                drawJ3Item("cog", Text::_('COM_GEOFACTORY_CPANEL_CONFIGURATION'),
                                    "index.php?option=com_config&view=component&component=com_geofactory");
                                drawJ3Item("cogs", Text::_('COM_GEOFACTORY_PLUGIN_CONFIGURATION'),
                                    "index.php?option=com_plugins&view=plugins&filter_folder=geocodefactory");
                                drawJ3Item("cube", Text::_('COM_GEOFACTORY_CPANEL_IMPORT_OLD'),
                                    "index.php?option=com_geofactory&view=oldmaps");
                                ?>
                            </div>
                        </div>

                        <div class="card mb-3">
                            <div class="card-header">
                                Update center
                                <a class="float-end" target="_blank" href="http://www.myjoom.com/index.php/documentation?view=kb&prodid=4&kbartid=113">
                                    <span class="badge bg-danger">help</span>
                                </a>
                            </div>
                            <div class="card-body">
                                <?php
                                drawJ3Item("refresh", "Check for updates",
                                    'index.php?option=com_geofactory&view=accueil&task=accueil.updates');

                                $vExts = GeofactoryHelperUpdater::getUpdatesList();
                                if (is_array($vExts) && count($vExts)) {
                                    foreach ($vExts as $ext) {
                                        drawJ3Item("puzzle", $ext);
                                    }
                                }
                                drawJ3Item("download", Text::_('COM_GEOFACTORY_CPANEL_GET_MORE_PLUGINS'),
                                    'http://www.myjoom.com" target="_blank');
                                ?>
                            </div>
                        </div>

                        <?php
                        $edNone  = GeofactoryHelperAdm::isEditorNoneEnabled();
                        $codeMir = GeofactoryHelperAdm::isCodeMirrorEnabled();
                        if (!$codeMir) :
                        ?>
                        <div class="card mb-3">
                            <div class="card-header">Warnings</div>
                            <div class="card-body">
                                <?php
                                if (!$codeMir && !$edNone) {
                                    drawJ3Item("warning", Text::_('COM_GEOFACTORY_CPANEL_ENABLE_EDITORS'));
                                }
                                if (!$codeMir) {
                                    drawJ3Item("warning", Text::_('COM_GEOFACTORY_CPANEL_ENABLE_EDITOR'));
                                }
                                ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <!-- Sezione destra -->
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header"><?php echo Text::_('COM_GEOFACTORY'); ?></div>
                            <div class="card-body">
                                <p><?php echo Text::_('COM_GEOFACTORY_DESCRIPTION_WELCOME'); ?></p>
                            </div>
                        </div>

                        <div class="card mb-3">
                            <div class="card-header">Credits</div>
                            <div class="card-body">
                                <p><?php echo Text::_('COM_GEOFACTORY_CPANEL_CREDITS'); ?></p>
                                <ul class="list-unstyled">
                                    <li>
                                        <strong>
                                            Kostas Stathakos -
                                            <a href="http://www.e-leven.net" target="_blank">
                                                e-leven social webs
                                            </a>
                                        </strong>
                                        <br />Product tester, redattore della documentazione
                                    </li>
                                    <li>
                                        <strong>
                                            Steve Hess -
                                            <a href="http://www.karaokeacrossamerica.com" target="_blank">
                                                Karaoke Across America
                                            </a>
                                        </strong>
                                        <br />Product tester, redattore e correttore della documentazione
                                    </li>
                                    <li>
                                        <strong>
                                            Fred Vogels -
                                            <a href="http://www.backtonormandy.org" target="_blank">
                                                Backtonormandy historical site
                                            </a>
                                        </strong>
                                        <br />Product tester
                                    </li>
                                    <li>
                                        <strong>
                                            Mapicons -
                                            <a href="http://mapicons.nicolasmollet.com/" target="_blank">
                                                Map Icons Collection
                                            </a>
                                        </strong>
                                        <br />Geocode Factory include circa 200 marker di icone da Map Icons Collection. È possibile personalizzare i colori e ottenere oltre 500 ulteriori icone dal sito dell'autore.
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div class="card mb-3">
                            <div class="card-header"><?php echo Text::_('COM_GEOFACTORY_CPANEL_LASTNEWS'); ?></div>
                            <div class="card-body">
                                <iframe id="if_news"
                                        height="400px"
                                        width="100%"
                                        frameborder="0"
                                        src="http://www.myjoom.com/index.php?option=com_content&view=category&id=72&tmpl=component">
                                </iframe>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="tab-pane fade" id="changelog" role="tabpanel" aria-labelledby="changelog-tab">
                <iframe id="if_changelog"
                        height="700px"
                        width="100%"
                        frameborder="0"
                        src="http://www.myjoom.com/index.php?option=com_content&view=article&id=128&tmpl=component">
                </iframe>
            </div>
        </div>
        <input type="hidden" name="type" value="" />
        <input type="hidden" name="task" value="" />
        <?php echo HTMLHelper::_('form.token'); ?>
    </div>
</form>
