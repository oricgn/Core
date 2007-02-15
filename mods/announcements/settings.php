<?php

if(!defined("PHORUM_ADMIN")) return;

$error="";

if(count($_POST)){

    $_POST["number_to_show"] = (int)$_POST["number_to_show"];
    if((int)$_POST["number_to_show"]==0){
        // sanity check
        $_POST["number_to_show"] = 5;
    }

    $PHORUM["mod_announcements"]["forum_id"] = (int)$_POST["forum_id"];
    $PHORUM["mod_announcements"]["pages"] = $_POST["pages"];
    $PHORUM["mod_announcements"]["only_show_unread"] = $_POST["only_show_unread"];
    $PHORUM["mod_announcements"]["number_to_show"] = (int)$_POST["number_to_show"];
    $PHORUM["mod_announcements"]["days_to_show"] = (int)$_POST["days_to_show"];

    if(empty($error)){
        if(!phorum_db_update_settings(array("mod_announcements"=>$PHORUM["mod_announcements"]))){
            $error="Database error while updating settings.";
        } else {
            echo "Announcements Settings Updated<br />";
        }
    }
}

include_once "./include/admin/PhorumInputForm.php";

$frm =& new PhorumInputForm ("", "post", "Save");

$frm->hidden("module", "modsettings");

$frm->hidden("mod", "announcements");

$frm->addbreak("Announcement Settings");

$forum_list = phorum_get_forum_info(true);
$frm->addrow("Announcement Forum", $frm->select_tag("forum_id", $forum_list, $PHORUM["mod_announcements"]["forum_id"]));


$page_list = $frm->checkbox("pages[index]", 1, "Forum List (index.php)", $PHORUM["mod_announcements"]["pages"]["index"])."<br/>".
             $frm->checkbox("pages[list]", 1, "Message List (list.php)", $PHORUM["mod_announcements"]["pages"]["list"])."<br/>".
             $frm->checkbox("pages[read]", 1, "Read Message (read.php)", $PHORUM["mod_announcements"]["pages"]["read"]);

$frm->addrow("Announcements Appear On", $page_list);

$frm->addrow("Show Only Unread?", $frm->checkbox("only_show_unread", 1, "", $PHORUM["mod_announcements"]["only_show_unread"]));
$frm->addrow("Number To Show", $frm->text_box("number_to_show", $PHORUM["mod_announcements"]["number_to_show"], 10));
$frm->addrow("Maximum Days To Show", $frm->text_box("days_to_show", $PHORUM["mod_announcements"]["days_to_show"], 10) . " (0 = forever)");

$frm->show();


?>
