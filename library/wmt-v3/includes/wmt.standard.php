<?php 
if(!isset($GLOBALS['fileroot'])) include_once('../../globals.php');
require_once($GLOBALS['fileroot'].'/custom/code_types.inc.php');
require_once($GLOBALS['srcdir'].'/lists.inc');
require_once($GLOBALS['srcdir'].'/wmt-v2/list_tools.inc');
require_once($GLOBALS['srcdir'].'/wmt-v2/wmtSettings.inc');
require_once($GLOBALS['srcdir'].'/wmt-v2/wmtportal.inc');
require_once($GLOBALS['srcdir'].'/wmt-v2/wmtcalendar.inc');

if($GLOBALS['date_display_format'] == 1) {
	$date_img_fmt = '%m/%d/%Y';
	$date_title_fmt = 'MM/DD/YYYY';
} else if($GLOBALS['date_display_format'] == 2) {
	$date_img_fmt = '%d/%m/%Y';
	$date_title_fmt = 'DD/MM/YYYY';
} else {
	$date_img_fmt = '%Y-%m-%d';
	$date_title_fmt = 'YYYY-MM-DD';
}

if(!defined('FORM_BUTTONS')) {
	define('FORM_BUTTONS', $GLOBALS['srcdir'].'/wmt-v2/form_buttons/');
	define('FORM_BUTTONS_JS', $GLOBALS['webroot'].'/library/wmt-v2/form_buttons/');
	define('FORM_JS_DIR', $GLOBALS['webroot'].'/library/wmt-v2/form_js/');
	define('FORM_MODULES', $GLOBALS['srcdir'].'/wmt-v2/form_modules/');
	define('FORM_MODULES_JS', $GLOBALS['webroot'].'/library/wmt-v2/form_modules/');
	define('FORM_VIEWS', $GLOBALS['srcdir'].'/wmt-v2/form_views/');
	define('FORM_PREPROCESS', $GLOBALS['srcdir'].'/wmt-v2/form_pre_process/');
	define('FORM_PREPROCESS_JS', $GLOBALS['webroot'].'/library/wmt-v2/form_pre_process/');
	define('FORM_PROCESS', $GLOBALS['srcdir'].'/wmt-v2/form_process/');
	define('FORM_PROCESS_JS', $GLOBALS['webroot'].'/library/wmt-v2/form_process/');
	define('FORM_BRICKS', $GLOBALS['srcdir'].'/wmt-v2/form_bricks/');
	define('FORM_BRICKS_JS', $GLOBALS['webroot'].'/library/wmt-v2/form_bricks/');
}

if(!function_exists('ReportMissingPID')) {
function ReportMissingPID()
{
	echo "<br><h><b>The Session Patient ID Seems To Be Missing!</b></h><br>\n";
	echo "<b>This is usually due to an idle session timing out, please log out and back in to reset session variables</b><br/>\n";
	exit;
}
}

if(!function_exists('ReportMissingEncounter')) {
function ReportMissingEncounter()
{
	echo "<br><h><b>The Session Encounter ID Seems To Be Missing!</b></h><br>\n";
	echo "<b>This is usually due to an idle session timing out, please log out and back in to reset session variables</b><br/>\n";
	exit;
}
}

if(!function_exists('ReportMissingID')) {
function ReportMissingID()
{
	echo "<h><b>The Form ID Seems To Be Missing!</b></h><br/>\n";
	echo "<b>This is usually due to an idle session timing out, please log out and back in to reset session variables</b><br/>\n";
	exit;
}
}

if(!function_exists('getNameOrNickName')) {
function getNameOrNickName($frmdir = '')
{
	if(!$frmdir) return '';
	$frow = sqlQuery('SELECT * FROM registry WHERE directory=?',array($frmdir));
	$name = $frow{'name'};
	if($frow{'nickname'} != '') $name = $frow{'nickname'};
	return $name;
}
}

if(!function_exists('FlagListSel')) {
function FlagListSel($thisField, $thisList, $exists, $default) {
  $rlist= sqlStatementNoLog("SELECT * FROM list_options WHERE list_id=? AND ".
			"(UPPER(notes) NOT LIKE '%DO NOT USE%') ORDER BY seq", array($thisList));
  echo "<option value=''";
  if(!$thisField && $exists) echo " selected='selected'";
  echo ">&nbsp;</option>";
  while ($rrow= sqlFetchArray($rlist)) {
    echo "<option value='" . $rrow['option_id'] . "'";
    if(($thisField == $rrow['option_id']) || (!$exists && ($rrow['option_id'] == $default))) echo " selected='selected'";
    echo ">" . htmlspecialchars($rrow['title'], ENT_NOQUOTES);
    echo "</option>";
  }
}
}

if(!function_exists('DrugUseListSel')) {
function DrugUseListSel($thisField) {
  echo "<option value=''";
  if(!$thisField) echo " selected='selected'";
  echo ">&nbsp;</option>";
  echo "<option value='neverrecreational_drugs'";
  if($thisField == 'neverrecreational_drugs') echo " selected='selected'";
  echo ">Never</option>\n";
  echo "<option value='currentrecreational_drugs'";
  if($thisField == 'currentrecreational_drugs') echo " selected='selected'";
  echo ">Current</option>\n";
  echo "<option value='quitrecreational_drugs'";
  if($thisField == 'quitrecreational_drugs') echo " selected='selected'";
  echo ">Quit</option>\n";
  echo "<option value='not_applicablerecreational_drugs'";
  if($thisField == 'not_applicablerecreational_drugs') echo " selected='selected'";
  echo ">N/A</option>\n";
}
}

if(!function_exists('DrugUseListLook')) {
function DrugUseListLook($thisField) {
	$ret = '';
  if($thisField == 'neverrecreational_drugs') { $ret = 'Never'; }
  if($thisField == 'currentrecreational_drugs') { $ret = 'Current'; }
  if($thisField == 'quitrecreational_drugs') { $ret = 'Quit'; }
  if($thisField == 'not_applicablerecreational_drugs') { $ret = 'N/A'; }
	return $ret;
}
}

if(!function_exists('AlcoholUseListSel')) {
function AlcoholUseListSel($thisField) {
  echo "<option value=''";
  if(!$thisField) echo " selected='selected'";
  echo ">&nbsp;</option>";
  echo "<option value='neveralcohol'";
  if($thisField == 'neveralcohol') echo " selected='selected'";
  echo ">Never</option>\n";
  echo "<option value='currentalcohol'";
  if($thisField == 'currentalcohol') echo " selected='selected'";
  echo ">Current</option>\n";
  echo "<option value='quitalcohol'";
  if($thisField == 'quitalcohol') echo " selected='selected'";
  echo ">Quit</option>\n";
  echo "<option value='not_applicablealcohol'";
  if($thisField == 'not_applicablealcohol') echo " selected='selected'";
  echo ">N/A</option>\n";
}
}

if(!function_exists('AlcoholUseListLook')) {
function AlcoholUseListLook($thisField) {
	$ret = '';
  if($thisField == 'neveralcohol') $ret = 'Never';
  if($thisField == 'currentalcohol') $ret = 'Current';
  if($thisField == 'quitalcohol') $ret = 'Quit';
  if($thisField == 'not_applicablealcohol') $ret = 'N/A';
	return $ret;
}
}

if(!function_exists('ListSel')) {
function ListSel($thisField, $thisList, $empty_label = '') {
  $rlist= sqlStatementNoLog("SELECT * FROM list_options WHERE list_id=? AND ".
		// "seq >= 0 AND (UPPER(notes) NOT LIKE '%DO NOT USE%') ".
		"seq >= 0 ORDER BY seq, title",array($thisList));
  echo "<option value=''";
  // if($thisField == '') echo " selected='selected'";
  echo ">$empty_label&nbsp;</option>";
  while ($rrow= sqlFetchArray($rlist)) {
    echo "<option value='" . $rrow{'option_id'} . "'";
    if($thisField == $rrow{'option_id'}) {
			echo " selected='selected'";
		} else if(empty($thisField)) {
			if($rrow{'is_default'} == 1) echo " selected='selected'";
		}
    echo ">" . htmlspecialchars($rrow{'title'}, ENT_NOQUOTES);
    echo "</option>";
  }
}
}

if(!function_exists('NumSel')) {
function NumSel($thisField, $min, $max, $inc=1, $default = '', $allow_empty = false, $empty_label = '') {
	if($allow_empty) {
  	echo '<option value=""';
  	if($thisField == '') echo ' selected="selected"';
  	echo ">$empty_label&nbsp;</option>";
	}
  while ($min <= $max) {
    echo "<option value='$min'";
    if($thisField == $min) {
			echo " selected='selected'";
		} else if(empty($thisField)) {
			if($min == $default) echo " selected='selected'";
		}
    echo ">$min</option>";
		$min = $min + $inc;
  }
}
}

if(!function_exists('getListOptions')) {
function getListOptions($thisList) {
  $rlist= sqlStatementNoLog("SELECT * FROM list_options WHERE list_id=? AND ".
		"seq >= 0 AND (UPPER(notes) NOT LIKE '%DO NOT USE%') ORDER BY seq, title",
		array($thisList));
	$all = array();
	$iter = 0;
  while($row = sqlFetchArray($rlist)) {
  	$all[$iter] = $row;
		$iter++;
	}
	return $all;
}
}

if(!function_exists('ListSelString')) {
function ListSelString($thisField, $thisList) {
  $rlist= sqlStatementNoLog("SELECT * FROM list_options WHERE list_id=? AND ".
		"seq >= 0 AND (UPPER(notes) NOT LIKE '%DO NOT USE%') ORDER BY seq, title", 
		array($thisList));
  $ret = "<option value=''";
  // if(!$thisField) $ret .= " selected='selected'";
  $ret .= ">&nbsp;</option>";
  while ($rrow= sqlFetchArray($rlist)) {
    $ret .= "<option value='" . $rrow{'option_id'} . "'";
    if($thisField == $rrow{'option_id'}) {
			$ret .= " selected='selected'";
		} else if(empty($thisField)) {
			if($rrow{'is_default'} == 1) {
				$ret .= " selected='selected'";
			}
		}
    $ret .= ">". htmlspecialchars($rrow{'title'}, ENT_NOQUOTES)."</option>";
  }
	return $ret;
}
}

if(!function_exists('ListSelAlpha')) {
function ListSelAlpha($thisField, $thisList) {
  $rlist= sqlStatementNoLog("SELECT * FROM list_options WHERE list_id=? AND ".
		"seq >= 0 AND (UPPER(notes) NOT LIKE '%DO NOT USE%') ORDER BY title, seq", 
		array($thisList));
  echo "<option value=''";
  // if(!$thisField) echo " selected='selected'";
  echo ">&nbsp;</option>";
  while ($rrow= sqlFetchArray($rlist)) {
    echo "<option value='" . $rrow{'option_id'} . "'";
    if($thisField == $rrow{'option_id'}) {
			echo " selected='selected'";
		} else if(empty($thisField)) {
			if($rrow{'is_default'} == 1) echo " selected='selected'";
		}
    echo ">" . htmlspecialchars($rrow{'title'}, ENT_NOQUOTES);
    echo "</option>";
  }
}
}

if(!function_exists('MultListSel')) {
function MultListSel($thisArray, $thisList, $thisVal='') {
  $rlist= sqlStatementNoLog("SELECT * FROM list_options WHERE list_id=? AND ".
		"seq >= 0 AND (UPPER(notes) NOT LIKE '%DO NOT USE%') ORDER BY seq",
		array($thisList));
  echo "<option value=''";
  // if(!$thisArray[0]) echo " selected='selected'";
	if(count($thisArray) == 0) { 
		echo " selected='selected'"; 
	} elseif ($thisArray[0] == '') {
		echo " selected='selected'"; 
	}
  echo ">Unassigned</option>";
  while ($rrow= sqlFetchArray($rlist)) {
    echo "<option value='" . $rrow['option_id'] . "'";
    if(is_array($thisArray)) {
      if(in_array($rrow['option_id'],$thisArray)) echo " selected='selected'";
    }
    echo ">" . htmlspecialchars($rrow['title'], ENT_NOQUOTES);
    echo "</option>";
  }
}
}

if(!function_exists('MultLook')) {
function MultLook($thisArray, $thisList, $thisVal='') {
  $dispVal='';
  if((!($thisArray[0])) || (!is_array($thisArray))) {
    echo "<option value='' selected='selected'>Unassigned</option>";
    return;
  }
  foreach ($thisArray as $rrow) {
    $lres = sqlStatementNoLog("SELECT * FROM list_options WHERE list_id=? AND ".
				"option_id =?",array($thisList, $rrow));
    $llist = sqlFetchArray($lres);
    echo "<option value='".$rrow."' selected='selected'>".
			htmlspecialchars($llist['title'], ENT_NOQUOTES)."</option>";
  }
}
}

if(!function_exists('ListLook')) {
function ListLook($thisData, $thisList, $err = '* Not Found *') {
  if($thisList == 'occurrence') {
    if(!$thisData || $thisData == '') return 'Unknown or N/A'; 
  }
  if($thisData == '') return ''; 
	$dispValue = '';
  $rret=sqlQuery("SELECT * FROM list_options WHERE list_id=? ".
        "AND option_id=?", array($thisList, $thisData));
  if($rret{'title'} != '') {
    $dispValue= $rret{'title'};
    if($thisList == 'occurrence' && $dispValue == '') {
      $dispValue = 'Unknown or N/A';
    }
  } else {
    if($thisData != '0') $dispValue= $err;
  }
  return htmlspecialchars($dispValue, ENT_QUOTES, '', FALSE);
}
}

if(!function_exists('SetSmokingDesc')) {
function SetSmokingDesc($thisValue) {
  $retval= '0';
  if(!$thisValue) $retval='0';
  if($thisValue == 1) $retval='currenttobacco';
  if($thisValue == 2) $retval='currenttobacco';
  if($thisValue == 3) $retval='quittobacco';
  if($thisValue == 4) $retval='nevertobacco';
  if($thisValue == 5) $retval='not_applicabletobacco';
  if($thisValue == 9) $retval='not_applicabletobacco';
  return $retval;
}
}

if(!function_exists('getPastPregnancies')) {
function getPastPregnancies($thisPid, $thisEnc='') {
  $sql= "SELECT * FROM form_whc_pp WHERE pid=? ORDER BY pp_date_of_pregnancy";
	$binds=array($thisPid);
	$all=array();
	if($thisEnc != '') {
  	$sql = "SELECT form_wmt_ll.list_id, form_whc_pp.* FROM form_wmt_ll ".
			"LEFT JOIN form_whc_pp on form_wmt_ll.list_id=form_whc_pp.id WHERE ".
			"form_wmt_ll.pid=? AND form_wmt_ll.list_type=? AND ".
			"form_wmt_ll.encounter_id=? ORDER BY pp_date_of_pregnancy";
		$binds=array($thisPid, 'past_pregnancy', $thisEnc);
	}
  $res= sqlStatementNoLog($sql, $binds);

	$iter = 0;
  while($row = sqlFetchArray($res)) {
  	$all[$iter] = $row;
		$sql = "SELECT COUNT(*) FROM form_wmt_ll WHERE form_wmt_ll.list_type=? ".
				"AND form_wmt_ll.list_id=?";
		$chk = sqlStatementNoLog($sql, array('past_pregnancy', $all[$iter]['id']));
		$num = sqlFetchArray($chk);
		$all[$iter]['num_links']= $num['COUNT(*)'];
		$iter++;
	}
  return $all;
}
}

