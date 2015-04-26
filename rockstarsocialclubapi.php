<?php
    /*
        This class allows:
            - to check if the user [SocialClubID] owns a copy of GTA:V on any platform
            - send messages to users

        Script authors:
            - Gamer_Z / grasmanek94 (https://github.com/grasmanek94/)
            - eider (https://github.com/eider-i128/)

        Requires:  http://sourceforge.net/projects/simplehtmldom/
    */

    require('simplehtmldom.php');

    class RockStarSocialClubAPI
    {
        private $_username;
        private $_password;
        private $_connection;

        //make sure no-one can access this file(!) [eg with .htaccess] 
        //http://stackoverflow.com/questions/11728976/how-to-deny-access-to-a-file-in-htaccess
        private $_cookiefile;

        private $_static_curl_options;

        private $_ssl_curl_problem_solving;

        public function __construct()
        {
            $this->_cookiefile = realpath(dirname(__FILE__)) . '/cookies.txt';

            $this->_static_curl_options = array
            (
                CURLOPT_RETURNTRANSFER   => true,
                CURLOPT_ENCODING         => 'gzip',
                CURLOPT_AUTOREFERER      => true,
                CURLOPT_CONNECTTIMEOUT   => 3,
                CURLOPT_TIMEOUT          => 5,
                CURLOPT_MAXREDIRS        => 3,
                CURLOPT_COOKIEJAR        => $this->_cookiefile,
                CURLOPT_COOKIEFILE       => $this->_cookiefile  
            );

            $this->_ssl_curl_problem_solving = array
            (
                //keep this enabled, for more information check: https://www.openssl.org/~bodo/ssl-poodle.pdf
                CURLOPT_SSLVERSION       => 1, //CURL_SSLVERSION_TLSv1
                CURLOPT_SSL_CIPHER_LIST  => 'TLSv1'
            );

            $this->_connection = curl_init();
        }

        public function __destruct()
        {
            curl_close($this->_connection);
        }

        //uuse this function and download this file http://curl.haxx.se/ca/cacert.pem (its bundle of CA Root Certificates) if you have problems with verifying ssl cert
        public function EnableCAINFO()
        {
            $this->_ssl_curl_problem_solving[CURLOPT_CAINFO] = realpath(dirname(__FILE__)) . '/cacert.pem';
        }

        //if you have problems even after using EnableCAINFO(), use this function (will make your script vulnerable to MITM attacks):
        public function DisableVerification()
        {
            $this->_ssl_curl_problem_solving = $this->_ssl_curl_problem_solving + array
            (
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_SSL_VERIFYHOST => 0
            );
        }

        private function GetRequestData($index, $url)
        {
            curl_reset($this->_connection);

            curl_setopt_array($this->_connection, (array(
                CURLOPT_URL              => $url,
                CURLOPT_FOLLOWLOCATION   => true,
                CURLOPT_HTTPHEADER       => array('Accept-Encoding: gzip, deflate')
            ) + $this->_static_curl_options + (($url[4] == 's' || $url[4] == 'S') ? $this->_ssl_curl_problem_solving : array())));

            $homepage = curl_exec($this->_connection);

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

        public function SetCredentials($username, $password) 
        {
            $this->_username = $username;
            $this->_password = $password;
        }

        //see function for return values, 0 means success
        public function Login()
        {
            @unlink($this->_cookiefile);

            $RequestVerificationToken = $this->GetRequestData(0, 'https://socialclub.rockstargames.com/profile/signin/')[0];
            if($RequestVerificationToken == "")
            {
                return 3;//login failed because cannot download the Rockstar Signin page (SSL error? R* down?)
            }

            $data = json_encode(array("login" => $this->_username, "password" => $this->_password, "rememberme" => TRUE));

            curl_reset($this->_connection);

            curl_setopt_array($this->_connection, (array(
                CURLOPT_URL              => 'https://socialclub.rockstargames.com/profile/signincompact',
                CURLOPT_POST             => true,
                CURLOPT_POSTFIELDS       => $data,
                CURLOPT_HTTPHEADER       => array('Accept-Encoding: gzip, deflate', 'Content-Type: application/json; charset=UTF-8','Content-Length:' . strlen($data), 'RequestVerificationToken: ' . $RequestVerificationToken)
            ) + $this->_static_curl_options + $this->_ssl_curl_problem_solving));

            curl_exec($this->_connection);

            $retData = curl_exec($this->_connection);
            $http_code = curl_getinfo($this->_connection, CURLINFO_HTTP_CODE);   
            $retJSON = json_decode($retData);

            if($http_code == 200)
            {
                if(isset($retJSON->result) && $retJSON->result == true)
                {
                    return 0;//login OK
                }
                return 1;//login failed because credentials do not work
            }
            return 2;//login failed because, well.. because. (probably invalid cookie or invalid requestverificationtoken)
        }

        //returns true if logged in, false if not or some error occured
        public function AlreadyLoggedIn()
        {
	        $modtime = @filemtime($this->_cookiefile);
	        $timenow = time();

	        $diff = $timenow - $modtime;
	        if ($diff > 5*60)
	        {
                @unlink($this->_cookiefile);
            }

            curl_reset($this->_connection);

            curl_setopt_array($this->_connection, (array(
                CURLOPT_URL              => 'http://socialclub.rockstargames.com',
                CURLOPT_HTTPHEADER       => array('Accept-Encoding: gzip, deflate')
            ) + $this->_static_curl_options));

            curl_exec($this->_connection);

            $http_code = curl_getinfo($this->_connection, CURLINFO_HTTP_CODE);   
            
            return $http_code == 302;
        }

        public function UserGTAStatus($SocialClubID, $maxretries, $retries = 0)
        {
            curl_reset($this->_connection);
    
            curl_setopt_array($this->_connection, (array(
                CURLOPT_URL              => 'http://socialclub.rockstargames.com/games/gtav/api/mp/gun/0/minigun?nickname=' . $SocialClubID,
                CURLOPT_HTTPHEADER       => array('Accept-Encoding: gzip, deflate')
            ) + $this->_static_curl_options));

            $page = curl_exec ($this->_connection);

            $http_code = curl_getinfo($this->_connection, CURLINFO_HTTP_CODE);

            if ($http_code == 200) 
            {
                if(strlen($page) > 0)
                {
                    return 0;//user has GTA:V on any platform
                }
                else
                {
                    return 1;//user has no GTA or privacy settings do not allow viewing this information
                }
            }
            else if($http_code == 302)
            {
                return 2;//user does not exist
            }
            else if($retries < $maxretries)
            {
                return $this->UserGTAStatus($SocialClubID, $maxretries, $retries+1);
            }
            else
            {
                return 3;//max retries reached
            }
        }

        function SendMessage($SocialClubID, $message, $maxretries, $retries = 0)
        {
            $reqData = $this->GetRequestData(0, 'http://socialclub.rockstargames.com/member/'. $SocialClubID .'/');

            $RequestVerificationToken = $reqData[0];
            if($RequestVerificationToken == "")
            {
                return 4;//sendmessage failed because cannot download the Rockstar Signin page (SSL error? R* down?)
            }

            if((strpos($reqData[1], $SocialClubID) !== false))
            {
                curl_reset($this->_connection);

                $data = json_encode(array("nickname" => $SocialClubID, "__RequestVerificationToken" => $RequestVerificationToken, "message" => $message));  

                curl_setopt_array($this->_connection, (array(
                    CURLOPT_URL              => 'http://socialclub.rockstargames.com/Message/AddMessage',
                    CURLOPT_POST             => true,
                    CURLOPT_POSTFIELDS       => $data,
                    CURLOPT_HTTPHEADER       => array('Accept-Encoding: gzip, deflate', 'Content-Type: application/json; charset=UTF-8','Content-Length:' . strlen($data), 'RequestVerificationToken: ' . $RequestVerificationToken)
                ) + $this->_static_curl_options));

                $retData = curl_exec($this->_connection);

                $http_code = curl_getinfo($this->_connection, CURLINFO_HTTP_CODE);

                $retJSON = json_decode($retData);

                if($http_code == 200)
                {
                    if(isset($retJSON->Status) && $retJSON->Status == true)
                    {
                        return 0;//message send
                    }
                    else
                    {
                        return 1;//message send failed, unkown error
                    }
                }
                else if($retries < $maxretries)
                {
                    return $this->SendMessage($SocialClubID, $message, $maxretries, $retries+1);
                }
                else
                {
                    return 2;//message send failed, too many retries
                }
            }
            else
            {
                return 3;//error user not found
            } 
        }
    }
?>