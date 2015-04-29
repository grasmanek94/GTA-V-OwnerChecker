<?php
    /*
        This script checks if the user [SocialClubID] owns a copy of GTA:V on any platform.

        Script authors:
            - Gamer_Z / grasmanek94 (https://github.com/grasmanek94/)
            - eider (https://github.com/eider-i128/)
    */

    //list of ip addresses that are allowed to execute this script
    $allowed_ips = array('0.0.0.0', '127.0.0.1', '192.168.2.1', '::1');

    if(!in_array($_SERVER['REMOTE_ADDR'], $allowed_ips))
    {
        die();
    }

    require('rockstarsocialclubapi.php');

    // Set default timezone for date()
    date_default_timezone_set('Europe/Berlin');

    //example usage:
    $rsscapi = new RockStarSocialClubAPI();

    //create an account for this script on social club:
    $rsscapi->SetCredentials("SocialClubLogin", "PaSSword");

    //Uncomment if you have problems:
    //$rsscapi->DisableVerification();

    if(!$rsscapi->AlreadyLoggedIn())
    {
        $status = $rsscapi->Login();

        switch($status)
        {
            case 3:
            case 2:
            echo("0<BR/>ERROR: SSL error or Rockstar Service is unavailable.");
            exit();

            case 1:
            echo("1<BR/>ERROR: Invalid credentials, cannot logon to Rockstar SocialClub.");
            exit();
        }
    }

    $SocialClubID = isset($_GET['SocialClubID']) ? $_GET['SocialClubID'] : '';

    if(strlen($SocialClubID) < 2)
    {
        $SocialClubID = "-";
    }

    if(!isset($_GET['message']))
    {
        $status = $rsscapi->UserGTAStatus($SocialClubID, 1);

        switch($status)
        {
            case 3:
            echo("2<BR/>ERROR: Too many retries.");
            break;
            case 2:
            echo("3<BR/>ERROR: User does not exist.");
            break;
            case 1:
            echo("4<BR/>ERROR: User does not own a legitimate copy of GTA:V or privacy settings do not allow viewing this information.");
            break;
            case 0:
            echo("9<BR/>SUCCESS: User owns a legitimate copy of GTA:V on any platform.");
            break;
        }
    }
    else
    {
        $status = $rsscapi->SendMessage($SocialClubID, $_GET['message'], 1);

        switch($status)
        {
            case 4:
            echo("2<BR/>ERROR: Too many retries.");
            break;
            case 3:
            echo("3<BR/>ERROR: User does not exist.");
            break;
            case 2:
            echo("4<BR/>ERROR: SSL error or Rockstar Service is unavailable.");
            break;
            case 1:
            echo("5<BR/>ERROR: Unknown error while sending the message.");
            break;
            case 0:
            echo("9<BR/>SUCCESS: Message sent.");
            break;
        }        
    }
?>
