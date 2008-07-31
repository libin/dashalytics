<?php
/*
Copyright (C) 2005	Joe Tan

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA

*/

class tantan_GoogleAnalytics {
	var $request;
	var $response;
	var $headers;
	var $cookies;
	var $error;
	var $loggedIn;
	
	function tantan_GoogleAnalytics ( ) {
		$this->loggedIn = false;
		
		require_once(dirname(__FILE__) ."/lib/curl.php");
		$this->request =& new TanTanCurl();

		$this->request->addHeader("User-Agent", "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)");
	}
	
	function getError ( ) {
		return $this->error;
	}
	
	function setError ( $errorString ) {
		$this->error = $errorString;
	}
	
	function isLoggedIn ( ) {
		return ( $this->loggedIn ) ? true : false;
	}
	
	function logIn ( $user, $password ) {
		if ( $this->isLoggedIn() ) $this->logOut();
		
		// PARSE LOGIN FORM
		$loginForm = "https://www.google.com/accounts/ServiceLoginBox?service=analytics&nui=1&hl=en-US&continue=http://www.google.com/analytics/home/%3Fet%3Dreset%26hl%3Den-US";
		$this->request->setMethod(HTTP_REQUEST_METHOD_GET);
		$this->request->setURL($loginForm);
		$this->request->sendRequest();
		$cookies = $this->request->getResponseCookies();
		$response = $this->request->getResponseBody();
		
		if ( strpos($response, "Google Accounts") === false ) {
			$this->setError("Unable to establish a connection to Google Analytics. Make sure your server has the proper <a href=\"http://us2.php.net/manual/en/ref.curl.php\">libcurl</a> libraries (with OpenSSL support) for PHP installed.");
			return false;
		}
		
		// PERFORM THE LOGIN ACTION
		$loginAction = "https://www.google.com/accounts/ServiceLoginBoxAuth";
		$this->request->setMethod(HTTP_REQUEST_METHOD_POST);
		$this->request->setURL($loginAction);
		$this->request->addPostData("continue", "http://www.google.com/analytics/home/?et=reset&amp;hl=en-US");
		$this->request->addPostData("service", "analytics");
		$this->request->addPostData("nui", "1");
		$this->request->addPostData("hl", "en-US");
		$this->request->addPostData("GA3T", $cookies["GA3T"]["value"]);
		$this->request->addPostData("Email", $user);
		$this->request->addPostData("Passwd", $password);
		$this->request->sendRequest();
		$this->headers = $this->request->getResponseHeader();
		$this->cookies = $this->request->getResponseCookies();
		$this->response = $this->request->getResponseBody();
		
		// FIXME I hope Google doesn't change their HTML, this looks fragile :)
		preg_match("/.*<a target=.* href=\"(.*?)\"/", $this->response, $matches);
		$nextLink = str_replace("&amp;", "&", $matches[1]);
		
		// CHECK IF LOGIN WAS OK
		if ( strpos($nextLink, "accounts/TokenAuth") === false ) {
			$this->setError("Your login and password don't match. Make sure you've typed them in correctly.");
			return false;
		}
		
		// REDIRECT TO COOKIE CHECK
		$this->request->clearPostData();
		$this->request->setMethod(HTTP_REQUEST_METHOD_GET);
		$this->request->setURL($nextLink);
		foreach ( $this->cookies as $c ) {
			$this->request->addCookie($c["name"], $c["value"]);
		}
		$this->request->sendRequest();
		$nextLink = $this->request->getResponseHeader("Location");
		
		if ( !$nextLink ) {
			$this->setError("Unable to forward to Google Analytics.");
			return false;
		}
		
		// REDIRECT TO SERVICE
		$this->request->setURL($nextLink);
		foreach ( $this->cookies as $c ) {
			$this->request->addCookie($c["name"], $c["value"]);
		}
		$this->request->sendRequest();
		
		// We're now logged in, so we can now go grab reports.
		$this->loggedIn = true;
		
		return true;
	}
	
