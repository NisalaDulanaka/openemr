<?php
/**
 * interface/billing/customize_log.php - starting point for customization of billing log
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2014 Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2019 Brady Miller <brady.g.miller@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */


require_once("../globals.php");

$filename = $GLOBALS['OE_SITE_DIR'] . '/edi/process_bills.log';

$fh = file_get_contents($filename);

if (cryptCheckStandard($fh)) {
    $fh = decryptStandard($fh, null, 'database');
}

echo nl2br(text($fh));
