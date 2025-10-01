<?php
// Author: Terry Brady, Georgetown University Libraries
ini_set("log_errors", 1);
ini_set("error_log", "/var/log/httpd/almainventory_error.log");
error_log("### Alma.php logging test ###");


class Alma {



    function __construct() {
        $configpath = parse_ini_file("Alma.prop", false);
        $proppath   = $configpath["proppath"];
        $sconfig    = parse_ini_file($proppath, false);

        $this->alma_apikey      = $sconfig["ALMA_APIKEY"];
        $this->alma_conf_apikey = $sconfig["ALMA_CONF_APIKEY"];
    }

    function getApiKey() {
        return $this->alma_apikey;
    }

    function getConfApiKey() {
        return $this->alma_conf_apikey;
    }

    function getRequest($param) {
        if (isset($param["apipath"])) {
            $apipath = $param["apipath"];
            unset($param["apipath"]);

            // Use conf API key for ANY /conf/ path
            if (strpos($apipath, '/conf/') !== false) {
                $param["apikey"] = $this->getConfApiKey();
                error_log("Forwarding to CONF API: $apipath");
            } else {
                $param["apikey"] = $this->getApiKey();
                error_log("Forwarding to BIB API: $apipath");
            }

            // Build URL with the chosen key
            $url = "{$apipath}?" . http_build_query($param);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Accept: application/json'
            ));

            $jsonstr = curl_exec($ch);
            curl_close($ch);

            echo $jsonstr;
            return;
        }

        // No apipath supplied
        echo "";
    }
}
