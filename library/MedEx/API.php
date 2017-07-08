<?php
/**
 * /library/MedEx/API.php
 *
 * Copyright (C) 2017 MedEx <support@MedExBank.com>
 *
 * LICENSE: This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEMR
 * @author MedEx <support@MedExBank.com>
 * @link http://www.open-emr.org
 */
namespace MedExApi;

class CurlRequest {
    private $url;
    private $postData = array();
    private $cookies = array();
    private $response = '';
    private $handle;
    private $sessionFile;

    private function getCookies() {
        $cookies = array();
        foreach ($this->cookies as $name=>$value) {
            $cookies[] = $name . '=' . $value;
        }
        return implode('; ', $cookies);
    }

    private function saveSession() {
        if (empty($this->sessionFile)) return;

        if (!file_exists(dirname($this->sessionFile))) {
            mkdir(dirname($this->sessionFile, 0755, true));
        }

        file_put_contents($this->sessionFile, json_encode($this->cookies));
    }

    private function restoreSession() {
        if (file_exists($this->sessionFile)) {
            $this->cookies = json_decode(file_get_contents($this->sessionFile), true);
        }
    }

    public function __construct($sessionFile) {
        $this->sessionFile = $sessionFile;
        $this->restoreSession();
    }

    public function makeRequest() {
        $this->handle = curl_init($this->url);
        curl_setopt($this->handle, CURLOPT_HEADER, true);
        curl_setopt($this->handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->handle, CURLOPT_POST, true);
        curl_setopt($this->handle, CURLOPT_SSL_VERIFYPEER, true);//changed when certificates done - 
        //some may need this to = false if cert authority list not on their server
        curl_setopt($this->handle, CURLOPT_POSTFIELDS, http_build_query($this->postData));
        if (!empty($this->cookies)) {
            curl_setopt($this->handle, CURLOPT_COOKIE, $this->getCookies());
        }

        $this->response = curl_exec($this->handle);
        $header_size = curl_getinfo($this->handle, CURLINFO_HEADER_SIZE);
        $headers = substr($this->response, 0, $header_size);
        $this->response = substr($this->response, $header_size);
        
        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $headers, $matches);
        $cookies = $matches[1];
        foreach ($cookies as $cookie) {
            $parts = explode('=', $cookie);
            $name = array_shift($parts);
            $value = implode('=', $parts);
            $this->cookies[$name] = $value;
        }
        curl_close($this->handle);
        $this->saveSession(); 
    }
    public function setUrl($url) { $this->url = $url;}
    public function setData($postData) { $this->postData = $postData; }
    public function getResponse() { return json_decode($this->response, true);  }
    public function getRawResponse() { return $this->response; }
}

class Base {
    protected $MedEx;
    protected $curl;

    public function __construct($MedEx) {
        $this->MedEx = $MedEx;
        $this->curl = $MedEx->curl;
    }
}

class practice extends Base {
    public function sync($token) {
        global $GLOBALS;
        $fields2 = array();
        $fields3 = array();
        
        $callback = "https://".$GLOBALS['_SERVER']['SERVER_NAME'].$GLOBALS['_SERVER']['PHP_SELF'];
        $callback = str_replace('ajax/execute_background_services.php','MedEx/MedEx.php',$callback);
        $fields2['callback_url'] = $callback;
        //get the providers list:
        $sqlQuery = "SELECT * from medex_prefs";
        $my_status = sqlStatement($sqlQuery);
        while ($urowME = sqlFetchArray($my_status)) {
            $providers = explode('|',$urowME['ME_providers']);
            foreach ($providers as $provider) {
                $runQuery = "SELECT * FROM users WHERE id=?";
                $ures = sqlStatement($runQuery,array($provider));
                while ($urow = sqlFetchArray($ures)) {
                    $fields2['providers'][] = $urow;
                }
            }
        }
        //get the facilities list:
        $runQuery ="select * from facility order by name";
        $ures = sqlStatement($runQuery);
        while ($urow = sqlFetchArray($ures)) {
            $fields2['facilities'][] = $urow;
        }
        //get the categories list:
        $runQuery = "SELECT pc_catid, pc_catname, pc_catdesc, pc_catcolor, pc_seq
                     FROM openemr_postcalendar_categories WHERE pc_active = 1 and pc_cattype='0' order by pc_catid";
        $ures = sqlStatement($runQuery);
        while ($urow = sqlFetchArray($ures)) {
            $fields2['categories'][] = $urow;
        }
        //get apptstats
        $runQuery = "SELECT * FROM `list_options` WHERE `list_id` LIKE 'apptstat'";
        $ures = sqlStatement($runQuery);
        while ($urow = sqlFetchArray($ures)) {
            $fields2['apptstats'][] = $urow;
        }
        $data = array($fields2);
        if (!is_array($data)) {
            return false; //throw new InvalidProductException('Invalid practice information');
        }
        $this->curl->setUrl($this->MedEx->getUrl('custom/addpractice&token='.$token));
        $this->curl->setData($fields2);
        $this->curl->makeRequest();
        $response = $this->curl->getResponse();
        //Patient responses are delivered via callback as they are generated.
        //However client's server may have downtime or perhaps this EHR is run from a laptop only during certain hours?
        //If they are ever offline, we also need a different way of loading appointments! For now they are
        //always on-line sending crons and background_service messages.
        //We need to make sure all our messages are up-to-date.
        //Practice: Since the last MedEx login, who has responded to one of our messages and do we know about these responses?  
        //Have we deleted, cancelled or changed an appointment that we thought we were supposed to send, and now don't want to?
        //1. Check to see if anything pending was cancelled/changed.
        //2. Make sure any recalls don't have appointments just scheduled
        //3. Sync any responses received on MedEx that we didn't see already.
        //     Send and Receive everything since last timestamp/update noted in medex_prefs and check. 
        //      Finally we may have manually made an appointment (which deletes a Recall) or manually confirmed an appt too.
        //4. We need to send this data to MedEx so it stops processing events that are confirmed/completed.
        
        //1. Check to see if anything pending was cancelled/changed.
        //for appts, we are just looking for appts that are flagged as 'To Send' BUT
        // were confirmed, cancelled, moved by the staff since added to table medex_outgoing...
        $sql = "SELECT * from medex_outgoing where msg_pc_eid != 'recall_%' AND msg_reply like 'To Send'";
        $test = sqlStatement($sql);
        while ($result1 = sqlFetchArray($test)) {
            $query  = "select * from openemr_postcalendar_events where pc_eid = ?";
            $test2 = sqlStatement($query,array($result1['msg_pc_eid']));
            $result2 = sqlFetchArray($test2);
            //for custom installs, insert custom apptstatus here that mean appt is not happening/changed
            if ($result2['pc_apptstatus'] =='*' ||  //confirmed
                $result2['pc_apptstatus'] =='%' ||  //cancelled < 24hour
                $result2['pc_apptstatus'] =='x' ) { //cancelled

                $sqlUPDATE = "UPDATE medex_outgoing set msg_reply = 'DONE',msg_extra_text=? where msg_uid = ?";
                sqlQuery($sqlUPDATE,array($result2['pc_apptstatus'],$result2['msg_uid']));
                //we need to update MedEx regarding actions to try to cancel
                $tell_MedEx['DELETE_MSG'][] = $result1['msg_pc_eid'];
            }
        }

        // 2. Make sure any recalls don't have appointments just scheduled
        //   Go through each recall event in medex_outgoing and check to see if it is completed.  If so tell MedEx.
        //   We do this when we load the Recall Board........ and now when running a cron job/background_service
        $sql = "SELECT * from medex_outgoing where msg_pc_eid like 'recall_%' GROUP by msg_pc_eid";
        $result = sqlStatement($sql);
        while ($row = sqlFetchArray($result)) {
            $pid = trim($row['msg_pc_eid'],"recall_");
            //if there is a future appointment now in calendar, stop this recall
            $query  = "select pc_eid from openemr_postcalendar_events WHERE (pc_eventDate > CURDATE()) AND pc_pid=?";
            $test3 = sqlStatement($query,array($pid));
            $result3 = sqlFetchArray($test3);
            if ($result3) {
                $sqlUPDATE = "UPDATE medex_outgoing set msg_reply = 'SCHEDULED', msg_extra_text=? where msg_uid = ?";
                sqlQuery($sqlUPDATE,array($result3['pc_eid'],$result2['msg_uid']));
                //we need to update MedEx regarding actions to try to cancel
                $tell_MedEx['DELETE_MSG'][] = $row['msg_pc_eid'];       
            }
        }

        //3. Sync any responses received on MedEx that we didn't see already.
        //get last update
        $sqlQuery = "SELECT * from medex_prefs";
        $my_status = sqlStatement($sqlQuery);
        while ($urow = sqlFetchArray($my_status)) {
            $fields3['MedEx_lastupdated']   = $urow['MedEx_lastupdated'];
            $fields3['ME_providers']        = $urow['ME_providers'];
        }
        $this->curl->setUrl($this->MedEx->getUrl('custom/sync_responses&token='.$token));
        $this->curl->setData($fields3);
        $this->curl->makeRequest();
        $responses = $this->curl->getResponse();
        foreach ($responses['messages'] as $data) {
            //check to see if this response is present already
            $data['msg_extra'] = $data['msg_extra']?:'';
            $sqlQuery ="SELECT * from medex_outgoing where msg_pc_eid=? and campaign_uid=? and msg_type=? and msg_reply=?";//" and msg_extra_text=?";
            $checker = sqlStatement($sqlQuery,array($data['e_pc_eid'],$data['campaign_uid'], $data['M_type'],$data['msg_reply']));//,$data['msg_extra']));
            if (sqlNumRows($checker)=='0') { //if this isn't already here, add it to local DB.        
                $this->MedEx->callback->receive($data); 
            }
        }
        $sqlUPDATE = "UPDATE medex_prefs set MedEx_lastupdated=utc_timestamp()";
        sqlStatement($sqlUPDATE);

        if (!empty($tell_MedEx['DELETE_MSG'])) {
            $this->curl->setUrl($this->MedEx->getUrl('custom/remMessaging&token='.$token));
            $this->curl->setData($tell_MedEx['DELETE_MSG']);
            $this->curl->makeRequest();
            $response = $this->curl->getResponse();
         }
        if (!empty($response['found_replies'])) {
            $response['success']['message'] = "Replies retrieved: ".$response['found_replies'];
        } else {
            $response['success']['message'] = "No new messages on MedEx.";
        }

        if (isset($response['success'])) { 
            return $response;
        } else if (isset($response['error'])) {
            $this->lastError = $response['error'];
        }
        return false;
    }
}

class Campaign extends Base {
    public function events($token) {
        $this->curl->setUrl($this->MedEx->getUrl('custom/showEvents&token='.$token));
        $this->curl->makeRequest();
        $response = $this->curl->getResponse();
    
        if (isset($response['success'])) { 
            return $response;
        } else if (isset($response['error'])) {
            $this->lastError = $response['error'];
        }
        return false;
    }
    public function display_campaign_events($logged_in) {
        $this->curl->setUrl($this->MedEx->getUrl('account/edit&token='.$logged_in['token']));
        $this->curl->makeRequest();
        $response = $this->curl->getResponse();
        if (isset($response['success'])) { 
            return $response;
        } else if (isset($response['error'])) {
            $this->lastError = $response['error'];
        }
        return false;
    }
}

