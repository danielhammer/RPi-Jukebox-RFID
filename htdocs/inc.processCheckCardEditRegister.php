<?php
/*
* eventually, the two pages cardEdit.php and cardRegisterNew.php should be one
* since most of the functionality is the same. This file is an intermediary step
* to unify both, taking some core functionality into one file and then see if it
* works for both.
*/

/******************************************
* read available RFID trigger commands
*/
$rfidAvailRaw = "";
$fn = fopen("../settings/rfid_trigger_play.conf.sample","r");
while(! feof($fn))  {
    $result = fgets($fn);
    // ignore commented and empty lines
    if(!startsWith($result, "#") && trim($result) != "") {
        $rfidAvailRaw .= $result."\n";
    }
}
fclose($fn);
$rfidAvailArr = parse_ini_string($rfidAvailRaw); //print "<pre>"; print_r($rfidAvailArr); print "</pre>";
/******************************************/

/******************************************
* read RFID trigger commands already in use
*/
$rfidUsedRaw = "";
$fn = fopen("../settings/rfid_trigger_play.conf","r");
while(! feof($fn))  {
    $result = fgets($fn);
    // ignore commented and empty lines
    if(!startsWith($result, "#") && trim($result) != "") {
        $rfidUsedRaw .= $result."\n";
    }
}
fclose($fn);
$rfidUsedArr = parse_ini_string($rfidUsedRaw); //print "<pre>"; print_r($rfidUsedRaw); print "</pre>";
/******************************************/

/******************************************
* fill Avail with Used, else empty value
*/
foreach($rfidAvailArr as $key => $val) {
    // check if there is something in the existing conf file already
    if(startsWith($rfidUsedArr[$key], "%")) {
        $rfidAvailArr[$key] = "";
    } else {
        $rfidAvailArr[$key] = $rfidUsedArr[$key];
    }
}

/******************************************
* read the shortcuts available
*/
$shortcutstemp = array_filter(glob($conf['base_path'].'/shared/shortcuts/*'), 'is_file');
$shortcuts = array(); // the array with pairs of ID => foldername
// read files' content into array
foreach ($shortcutstemp as $shortcuttemp) {
    $shortcuts[basename($shortcuttemp)] = trim(file_get_contents($shortcuttemp));
}
//print "<pre>"; print_r($shortcuts); print "</pre>"; //???


/******************************************
* read the subfolders of $Audio_Folders_Path
*/
$audiofolders_abs = dir_list_recursively($Audio_Folders_Path);
usort($audiofolders_abs, 'strcasecmp');
/*
* get relative paths for pulldown
*/
$audiofolders = array();
foreach($audiofolders_abs as $audiofolder){
    /*
    * get the relative path as value, set the absolute path as key
    */
    $relpath = substr($audiofolder, strlen($Audio_Folders_Path) + 1, strlen($audiofolder));
    if($relpath != "") {
        $audiofolders[$audiofolder] = substr($audiofolder, strlen($Audio_Folders_Path) + 1, strlen($audiofolder));
    }
}

//print "<pre>"; print_r($audiofolders); print "</pre>"; //???
//print "<pre>\$post: "; print_r($post); print "</pre>"; //???

$messageError = "";
$messageAction = "";
$messageSuccess = "";

