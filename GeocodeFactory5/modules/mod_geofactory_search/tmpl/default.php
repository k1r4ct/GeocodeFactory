<?php
/**
 * @name        Geocode Factory Search module
 * @package     mod_geofactory_search
 * @copyright   Copyright © 2014
 * @license     GNU/GPL
 * @author      ...
 * @update      Daniele Bellante
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
    echo "Invalid menu ID in module params (sMapUrl).";
    return;
}

// Costruisci l'URL
$url = Route::_($item->link . '&Itemid=' . $item->id, true);

// Leggi eventuali variabili passate dal helper
// (assicurati che in mod_geofactory_search.php o helper.php
//  queste variabili siano effettivamente definite)
$radIntro   = isset($radIntro)   ? $radIntro   : '';
$radInpHtml = isset($radInpHtml) ? $radInpHtml : '';
$radDistHtml= isset($radDistHtml)? $radDistHtml: '';
$buttons    = isset($buttons)    ? $buttons    : '';
$barHtml    = isset($barHtml)    ? $barHtml    : '';
$listHtml   = isset($listHtml)   ? $listHtml   : '';
$labels     = isset($labels)     ? $labels     : ['City','Distance'];

// Codice
$tmplCode = $params->get('tmplCode');

// Se si usa il radius
if ($params->get('bRadius'))
{
    ?>
    <form action="<?php echo $url; ?>" method="post" id="gf_search-form">
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
            <p id="rad-intro">
                <?php echo $radIntro; ?>
            </p>
            <p id="rad-city">
                <label for="gf_mod_search"><?php echo $labels[0]; ?></label><br />
                <?php echo $radInpHtml; ?>
            </p>
            <p id="rad-dist">
                <label for="gf_mod_radius"><?php echo $labels[1]; ?></label><br />
                <?php echo $radDistHtml; ?>
            </p>
            <p id="rad-btn">
                <?php echo $buttons; ?>
            </p>
            <?php
        }
        ?>
    </form>
    <?php
}

// Stampa eventuali HTML finali
echo $barHtml;
echo $listHtml;
?>
