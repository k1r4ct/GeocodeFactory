<?php
/**
 * @name		Geocode Factory - Content plugin
 * @package		geoFactory
 * @copyright	Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later
 * @author		Cédric Pelloquin <info@myJoom.com>
 * @website		www.myJoom.com
 * 
 */
defined('_JEXEC') or die;
JHtml::_('jquery.framework');
require_once JPATH_ROOT	.'/components/com_geofactory/helpers/externalMap.php';

	$lang = JFactory::getLanguage(); 
	$lang->load( 'com_geofactory', JPATH_SITE);

	// quel component?
	$c_opt	= JRequest::getString('c_opt', 'com_content');

	// y'a t'il un id d'article ?
	$id		= JRequest::getInt('idArt', 0);
	if ($id<1){
		JError::raiseError( 4711, JText::_('COM_GEOFACTORY_BTN_PLG_NO_ARTICLE_ID'));
		return;
	}

	// prépare la variable de carte
	$ctx 	= 'jcbtn' ;
	$idMap 	= 999;
	$mapVar = $ctx.'_gf_'.$idMap ;

	$js="
	function savePosition(){
		var arData 		= {} ;
		var addcode 	= jQuery('#addcode').prop('checked') ;
		arData['idArt'] = jQuery('#idArt').val() ;
		arData['lat'] 	= jQuery('#jcbtn_lat').val() ;
		arData['lng'] 	= jQuery('#jcbtn_lng').val() ;
		arData['c_opt'] = jQuery('#jcbtn_c_opt').val() ;
		arData['adr'] 	= jQuery('#searchPos_{$mapVar}').val() ;

		if (addcode){
			window.parent.jInsertEditorText('{myjoom_map}');
		}

		jQuery.ajax({
			url: '".JURI::root()."index.php?option=com_geofactory&task=map.geocodearticle',
			data: arData,		
			success:function(data){
				window.parent.SqueezeBox.close();
			}
		})
	}
	";

	JFactory::getDocument()->addScriptDeclaration($js);

	// defaut, il n'y a rien
	$lat 	= '';
	$lng 	= '';
	$adr 	= '';

	// cherche les coordonnées de l'article si deja geocodé
	$db = JFactory::getDBO();
	$query = $db->getQuery(true);
	$query->select('address, latitude,longitude');
	$query->from($db->quoteName('#__geofactory_contents'));
	$query->where('id_content='.(int) $id.' AND type='.$db->Quote($c_opt));
	$db->setQuery($query, 0, 1);
    $res = $db->loadObjectList() ;
    if( $db->getErrorNum()) { trigger_error("_loadCoord error  :".$db->stderr()); exit(); }
	if (count($res)>0){
		$lat = $res[0]->latitude ;
		$lng = $res[0]->longitude ;
		$adr = $res[0]->address ;
	}

	// dessin de la carte
	$map = GeofactoryExternalMapHelper::getProfileEditMap('jcbtn_lat','jcbtn_lng', $mapVar, true, $adr, array(), array(), JRequest::getString('dla', '').','.JRequest::getString('dln', '') );
?>

	<input type="hidden" id="jcbtn_c_opt" name="jcbtn_c_opt" value="<?php echo $c_opt; ?>" />

	<ul style="padding: 3px 3px 4px 6px;">
		<li>
			<label for="jcbtn_lat"><?php echo JText::_('COM_GEOFACTORY_BTN_PLG_LATITUDE');?></label>
			<input type="text" id="jcbtn_lat" name="jcbtn_lat" value="<?php echo $lat; ?>" />
		</li>
		<li>
			<label for="jcbtn_lng"><?php echo JText::_('COM_GEOFACTORY_BTN_PLG_LONGITUDE');?></label>
			<input type="text" id="jcbtn_lng" name="jcbtn_lng" value="<?php echo $lng; ?>" />
		</li>
		<li>
			<label for="addcode"><?php echo JText::_('COM_GEOFACTORY_BTN_PLG_ADDCODE');?></label>
			<input type="checkbox" id="addcode" name="addcode" value="1" />
		</li>
	</ul>

	<?php echo $map; ?>

	<div style="padding: 3px 3px 4px 6px;">
		<input type="hidden" id="idArt" value="<?php echo $id; ?>"/>
		<button onclick="savePosition();"><?php echo JText::_('JSAVE');?></button>
		<button onclick="window.parent.SqueezeBox.close();"><?php echo JText::_('JCANCEL');?></button>
	</div>