if(!function_exists('getNextOrder')) {
function getNextOrder($thisPid, $cols='*') {
  $retVal=0;
  $sql= "SELECT $cols FROM form_whc_pp WHERE pid=? ORDER BY ".
		"pp_order DESC LIMIT 1";
  $result=sqlStatementNoLog($sql, array($thisPid));
  if($result) {
    $row=sqlFetchArray($result);
    if($row{'pp_order'}) $retVal=$row{'pp_order'};
  }
  $retVal++;
  return($retVal);
}
}

if(!function_exists('GetList')) {
function GetList($thisPid, $type, $enc='', $dt='', $mode='show_enc') {
  $sql = "SELECT * FROM lists WHERE pid=? AND type=? AND activity = 1 ".
		"ORDER BY begdate";
	$list_type = $type;
	if($type == 'allergy' || $type == 'medication') {
  	$sql = "SELECT * FROM lists WHERE pid=? AND type=? ";
		if($mode != 'show_all') $sql .= "AND (enddate IS NULL ".
			"OR enddate = '0000-00-00' OR enddate = '') ";
		if($type != 'allergy') $sql .= "AND activity = 1 ";
		$sql .= "ORDER BY begdate";
	}
	if($type == 'med_history') {
  	$sql = "SELECT * FROM lists WHERE pid=? AND type=? AND (enddate IS NOT ".
			"NULL AND enddate != '0000-00-00' AND enddate != '') AND activity = 1 ".
			"ORDER BY begdate";
		$list_type = 'medication';
	}
	$binds=array($thisPid, $list_type);
	if($type == 'ultrasound' && $dt != '') {
  	$sql = "SELECT * FROM lists WHERE pid = ? AND type = ? AND activity = 1 ".
         "AND begdate > ? ORDER BY begdate";
		$binds=array($thisPid, $list_type, $dt);
	}
	if($enc != '') {
  	$sql = "SELECT form_wmt_ll.list_id, lists.* FROM form_wmt_ll LEFT JOIN ".
				"lists on form_wmt_ll.list_id = lists.id ";
		$sql .= "WHERE form_wmt_ll.pid=? ".
				"AND form_wmt_ll.list_type=? AND form_wmt_ll.encounter_id=? ";
		if($type == 'med_history') {
			$sql .= "AND (enddate IS NOT NULL AND enddate != '0000-00-00' ".
					"AND enddate != '') ";
		}
		if($type == 'allergy' || $type == 'medication') {
			if($mode != 'show_all') $sql .= "AND (enddate IS NULL OR ".
					"enddate='0000-00-00' OR enddate='') ";
		}
		$sql .= "ORDER BY begdate";
		$binds=array($thisPid, $list_type, $enc);
	}

	$all=array();
  $res = sqlStatementNoLog($sql,$binds);
	// echo "Statement: ",$sql,"<br>\n";
	$iter=0;
  while($row = sqlFetchArray($res)) {
  	$all[$iter] = $row;
		// echo "Row Found: ";
		// echo print_r($row);
		// echo "<br>\n";
		$sql = "SELECT COUNT(*) FROM form_wmt_ll WHERE form_wmt_ll.list_type=? ".
				"AND form_wmt_ll.list_id=?";
		$chk = sqlStatementNoLog($sql, array($list_type, $all[$iter]['id']));
		$num = sqlFetchArray($chk);
		$all[$iter]['num_links']= $num['COUNT(*)'];
		$iter++;
	}
  return $all;
}
}

if(!function_exists('GetProblemsWithDiags')) {
function GetProblemsWithDiags($thisPid, $mode='all', $thisEnc=0, $type='ICD') {
	// Select all here, set a flag in the row so we know if it's linked or not
	$binds = array();
	$sql = 'SELECT id, title, begdate, enddate, occurrence, diagnosis, '.
			'comments, outcome, list_id, encounter, issue_encounter.seq FROM lists '.
			'LEFT JOIN issue_encounter ON list_id=id ';
	$sql .= 'AND encounter=? WHERE ';
	$binds[] = $thisEnc;
	if($mode == 'enc' || $mode == 'encounter') {
		$sql .= 'encounter = ? AND ';
		$binds[] = $thisEnc;
	}
	$sql .=	'type = "medical_problem" AND lists.pid = ? AND diagnosis LIKE '.
			'? AND activity = 1 ORDER BY seq * 1 ASC, seq ASC';
	$binds[] = $thisPid;
	$binds[] = $type.'%';
	$all=array();
  $res = sqlStatementNoLog($sql,$binds);
  for($iter =0;$row = sqlFetchArray($res);$iter++) {
		$all[$iter] = $row;
		$sql = "SELECT COUNT(*) FROM form_wmt_ll WHERE form_wmt_ll.list_type=? ".
				"AND form_wmt_ll.list_id=?";
		$chk = sqlStatementNoLog($sql, array('medical_problem', $all[$iter]['id']));
		$num = sqlFetchArray($chk);
		$all[$iter]['num_links']= $num['COUNT(*)'];
		$iter++;
	}
  return $all;
}
}

if(!function_exists('GetListbyTitle')) {
function GetListbyTitle($thisPid, $type, $title) {
	$title='%'.$title.'%';
  $sql = "SELECT * FROM lists WHERE pid = ? AND type = ? AND activity = 1 ".
         "AND title LIKE ? ORDER BY begdate";
	$all=array();
  $res = sqlStatementNoLog($sql,array($thisPid, $type, $title));
  for($iter =0;$row = sqlFetchArray($res);$iter++)
  $all[$iter] = $row;
  return $all;
}
}

if(!function_exists('GetFYI')) {
function GetFYI($thisPid) {
  $sql = "SELECT fyi FROM form_fyi WHERE pid=?";
  $res = sqlStatementNoLog($sql,array($thisPid));
  $rrow = sqlFetchArray($res);
  return($rrow['fyi']);
}
}

if(!function_exists('UpdateFYI')) {
function UpdateFYI($thisPid, $fyi) {
  $sql = "INSERT INTO form_fyi (date, pid, user, groupname, authorized, ".
        "activity, fyi) VALUES (NOW(), ?, ?, ?, ?, '1', ?) ".
				"ON DUPLICATE KEY UPDATE fyi=?, date=NOW(), user=?, authorized=?";
	$binds=array($thisPid, $_SESSION['authUser'], $_SESSION['authProvider'],
			$_SESSION['userauthorized'], $fyi, $fyi, $_SESSION['authUser'], 
			$_SESSION['userauthorized']);
  sqlInsert($sql, $binds);
  return(true);
}
}

if(!function_exists('DiagLook')) {
function DiagLook($thisData) {
  if(!$thisData || $thisData == '') {
    return ''; 
  }
  $fres= sqlStatementNoLog("SELECT * FROM codes WHERE code_type='2' ".
           "AND active='1' AND code=?", array($thisData));
  $rret=sqlFetchArray($fres);
  $dispValue= $rret{'code'}.'-'.$rret{'code_text'};
  return $dispValue;
}
}

if(!function_exists('DiagDescLook')) {
function DiagDescLook($thisData) {
  if(!$thisData || $thisData == '') {
    return ''; 
  }
  $fres= sqlStatementNoLog("SELECT code_text FROM codes WHERE code_type='2' ".
           "AND active='1' AND code=?", array($thisData));
  $rret=sqlFetchArray($fres);
  $dispValue= $rret{'code_text'};
  return $dispValue;
}
}

if(!function_exists('UserSelect')) {
function UserSelect($thisField, $blank='') {
  $rlist= sqlStatementNoLog("SELECT * FROM users WHERE " .
           "active=1 AND username != '' ORDER BY lname");
  echo "<option value=''";
  if(!$thisField) echo " selected='selected'";
  echo ">$blank&nbsp;</option>";
  while ($rrow= sqlFetchArray($rlist)) {
    echo "<option value='" . $rrow['username'] . "'";
    if($thisField == $rrow['username']) echo " selected='selected'";
    echo ">" . $rrow['lname'].', '.$rrow['fname'].' '.$rrow['mname'];
    echo "</option>";
  }
}
}

if(!function_exists('DoctorSelect')) {
function DoctorSelect($thisField) {
  $rlist= sqlStatementNoLog("SELECT * FROM users WHERE " .
           "active=1 AND username != '' ORDER BY lname");
  echo "<option value=''";
  if(!$thisField) echo " selected='selected'";
  echo ">&nbsp;</option>";
  while ($rrow= sqlFetchArray($rlist)) {
    echo "<option value='" . $rrow['username'] . "'";
    if($thisField == $rrow['username']) echo " selected='selected'";
    echo ">" . $rrow['lname'].', '.$rrow['fname'].' '.$rrow['mname'];
    echo "</option>";
  }
}
}

if(!function_exists('IsSupervisor')) {
function IsSupervisor($user) {
	if(!$user) return false;
	$sql = "SELECT id, username, specialty FROM users WHERE username=?";
	$frow = sqlQuery($sql, array($user));
	$test = strtolower($frow{'specialty'});
	$val = strpos($test,'supervisor');
	if($val === false) return false;
	return true;
}
}

if(!function_exists('ReferringSelect')) {
function ReferringSelect($thisField, $special_title='', $specialty='', $display_extra = '', $allow_empty=true) {
	if($specialty) {
		$specialty = "AND UPPER(specialty) LIKE UPPER('%$specialty%')";
	}
  $sql = "SELECT id, lname, fname, mname, specialty";
	if($display_extra) { $sql .= ", $display_extra"; }
	$sql .= " FROM users WHERE active=1 AND (lname != '' AND fname != '') ".
		"AND username='' $specialty ORDER BY lname";
  $rlist= sqlStatementNoLog($sql);
	if($allow_empty) {
  	echo "<option value=''";
  	if(!$thisField) echo " selected='selected'";
  	echo ">&nbsp;</option>";
	}
	if($special_title) {
  	echo "<option value='-1'";
  	if($thisField == -1) echo " selected='selected'";
  	echo ">$special_title</option>";
	}
  while ($rrow= sqlFetchArray($rlist)) {
    echo "<option value='" . $rrow['id'] . "'";
    if($thisField == $rrow['id']) echo " selected='selected'";
    echo ">" . $rrow['lname'].', '.$rrow['fname'].' '.$rrow['mname'];
		if($display_extra) {
			$keys = explode(',',$display_extra);
			foreach($keys as $extra) {
				$extra = trim($extra);
				if($extra) { echo ' - '.$rrow{$extra}; }
			}
		}
    echo "</option>";
  }
}
}

if(!function_exists('AddressSelect')) {
function AddressSelect($thisField, $org='', $spec='', $type='', $extra = '', $order='organization', $allow_empty=true, $title='', $local = false) {
	if($org) {
		if($org == '~~NULL~~') {
			$org_filter = "AND (organization = '' OR organization IS NULL) ";
		} elseif($org == '~~NOT NULL~~') {
			$org_filter = "AND (organization != '' OR organization IS NOT NULL) ".
				"AND (lname = '' OR lname IS NULL) ";
		} else {
			$org_filter = "AND UPPER(organization) LIKE UPPER('%$org%') ";
		}
	}
	if($spec) {
		$spec = "AND UPPER(specialty) LIKE UPPER('%$spec%') ";
	}
	if($type) {
		$type = "AND UPPER(abook_type) LIKE UPPER('%$type%') ";
	}
  $sql = "SELECT id, lname, fname, mname, organization, specialty, ".
		"street, streetb, city, state, zip, fax, phonew1, phonew2";
	if($extra) $sql .= ", $extra";
	$sql .= " FROM users WHERE active=1 AND username='' ";
	if($org_filter) $sql .= $org_filter;
	if($spec) $sql .= $spec;
	if($type) $sql .= $type;
	$sql .= "ORDER BY $order";
  $rlist= sqlStatementNoLog($sql);
	if($allow_empty) {
  	echo "<option value=''";
  	if(!$thisField) echo " selected='selected'";
  	echo ">&nbsp;</option>";
	}
	if($title) {
  	echo "<option value='-1'";
  	if($thisField == -1) echo " selected='selected'";
  	echo ">$title</option>";
	}
  while ($rrow= sqlFetchArray($rlist)) {
    echo "<option value='" . $rrow['id'] . "'";
    if($thisField == $rrow['id']) echo " selected='selected'";
		if($rrow['organization']) {
    	echo ">" . $rrow['organization'];
		} else {
    	echo ">" . $rrow['lname'].', '.$rrow['fname'].' '.$rrow['mname'];
		}
		if($extra) {
			$keys = explode(',',$extra);
			foreach($keys as $add) {
				$add= trim($add);
				if($add) echo ' - '.$rrow{$add};
			}
		}
    echo "</option>";
  }
}
}

if(!function_exists('IsDoctor')) {
function IsDoctor($thisUser = '') {
	if($thisUser == '') $thisUser = $_SESSION['authUser'];
  $doc= sqlQuery("SELECT id, specialty FROM users WHERE " .
      "username = ?", array($thisUser));
	if(stripos('provider',$doc{'specialty'}) !== false) return(1);
	if(stripos('supervisor',$doc{'specialty'}) !== false) return(1);
	return(0);
}
}

if(!function_exists('ProviderSelect')) {
function ProviderSelect($thisField) {
  $rlist= sqlStatementNoLog("SELECT * FROM users WHERE " .
      "active=1 AND username != '' AND (UPPER(specialty) LIKE '%PROVIDER%' ".
			"|| UPPER(specialty) LIKE '%SUPERVISOR%') ".
					"ORDER BY lname");
  echo "<option value=''";
  if(!$thisField) echo " selected='selected'";
  echo ">&nbsp;</option>";
  while ($rrow= sqlFetchArray($rlist)) {
    echo "<option value='" . $rrow['id'] . "'";
    if($thisField == $rrow['id']) echo " selected='selected'";
    echo ">" . $rrow['lname'].', '.$rrow['fname'].' '.$rrow['mname'];
    echo "</option>";
  }
}
}

if(!function_exists('SupervisorSelect')) {
function SupervisorSelect($thisField) {
  $rlist= sqlStatementNoLog("SELECT * FROM users WHERE active=1 AND ".
		"username != '' AND UPPER(specialty) LIKE '%SUPERVISOR%' ORDER BY lname");
  echo "<option value=''";
  if(!$thisField) echo " selected='selected'";
  echo ">&nbsp;</option>";
  while ($rrow= sqlFetchArray($rlist)) {
    echo "<option value='" . $rrow['id'] . "'";
    if($thisField == $rrow['id']) echo " selected='selected'";
    echo ">" . $rrow['lname'].', '.$rrow['fname'].' '.$rrow['mname'];
    echo "</option>";
  }
}
}

