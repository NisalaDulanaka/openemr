<?php

include_once("../../globals.php");
include_once("$srcdir/OemrAD/oemrad.globals.php");

use OpenEMR\Core\Header;
use OpenEMR\OemrAd\FaxMessage;
use OpenEMR\OemrAd\PostalLetter;

if(!isset($_REQUEST['pid'])) $_REQUEST['pid'] = '';
if(!isset($_REQUEST['pagetype'])) $_REQUEST['pagetype'] = '';

$pid = strip_tags($_REQUEST['pid']);
$pagetype = $_REQUEST['pagetype'];
$pageTypeStr = !empty($pagetype) ? '&pagetype='.$pagetype : '';

if(isset($_REQUEST['ajax'])) {
	$aColumns = explode(',', $_REQUEST['sColumns']);

	// Paging parameters.  -1 means not applicable.
	//
	$iDisplayStart  = isset($_REQUEST['iDisplayStart' ]) ? 0 + $_REQUEST['iDisplayStart' ] : -1;
	$iDisplayLength = isset($_REQUEST['iDisplayLength']) ? 0 + $_REQUEST['iDisplayLength'] : -1;
	$limit = '';
	if ($iDisplayStart >= 0 && $iDisplayLength >= 0) {
	    $limit = "LIMIT " . escape_limit($iDisplayStart) . ", " . escape_limit($iDisplayLength);
    }

    // Column sorting parameters.
	//
	$orderby = '';
	if (isset($_REQUEST['iSortCol_0'])) {
	    for ($i = 0; $i < intval($_REQUEST['iSortingCols']); ++$i) {
	        $iSortCol = intval($_REQUEST["iSortCol_$i"]);
	        if ($_REQUEST["bSortable_$iSortCol"] == "true") {
	            $sSortDir = escape_sort_order($_REQUEST["sSortDir_$i"]); // ASC or DESC
	      		// We are to sort on column # $iSortCol in direction $sSortDir.
	            $orderby .= $orderby ? ', ' : 'ORDER BY ';
	      		//
	            $orderby .= "`" . escape_sql_column_name($aColumns[$iSortCol], array('facility')) . "` $sSortDir";
	        }
	    }
    }
    
    // Global filtering.
	//
	$tmp_where = "WHERE u.active = 1 AND ( u.authorized = 1 OR u.username = '' ) AND ";
	$where = "";
	if (isset($_GET['sSearch']) && $_GET['sSearch'] !== "") {
	    $sSearch = add_escape_custom(trim($_GET['sSearch']));
	    foreach ($aColumns as $colname) {
	        $where .= $where ? "OR " : $tmp_where. " ( ";
	        $where .= "`" . escape_sql_column_name($colname, array('facility')) . "` LIKE '$sSearch%' ";
	    }

	    if ($where) {
	        $where .= ")";
	    }
    }
    
    // Column-specific filtering.
	//
	for ($i = 0; $i < count($aColumns); ++$i) {
	    $colname = $aColumns[$i];
	    if (isset($_GET["bSearchable_$i"]) && $_GET["bSearchable_$i"] == "true" && $_GET["sSearch_$i"] != '') {
	        $where .= $where ? ' AND' : $tmp_where;
	        $sSearch = add_escape_custom($_GET["sSearch_$i"]);
	        $where .= " `" . escape_sql_column_name($colname, array('facility')) . "` LIKE '$sSearch%'";
	    }
    }
    
    // Get total number of rows in the table.
	//
	$iTotalsqlQtr = "SELECT COUNT(f.id) AS count FROM facility AS f ";
    $row = sqlQuery($iTotalsqlQtr);
    $iTotal = $row['count'];

    // Get total number of rows in the table after filtering.
	//
	$iFilteredTotalsqlQtr = "SELECT COUNT(f.id) AS count FROM facility AS f";
    $row = sqlQuery($iFilteredTotalsqlQtr . $where);
    $iFilteredTotal = $row['count'];
    
    $out = array(
        "sEcho"                => intval($_GET['sEcho']),
        "iTotalRecords"        => $iTotal,
        "iTotalDisplayRecords" => $iFilteredTotal,
        "aaData"               => array()
    );

    $sellist = "f.id, f.name, f.street, f.city, f.state, f.postal_code, f.country_code, f.phone";
	$query = "SELECT $sellist FROM facility AS f $where $orderby $limit";
    $res = sqlStatement($query);
    
    while ($row = sqlFetchArray($res)) {
		$lastStr = '';
		$nameStr = '';
		if($pagetype == "postal_letter") {
			$pl = PostalLetter::generatePostalAddress(array(
				'name' => $row['name'],
				'street' => $row['street'],
				'city' => $row['city'],
				'state' => $row['state'],
                'postal_code' => $row['postal_code'],
                'country' => $row['country']
			), "\n");
			$lastStr = base64_encode(json_encode($pl));
			$nameStr = $row['name'];
		} else {
			$lastStr = '';
			$nameStr = $row['name'];
		}
		$arow = array('DT_RowId' => $row['id'].'~'.$nameStr.'~'.$lastStr);

		foreach ($aColumns as $colname) {
	        $arow[] = isset($row[$colname]) ? $row[$colname] : '';
	    }

	    $out['aaData'][] = $arow;
	}

	echo json_encode($out, 15);
} else {
?>
<html>
<head>
	<title><?php echo htmlspecialchars( xl('Facility Finder'), ENT_NOQUOTES); ?></title>
	<link rel="stylesheet" href='<?php echo $css_header ?>' type='text/css'>
	<?php Header::setupHeader(['opener', 'dialog', 'jquery', 'jquery-ui', 'datatables', 'datatables-colreorder', 'datatables-bs', 'fontawesome', 'oemr_ad']); ?>

	<link rel="stylesheet" href="//gyrocode.github.io/jquery-datatables-checkboxes/1.2.12/css/dataTables.checkboxes.css">
	<script type="text/javascript" src="//gyrocode.github.io/jquery-datatables-checkboxes/1.2.12/js/dataTables.checkboxes.min.js"></script>

    <style type="text/css">
		/*table#facilityDataTable thead th, table#facilityDataTable thead td {
			border-bottom: 0px solid black;
		}
		table#facilityDataTable thead th, table#facilityDataTable thead td,
		table#facilityDataTable tr th, table#facilityDataTable tr td {
			padding: 4px!important;
		}

		table#facilityDataTable tr td {
			border-top: 1px solid black;
			vertical-align: text-top;
		}
		table#facilityDataTable tr:hover td {
			background-color: #bbb!important;
			cursor: pointer;
		}

		.sectionTitle {
			padding: 0px 10px;
			margin-top: 20px;
			margin-bottom: 10px;
		}
		.dataTables_wrapper .dataTables_paginate .paginate_button {
			padding: 5px 10px!important;
		    font-size: 12px!important;
		    line-height: 1.5!important;
		    border-radius: 3px!important;
		    box-shadow: none!important;
		}
		.dataTables_wrapper .dataTables_paginate .paginate_button.current{
			background: #2672ec!important;
			color: #FFF!important;
		}
		#facilityDataTable_wrapper {
			margin-top: 20px;
		}
		#facilityDataTable_wrapper {
			
		}
		#facilityDataTable_wrapper input{
			padding: 5px 12px;
		    font-size: 14px;
		    line-height: 1.42857143;
		    color: #555;
		    background-color: #fff;
		    background-image: none;
		    border: 1px solid #444444;
		    border-radius: 4px;
		}
		#facilityDataTable_filter {
			margin-right: 10px;
			margin-bottom: 20px;
		}*/
		.disclaimersContainer {
			font-size: 14px;
			padding: 15px;
		}
	</style>
    <style type="text/css">
    	.facilityDataTable {
    		width: 100%!important;
    	}
    </style>
    <script language="JavaScript">

	 function selreplyaddress(id, name, data) {
		if (opener.closed || ! opener.setFacility)
		alert("<?php echo htmlspecialchars( xl('The destination form was closed; I cannot act on your selection.'), ENT_QUOTES); ?>");
		else
		opener.setFacility(id, name, data);
		window.close();
		return false;
	 }

	</script>
</head>
<body>
	<div class="disclaimersContainer">
		<b>Disclaimer:</b> <span>Result will contain only active entries</span>
	</div>
	<div class="table-responsive table-container">
		<table id='facilityDataTable' class='table table-sm addressDataTable'>
		  <thead class="thead-dark">
		    <tr>
		      <th>Name</th>
			  <th>Street</th>
			  <th>City</th>
	          <th>State</th>
			  <th>Zip</th>
	          <th>Country</th>
		      <th>Phone</th>
		    </tr>
		  </thead>
		</table>
	</div>
	<script type="text/javascript">
		$(document).ready(function(){
		   $('#facilityDataTable').DataTable({
		      'processing': true,
		      'serverSide': true,
		      'pageLength': 8,
		      'bLengthChange': false,
		      'sAjaxSource': '<?php echo $GLOBALS['webroot']."/library/OemrAD/interface/main/messages/find_facilities_popup.php?pid=". $pid; ?>&ajax=1<?php echo $pageTypeStr; ?>',
		      'columns': [
		         { sName: 'name' },
		         { sName: 'street' },
				 { sName: 'city' },
				 { sName: 'state' },
				 { sName: 'postal_code' },
		         { sName: 'country_code' },
		         { sName: 'phone' }
		      ],
		      <?php // Bring in the translations ?>
    			<?php $translationsDatatablesOverride = array('search'=>(xla('Search all columns') . ':')) ; ?>
    		 <?php require($GLOBALS['srcdir'] . '/js/xl/datatables-net.js.php'); ?>
		   });

		    $("#facilityDataTable").on('click', 'tbody > tr', function() { SelectFacility(this); });

		    var SelectFacility = function (eObj) {
			    objID = eObj.id;
			    var parts = objID.split("~");
			    return selreplyaddress(parts[0], parts[1], parts[2]);
			}

		});
	</script>
</body>
</html>
<?php
}