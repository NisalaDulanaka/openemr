<?php
// FIX - form_complete should be set by now
?>
<script type="text/javascript">
var mypcc = '<?php echo $GLOBALS['phone_country_code'] ?>';

function printForm()
{
	var target = '<?php echo $rootdir; ?>/forms/<?php echo $frmdir; ?>/printable.php?id=<?php echo $id; ?>&pid=<?php echo $pid; ?>&pop=pop&enc=<?php echo $encounter; ?>';
	wmtOpen(target, '_blank', 600, 800);
}

function printInstructions()
{
	var target = '<?php echo $rootdir; ?>/forms/<?php echo $frmdir; ?>/printinstructions.php?id=<?php echo $id; ?>&pid=<?php echo $pid; ?>&pop=pop&enc=<?php echo $encounter; ?>';
	wmtOpen(target, '_blank', 600, 800);
}

function printSummary()
{
	var target = '<?php echo $rootdir; ?>/forms/<?php echo $frmdir; ?>/print_pat_summary.php?id=<?php echo $id; ?>&pid=<?php echo $pid; ?>&pop=pop&enc=<?php echo $encounter; ?>';
	wmtOpen(target, '_blank', 600, 800);
}

function printReferral()
{
	var target = '<?php echo $rootdir; ?>/forms/<?php echo $frmdir; ?>/print_pat_referral.php?id=<?php echo $id; ?>&pid=<?php echo $pid; ?>&pop=pop&enc=<?php echo $encounter; ?>';
	wmtOpen(target, '_blank', 600, 800);
}

function PopRTO() {
 wmtOpen('<?php echo $GLOBALS['webroot']; ?>/interface/forms/rto/new.php?pop=yes&pid=<?php echo $pid; ?>', '_blank', 1200, 400);
}

function cancelClicked() {
<?php if($warn_popup && strtolower($dt[$cancel_field]) != $cancel_compare) { ?>
	response=confirm("<?php echo $cancel_warning; ?>");
	if(response == true) {
	<?php if(!$pop_form) { ?>
	<?php } ?>
		if(typeof top.restoreSession === "function") {
			top.restoreSession();
			return true;
		}
	} else {
		return false;
	}
<?php } else { ?>
	return true;
<?php } ?>
}

function enableButton(btn)
{
}

function validateForm()
{
	if(typeof top.restoreSession === "function") {
		top.restoreSession();
	}
	// window.setTimeout("enableButton('save_and_quit')", 5000);
	document.forms[0].submit();
}

function clearForm() {
	var i;
	var l = document.forms[0].elements.length;
	for (i=0; i<l; i++) {
		if(document.forms[0].elements[i].name.indexOf('pid') != -1) continue;
		if(document.forms[0].elements[i].name.indexOf('form_') != -1) continue;
		if(document.forms[0].elements[i].name.indexOf('pat_') != -1) continue;
		if(document.forms[0].elements[i].name.indexOf('ins_') != -1) continue;
		if(document.forms[0].elements[i].name.indexOf('tmp_') != -1) continue;
		if(document.forms[0].elements[i].type.indexOf('select') != -1) {
			document.forms[0].elements[i].selectedIndex = 0;
		} else if(document.forms[0].elements[i].type.indexOf('check') != -1) {
			document.forms[0].elements[i].checked = false;
		} else {
			document.forms[0].elements[i].value = '';
		}
	}	
}

// SECTION COLLAPSE CONTROL
$(document).ready(function() {
	// this routine opens and closes sections
	$('.wmtCollapseBar, .wmtBottomBar').click(function() {
		var key = $(this).attr('id');
		key = key.replace('BottomBar','');
		key = key.replace('Bar','');
		var id = '#' + key; 
		var toggle = '#tmp_'+key+'_disp_mode';
		if ($(id+'Box').is(':visible')) {
			$(id+'Box').hide();
			$(id+'Bar').addClass("wmtBarClosed");
			$(id+'Bar').children('img').attr("src","<?php echo $webroot;?>/library/wmt-v2/fill-270.png");
			$(id+'BottomBar').addClass("wmtBarClosed");
			$(id+'BottomBar').children('img').attr("src","<?php echo $webroot;?>/library/wmt-v2/fill-270.png");
			$(toggle).val('none');
		} else {
			$(toggle).val('block');
			$(id+'Bar').removeClass("wmtBarClosed");
			$(id+'Bar').children('img').attr("src","<?php echo $webroot;?>/library/wmt-v2/fill-090.png");
			$(id+'BottomBar').removeClass("wmtBarClosed");
			$(id+'BottomBar').children('img').attr("src","<?php echo $webroot;?>/library/wmt-v2/fill-090.png");
			$(id+'Box').show();
		}
	});
});

<?php include($srcdir.'/wmt-v2/ajax/init_ajax.inc.js'); ?>

</script>