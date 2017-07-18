<?php
/**
 * weno admin.
 *
 * @package OpenEMR
 * @link    http://www.open-emr.org
 * @author  Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) 2016-2017 Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

use OpenEMR\Core\Header;
require_once('../globals.php');
require_once('transmitDataClass.php');
require_once("adminClass.php");
require_once("$srcdir/options.inc.php");

$tables   = new adminProperties();

?>
<html>
<head>
     <title><?php print xlt("Weno Admin"); ?></title>
        <?php Header::setupHeader(); ?>

</head>
<body class="body_top">
<div class="container">
<?php

if($GLOBALS['weno_rx_enable'] != 1){
    print xlt("You must activate Weno first! Go to Admnistration, Globals, Connectors");
    exit;
} else {
    print xlt("Weno Service is Enabled")."<br><br>";
}



   $drugData = $tables->drugTableInfo();
if(!$drugData['ndc']){
    echo "<a href='drugPaidInsert.php' class='btn btn-default'>".xlt("Import Formularies")."</a> <br>".xlt("Be patient this may take a while");
} else {
    print xlt("Formularies inserted into table")."<br>";


}

?>

<h3><?php echo xlt("Select State to Import for Pharmacy"); ?></h3>

<form method="post" action="import_pharmacies.php" >
    <div class="col-lg-2">
    <?php echo generate_form_field(array('data_type'=>$GLOBALS['state_data_type'],'list_id'=>$GLOBALS['state_list'], 'field_id'=>'state')); ?>
    </div>
    

    <button type="submit" class="btn btn-default btn-save" value= ><?php echo xlt("Import Pharmacies"); ?> </button>

    <br>
<p><?php echo xlt("Be patient, this can take a while."); ?><br> </p>
</form>
<br><br>

<?php  if(!empty($finish)){echo $finish . xlt("with import");} ?>


</div>
</body>
</html>


