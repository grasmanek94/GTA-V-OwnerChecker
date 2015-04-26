<?php
    /*
        This script checks if the user [SocialClubID] owns a copy of GTA:V on any platform.

        Script authors:
            - Gamer_Z / grasmanek94 (https://github.com/grasmanek94/)
            - eider (https://github.com/eider-i128/)

        Requires:  http://sourceforge.net/projects/simplehtmldom/
        Thanks to: https://github.com/gta5-map/Social-Club-API-cheat-sheet
    */
    
    if(!defined('CURL_SSLVERSION_TLSv1')) define('CURL_SSLVERSION_TLSv1', 1); // some PHP versions seems to lack this predefined variable

    //list of ip addresses that are allowed to execute this script
    $allowed_ips = array('0.0.0.0', '127.0.0.1', '192.168.2.1');

    if(!in_array($_SERVER['REMOTE_ADDR'], $allowed_ips))
    {
        die();
    }

    //Create a new social club id for this script and use it here
    $username = 'SocialClubID';
    $password = 'Pa$$word';

    //make sure no-one can access this file(!) [eg with .htaccess] 
    //http://stackoverflow.com/questions/11728976/how-to-deny-access-to-a-file-in-htaccess
    $cookiefile = realpath(dirname(__FILE__)) . '/cookies.txt';

    // Require SimpleHTMLDOM library
    require('simplehtmldom.php');

    // Set default timezone for date()
    date_default_timezone_set('Europe/Berlin');

    //error_reporting(E_ALL);
    //ini_set('display_errors', 1);

    error_reporting(0);
    //ini_set('display_errors', 0);

    $static_curl_options = array(
        CURLOPT_RETURNTRANSFER   => true,
        CURLOPT_ENCODING         => 'gzip',
        CURLOPT_AUTOREFERER      => true,
        CURLOPT_CONNECTTIMEOUT   => 15,
        CURLOPT_TIMEOUT          => 30,
        CURLOPT_MAXREDIRS        => 3,
        CURLOPT_COOKIEJAR        => $cookiefile,
        CURLOPT_COOKIEFILE       => $cookiefile  
    );

    $ssl_curl_problem_solving = array(
        //keep this enabled, for more information check: https://www.openssl.org/~bodo/ssl-poodle.pdf
        CURLOPT_SSLVERSION       => CURL_SSLVERSION_TLSv1,
        CURLOPT_SSL_CIPHER_LIST  => 'TLSv1',
        
        //uncomment it and download this file http://curl.haxx.se/ca/cacert.pem (its bundle of CA Root Certificates) if you have problems with verifying ssl cert
        //CURLOPT_CAINFO           => realpath(dirname(__FILE__)) . '/cacert.pem',

        //if you have problems, uncomment these (will make your script vulnerable to MITM attacks):
        //CURLOPT_SSL_VERIFYPEER   => 0,
        //CURLOPT_SSL_VERIFYHOST   => 0   
    );

    function GetRequestData($index, $url, $https)
    {
        $ch_home = curl_init();

        global $static_curl_options;
        global $ssl_curl_problem_solving;

        curl_setopt_array( $ch_home, (array(
            CURLOPT_URL              => $url,
            CURLOPT_FOLLOWLOCATION   => true,
            CURLOPT_HTTPHEADER       => array('Accept-Encoding: gzip, deflate')
        ) + $static_curl_options + ($https ? $ssl_curl_problem_solving : array())));

        $homepage = curl_exec($ch_home);

        curl_close ($ch_home);

        $html = str_get_html($homepage);
        if(strlen($homepage) > 0 && $html !== false)
        {
            $req = $html->find('input[name=__RequestVerificationToken]', $index);
            if($req !== null)
            {
                return array(0 => $req->value, 1 => $homepage);
            }
        }
        return array(0 => "", 1 => $homepage);        
    }

    function GetRequestVerificationToken($index, $url = 'https://socialclub.rockstargames.com/profile/signin/', $https = true)
    {  
        return GetRequestData($index, $url, $https)[0];
    }

    function Login($index = 0)
    {
        @unlink($cookiefile);

        $RequestVerificationToken = GetRequestVerificationToken($index);
        if($RequestVerificationToken == "")
        {
            return 2;
        }

        $ch_login = curl_init();

        global $username;
        global $password;
        global $static_curl_options;
        global $ssl_curl_problem_solving;

        curl_setopt_array( $ch_login, (array(
            CURLOPT_URL              => 'https://socialclub.rockstargames.com/profile/signin',
            CURLOPT_HEADER           => true,
            CURLOPT_POST             => true,
            CURLOPT_POSTFIELDS       => 'login=' . $username . '&password=' . $password . '&rememberme=true&__RequestVerificationToken=' . $RequestVerificationToken,
            CURLOPT_HTTPHEADER       => array('Accept-Encoding: gzip, deflate', 'Content-Type: application/x-www-form-urlencoded', 'RequestVerificationToken: ' . $RequestVerificationToken)
        ) + $static_curl_options + $ssl_curl_problem_solving));

        curl_exec($ch_login);
        curl_close ($ch_login); 

        $check_status = curl_init();

        curl_setopt_array($check_status, (array(
            CURLOPT_URL              => 'http://socialclub.rockstargames.com/',
            CURLOPT_FOLLOWLOCATION   => true,
            CURLOPT_HTTPHEADER       => array('Accept-Encoding: gzip, deflate')
        ) + $static_curl_options));

        $data = curl_exec($check_status);
        curl_close ($check_status); 

        return (int)(strpos($data, $username) !== false);    
    }

    function LoginErrorCode($index)
    {
        $status = Login($index);

        if($status == 2)
        {
            echo('2<br/>SSL Error occured, please reconfigure [ssl_curl_problem_solving] array (SSL options) in script.');
        }
        elseif ($status == 1)
        {
            echo('1<br/>Error occured, cannot access Rockstar Social Club data.');
        }
        else if($status == 0)
        {
            echo('0<br/>Error occured, cannot log-in to Rockstar Social Club with provided credentials.');
        }        
    }

    function CheckUser($retries, $maxretries, $index = 0)
    {
        $SocialClubID = isset($_GET['SocialClubID']) ? $_GET['SocialClubID'] : '';

        $ch = curl_init();
        
        global $static_curl_options;

        curl_setopt_array($ch, (array(
            CURLOPT_URL              => 'http://socialclub.rockstargames.com/games/gtav/api/mp/gun/0/minigun?nickname='.$SocialClubID,
            CURLOPT_HTTPHEADER       => array('Accept-Encoding: gzip, deflate')
        ) + $static_curl_options));

        $buf3 = curl_exec ($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close ($ch);

        if ($http_code == 200) 
        {
            if(strlen($buf3) > 0)
            {
                echo('5<br/>Success, user own a copy of GTA:V on any platform.');
            }
            else
            {
                echo('4<br/>Error occured, user does not have GTA:V or privacy settings of user do not allow viewing of this information.');
            }
        }
        else if($http_code == 302)
        {
            echo('3<br/>Error occured, user does not exist.');           
        }
        else if($retries < $maxretries)
        {
            Login($index);
            return CheckUser($retries+1, $maxretries);
        }
        else
        {
            LoginErrorCode($index);         
        }  
    }

    function SendMessage($retries, $maxretries, $index = 0)
    {
        $SocialClubID = isset($_GET['SocialClubID']) ? $_GET['SocialClubID'] : '';
        $message = isset($_GET['message']) ? $_GET['message'] : '';

        $reqData = GetRequestData($index, 'http://socialclub.rockstargames.com/member/'. $SocialClubID .'/', false);

        $RequestVerificationToken = $reqData[0];
        if($RequestVerificationToken == "")
        {
            echo('3<br/>Message cannot be sent, RequestVerificationToken error.');
            return;
        }

        if((strpos($reqData[1], $SocialClubID) !== false))
        {
            $ch_message = curl_init();

            global $static_curl_options;

            $data = array("nickname" => $SocialClubID, "__RequestVerificationToken" => $RequestVerificationToken, "message" => $message);  
            $json_data = json_encode($data);

            curl_setopt_array( $ch_message, (array(
                CURLOPT_URL              => 'http://socialclub.rockstargames.com/Message/AddMessage',
                CURLOPT_POST             => true,
                CURLOPT_POSTFIELDS       => $json_data,
                CURLOPT_HTTPHEADER       => array('Accept-Encoding: gzip, deflate', 'Content-Type: application/json; charset=UTF-8','Content-Length:' . strlen($json_data), 'RequestVerificationToken: ' . $RequestVerificationToken)
            ) + $static_curl_options));

            $retData = curl_exec($ch_message);

            $http_code = curl_getinfo($ch_message, CURLINFO_HTTP_CODE);

            curl_close ($ch_message);            

            $retJSON = json_decode($retData);

            if($http_code == 200)
            {
                if($retJSON->{'Status'} == true)
                {
                    echo('5<br/>Message sent.');
                }
                else
                {
                    echo('4<br/>Error sending message:<br/>');
                    var_dump($retJSON);
                }
            }
            else if($retries < $maxretries)
            {
                Login($index);
                return SendMessage($retries+1, $maxretries);
            }
            else
            {
                echo('3<br/>Message cannot be sent, unkown error:<br/>' . $retData);
            }
        }
        else if($retries < $maxretries)
        {
            Login($index);
            return SendMessage($retries+1, $maxretries);
        }
        else
        {
            LoginErrorCode($index);    
        }  
    }

    $action = isset($_GET['action']) ? $_GET['action'] : 0;

    switch($action)
    {
        case 0:
        CheckUser(0, 1);
        break;

        case 1:
        SendMessage(0, 1);
        break;
    }
?>