	function logOut ( ) {
		$this->loggedIn = false;
	}
	
	function getSession ( ) {
		return $this->cookies;
	}
	
	function setSession ( $session ) {
		if ( !is_array($session) ) return false;
		
		$this->cookies = $session;
		
		foreach ( $this->cookies as $c ) {
			$this->request->addCookie($c["name"], $c["value"]);
		}
		
		$this->loggedIn = true;
		
		return true;
	}
	
	function getSiteProfiles ( $accountID = null ) {
			if ( !$this->isLoggedIn() ) return array();

			$url = "https://www.google.com/analytics/home/admin?scid={$accountID}";
			$this->request->setMethod(HTTP_REQUEST_METHOD_GET);
			$this->request->setURL($url);
			$this->request->sendRequest();
			$body = $this->request->getResponseBody();

			// This should strip out extraneous parts quickly so preg_match_all will run more quickly
			$body = substr($body, strpos($body, "name=\"profile_list\""));
			$body = substr($body, 0, strpos($body, "</select>"));
			preg_match_all("/<option.*value=\"(.*)\".*>(.*)<\/option>/isU",$body, $matches);

			$profiles = array();
			foreach ( $matches[1] as $matchNumber => $siteID ) {
				// I am not sure why this is keyed off the siteID then the siteID is then put in the value as well, this could simply be 
				// $profiles[$siteID] = $profileName or something. Just seems like an
				// extra dimension for nothing.
				$profileName = ( isset($matches[2][$matchNumber]) ) ? $matches[2][$matchNumber] : null;
				if ( $siteID != 0 && !is_null($profileName) ) $profiles[$siteID] = array("id" => $siteID, "name" => $profileName);
			}

			return $profiles;
		}

	function getAccounts ( ) {
		if ( !$this->isLoggedIn() ) return array();
		
		$url = "https://www.google.com/analytics/home/";
		$this->request->setMethod(HTTP_REQUEST_METHOD_GET);
		$this->request->setURL($url);
		$this->request->sendRequest();
		$body = $this->request->getResponseBody();
		
		// This should strip out extraneous parts quickly so preg_match_all will run more quickly
		$body = substr($body, strpos($body, "name=\"account_list\""));
		$body = substr($body, 0, strpos($body, "</select>"));
		preg_match_all("/<option.*value=\"(.*)\".*>(.*)<\/option>/isU", $body, $matches);

		$accounts = array();
		foreach ( $matches[1] as $matchNumber => $accountID ) {
			// I am not sure why this is keyed off the siteID then the siteID is then put in the value as well, this could simply be $profiles[$siteID] = $profileName or something. Just seems like an extra dimension for nothing.
//			$accountName = ( isset($matches[2][$matchNumber]) ) ? $matches[2][$matchNumber] : null;
//			if ( !is_null($accountName) ) $accounts[$accountID] = array("id" => $accountID, "name" => $accountName);
			$accountID = ( isset($matches[1][$matchNumber]) ) ? $matches[1][$matchNumber] : null;
			$accountName = ( isset($matches[2][$matchNumber]) ) ? $matches[2][$matchNumber] : null;

			if ( !is_null($accountID) & $accountID != 0 ) $accounts[$accountID] = array("id" => $accountID, "name" => $accountName);
		}
		return $accounts;
	}
	
//	function getReport ( $profile, $start, $stop, $reportType = null ) {
//		return $this->getReportXML($profile, $start, $stop, $reportType);
//	}
	
	function getReportXML ( $profile, $start, $stop, $reportType = null, $gdfmt ) {
		if ( !$this->isLoggedIn() ) return '';
		
		$url = "https://www.google.com/analytics/reporting/export?fmt=1&id={$profile}&pdr={$start}-{$stop}&cmp=average&rpt={$reportType}&gdfmt={$gdfmt}";
						
		$this->request->setMethod(HTTP_REQUEST_METHOD_GET);
		$this->request->setURL($url);
		$this->request->sendRequest();
		
		return $this->request->getResponseBody();
	}
	
}

?>