class Events extends Base {
    //this is run via cron to find appointments that match our Campaign events and rules.
    private function recursive_array_search($needle,$haystack) {
        foreach($haystack as $key=>$value) {
            $current_key=$key;
            if($needle===$value OR (is_array($value) && $this->recursive_array_search($needle,$value))) {
                return true; //$current_key;
            }
        }
        return false;
    } 
    public function generate($token,$events) {
        if (empty($events)) return; //You have no campaign events on MedEx!
        $appts = array();
        $appt3 = array();
        $count_appts='0';
        // For future appts, we don't want to run anything on the weekend so do them Friday.
        // There is a GLOBALS value for weekend days, maybe use that LTR.
        // -->If Friday, send all appts matching campaign fire_Time + 2 days
        // For past appts, we also don't want to send messages on the weekend, so do them Friday.
        // -->If Friday, send all appts matching campaign fire_Time + 2 days works for past appts also.
        foreach ($events as $event) {
            /**
             * If it is a PM event, it needs to run after 6PM to generate the requested appt list
             * MedEx E_timing options:
             * 1 = days before
             * 2 = days before PM
             * 3 = days after
             * 4 = days after PM
             * So if it == 2 or 4, MedEx checks time of day (local timezone) and run if after 6PM. 
             */
            if (($event['E_timing'] == '2')||($event['E_timing'] == '2')) {
                if (date('H') < 18) {
                    continue;
                }
            }
            if ($event['M_group'] == 'REMINDER') { 
                if ($event['time_order'] > '0') { // future appts
                    $interval ="+";
                    //NOTE IF you have customized the pc_appstatus flags, you need to adjust them here too.
                    //Hunting a bug - error log via cron shows some appts do not have E_instructions set.
                    //Should not be possible though.  For now if not set, set it to stop if confirmed:
                    if (empty($event['E_instructions'])) $event['E_instructions'] ='stop';
                    //remove when bug found
                    if ($event['E_instructions'] == "stop") {   //ie don't send this if it has been confirmed.
                                                                // we will only send it if nothing has been done to the appointment status
                        $appt_status = " and pc_apptstatus='-'";//we only look at future appts w/ apptstatus == NONE ='-'
                    // OR send anyway - unless appstatus is not cancelled, then it is no longer an appointment to confirm...
                    } elseif ($event['E_instructions'] == "always") {  //send anyway
                        $appt_status = " and pc_apptstatus != '%' 
                                         and pc_apptstatus != 'x' ";
                    }
                } else { 
                    // Past appts -> appts that are completed.  
                    // Need to exclude appts that were cancelled or noshowed 
                    // Use the flag in the list_options to note that the appointment is completed
                    // in v2.0 we will have the option to do campaigns based on appstatus, eg. send message to NoShows etc
                    // Time will tell when the demand for these features warrants adding them here.
                    // Cool way to do MIPS post-appt activities! 
                    $interval ='-';//for now just use appts completed - this is defined by list_option->toggle_setting2=1 for Flow Board
                    $appt_status = " and pc_apptstatus in (SELECT option_id from list_options where toggle_setting_2='1' and list_id='appstat') "; 
                }
                $timing = (int)$event['E_fire_time'];
                // if it is Friday do stuff as scheduled + 2 days more
                // for future appts, widen the net to get Timing2 = $timing +2.":1:1";
                // eg an event/message is scheduleto go out 2 days in advance - reminder SMS.
                // It is Friday.  2 days ahead is Sunday, but Monday's would run on Saturday and Tuesday's on Sunday.
                // We should run them all on Friday...  So load them that way now.
                $today=date("l");
                if ($today =="Sunday") continue;
                if ($today == "Friday") {
                    $timing2 = ($timing + 3).":0:1"; //this is + 3 day, 0 hour and 1 minute...  
                } else {
                    $timing2 = ($timing + 1).":1:1"; //this is + 1 day, 1 hour and 1 minute...  
                }
                $sql2= "SELECT ME_facilities from medex_prefs";
                $pref_facilities = sqlQuery($sql2);
                
                if ($pref_facilities['ME_facilities'] !='') {
                    $facs = explode(',',$pref_facilities['ME_facilities']);
                    $places='';
                    foreach ($facs as $place) {
                        $places .= $place.",";
                    }
                    $places = rtrim($places,",");
                    
                    $query  = "select * from openemr_postcalendar_events as cal 
                                left join patient_data as pat on cal.pc_pid=pat.pid 
                                WHERE (pc_eventDate > CURDATE() ".$interval." INTERVAL ".$timing." DAY 
                                AND pc_eventDate < (curdate() ".$interval." INTERVAL '".$timing2."' DAY_MINUTE)) 
                                AND pc_facility in (".$places.")
                                AND pat.pid=cal.pc_pid ".$appt_status." ORDER BY pc_eventDate,pc_startTime";
                    $result = sqlStatement($query);
                    while ($appt= sqlFetchArray($result))   {
                        //SUBEVENTS are not implemented yet.  This is how they will work:
                        // If you routinely make 2 back-to-back or related appointments (eg 2 stage yearly physical or Surgery and post-op day#3 visit)
                        // we can combine reminders occurring within X days.  
                        // Visit 2 is silent as far as its own reminder messages until Visit 1 has passed.
                        // Then Visit 2 fires for any remaining rules that match it.
                        // Let me know if this is a desired feature and we can add it to v2.0
                        /*if ($appt['e_is_subEvent_of'] > '0') {
                            $query ="select * from medex_outgoing where e_uid=?";
                            $event2 = sqlStatement($query,array($appt['e_is_subEvent_of']));
                            if (new DateTime() < new DateTime($event2["e_eventDate"]." ".$event2["e_eventTime"])) {
                                // if current time is less than Parent Appt, ignore this appt
                                continue;
                            }
                        }*/
                        list($response,$results) = $this->MedEx->checkModality($event,$appt);
                        if($results==false) continue; //not happening - either not allowed or not possible
                        $count_appts++;

                        $appt2 = array();                    
                        $appt2['pc_pid']        = $appt['pc_pid'];
                        $appt2['e_C_UID']       = $event['C_UID'];
                        $appt2['pc_eventDate']  = $appt['pc_eventDate'];
                        $appt2['pc_startTime']  = $appt['pc_startTime'];
                        $appt2['pc_eid']        = $appt['pc_eid'];
                        $appt2['e_pc_eid']      = $appt['pc_eid'];
                        $appt2['pc_aid']        = $appt['pc_aid'];
                        $appt2['e_reason']      = (!empty($appt['e_reason']))?:'';
                        $appt2['e_is_subEvent_of']= (!empty($appt['e_is_subEvent_of']))?:"0";
                        $appt2['language']      = $appt['language'];
                        $appt2['pc_facility']   = $appt['pc_facility'];
                        $appt2['fname']         = $appt['fname'];
                        $appt2['lname']         = $appt['lname'];
                        $appt2['mname']         = $appt['mname'];
                        $appt2['street']        = $appt['street'];
                        $appt2['postal_code']   = $appt['postal_code'];
                        $appt2['city']          = $appt['city'];
                        $appt2['state']         = $appt['state'];
                        $appt2['country_code']  = $appt['country_code'];
                        $appt2['phone_home']    = $appt['phone_home'];
                        $appt2['phone_cell']    = $appt['phone_cell'];
                        $appt2['email']         = $appt['email'];
                        $appt2['C_UID']         = $event['C_UID'];
                        $appt2['E_fire_time']   = $event['E_fire_time'];
                        $appt2['time_order']    = $event['time_order'];
                        $appt2['M_type']        = $event['M_type'];
                        $appt2['reply']         = "To Send";
                        $appt2['extra']         = "QUEUED";
                        $appt2['status']        = "SENT";
                       
                        $appt2['to']            = $results;
                        $appt3[] = $appt2;
                    }
                }
            } elseif ($event['M_group'] == 'RECALL') { 
                if ($event['time_order'] > '0') {
                    $interval ="+";
                } else {
                    $interval ='-';
                }
                $timing = $event['E_fire_time'];
                $timing2 = ($timing +1).":1:1"; 
                // this is + 1 day, 1 hour and 1 minute...  
                // This retrieves recalls that need consideration today.
                $count_recalls ='0';
                $query  = "select * from medex_recalls as recall 
                            left join patient_data as pat on recall.r_pid=pat.pid 
                            WHERE (r_eventDate < CURDATE() ".$interval." INTERVAL ".$timing." DAY) 
                            ORDER BY r_eventDate";
                $result = sqlStatement($query);
                $recall3 = array();
                while ($recall = sqlFetchArray($result))   {
                    // Can we run the rule - is the modality possible?
                    list($response,$results) = $this->MedEx->checkModality($event,$recall);
                    if($results==false) continue; //not happening - either not allowed or not possible
                    
                    // If this is the first rule to run for this recall/patient, nothing exists anywhere.
                    // Once something happens, we put a row in medex_outgoing.
                    // If an appointment was made already, within 3 months of this Recall date, 
                    // Recall Board will auto-delete this from recall board after 16 hours.
                    // If you don't load the Recall Board, we need to run this check w/ this cron command also.
                    $show = $this->MedEx->display->show_progress_recall($recall,$event);
                    if ($show['DONE'] == '1') { 
                        // It's done/finished, about to be deleted, so don't process this RECALL, o/w do it
                        // MedEx doesn't make RECALL appts, so we need to tell MedEx it is done, or do we?
                        // As long as Practice knows it is done, who cares if MedEx knows?
                        //  Hold on telling MedEx it is done too.  Test to see if we care.  If we made a new Recall
                        //  message, this still won't fire because it is already DONE!  And once done, it is deleted!
                        //  Yeah, don't waste time sending message to MedEx.  
                        //  Wait, WE CARE:  MedEx runs messaging by cron and it needs to know when to delete a RECALL!
                        //  MEDEX NEEDS TO KNOW IF THE STAFF MADE THE APPOINTMENT SO MEDEX can delete the row, and relax.
                        //  OK, send completed message to MedEx TO DELETE OVER THERE.  
                        $RECALLS_completed[] = $recall;
                        continue;
                    }
                    if ($show['status']!=="reddish") {
                        // OK there is status for this recall.  Something happened.  Maybe something was sent.
                        // Maybe despite everything there has been no appointment yet (yellowish) or it was just
                        // made today (greenish)?  Either way, we don't want to regenerate this - don't add it to our Appt list.
                        continue;
                    }

                    //OK  the list of recalls may include "older than today" recalls, they should only be included once...
                    //If a recall date has passed (ie. loading recalls for the first time would included recalls that are already due,
                    // not just upcoming.  If there are more than one campaign event that could fire for this
                    // recall, we are only going to do the first one.  Generate one thing for MedEx to do for this event.
                    // Think.  Maybe the practice schedule 2 Campaign Event Messages for the same day?  We would want to run both right?
                    // Yes, so only limit recalls that are in the past to one event!  T
                   
                    if (strtotime($recall['r_eventDate']) < mktime(0,0,0)) {
                        if ($this->recursive_array_search("recall_".$recall['r_pid'],$appt3)) { continue; }
                    }
                    $count_recalls++;
                    $recall2 = array();                    
                    $recall2['pc_pid']        = $recall['r_pid'];
                    $recall2['e_C_UID']       = $event['C_UID'];
                    $recall2['pc_eventDate']  = $recall['r_eventDate'];
                    $recall2['pc_startTime']  = '10:42:00';
                    $recall2['pc_eid']        = "recall_".$recall['r_pid'];
                    $recall2['e_pc_eid']      = "recall_".$recall['r_pid'];
                    $recall2['pc_aid']        = $recall['r_provider'];
                    $recall2['e_reason']      = (!empty($recall['r_reason']))?:'';
                    $recall2['e_is_subEvent_of']= "0";
                    $recall2['language']      = $recall['language'];
                    $recall2['pc_facility']   = $recall['r_facility'];
                    $recall2['fname']         = $recall['fname'];
                    $recall2['lname']         = $recall['lname'];
                    $recall2['mname']         = $recall['mname'];
                    $recall2['street']        = $recall['street'];
                    $recall2['postal_code']   = $recall['postal_code'];
                    $recall2['city']          = $recall['city'];
                    $recall2['state']         = $recall['state'];
                    $recall2['country_code']  = $recall['country_code'];
                    $recall2['phone_home']    = $recall['phone_home'];
                    $recall2['phone_cell']    = $recall['phone_cell'];
                    $recall2['email']         = $recall['email'];
                    $recall2['C_UID']         = $event['C_UID'];
                    $recall2['E_fire_time']   = $event['E_fire_time'];
                    $recall2['time_order']    = $event['time_order'];
                    $recall2['M_type']        = $event['M_type'];
                    $recall2['reply']         = "To Send";
                    $recall2['extra']         = "QUEUED";
                    $recall2['status']        = "SENT";
                    $recall2['to']            = $results;
                    
                    $appt3[] = $recall2;
                }
            }   
        }
        if (!empty($RECALLS_completed)) {
            $hipaa = $this->process_deletes($token,$RECALLS_completed);
        }
        if (!empty($appt3)) {
            $hipaa = $this->process($token,$appt3);
        }     

        $responses['count_appts'] = $count_appts;
        $responses['count_recalls'] = $count_recalls;
        return $responses;
    }
    /**
     *  This function processes appointments/recalls that meet the timimg requirements for a MedEx Campaign Event
     */
    private function process($token,$appts) {
        if (empty($appts)) return; //throw new InvalidDataException("You have no appointments that need processing at this time.");
        $data= array();
        foreach ($appts as $appt) {
            $data['appts'][] = $appt;
           /* if ($appt['reply'] == "To Send") {
                $sqlINSERT = "INSERT INTO medex_outgoing (msg_pc_eid, campaign_uid, msg_type, msg_reply, msg_extra_text) 
                        VALUES (?,?,?,?,?)
                        ON DUPLICATE KEY UPDATE msg_reply=?";
                sqlQuery($sqlINSERT,array($appt['pc_eid'],$appt['C_UID'], $appt['M_type'],$appt['reply'],$appt['extra'],$appt['reply']));
            } else {*/
                $sqlUPDATE = "UPDATE medex_outgoing set msg_reply=?, msg_extra_text=?, msg_date=NOW()
                                WHERE msg_pc_eid=? and campaign_uid=? and msg_type=? and msg_reply='To Send'";
                sqlQuery($sqlUPDATE,array($appt['reply'],$appt['extra'],$appt['pc_eid'],$appt['C_UID'], $appt['M_type']));
            //}
            //send ten at a time.
            if (count($data['appts'])>'20') {
                $this->curl->setUrl($this->MedEx->getUrl('custom/loadAppts&token='.$token));
                $this->curl->setData($data);
                $this->curl->makeRequest();
                $response   = $this->curl->getResponse(); 
                $data       = array();
                sleep(1);
            }
        }
        //finish those $data < 20.
        $this->curl->setUrl($this->MedEx->getUrl('custom/loadAppts&token='.$token));
        $this->curl->setData($data);
        $this->curl->makeRequest();
        $response = $this->curl->getResponse(); 
   
        if (isset($response['success'])) { 
            return $response;
        } else if (isset($response['error'])) {
            $this->lastError = $response['error'];
        }
        return false;
    }    
    /**
     *  This function deletes Recalls from MedEx when they are completed and no further processing is 
     *   needed. They are in an array = $data.
     */
    private function process_deletes($token,$data) {

        $this->curl->setUrl($this->MedEx->getUrl('custom/remRecalls&token='.$token));
        $this->curl->setData($data);
        $this->curl->makeRequest();
        $response = $this->curl->getResponse(); 
        
        if (isset($response['success'])) { 
            return $response;
        } else if (isset($response['error'])) {
            $this->lastError = $response['error'];
        }
        return false;
    }
    public function save_recall($saved) {
        $this->delete_Recall($saved);
        
        $mysqldate = date('Y-m-d', strtotime($_REQUEST['datepicker2']));
        $queryINS = "INSERT into medex_recalls (r_pid,r_reason,r_eventDate,r_provider,r_facility) 
                        VALUES (?,?,?,?,?) 
                        on DUPLICATE KEY 
                        UPDATE r_reason=?, r_eventDate=?, r_provider=?,r_facility=?";
        $result = sqlStatement($queryINS,array($_REQUEST['new_pid'],$_REQUEST['new_reason'],$mysqldate,$_REQUEST['new_provider'],$_REQUEST['new_facility'],$_REQUEST['new_reason'],$mysqldate,$_REQUEST['new_provider'],$_REQUEST['new_facility']));
        $query = "UPDATE patient_data 
                    set phone_home=?,phone_cell=?,email=?,
                        hipaa_allowemail=?,hipaa_voice=?,hipaa_allowsms=?,
                        street=?,postal_code=?,city=?,state=?
                    where pid=?";
        $sqlValues = array($_REQUEST['new_phone_home'],$_REQUEST['new_phone_cell'],$_REQUEST['new_email'],
                            $_REQUEST['new_email_allow'],$_REQUEST['new_voice'],$_REQUEST['new_allowsms'],      
                            $_REQUEST['new_address'],$_REQUEST['new_postal_code'],$_REQUEST['new_city'],$_REQUEST['new_state'], 
                            $_REQUEST['new_pid']);
        $result = sqlStatement($query,$sqlValues);
        return;
    }
    public function delete_Recall($saved) {
        $sqlQuery = "DELETE FROM medex_recalls where r_pid=? or r_ID=?";
        sqlStatement($sqlQuery,array($_POST['pid'],$_POST['r_ID']));

        $sqlDELETE = "DELETE from medex_outgoing where msg_pc_eid = ?";
        sqlStatement($sqlDELETE,array('recall_'.$_POST['pid']));
    }
    public function getAge($dob, $asof='') {
        if (empty($asof)) $asof = date('Y-m-d');
        $a1 = explode('-', substr($dob , 0, 10));
        $a2 = explode('-', substr($asof, 0, 10));
        $age = $a2[0] - $a1[0];
        if ($a2[1] < $a1[1] || ($a2[1] == $a1[1] && $a2[2] < $a1[2])) --$age;
        return $age;
    }
}