if(!function_exists('UserIdLook')) {
function UserIdLook($thisField) {
  if(!$thisField) return '';
  $ret = '';
  $frow= sqlQuery("SELECT * FROM users WHERE id=?",array($thisField));
  if($frow{'id'}) {
    $ret = $frow{'lname'}.', '.$frow{'fname'}.' '.$frow{'mname'};
  }
  return $ret;
}
}

if(!function_exists('UserNameFromID')) {
function UserNameFromID($thisField) {
  if(!$thisField) return '';
  $ret = '';
  $rlist= sqlStatementNoLog("SELECT * FROM users WHERE id=?",array($thisField));
  $rrow= sqlFetchArray($rlist);
  if($rrow) {
    $ret = $rrow{'lname'}.', '.$rrow{'fname'}.' '.$rrow{'mname'};
  }
  return $ret;
}
}

if(!function_exists('OrganizationFromID')) {
function OrganizationFromID($thisField) {
  if(!$thisField) return '';
  $ret = '';
  $rrow = sqlQuery("SELECT * FROM users WHERE id=?",array($thisField));
  $ret = $rrow{'organization'};
  return $ret;
}
}

if(!function_exists('UserNameFromName')) {
function UserNameFromName($thisField, $order='last') {
  if(!$thisField) return '';
  $ret = '';
  $rrow = sqlQuery("SELECT * FROM users WHERE username=?",array($thisField));
  $ret = $rrow{'lname'}.', '.$rrow{'fname'}.' '.$rrow{'mname'};
  if($order == 'first') {
		$ret = $rrow{'fname'}.' ';
		if($rrow{'mname'}) $ret .= $rrow{'mname'}.' ';
		$ret .= $rrow{'lname'};
	}
  return $ret;
}
}

if(!function_exists('UserDispNameFromID')) {
function UserDispNameFromID($thisField) {
  if(!$thisField) return '';
  $ret = '';
  $rlist= sqlStatementNoLog("SELECT * FROM users WHERE id=?", array($thisField));
  $rrow= sqlFetchArray($rlist);
  if($rrow) {
		$_mi = ' ';
		if(!empty($rrow{'mname'})) { $_mi = ' '.$rrow{'mname'}.' '; }
    $ret = $rrow{'fname'}.$_mi.$rrow{'lname'};
  }
  return $ret;
}
}

if(!function_exists('UserLook')) {
function UserLook($thisField) {
  if(!$thisField) return '';
  $ret = '';
  $rlist= sqlStatementNoLog("SELECT * FROM users WHERE username=?", array($thisField));
  $rrow= sqlFetchArray($rlist);
  if($rrow) {
    $ret = $rrow['lname'].', '.$rrow['fname'].' '.$rrow['mname'];
  }
  return $ret;
}
}

if(!function_exists('FacilityLook')) {
function FacilityLook($thisField) {
  if(!$thisField) return '';
  $flist= sqlQuery("SELECT name FROM facility WHERE id=?",array($thisField));
  return($flist{'name'});
}
}

if(!function_exists('ImmLook')) {
function ImmLook($thisField) {
  if(!$thisField) return '';
  $fres=sqlStatementNoLog("SELECT ct_id FROM code_types WHERE ct_key='CVX'");
  $frow=sqlFetchArray($fres);
	$type=$frow{'ct_id'};
	if($type == '' || !$type) return '';
  $frow=sqlQuery("SELECT code_text, code_text_short FROM ".
		"codes WHERE code_type=? AND code=?",array($type, $thisField));
  return($frow{'code_text_short'});
}
}

if(!function_exists('ImmDateCheck')) {
function ImmDateCheck($thisPid='', $thisCode) {
	if(!$thisPid) { return ''; }
	if(is_array($thisCode)) {
		if(!count($thisCode)) return '';
	} else {
  	if(!$thisField) return '';
	}
	if(is_array($thisCode)) {
		$binds = array($thisPid);
  	$sql = "SELECT administered_date, cvx_code FROM immunizations WHERE ".
			"patient_id=? AND (";
		$cnt = 1;
		foreach($thisCode as $code) {
			if($cnt > 1) { $sql .= " OR "; }
			$sql .= "cvx_code=?";	
			$binds[] = $code;
			$cnt++;
		}
		$sql .= ") ORDER BY administered_date DESC LIMIT 1";
	} else {
  	$sql = "SELECT administered_date, cvx_code FROM ".
			"immunizations WHERE patient_id=? AND cvx_code=? ORDER BY ".
			"administered_date DESC LIMIT 1";
		$binds = array($thisPid, $thisCode);
	}
	// echo "Immunization Statement: $sql<br>\n";
	// echo print_r($binds),"<br>\n";
	$fres=sqlStatementNoLog($sql, $binds);
  $frow=sqlFetchArray($fres);
  return($frow{'administered_date'});
}
}

if(!function_exists('AddVitalsTest')) {
function AddVitalsTest($vrec,$ht='',$wt='',$bps='',$bpd='',$bmi='',$bmi_status='',$hr='') {
  // echo "Height: ".$ht."<br />\n";
  // echo "Weight: ".$wt."<br />\n";
  // echo "Bps: ".$bps."<br />\n";
  // echo "Bpd: ".$bpd."<br />\n";
  // echo "BMI: ".$bmi."<br />\n";
  $add=false;
  // If there is no vitals record, and we captured data =>  ADD
  if($vrec == '' || $vrec == '0') { $add=true; }
	// echo "Vrec: $vrec    Add: $add<br>\n";
  $vitals_entered = ($ht > 0);
  // echo "Test 1 -Vitals [".$vitals_entered."]  and Height(".$ht.")<br />\n";
  if(!$vitals_entered) { $vitals_entered= ($wt > 0); }
  // echo "Test 2 -Vitals [".$vitals_entered."]  and Weight(".$wt.")<br />\n";
  if(!$vitals_entered) { $vitals_entered= ($bps > 0); }
  if(!$vitals_entered) { $vitals_entered= ($bpd > 0); }
  if(!$vitals_entered) { $vitals_entered= ($bmi > 0); }
  if(!$vitals_entered) { $vitals_entered= ($bmi_status != ''); }
  // echo "Test 1 - Add [".$add."]  and Entered (".$vitals_entered.")<br />\n";
  if($add && $vitals_entered) { return true; }
  $add=false;
  if($vrec != '' && $vrec != '0' && $vitals_entered) {
    // echo "Checking for Vitals Record<br/>\n";
    $fres=sqlStatementNoLog("SELECT * FROM form_vitals WHERE id=?", array($vrec));
    $vitals=sqlFetchArray($fres);
    if($vitals) {
      // echo "Setting Vitals<br/>\n";
      if($ht && $vitals{'height'} && ($ht != $vitals{'height'})) { $add=true; }
  		// echo "Test 2 - Add [".$add."] Ht: $ht (".$vitals{'height'}.")<br />\n";
      if($wt && $vitals{'weight'} && ($wt != $vitals{'weight'})) { $add=true; }
  		// echo "Test 2 - Add [".$add."] Wt: $ht (".$vitals{'weight'}.")<br />\n";
      if($bps && $vitals{'bps'} && ($bps != $vitals{'bps'})) { $add=true; }
  		// echo "Test 2 - Add [".$add."] BPS: $bps (".$vitals{'bps'}.")<br />\n";
      if($bpd && $vitals{'bpd'} && ($bpd != $vitals{'bpd'})) { $add=true; }
  		// echo "Test 2 - Add [".$add."] BPD: $bpd (".$vitals{'bpd'}.")<br />\n";
      if($bmi && $vitals{'BMI'} && ($bmi != $vitals{'BMI'})) { $add=true; }
  		// echo "Test 2 - Add [".$add."] BMI: $bmi (".$vitals{'BMI'}.")<br />\n";
      if($bmi_status != $vitals{'BMI_status'}) { $add=true; }
  		// echo "Test 2 - Add [".$add."] Stat: $bmi_status (".$vitals{'BMI_status'}.")<br />\n";
      if($hr && $vitals{'pulse'} && ($hr != $vitals{'pulse'})) { $add=true; }
  		// echo "Test 2 - Add [".$add."] HR: $hr (".$vitals{'pulse'}.")<br />\n";
    }
  }
	// echo "Sending Back [$add]<br>\n";
  return $add;
}
}

if(!function_exists('UpdateDashboard')) {
function UpdateDashboard($thisPid,$pap='',$mamm='',$bd='',$chol='',$colon='') {
  if(empty($thisPid)) { return false; }
  $update='';
  // echo "Add Dashboard Test, PID: ",$thisPid,"<br/>\n";
  // echo 'Updating dashboard entries....';
  $dates_entered = ($colon != 0 && $colon != '');
  if(!$dates_entered) { $dates_entered= ($bd != 0 && $bd != ''); }
  if(!$dates_entered) { $dates_entered= ($chol > 0 && $chol != ''); }
  if(!$dates_entered) { $dates_entered= ($pap > 0 && $pap != ''); }
  if(!$dates_entered) { $dates_entered= ($mamm > 0 && $mamm != ''); }
  if(!$dates_entered) { return true; }
  // echo '..';
  $fres=sqlStatementNoLog("SELECT * FROM form_dashboard WHERE pid=?",array($thisPid));
  // echo '..';
  $dash=sqlFetchArray($fres);
  // echo '..';
  if(!$dash{'id'}) {
    // echo "No dashboard was found..Creating";
		$binds=array($thisPid, $_SESSION['authUser'], $_SESSION['authProvider'],
				$_SESSION['userauthorized']);
    sqlStatement("INSERT INTO form_dashboard (date, pid, user, groupname, ".
				"authorized, activity) VALUES (NOW(), ?, ?, ?, ?, '1')", $binds);

  }
  if(!empty($colon) && $colon != $dash{'db_last_colon'}) { 
		$update.="db_last_colon='".$colon."', ";
  }
  if(!empty($bd) && $bd > $dash{'db_last_bone'}) {
		$update.="db_last_bone='".$bd."', ";
  }
  if(!empty($chol) && $chol > $dash{'db_last_chol'}) {
		$update.="db_last_chol='".$chol."', ";
  }
  if(!empty($pap) && $pap > $dash{'db_last_pap'}) {
		$update.="db_last_pap='".$pap."', ";
  }
  if(!empty($mamm) && $mamm > $dash{'db_last_mamm'}) {
		$update.="db_last_mamm='".$mamm."', ";
  }
	if(!empty($update)) {
		$form_dt=date('Y-m-d');
		$update.="db_form_dt='".$form_dt."'";
    $sql = "UPDATE form_dashboard SET date=NOW(), ".$update.
			", user='".$_SESSION['authUser']."', groupname='".
			$_SESSION['authProvider']."', authorized='".$_SESSION['userauthorized'].
			"', activity='1' WHERE pid='".$thisPid."'";
    // echo $sql."<br/>\n";
    sqlInsert($sql);
  }
  // echo '..';
  return true;
}
}

if(!function_exists('AppendItem')) {
function AppendItem($existing='',$new='', $comma=true, $sep='')
{
	if($new == '') return($existing);
	$existing=trim($existing);
	if(!empty($existing) && $comma) { 
		$existing .= ', ';
	} else if(!empty($existing) && $sep != '') {
		$existing .= $sep;
	} else if(!empty($existing)) {
		$existing .= ' ';
	}
	$existing .= $new;
	return($existing);
}
}

if(!function_exists('VerifyPatientID')) {
function VerifyPatientID($testID='')
{
  if(!$testID || $testID == 0 || $testID =='') {
		echo "<h>Session Corrupt...Could NOT Access Patient ID!</h><br/>\n";
		echo "<h>ID Value of '",$testID,"' Was Submitted</h><br/>\n";
		echo "Aborting!!<br/>\n";
		return false;
	}
	// Could do other checks here, like retrieve that row of patient data
	return true;
}
}

if(!function_exists('getPrescriptionsbyPatient')) {
function getPrescriptionsbyPatient ($thisPid, $cols = "*", $active='= 1')
{
  $sql = "SELECT $cols FROM prescriptions WHERE patient_id=? ".
				"AND active $active ORDER BY active DESC, date_added DESC";
	$all=array();
  $res = sqlStatementNoLog($sql,array($thisPid));
  for($iter =0;$row = sqlFetchArray($res);$iter++)
  $all[$iter] = $row;
  return $all;
}
}

if(!function_exists('getPrescriptionsByPatient')) {
function getPrescriptionsByPatient ($thisPid, $cols = "*", $active='= 1')
{
  $sql = "SELECT $cols FROM prescriptions WHERE patient_id=? ".
				"AND active $active ORDER BY active DESC, date_added DESC";
	$all=array();
  $res = sqlStatementNoLog($sql,array($thisPid));
  for($iter =0;$row = sqlFetchArray($res);$iter++)
  $all[$iter] = $row;
  return $all;
}
}

if(!function_exists('getLinkedPrescriptionsByPatient')) {
function getLinkedPrescriptionsByPatient($thisPid,$enc='',$active='=1')
{
	$all=array();
	if(!$enc) return $all;
  $sql = "SELECT form_wmt_ll.list_id, prescriptions.id, ".
			"prescriptions.date_added, prescriptions.drug, prescriptions.drug_id, ".
			"prescriptions.form, prescriptions.dosage, prescriptions.quantity, ".
			"prescriptions.size, prescriptions.unit, prescriptions.route, ".
			"prescriptions.interval, prescriptions.refills, prescriptions.note, ".
			"prescriptions.active, form_wmt_ll.list_id FROM form_wmt_ll ".
			"LEFT JOIN prescriptions ON ".
			"form_wmt_ll.list_id=prescriptions.id WHERE prescriptions.patient_id=? ".
			"AND form_wmt_ll.encounter_id=? AND form_wmt_ll.list_type=? ".
			"AND prescriptions.active $active ORDER BY date_added DESC";
	$binds=array($thisPid, $enc, 'prescriptions');
  $res = sqlStatementNoLog($sql,$binds);
  for($iter =0;$row = sqlFetchArray($res);$iter++)
	$all[] = $row;
  return $all;
}
}

