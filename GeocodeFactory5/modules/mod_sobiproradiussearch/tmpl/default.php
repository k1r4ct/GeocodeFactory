<?php
/**
 * @name        Sobipro Radius Search module
 * @package     mod_sobiproRadiusSearch
 * @copyright   Copyright © 2012 - All rights reserved.
 * @license     GNU/GPL
 * @author      Cédric Pelloquin
 * @author mail info@myJoom.com
 * @website     www.myJoom.com
 * @update      Daniele Bellante
 */
defined('_JEXEC') or die;

// Aggiunta la use per Joomla 4
use Joomla\CMS\Router\Route;
?>
<form action="<?php echo Route::_('index.php'); ?>" method="post" id="spSearchForm">
    <input type="hidden" name="sid" value="<?php echo $sps; ?>"/>
    <input type="hidden" name="option" value="com_sobipro"/>
    <input type="hidden" name="task" value="search.search"/>
    <input type="hidden" name="Itemid" value="<?php echo $jit; ?>"/>
    <input type="hidden" id="mj_rs_mod_ref_lat" name="mj_rs_mod_ref_lat" value=""/>
    <input type="hidden" id="mj_rs_mod_ref_lng" name="mj_rs_mod_ref_lng" value=""/>
    <input type="hidden" id="mj_rs_mod_ref_excl" name="mj_rs_mod_ref_excl" value=""/>
    <?php
        if ($tpl) {
            echo $tpl;
        } else {
            // se non usi template personalizzato, mostra un campo testuale generico "Cosa vuoi fare?"
            echo '<div>
                    <div class="spsearch_label">Cosa vuoi fare?</div>
                    <input type="text" name="sp_search_for" value="" class="form-control search-query" autocomplete="off" id="SPSearchBox2" placeholder=""/>
                  </div>';
            echo $keyw;
            echo $rad . $lmb;
            echo $btn;
        }
    ?>
    <script>
    jQuery(document).ready(function() {
        if(jQuery('#SPSearchBox').length) {
            if(jQuery('#SPSearchBox').val()!='...') {
                jQuery('#SPSearchBox2').val(jQuery('#SPSearchBox').val());
            }
        }
        if(jQuery('#mj_rs_center_selector').length) {
            if(jQuery('#mj_rs_ref_lat').length) {
                if(jQuery('#mj_rs_ref_lat').val()!='') {
                    jQuery('#mj_rs_mod_center_selector').val(jQuery('#mj_rs_center_selector').val());
                }
            }
        }
        if(jQuery('#mj_rs_ref_lat').length) {
            jQuery('#mj_rs_mod_ref_lat').val(jQuery('#mj_rs_ref_lat').val());
            jQuery('#mj_rs_mod_ref_lng').val(jQuery('#mj_rs_ref_lng').val());
        }
        if(jQuery('#mj_rs_radius_selector').length) {
            jQuery('#mj_rs_mod_radius_selector').val(jQuery('#mj_rs_radius_selector').val());
        }
        jQuery("input[type='text']").click(function () {
            jQuery(this).select();
        });
        jQuery('#mj_rs_mod_center_selector').focusout(function() {
            if(jQuery('#mj_rs_mod_center_selector').val()=='') {
                jQuery('#mj_rs_mod_ref_lat').removeAttr('value');
                jQuery('#mj_rs_mod_ref_lng').removeAttr('value');
                jQuery('#mj_rs_center_selector').removeAttr('value');
                jQuery('#mj_rs_ref_lat').removeAttr('value');
                jQuery('#mj_rs_ref_lng').removeAttr('value');
            }
        });
        jQuery('#spSearchForm').submit(function() {
            if (
                jQuery.trim(jQuery("#SPSearchBox2").val()) === "" &&
                jQuery.trim(jQuery("#mj_rs_mod_center_selector").val()) === ""
            ) {
                if(jQuery("#msg-empty-form").length) {
                    return false;
                } else {
                    jQuery("#spSearchForm").append(
                        "<div id=\"msg-empty-form\">Ops! Sembra che tu non abbia compilato i campi di ricerca. Per cercare tra le offerte di lavoro, scrivi cosa vuoi fare e/o dove.</div>"
                    );
                    return false;
                }
            }
        });
    });
    </script>
</form>