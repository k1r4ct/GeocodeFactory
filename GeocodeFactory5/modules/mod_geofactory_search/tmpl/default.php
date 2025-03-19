<?php
/**
 * @name        Geocode Factory Search module
 * @package     mod_geofactory_search
 * @copyright   Copyright © 2014-2023 - All rights reserved.
 * @license     GNU/GPL
 * @author      Cédric Pelloquin
 * @author mail  info@myJoom.com
 * @update      Daniele Bellante, Aggiornato per Joomla 4.4.10
 * @website     www.myJoom.com
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;

// Recupera l'app e i parametri
$app    = Factory::getApplication();
$itemId = (int) $params->get('sMapUrl', 0);
$item   = $app->getMenu()->getItem($itemId);

// Se l'item di menu non esiste, esci
if (!$item) {
    echo '<div class="alert alert-warning">Invalid menu ID in module params (sMapUrl).</div>';
    return;
}

// Costruisci l'URL
$url = Route::_($item->link . '&Itemid=' . $item->id, true);

// Leggi eventuali variabili passate dal helper
// (assicurati che in mod_geofactory_search.php o helper.php
//  queste variabili siano effettivamente definite)
$radIntro   = $radIntro ?? '';
$radInpHtml = $radInpHtml ?? '';
$radDistHtml = $radDistHtml ?? '';
$buttons    = $buttons ?? '';
$barHtml    = $barHtml ?? '';
$listHtml   = $listHtml ?? '';
$labels     = $labels ?? ['City', 'Distance'];

// Codice
$tmplCode = $params->get('tmplCode');

// Se si usa il radius
if ($params->get('bRadius'))
{
    ?>
    <div class="mod-geofactory-search<?php echo $params->get('moduleclass_sfx'); ?>">
        <form action="<?php echo $url; ?>" method="post" id="gf_search-form" class="gf-search-form">
            <?php
            if (strlen($tmplCode) > 3)
            {
                $tmplCode = str_replace('[INPUT]', $radInpHtml, $tmplCode);
                $tmplCode = str_replace('[DISTANCE]', $radDistHtml, $tmplCode);
                $tmplCode = str_replace('[SEARCH_BTN]', $buttons, $tmplCode);
                echo $tmplCode;
            }
            else
            {
                ?>
                <?php if (!empty($radIntro)): ?>
                <p id="rad-intro" class="mb-3">
                    <?php echo $radIntro; ?>
                </p>
                <?php endif; ?>
                
                <div id="rad-city" class="mb-3">
                    <label for="gf_mod_search" class="form-label"><?php echo $labels[0]; ?></label>
                    <?php echo $radInpHtml; ?>
                </div>
                
                <div id="rad-dist" class="mb-3">
                    <label for="gf_mod_radius" class="form-label"><?php echo $labels[1]; ?></label>
                    <?php echo $radDistHtml; ?>
                </div>
                
                <div id="rad-btn" class="d-grid gap-2 d-md-flex">
                    <?php echo $buttons; ?>
                </div>
                <?php
            }
            ?>
        </form>
        
        <?php
        // Stampa eventuali HTML finali
        echo $barHtml;
        echo $listHtml;
        ?>
    </div>
    <?php
}