if(!function_exists('getActiveRxByPatient')) {
function getActiveRxByPatient($thisPid,$enc='')
{
  $sql = "SELECT * FROM prescriptions ".
			"WHERE patient_id=? AND active=1 ORDER BY date_added DESC";
	$binds=array($thisPid);
	if($enc != '') {
  	$sql = "SELECT prescriptions.id, ".
			"prescriptions.date_added, prescriptions.drug, prescriptions.drug_id, ".
			"prescriptions.form, prescriptions.dosage, prescriptions.quantity, ".
			"prescriptions.size, prescriptions.unit, prescriptions.route, ".
			"prescriptions.interval, prescriptions.refills, prescriptions.note, ".
			"prescriptions.active, form_wmt_ll.list_id FROM prescriptions ".
			"LEFT JOIN form_wmt_ll ON ".
			"(prescriptions.id=form_wmt_ll.list_id AND form_wmt_ll.encounter_id=? ".
			"AND form_wmt_ll.list_type='prescriptions') ".
			"WHERE prescriptions.patient_id=? AND ".
			"prescriptions.active=1 ORDER BY date_added DESC";
		$binds=array($enc, $thisPid);
	}
	$all=array();
  $res = sqlStatementNoLog($sql,$binds);
  for($iter =0;$row = sqlFetchArray($res);$iter++)
	$all[] = $row;
  return $all;
}
}

if(!function_exists('getInactiveRxByPatient')) {
function getInactiveRxByPatient($thisPid,$enc='')
{
  $sql = "SELECT * FROM prescriptions ".
			"WHERE patient_id=? AND active<=0 ORDER BY date_added DESC";
	$binds=array($thisPid);
	if($enc != '') {
  	$sql = "SELECT form_wmt_ll.list_id, prescriptions.id, ".
			"prescriptions.start_date, ".
			"prescriptions.date_added, prescriptions.drug, prescriptions.drug_id, ".
			"prescriptions.form, prescriptions.dosage, prescriptions.quantity, ".
			"prescriptions.size, prescriptions.unit, prescriptions.route, ".
			"prescriptions.interval, prescriptions.refills, prescriptions.note, ".
			"prescriptions.active FROM prescriptions LEFT JOIN form_wmt_ll ON ".
			"(prescriptions.id=form_wmt_ll.list_id AND form_wmt_ll.encounter_id=? ".
			"AND form_wmt_ll.list_type='prescriptions') ".
			"WHERE prescriptions.patient_id=? AND ".
			"prescriptions.active<=0 ORDER BY date_added DESC";
		$binds=array($enc, $thisPid);
	}
	$all=array();
  $res = sqlStatementNoLog($sql,$binds);
  for($iter =0;$row = sqlFetchArray($res);$iter++)
	$all[] = $row;
  return $all;
}
}

if(!function_exists('getActivePrescriptionsbyPatient')) {
function getActivePrescriptionsbyPatient($thisPid,$enc='')
{
  $sql = "SELECT * FROM prescriptions WHERE patient_id=? ".
			"AND active=1 ORDER BY date_added DESC";
	$binds=array($thisPid);
	if($enc != '') {
  	$sql = "SELECT form_wmt_ll.list_id, prescriptions.id, ".
			"prescriptions.date_added, prescriptions.drug, prescriptions.drug_id, ".
			"prescriptions.form, prescriptions.dosage, prescriptions.quantity, ".
			"prescriptions.size, prescriptions.unit, prescriptions.route, ".
			"prescriptions.interval, prescriptions.refills, prescriptions.note, ".
			"prescriptions.active FROM form_wmt_ll LEFT JOIN prescriptions on ".
			"form_wmt_ll.list_id=prescriptions.id WHERE form_wmt_ll.pid=? AND ".
			"form_wmt_ll.encounter_id=? AND form_wmt_ll.list_type='prescriptions' ".
			"AND prescriptions.active=1 ORDER BY date_added DESC";
		$binds=array($thisPid, $enc);
	}
	// if($max) { $sql .= " LIMIT $max"; }
	$all=array();
  $res = sqlStatementNoLog($sql,$binds);
  for($iter =0;$row = sqlFetchArray($res);$iter++)
	$all[] = $row;
  return $all;
}
}

if(!function_exists('getInactivePrescriptionsbyPatient')) {
function getInactivePrescriptionsbyPatient($thisPid,$enc='')
{
  $sql = "SELECT * FROM prescriptions WHERE patient_id=? ".
			"AND active<=0 ORDER BY start_date DESC";
	$binds=array($thisPid);
	if($enc != '') {
  	$sql = "SELECT form_wmt_ll.list_id, prescriptions.id, ".
			"prescriptions.start_date, ".
			"prescriptions.date_added, prescriptions.drug, prescriptions.drug_id, ".
			"prescriptions.form, prescriptions.dosage, prescriptions.quantity, ".
			"prescriptions.size, prescriptions.unit, prescriptions.route, ".
			"prescriptions.interval, prescriptions.refills, prescriptions.note, ".
			"prescriptions.active FROM form_wmt_ll LEFT JOIN prescriptions on ".
			"form_wmt_ll.list_id=prescriptions.id WHERE form_wmt_ll.pid=? AND ".
			"form_wmt_ll.encounter_id=? AND form_wmt_ll.list_type='prescriptions' ".
			"AND prescriptions.active<=0 ORDER BY start_date DESC";
		$binds=array($thisPid, $enc);
	}
	// if($max) { $sql.= " LIMIT $max"; }
	$all=array();
  $res = sqlStatementNoLog($sql,$binds);
  for($iter =0;$row = sqlFetchArray($res);$iter++)
	$all[] = $row;
  return $all;
}
}

if(!function_exists('getAllPrescriptionsbyPatient')) {
function getAllPrescriptionsbyPatient ($thisPid,$cols="*",$active="*",$enc='')
{
  $sql = "SELECT * FROM prescriptions WHERE patient_id=? ".
			"AND active=? ORDER BY date_added DESC";
	$binds=array($thisPid, $active);
	if($enc != '') {
  	$sql = "SELECT form_wmt_ll.list_id, prescriptions.id, ".
			"prescriptions.date_added, prescriptions.drug, prescriptions.drug_id, ".
			"prescriptions.form, prescriptions.dosage, prescriptions.quantity, ".
			"prescriptions.size, prescriptions.unit, prescriptions.route, ".
			"prescriptions.interval, prescriptions.refills, prescriptions.note, ".
			"prescriptions.active FROM form_wmt_ll LEFT JOIN prescriptions on ".
			"form_wmt_ll.list_id=prescriptions.id WHERE form_wmt_ll.pid=? AND ".
			"form_wmt_ll.encounter_id=? AND form_wmt_ll.list_type='prescriptions' ".
			"AND prescriptions.active=? ORDER BY date_added DESC";
		$binds=array($thisPid, $enc, $active);
	}
	$all=array();
  $res = sqlStatementNoLog($sql,$binds);
  for($iter =0;$row = sqlFetchArray($res);$iter++)
	$all[] = $row;
  return $all;
}
}

if(!function_exists('getFormsByType')) {
function getFormsByType($type='pop_form', $type2='', $type3='')
{
  $sql = "SELECT wmt_pop_forms.*, registry.name, registry.nickname ".
				"FROM wmt_pop_forms ".
				"LEFT JOIN registry on wmt_pop_forms.form_name=registry.directory ".
				"WHERE $type=1 ";
	if($type2 != '') { $sql .= "OR $type2=1 "; }
	if($type3 != '') { $sql .= "OR $type3=1 "; }
	$sql .= "ORDER BY ".
				"IF((registry.nickname IS NOT NULL AND registry.nickname != ''),".
				"registry.nickname,registry.name)";
	$all=array();
	// echo "My Form Query: $sql<br>\n";
  $res = sqlStatementNoLog($sql);
  for($iter =0;$row = sqlFetchArray($res);$iter++) {
  	$row{'full_name'} = 'form_'.$row{'form_name'};
  	$all[$iter] = $row;
	}
  return $all;
}
}

if(!function_exists('AddMedication')) {
function AddMedication($thisPid,$sdate=NULL,$title='',$edate=NULL,$dest='',$comm='',$src=1,$type='medication',$xtra='',$ref='')
{
	$test=false;
	if($sdate == 'YYYY-MM-DD') $sdate='';
	if($edate == 'YYYY-MM-DD') $edate='';
	if(strlen($sdate) == 4) $sdate .= '-00-00';
	if(strlen($edate) == 4) $edate .= '-00-00';
	if($sdate == '' || !$sdate) $sdate = NULL;
	if($edate == '' || !$edate) $edate = NULL;
  if(!VerifyPatientID($thisPid)) return $test;
	if(!empty($sdate) || !empty($title) || !empty($comm)) {
  	$sql="INSERT INTO lists (date, pid, type, title, activity, ".
			"user, groupname, begdate, enddate, comments, destination, ".
			"extrainfo, referredby, classification) ".
			"VALUES (NOW(), ?, ?, ?, '1', ?, ?, ?, ?, ?, ?, ?, ?, ?)";
		// echo $sql,"<br/>\n";
		$binds=array($thisPid, $type, $title, $_SESSION['authUser'], 
				$_SESSION['authProvider'], $sdate, $edate, $comm, $dest, 
				$xtra, $ref, $src);
  	$test=sqlInsert($sql,$binds);
		setListTouch($thisPid, $type);
	}
	return $test;
}
}

if(!function_exists('UpdateMedication')) {
function UpdateMedication($thisPid,$item='',$sdate=NULL,$title='',$edate=NULL,$dest='',$comm='',$src=1,$type='medication',$xtra='',$ref='')
{
	if(!$item) return false;
  if(!VerifyPatientID($thisPid)) return false;
	if($sdate == 'YYYY-MM-DD') $sdate='';
	if($edate == 'YYYY-MM-DD') $edate='';
	if(strlen($sdate) == 4) $sdate .= '-00-00';
	if(strlen($edate) == 4) $edate .= '-00-00';
	if($sdate == '' || !$sdate) $sdate = NULL;
	if($edate == '' || !$edate) $edate = NULL;
  $sql="UPDATE lists SET date=NOW(), user=?, groupname=?, begdate=?, ".
			"enddate=?, title=?, comments=?, destination=?, classification=?, ".
			"extrainfo=?, referredby=?  WHERE pid=? AND id=?";
	$binds=array($_SESSION['authUser'], $_SESSION['authProvider'], $sdate,
		$edate, $title, $comm, $dest, $src, $xtra, $ref, $thisPid, $item);
	sqlStatement($sql, $binds);
	setListTouch($thisPid, $type);
}
}

if(!function_exists('DeleteMedication')) {
function DeleteMedication($thisPid,$item='',$links=0,$type='medication')
{
  if(!VerifyPatientID($thisPid)) return false;
	if(!$item) return false;
 	sqlStatement("DELETE FROM lists WHERE pid=? AND id=? AND type=?",
		array($thisPid, $item, $type));
	setListTouch($thisPid, $type);
	if($links) {
		DeleteAllListEntries($thisPid, $item, $type);
	}
}
}

if(!function_exists('GetAllImmunizationsbyPatient')) {
function GetAllImmunizationsbyPatient ($thisPid, $enc='')
{
  if(!VerifyPatientID($thisPid)) { return false; }
  $sql = "SELECT * FROM immunizations WHERE patient_id=? ORDER BY ".
				"administered_date DESC";
	$binds=array($thisPid);
	if($enc != '') {
  	$sql = "SELECT form_wmt_ll.list_id, immunizations.id, ".
				"immunizations.administered_date, ".
				"immunizations.cvx_code, immunizations.manufacturer, ".
				"immunizations.lot_number, immunizations.note ".
				"FROM form_wmt_ll LEFT JOIN immunizations on ".
				"form_wmt_ll.list_id=immunizations.id WHERE form_wmt_ll.pid=? ".
				"AND form_wmt_ll.encounter_id=? AND form_wmt_ll.list_type=? ".
				"ORDER BY immunizations.administered_date DESC";
		$binds=array($thisPid, $enc, 'immunizations');
	}
	$all=array();
  $res = sqlStatementNoLog($sql,$binds);
  for($iter =0;$row = sqlFetchArray($res);$iter++)
  $all[$iter] = $row;
  return $all;
}
}

if(!function_exists('AddInjury')) {
function AddInjury($thisPid,$title='',$sdate='',$hosp='',$comm='',$src=1)
{
	$test=false;
	if($sdate == 'YYYY-MM-DD') $sdate='';
	if(strlen($sdate) == 4) $sdate .= '-00-00';
  if(!VerifyPatientID($thisPid)) return $test;
	if(!empty($sdate) || !empty($title) || !empty($comm) || !empty($hosp)) {
  	$sql="INSERT INTO lists (date, pid, type, title, activity, ".
			"user, groupname, begdate, enddate, comments, extrainfo, ".
			"classification) ".
			"VALUES (NOW(), ?, 'wmt_inj_history', ?, '1', ?, ?, ?, ?, ?, ?, ?)";
		// echo $sql,"<br/>\n";
		$binds=array($thisPid, $title, $_SESSION['authUser'], 
				$_SESSION['authProvider'], $sdate, $sdate, $comm, $hosp, $src);
  	$test=sqlInsert($sql,$binds);
		setListTouch($thisPid, 'wmt_inj_history');
	}
	return $test;
}
}

if(!function_exists('UpdateInjury')) {
function UpdateInjury($thisPid,$item='',$title='',$sdate='',$comm='',$hosp='',$src=1)
{
	if($sdate == 'YYYY-MM-DD') $sdate='';
	if(strlen($sdate) == 4) $sdate .= '-00-00';
  if(!VerifyPatientID($thisPid)) return false;
	if(!empty($sdate) || !empty($title) || !empty($comm) || !empty($refer)) {
  	$sql="UPDATE lists SET date=NOW(), begdate=?, title=?, comments=?, ".
				"extrainfo=?, classification=? WHERE pid=? AND id=?";
		$binds=array($sdate, $title, $comm, $hosp, $src, $thisPid, $item);
		sqlStatement($sql, $binds);
		setListTouch($thisPid, 'wmt_inj_history');
	}
}
}

if(!function_exists('DeleteInjury')) {
function DeleteInjury($thisPid,$item='',$links=0)
{
  if(!VerifyPatientID($thisPid)) return false;
 	sqlStatement("DELETE FROM lists WHERE pid=? AND id=? AND type=?",
		array($thisPid, $item, 'wmt_inj_history'));
	setListTouch($thisPid, 'wmt_inj_history');
	if($links) {
		DeleteAllListEntries($thisPid, $item, 'wmt_inj_history');
	}
}
}

if(!function_exists('AddSurgery')) {
function AddSurgery($thisPid,$sdate='',$title='',$comm='',$refer='',$hosp='',$src=1)
{
	$test=false;
	if($sdate == 'YYYY-MM-DD') { $sdate=''; }
	if(strlen($sdate) == 4) { $sdate .= '-00-00'; }
  if(!VerifyPatientID($thisPid)) { return $test; }
	if(!empty($sdate) || !empty($title) || !empty($comm) || !empty($refer)) {
  	$sql="INSERT INTO lists (date, pid, type, title, activity, ".
			"user, groupname, begdate, enddate, comments, referredby, extrainfo, ".
			"classification) ".
			"VALUES (NOW(), ?, 'surgery', ?, '1', ?, ?, ?, ?, ?, ?, ?, ?)";
		// echo $sql,"<br/>\n";
		$binds=array($thisPid, $title, $_SESSION['authUser'], 
				$_SESSION['authProvider'], $sdate, $sdate, $comm, $refer, $hosp, $src);
  	$test=sqlInsert($sql,$binds);
		setListTouch($thisPid, 'surgery');
	}
	return $test;
}
}