if($post['delete'] == "delete") {
    $messageAction .= "<p>The card with the ID '".$post['cardID']." has been deleted. 
        If you made a mistake, this is your chance to press 'Submit' to restore the card settings. 
        Else: Go <a href='index.php' class='mainMenu'><i class='mdi mdi-home'></i> Home</a>.</p>";
    // remove $fileshortcuts to cardID file in shortcuts
    $exec = "rm ".$fileshortcuts;
    if($debug == "true") {
        print "<pre>deleting shortcut:\n";
        print $exec;
        print "</pre>";
    } else {
        exec($exec);
    }
} elseif($post['submit'] == "submit") {
    /*
    * error check
    */
   
    // posted too little?
    if(
        (!isset($post['streamURL']) || !isset($post['streamType'])) 
        && !isset($post['audiofolder']) 
        && !isset($post['YTstreamURL']) 
        && !isset($post['TriggerCommand'])
    ) {
        $messageError .= $lang['cardRegisterErrorStreamOrAudio']." (error 002)";
    }
    // posted too much?
    $countActions = 0;
    if(isset($post['audiofolder'])) { $countActions++; }
    if(isset($post['audiofolderNew'])) { $countActions++; }
    if(isset($post['TriggerCommand'])) { $countActions++; }
    if($countActions > 1) {
        $messageError .= $lang['cardRegisterErrorStreamAndAudio']." (error 001)";
    }

    // posted streamFolderName and audiofolder
    if(isset($post['audiofolderNew']) && isset($post['audiofolder'])) {
        $messageError .= $lang['cardRegisterErrorExistingAndNew']." (error 003)";
    }

    // audiofolder already exists
    if(isset($post['audiofolderNew']) && file_exists($Audio_Folders_Path.'/'.$post['audiofolderNew'])) {
        $messageError .= $lang['cardRegisterErrorExistingFolder']." (error 004)";
    }

    // No streamFolderName entered
    if(isset($post['streamURL']) && !isset($post['audiofolderNew'])) {
        $messageError .= $lang['cardRegisterErrorSuggestFolder']." (error 005)";
        // suggest folder name: get rid of strange chars, prefixes and the like
        $post['audiofolderNew'] = $link = str_replace(array('http://','https://','/','=','-','.', 'www','?','&'), '', $post['streamURL']);
    }

    // streamFolderName not given
    if( 
        (isset($post['streamURL']) || isset($post['YTstreamURL'])) 
        && !isset($post['audiofolder']) 
        && !isset($post['audiofolderNew'])
    ) {
        $messageError .= $lang['cardRegisterErrorSuggestFolder']." (error 006)";
        // suggest folder name: get rid of strange chars, prefixes and the like
        $post['audiofolderNew'] = $link = str_replace(array('http://','https://','/','=','-','.', 'www','?','&'), '', $post['streamURL']);
    }

    /*
    * any errors?
    */
    if($messageAction == "" && $messageError == "") {
        /*
        * do what's asked of us
        */
        $fileshortcuts = $conf['shared_abs']."/shortcuts/".$post['cardID'];
        if(isset($post['streamURL'])) {
            /*
            * Stream URL to be created
            */
            include('inc.processAddNewStream.php');
            // success message
            $messageSuccess = "<p>".$lang['cardRegisterStream2Card']." ".$lang['globalFolder']." '".$post['audiofolderNew']."' ".$lang['globalCardId']." '".$post['cardID']."'</p>";
        }
        elseif(isset($post['TriggerCommand']) && trim($post['TriggerCommand']) != "false") {
            /*
            * RFID triggers system commands
            */
            include('inc.processAddTriggerCommand.php');
            // success message
            $messageSuccess = $lang['cardRegisterTriggerSuccess']." ".$post['TriggerCommand'];
        }        
        elseif(isset($post['YTstreamURL'])) {
            /*
            * Stream URL to be created
            */
            include('inc.processAddYT.php');
            // success message
            $messageSuccess = $lang['cardRegisterDownloadingYT'];
        } 
        elseif(isset($post['audiofolder']) && trim($post['audiofolder']) != "false") {
            /*
            * connect card with existing audio folder
            */
            // write $post['audiofolder'] to cardID file in shortcuts
            $exec = "rm ".$fileshortcuts."; echo '".$post['audiofolder']."' > ".$fileshortcuts."; chmod 777 ".$fileshortcuts;
            exec($exec);
            // success message
            $messageSuccess = "<p>".$lang['cardRegisterFolder2Card']."  ".$lang['globalFolder']." '".$post['audiofolder']."' ".$lang['globalCardId']." '".$post['cardID']."'</p>";
        }
        elseif(isset($post['audiofolderNew']) && trim($post['audiofolderNew']) != "") {
            /*
            * connect card with new audio folder
            */
            // create new folder
            $exec = "mkdir --parents '".$Audio_Folders_Path."/".$post['audiofolderNew']."'; chmod 777 '".$Audio_Folders_Path."/".$post['audiofolderNew']."'";
            exec($exec);
            $foldername = $Audio_Folders_Path."/".$post['audiofolderNew'];
            // New folder is created so we link a RFID to it. Write $post['audiofolderNew'] to cardID file in shortcuts
            $exec = "rm ".$fileshortcuts."; echo '".$post['audiofolderNew']."' > ".$fileshortcuts."; chmod 777 ".$fileshortcuts;
            exec($exec);
            // success message
            $messageSuccess = "<p>".$lang['cardRegisterFolder2Card']."  ".$lang['globalFolder']." '".$post['audiofolderNew']."' ".$lang['globalCardId']." '".$post['cardID']."'</p>";
        }
    } else {
        /*
        * Warning given, action can not be taken
        */
    }
}
?>