/**
 *  Process updates and message replies received from MedEx.
 *  Let MedEx know if we did anything manually to a queued event.
 */
class Callback extends Base {
    public function receive($data='') {
        if ($data=='') $data = $_POST;
        if (empty($data['campaign_uid'])) return; //throw new InvalidDataException("There must be a Campaign to update...");
        
        //logging should follow openEMR conventions, this doesn't but it works for now
        $data['TIMER'] = date(DATE_RFC2822);
        $this->MedEx->logging->log_this($data);
        //Store responses in TABLE medex_outgoing
        $sqlINSERT = "INSERT INTO medex_outgoing (msg_pc_eid, campaign_uid, msg_type, msg_reply, msg_extra_text,msg_date) 
                        VALUES (?,?,?,?,?,utc_timestamp())";
//                        ON DUPLICATE KEY UPDATE msg_extra_text=?";
        sqlQuery($sqlINSERT,array($data['pc_eid'],$data['campaign_uid'], $data['msg_type'],$data['msg_reply'],$data['msg_extra']));
        //process AVM responses
        if ($data['msg_reply']=="CONFIRMED") {
            $sqlUPDATE = "UPDATE openemr_postcalendar_events set pc_apptstatus = ? where pc_eid=?";
            sqlStatement($sqlUPDATE,array($data['msg_type'],$data['pc_eid']));
            $sqlFLOW = "UPDATE patient_tracker_element set status=? where pt_tracker_id in (select id from patient_tracker where eid=?)";
            sqlStatement($sqlFLOW,array($data['msg_type'],$data['pc_eid']));//if it is not in tracker what will happen?  Error and continue?
            $log['sql']=$sqlFlow;
            $this->MedEx->logging->log_this($log);     
        } elseif ($data['msg_reply']=="CALL") {
            $sqlUPDATE = "UPDATE openemr_postcalendar_events set pc_apptstatus = 'CALL' where pc_eid=?";
            $test = sqlQuery($sqlUPDATE,array($data['pc_eid']));
            $log['sql']=$sqlUPDATE;
            $this->MedEx->logging->log_this($log);
        } elseif (($data['msg_type']=="AVM") && ($data['reply']=="STOP")) {
            //if reply = "STOP" update patient demographics to disallow this mode of communication
            //well this is where we are also for RECALLS
            if (!$data['patient_id']) {
                if ($data['e_pid']) {
                    $data['patient_id'] = $patient['e_pid'];
                } else {
                    $query = "SELECT * from openemr_postcalendar_events where pc_eid=?"; //assume one patient per appointment pc_eid/slot...
                    $patient = sqlFetchArray(sqlStatement($query,array($data['pc_eid'])));  //otherwise this will need to be a loop          
                    $data['patient_id'] = $patient['pid'];
                }
            }
            $sqlUPDATE = "UPDATE patient_data set hipaa_voice = 'NO' where pid=?";
            sqlQuery($sqlUPDATE,array($data['patient_id']));
        } elseif (($data['msg_type']=="SMS") && ($data['reply']=="STOP")) {
            $sqlUPDATE = "UPDATE patient_data set hipaa_allowsms = 'NO' where pid=?";
            $log['sql']=$sqlUPDATE;
            $this->MedEx->logging->log_this($log);
            sqlQuery($sqlUPDATE,array($data['e_pid']));
        } elseif (($data['msg_type']=="EMAIL") && ($data['reply']=="STOP")) {
            $sqlUPDATE = "UPDATE patient_data set hipaa_allowemail = 'NO' where pid=?";
            $log['sql']=$sqlUPDATE;
            $this->MedEx->logging->log_this($log);
            sqlQuery($sqlUPDATE,array($data['patient_id']));
        } 
        if (($data['msg_reply']=="SENT")||($data['reply']=="READ")) {
            $sqlDELETE = "DELETE FROM medex_outgoing where msg_pc_eid=? and msg_reply='To Send'";
            $log['sql']=$sqlDELETE;
            $this->MedEx->logging->log_this($log);
            sqlQuery($sqlDELETE,array($data['pc_eid']));
        }
        //process E-MAIL responses
        //TODO        --- return manual processed records.

        $response['comments'] = $data['pc_eid']." - ".$data['campaign_uid']." - ".$data['msg_type']." - ".$data['reply']." - ".$data['extra'];
        $response['pid'] = $data['pid'];
        $response['success'] = $data['msg_type']." reply";
        
        return $response;
    }
}

class Logging extends base {
    public function log_this($data) {
        //look in the openEMR files to figure out how to log this.
        $log = "/tmp/myhipaa.log" ;
        $stdlog = fopen($log, 'a');
        $timed = date(DATE_RFC2822);
        fputs($stdlog,"\n".$timed."\n");        
        foreach ($data as $key => $value) {
            fputs($stdlog, $key.": ".$value."\n");
        }
        fclose($stdlog);
        return true;
    }
}