if(!function_exists('UpdateSurgery')) {
function UpdateSurgery($thisPid,$item='',$sdate='',$title='',$comm='',$refer='',$hosp='',$src=1)
{
	if($sdate == 'YYYY-MM-DD') { $sdate=''; }
	if(strlen($sdate) == 4) { $sdate .= '-00-00'; }
  if(!VerifyPatientID($thisPid)) { return false; }
	if(!empty($sdate) || !empty($title) || !empty($comm) || !empty($refer)) {
  	$sql="UPDATE lists SET date=NOW(), begdate=?, title=?, comments=?, ".
				"referredby=?, extrainfo=?, classification=? WHERE pid=? AND id=?";
		$binds=array($sdate, $title, $comm, $refer, $hosp, $src, $thisPid, $item);
		sqlStatement($sql, $binds);
		setListTouch($thisPid, 'surgery');
	}
}
}

if(!function_exists('DeleteSurgery')) {
function DeleteSurgery($thisPid,$item='',$links=0)
{
  if(!VerifyPatientID($thisPid)) { return false; }
 	sqlStatement("DELETE FROM lists WHERE pid=? AND id=? AND type=?",
		array($thisPid, $item, 'surgery'));
	setListTouch($thisPid, 'surgery');
	if($links) {
		DeleteAllListEntries($thisPid, $item, 'surgery');
	}
}
}

if(!function_exists('AddHospitalization')) {
function AddHospitalization($thisPid,$sdate='',$why='',$type='',$note='')
{
	$test=false;
	if($sdate == 'YYYY-MM-DD') $sdate='';
	if(strlen($sdate) == 4) $sdate .= '-00-00';
  if(!VerifyPatientID($thisPid)) return $test;
	if(!empty($sdate) || !empty($why) || !empty($note) || !empty($type)) {
		$binds=array($thisPid, $why, $_SESSION['authUser'], 
				$_SESSION['authProvider'], $sdate, $sdate, $note, $type);
  	$test=sqlInsert("INSERT INTO lists (date, pid, type, title, activity, ".
			"user, groupname, begdate, enddate, comments, extrainfo) VALUES ".
			"(NOW(), ?, 'hospitalization', ?, '1', ?, ?, ?, ?, ?, ?)", $binds);
		setListTouch($thisPid, 'hospitalization');
	}
	return $test;
}
}

if(!function_exists('UpdateHospitalization')) {
function UpdateHospitalization($thisPid,$item='',$sdate='',$why='',$type='',$note='')
{
	if($sdate == 'YYYY-MM-DD') { $sdate=''; }
	if(strlen($sdate) == 4) { $sdate .= '-00-00'; }
  if(!VerifyPatientID($thisPid)) { return false; }
	if(!empty($sdate) || !empty($note) || !empty($why)) {
		$binds= array($sdate, $why, $note, $type, $thisPid, $item);
  	sqlStatement("UPDATE lists SET date=NOW(), begdate=?, ".
			"title=?, comments=?, extrainfo=? WHERE pid=? AND id=?", $binds);
		setListTouch($thisPid, 'hospitalization');
	}
}
}

if(!function_exists('DeleteHospitalization')) {
function DeleteHospitalization($thisPid,$item='',$links=0)
{
  if(!VerifyPatientID($thisPid)) { return false; }
 	sqlStatement("DELETE FROM lists WHERE pid=? AND id=? AND ".
			"type='hospitalization'",array($thisPid, $item));
	setListTouch($thisPid, 'hospitalization');
	if($links) {
		DeleteAllListEntries($thisPid, $item, 'hospitalization');
	}
}
}

if(!function_exists('GetMedicalHistory')) {
function GetMedicalHistory($thisPid, $enc='') {
  $sql = "SELECT id, injury_type as pmh_type, comments as pmh_nt, ".
			"extrainfo, classification FROM lists ".
			"WHERE pid=? AND type='wmt_med_history' AND activity = 1 ORDER BY id";
	$binds=array($thisPid);
	if($enc != '') {
  	$sql = "SELECT form_wmt_ll.list_id, lists.id, injury_type as pmh_type, ".
				"comments as pmh_nt, extrainfo, classification FROM form_wmt_ll ".
				"LEFT JOIN lists on ".
				"form_wmt_ll.list_id=lists.id WHERE form_wmt_ll.pid=? ".
				"AND form_wmt_ll.encounter_id=? AND form_wmt_ll.list_type=? ".
				"ORDER BY lists.id";
		$binds=array($thisPid, $enc, 'wmt_med_history');
	}
	$all=array();
  $res = sqlStatementNoLog($sql, $binds);
	$iter=0;
  while($row = sqlFetchArray($res)) {
		$all[$iter] = $row;
		$sql = "SELECT COUNT(*) FROM form_wmt_ll WHERE form_wmt_ll.list_type=? ".
				"AND form_wmt_ll.list_id=?";
		$chk = sqlStatementNoLog($sql, array('wmt_med_history', $all[$iter]['id']));
		$num = sqlFetchArray($chk);
		$all[$iter]['pmh_num_links']= $num['COUNT(*)'];
		$iter++;
	}
  return $all;
}
}

if(!function_exists('AddMedicalHistory')) {
function AddMedicalHistory($thisPid,$type='',$sub='',$note='',$hosp='',$src=1)
{
	$test=false;
  if(!VerifyPatientID($thisPid)) { return $test; }
	$type = trim($type);
	$note = trim($note);
	$hosp = trim($hosp);
	if($type || $sub || $note || $hosp) {
		// Auto-create a title for this issue
		// echo "Somehow we got into the add history section<br>\n";
		$title=ListLook($type,'Medical_History_Problems');
		if($title == '') { $title='No Type of History Specified'; }
		$binds=array($thisPid, $_SESSION['authUser'], $_SESSION['authProvider'],
			$type, $sub, $note, $title, $hosp, $src);
  	$test=sqlInsert("INSERT INTO lists (date, pid, user, groupname, activity, ".
			"type, enddate, injury_type, injury_part, comments, title, ".
			"extrainfo, classification) VALUES (NOW(), ?, ?, ?, '1', ".
			"'wmt_med_history', '0000-00-00', ?, ?, ?, ?, ?, ?)", $binds);
		setListTouch($thisPid, 'wmt_med_history');
	}
	return $test;
}
}

if(!function_exists('UpdateMedicalHistory')) {
function UpdateMedicalHistory($thisPid,$item='',$type='',$sub='',$note='',$hosp='',$src=1)
{
  if(!VerifyPatientID($thisPid)) { return false; }
	if(!empty($type) || !empty($sub) || !empty($note) || !empty($hosp)) {
		// Auto-create a title for this issue
		$title=ListLook($type,'Medical_History_Problems');
		if($title == '') { $title='No Type of History Specified'; }
		$binds=array($type, $sub, $note, $title, $hosp, $src, $thisPid, $item);
  	sqlStatement("UPDATE lists SET date=NOW(), injury_type=?, ".
				"injury_part=?, comments=?, title=?, extrainfo=?, classification=? ".
				" WHERE pid=? AND id=?",$binds);
		setListTouch($thisPid, 'wmt_med_history');
	}
}
}

if(!function_exists('DeleteMedicalHistory')) {
function DeleteMedicalHistory($thisPid,$item='',$links=0)
{
  if(!VerifyPatientID($thisPid)) { return false; }
 	sqlStatement("DELETE FROM lists WHERE pid=? AND id=?", 
			array($thisPid, $item));
	setListTouch($thisPid, 'wmt_med_history');
	if($links) {
		DeleteAllListEntries($thisPid, $item, 'wmt_med_history');
	}
}
}

if(!function_exists('AddSupplier')) {
function AddSupplier($thisPid,$name='',$type='',$note='')
{
	$test=false;
  if(!VerifyPatientID($thisPid)) return $test;
	if(!empty($name) || !empty($type) || !empty($note)) {
		// Auto-create a title for this issue
		$binds=array($thisPid, $_SESSION['authUser'], $_SESSION['authProvider'],
			$name, $type, $note);
  	$test=sqlInsert("INSERT INTO lists (date, pid, user, groupname, activity, ".
			"type, enddate, referredby, title, comments) ".
			"VALUES (NOW(), ?, ?, ?, '1', 'wmt_supplier', ".
			"'0000-00-00', ?, ?, ?)", $binds);
		setListTouch($thisPid, 'wmt_supplier');
	}
	return $test;
}
}

if(!function_exists('UpdateSupplier')) {
function UpdateSupplier($thisPid,$item='',$name='',$type='',$note='')
{
  if(!VerifyPatientID($thisPid)) { return false; }
	if(!empty($type) || !empty($name) || !empty($note)) {
		// Auto-create a title for this issue
		$binds=array($name, $type, $note, $thisPid, $item);
  	sqlStatement("UPDATE lists SET date=NOW(), referredby=?, ".
				"title=?, comments=? WHERE pid=? AND id=?",$binds);
		setListTouch($thisPid, 'wmt_supplier');
	}
}
}

if(!function_exists('DeleteSupplier')) {
function DeleteSupplier($thisPid,$item='',$links=0)
{
  if(!VerifyPatientID($thisPid)) { return false; }
 	sqlStatement("DELETE FROM lists WHERE pid=? AND id=?", 
			array($thisPid, $item));
	setListTouch($thisPid, 'wmt_supplier');
	if($links) {
		DeleteAllListEntries($thisPid, $item, 'wmt_supplier');
	}
}
}

if(!function_exists('GetImageHistory')) {
function GetImageHistory($thisPid,$enc='') {
  $sql = "SELECT id, injury_type as img_type, comments as img_nt, ".
		"begdate as img_dt, classification  FROM lists WHERE pid = ? AND ".
		"type = ? AND activity = 1 ORDER BY begdate";
	$binds=array($thisPid, 'wmt_img_history');
	// Create a new sql statement to only get those included on the linked list
	if($enc != '') {
  	$sql = "SELECT form_wmt_ll.list_id, lists.id, injury_type as img_type, ".
				"comments as img_nt, begdate as img_dt, classification FROM ".
				"form_wmt_ll LEFT JOIN ".
				"lists on form_wmt_ll.list_id=lists.id WHERE form_wmt_ll.pid=? ".
				"AND form_wmt_ll.encounter_id=? AND form_wmt_ll.list_type=? ".
				"ORDER BY begdate";
		$binds=array($thisPid, $enc, 'wmt_img_history');
	}
	$all=array();
  $res = sqlStatementNoLog($sql,$binds);
	$iter = 0;
  while($row = sqlFetchArray($res)) {
  	$all[$iter] = $row;
		$sql = "SELECT COUNT(*) FROM form_wmt_ll WHERE form_wmt_ll.list_type=? ".
				"AND form_wmt_ll.list_id=?";
		$chk = sqlStatementNoLog($sql, array('wmt_img_history', $all[$iter]['id']));
		$num = sqlFetchArray($chk);
		$all[$iter]['img_num_links']= $num['COUNT(*)'];
		$iter++;
	}
  return $all;
}
}

if(!function_exists('AddImageHistory')) {
function AddImageHistory($thisPid,$type='',$dt='',$note='', $src=1)
{
	$test = 0;
  if(!VerifyPatientID($thisPid)) return false;
	if(strlen($dt) == 4) $dt .= '-00-00';
	if(!empty($type) || !empty($dt) || !empty($note)) {
		// Auto-create a title for this issue
		$title = ListLook($type,'Image_Types');
		if($title == '') $title = 'No Type of Image Specified';
		if($dt == 0 || $dt == 'YYYY-MM-DD' || $dt == '') $dt = '0000-00-00';
  	$sql="INSERT INTO lists (date, pid, user, groupname, activity, begdate, ".
			"enddate, type, injury_type, comments, title, classification) ".
			"VALUES (NOW(), ?, ?, ?, '1', ?, ?, ?, ?, ?, ?, ?)";
		$test=sqlInsert($sql, array($thisPid, $_SESSION['authUser'], 
			$_SESSION['authProvider'],$dt,$dt,'wmt_img_history',$type,$note,
			$title, $src));
		setListTouch($thisPid, 'wmt_img_history');
		if(($dt != '0000-00-00') && checkSettingMode('wmt::auto_create_bd')) {
			if(!function_exists('AddBoneDensity')) 
							include($GLOBALS['srcdir'].'/wmt-v2/bd_track.inc');
			$irow = sqlQuery("SELECT * FROM list_options WHERE list_id=? ".
				"AND option_id=?", array('Image_types',$type));
			if(!isset($irow{'codes'})) $irow{'codes'} = '';
			if($irow{'codes'} == 'bone_density_link') {
				$dup = sqlQuery("SELECT * FROM lists WHERE type='bonedensity' AND ".
					"pid = ? AND begdate = ?", array($thisPid, $dt));	
				if(!isset($dup{'id'})) $dup{'id'} = '';
				$comm = 'Auto-Add From Image Link to Bone Density';
				if(!$dup{'id'}) AddBoneDensity($thisPid, $dt, $note, $comm, '');
			}
		}
	}
	return $test;
}
}

if(!function_exists('UpdateImageHistory')) {
function UpdateImageHistory($thisPid,$item='',$type='',$dt='',$note='',$src=1,$sub='')
{
	if(strlen($dt) == 4) { $dt .= '-00-00'; }
	if($dt == 0 || $dt == 'YYYY-MM-DD' || $dt == '0000-00-00') { $dt=''; }
  if(!VerifyPatientID($thisPid)) { return false; }
	if(!empty($type) || !empty($sub) || !empty($note)) {
		// Auto-create a title for this issue
		$title=ListLook($type,'Image_Types');
		if($title == '') { $title='No Type of Image Specified'; }
		$binds=array($type, $sub, $note, $title, $src, $thisPid, $item);
  	$sql="UPDATE lists SET date=NOW(), ";
		if(!empty($dt) && $dt != 0) { 
			$sql.="begdate=?, ";
			array_unshift($binds, $dt);
		}
		$sql.="injury_type=?, injury_part=?, comments=?, title=?, ".
				"classification=?  WHERE pid=? AND id=?";
		sqlStatement($sql,$binds);
		setListTouch($thisPid, 'wmt_img_history');
	}
}
}

if(!function_exists('DeleteImageHistory')) {
function DeleteImageHistory($thisPid,$item='',$links=0)
{
  if(!VerifyPatientID($thisPid)) { return false; }
 	sqlStatement("DELETE FROM lists WHERE pid=? AND id=?", 
			array($thisPid, $item));
	setListTouch($thisPid, 'wmt_img_history');
	if($links) {
		DeleteAllListEntries($thisPid, $item, 'wmt_img_history');
	}
}
}

