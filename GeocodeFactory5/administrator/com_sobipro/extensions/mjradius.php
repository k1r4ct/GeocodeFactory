<?php
/**
 * @name		Radius Search Application
 * @package		mjradius
 * @copyright	Copyright © 2011 - All rights reserved.
 * @license		GNU/GPL
 * @author		MyJoom Team
 * @author mail	info@myJoom.com
 * @website		www.myJoom.com
 * @update    	Daniele Bellante
 */
 
defined( 'SOBIPRO' ) || exit( 'Restricted access' );


use Joomla\CMS\Factory;

//1=translate:[OPT_YES], 0=translate:[OPT_NO]
function translateValues($vals){
	$res = array() ;
	$value = explode(',', $vals);

	foreach($value as $v){
		$pair =  explode('=', $v);
		if (!count($pair)==2)
			continue;

		$idx = $pair[0] ;
		$txt = $pair[1] ;

		$txt = str_replace(array('translate:','[',']'), '', $txt);
		$txt = Sobi::Txt($txt);

		$res[$idx] = trim($txt);

	}

	return $res ;


}

function fieldselect($name, $vals, $def, $rien2, $cust){
	//array( 'id' => 'm_enabled', 'size' => 1, 'class' => 'inputbox' )
	$custom = '' ;
	foreach($cust as $k=>$v){
		$custom.= $k.'="'.$v.'"';
	} 

	//1=translate:[OPT_YES], 0=translate:[OPT_NO]
	$values = translateValues($vals) ;
	echo '<select name="'.$name.'" '.$custom.'>';
	foreach ($values as $key => $value) {
		$sel = ($key==$def)?'selected="selected"':'';
		echo '<option value="'.$key.'" '.$sel.'>'.$value.'</option>';
	}

	echo '</select>';
}


function fieldtext($rien, $name, $def, $cust){
	//array( 'id' => 'm_enabled', 'size' => 1, 'class' => 'inputbox' )
	$custom = '' ;
	foreach($cust as $k=>$v){
		$custom.= $k.'="'.$v.'"';
	} 

	echo '<input type="text" name="'.$name.'" '.$custom.' value="'.$def.'" />';
}


function mjDrawMsg($type, $title, $text){
	if ($type==1)	$type =	"alert alert-success spSystemAlert"; // spConfigAlert?
	if ($type==2)	$type =	"alert alert-warning spSystemAlert";
	if ($type==3)	$type =	"alert alert-error spSystemAlert";

	echo '<div class="'.$type.'">';
	echo '<button style="float:right!important;" type="button" class="close" data-dismiss="alert">×</button>';
	echo '<strong>'.$title.'</strong><br />';
	echo '<span style="font-weight:normal!important;">'.$text.'</span></div>';
}

$db = Factory::getDbo();
$db->setQuery("SELECT version FROM `#__sobipro_plugins` WHERE `pid` = 'mjradius'" );
$cv = $db->loadResult();
$row = 0;

