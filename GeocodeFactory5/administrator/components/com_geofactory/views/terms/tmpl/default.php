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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;

// Caricamento behavior standard (se serve)
HTMLHelper::_('behavior.multiselect');

// Messaggio
Factory::getApplication()->enqueueMessage(Text::_('COM_GEOFACTORY_TERM_CACHE_ISSUE'), 'message');
?>

<script>
function validateTerms() {
    var res = 0;
    if (document.getElementById('accept_rules').checked == true) { 
        res += 1; 
    }
    if (document.getElementById('accept_support').checked == true) {
        res += 1;
    }
    if (res != 2) {
        alert('<?php echo Text::_('COM_GEOFACTORY_PLEASE_ACCEPT_CONDITIONS');?>');
        return false;
    }
    return true;
}
</script>

<h1><?php echo Text::_('COM_GEOCODE_TERMS_TITLE'); ?></h1>
<br/>
<label for="accept_support" class="postsetup-main">
    <input type="checkbox" id="accept_support" name="accept_support" />
    <?php echo Text::_('COM_GEOFACTORY_ACCEPT_NO_SUPPORT'); ?>
</label>
<label for="accept_rules" class="postsetup-main">
    <input type="checkbox" id="accept_rules" name="accept_rules" />
    <?php echo Text::_('COM_GEOFACTORY_ACCEPT_RULES'); ?>
</label>
<br/>
<a class="btn btn-primary btn-large"
   onclick="return validateTerms();"
   href="<?php echo Route::_('index.php?option=com_geofactory&task=terms.apply', false); ?>">
   <?php echo Text::_('COM_GEOFACTORY_AGREE_TERMS'); ?>
</a>

<a class="btn btn-warning btn-large"
   href="<?php echo Route::_('index.php?option=com_geofactory&task=terms.cancel', false); ?>">
   <?php echo Text::_('COM_GEOFACTORY_DECLINE_TERMS'); ?>
</a>