if(!function_exists('LinkListEntry')) {
function LinkListEntry($thisPid, $item='', $enc='', $type='', $report=false, $skip=false)
{
	if($item == '' || $enc == '') return false;
  if(!VerifyPatientID($thisPid)) return false;
 	sqlStatement("INSERT INTO form_wmt_ll (encounter_id, ".
			"list_type, list_id, pid) VALUES (?, ?, ?, ?) ".
			"ON DUPLICATE KEY UPDATE list_id=?",
			array($enc, $type, $item, $thisPid, $item));
	if($report) {
		echo "Linked $type (ID: $item) to Encounter [$enc] for Patient ID: $thisPid<br/>\n";
	}
	// If this was for an entry in lists, also update the issue_encounter table
	if($type != 'prescriptions' && $type != 'immunizations' && $type != 'pap' &&
		$type != 'past_pregnancy' && !$skip) {
		$sql="INSERT INTO issue_encounter (pid, list_id, encounter, resolved) ".
				"VALUES (?, ?, ?, 0) ON DUPLICATE KEY UPDATE encounter=?";
		$binds=array($thisPid, $item, $enc, $enc);
		$link=sqlInsert($sql,$binds);
	}
}
}

if(!function_exists('UnlinkListEntry')) {
function UnlinkListEntry($thisPid, $item='',$enc='', $type='', $skip=false)
{
  if(!VerifyPatientID($thisPid)) return false;
	if($item == '' || $enc == '') return false;
 	sqlStatement("DELETE FROM form_wmt_ll WHERE pid=? AND encounter_id=? ".
		"AND list_id=? AND list_type=?", array($thisPid, $enc, $item, $type));
	// If this was for an entry in lists, also update the issue_encounter table
	if($type != 'prescriptions' && $type != 'immunizations' && $type != 'pap' &&
		$type != 'past_pregnancy' && !$skip) {
		$sql="DELETE FROM issue_encounter WHERE pid=? AND list_id=? AND ".
			"encounter=?";
		$binds=array($thisPid, $item, $enc);
		sqlStatement($sql,$binds);
	}
}
}

if(!function_exists('DeleteAllListEntries')) {
function DeleteAllListEntries($thisPid, $item='', $type='')
{
  if(!VerifyPatientID($thisPid)) return false;
	if($item == '' || $type == '') return false;
 	sqlStatement("DELETE FROM form_wmt_ll WHERE pid=? AND ".
		"list_id=? AND list_type=?", array($thisPid, $item, $type));
	// If this was for an entry in lists, also update the issue_encounter table
	if($type != 'prescriptions' && $type != 'immunizations' && $type != 'pap' &&
		$type != 'past_pregnancy') {
		$sql="DELETE FROM issue_encounter WHERE pid=? AND list_id=?";
		$binds=array($thisPid, $item);
		sqlStatement($sql,$binds);
	}
}
}

if(!function_exists('UnlinkTypeFromEncounter')) {
function UnlinkTypeFromEncounter($thisPid, $enc='', $type='')
{
  if(!VerifyPatientID($thisPid)) return false;
	if($enc== '' || $type == '') return false;
 	sqlStatement("DELETE FROM form_wmt_ll WHERE pid=? AND ".
		"encounter_id=? AND list_type=?", array($thisPid, $enc, $type));
	// If this was for an entry in lists, also update the issue_encounter table
	if($type != 'prescriptions' && $type != 'immunizations' && $type != 'pap' &&
		$type != 'past_pregnancy') {
		$sql="DELETE FROM issue_encounter WHERE pid=? AND encounter=?";
		$binds=array($thisPid, $enc);
		sqlStatement($sql,$binds);
	}
}
}

if(!function_exists('AddAllergy')) {
function AddAllergy($thisPid,$sdate='',$title='',$comm='',$react='',$occur='')
{
	$test=false;
	if($sdate == 'YYYY-MM-DD' || $sdate == '') $sdate=NULL;
  if(!VerifyPatientID($thisPid)) return false;
	if(!empty($sdate) || !empty($title) || !empty($comm) || 
			!empty($react) || !empty($occur)) {
		$binds=array($thisPid, $title, $_SESSION['authUser'], 
			$_SESSION['authProvider'], $sdate, $occur, $comm, $react);
  	$test = sqlInsert("INSERT INTO lists (date, pid, type, title, activity, ".
			"user, groupname, begdate, occurrence, comments, reaction) VALUES ".
			"(NOW(), ?, 'allergy', ?, '1', ?, ?, ?, ?, ?, ?)", $binds);
		setListTouch($thisPid, 'allergy');
	}
	return $test;
}
}

if(!function_exists('UpdateAllergy')) {
function UpdateAllergy($thisPid,$item='',$comm='',$sdate=NULL,$title='',$react='',$occur='')
{
  if(!VerifyPatientID($thisPid)) return false;
  if(!$item) return false;
	if($sdate == 'YYYY-MM-DD' || $sdate == '') $sdate=NULL;
	$add_allowed = checkSettingMode('wmt::db_allergy_add');
	if($add_allowed) {
  	sqlStatement("UPDATE lists SET date=NOW(), comments=?, begdate=?, ".
			"title=?, reaction=?, occurrence=? WHERE pid=? AND id=?", 
			array($comm, $sdate, $title, $react, $occur, $thisPid, $item));
	} else {
  	sqlStatement("UPDATE lists SET date=NOW(), comments=? ".
			"WHERE pid=? AND id=?", 
			array($comm, $thisPid, $item));
	}
	setListTouch($thisPid, 'allergy');
}
}

if(!function_exists('DeleteListItem')) {
function DeleteListItem($thisPid,$item='',$links=0,$type='')
{
  if(!VerifyPatientID($thisPid)) return false;
	if(!$item) return false;
 	sqlStatement("UPDATE lists SET activity=0 WHERE pid=? AND id=?",
		array($thisPid, $item));
	if($type) setListTouch($thisPid, $type);
	if($links) DeleteAllListEntries($thisPid, $item, $type);
}
}

if(!function_exists('UpdatePrescription')) {
function UpdatePrescription($thisPid,$item='',$note='')
{
  if(!VerifyPatientID($thisPid)) { return false; }
	if(!empty($note)) {
  	sqlStatement("UPDATE prescriptions SET date_modified=NOW(), note=? ".
			"WHERE patient_id=? AND id=?", array($note, $thisPid, $item));
		setListTouch($thisPid, 'prescription_erx');
	}
}
}

if(!function_exists('UpdateImmunization')) {
function UpdateImmunization($thisPid,$item='',$note='')
{
  if(!VerifyPatientID($thisPid)) { return false; }
	if(!empty($note)) {
		$binds=array($note, $_SESSION['authUserID'], $thisPid, $item);
  	sqlStatement("UPDATE immunizations SET update_date=NOW(), note=?, ".
			"updated_by=? WHERE patient_id=? AND id=?",$binds);
		setListTouch($thisPid, 'immunization');
	}
}
}

if(!function_exists('AddProblem')) {
function AddProblem($thisPid, $pdate='', $pdiag='')
{
 	if(!empty($pdiag) && $pdiag != 0) {
		$desc=lookup_code_descriptions('ICD9:'.$pdiag);
		$pdiag='ICD9:'.$pdiag;
   	$title=$pdiag.' - '.$desc;
   	$comment='Auto-Add From Encounter: '.$pdate.' - Extended Exam (1)';
		$binds=array($thisPid, $title, $_SESSION['authUser'], 
			$_SESSION['authProvider'], $pdate, $pdiag, $comment);
   	sqlInsert("INSERT INTO lists (date, pid, type, title, activity, user, ".
      	"groupname, begdate, occurrence, referredby, diagnosis, comments ) ".
				"VALUES (NOW(), ?, 'medical_problem',  ?, '1', ?, ?, ?, '', '', ?, ?)",
				$binds);
		setListTouch($thisPid, 'medical_problem');
 	}
}
}

if(!function_exists('AddFamilyHistory')) {
function AddFamilyHistory($thisPid,$who='',$type='',$note='',$dead='',$age='', $age_dead='',$src=1)
{
	$ret=false;
	if($age == 'YYYY-MM-DD') { $age=''; }
	if($age_dead == 'YYYY-MM-DD') { $age_dead=''; }
  if(!$thisPid || $thisPid == 0 || $thisPid=='') {
		echo "<h>Session Corrupt...Could NOT Access Patient ID!</h><br/>\n";
		echo "Aborting!!<br/>\n";
		exit;
	}
	if(!empty($who) && (!empty($type) || !empty($note))) { 
		// Auto-create a title for this issue
		$title='No Type of History';
		$tmp=ListLook($type,'Family_History_Problems');
		if($tmp != '') { $title=$tmp.' - '; }
		$tmp=ListLook($who,'Family_Relationships');
		if($tmp == '') { $tmp='No Family Member Specified'; }
		$title.=$tmp;
		
		$binds=array($thisPid, $_SESSION['authUser'], $_SESSION['authProvider'],
				$who, $dead, $age, $age_dead, $type, $note, $title, $src);
  	$ret=sqlInsert("INSERT INTO lists (date, type, pid, user, groupname, ".
     	"activity, begdate, enddate, injury_part, injury_grade, reaction, ".
			"destination, injury_type, comments, title, classification) VALUES ".
			"(NOW(), ".
			"'wmt_family_history', ?, ?, ?, '1', '0000-00-00', '0000-00-00', ?, ".
			"?, ?, ?, ?, ?, ?, ?)", $binds);
		setListTouch($thisPid, 'wmt_family_history');
	}
	if(!empty($who) && (!empty($dead) || !empty($age) || !empty($age_dead))) { 
		$sql="SELECT * FROM form_fh_misc WHERE pid=? AND fhm_who=?";
		$res=sqlStatementNoLog($sql,array($thisPid, $who));
		$row=sqlFetchArray($res);
		$binds=array();
		if(($row{'pid'} != '') && ($row{'fhm_who'} != '')) {
			$update=',';
			if(!empty($dead)) { 
				array_push($binds, $dead);
				$update.=" fhm_deceased=?,";
			}
			if(!empty($age)) { 
				array_push($binds, $age);
				$update.=" fhm_age=?,";
			}
			if(!empty($age_dead)) {
				array_push($binds, $age_dead);
				$update.=" fhm_age_dead=?,";
			}
			array_push($binds, $_SESSION['authUser'], $_SESSION['authProvider'], 
					$_SESSION['userauthorized'], $thisPid, $who);
    	$sql = "UPDATE form_fh_misc SET date=NOW() ".$update." user=?, ".
				"groupname=?, authorized=?, activity='1' WHERE pid=? AND fhm_who=?";
			sqlStatement($sql,$binds);
		} else {
			// echo "No result!<br/>\n";
			// echo $res,"<br/>\n";
			// echo "Making an entry for this person<br/>\n";
			$binds=array($thisPid, $_SESSION['authUser'], $_SESSION['authProvider'],
				$_SESSION['userauthorized'], $who, $dead, $age, $age_dead);
  		sqlInsert("INSERT INTO form_fh_misc (date, pid, user, groupname, ".
     		"authorized, activity, fhm_who, fhm_deceased, fhm_age, fhm_age_dead) ".
				"VALUES (NOW(), ?, ?, ?, ?, '1', ?, ?, ?, ?)",$binds);
		}
	}
	return $ret;
}
}

if(!function_exists('UpdateFamilyHistory')) {
function UpdateFamilyHistory($thisPid,$item='',$who='',$type='',$note='',$dead='',$age='',$age_dead='',$src=1)
{
  if(!VerifyPatientID($thisPid)) { return false; }
	if(!empty($who) && (!empty($type) || !empty($note))) { 
		// Auto-create a title for this issue
		$title='No Type of History';
		$tmp=ListLook($type,'Family_History_Problems');
		if($tmp != '') { $title=$tmp.' - '; }
		$tmp=ListLook($who,'Family_Relationships');
		if($tmp == '') { $tmp='No Family Member Specified'; }
		$title.=$tmp;
		$binds=array($_SESSION['authUser'], $_SESSION['authProvider'], $who,
					$dead, $age, $age_dead, $type, $note, $title, $src, $thisPid, $item);
    $sql = "UPDATE lists SET date=NOW(), user=?, groupname=?, ".
				"activity='1', injury_part=?, injury_grade=?, reaction=?, ".
				"destination=?, injury_type=?, comments=?, title=?, classification=? ".
				"WHERE pid=? AND id=?";
		sqlStatement($sql,$binds);
		setListTouch($thisPid, 'wmt_family_history');
	}

	if(!empty($who) && (!empty($dead) || !empty($age) || !empty($age_dead))) { 
		$sql="SELECT * FROM form_fh_misc WHERE pid=? AND fhm_who=?";
		$res=sqlStatementNoLog($sql, array($thisPid, $who));
		$row=sqlFetchArray($res);
		if(($row{'pid'} != '') && ($row{'fhm_who'} != '')) {
			$binds=array();
			$update=',';
			if(!empty($dead)) {
				array_push($binds, $dead);
				$update.=" fhm_deceased=?,";
			}
			if(!empty($age)) {
				array_push($binds, $age);
				$update.=" fhm_age=?,";
			}
			if(!empty($age_dead)) {
				array_push($binds, $age_dead);
				$update.=" fhm_age_dead=?,";
			}
			array_push($binds, $_SESSION['authUser'], $_SESSION['authProvider'],
				$_SESSION['userauthorized'], $thisPid, $who);
    	$sql = "UPDATE form_fh_misc SET date=NOW() ".$update." user=?, ".
				"groupname=?, authorized=?, activity='1' WHERE pid=? AND fhm_who=?";
			sqlStatement($sql,$binds);
		} else {
			$binds=array($thisPid, $_SESSION['authUser'], $_SESSION['authProvider'],
				$_SESSION['userauthorized'], $who, $dead, $age, $age_dead);
  		sqlInsert("INSERT INTO form_fh_misc (date, pid, user, groupname, ".
     		"authorized, activity, fhm_who, fhm_deceased, fhm_age, fhm_age_dead) ".
				"VALUES (NOW(), ?, ?, ?, ?, '1', ?, ?, ?, ?)", $binds);
		}
	}
}
}

if(!function_exists('DeleteFamilyHistory')) {
function DeleteFamilyHistory($thisPid,$item='',$links=0)
{
  if(!VerifyPatientID($thisPid)) { return false; }
 	sqlStatement("DELETE FROM lists WHERE pid=?  AND id=?", 
				array($thisPid, $item));
	setListTouch($thisPid, 'wmt_family_history');
	if($links) {
		DeleteAllListEntries($thisPid, $item, 'wmt_family_history');
	}
}
}