// recherche la liste des champs //F.`nid` as id, = field_xxx
$db->setQuery("	SELECT DISTINCT L.`sValue` as name, F.`fid` as fid, F.`fieldType`
				FROM `#__sobipro_language` L
				LEFT OUTER JOIN `#__sobipro_field` F ON  L.`fid`= F.`fid` 
				WHERE ( L.`oType`='field' AND  L.`sKey`='name') 
				AND F.`section`= " . Sobi::Section() ."
				ORDER BY  L.`language`,  L.`sValue` ASC ") ;

$fields = $db->loadObjectList();
$field_val ="0=Do not use";
$field_geo ="0=Default";
$fieldsEx ="";
if (count($fields)){
	foreach($fields as $f){
		if ($f->fieldType=='inbox'){
			$field_val.=",";
			$field_val.=str_replace("'","`",str_replace("/","-",str_replace(",",";","{$f->fid}={$f->name}")));
		} 
		if (($f->fieldType=='select')OR($f->fieldType=='radio')OR($f->fieldType=='multiselect')){
			$fid = $f->fid * -1 ;
			$fieldsEx.=",";
			$fieldsEx.=str_replace("'","`",str_replace("/","-",str_replace(",",";","{$fid}={$f->name}")));
		} 
		if ($f->fieldType=='geomap'){
			$field_geo.=",";
			$field_geo.=str_replace("'","`",str_replace("/","-",str_replace(",",";","{$f->fid}={$f->name}")));
		} 
	}
}

$fieldsEx = $field_val.$fieldsEx ;

// liste des valeurs de référence de google 
$fieldsExGg = array() ;
$fieldsExGg['locality']						= 'locality - short' ;
$fieldsExGg['-locality']					= 'locality - long' ;
$fieldsExGg['administrative_area_level_2']	= 'Administrative level 2 - short' ;
$fieldsExGg['-administrative_area_level_2']	= 'Administrative level 2 - long' ;
$fieldsExGg['administrative_area_level_1']	= 'Administrative level 1 - short' ;
$fieldsExGg['-administrative_area_level_1']	= 'Administrative level 1 - long' ;
$fieldsExGg['country']						= 'Country - short' ;
$fieldsExGg['-country']						= 'Country - long' ;

// affiche le statut des entrée
// toutes les entrées
$query = " SELECT count(*) ";
$query.= " FROM `#__sobipro_object` AS O ";
$query.= "  JOIN `#__sobipro_config`   		AS C ON C.section = ".Sobi::Section()." AND sKey='name_field' ";
$query.= "  JOIN `#__sobipro_field_data` 	AS T ON T.sid = O.id AND T.fid= C.sValue ";
$query.= " WHERE O.oType='entry' AND (  ( O.validUntil > NOW() OR O.validUntil IN ( '0000-00-00 00:00:00', '1970-01-01 01:00:00' ) ) AND  ( O.validSince < NOW() OR O.validSince IN( '0000-00-00 00:00:00', '1970-01-01 01:00:00' ) ) AND state = 1 ) ";
$db->setQuery($query);
$allE = $db->loadResult();
$allE = $allE>0 ? $allE : 0 ;

// quel field sp geo utilisé
$spGeo	= ($this->get('m_spGeoFieldId')<1)	?" ":" AND GEO.fid=".(int) $this->get('m_spGeoFieldId') ;

$query = " SELECT DISTINCT count(*) ";
$query.= " FROM `#__sobipro_object` AS O ";
$query.= " LEFT JOIN `#__sobipro_field_geo` AS GEO ON GEO.sid = O.id {$spGeo} ";
$query.= " WHERE O.oType='entry' AND (  ( O.validUntil > NOW() OR O.validUntil IN ( '0000-00-00 00:00:00', '1970-01-01 01:00:00' ) ) AND  ( O.validSince < NOW() OR O.validSince IN( '0000-00-00 00:00:00', '1970-01-01 01:00:00' ) ) AND state = 1 ) AND GEO.section=".Sobi::Section()." ";
$query.= " AND GEO.latitude IS NOT NULL AND GEO.longitude IS NOT NULL AND GEO.latitude<>0 AND GEO.longitude<>0 " ;
$db->setQuery($query);
$gpsE = $db->loadResult(); 
$gpsE = $gpsE>0 ? $gpsE : 0 ;

$noGps = $allE - $gpsE ;

?>

<div style="float: left; width: 20em; margin-left: 3px;">
	<?php $this->menu(); ?>
</div>
<?php $this->trigger( 'AfterDisplayMenu' ); ?>
<div style="margin-left: 20.8em; margin-top: 3px;">
	<?php mjDrawMsg(1, "No worries", "The above message '<i>This application is working in legacy mode</i>', does not affect this application, it's only here because we use advanced php function, like the database geocode state check."); ?>
	<fieldset class="adminform">
		<legend>
			<?php $this->txt( 'MJRS.LICENCE_VERSION' ); ?>
		</legend>
		<table class="admintable" cellspacing="1">

			<tr class="row<?php echo ++$row%2; ?>" style="vertical-align:middle;">
				<td class="key" style="padding: 8px;width:250px;">
					<label for="m_enabled" class="editlinktip hasTip" title="<?php $this->txt( 'MJRS.ENABLE' ) ?>::<?php $this->txt( 'MJRS.ENABLE.HLP' ); ?>">
						<?php $this->txt( 'MJRS.ENABLE' ); ?></label>
				</td>
				<td style="padding-right: 8px;">
<?php //$this->field( 'select', 'm_enabled', '1=translate:[OPT_YES], 0=translate:[OPT_NO]', 'value:m_enabled', false, array( 'id' => 'm_enabled', 'size' => 1, 'class' => 'inputbox' ) ); 
fieldselect('m_enabled', '1=Yes, 0=No', $this->get('m_enabled'), false, array( 'id' => 'm_enabled', 'size' => 1, 'class' => 'inputbox' ) );
					?>


				</td>
			</tr>
			
			<tr class="row<?php echo ++$row%2; ?>" style="vertical-align:middle;">
				<td class="key" style="padding: 8px; width:220px;">
					<label for="m_mjrslic" class="editlinktip hasTip" title="<?php $this->txt( 'MJRS.LICENCE' ) ?>::<?php $this->txt( 'MJRS.LICENCE.HLP' ); ?>">
						<?php $this->txt( 'MJRS.LICENCE' ); ?></label>
				</td>
				<td style="padding-right: 8px;">
					<?php //$this->field( 'text', 'm_mjrslic', 'value:m_mjrslic', array( 'id' => 'm_mjrslic', 'size' => 35, 'maxlength' => 255, 'class' => 'inputbox required' ) ); 
fieldtext('text', 'm_mjrslic', $this->get('m_mjrslic'), array( 'id' => 'm_mjrslic', 'size' => 35, 'maxlength' => 255, 'class' => 'inputbox required' ));
					?>


				</td>
			</tr>
			
			<tr class="row<?php echo ++$row%2; ?>" style="vertical-align:middle;">
				<td class="key" style="padding: 8px; width:220px;">
					<label for="m_mjrscur" class="editlinktip hasTip" title="Version info::Info about your version and the available lastest version">
						Version info</label>
				</td>
				<td style="padding-right: 8px; font-weight:bold;">
					
					<iframe style="border:0;" src="http://www.myjoom.com/checkversion.php?m=sc&amp;c=<?php echo $cv;?>&amp;p=28" width="400px" height="175px"></iframe>
				</td>
			</tr>

			<tr class="row<?php echo ++$row%2; ?>" style="vertical-align:middle;">
				<td class="key" style="padding: 8px; width:220px;">
					<label for="_testDb" class="editlinktip hasTip" title="Data check::Check the section about entry compatibility">
						Data check</label>
				</td>
				<td style="padding-right: 8px; font-weight:bold;">
				<?php
					// message 
					if($noGps<1){
						mjDrawMsg(1, "Perfect", "100% your entries are currently geocoded.") ;
					} else if($noGps == $allE){
						mjDrawMsg(3, "Error", "0% of your entries are geocoded. The application need to have the entries geocoded. Don't worry, there is 2 solutions, see this <a href='http://www.myjoom.com/index.php?option=com_fss&view=faq&catid=2&faqid=52' target='_blank' >Sobipro Radius search article</a>.") ;
					} else {
						$pct = round((100*$gpsE)/$allE,0) ;
						mjDrawMsg(2, "Warning", "{$pct}% ({$gpsE} of {$allE}) of your entries are geocoded. There is not a problem you can manage the not geocoded entries to be on the end of search results. There is 2 solutions to geocode your entries, see this <a href='http://www.myjoom.com/index.php?option=com_fss&view=faq&catid=2&faqid=52' target='_blank' >Sobipro Radius search article</a>.") ;
					}
				?>
				</td>
			</tr>

		</table>
	</fieldset>

	<fieldset class="adminform">
		<legend>
			<?php $this->txt( 'MJRS.CONFIG_TITLE' ); ?>
		</legend>
		<table class="admintable" cellspacing="1">
			<tr class="row<?php echo ++$row%2; ?>" style="vertical-align:middle;">
				<td class="key" style="padding: 8px;">
					<label for="m_spGeoFieldId" class="editlinktip hasTip" title="<?php $this->txt( 'MJRS.SP_GEOFIELD' ) ?>::<?php $this->txt( 'MJRS.SP_GEOFIELD.HLP' ); ?>">
						<?php $this->txt( 'MJRS.SP_GEOFIELD' ); ?>
					</label>
				</td>
				<td style="padding-right: 8px;">
<?php //$this->field( 'select', 'm_spGeoFieldId', $field_geo, 'value:m_spGeoFieldId', false, array( 'id' => 'm_spGeoFieldId', 'size' => 1, 'class' => 'inputbox' ) ); 
fieldselect('m_spGeoFieldId', $field_geo, $this->get('m_spGeoFieldId'), false, array( 'id' => 'm_enabled', 'size' => 1, 'class' => 'inputbox' ) );


					?>
				</td>
			</tr>

			<tr class="row<?php echo ++$row%2; ?>" style="vertical-align:middle;">
				<td class="key" style="padding: 8px; width:220px;">
					<label for="m_label" class="editlinktip hasTip" title="<?php $this->txt( 'MJRS.RADLABEL' ) ?>::<?php $this->txt( 'MJRS.RADLABEL.HLP' ); ?>">
						<?php $this->txt( 'MJRS.RADLABEL' ); ?></label>
				</td>
				<td style="padding-right: 8px;">
					<?php //$this->field( 'text', 'm_label', 'value:m_label', array( 'id' => 'm_label', 'size' => 35, 'maxlength' => 255, 'class' => 'inputbox' ) );
fieldtext('text', 'm_label', $this->get('m_label'), array( 'id' => 'm_label', 'size' => 35, 'maxlength' => 255, 'class' => 'inputbox' ));


					 ?>

				</td>
			</tr>
			
			<tr class="row<?php echo ++$row%2; ?>" style="vertical-align:middle;">
				<td class="key" style="padding: 8px;">
					<label for="m_unit" class="editlinktip hasTip" title="<?php $this->txt( 'MJRS.UNIT' ) ?>::<?php $this->txt( 'MJRS.UNIT.HLP' ); ?>">
						<?php $this->txt( 'MJRS.UNIT' ); ?>
					</label>
				</td>
				<td style="padding-right: 8px;">
<?php //$this->field( 'select', 'm_unit', '1=km, 2=mi, 3=nm, 4=m/km', 'value:m_unit', false, array( 'id' => 'm_unit', 'size' => 1, 'class' => 'inputbox' ) ); 
fieldselect( 'm_unit', '1=km, 2=mi, 3=nm, 4=m/km', $this->get('m_unit'), false, array( 'id' => 'm_unit', 'size' => 1, 'class' => 'inputbox' ) ); 



					?>
				</td>
			</tr>

			<tr class="row<?php echo ++$row%2; ?>" style="vertical-align:middle;">
				<td class="key" style="padding: 8px;">
					<label for="m_distances" class="editlinktip hasTip" title="<?php $this->txt( 'MJRS.DISTANCES' ) ?>::<?php $this->txt( 'MJRS.DISTANCES.HLP' ); ?>">
						<?php $this->txt( 'MJRS.DISTANCES' ); ?>
					</label>
				</td>
				<td style="padding-right: 8px;">
					<?php //$this->field( 'text', 'm_distances', 'value:m_distances', array( 'id' => 'm_distances', 'size' => 35, 'maxlength' => 255, 'class' => 'inputbox required' ) ); 
fieldtext('text', 'm_distances', $this->get('m_distances'), array( 'id' => 'm_distances', 'size' => 35, 'maxlength' => 255, 'class' => 'inputbox required' ));

					?>
				</td>
			</tr>

			<tr class="row<?php echo ++$row%2; ?>" style="vertical-align:middle;">
				<td class="key" style="padding: 8px;">
					<label for="m_hideDistances" class="editlinktip hasTip" title="<?php $this->txt( 'MJRS.HIDE_DISTANCES' ) ?>::<?php $this->txt( 'MJRS.HIDE_DISTANCES.HLP' ); ?>">
						<?php $this->txt( 'MJRS.HIDE_DISTANCES' ); ?>
					</label>
				</td>
				<td style="padding-right: 8px;">
					<?php // $this->field( 'select', 'm_hideDistances', '1=translate:[OPT_YES], 0=translate:[OPT_NO]', 'value:m_hideDistances', false, array( 'id' => 'm_hideDistances', 'size' => 1, 'class' => 'inputbox' ) ); 
fieldselect('m_hideDistances', '1=Yes, 0=No', $this->get('m_hideDistances'), false, array( 'id' => 'm_hideDistances', 'size' => 1, 'class' => 'inputbox' )  );

					?>
				</td>
			</tr>

			<tr class="row<?php echo ++$row%2; ?>" style="vertical-align:middle;">
				<td class="key" style="padding: 8px;">
					<label for="tmp_radstyle" class="editlinktip hasTip" title="<?php $this->txt( 'MJRS.RADSTYLE' ) ?>::<?php $this->txt( 'MJRS.RADSTYLE.HLP' ); ?>">
						<?php $this->txt( 'MJRS.RADSTYLE' ); ?>
					</label>
				</td>
				<td style="padding-right: 8px;">
					Decimals : <?php //$this->field( 'select', 'm_raddec', '0=n/a, 1=1, 2=2, 3=3, 4=4', 'value:m_raddec', false, array( 'id' => 'm_raddec', 'title'=>'decimals', 'size' => 1, 'class' => 'inputbox' ) ); 
fieldselect('m_raddec', '0=n/a, 1=1, 2=2, 3=3, 4=4', $this->get('m_raddec'), false, array( 'id' => 'm_raddec', 'title'=>'decimals', 'size' => 1, 'class' => 'inputbox' ));

					?>
					Thousand sep.:<?php //$this->field( 'select', 'm_radmil', '0=n/a, 1=space, 2=dot, 3=quote, 4=comma', 'value:m_radmil', false, array( 'id' => 'm_radmil','title'=>'thousands sep', 'size' => 1, 'class' => 'inputbox' ) ); 
fieldselect('m_radmil', '0=n/a, 1=space, 2=dot, 3=quote, 4=comma', $this->get('m_radmil'), false, array( 'id' => 'm_radmil','title'=>'thousands sep', 'size' => 1, 'class' => 'inputbox' )  );

					?>
					decimal sep.:<?php //$this->field( 'select', 'm_radvir', '0=dot, 1=comma', 'value:m_radvir', false, array( 'id' => 'm_radvir', 'size' => 1, 'title'=>'decimal comma', 'class' => 'inputbox' ) );
fieldselect('m_radvir', '0=dot, 1=comma',  $this->get('m_radvir'), false, array( 'id' => 'm_radvir', 'size' => 1, 'title'=>'decimal comma', 'class' => 'inputbox' ) );
 ?>
				</td>
			</tr>

			<tr class="row<?php echo ++$row%2; ?>" style="vertical-align:middle;">
				<td class="key" style="padding: 8px;">
					<label for="m_custDistText" class="editlinktip hasTip" title="<?php $this->txt( 'MJRS.DISTANCE_CUST' ) ?>::<?php $this->txt( 'MJRS.DISTANCE_CUST.HLP' ); ?>">
						<?php $this->txt( 'MJRS.DISTANCE_CUST' ); ?>
					</label>
				</td>
				<td style="padding-right: 8px;">
					<?php // $this->field( 'text', 'm_custDistText', 'value:m_custDistText', array( 'id' => 'm_custDistText', 'size' => 35, 'maxlength' => 255, 'class' => 'inputbox required' ) ); 
fieldtext('text', 'm_custDistText', $this->get('m_custDistText'), array( 'id' => 'm_custDistText', 'size' => 35, 'maxlength' => 255, 'class' => 'inputbox required' ) );

					?>
				</td>
			</tr>

			<tr class="row<?php echo ++$row%2; ?>" style="vertical-align:middle;">
				<td class="key" style="padding: 8px;">
					<label for="m_orderresult" class="editlinktip hasTip" title="<?php $this->txt( 'MJRS.ORDER_VCARD' ) ?>::<?php $this->txt( 'MJRS.ORDER_VCARD.HLP' ); ?>">
						<?php $this->txt( 'MJRS.ORDER_VCARD' ); ?>
					</label>
				</td>
				<td style="padding-right: 8px;">
					<?php // $this->field( 'select', 'm_orderresult', '1=translate:[OPT_YES], 0=translate:[OPT_NO]', 'value:m_orderresult', false, array( 'id' => 'm_orderresult', 'size' => 1, 'class' => 'inputbox' ) );
fieldselect('m_orderresult', '1=Yes, 0=No', $this->get('m_orderresult'), false, array( 'id' => 'm_orderresult', 'size' => 1, 'class' => 'inputbox' ) );
 ?>
				</td>
			</tr>

			<tr class="row<?php echo ++$row%2; ?>" style="vertical-align:middle;">
				<td class="key" style="padding: 8px;">
					<label for="m_googleicon" class="editlinktip hasTip" title="<?php $this->txt( 'MJRS.HIDEGOOGLE' ) ?>::<?php $this->txt( 'MJRS.HIDEGOOGLE.HLP' ); ?>">
						<?php $this->txt( 'MJRS.HIDEGOOGLE' ); ?>
					</label>
				</td>
				<td style="padding-right: 8px;">
					<?php $this->field( 'select', 'm_googleicon', '1=White, 2=Black, 0=translate:[OPT_NO]', 'value:m_googleicon', false, array( 'id' => 'm_googleicon', 'size' => 1, 'class' => 'inputbox' ) ); 
fieldselect('m_googleicon', '1=White, 2=Black, 0=No', $this->get('m_googleicon'), false, array( 'id' => 'm_googleicon', 'size' => 1, 'class' => 'inputbox' ) );
?>
				</td>
			</tr>

			<tr class="row<?php echo ++$row%2; ?>" style="vertical-align:middle;">
				<td class="key" style="padding: 8px; width:220px;">
					<label for="m_inputwidth" class="editlinktip hasTip" title="<?php $this->txt( 'MJRS.INPUTWIDTH' ) ?>::<?php $this->txt( 'MJRS.INPUTWIDTH.HLP' ); ?>">
						<?php $this->txt( 'MJRS.INPUTWIDTH' ); ?></label>
				</td>
				<td style="padding-right: 8px;">
					<?php // $this->field( 'text', 'm_inputwidth', 'value:m_inputwidth', array( 'id' => 'm_inputwidth', 'size' => 5, 'maxlength' => 3 ) ); 
fieldtext('text', 'm_inputwidth', $this->get('m_inputwidth'), array( 'id' => 'm_inputwidth', 'size' => 5, 'maxlength' => 3 ));
					?>
				</td>
			</tr>
			
			<tr class="row<?php echo ++$row%2; ?>" style="vertical-align:middle;">
				<td class="key" style="padding: 8px;">
					<label for="m_uselocateme" class="editlinktip hasTip" title="<?php $this->txt( 'MJRS.LOCATEME' ) ?>::<?php $this->txt( 'MJRS.LOCATEME.HLP' ); ?>">
						<?php $this->txt( 'MJRS.LOCATEME' ); ?>
					</label>
				</td>
				<td style="padding-right: 8px;">
					<?php // $this->field( 'select', 'm_uselocateme', '1=translate:[OPT_YES], 0=translate:[OPT_NO], 2=Icon, 3=Embed-Icon', 'value:m_uselocateme', false, array( 'id' => 'm_uselocateme', 'size' => 1, 'class' => 'inputbox' ) ); 
fieldselect('m_uselocateme', '1=Yes, 0=No, 2=Icon, 3=Embed-Icon', $this->get('m_uselocateme'), false, array( 'id' => 'm_uselocateme', 'size' => 1, 'class' => 'inputbox' ) );
?>
				</td>
			</tr>

			<tr class="row<?php echo ++$row%2; ?>" style="vertical-align:middle;">
				<td class="key" style="padding: 8px;">
					<label for="m_locateStart" class="editlinktip hasTip" title="<?php $this->txt( 'MJRS.LOCATESTART' ) ?>::<?php $this->txt( 'MJRS.LOCATESTART.HLP' ); ?>">
						<?php $this->txt( 'MJRS.LOCATESTART' ); ?>
					</label>
				</td>
				<td style="padding-right: 8px;">
					<?php // $this->field( 'select', 'm_locateStart', '1=translate:[OPT_YES], 0=translate:[OPT_NO]', 'value:m_locateStart', false, array( 'id' => 'm_locateStart', 'size' => 1, 'class' => 'inputbox' ) ); 
fieldselect('m_locateStart', '1=Yes, 0=No', $this->get('m_locateStart'), false, array( 'id' => 'm_locateStart', 'size' => 1, 'class' => 'inputbox' ) );
?>
				</td>
			</tr>

			<tr class="row<?php echo ++$row%2; ?>" style="vertical-align:middle;">
				<td class="key" style="padding: 8px;">
					<label for="m_geocodeMode" class="editlinktip hasTip" title="<?php $this->txt( 'MJRS.GEOCODEMODE' ) ?>::<?php $this->txt( 'MJRS.GEOCODEMODE.HLP' ); ?>">
						<?php $this->txt( 'MJRS.GEOCODEMODE' ); ?>
					</label>

				</td>
				<td style="padding-right: 8px;">
					<?php // $this->field( 'select', 'm_geocodeMode', '0=translate:[MJRS.OPT_GEO_AUTOCOMP], 1=translate:[MJRS.OPT_GEO_SEARCH], 2=translate:[MJRS.OPT_GEO_HYBRID]', 'value:m_geocodeMode', false, array( 'id' => 'm_geocodeMode', 'size' => 1, 'class' => 'inputbox' ) ); 
fieldselect('m_geocodeMode', '0='.Sobi::Txt( 'MJRS.OPT_GEO_AUTOCOMP').', 1='.Sobi::Txt( 'MJRS.OPT_GEO_SEARCH').', 2='.Sobi::Txt( 'MJRS.OPT_GEO_HYBRID'), $this->get('m_geocodeMode'), false, array( 'id' => 'm_geocodeMode', 'size' => 1, 'class' => 'inputbox' ) );
?>
				</td>
			</tr>

			<tr class="row<?php echo ++$row%2; ?>" style="vertical-align:middle;">
				<td class="key" style="padding: 8px;">
					<label for="m_useNotGeocode" class="editlinktip hasTip" title="<?php $this->txt( 'MJRS.USE_NOT_GEOCODED' ) ?>::<?php $this->txt( 'MJRS.USE_NOT_GEOCODED.HLP' ); ?>">
						<?php $this->txt( 'MJRS.USE_NOT_GEOCODED' ); ?>
					</label>
				</td>
				<td style="padding-right: 8px;">
					<?php //$this->field( 'select', 'm_useNotGeocode', '1=translate:[OPT_YES], 0=translate:[OPT_NO]', 'value:m_useNotGeocode', false, array( 'id' => 'm_useNotGeocode', 'size' => 1, 'class' => 'inputbox' ) ); 
fieldselect('m_useNotGeocode', '1=Yes, 0=No', $this->get('m_useNotGeocode'), false, array( 'id' => 'm_useNotGeocode', 'size' => 1, 'class' => 'inputbox' ) );
?>
				</td>
			</tr>

		</table>
	</fieldset>

	<fieldset class="adminform">
		<legend>
			<?php $this->txt( 'MJRS.AUTOCOMPLETE_CONFIG' ); ?>
		</legend>
		<table class="admintable" cellspacing="1">

			<tr class="row<?php echo ++$row%2; ?>" style="vertical-align:middle;">
				<td class="key" style="padding: 8px;">
					<label for="m_restricpt1" class="editlinktip hasTip" title="<?php $this->txt( 'MJRS.RESTRICTPOINT' ) ?>::<?php $this->txt( 'MJRS.RESTRICTPOINT.HLP' ); ?>">
						<?php $this->txt( 'MJRS.RESTRICTPOINT' ); ?>
						<br /><a href="http://www.myjoom.com/sobipro-extensions/radius-search-application/how-to-use-a-defined-area-for-the-places-predictions" target="_Blank">online help (->)</a>
					</label>
				</td>
				<td style="padding-right: 8px;">
					<?php //$this->txt( 'MJRS.RESTRICTPOINT_TOPLEFT' ); ?> lat,long : <?php // $this->field( 'text', 'm_restricpt1', 'value:m_restricpt1', array( 'id' => 'm_restricpt1', 'size' => 30, 'maxlength' => 255, 'class' => 'inputbox required' ) ); 
fieldtext('text', 'm_restricpt1', $this->get('m_restricpt1'), array( 'id' => 'm_restricpt1', 'size' => 30, 'maxlength' => 255, 'class' => 'inputbox required' ));

					?>
					<br />
					<?php //$this->txt( 'MJRS.RESTRICTPOINT_BOTRIGHT' ); ?> lat,long : <?php //$this->field( 'text', 'm_restricpt2', 'value:m_restricpt2', array( 'id' => 'm_restricpt2', 'size' => 30, 'maxlength' => 255, 'class' => 'inputbox required' ) ); 
fieldtext('text', 'm_restricpt2', $this->get('m_restricpt2'), array( 'id' => 'm_restricpt2', 'size' => 30, 'maxlength' => 255, 'class' => 'inputbox required' ));

					?>
				</td>
			</tr>

			<tr class="row<?php echo ++$row%2; ?>" style="vertical-align:middle;">
				<td class="key" style="padding: 8px;">
					<label for="m_inputText" class="editlinktip hasTip" title="<?php $this->txt( 'MJRS.INPUT_TXT' ) ?>::<?php $this->txt( 'MJRS.INPUT_TXT.HLP' ); ?>">
						<?php $this->txt( 'MJRS.INPUT_TXT' ); ?>
					</label>
				</td>
				<td style="padding-right: 8px;">
					<?php // $this->field( 'text', 'm_inputText', 'value:m_inputText', array( 'id' => 'm_inputText', 'size' => 35, 'maxlength' => 255, 'class' => 'inputbox required' ) ); 
fieldtext('text', 'm_inputText', $this->get('m_inputText'), array( 'id' => 'm_inputText', 'size' => 35, 'maxlength' => 255, 'class' => 'inputbox required' ));

					?>
				</td>
			</tr>

			<tr class="row<?php echo ++$row%2; ?>" style="vertical-align:middle;">
				<td class="key" style="padding: 8px;">
					<label for="m_acTypes" class="editlinktip hasTip" title="<?php $this->txt( 'MJRS.GEOCODE_TYPES' ) ?>::<?php $this->txt( 'MJRS.GEOCODE_TYPES.HLP' ); ?>">
						<?php $this->txt( 'MJRS.GEOCODE_TYPES' ); ?>
					</label>
				</td>
				<td style="padding-right: 8px;">
					<?php // $this->field( 'select', 'm_acTypes', "[]=translate:[MJRS.OPT_ALL],['establishment']=translate:[MJRS.OPT_ESTABLISHEMENTS],['geocode']=translate:[MJRS.OPT_GEOCODES],['(regions)']=translate:[MJRS.OPT_CITY_REG],['(cities)']=translate:[MJRS.OPT_CITY_ONLY]", 'value:m_acTypes', false, array( 'id' => 'm_acTypes', 'size' => 1, 'class' => 'inputbox' ) ); 
fieldselect('m_acTypes', "[]=translate:[MJRS.OPT_ALL],['establishment']=translate:[MJRS.OPT_ESTABLISHEMENTS],['geocode']=translate:[MJRS.OPT_GEOCODES],['(regions)']=translate:[MJRS.OPT_CITY_REG],['(cities)']=translate:[MJRS.OPT_CITY_ONLY]", $this->get('m_acTypes'), false, array( 'id' => 'm_acTypes', 'size' => 1, 'class' => 'inputbox' ));



?>
				</td>
			</tr>

			<tr class="row<?php echo ++$row%2; ?>" style="vertical-align:middle;">
				<td class="key" style="padding: 8px;">
					<label for="m_acCountry" class="editlinktip hasTip" title="<?php $this->txt( 'MJRS.COUNTRYCODE' ) ?>::<?php $this->txt( 'MJRS.COUNTRYCODE.HLP' ); ?>">
						<?php $this->txt( 'MJRS.COUNTRYCODE' ); ?>
						<br /><a href="http://en.wikipedia.org/wiki/ISO_3166-1_alpha-2" target="_Blank">ISO 3166-1 Alpha-2 country list</a>
					</label>
				</td>
				<td style="padding-right: 8px;">
					<?php //$this->field( 'text', 'm_acCountry', 'value:m_acCountry', array( 'id' => 'm_acCountry', 'size' => 5, 'maxlength' => 2, 'class' => 'inputbox required' ) ); 
fieldtext('text', 'm_acCountry', $this->get('m_acCountry'), array( 'id' => 'm_acCountry', 'size' => 5, 'maxlength' => 2, 'class' => 'inputbox required' ));

					?>
				</td>
			</tr>
			
			<tr class="row<?php echo ++$row%2; ?>" style="vertical-align:middle;">
				<td class="key" style="padding: 8px;">
					<label for="m_mapVariable" class="editlinktip hasTip" title="<?php $this->txt( 'MJRS.MAP_CONTAINER' ) ?>::<?php $this->txt( 'MJRS.MAP_CONTAINER.HLP' ); ?>">
						<?php $this->txt( 'MJRS.MAP_CONTAINER' ); ?>
					</label>
				</td>
				<td style="padding-right: 8px;">
					<?php //$this->field( 'text', 'm_mapVariable', 'value:m_mapVariable', array( 'id' => 'm_mapVariable', 'size' => 35, 'maxlength' => 255 ) ); 
fieldtext('text', 'm_mapVariable', $this->get('m_mapVariable'), array( 'id' => 'm_mapVariable', 'size' => 35, 'maxlength' => 255 ));

					?>
				</td>
			</tr>

		</table>
	</fieldset>

	<fieldset class="adminform">
		<legend>
			<?php $this->txt( 'MJRS.SALESAREA_CONFIG' ); ?>
		</legend>
		<table class="admintable" cellspacing="1">

			<tr class="row<?php echo ++$row%2; ?>" style="vertical-align:middle;">
				<td class="key" style="padding: 8px;">
					<label for="m_salesAreaVcMode" class="editlinktip hasTip" title="<?php $this->txt( 'MJRS.SALESAREA_VC_MODE' ) ?>::<?php $this->txt( 'MJRS.SALESAREA_VC_MODE.HLP' ); ?>">
						<?php $this->txt( 'MJRS.SALESAREA_VC_MODE' ); ?>
					</label>
				</td>
				<td style="padding-right: 8px;">
					<?php // $this->field( 'select', 'm_salesAreaVcMode', '0=translate:[MJRS.OPT_SA_VC_FULL], 1=translate:[MJRS.OPT_SA_VC_USER], 2=translate:[MJRS.OPT_SA_VC_BOTH]', 'value:m_salesAreaVcMode', false, array( 'id' => 'm_salesAreaVcMode', 'size' => 1, 'class' => 'inputbox' ) ); 
fieldselect('m_salesAreaVcMode', '0=translate:[MJRS.OPT_SA_VC_FULL], 1=translate:[MJRS.OPT_SA_VC_USER], 2=translate:[MJRS.OPT_SA_VC_BOTH]', $this->get('m_salesAreaVcMode'), false, array( 'id' => 'm_salesAreaVcMode', 'size' => 1, 'class' => 'inputbox' ) );
?>
				</td>
			</tr>

			<tr class="row<?php echo ++$row%2; ?>" style="vertical-align:middle;">
				<td class="key" style="padding: 8px;">
					<label for="m_salesAreaDist" class="editlinktip hasTip" title="<?php $this->txt( 'MJRS.SALESAREADISTANCE' ) ?>::<?php $this->txt( 'MJRS.SALESAREADISTANCE.HLP' ); ?>">
						<?php $this->txt( 'MJRS.SALESAREADISTANCE' ); ?>
					</label>
				</td>
				<td style="padding-right: 8px;">
					<?php // $this->field( 'select', 'm_salesAreaDist', $field_val, 'value:m_salesAreaDist', false, array( 'id' => 'm_salesAreaDist', 'size' => 1, 'class' => 'inputbox' ) ); 
fieldselect('m_salesAreaDist', $field_val, $this->get('m_salesAreaDist'), false, array( 'id' => 'm_salesAreaDist', 'size' => 1, 'class' => 'inputbox' ) );
?>
				</td>
			</tr>

		</table>
	</fieldset>

	<fieldset class="adminform">
		<legend>
			<?php $this->txt( 'MJRS.EXCLUSION_CONFIG' ); ?>
		</legend>
		<table class="admintable" cellspacing="1">

			<tr class="row<?php echo ++$row%2; ?>" style="vertical-align:middle;">
				<td class="key" style="padding: 8px;">
					<label for="m_exclusionField" class="editlinktip hasTip" title="<?php $this->txt( 'MJRS.EXCLUSION_FIELD' ) ?>::<?php $this->txt( 'MJRS.EXCLUSION_FIELD.HLP' ); ?>">
						<?php $this->txt( 'MJRS.EXCLUSION_FIELD' ); ?>
					</label>
				</td>
				<td style="padding-right: 8px;">
					<?php // $this->field( 'select', 'm_exclusionField', $fieldsEx, 'value:m_exclusionField', false, array( 'id' => 'm_exclusionField', 'size' => 1, 'class' => 'inputbox' ) ); 
	fieldselect('m_exclusionField', $fieldsEx, $this->get('m_exclusionField'), false, array( 'id' => 'm_exclusionField', 'size' => 1, 'class' => 'inputbox' ) );
?>
					<?php // $this->field( 'text', 'm_exclusionFilter', 'value:m_exclusionFilter', array( 'id' => 'm_exclusionFilter', 'size' => 35, 'maxlength' => 255 ) ); 
fieldtext('text', 'm_exclusionFilter', $this->get('m_exclusionFilter'), array( 'id' => 'm_exclusionFilter', 'size' => 35, 'maxlength' => 255 ));

					?>
				</td>
			</tr>

		</table>
		<?php $this->txt( 'MJRS.EXCLUSION_NOTE' ); ?>
	</fieldset>

	<fieldset class="adminform">
		<legend>
			<?php $this->txt( 'MJRS.EXCLUSION_GOOGLE_CONFIG' ); ?>
		</legend>
		<table class="admintable" cellspacing="1">

			<tr class="row<?php echo ++$row%2; ?>" style="vertical-align:middle;">
				<td class="key" style="padding: 8px;">
					<label for="m_exclusionFieldGoogle" class="editlinktip hasTip" title="<?php $this->txt( 'MJRS.EXCLUSION_FIELD_GOOGLE' ) ?>::<?php $this->txt( 'MJRS.EXCLUSION_FIELD_GOOGLE.HLP' ); ?>">
						<?php $this->txt( 'MJRS.EXCLUSION_FIELD_GOOGLE' ); ?>
					</label>
				</td>
				<td style="padding-right: 8px;">
					<?php //$this->field( 'select', 'm_exclusionFieldGoogle', $fieldsEx, 'value:m_exclusionFieldGoogle', false, array( 'id' => 'm_exclusionFieldGoogle', 'size' => 1, 'class' => 'inputbox' ) ); 
fieldselect('m_exclusionFieldGoogle', $fieldsEx, $this->get('m_exclusionFieldGoogle'), false, array( 'id' => 'm_exclusionFieldGoogle', 'size' => 1, 'class' => 'inputbox' ));
?>
					<?php //$this->field( 'select', 'm_exclusionValueGoogle', $fieldsExGg, 'value:m_exclusionValueGoogle', false, array( 'id' => 'm_exclusionFieldGoogle', 'size' => 1, 'class' => 'inputbox' ) ); 

$_fieldsEx = array() ;
foreach ($fieldsExGg as $key => $value) {
	$_fieldsEx[]= $key.'='.$value  ;
}


fieldselect('m_exclusionValueGoogle', implode(',',$_fieldsEx), $this->get('m_exclusionValueGoogle'), false, array( 'id' => 'm_exclusionFieldGoogle', 'size' => 1, 'class' => 'inputbox' ) );
?>
				</td>
			</tr>

		</table>
		<?php $this->txt( 'MJRS.EXCLUSION_CONFIG_NOTE' ); ?>
	</fieldset>

	<fieldset class="adminform">
		<legend>
			<?php $this->txt( 'MJRS.DURING_CONFIG' ); ?>
		</legend>
		<table class="admintable" cellspacing="1">

			<tr class="row<?php echo ++$row%2; ?>" style="vertical-align:middle;">
				<td class="key" style="padding: 8px;">
					<label for="m_defaultcenter" class="editlinktip hasTip" title="<?php $this->txt( 'MJRS.DEF_CENTER' ) ?>::<?php $this->txt( 'MJRS.DEF_CENTER.HLP' ); ?>">
						<?php $this->txt( 'MJRS.DEF_CENTER' ); ?>
					</label>
				</td>
				<td style="padding-right: 8px;">
					<?php // $this->field( 'text', 'm_defaultcenter', 'value:m_defaultcenter', array( 'id' => 'm_defaultcenter', 'size' => 35, 'maxlength' => 255) ); 
fieldtext('text', 'm_defaultcenter', $this->get('m_defaultcenter'), array( 'id' => 'm_defaultcenter', 'size' => 35, 'maxlength' => 255));

					?>
				</td>
			</tr>

		</table>
	</fieldset>

	<fieldset class="adminform">
		<legend>
			<?php $this->txt( 'MJRS.INSTRUCTION' ); ?>
		</legend>
		<?php $this->show( 'description' ); ?>
	</fieldset>
	
	
</div>
