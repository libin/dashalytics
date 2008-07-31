<?php
/*
This is a clone of the PEAR HTTP/Request class object. It uses libcurl to do the networking stuff. 
Should also work with the HTTPS protocol

Important: Not every method has been ported, just the ones that were needed.
*/

class TanTanCurl {
    var $curl;
    var $postData;
    var $cookies;
    var $raw;
    var $response;
    var $headers;
    var $url;
    
    function TanTanCurl() {
        $this->curl = curl_init();
        $this->postData = array();
        $this->cookies = array();
        $this->headers = array();
        $this->url = false;
    }
    
    function addHeader($header, $value) {
        $this->headers[$header] = $value;
    }
    
    function setMethod($method) {
        switch ($method) {
            case HTTP_REQUEST_METHOD_POST:
            case 'POST':
                curl_setopt($this->curl, CURLOPT_POST, true);
            break;
            default:
            case 'GET':
                curl_setopt($this->curl, CURLOPT_HTTPGET, true);
            break;
        }
    }
    function setURL($url) {
        $this->url = $url;
    }
    function addPostData($name, $value) {
        $this->postData[$name] = $value;
    }
    function addCookie($name, $value) {
        $this->cookies[$name] = array('name' => $name, 'value' => $value);
    }
    function sendRequest() {
        $headers = array(
           "Accept: *.*",
        );
        
        foreach ($this->headers as $k=>$h) {
            $headers[] = "$k: $h";
        }

        if (count($this->cookies) > 0) {
            $cookieVars = '';
            foreach ($this->cookies as $cookie) {
                //$headers[] = "Cookie: ".$cookie['name'].'='.$cookie['value'];
                $cookieVars .= ''.$cookie['name'].'='.$cookie['value'].'; ';
            }
            curl_setopt($this->curl, CURLOPT_COOKIE, $cookieVars);
            //print_r($cookieVars);
        }
        
        if (count($this->postData) > 0) { // if a POST
            $postVars = '';
            foreach ($this->postData as $key=>$value) {
                $postVars .= $key.'='.urlencode($value).'&';
            }
            // *** TODO ***
            // weird, libcurl doesnt seem to POST correctly
            //curl_setopt($this->curl, CURL_POST, true);
            //curl_setopt($this->curl, CURL_POSTFIELDS, $postVars);
            curl_setopt($this->curl, CURLOPT_POST, true);
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, $postVars);

            //curl_setopt($this->curl, CURLOPT_HTTPGET, true);
            //$this->url .= '?'.$postVars;

        } else {
            curl_setopt($this->curl, CURLOPT_HTTPGET, true);
        }
        curl_setopt($this->curl, CURLOPT_URL, $this->url);
        curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($this->curl, CURLOPT_HEADER, true);
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
        $this->raw = curl_exec($this->curl);
        $this->response = $this->_parseResponse($this->raw);
        return true; // hmm no error checking for now
    }
    
    function getResponseHeader($header=false) {
        if ($header) {
            return $this->response['header'][$header];
        } else {
            return $this->response['header'];
        }
    }
    function getResponseCookies() {
        $hdrCookies = explode("\n", $this->response['header']['Set-Cookie']);
        $cookies = array();
        
        foreach ($hdrCookies as $cookie) {
            if ($cookie) {
                list($name, $value) = explode('=', $cookie, 2);
                list($value, $domain, $path, $expires) = explode(';', $value);
                $cookies[$name] = array('name' => $name, 'value' => $value);
            }
        }
        return $cookies;
    }
    function getResponseBody() {
        return $this->response['body'];
    }
    function getResponseRaw() {
        return $this->raw;
    }
    function clearPostData() {
        $this->postData = array();
    }
    
    function _parseResponse($this_response) {
        list($response_headers, $response_body) = explode("\r\n\r\n", $this_response, 2);
        $response_header_lines = explode("\r\n", $response_headers);
        $http_response_line = array_shift($response_header_lines);
        if(preg_match('@^HTTP/[0-9]\.[0-9] ([0-9]{3})@',$http_response_line, $matches)) { $response_code = $matches[1]; }
        $response_header_array = array();
        foreach($response_header_lines as $header_line) {
            list($header,$value) = explode(': ', $header_line, 2);
            $response_header_array[$header] .= $value."\n";
        }
        return array("code" => $response_code, "header" => $response_header_array, "body" => $response_body); 
    }
}
?>