if(!function_exists('GetFamilyHistory')) {
function GetFamilyHistory($thisPid,$enc='') {
  $sql = "SELECT id, injury_part as fh_who, injury_grade as fh_deceased, ".
			"reaction as fh_age, destination as fh_age_dead, classification, ".
			"injury_type as fh_type, comments as fh_nt FROM lists ".
			"WHERE pid = ? AND activity = 1 AND type = 'wmt_family_history' ".
			"ORDER BY id";
	$binds=array($thisPid);
	if($enc != '') {
  	$sql = "SELECT form_wmt_ll.list_id, lists.id, injury_grade as ".
				"fh_deceased, reaction as fh_age, destination as fh_age_dead, ".
				"injury_part as fh_who, classification, ".
				"injury_type as fh_type, comments as fh_nt FROM form_wmt_ll ".
				"LEFT JOIN lists on form_wmt_ll.list_id=lists.id WHERE ".
				"form_wmt_ll.pid=? AND form_wmt_ll.encounter_id=? AND ".
				"form_wmt_ll.list_type=? ORDER BY lists.id";
		$binds=array($thisPid, $enc, 'wmt_family_history');
	}
	$all=array();
  $res = sqlStatementNoLog($sql,$binds);
	$iter=0;
  while($row = sqlFetchArray($res)) {
  	$all[$iter] = $row;
		$sql= "SELECT COUNT(*) FROM form_wmt_ll WHERE form_wmt_ll.list_type=? ".
				"AND form_wmt_ll.list_id=?";
		$chk= sqlStatementNoLog($sql, array('wmt_family_history', $all[$iter]['id']));
		$num= sqlFetchArray($chk);
		$all[$iter]['fh_num_links'] = $num['COUNT(*)'];
		$iter++;
	}
  return $all;
}
}

if(!function_exists('GetFamilyHistoryDefaults')) {
function GetFamilyHistoryDefaults($thisPid) {
	$all=array();
	$fres=sqlStatementNoLog("SELECT option_id, title FROM list_options WHERE ".
       "list_id='Family_Relationships' ORDER BY seq");
  while($frow = sqlFetchArray($fres)) {
  	$sql = "SELECT fhm_who, fhm_deceased, fhm_age, fhm_age_dead FROM ".
				"form_fh_misc WHERE pid=? AND fhm_who=?";
		// echo "Query: ",$sql,"<br/>\n";
  	$res = sqlStatementNoLog($sql, array($thisPid, $frow{'option_id'}));
		$row = sqlFetchArray($res);

  	$all[$frow{'option_id'}] = $row;
	}
  return $all;
}
}

if(!function_exists('AddDiagnosis')) {
function AddDiagnosis($thisPid,$enc=0,$type='',$diag='',$title='',$note='',$bdt='',$edt='',$seq='')
{
	if($bdt == 'YYYY-MM-DD') $bdt='';
	if($edt == 'YYYY-MM-DD') $edt='';
  if(!VerifyPatientID($thisPid)) return false;
	if($type == '') {
		if(isset($GLOBALS['wmt::default_diag_type'])) $type = $GLOBALS['wmt::default_diag_type'];
	}
	if(!empty($diag) || !empty($note) || !empty($title)) {
		// The start date must be set
		if(empty($bdt) || !$bdt) $bdt=date('Y-m-d');
		if(empty($title)) { 
			if(!empty($diag)) {
				$title=$diag.' - '.lookup_code_descriptions($type.':'.$diag);
			} else {
				$title='Problem added for visit on '.date('Y-m-d');
			}
		}
		if(!empty($type) && !empty($diag)) { $diag=$type.':'.$diag; }
  	$sql= "INSERT INTO lists (date, pid, user, groupname, type, title, ".
			"begdate, enddate, activity, diagnosis, comments) VALUES ".
			"(NOW(), ?, ?, ?, 'medical_problem', ?, ?, ?, '1', ?, ?)";
		$binds = array($thisPid, $_SESSION['authUser'], $_SESSION['authProvider'],
			$title, $bdt, $edt, $diag, $note);
		if(empty($edt) || $edt=='' || !$edt) {
  		$sql= "INSERT INTO lists (date, pid, user, groupname, type, title, ".
				"begdate, activity, diagnosis, comments) VALUES ".
				"(NOW(), ?, ?, ?, 'medical_problem', ?, ?, '1', ?, ?)";
			$binds = array($thisPid, $_SESSION['authUser'], $_SESSION['authProvider'],
				$title, $bdt, $diag, $note);
		}
		$test=sqlInsert($sql, $binds);
		setListTouch($thisPid, 'medical_problem');
		if(!$seq) $seq = GetDiagnosisSequence($thisPid, $enc);
		// Now add the link from this diagnosis to this encounter
		// echo "Diagnosis Insert returned ",$test,"<br/>\n";
		LinkDiagnosis($thisPid, $test, $enc, $seq);
	}
}
}

if(!function_exists('GetDiagnosisSequence')) {
function GetDiagnosisSequence($thisPid,$thisEnc='')
{
	if(!$thisEnc) return false;
	$sql="SELECT pid, seq FROM issue_encounter WHERE pid=? AND encounter=? ".
			"ORDER BY seq * 1 DESC, seq DESC";
	$frow = sqlQuery($sql, array($thisPid, $thisEnc));
	$val = 1;
	// Could make some alpha sensitive code here
	if($frow{'seq'}) $val = $frow{'seq'} + 1;
	return $val;
}
}

if(!function_exists('SequenceDiagnosis')) {
function SequenceDiagnosis($thisPid,$thisId='',$thisEnc='', $seq='')
{
	if(!$thisId) return false;
	if(!$thisEnc) return false;
	// echo "Past the value checks<br>\n";
	// $sql="INSERT INTO issue_encounter (pid, list_id, encounter, resolved, seq) ".
			// "VALUES (?, ?, ?, 0, ?) ON DUPLICATE KEY UPDATE seq=VALUES(seq)";
	$sql = "SELECT * FROM issue_encounter WHERE pid=? AND list_id=? ".
		"AND encounter=?";
	$frow = sqlQuery($sql, array($thisPid, $thisId, $thisEnc));
	// We only update this if the diag is already linked to the encounter
	if($frow{'list_id'} == $thisId) {
		$sql="UPDATE issue_encounter SET seq=? WHERE pid=? AND list_id=? ".
			"AND encounter=?";
		sqlStatement($sql, array($seq, $thisPid, $thisId, $thisEnc));
	}
}
}

if(!function_exists('LinkDiagnosis')) {
function LinkDiagnosis($thisPid,$thisId='',$thisEnc='', $seq='')
{
  if(!VerifyPatientID($thisPid)) return false;
	if(!$thisId) return false;
	if(!$thisEnc) return false;
	$sql="INSERT INTO issue_encounter (pid, list_id, encounter, resolved, seq) ".
			"VALUES (?, ?, ?, 0, ?) ON DUPLICATE KEY update encounter=?";
	$test=sqlInsert($sql, array($thisPid, $thisId, $thisEnc, $seq, $thisEnc));
}
}

if(!function_exists('UnlinkDiagnosis')) {
function UnlinkDiagnosis($thisPid,$thisId='',$thisEnc='')
{
  if(!VerifyPatientID($thisPid)) return false;
	if(!$thisId) return false;
	if(!$thisEnc) return false;
	$sql="DELETE FROM issue_encounter WHERE pid=? AND list_id=? AND encounter=?";
	// echo "My Patient: $thisPid  ID: $thisId and Enc: $thisEnc<br>\n";
	sqlStatement($sql, array($thisPid, $thisId, $thisEnc));
	// exit;
}
}

if(!function_exists('UpdateDiagnosis')) {
function UpdateDiagnosis($thisPid,$thisId='',$diag='',$title='',$note='',$bdt='',$edt='',$pre='',$suff='',$seq='',$thisEnc='')
{
	// echo "In the Diag Update, our complete code is [$diag]<br>\n";
	if($bdt == 'YYYY-MM-DD') $bdt = '';
	if($edt == 'YYYY-MM-DD') $edt = '';
  if(!VerifyPatientID($thisPid)) return false;
	if(!empty($diag) || !empty($note) || !empty($title)) {
		if(!empty($pre) && (substr($pre,-1) != ':')) $pre .= ':';
		if(empty($title)) { 
			if(!empty($diag)) {
				$title = $diag.' - '.lookup_code_descriptions($pre.$diag);
			} else {
				$title = 'Problem added for visit on '.date('Y-m-d');
			}
		}
		$diag = $pre.$diag.$suff;
		$binds = array($diag, $bdt, $title, $note, $thisPid, $thisId);
  	$sql = "UPDATE lists SET date=NOW(), diagnosis=?, begdate=?, ".
			"title=?, comments=? WHERE pid=? AND id=?";
		if(!empty($edt)) {
			$binds = array($diag, $bdt, $edt, $title, $note, $thisPid, $thisId);
  		$sql = "UPDATE lists SET date=NOW(), diagnosis=?, begdate=?, ".
				"enddate=?, title=?, comments=? WHERE pid=? AND id=?";
		}
  	sqlStatement($sql, $binds);
		setListTouch($thisPid, 'medical_problem');
	}
	// echo "Trying to update $thisId -> $thisEnc -> $seq<br>\n";
	SequenceDiagnosis($thisPid, $thisId, $thisEnc, $seq);
}
}

if(!function_exists('DeleteDiagnosis')) {
function DeleteDiagnosis($thisPid,$item='')
{
  if(!VerifyPatientID($thisPid)) { return false; }
 	sqlStatement("DELETE FROM lists WHERE pid=? AND id=?",
				array($thisPid, $item));
	setListTouch($thisPid, 'medical_problem');
	// echo "Test of Individual Delete: ",$test,"<br/>\n";
}
}

if(!function_exists('GetFormFromRepository')) {
function GetFormFromRepository($thisPid,$thisEnc,$thisId,$thisName)
{
	$_ret = 'Could Not Locate Archived Form'; 
  if(!VerifyPatientID($thisPid)) return false;
	$binds=array($thisPid, $thisId, $thisName, $thisEnc);
  $sql="SELECT id, form_id, content FROM form_repository WHERE pid=? ".
		"AND form_id=? AND form_name=? AND encounter=?";
  $fres = sqlQuery($sql, $binds);
	if(isset($fres{'content'})) $_ret=$fres{'content'};

	return $_ret;
}
}

if(!function_exists('FormInRepository')) {
function FormInRepository($thisPid,$thisEnc,$thisId,$thisName)
{
	$_ret = false;
  if(!VerifyPatientID($thisPid)) return false;
	if(!empty($thisEnc) && !empty($thisId) && !empty($thisName)) {
		$binds=array($thisPid, $thisId, $thisName, $thisEnc);
  	$sql="SELECT id, pid, content FROM form_repository WHERE pid=? ".
			"AND form_id=? AND form_name=? AND encounter=?";
  	$frow=sqlQuery($sql, $binds);
		if($frow{'id'} != '' && $frow{'id'} != 0) $_ret = $frow{'content'};
	}
	return $_ret;
}
}

if(!function_exists('AddFormToRepository')) {
function AddFormToRepository($thisPid,$thisEnc,$thisId,$thisName,$thisContent)
{
  if(!VerifyPatientID($thisPid)) return false;
	if(!empty($thisEnc) && !empty($thisId) && !empty($thisName)) {
		$binds=array($thisPid, $thisId, $thisName, $thisEnc, $thisContent);
  	$sql="INSERT INTO form_repository (pid, form_id, form_name, encounter, ".
			"content) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE content = ".
			"VALUES(content)";
  	$test=sqlInsert($sql, $binds);
	}
}
}

if(!function_exists('DeleteFromRepository')) {
function DeleteFromRepository($thisPid,$thisEnc,$thisId,$thisName)
{
  if(!VerifyPatientID($thisPid)) return false;
	if(!empty($thisEnc) && !empty($thisId) && !empty($thisName)) {
		$binds=array($thisPid, $thisId, $thisName, $thisEnc);
  	$sql="DELETE FROM form_repository WHERE pid=? AND form_id=? AND ".
				"form_name=? AND encounter=?";
  	sqlStatement($sql, $binds);
	}
}
}

if(!function_exists('AddPP')) {
function AddPP($thisPid,&$dt)
{
  if(!VerifyPatientID($thisPid)) { return false; }
  if (! is_array($dt)) { return false; }
  
  $sql= "INSERT INTO `form_whc_pp` SET `pid` = ?, `groupname` = ?, `user` = ?, `authorized` = ?, `activity` = ?, `date` = ? ";
  $binds = array($thisPid, $_SESSION['authProvider'], $_SESSION['authUser'], $_SESSION['userauthorized'], 1, date('Y-m-d H:i:s'));
  
  $fields = sqlListFields('form_whc_pp');
  foreach ($fields AS $field) {
	if ( strpos($field, 'pp_') === false ) continue;
	if ( $field == 'pp_source' ) $value = 1;
	else $value = $dt[$field];
	if ($value == 'YYYY-MM-DD' || $value == "_blank") $value = "";

	$sql .= ($sql)? ", `$field` = ? " : "`$field` = ? ";
	$binds[] = ($value == 'NULL')? "" : $value;
  }

  $ret=sqlInsert($sql, $binds);
	return $ret;
}
}

if(!function_exists('UpdatePP')) {
function UpdatePP($thisPid,$cnt,&$dt)
{
  if(!VerifyPatientID($thisPid)) { return false; }
  if(!$cnt) { return false; }
  if (! is_array($dt)) { return false; }
  
   	// Update an existing row
  $sql= "";
  $binds = array();
  
  $fields = sqlListFields('form_whc_pp');
  foreach ($fields AS $field) {
	if ( strpos($field, 'pp_') === false ) continue;
	if ( $field == 'pp_source' ) continue;
	
	$value = $dt[$field.'_'.$cnt];
	if ( $field == 'pp_baby_pid' && !$value ) continue;
	if ($value == 'YYYY-MM-DD' || $value == "_blank") $value = "";

	$sql .= ($sql)? ", `$field` = ? " : "`$field` = ? ";
	$binds[] = ($value == 'NULL')? "" : $value;
  }

  $sql .= "WHERE id = ?";
  $binds[] = $dt['pp_id_'.$cnt];
  
  sqlInsert("UPDATE `form_whc_pp` SET ".$sql, $binds);
}
}

if(!function_exists('AddOrUpdatePP')) {
function AddOrUpdatePP($thisPid, $ppid=0, &$dt)
{
  if ($ppid) {
   	// Update an existing row
   	UpdatePP($thisPid, $ppid, $dt);
  } else {
  	AddPP($thisPid, $dt);
  }
}
}

if(!function_exists('DeletePP')) {
function DeletePP($thisPid,$item='',$links=0)
{
  if(!VerifyPatientID($thisPid)) { return false; }
 	sqlStatement("DELETE FROM form_whc_pp WHERE pid=? AND id=?",
		array($thisPid, $item));
	if($links) {
		DeleteAllListEntries($thisPid, $item, 'past_pregnancy');
	}
}
}

if(!function_exists('SearchMultiArray')) {
function SearchMultiArray($needle, $haystack) {
	foreach($haystack as $key => $value) {
		$this_key = $key;
		if($needle === $value || 
				(is_array($value) && SearchMultiArray($needle, $value) !== false)) {
			return $this_key;
		}
	}
	return false;
}
}