class Display extends base {
    public function navigation($logged_in) {
        ?>
        <nav class="navbar-fixed-top navbar-custom navbar-bright navbar-inner" data-role="page banner navigation" Xstyle="margin-bottom: 0;z-index:1000000;font-size: 1.2em;">
            <!-- Brand and toggle get grouped for better mobile display -->
            <div class="container-fluid">
                <div class="navbar-header brand">
                    <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#oer-navbar-collapse-1">
                        <span class="sr-only"><?php echo xlt("Toggle navigation"); ?></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </button>
                    &nbsp;
                    <img src="<?php echo $GLOBALS['webroot']; ?>/sites/default/images/login_logo.gif" class="little_image">
                </div>
                <div class="navbar-collapse collapse" id="oer-navbar-collapse-1">
                    <ul class="navbar-nav">
                        <li class="dropdown">
                            <a class="dropdown-toggle" data-toggle="dropdown" id="menu_dropdown_file" role="button" aria-expanded="true"><?php echo xlt("File"); ?> </a>
                            <ul class="dropdown-menu" role="menu">
                                <?php if ($logged_in) { ?>
                                 <li id="menu_PREFERENCES"  name="menu_PREFERENCES" class=""><a href="<?php echo $GLOBALS['web_root']; ?>/interface/main/messages/messages.php?go=Preferences"><?php echo xlt("Preferences"); ?></a></li>
                                    <?php } else { ?>
                                <li id="menu_PREFERENCES"  name="menu_PREFERENCES" class="">
                                    <a href="<?php echo $GLOBALS['web_root']; ?>/interface/main/messages/messages.php?go=setup&stage=1"><?php echo xlt("Setup MedEx"); ?></a></li>
                                <?php } ?>
                                <li id="icons" name="icons"><a onclick="doRecallclick_edit('icons');"><?php echo xlt('Icon Chart'); ?></a></li>
                            </ul>
                        </li>
                        
                        <li class="dropdown">
                            <a class="dropdown-toggle" data-toggle="dropdown" id="menu_dropdown_msg" role="button" aria-expanded="true"><?php echo xlt("Messages"); ?> </a>
                            <ul class="dropdown-menu" role="menu">
                                <li id="menu_new_msg"> <a href="<?php echo $GLOBALS['web_root']; ?>/interface/main/messages/messages.php?showall=no&sortby=users.lname&sortorder=asc&begin=0&task=addnew&form_active=1"> <?php echo xlt("New Message"); ?></a></li>

                                <li class="divider"><hr /></li>

                                <li id="menu_new_msg"> <a href="<?php echo $GLOBALS['web_root']; ?>/interface/main/messages/messages.php?show_all=no&form_active=1"> <?php echo xlt("My Messages"); ?></a></li>
                                <li id="menu_all_msg"> <a href="<?php echo $GLOBALS['web_root']; ?>/interface/main/messages/messages.php?show_all=yes&form_active=1"> <?php echo xlt("All Messages"); ?></a></li>

                                <li class="divider"><hr /></li>
                                
                                <li id="menu_active_msg"> <a href="<?php echo $GLOBALS['web_root']; ?>/interface/main/messages/messages.php?show_all=yes&form_active=1"> <?php echo xlt("Active Messages"); ?></a></li>
                                <li id="menu_inactive_msg"> <a href="<?php echo $GLOBALS['web_root']; ?>/interface/main/messages/messages.php?form_inactive=1"> <?php echo xlt("Inactive Messages"); ?></a></li>
                                <li id="menu_log_msg"> <a onclick="openLogScreen();" > <?php echo xlt("Message Log"); ?></a></li>
                            </ul>
                        </li>
                    <!--    In ver 1.0 we do not tackle Clinical Reminders, just appt reminders...  
                        <li class="dropdown" > <a class="dropdown-toggle" data-toggle="dropdown" id="menu_dropdown_recalls" role="button" aria-expanded="true"><?php echo xlt("Clinical Reminders"); ?> </a>
                             <ul class="dropdown-menu" role="menu">
                                <li><a id="BUTTON_ApRem_menu" href="<?php echo $GLOBALS['web_root']; ?>/interface/patient_file/reminder/patient_reminders.php?mode=admin&patient_id="> <?php echo xlt("Cl. Reminder Board"); ?></a></li>
                                <li class="divider"><hr /></li>
                                <li id="menu_pend_recalls" name="menu_pend_recalls"> <a onclick="goReminderRecall('Recalls');" id="BUTTON_pend_recalls_menu" href="#"> <?php echo xlt("Cl. Reminder Options"); ?></a></li>
                            <?php if ($logged_in) { ?>    <li class="divider"><hr /></li>
                                <li id="menu_pend_recalls" name="menu_pend_recalls"> <a onclick="goReminderRecall('Reminder_Campaigns');" id="BUTTON_pend_recalls_menu" href="#"> <?php echo xlt("Cl. Reminder Campaigns"); ?></a></li>
                            <?php } ?>
                            </ul>
                         </li>
                        -->
                        <?php if ($logged_in) { ?>
                        <li class="dropdown" > <a class="dropdown-toggle" data-toggle="dropdown" id="menu_dropdown_recalls" role="button" aria-expanded="true"><?php echo xlt("Appt. Reminders"); ?> </a>
                             <ul class="dropdown-menu" role="menu">
                                <li><a id="BUTTON_ApRem_menu" href="<?php echo $GLOBALS['web_root']; ?>/interface/main/main_info.php"> <?php echo xlt("Calendar"); ?></a></li>
                                <li class="divider"><hr /></li>
                                <li id="menu_pend_recalls" name="menu_pend_recalls"> <a id="BUTTON_pend_recalls_menu" href="<?php echo $GLOBALS['web_root']; ?>/interface/patient_tracker/patient_tracker.php?skip_timeout_reset=1"> <?php echo xlt("Flow Board"); ?></a></li>
                                <li class="divider"><hr /></li>
                                <li id="menu_pend_recalls" name="menu_pend_recalls"> <a href='https://medexbank.com/cart/upload/index.php?route=information/campaigns' class='nowrap left' target='_MedEx' id="BUTTON_pend_recalls_menu"> <?php echo xlt("Reminder Campaigns"); ?></a></li>
                            </ul>
                         </li>
                         <?php } ?>
                         <li class="dropdown">
                            <a class="dropdown-toggle" data-toggle="dropdown" id="menu_dropdown_recalls" role="button" aria-expanded="true"><?php echo xlt("Patient Recalls"); ?> </a>
                            <ul class="dropdown-menu" role="menu">
                                <li id="menu_new_recall" name="menu_new_recall"> <a id="BUTTON_new_recall_menu" href="<?php echo $GLOBALS['web_root']; ?>/interface/main/messages/messages.php?go=addRecall"> <?php echo xlt("New Recall"); ?></a></li>
                                <li id="menu_pend_recalls" name="menu_pend_recalls"> <a  onclick="goReminderRecall('Recalls');" id="BUTTON_pend_recalls_menu" href="#"> <?php echo xlt("Recall Board"); ?></a></li>
                            <?php if ($logged_in) { ?>
                                 <li class="divider"><hr /></li>
                                <li id="menu_pend_recalls" name="menu_pend_recalls"> <a href='https://medexbank.com/cart/upload/index.php?route=information/campaigns' class='nowrap left' target='_blank' id="BUTTON_pend_recalls_menu"> <?php echo xlt("Recall Campaigns"); ?></a></li>
                            <?php } ?>
                            </ul>
                        </li>
                    </ul>
                </div><!-- /.navbar-collapse -->
            </div>
        </nav>
        <?php 
        $error=$this->MedEx->getLastError();
        if (!empty($error['ip'])) { ?>
        <div class="alert alert-danger" style="width:50%;margin:30px auto 5px;font-size:0.9em;text-align:center;">    
            <?php  echo $error['ip']; ?>
        </div>
        <?php   }
    }
    public function preferences($prefs='') {
        if (empty($prefs)) {
            $prefs = sqlFetchArray(sqlStatement("SELECT * from medex_prefs"));
        }
        ?>
        <div class="row">
            <div class="col-sm-12">
                <div class="showRecalls" id="show_recalls" style="text-align:center;margin:40px auto;">
                    <div class="title"><?php echo xlt('Reminder/Recall Preferences'); ?></div>
                    <div name="div_response" id="div_response"><br /></div>
                        <form action="#" name="save_prefs" id="save_prefs">
                            <div class="row">
                                <div class="col-sm-1"><input type="hidden" name="go" id="go" value="Preferences">
                                </div>
                                <div class="col-sm-10 div-center" id="daform2"><div class="divTable2">
                                        <div class="divTableBody prefs">
                                            <div class="divTableRow">
                                                <div class="divTableCell divTableHeading"><?php echo xlt('General'); ?></div>
                                                <div class="divTableCell indent20">
                                                    <input type="checkbox" class="update" name="ME_hipaa_default_override" id="ME_hipaa_default_override" value="1" <?php 
                                                        if ($prefs['ME_hipaa_default_override']=='1') echo 'checked ="checked"'; ?>/>
                                                    <label for="ME_hipaa_default_override" class="input-helper input-helper--checkbox" title='<?php echo xla('Default: "checked". When checked, messages are processed for patients with Patient Demographic Choice: "Hipaa Notice Received" set to "Unassigned" or "Yes". When unchecked, this choice must = "YES" to process the patient reminder.  For patients with Choice ="No", Reminders will need to be processed manually.'); //or no translation... ?>'>
                                                        <?php echo xlt('Assume patients receive HIPAA policy'); ?></label><br />
                                                    <input type="checkbox" class="update" name="MSGS_default_yes" id="MSGS_default_yes" value="1" <?php if ($prefs['MSGS_default_yes']=='1') echo "checked='checked'"; ?>>
                                                    <label for="MSGS_default_yes" class="input-helper input-helper--checkbox" title="<?php echo xla('Default: Checked.  When checked, messages are processed for patients with Patient Demographic Choice (Phone/Text/Email) set to \'Unassigned\' or \'Yes\'.  If this is unchecked, a given type of message can only be sent if its Demographic Choice = \'Yes\'.'); ?>">
                                                        <?php echo xlt('Assume patients permit Messaging'); ?></label>
                                                </div>
                                            </div>
                                            <div class="divTableRow">
                                                <div class="divTableCell divTableHeading"><?php echo xlt('Process Reminders for Facility'); ?></div>
                                                <div class="divTableCell indent20">
                                                <?php
                                                $count="1";
                                                $query = "select * from facility";
                                                $result = sqlStatement($query);
                                                while ($fac = sqlFetchArray($result))   {
                                                    $checked ="";
                                                    if ($prefs) {
                                                        $facs = explode('|',$prefs['ME_facilities']);
                                                        foreach ($facs as $place) {
                                                            if ($place == $fac['id']) $checked = 'checked ="checked"';
                                                        }
                                                    }
                                                    ?>
                                                    <input <?php echo $checked; ?> class="update" type="checkbox" name="facilities[]" id="facility_<?php echo $fac['id']; ?>" value="<?php echo $fac['id']; ?>">
                                                    <label for="facility_<?php echo $fac['id']; ?>"><?php echo $fac['name']; ?></label><br /><?php
                                                }
                                                ?>
                                                </div>
                                            </div>
                                            <div class="divTableRow">
                                                <div class="divTableCell divTableHeading"><?php echo xlt('Process Reminders for Provider'); ?></div>
                                                <div class="divTableCell indent20">
                                                <?php
                                                $count="1";
                                                $ures = sqlStatement("SELECT * FROM users WHERE authorized != 0 AND active = 1 ORDER BY lname, fname");
                                                while ($prov = sqlFetchArray($ures))   {
                                                    $checked ="";
                                                    $suffix="";
                                                    if ($prefs) {
                                                        $provs = explode('|',$prefs['ME_providers']);
                                                        foreach ($provs as $doc) {
                                                            if ($doc == $prov['id']) $checked = 'checked ="checked"';
                                                        }
                                                    }
                                                    if (!empty($prov['suffix']))  $suffix = ', '.$prov['suffix'];
                                                    ?>
                                                    <input <?php echo $checked; ?> class="update" type="checkbox" name="providers[]" id="provider_<?php echo attr($prov['id']); ?>" value="<?php echo attr($prov['id']); ?>">
                                                    <label for="provider_<?php echo $prov['id']; ?>"><?php echo text($prov['fname'])." ".text($prov['lname']).text($suffix); ?></label><br /><?php
                                                }
                                                ?>
                                                </div>
                                            </div>
                                            <div class="divTableRow">
                                                <div class="divTableCell divTableHeading"><?php echo xlt('Labels'); ?></div>
                                                <div class="divTableCell indent20">
                                                    <input type="checkbox" class="update" name="LABELS_local" id="LABELS_local" value="1" <?php if ($prefs['LABELS_local']) echo "checked='checked'"; ?> />
                                                    <label for="LABELS_local" class="input-helper input-helper--checkbox" title='<?php echo xla('Check if you plan to use Avery Labels for Reminders or Recalls'); ?>'>
                                                    <?php echo xlt('Use Labels'); ?></label> 
                                                    <table id="LABELS_local_details" name="LABELS_local_details" class="<?php echo $show_local_only; ?>">
                                                        <tr>
                                                            <td colspan="2">
                                                                <?php echo xlt('Avery Label'); ?>: 
                                                                <select class="update" id="chart_label_type" name="chart_label_type">
                                                                    <option value='1' <?php if ($prefs['LABELS_choice'] == '1') echo "selected"; ?>>5160</option>
                                                                    <option value='2' <?php if ($prefs['LABELS_choice'] == '2') echo "selected"; ?>>5161</option>
                                                                    <option value='3' <?php if ($prefs['LABELS_choice'] == '3') echo "selected"; ?>>5162</option>
                                                                    <option value='4' <?php if ($prefs['LABELS_choice'] == '4') echo "selected"; ?>>5163</option>
                                                                    <option value='5' <?php if ($prefs['LABELS_choice'] == '5') echo "selected"; ?>>5164</option>
                                                                    <option value='6' <?php if ($prefs['LABELS_choice'] == '6') echo "selected"; ?>>8600</option>
                                                                    <option value='7' <?php if ($prefs['LABELS_choice'] == '7') echo "selected"; ?>>L7163</option>
                                                                    <option value='8' <?php if ($prefs['LABELS_choice'] == '8') echo "selected"; ?>>3422</option>
                                                                </select>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </div>
                                            </div>
                                            <div class="divTableRow">
                                                <div class="divTableCell divTableHeading"><?php echo xlt('Postcards'); ?></div>
                                                <div class="divTableCell indent20">
                                                <!--    
                                                    <input type="checkbox" class="update" name="POSTCARDS_local" id="POSTCARDS_local" value="1" <?php if ($prefs['POSTCARDS_local']) echo "checked='checked'"; ?>" />
                                                    <label for="POSTCARDS_local" name="POSTCARDS_local" class="input-helper input-helper--checkbox" title='<?php echo xla('Check if you plan to print postcards locally'); ?>'><?php echo xlt('Print locally'); ?></label><br />
                                                    <input type="checkbox" class="update" name="POSTCARDS_remote" id="POSTCARDS_remote" value="1" <?php if ($prefs['POSTCARDS_remote']) echo "checked='checked'"; ?>" />
                                                    <label for="POSTCARDS_remote" name="POSTCARDS_remote" class="input-helper input-helper--checkbox" title='<?php echo xla('Check if you plan to send postcards via MedEx'); ?>'><?php echo xlt('Print remotely'); ?></label>
                                                -->
                                                    <label for="postcards_top" title="<?php echo xla('Custom text for Flow Board postcards. After changing text, print samples before printing mass quantities!'); ?>"><?php echo xlt('Custom Greeting'); ?>:</label><br /><textarea rows=3 columns=30 id="postcard_top" name="postcard_top" class="update"><?php echo $prefs['postcard_top']; ?></textarea>
                                                </div>
                                            </div>
                                         <?php 
                                         /*      <!--    
                                            These options are for future use...
                                           
                                            <div class="divTableRow">
                                                <div class="divTableCell divTableHeading"><?php echo xlt('Combine Reminders'); ?></div>
                                                <div class="divTableCell indent20">
                                                    
                                                    <label for="combine_time" class="input-helper input-helper--checkbox" title='If a patient has two or more future appointments scheduled within X days, combine reminders.  eg. If you indicate "7" for this value, for a yearly physical with two appointments 3 days apart, or a surgical appointment with a follow-up 6 days post-op, these appointment reminds will be combined into one message, because they are less than "7" days apart.'>
                                                    for appts within <input type="text" class="flow_time update" name="combine_time" id="combine_time" value="<?php echo xla($prefs['combine_time']); ?>" /> <?php echo xlt('days of each other'); ?></label> 
                                                </div>
                                            </div>
                                            -->
                                        */   
                                        ?>
                                            <input type="hidden" name="ME_username" id="ME_username" value="<?php echo attr($prefs['ME_username']);?>" />
                                            <input type="hidden" name="ME_api_key" id="ME_api_key" value="<?php echo attr($prefs['ME_api_key']);?>" />
                                        </div>
                                    </div>
                                </div>
                               <div class="col-sm-1"></div>
                            </div>
                            <div style="clear:both;text-align:center;" id="msg bottom"><br />
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    public function display_recalls($logged_in) {
        global $MedEx;
        $recalls    = $this->get_recalls();
        // if all we don't use MedEx, there is no need to display the progress tabs, all recall processing is manual.
        if (!$logged_in) {
            $reminder_bar = "nodisplay";
            $events='';
        } else {
            $results = $MedEx->campaign->events($logged_in['token']);
            $events  = $results['events'];
            $reminder_bar = "indent20";
        }
        $processed = $this->recall_board_process($logged_in,$recalls,$events,$status);

        ob_start();
        ?>
      <div class="row">
        <div class="col-sm-12">
            <div class="showRecalls" id="show_recalls" style="text-align:center;margin:40 auto;">
                <?php if ($logged_in) {
                    foreach ($results['events'] as $event) {
                        if ($event['M_group'] != 'RECALL') continue;
                        $icon = $this->get_icon($event['M_type'],'SCHEDULED');
                        if ($event['E_timing'] =='1') $action = "before";
                        if ($event['E_timing'] =='2') $action = "before (PM)";
                        if ($event['E_timing'] =='3') $action = "after";
                        if ($event['E_timing'] =='4') $action = "after (PM)";
                        $current_events .=  $icon." ".$event['E_fire_time']." days ".$action."<br />";
                    }
                ?>
                <div class="borderShadow" style="position:absolute;top:20px;right:10px;width:200px;font-size:0.9em;">
                    <a class="fa fw fa-plus-square" title="<?php echo xla('Add a New Recall'); ?>" id="BUTTON_new_recall_menu" style="float:left;" href="<?php echo $GLOBALS['web_root']; ?>/interface/main/messages/messages.php?go=addRecall"></a>
                    <b><u>MedEx <?php echo xlt('Recall Schedule'); ?></u></b><br />
                    <span>
                        <?php echo $current_events; ?>
                    </span>
                </div>
                <?php } ?>
                <div class="title"><?php echo xlt('Recall Board'); ?></div>
                <div name="div_response" id="div_response"><?php echo xlt('Persons needing a recall, no appt scheduled yet'); ?>.</div>
                <div name="message" id="message" class="warning"></div>
                <ul class="nav nav-tabs <?php echo attr($reminder_bar); ?>">
                    <li class="active whitish"><a href="#tab-all" data-toggle="tab"><?php echo xlt('All'); ?></a></li>
                    <li class="whitish"><a href="#tab-pending" data-toggle="tab"><?php echo xlt('Events Scheduled'); ?></a></li>
                    <li class="yellowish"><a href="#tab-processing" data-toggle="tab"><?php echo xlt('In-process'); ?></a></li>
                    <li class="reddish"><a href="#tab-manual" data-toggle="tab"><?php echo xlt('Manual Processing Required'); ?></a></li>
                    <li class="greenish"><a href="#tab-complete" data-toggle="tab"><?php echo xlt('Recently Completed'); ?></a></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane active" id="tab-all">
                        <?php  
                            $this->recall_board_top($logged_in); 
                            echo $processed['ALL'];
                            $this->recall_board_bot($logged_in);
                        ?>
                    </div>
                    <?php if ($logged_in) { ?>        
                    <div class="tab-pane" id="tab-pending">
                        <?php  
                            $this->recall_board_top($logged_in); 
                            echo $processed['whitish'];
                            $this->recall_board_bot($logged_in);
                        ?>
                    </div>
                    <div class="tab-pane" id="tab-processing">
                        <?php  
                            $this->recall_board_top($logged_in); 
                            echo $processed['yellowish'];
                            $this->recall_board_bot($logged_in);
                        ?>
                    </div>
                    <div class="tab-pane" id="tab-complete">
                        <?php  
                            $this->recall_board_top($logged_in); 
                            echo $processed['greenish'];
                            $this->recall_board_bot($logged_in);
                        ?>
                    </div>
                    <div class="tab-pane" id="tab-manual">
                        <?php  
                            $this->recall_board_top($logged_in); 
                            echo $processed['reddish'];
                            $this->recall_board_bot($logged_in);
                        ?>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>
            <?php
        $content = ob_get_clean();
        echo $content;
    }
    private function recall_board_process($logged_in,$recalls,$events='',$status='') {
        $process = array();
        if (empty($recalls)) return;
        foreach ($recalls as $recall) {     
            $show = $this->show_progress_recall($recall,$events);
            if (!empty($show['DONE'])) continue;
            if (empty($show['status'])) $show['status'] = 'whitish';
            ob_start();
            echo '<div class="divTableRow ALL '.$show['status'].' '.$show['plan'].'" id="remind_'.$recall['pid'].'">';
            echo '<div class="divTableCell center"><a href="#" onclick="show_patient(\''.$recall['pid'].'\');"> '.$recall['fname'].' '.$recall['lname'].'</a></div>';

            echo '<div class="divTableCell appt_date">'.oeFormatShortDate($recall['r_eventDate']);
            if ($recall['r_reason']>'') echo '<br />'.$recall['r_reason'];
            echo '</div>';
            echo '<div class="divTableCell phones">';
            if ($recall['phone_cell'] >'') {
                echo 'C: '.$recall['phone_cell']."<br />";
            } 
            if ($recall['phone_home'] >'') {
                echo 'H: '.$recall['phone_home']."<br />";
            }
            if ($recall['email'] >'') {
                $mailto = $recall['email'];
                if (strlen($recall['email']) > 15) {
                    $recall['email'] = substr($recall['email'], 0, 12)."...";
                }
                echo 'E: <a title="'.xla('Send an email to ').attr($mailto).'" href="mailto:'.attr($mailto).'">'.text($recall['email']).'</a><br />';
            }
            if ($logged_in) { 
                $pat = $this->possibleModalities($recall);
                echo $pat['SMS'].$pat['AVM'].$pat['EMAIL'];
            }
            echo '</div>';
           
            if ($show['postcard'] > '') {
                echo '<div class="divTableCell center postcards">'.$show['postcard'].'</div>';
            } else {
                echo '<div class="divTableCell center postcards"><input type="checkbox" name="postcards" id="postcards[]" value="'.attr($recall['pid']).'"></div>';
            }
        
            if ($show['label'] > '') {
                echo '<div class="divTableCell center labels">'.$show['label'].'</div>';
            } else {
                echo '<div class="divTableCell center labels"><input type="checkbox" name="labels" id="labels[]" value="'.attr($recall['pid']).'"></div>';
            }
            echo '  <div class="divTableCell center msg_manual"><span class="fa fa-fw spaced_icon" >
                    <input type="checkbox" name="msg_phone" id="msg_phone_'.$recall['pid'].'" onclick="process_this(\'phone\',\''.attr($recall['pid']).'\',\''.attr($recall['r_ID']).'\')" />
                </span>'; 
            echo '    <span title="'.xla('Scheduling').'" class="fa fa-calendar-check-o fa-fw" onclick="newEvt(\''.attr($recall['pid']).'\',\'\');">
                </span>';
            echo '</div>';

            echo '  <div class="divTableCell left msg_resp">';
                //    if phone all made show each info from progress
            echo '<textarea onblur="process_this(\'notes\',\''.attr($recall['pid']).'\',\''.attr($recall['r_ID']).'\');" name="msg_notes" id="msg_notes_'.attr($recall['pid']).'" style="width:90%;height:30px;">'.nl2br(text($recall['NOTES'])).'</textarea>';
            echo '</div>';
            echo '  <div class="divTableCell left msg_resp">
            <i class="top_right_corner fa fa-times" onclick="delete_Recall(\''.attr($recall['pid']).'\',\''.attr($recall['r_ID']).'\')"></i> ';
            echo $show['progression'];
                
            if ($show['appt']) echo "<span  onclick=\"newEvt('".attr($prog['pid'])."','".attr($show['pc_eid'])."');\" class='btn btn-danger center' title='".xla('Appointment made by')." ".attr($prog['who'])." ".xla('on')." ".attr($prog['when'])."'><b>".xlt('Appt{{Appointment}}').":</b> ".text($show['appt'])."<br />";
            echo '</div>';
            echo '</div>';
            $content = ob_get_clean();
            $process[$show['status']] .= $content;
            $process['ALL'] .= $content;
        }
        return $process;
    }
    private function recall_board_top($logged_in) {
        ?>
        <div class="divTable" style="text-align:center;margin:0px auto 30px;width:100%;">
            <div class="divTableBody">   
                <div class="sticky divTableRow divTableHeading">
                    <div class="divTableCell center" style="width:10%;"><?php echo xlt('Name'); ?></div>
                    <div class="divTableCell center" style="width:10%;"><?php echo xlt('Recall Date'); ?></div>
                    
                    <div class="divTableCell center phones" style="width:10%;"><?php echo xlt('Contacts'); ?></div>
                    <div class="divTableCell center msg_resp"><?php echo xlt('Postcards'); ?><br />
                        <span onclick="top.restoreSession();checkAll('postcards',true);" class="fa fa-square-o fa-lg" id="chk_postcards"></span>
                        &nbsp;&nbsp;
                        <span onclick="process_this('postcards');" class="fa fa-print fa-lg"></span>
                    </div>
                    <div class="divTableCell center msg_resp"><?php echo xlt('Labels'); ?><br />
                        <span onclick="checkAll('labels',true);" class="fa fa-square-o fa-lg" id="chk_labels"></span>
                                                &nbsp;&nbsp;
                        <span onclick="process_this('labels');" class="fa fa-print fa-lg"></span>
                    </div>
                    <div class="divTableCell center msg_resp"><?php echo xlt('Office: Phone'); ?></div>
                    <div class="divTableCell center msg_notes"><?php echo xlt('Notes'); ?></div>
                    <div class="divTableCell center msg_notes"><?php echo xlt('Progress'); ?></div>
                </div>
        <?php
    }
    private function recall_board_bot($logged_in) {
        ?>
            </div>
        </div><?php 
    }
    public function get_recalls($facilities='all',$duration='1200') {
        // Recalls are requests to schedule a future appointment.  
        // Thus there is no r_appt_time (NULL) but there is a DATE set.  
        // Get recalls for today and the future.
        // Will build mechanism for user to narrow date range, or pagination, so as not to display EVERYTHING.
        $query = "Select * from medex_recalls,patient_data as pat where pat.pid=medex_recalls.r_pid and r_eventDate < CURDATE()+INTERVAL ".add_escape_custom($duration)." DAY order by r_eventDate ASC";
        $result = sqlStatement($query);
        while ($recall= sqlFetchArray($result))   {
            $recalls[]=$recall; 
        }
        return $recalls;
    }
    public function possibleModalities($appt) {
        $pat = array();
        $sqlQuery = "select * from medex_icons";
        $result = sqlStatement($sqlQuery);
        while ($icons = sqlFetchArray($result)) {
            $icon[$icons['msg_type']][$icons['msg_status']] = $icons['i_html'];
        }
        if (empty($appt['phone_cell']) || ($appt["hipaa_allowsms"]=="NO")) {
                $pat['SMS'] = $icon['SMS']['NotAllowed'];
                $pat['ALLOWED']['SMS'] = 'NO';
        } else {
                $phone = preg_replace("/[^0-9]/", "", $appt["phone_cell"]); 
                $pat['SMS'] = $icon['SMS']['ALLOWED'];     // It is allowed and they have a cell phone
        }
        if  ((empty($appt["phone_home"]) && (empty($appt["phone_cell"])) || ($appt["hipaa_voice"]=="NO"))) {
                $pat['AVM'] = $icon['AVM']['NotAllowed'];
                $pat['ALLOWED']['AVM'] = 'NO';
        } else {
                if (!empty($appt["phone_cell"])) { 
                    $phone = preg_replace("/[^0-9]/", "", $appt["phone_cell"]); 
                } else { 
                    $phone = preg_replace("/[^0-9]/", "", $appt["phone_home"]); 
                }
                $pat['AVM'] = $icon['AVM']['ALLOWED']; //We have a phone to call and permission!
            }
        if (($appt["email"]=="")||($appt["hipaa_allowemail"]=="NO")) {
                $pat['EMAIL'] = $icon['EMAIL']['NotAllowed'];
                $pat['ALLOWED']['EMAIL'] = 'NO';
            } else {
                $pat['EMAIL'] = $icon['EMAIL']['ALLOWED'];
            }
        return $pat;
    }
    public function display_add_recall($pid='new') {
        global $result_pat;
        ?>
        <div class="row">
            <div class="col-sm-2"></div>
            <div class="col-sm-8">

                <div id="add_recall" class="showReminders">
                    <div class="title"><?php echo xlt('New Recall'); ?></div>
                    <div name="div_response" id="div_response"><?php echo xlt('Create a reminder to schedule a future visit'); ?> .</div>
                    <div id="add_recall">
                        <form name="addRecall" id="addRecall">
                            <input type="hidden" name="go" id="go" value="addRecall">
                            <input type="hidden" name="action" id="go" value="addRecall">
                            <div class="newRecall float_left" >
                                <div class="divTableBody">
                                    <div class="divTableRow">
                                        <div class="divTableCell divTableHeading"><?php echo xlt('Name'); ?></div>
                                        <div class="divTableCell recall_name">
                                            <input type="text" name="new_recall_name" id="new_recall_name" 
                                                onclick="recall_name_click(this)" 
                                                value="<?php echo attr($result_pat['fname'])." ".attr($result_pat['lname']); ?>">
                                            <input type="hidden" name="new_pid" id="new_pid" value="<?php echo attr($result_pat['id']); ?>">
                                        </div>
                                    </div>
                                    <div class="divTableRow">
                                        <div class="divTableCell divTableHeading"><?php echo xlt('Recall When'); ?></div>
                                        <div class="divTableCell indent20">
                                             <span class="bold"><?php echo xlt('Last Visit'); ?>: </span><input type="text" value="" name="DOLV" id="DOLV">
                                            <br />
                                            <!-- Feel free to add in any dates you would like to show here...
                                            <input type="radio" name="new_recall_when" id="new_recall_when_6mos" value="180">
                                            <label for="new_recall_when_6mos" class="input-helper input-helper--checkbox">+ 6 months</label><br />
                                            -->
                                            <input type="radio" name="new_recall_when" id="new_recall_when_1yr" value="365">
                                            <label for="new_recall_when_1yr" class="input-helper input-helper--checkbox"><?php echo xlt('Plus 1 year'); ?></label><br />
                                            <input type="radio" name="new_recall_when" id="new_recall_when_2yr" value="730">
                                                <label for="new_recall_when_2yr" class="input-helper input-helper--checkbox"><?php echo xlt('Plus 2 years'); ?></label><br />
                                            <input type="radio" name="new_recall_when" id="new_recall_when_3yr" value="1095">
                                                <label for="new_recall_when_3yr" class="input-helper input-helper--checkbox"><?php echo xlt('Plus 3 years'); ?></label><br />
                                                <span class="bold"> <?php echo xlt('Date'); ?>:</span> <input type="text" id="datepicker2" name="datepicker2" value="" style="width:100px;">

                                        </div>
                                    </div>
                                    <div class="divTableRow">
                                        <div class="divTableCell divTableHeading"><?php echo xlt('Recall Reason'); ?></div>
                                        <div class="divTableCell">
                                            <input type="text" name="new_reason" id="new_reason" value="<?php echo attr($result_pat['PLAN']); ?>">
                                        </div>
                                    </div>
                                    <div class="divTableRow">
                                        <div class="divTableCell divTableHeading"><?php echo xlt('Provider'); ?></div>
                                        <div class="divTableCell">
                                            <?php 
                                            $ures = sqlStatement("SELECT id, username, fname, lname FROM users WHERE authorized != 0 AND active = 1 ORDER BY lname, fname");
                                            $defaultProvider = $_SESSION['authUserID'];
                                            // or, if we have chosen a provider in the calendar, default to them
                                            // choose the first one if multiple have been selected
                                            if (count($_SESSION['pc_username']) >= 1) {
                                                // get the numeric ID of the first provider in the array
                                                $pc_username = $_SESSION['pc_username'];
                                                $firstProvider = sqlFetchArray(sqlStatement("select id from users where username=?", array($pc_username[0]) ));
                                                $defaultProvider = $firstProvider['id'];
                                            }
                                            // if we clicked on a provider's schedule to add the event, use THAT.
                                            if ($userid) $defaultProvider = $userid;
                                            
                                            echo "<select name='new_provider' id='new_provider' style='padding:4px;' />";
                                            while ($urow = sqlFetchArray($ures)) {
                                                echo "    <option value='" . attr($urow['id']) . "'";
                                                if ($urow['id'] == $defaultProvider) echo " selected";
                                                echo ">" . text($urow['lname']);
                                                if ($urow['fname']) echo ", " . text($urow['fname']);
                                                echo "</option>\n";
                                            }
                                            echo "</select>";
                                        ?>
                                        </div>
                                    </div>
                                    <div class="divTableRow">
                                        <div class="divTableCell divTableHeading"><?php echo xlt('Facility'); ?></div>
                                        <div class="divTableCell">
                                            <select name="new_facility" id="new_facility" style="padding: 4px;">
                                                <?php
                                                    $qsql = sqlStatement("SELECT id, name, primary_business_entity FROM facility WHERE service_location != 0");
                                                    while ($facrow = sqlFetchArray($qsql)) {
                                                       if ($facrow['primary_business_entity'] == '1') {
                                                            $selected = 'selected="selected"';
                                                            echo "<option value='" . attr($facrow['id']) . "' $selected>" . text($facrow['name']) . "</option>";
                                                        } else {
                                                            $selected = '';
                                                            echo "<option value='" . attr($facrow['id']) . "' $selected>" . text($facrow['name']) . "</option>";
                                                        }
                                                    }
                                                  ?>
                                              </select>
                                        </div>
                                    </div>

                                </div>
                            </div>

                            <div class="newRecall float_right">
                                <div class="divTableBody">
                                    <div class="divTableRow news">
                                        <div class="divTableCell divTableHeading"><?php echo xlt('DOB'); ?></div>
                                        <div class="divTableCell">&nbsp;&nbsp;
                                        <?php 
                                        $DOB = oeFormatShortDate($result_pat['DOB']);
                                        ?>
                                        <span name="new_DOB" id="new_DOB" style="width:90px;"><?php echo $DOB; ?></span> - 
                                             <span id="new_age" name="new_age"><?php echo $result_pat['age']; ?></span></div>
                                    </div>
                                    <div class="divTableRow news">
                                        <div class="divTableCell divTableHeading"><?php echo xlt('Address'); ?></div>
                                        <div class="divTableCell">
                                            <input type="text" name="new_address" id="new_address" style="width:200px;" value="<?php echo attr($result_pat['street']); ?>"><br />
                                            <input type="text" name="new_city" id="new_city" style="width:100px;" value="<?php echo attr($result_pat['city']); ?>">
                                            <input type="text" name="new_state" id="new_state" style="width:30px;" value="<?php echo attr($result_pat['state']); ?>">
                                            <input type="text" name="new_postal_code" id="new_postal_code" style="width:50px;" value="<?php echo attr($result_pat['postal_code']); ?>"></div>
                                    </div>
                                    <div class="divTableRow news">
                                        <div class="divTableCell divTableHeading phone_home"><?php echo xlt('Home Phone'); ?></div>
                                        <div class="divTableCell"><input type="text" name="new_phone_home" id="new_phone_home" style="width:200px;" value="<?php echo attr($result_pat['phone_home']); ?>"></div>
                                    </div>
                                    <div class="divTableRow news">
                                        <div class="divTableCell divTableHeading phone_cell"><?php echo xlt('Mobile Phone'); ?></div>
                                        <div class="divTableCell"><input type="text" name="new_phone_cell" id="new_phone_cell" style="width:200px;" value="<?php echo attr($result_pat['phone_cell']); ?>"></div>
                                    </div>
                                    <div class="divTableRow news">
                                        <div class="divTableCell divTableHeading msg_sent" title="Text Message"><?php echo xlt('SMS OK'); ?></div>
                                
                                        <div class="divTableCell indent20">
                                            <input type="radio" name="new_allowsms" id="new_allowsms_yes" value="YES"> <label for="new_allowsms_yes"><?php echo xlt('YES'); ?></label>
                                            &nbsp;&nbsp; 
                                            <input type="radio" name="new_allowsms" id="new_allowsms_no" value="NO"> <label for="new_allowsms_no"><?php echo xlt('NO'); ?></label>
                                        </div>
                                    </div>
                                    <div class="divTableRow indent20">
                                        <div class="divTableCell divTableHeading msg_how" title="Automated Voice Message"><?php echo xlt('AVM OK'); ?></div>
                                        <div class="divTableCell indent20">
                                            <input type="radio" name="new_voice" id="new_voice_yes" value="YES"> <label for="new_voice_yes"><?php echo xlt('YES'); ?></label>
                                            &nbsp;&nbsp; 
                                            <input type="radio" name="new_voice" id="new_voice_no" value="NO"> <label for="new_voice_no"><?php echo xlt('NO'); ?></label>
                                        </div>
                                    </div>
                                    <div class="divTableRow news">
                                        <div class="divTableCell divTableHeading phone_cell"><?php echo xlt('E-Mail'); ?></div>
                                        <div class="divTableCell"><input type="text" name="new_email" id="new_email" style="width:200px;" value="<?php echo attr($result_pat['email']); ?>"></div>
                                    </div>
                                    
                                    <div class="divTableRow news">
                                        <div class="divTableCell divTableHeading msg_when"><?php echo xlt('E-mail OK'); ?></div>
                                        <div class="divTableCell indent20">
                                            <input type="radio" name="new_email_allow" id="new_email_yes" value="YES"> <label for="new_email_yes"><?php echo xlt('YES'); ?></label>
                                            &nbsp;&nbsp; 
                                            <input type="radio" name="new_email_allow" id="new_email_no" value="NO"> <label for="new_email_no"><?php echo xlt('NO'); ?></label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="clear center" style="width:100%;clear:both;font-size:1.1em;">
                                
                                <input class="ui-buttons ui-widget ui-corner-all news btn" onclick="add_this_recall();"  style="width:100px;" value="<?php echo xla('Add Recall'); ?>" id="add_new" name="add_new">
                                <br />
                                <em>* <?php echo xlt('N.B.{{Nota bene}}')." ".xlt('Demographic changes made here are recorded system-wide'); ?>.</em>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-sm-2"></div>
        </div>
        <?php
    }
    /**
     *   This function looks at a single recall and assesses its status.
     *   Has it been worked on yet?  Any phone calls made, labels printed or postcards printed?
     *   If they are a MedEx subscriber, do they have any scheduled Recall Campaign Events and if so when?
     *   Have any of these MedEx events happened?  Given all the variables, what is the status of this recall at this moment?
     *   We also use color coding in the Recall Board - 
     *       -- whitish for pending, nothing happed yet.
     *       -- yellowish for in process, something happened but no appointment was made yet!
     *       -- reddish for manual processing needed.  This only applies to MedEx subscribers - all the other recalls are already manual, hence whitish for them.
     *       -- greenish for completed and an appointment was made.  
     *   In the ideal workflow, the secretary would be going through the Recall Board line by line once per day/week/month (per practice needs).  
     *       When they call a patient and make the appointment (by clicking the  Calendar icon)
     *       They can then just delete the Recall by clicking the X on the right.  Done move on to the next.
     *       However a patient may have called in randomly to make this appointment - the secretary may be unaware that the Recall even exists.
     *       In this work-flow, the secretary does not have to open the Recall Board, nor should they waste their time when we can do it for them...
     *       Let the Recall Board look to see if there is an appointment for this patient in the future.  
     *       If there is, and it was made more than 16 hours ago, it deletes the Recall altogether.  It is never displayed again.
     *       If an appointment was created less than 16 hours ago, it turns the row greenish, signaling anyone who looks that it was just taken care of,
     *       and display in the "progress" column what has transpired.
     *       Thanks, move on, nothing to see here.  
     *       This also allows anyone (management?) a tool to see what Recall work was performed over the last 16 hours.
     */
    public function show_progress_recall($recall,$events='',$possibleModalities='') {
        //Two scenarios: First, appt is made as recall asks. Second, appt is made not for recall reason - recall still needed.
        //We can either require all recalls to be manually deleted or do some automatically...  If manual only,
        //the secretary looking at the board will need to know when they were last seen at least and when next appt is
        //to know if they can/should delete the recall.  If semi-automatic, we'll use an artificial time horizon of 3 months.
        //If an appt is made through any means, and it is within 3 months of the recall date, assume it wipes out the recall.
        //If the appt was just made today, let the board show it as "green", ie. completed.  Gives us a sense of accomplishment,
        //that we got some work done today...
        //So, if appt was made more than 16 hours ago, and it is within 3 months of the recall date, auto-delete the recall from the board.
        //ie.  appts added in for problem visits won't auto-delete an official recall unless they are close in time to the recall...
        //Adjust according to your needs and work flows.  This function is run by the Recall Board and with cron MedEx calls.
        $show['EMAIL']['text']='';
        $show['SMS']['text']='';
        $show['AVM']['text']='';
        $show['progression']='';
        $show['DONE']='';
        
        $query = "select * from openemr_postcalendar_events WHERE 
                  pc_eventDate > CURDATE() and pc_pid =? and pc_eventDate > ? - INTERVAL 90 DAY  and pc_time <  CURDATE()- INTERVAL 16 HOUR"; 
        $count = sqlFetchArray(sqlStatement($query,array($recall['r_pid'],$recall['r_eventDate'])));
        if ($count) {
            $sqlDELETE = "DELETE from medex_outgoing where msg_pc_eid = ?";
            sqlStatement($sqlDELETE,array('recall_'.$recall['pid']));
            $sqlDELETE = "DELETE from medex_recalls where r_pid = ?";
            sqlStatement($sqlDELETE,array($recall['pid']));
            //log this action "Recall for $pid deleted now()"
            $show['DONE'] ='1';//tells recall board to move on.
            $show['status'] ='greenish'; //tells MedEx to move on, don't process this recall - delete it from their servers.
            return $show; 
            // Just cleaning up the Recall Board for you. Move along, nothing to see.  
            // If you need to look at the track of action, look in the log?
        }

        // Did anything happen yet? 
        // Table medex_outgoing is our log for current activities.
        // It includes records of local manual things your office did and the MedEx reports.
        // For non-MedEx subscribers, the local functionality will still work just fine...
        // Unless the RECALL is completed (appt made) more than 16 hours ago, the RECALL's data will be present.
        
        // We need to output the correct text and icon to visually display the appt status

        $sql ="SELECT * from medex_outgoing where msg_pc_eid = ?  order by msg_date asc";
        $result = sqlStatement($sql,array('recall_'.$recall['pid']));
        $something_happened='';
        while ($progress = sqlFetchArray($result)) {
            $i = $progress['campaign_uid'];//if this is a manual entry, this ==0.
            
            $phpdate = strtotime( $progress['msg_date'] );
            $when = oeFormatShortDate(date('Y-m-d',$phpdate))." @ ".date('g:iA',$phpdate);

            if (is_numeric($progress['msg_reply'])) { // it was manually added by id
                $sql2 = "SELECT * from users where id =?";
                $who  = sqlQuery($sql2,array($progress['msg_reply']));
                $who_name = $who['fname']." ".$who['lname'];
                //Manually generated actions
                if ($progress['msg_type'] == 'phone') { //ie. a manual phone call, not an AVM
                    $show['progression'] .= "<span class='left' title='".xla('Phone call made by')." ".text($who_name)."'><b>".xlt('Phone')."</b> ".text($when)."</span></br />\n";
                } elseif ($progress['msg_type'] == 'notes') {
                    $show['progression'] .= "<span class='left' title='".xla('Notes by')." ".text($who_name)." on ".text($when)."'><b>".xlt('Note').":</b> ".text($progress['msg_extra_text'])."</span></br />\n";
                } elseif ($progress['msg_type'] == 'postcards') {
                    $show['progression'] .= "<span class='left' title='".xla('Postcard printed by')." ".text($who_name)."'><b>".xlt('Postcard').":</b> ".text($when)."</span></br />\n";
                } elseif ($progress['msg_type'] == 'labels') {
                    $show['progression'] .= "<span class='left' title='".xla('Label printed by')." ".text($who)."'><b>".xlt('Label').":</b> ".text($when)."</span></br />";
                }
            } else {
                $who_name = "MedEx";
                // MedEx related actions
                // Recalls can't be confirmed...  They disappear 16 hours after an appt is made (they glow green for those 16 hours).  
                if (($progress['msg_reply'] == "READ")||($show[$progress['msg_type']]['stage']=="READ")) {
                    $show[$progress['msg_type']]['stage']   = "READ";
                    $icon = $this->get_icon($progress['msg_type'],"READ");
                    $show[$progress['msg_type']]['text']    = "<span class='left'>".$icon." ".text($when)."</span><br />";
                    if ($progress['msg_type'] == 'AVM') $show['campaign'][$i]['status']="reddish";
                } elseif (($progress['msg_reply'] == "SENT")||($show[$progress['msg_type']]['stage']=="SENT")) {
                    if ($show[$progress['msg_type']]['stage']!="READ") {
                        $show[$progress['msg_type']]['stage']   = "SENT";
                        $icon = $this->get_icon($progress['msg_type'],"SENT");
                        $show[$progress['msg_type']]['text']    = "<span class='left'>".$icon." ".text($when)."</span><br />";
                    }
                } elseif (($progress['msg_reply'] == "To Send")||($show[$progress['msg_type']]['stage']=="QUEUED")) {
                    if (($show[$progress['msg_type']]['stage']!="READ")&&($show[$progress['msg_type']]['stage']!="SENT")) {
                        $show[$progress['msg_type']]['stage']   = "QUEUED";
                        $icon = $this->get_icon($progress['msg_type'],$progress['msg_reply']);
                    }
                } 
                if ($progress['msg_reply'] == "CALL") { 
                    $icon = $this->get_icon($progress['msg_type'],"CALL");
                    $show['progression'] .= "<span class='left'>".$icon." ".text($progress['msg_type'])."@".text($when)."</span><br />";
                } elseif ($progress['msg_reply'] == "STOP") { 
                    $icon = $this->get_icon($progress['msg_type'],"STOP");
                    $show['progression'] .= "<span class='left'>".$icon." ".text($when)."</span><br />";
                } elseif ($progress['msg_reply'] == "EXTRA") { 
                    $icon = $this->get_icon($progress['msg_type'],"EXTRA");
                    $show['progression'] .= "<span class='left'>".$icon." ".text($when)."</span><br />";
                } elseif ($progress['msg_reply'] == "FAILED") { 
                    $icon = $this->get_icon($progress['msg_type'],"FAILED");
                    $show['progression']  .= "<span class='left'>".$icon." ".text($when)."</span><br />";
                    $show['campaign'][$i]['status']=1;
                }
                $show['campaign'][$i]['icon'] = $icon;
            }
            $something_happened=true;
        }
        $show['progression'] .= $show['EMAIL']['text'].$show['SMS']['text'].$show['AVM']['text'];
        // Let's look at the MedEx events: 
        // Show the DATE when a Campaign event will be run for a given patient 
        /*
         * E_fire_tire = number of days before/after recall date that a MedEx campaign event will run
         * MedEx E_timing options:
         * 1 = days before
         * 2 = days before PM
         * 3 = days after
         * 4 = days after PM
         */
        $camps='0';
        foreach ($events as $event) { 
            if ($event['M_group'] != "RECALL") continue;
            $pat = $this->possibleModalities($recall);
            if ($pat['ALLOWED'][$event['M_type']] == 'NO') continue;    //it can't happen
            if ($show['campaign'][$event['C_UID']]['status']) continue; //it is done
            $camps++;                                                   //there is still work to be done
            if ($show['campaign'][$event['C_UID']]['icon']) continue;   //but something has happened since it was scheduled.
             
            ($event['E_timing'] < '3') ? ($interval ='-') : ($interval ='+');//this is only scheduled, no action yet...
            $show['campaign'][$event['C_UID']] = $event;
            $show['campaign'][$event['C_UID']]['icon'] = $this->get_icon($event['M_type'],"SCHEDULED");
            
            $recall_date = date("Y-m-d",strtotime($interval.$event['E_fire_time']." days",strtotime($recall['r_eventDate'])));
            $date1 = date('Y-m-d');
            $date_diff=strtotime($date1) - strtotime($recall['r_eventDate']);
            if ($date_diff >= '-1') { //if it is sched for tomorrow or earlier, queue it up
                $show['campaign'][$event['C_UID']]['executed'] = "QUEUED";
                $show['status'] = "whitish"; 
            } else {
                $execute = oeFormatShortDate($recall_date);
                $show['campaign'][$event['C_UID']]['executed'] = $execute;
            }
            $show['progression'] .= "<a href='https://medexbank.com/cart/upload/index.php?route=information/campaigns' class='nowrap left' target='_MedEx'>".
                                    $show['campaign'][$event['C_UID']]['icon']." ".text($show['campaign'][$event['C_UID']]['executed'])."</a><br />";
        }
        
        // Show recall row status via background color.  
        // If an appt was made < 16hrs ago, make it green(completed) and $show['DONE'] = 1 
        //   o/w yellow(in progress) if something happened or Campaign fired
        //   o/w red (manual needed) if no more MedEx Recall Events are scheduled to be done and no appt was made yet. 
        //      ie. we struck out and need to process this manually 
        //      or write it off or delete it or do soemthing else?  Have to know what to do to write that. ;)
        $query  = "select * from openemr_postcalendar_events WHERE pc_eventDate > CURDATE() and pc_pid =? and pc_time >  CURDATE()- INTERVAL 16 HOUR";
        $result = sqlFetchArray(sqlStatement($query,array($recall['pid'])));
        if ($something_happened||$result) {
            if ($result) {
                $show['status'] = "greenish"; //appt made, move on
                $phpdate = strtotime( $result['pc_eventDate']." ".$result['pc_startTime'] );
                $show['pc_eid'] = $result['pc_eid'];
                $show['appt'] = oeFormatShortDate(date('Y-m-d',$phpdate))." @ ".date('g:iA',$phpdate);
                $show['DONE'] = '1';
            } else {
                if ($camps =='0')  {
                    $show['status'] = "reddish"; //hey, nothing automatic left to do - manual processing required.
                } else {
                    $show['status'] = "yellowish"; //no appt yet but something happened!
                }
            }
        } elseif ($camps =='0')  {
                    $show['status'] = "reddish"; //hey, nothing automatic left to do - manual processing required.
        }
        return $show;    
    }
    private function get_icon($event_type,$status='SCHEDULED') {
        $sqlQuery = "select * from medex_icons";
        $result = sqlStatement($sqlQuery);
        while ($icons = sqlFetchArray($result)) {
            if (($icons['msg_type'] == $event_type)&&($icons['msg_status'] == $status)) {
                return $icons['i_html'];
            }
        }
        return false;
    }
    public function icon_template() {
        ?>
        <!-- icon rubric -->
          <div style="position:relative;display: inline-block;margin:30px auto;vertical-align:middle;">
                <?php
                $sqlQuery = "SELECT * from medex_icons order by msg_type";
                $result  = sqlStatement($sqlQuery);
                $icons = array();
                while ($urow = sqlFetchArray($result)) {
                    $icons['msg_type']['description'] = $urow['i_description'];
                    $icons[$urow['msg_type']][$urow['msg_status']]  = $urow['i_html'];
                } ?>
                <div class="divTable" style="text-align:center;margin:30px auto 30px;width:100%;">
                  <div class="divTableBody">   
                    <div class="divTableRow divTableHeading">
                      <div class="divTableCell center"><?php echo xlt('Message'); ?></div>
                      <div class="divTableCell center"><?php echo xlt('Possible'); ?></div>
                      <div class="divTableCell center"><?php echo xlt('Not Possible'); ?></div>
                      <div class="divTableCell center"><?php echo xlt('Scheduled'); ?></div>
                      <div class="divTableCell center"><?php echo xlt('Sent')."<br />".xlt('In-process'); ?></div>
                      <div class="divTableCell center"><?php echo xlt('Read')."<br />".xlt('Done');; ?></div>
                      <div class="divTableCell center"><?php echo xlt('Confirmed'); ?></div>
                      <div class="divTableCell center"><?php echo xlt('Callback'); ?></div>
                      <div class="divTableCell center"><?php echo xlt('Failure'); ?></div>
                      <div class="divTableCell center"><?php echo xlt('Extras'); ?></div>
                      <div class="divTableCell center"><?php echo xlt('STOP'); ?></div>
                    </div>
                    <div class="divTableRow">
                      <div class="divTableCell center"><?php echo xlt('EMAIL'); ?></div>
                      <div class="divTableCell center"><?php echo $icons['EMAIL']['ALLOWED']; ?></div>
                      <div class="divTableCell center"><?php echo $icons['EMAIL']['NotAllowed']; ?></div>
                      <div class="divTableCell center"><?php echo $icons['EMAIL']['SCHEDULED']; ?></div>
                      <div class="divTableCell center"><?php echo $icons['EMAIL']['SENT']; ?></div>
                      <div class="divTableCell center"><?php echo $icons['EMAIL']['READ']; ?></div>
                      <div class="divTableCell center"><?php echo $icons['EMAIL']['CONFIRMED']; ?></div>
                      <div class="divTableCell center"><?php echo $icons['EMAIL']['CALL']; ?></div>
                      <div class="divTableCell center"><?php echo $icons['EMAIL']['FAILURE']; ?></div>
                      <div class="divTableCell center"><?php echo $icons['EMAIL']['EXTRA']; ?></div>
                      <div class="divTableCell center"><?php echo $icons['EMAIL']['STOP']; ?></div>
                    </div>
                    <div class="divTableRow">
                      <div class="divTableCell center"><?php echo xlt('SMS'); ?></div>
                      <div class="divTableCell center"><?php echo $icons['SMS']['ALLOWED']; ?></div>
                      <div class="divTableCell center"><?php echo $icons['SMS']['NotAllowed']; ?></div>
                      <div class="divTableCell center"><?php echo $icons['SMS']['SCHEDULED']; ?></div>
                      <div class="divTableCell center"><?php echo $icons['SMS']['SENT']; ?></div>
                      <div class="divTableCell center"><?php echo $icons['SMS']['READ']; ?></div>
                      <div class="divTableCell center"><?php echo $icons['SMS']['CONFIRMED']; ?></div>
                      <div class="divTableCell center"><?php echo $icons['SMS']['CALL']; ?></div>
                      <div class="divTableCell center"><?php echo $icons['SMS']['FAILURE']; ?></div>
                      <div class="divTableCell center"><?php echo $icons['SMS']['EXTRA']; ?></div>
                      <div class="divTableCell center"><?php echo $icons['SMS']['STOP']; ?></div>
                    </div>
                    <div class="divTableRow">
                      <div class="divTableCell center"><?php echo xlt('AVM'); ?></div>
                      <div class="divTableCell center"><?php echo $icons['AVM']['ALLOWED']; ?></div>
                      <div class="divTableCell center"><?php echo $icons['AVM']['NotAllowed']; ?></div>
                      <div class="divTableCell center"><?php echo $icons['AVM']['SCHEDULED']; ?></div>
                      <div class="divTableCell center"><?php echo $icons['AVM']['SENT']; ?></div>
                      <div class="divTableCell center"><?php echo $icons['AVM']['READ']; ?></div>
                      <div class="divTableCell center"><?php echo $icons['AVM']['CONFIRMED']; ?></div>
                      <div class="divTableCell center"><?php echo $icons['AVM']['CALL']; ?></div>
                      <div class="divTableCell center"><?php echo $icons['AVM']['FAILURE']; ?></div>
                      <div class="divTableCell center"><?php echo $icons['AVM']['EXTRA']; ?></div>
                      <div class="divTableCell center"><?php echo $icons['AVM']['STOP']; ?></div>
                    </div>
                    <?php 
                    //When we have added PostCards to MedEx, we can display this.  
                    //Until then this would just add confusion.
                /*
                    <div class="divTableRow">
                      <div class="divTableCell center"><?php echo xlt('POSTCARD'); ?></div>
                      <div class="divTableCell center"><?php echo $icons['POSTCARD']['ALLOWED']; ?></div>
                      <div class="divTableCell center"><?php echo $icons['POSTCARD']['NotAllowed']; ?></div>
                      <div class="divTableCell center"><?php echo $icons['POSTCARD']['SCHEDULED']; ?></div>
                      <div class="divTableCell center"><?php echo $icons['POSTCARD']['SENT']; ?></div>
                      <div class="divTableCell center"><?php echo $icons['POSTCARD']['READ']; ?></div>
                      <div class="divTableCell center"><?php echo $icons['POSTCARD']['CONFIRMED']; ?></div>
                      <div class="divTableCell center"><?php echo $icons['POSTCARD']['CALL']; ?></div>
                      <div class="divTableCell center"><?php echo $icons['POSTCARD']['FAILURE']; ?></div>
                      <div class="divTableCell center"><?php echo $icons['POSTCARD']['EXTRA']; ?></div>
                      <div class="divTableCell center"><?php echo $icons['POSTCARD']['STOP']; ?></div>
                    </div>
                  </div>
                  */
                  ?>
                </div>
          </div>

        <?php 
    }
    public function SMS_bot($logged_in) {
        $fields = array();
        $fields['pc_eid'] = $_REQUEST['pc_eid'];
        $fields['show'] = $_REQUEST['show'];
        $this->curl->setUrl($this->MedEx->getUrl('custom/SMS_bot&token='.$logged_in['token']));
        $this->curl->setData($fields);
        $this->curl->makeRequest();
//        echo $this->curl->getRawResponse();exit;
        $response = $this->curl->getResponse();

        if (isset($response['success'])) { 
            echo $response['success'];
        } else if (isset($response['error'])) {
            $this->lastError = $response['error'];
        }
        return false;
    }
}

class Setup extends Base {
    public function MedExBank($stage) {
        if ($stage =='1') {
        ?>
        <div class="row">
             <div class="col-sm-1"></div>
             <div class="col-sm-10 center">
                <div id="setup_1" class="showReminders borderShadow">
                    <div class="title row">MedEx Bank</div>
                    <div class="row showReminders ">
                        <div class="col-sm-1"></div>
                        <div class="col-sm-5 left">
                            <h3 class="title"><?php echo xlt('Targets'); ?>:</h3>
                            <ul>
                            <li> <?php echo xlt('Scheduled Appointments'); ?></li>
                            <li> <?php echo xlt('Patient Recalls'); ?></li>
                            </ul>
                        </div>
                        <div class="col-sm-1"></div>
                        <div class="col-sm-4 left">
                        <h3 class="title"><?php echo xlt('Methods'); ?>:</h3>
                            <ul>
                            <li> <?php echo xlt('SMS Messages'); ?></li>
                            <li> <?php echo xlt('Voice Mesages'); ?></li>
                            <li> <?php echo xlt('E-mail Messaging'); ?></li>
                            <li> <?php echo xlt('Postcards'); ?></li>
                            <li> <?php echo xlt('Address Labels'); ?></li>
                            </ul>
                        </div>
                        <div class="col-sm-1"></div>
                    </div>
                    <div class="align-center row showReminders">
                        <button onclick="goReminderRecall('setup&stage=2');" style="padding:8px 20px;"><?php echo xlt('Setup'); ?></button>
                        <br /><br />
                    </div>
                </div>
            </div>
            <div class="col-sm-1"></div>
        </div>

           <?php
        } else if ($stage =='2') {
        ?>
        <div class="row">
            <form name="medex_start" id="medex_start">
                <div class="col-sm-1"></div>
                <div class="col-sm-10 center">
                    <div id="setup_1" class="showReminders borderShadow">
                        <div class="title row fa"><?php echo xlt('Sign-up'); ?>: MedEx Bank</div>
                        <div class="row showReminders">
                            <div class="col-sm-1">
                            </div>
                            <div class="fa col-sm-10 center">
                                <div class="divTable4 fa" id="answer" name="answer">
                                    <div class="divTableBody">
                                        <div class="divTableRow">
                                            <div class="divTableCell divTableHeading">
                                                <?php echo xlt('E-mail'); ?>
                                            </div>
                                            <div class="divTableCell">
                                                <i id="email_check" name="email_check" class="top_right_corner nodisplay red fa fa-check"></i>
                                                <input type="text" data-rule-email="true" class="form-control" id="new_email" name="new_email" value="<?php echo attr($GLOBALS['user_data']['email']); ?>" placeholder="<?php echo xla('your email address'); ?>" required>
                                                <div style="font-size:0.8em;color:red;margin:0 15px;padding:0 15px;" class="red nodisplay" id="email_help" name="email_help"><?php echo xlt('Please provide a valid e-mail address to proceed'); ?>...</div>

                                            </div>
                                        </div>
                                        <div class="divTableRow">
                                            <div class="divTableCell divTableHeading">
                                                <?php echo xlt('Password'); ?>
                                            </div>
                                            <div class="divTableCell"><i id="pwd_check" name="pwd_check" class="top_right_corner nodisplay red fa fa-check"></i>
                                                <i class="fa top_right_corner fa-question" id="pwd_ico_help" aria-hidden="true" onclick="$('#pwd_help').toggleClass('nodisplay');"></i>
                                                <input type="password" placeholder="<?php xla('Password'); ?>" id="new_password" name="new_password" class="form-control" required> 
                                                <br />
                                                <div id="pwd_help" class="nodisplay" style="font-size:0.8em;color:red;margin:0 15px;padding:0 15px;"><?php echo xlt('8-12 characters long, including at least one upper case letter, one lower case letter, one number and one special character'); ?></div>
                                            </div>
                                        </div>
                                        <div class="divTableRow">
                                            <div class="divTableCell divTableHeading">
                                                <?php echo xlt('Repeat'); ?>
                                            </div>
                                            <div class="divTableCell"><i id="pwd_rcheck" name="pwd_rcheck" class="top_right_corner nodisplay red fa fa-check"></i>
                                                <input type="password" placeholder="<?php echo xla('Repeat password'); ?>" id="new_rpassword" name="new_rpassword" class="form-control" required>
                                                        <br />
                                                <div id="pwd_rhelp" class="nodisplay" style="color:red;margin:0 15px;padding:0 15px;arial,helvetica,clean,sans-serif
font-feature-settings: normal;
font-kerning: auto;
font-language-override: normal;
font-size: 11.7px;
font-size-adjust: none;
font-stretch: normal;
font-style: normal;
font-synthesis: weight style;
font-variant: normal;
font-variant-alternates: normal;
font-variant-caps: normal;
font-variant-east-asian: normal;
font-variant-ligatures: normal;
font-variant-numeric: normal;
font-variant-position: normal;
font-weight: 700;
line-height: 15.2167px;
"><?php echo xlt('Passwords do not match.'); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div id="ihvread" name="ihvread" class="fa left">
                                    <input type="checkbox" class="updated required" name="TERMS_yes" id="TERMS_yes" required>
                                    <label for="TERMS_yes" class="input-helper input-helper--checkbox" title="Terms and Conditions"><?php echo xlt('I have read and my practice agrees to the'); ?> 
                                        <a href="#" onclick="cascwin('https://medexbank.com/cart/upload/index.php?route=information/information&information_id=5','TERMS',800, 600);">MedEx <?php echo xlt('Terms and Conditions'); ?></a></label><br />
                                    <input type="checkbox" class="updated required" name="BusAgree_yes" id="BusAgree_yes" required>
                                    <label for="BusAgree_yes" class="input-helper input-helper--checkbox" title="<?php echo xla('HIPAA Agreement'); ?>"><?php echo xlt('I have read and accept the'); ?> 
                                        <a href="#" onclick="cascwin('https://medexbank.com/cart/upload/index.php?route=information/information&information_id=8','HIPAA Bus Agree',800, 600);">MedEx <?php echo xlt('HIPAA Business Associate Agreement'); ?></a></label>
                                        <br />
                                <br /><br />
                                <div class="fa btn button-success" onclick="signUp();"> <?php echo xlt('Sign-up'); ?> </div>
                                
                                <span > </span>
                            </div>
                            <div class="col-sm-1"></div>
                        </div>
                    </div>
                    <div class="col-sm-1"></div>
                </div>
                <div class="col-sm-1"></div>
            </form>
        </div>
        <script>
            function signUp() {
                var email = $("#new_email").val();
                if (!validateEmail(email))  return alert('<?php echo xlt('Please provide a valid e-mail address to proceed'); ?>...');
                var password = $("#new_password").val();
                var passed = check_Password(password);
                if (!passed) return alert('<?php echo xlt('Passwords must be 8-12 characters long and include one capital letter, one lower case letter and one special character...'); ?>  ');
                if ($("#new_rpassword").val() !== password) return alert('<?php echo xlt('Passwords do not match'); ?>!');
                if (!$("#TERMS_yes").is(':checked')) return alert('<?php echo xlt('You must agree to the Terms & Conditions before signing up...');?> ');
                if (!$("#BusAgree_yes").is(':checked')) return alert('<?php echo xlt('You must agree to the HIPAA Business Associate Agreement...');?> ');
                //do we want this translated?  MedEx is in English.
                if (confirm("    <?php echo xla('Confirm: you are opening a secure connection to MedExBank.com to create your account').".\n\n".xla('Before your practice can send live messages, you will need to login to')." MedExBank.com ".xla('to').":\n     ".xla('confirm your practice information')."\n     ".xla('choose your service options')."\n     ".xla('create your desired SMS/Voice and/or e-mail messages')."."; ?>"))
                {
                    var url = "save.php?MedEx=start";
                    formData = $("form#medex_start").serialize();
                    top.restoreSession();
                    $.ajax({
                        type   : 'POST',
                        url    : url,
                        data   : formData 
                        })
                    .done(function(result) {
                            obj = JSON.parse(result);
                            $("#answer").html(obj.show);
                            $("#ihvread").addClass('nodisplay');
                                
                            if (obj.success) {
                                $("#butme").html('<a href="messages.php?go=Preferences"><?php echo xlt('Preferences'); ?></a>');
                                url="https://www.medexbank.com/cart/upload/";
                                window.open(url, 'clinical', 'width=fullscreen,height=fullscreen,resizable=1,scrollbars=1,directories=0,titlebar=0,toolbar=0,location=0,status=0,menubar=0');
                                header("location: messages.php?go=Preferences");//or refresh_me(); not sure which way to go - need to test errors to know...
                            } 
                    });
                }
            }
            function validateEmail(email) {
                var re = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
                return re.test(email);
            }
            function check_Password(password) {
                var passed = validatePassword(password, {
                    length:   [8, Infinity],
                    lower:    1,
                    upper:    1,
                    numeric:  1,
                    special:  1,
                    badWords: ["password", "qwerty", "12345"],
                    badSequenceLength: 4
                });
                return passed;
            }
            function validatePassword (pw, options) {
                // default options (allows any password)
                var o = {
                    lower:    0,
                    upper:    0,
                    alpha:    0, /* lower + upper */
                    numeric:  0,
                    special:  0,
                    length:   [0, Infinity],
                    custom:   [ /* regexes and/or functions */ ],
                    badWords: [],
                    badSequenceLength: 0,
                    noQwertySequences: false,
                    noSequential:      false
                };

                for (var property in options)
                    o[property] = options[property];

                var re = {
                        lower:   /[a-z]/g,
                        upper:   /[A-Z]/g,
                        alpha:   /[A-Z]/gi,
                        numeric: /[0-9]/g,
                        special: /[\W_]/g
                    },
                    rule, i;

                // enforce min/max length
                if (pw.length < o.length[0] || pw.length > o.length[1])
                    return false;

                // enforce lower/upper/alpha/numeric/special rules
                for (rule in re) {
                    if ((pw.match(re[rule]) || []).length < o[rule])
                        return false;
                }

                // enforce word ban (case insensitive)
                for (i = 0; i < o.badWords.length; i++) {
                    if (pw.toLowerCase().indexOf(o.badWords[i].toLowerCase()) > -1)
                        return false;
                }

                // enforce the no sequential, identical characters rule
                if (o.noSequential && /([\S\s])\1/.test(pw))
                    return false;

                // enforce alphanumeric/qwerty sequence ban rules
                if (o.badSequenceLength) {
                    var lower   = "abcdefghijklmnopqrstuvwxyz",
                        upper   = lower.toUpperCase(),
                        numbers = "0123456789",
                        qwerty  = "qwertyuiopasdfghjklzxcvbnm",
                        start   = o.badSequenceLength - 1,
                        seq     = "_" + pw.slice(0, start);
                    for (i = start; i < pw.length; i++) {
                        seq = seq.slice(1) + pw.charAt(i);
                        if (
                            lower.indexOf(seq)   > -1 ||
                            upper.indexOf(seq)   > -1 ||
                            numbers.indexOf(seq) > -1 ||
                            (o.noQwertySequences && qwerty.indexOf(seq) > -1)
                        ) {
                            return false;
                        }
                    }
                }

                // enforce custom regex/function rules
                for (i = 0; i < o.custom.length; i++) {
                    rule = o.custom[i];
                    if (rule instanceof RegExp) {
                        if (!rule.test(pw))
                            return false;
                    } else if (rule instanceof Function) {
                        if (!rule(pw))
                            return false;
                    }
                }

                // great success!
                return true;
            }
            $(document).ready(function() {
                $("#new_email").blur(function(e) {
                                    e.preventDefault();
                                    var email = $("#new_email").val();
                                    if (validateEmail(email))  {
                                        $("#email_help").addClass('nodisplay');
                                        $("#email_check").removeClass('nodisplay');
                                    } else {
                                        $("#email_help").removeClass('nodisplay');
                                        $("#email_check").addClass('nodisplay');
                                    }
                                });
                $("#new_password,#new_rpassword").keyup(function(e) {
                                    e.preventDefault();
                                    var pwd = $("#new_password").val();
                                    if (check_Password(pwd))  {
                                        $('#pwd_help').addClass('nodisplay');
                                        $("#pwd_ico_help").addClass('nodisplay');
                                        $("#pwd_check").removeClass('nodisplay');
                                    } else {
                                        $("#pwd_help").removeClass('nodisplay');
                                        $("#pwd_ico_help").removeClass('nodisplay');
                                        $("#pwd_check").addClass('nodisplay');
                                    }
                                    if (this.id == "new_rpassword") {
                                        var pwd1 = $("#new_password").val();
                                        var pwd2 = $("#new_rpassword").val();
                                        if (pwd1 == pwd2) {
                                            $('#pwd_rhelp').addClass('nodisplay');
                                            $("#pwd_rcheck").removeClass('nodisplay');
                                        } else {
                                            $("#pwd_rhelp").removeClass('nodisplay');
                                            $("#pwd_rcheck").addClass('nodisplay');
                                        }
                                    }
                                });
            });
        </script>
        <?php
        } 
    }
    public function autoReg($data) {
        if (empty($data)) return false; //throw new InvalidDataException("We need to actually send some data...");
        $this->curl->setUrl($this->MedEx->getUrl('custom/signUp'));
        $this->curl->setData($data);
        $this->curl->makeRequest();
        $response = $this->curl->getResponse();
        if (isset($response['success'])) { 
            return $response;
        } else if (isset($response['error'])) {
            $this->lastError = $response['error'];
        }
        return false;
    }
}

class MedEx {
    private $cookie;
    private $url;
    public $lastError = '';
    public $curl;
    public $practice;
    public $campaign;
    public $events;
    public $callback;
    public $logging;
    public $display;
    public $setup;

   
    public function __construct($url, $sessionFile = 'cookiejar_MedExAPI') {
        global $GLOBALS;
   
        if ($sessionFile == 'cookiejar_MedExAPI') {
            $sessionFile = $GLOBALS['temporary_files_dir'].'/cookiejar_MedExAPI';
        }
        $this->url      = rtrim('http://'.preg_replace('/^https?\:\/\//', '', $url), '/') . '/cart/upload/index.php?route=api/';
        $this->curl     = new CurlRequest($sessionFile);
        $this->practice = new Practice($this);
        $this->campaign = new Campaign($this);
        $this->events   = new Events($this);
        $this->callback = new Callback($this);
        $this->logging  = new Logging($this);
        $this->display  = new Display($this);
        $this->setup    = new Setup($this);
    }
    public function getUrl($method) { return $this->url . $method; }
    public function getCookie() { return $this->cookie; }
    public function getLastError() { return $this->lastError; }
    public function login() {
        $response= array();
        $result = sqlStatement("SHOW TABLES LIKE 'medex_prefs'");
        $table2Exists = sqlFetchArray($result);
        if (!$table2Exists) return false;
                                                
        $query = "SELECT * from medex_prefs";
        $info = sqlFetchArray(sqlStatement($query));
        $username = $info['ME_username'];
        $key = $info['ME_api_key'];
        $UID = $info['MedEx_id'];
        if (empty($username) || empty($key) || empty($UID)) return false;//throw new InvalidCredentialsException("API Credentials are incomplete.");
        $this->curl->setUrl($this->getUrl('login'));
        $this->curl->setData(array(
            'username' => $username,
            'key' => $key,
            'UID' => $UID
        ));
        $this->curl->makeRequest();
        
        $response = $this->curl->getResponse();

         if (isset($response['success']) && isset($response['token'])) {
            return $response;
        } else if (isset($response['error'])) {
            $this->lastError = $response['error'];
        }
        return false;    
    }
    public function checkModality($event, $appt) {
        $sqlQuery = "select * from medex_icons";
        $result = sqlStatement($sqlQuery);
        while ($icons = sqlFetchArray($result)) {
            //substitute title="..." with title="'.xla('...').'" in $icons['i_html']
            $title = preg_match('/title=\"(.*)\"/', $icons['i_html']);
            $xl_title = xla($title);
            $icons['i_html'] = str_replace($title,$xl_title,$icons['i_html']);
            $icon[$icons['msg_type']][$icons['msg_status']] = $icons['i_html'];
        }
        if ($event['M_type'] =="SMS") {
            if (empty($appt['phone_cell']) || ($appt["hipaa_allowsms"]=="NO")) {
                return array($icon['SMS']['NotAllowed'],false);
            } else {
                $phone = preg_replace("/[^0-9]/", "", $appt["phone_cell"]); 
                return array($icon['SMS']['ALLOWED'],$phone);     // It is allowed and they have a cell phone
            }
        } else if ($event['M_type'] =="AVM") {
            if  ((empty($appt["phone_home"]) && (empty($appt["phone_cell"])) || ($appt["hipaa_voice"]=="NO"))) {
                return array($icon['AVM']['NotAllowed'],false);
            } else {
                if (!empty($appt["phone_cell"])) { 
                    $phone = preg_replace("/[^0-9]/", "", $appt["phone_cell"]); 
                } else { 
                    $phone = preg_replace("/[^0-9]/", "", $appt["phone_home"]); 
                }
                return array($icon['AVM']['ALLOWED'],$phone); //We have a phone to call and permission!
            }
        } else if ($event['M_type'] =="EMAIL") {
            if (($appt["email"]=="")||($appt["hipaa_allowemail"]=="NO")) {
                return array($icon['EMAIL']['NotAllowed'],false);
            } else {
                //need to make sure this is a valid email too eh?
                return array($icon['EMAIL']['ALLOWED'],$appt["email"]);
            }
        //need to add in check for address to send postcards? - when we add in postcards...
        } else {
            return array(false,false);
        }    
    }    
    public function medex_logit($event="MedEx-Messaging-Service",$success,$comments) {

        //Need to figure out how and where to log this!!!!


        //$event, $user, $groupname, $success, $comments="", $patient_id=null, 
        //$log_from = 'open-emr', $menu_item = 'dashboard', $ccda_doc_id = 0) {
        //newEvent($event,"MedEx-messenger",0,$success,$comments="", $patient_id=null,$log_from = 'open-emr', $menu_item = 'dashboard', $ccda_doc_id = 0);
    }
}
//class InvalidDataException extends \Exception {}