if(!function_exists('FacilitySelect')) {
function FacilitySelect($thisField, $allow_empty=false, $empty_title='', $allow_all=false, $limit='1') {
  $rlist= sqlStatementNoLog("SELECT id, name FROM facility WHERE $limit ORDER BY name");
	if($allow_all) {
  	echo "<option value='~ALL~'";
  	if(!$thisField == '~ALL~') echo " selected='selected'";
  	echo ">-- All Facilities --</option>";
	}
	if($allow_empty) {
  	echo "<option value=''";
  	if(!$thisField) echo " selected='selected'";
  	echo ">$empty_title&nbsp;</option>";
	}
  while ($rrow= sqlFetchArray($rlist)) {
    echo "<option value='" . $rrow['id'] . "'";
    if($thisField == $rrow['id']) echo " selected='selected'";
    echo ">" . $rrow['name'];
    echo "</option>";
  }
}
}

if(!function_exists('GetDiagDescription')) {
function GetDiagDescription($codes) {
	$code_types = array();
	$default_search_type = '';
	$ctres = sqlStatementNoLog("SELECT * FROM code_types WHERE ct_active=1 ORDER BY ct_seq, ct_key");
	while ($ctrow = sqlFetchArray($ctres)) {
  	$code_types[$ctrow['ct_key']] = array(
    	'active' => $ctrow['ct_active'  ],
    	'id'   => $ctrow['ct_id'  ],
    	'fee'  => $ctrow['ct_fee' ],
    	'mod'  => $ctrow['ct_mod' ],
    	'just' => $ctrow['ct_just'],
    	'rel'  => $ctrow['ct_rel' ],
    	'nofs' => $ctrow['ct_nofs'],
    	'diag' => $ctrow['ct_diag'],
    	'mask' => $ctrow['ct_mask'],
    	'label'=> ( (empty($ctrow['ct_label'])) ? $ctrow['ct_key'] : $ctrow['ct_label'] ),
    	'external'=> $ctrow['ct_external'],
    	'claim' => $ctrow['ct_claim'],
    	'proc' => $ctrow['ct_proc'],
  	);
  	if ($default_search_type === '') $default_search_type = $ctrow['ct_key'];
	}

	/**
 	* This array stores the external table options. 
 	* 'external' attribute  for explanation of the option listings.
 	* @var array
 	*/
	$cd_external_options = array(
  	'0' => xl('No'),
  	'4' => xl('ICD9 Diagnosis'),
  	'5' => xl('ICD9 Procedure/Service'),
  	'1' => xl('ICD10 Diagnosis'),
  	'6' => xl('ICD10 Procedure/Service'),
  	'2' => xl('SNOMED (RF1) Diagnosis'),
  	'3' => xl('SNOMED (RF2) Diagnosis'),
	);

  $code_text = '';
  if (!empty($codes)) {
    $relcodes = explode(';', $codes);
    foreach ($relcodes as $codestring) {
      if ($codestring === '') continue;
      list($codetype, $code) = explode(':', $codestring);
      if ( !($code_types[$codetype]['external']) ) { // Collect from default codes table
        $wheretype = "";
        $sqlArray = array();
        if (empty($code)) {
          $code = $codetype;
        } else {
          $wheretype = "code_type = ? AND ";
          array_push($sqlArray,$code_types[$codetype]['id']);
        }
        $sql = "SELECT code_text FROM codes WHERE " .
          "$wheretype code = ? ORDER BY id LIMIT 1";
        array_push($sqlArray,$code);
        $crow = sqlQuery($sql,$sqlArray);
        if (!empty($crow['code_text'])) {
          if ($code_text) $code_text .= '; ';
          $code_text .= $crow['code_text'];
        }
      }
      else if ($code_types[$codetype]['external'] == 1) { // Collect from ICD10 Diagnosis codeset tables
        // Ensure the icd10_dx_order_code sql table exists
        $check_table = sqlQuery("SHOW TABLES LIKE 'icd10_dx_order_code'");
        if ( !(empty($check_table)) ) {
          if ( !(empty($code)) ) {
            // Will grab from previous inactive revisions if unable to find in current revision
            $sql = "SELECT `long_desc` FROM `icd10_dx_order_code` " .
                   "WHERE `formatted_dx_code` = ? ORDER BY `revision` DESC LIMIT 1";
            $crow = sqlQuery($sql, array($code) );
            if (!empty($crow['long_desc'])) {
              if ($code_text) $code_text .= '; ';
              $code_text .= $crow['long_desc'];
            }
          }
        }
      }
      else if ($code_types[$codetype]['external'] == 2) { // Collect from SNOMED (RF1) Diagnosis codeset tables
        // Ensure the sct_concepts sql table exists
        $check_table = sqlQuery("SHOW TABLES LIKE 'sct_concepts'");
        if ( !(empty($check_table)) ) {
          if ( !(empty($code)) ) {
            $sql = "SELECT `FullySpecifiedName` FROM `sct_concepts` " .
                   "WHERE `ConceptId` = ? AND `ConceptStatus` = 0 LIMIT 1";
            $crow = sqlQuery($sql, array($code) );
            if (!empty($crow['FullySpecifiedName'])) {
              if ($code_text) $code_text .= '; ';
              $code_text .= $crow['FullySpecifiedName'];
            }
          }
        }
      }
      else if ($code_types[$codetype]['external'] == 3) { // Collect from SNOMED (RF2) Diagnosis codeset tables
        //placeholder
      }
      else if ($code_types[$codetype]['external'] == 4) { // Collect from ICD9 Diagnosis codeset tables
        // Ensure the icd9_dx_code sql table exists
        $check_table = sqlQuery("SHOW TABLES LIKE 'icd9_dx_code'");
        if ( !(empty($check_table)) ) {
          if ( !(empty($code)) ) {
            // Will grab from previous inactive revisions if unable to find in current revision
            $sql = "SELECT `long_desc` FROM `icd9_dx_code` " .
                   "WHERE `formatted_dx_code` = ? ORDER BY `revision` DESC LIMIT 1";
            $crow = sqlQuery($sql, array($code) );
            if (!empty($crow['long_desc'])) {
              if ($code_text) $code_text .= '; ';
              $code_text .= $crow['long_desc'];
            }
          }
        }
      }
      else if ($code_types[$codetype]['external'] == 5) { // Collect from ICD9 Procedure/Service codeset tables
        // Ensure the icd9_dx_code sql table exists
        $check_table = sqlQuery("SHOW TABLES LIKE 'icd9_sg_code'");
        if ( !(empty($check_table)) ) {
          if ( !(empty($code)) ) {
            // Will grab from previous inactive revisions if unable to find in current revision
            $sql = "SELECT `long_desc` FROM `icd9_sg_code` " .
                   "WHERE `formatted_sg_code` = ? ORDER BY `revision` DESC LIMIT 1";
            $crow = sqlQuery($sql, array($code) );
            if (!empty($crow['long_desc'])) {
              if ($code_text) $code_text .= '; ';
              $code_text .= $crow['long_desc'];
            }
          }
        }
      }
      else if ($code_types[$codetype]['external'] == 6) { // Collect from ICD10 PRocedure/Service codeset tables
        // Ensure the icd10_dx_order_code sql table exists
        $check_table = sqlQuery("SHOW TABLES LIKE 'icd10_pcs_order_code'");
        if ( !(empty($check_table)) ) {
          if ( !(empty($code)) ) {
            // Will grab from previous inactive revisions if unable to find in current revision
            $sql = "SELECT `long_desc` FROM `icd10_pcs_order_code` " .
                   "WHERE `pcs_code` = ? ORDER BY `revision` DESC LIMIT 1";
            $crow = sqlQuery($sql, array($code) );
            if (!empty($crow['long_desc'])) {
              if ($code_text) $code_text .= '; ';
              $code_text .= $crow['long_desc'];
            }
          }
        }
      }

      else {
        //using an external code that is not yet supported, so skip. 
      }
    }
  }
  return $code_text;
}
}

if(!function_exists('LooseDateCheck')) {
function LooseDateCheck($thisDate) {
	if($thisDate === '000-00-00') return false;
	if($thisDate == '') return false;
	if(substr($thisDate,4,1) != '-') return false;
	return true;
}
}

if(!function_exists('getAppointments')) {
function getAppointments($thisPid, $dt='current') {
  if(!VerifyPatientID($thisPid)) { return false; }
	$query = "SELECT e.pc_eid, e.pc_aid, e.pc_title, e.pc_eventDate, ".
	  "e.pc_startTime, e.pc_hometext, u.fname, u.lname, u.mname, c.pc_catname ".
	  "FROM openemr_postcalendar_events AS e, users AS u, ".
	  "openemr_postcalendar_categories AS c WHERE ".
	  "e.pc_pid = ? AND e.pc_eventDate >= ";
	$query .= ($dt == 'current') ? 'CURRENT_DATE ' : '? ';
	$query .= "AND u.id = e.pc_aid AND e.pc_catid = c.pc_catid " .
	  "ORDER BY e.pc_eventDate, e.pc_startTime";
	// echo $query,"<br>\n";
	$binds = array($thisPid);
	if($dt == 'current') { $binds[] = $dt; }
	$res = sqlStatementNoLog($query, $binds);
	$all = array();
	$iter=0;
  while($row = sqlFetchArray($res)) {
		$all[$iter] = $row;
		$iter++;
	}
  return $all;
}
}

if(!function_exists('generateChapter')) {
function generateChapter($title='', $bar_id='', $toggle='block', $class='wmtCollapseBar', $chp_class='wmtChapter', $collapsible=true, $bottom_bar=false) {
/*	$display_field = 'tmp_'.$bar_id.'_disp_mode';
	echo "<div class='$class' id='".$bar_id;
	echo ($bottom_bar == 2) ? "B" : "";
	echo "Bar' style='border-";
	echo ($bottom_bar == 2) ? 'top: ' : 'bottom: ';
	echo ($toggle == 'block') ? 'solid 1px black' : 'none'; 
	echo "; border-collapse: collapse;' onclick='togglePanel(\"".$bar_id."Box\",\"".$bar_id."ImageL\",\"".$bar_id."ImageR\",\"".$bar_id."Bar\",\"\",\"$display_field\"";
	if($bottom_bar) {
		echo ",\"".$bar_id."ImageBL\",\"".$bar_id."ImageBR\",\"".$bar_id."BBar\"";
	}
	echo ")'>\n";
	echo "	<table width='100%' border='0' cellspacing='0' cellpadding='0'>\n";
	echo "		<tr>\n";
	$img_source = $GLOBALS['webroot'].'/library/wmt-v2/fill-270.png';
	$alt = 'Maximize';
	if($toggle == 'block') {
		$img_source = $GLOBALS['webroot'].'/library/wmt-v2/fill-090.png';
		$alt = 'Minimize';
	}
	echo "			<td class='wmtL'><img id='".$bar_id."Image";
	echo ($bottom_bar == 2) ? "B" : "";
	echo "L' src='$img_source' border='0' alt='$alt' title='$alt' /></td>\n";
 	echo "<td class='$chp_class'>$title</td>\n";
	echo "<td class='wmtR'><img id='".$bar_id."Image";
	echo ($bottom_bar == 2) ? "B" : "";
	echo "R' src='$img_source' border='0' alt='$alt' title='$alt' /></td>\n";
	echo "		</tr>\n";
	echo "	</table>\n";
	echo "</div>\n";
*/			
			// NEW VERSION 
			$arrow = ($toggle == 'none')? 'fill-270.png' : 'fill-090.png'; ?>
			<div class="<?php echo $class ?> wmtColorBar <?php if ($toggle == 'none') echo 'wmtBarClosed' ?> <?php if (!$collapsible) echo 'wmtNoCollapse'?>" id="<?php echo $bar_id ?><?php if ($bottom_bar) echo 'Bottom' ?>Bar" style="text-align:center">
				<?php if ($collapsible) { ?><img class="wmtCollapseArrow" id="<?php echo $bar_id ?>ImageL" src="<?php echo $GLOBALS['webroot'];?>/library/wmt/<?php echo ($arrow) ?>" title="Show/Hide" style="float:left" /><?php } ?>
				<span class="wmtCollapseTitle"><?php echo $title ?></span>
				<?php if ($collapsible) { ?><img class="wmtCollapseArrow" id="<?php echo $bar_id ?>ImageR" src="<?php echo $GLOBALS['webroot'];?>/library/wmt/<?php echo ($arrow) ?>" title="Show/Hide"  style="float:right"/><?php } ?>
			</div>
<?php 
}
}

if(!function_exists('LoadActiveProcedures')) {
function LoadActiveProcedures($thisList,$thisForm='') {
	$all = array();
	$iter = 0;
	if($thisForm) {
  	$rlist= sqlStatementNoLog("SELECT * FROM list_options WHERE list_id=? AND ".
			"(UPPER(notes) NOT LIKE '%DO NOT USE%') AND seq >= 0 ORDER BY seq",
			array($thisList.'::'.$thisForm));
  	while($row = sqlFetchArray($rlist)) {
  		$all[$iter] = $row;
			$iter++;
		}
	}
	if($iter) return $all;
  $rlist= sqlStatementNoLog("SELECT * FROM list_options WHERE list_id=? AND ".
		"(UPPER(notes) NOT LIKE '%DO NOT USE%') AND seq >= 0 ORDER BY seq",
		array($thisList));
  while($row = sqlFetchArray($rlist)) {
  	$all[$iter] = $row;
		$iter++;
	}
	return $all;
}
}

if(!function_exists('isSnippetModule')) {
function isSnippetModule($module) {
	$sql = 'SELECT `title` FROM `list_options` WHERE `list_id` = ' .
	'"Templated_Fields" AND `activity` = 1 AND `option_id` = ?';
	$res = sqlQuery($sql,  array($module));
	if(!isset($res{'title'})) $res{'title'} = '';
	return $res{'title'};
}
}

if(!function_exists('getKeyedText')) {
function getKeyedText($key='', $user=0) {
	if($key == '') return '';
  $text = sqlQuery("SELECT * FROM wmt_keyed_text WHERE key_val=? AND user_id=?",
		array($key, $user));
	if(!isset($text{'note'})) $text{'note'} = '';
	if($text{'note'}) return($text{'note'});
  $text = sqlQuery("SELECT * FROM wmt_keyed_text WHERE key_val=? AND user_id=?",
		array($key, 0));
	if(!isset($text{'note'})) $text{'note'} = '';
	return($text{'note'});
}
}

if(!function_exists('GenerateHiddenInput')) {
function GenerateHiddenInput($id, $chc='') {
	$chc = trim($chc);
	echo "<input name='$id' id='$id' tabindex='-1' type='hidden' value='".htmlspecialchars($chc, ENT_QUOTES, '', FALSE)."' />\n";
}
}


?>