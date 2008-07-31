<?php
/*
Copyright (C) 2008  Joe Tan

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

$Revision: 109 $
$Date: 2008-07-15 10:51:01 -0400 (Tue, 15 Jul 2008) $
$Author: joetan54 $
$URL: http://wordpress-reports.googlecode.com/svn/trunk/tantan-reports/wordpress-reports/lib.googleanalytics.php $
*/

if (!$path_delimiter) {
    if (strpos($_SERVER['SERVER_SOFTWARE'], "Windows") !== false) {
        $path_delimiter = ";";
    } else {
        $path_delimiter = ":";
    }
    ini_set("include_path", substr(__FILE__, 0, strrpos(__FILE__, "/")) . "/PEAR" . $path_delimiter . ini_get("include_path") );
}

class tantan_GoogleAnalytics {

    var $response;
    var $headers;
    var $cookies;
    var $req;
    var $error;
    var $loggedin;
    var $xmlParser;
    
    function tantan_GoogleAnalytics() {
        $this->loggedin = false;
        $this->xmlParser = false;

        if (function_exists('curl_init')) {   
            if (!class_exists('TanTanHTTPRequestCurl')) require_once(dirname(__FILE__).'/lib/curl.php');     
            $this->req =& new TanTanHTTPRequestCurl();
        /*
        } elseif (file_exists(ABSPATH . 'wp-includes/class-snoopy.php')) {
			if (!class_exists('Snoopy')) require_once( ABSPATH . 'wp-includes/class-snoopy.php' );
	        if (!class_exists('TanTanHTTPRequestSnoopy')) require_once (dirname(__FILE__).'/../lib/snoopy.php');
	        $this->req =& new TanTanHTTPRequestSnoopy();
	    */
		} else {
            require_once("HTTP/Request.php");
            $this->req =& new HTTP_Request();
        }

        $this->req->addHeader("User-Agent", "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)");
    }
    
    function getError() {
        return $this->error;
    }
    function setError($err) {
        $this->error = $err;
    }
    function isLoggedIn() {
        return $this->loggedin ? true : false;
    }
    function checkIsLoggedIn() { // query the google service to make sure we're logged in
        if (!$this->isLoggedIn()) return false;
        $this->req->setMethod('GET');
        $this->req->setURL("https://www.google.com/analytics/home/admin");
        $this->req->sendRequest();
        $response = $this->req->getResponseBody();
        if (eregi('Sign in to your Google Account', $response)) {
            return false;
        } else {
            return true;
        }
    }
    function login($user, $passwd) {


        if ($this->isLoggedIn()) {
            $this->logout();
        }
        
        /*
            PARSE LOGIN FORM
        */
        
        $loginForm = "https://www.google.com/accounts/ServiceLoginBox?service=analytics&nui=1&hl=en-US&continue=http://www.google.com/analytics/home/%3Fet%3Dreset%26hl%3Den-US";
        $this->req->setMethod('GET');
        $this->req->setURL($loginForm);
        $this->req->sendRequest();
        $cookies = $this->req->getResponseCookies();
        $response = $this->req->getResponseBody();

        if (!ereg('Google Accounts', $response)) {
            $this->setError('Unable to establish a connection to Google Analytics. Make sure your server has the proper <a href="http://us2.php.net/manual/en/ref.curl.php">libcurl</a> libraries (with OpenSSL support) for PHP installed.');
            return false;
        }
        $hidden['GA3T'] = $cookies['GA3T']['value'];

        /*
            PERFORM THE LOGIN ACTION
        */
        $loginAction = "https://www.google.com/accounts/ServiceLoginBoxAuth";
        $this->req->setMethod('POST');
        $this->req->setURL($loginAction);
        $this->req->addPostData('continue', 'http://www.google.com/analytics/home/?et=reset&amp;hl=en-US');
        $this->req->addPostData('service', 'analytics');
        $this->req->addPostData('nui', '1');
        $this->req->addPostData('hl', 'en-US');
        $this->req->addPostData('GA3T', $hidden['GA3T']);
        $this->req->addPostData('Email', $user);
        $this->req->addPostData('Passwd', $passwd);
        $this->req->sendRequest();
        $this->headers = $this->req->getResponseHeader();
        $this->cookies = $this->req->getResponseCookies();
        $this->response = $this->req->getResponseBody();

        // ugly
        $nextLink = ereg_replace(".*<a href=\"(.*)\" target=.*", "\\1", $this->response);
        $nextLink = ereg_replace('amp;', '', $nextLink);

        /*
            CHECK IF LOGIN WAS OK
        */
        if (!ereg('accounts/TokenAuth', $nextLink)) {
            $this->setError("Your login and password don't match. Make sure you've typed them in correctly.");
            return false;
        }
        

        /*
            REDIRECT TO COOKIE CHECK
        */
        $this->req->clearPostData();
        $this->req->setMethod('GET');
        $this->req->setURL($nextLink);
        foreach ($this->cookies as $c) {
            $this->req->addCookie($c['name'], $c['value']);
        }
        $this->req->sendRequest();
        $nextLink = $this->req->getResponseHeader('Location');
        if (!$nextLink) {
            //print_r($this->req->raw);
            $this->setError("Unable to forward to Google Analytics.");
            return false;
        }

        /*
            REDIRECT TO SERVICE
        */
        $this->req->setURL($nextLink);
        foreach ($this->cookies as $c) {
            $this->req->addCookie($c['name'], $c['value']);
        }
        $this->req->sendRequest();

        /*
            We're now logged in, so we can now go grab reports.
        */

        $this->loggedin = true;
        return true;
    }
    
    function logout() {
        $this->loggedin = false;
    }
    
    function getSession() {
        return $this->cookies;
    }
    function setSession($session) {
        if (!is_array($session)) {
            return false;
        }
        $this->cookies = $session;
        foreach ($this->cookies as $c) {
            $this->req->addCookie($c['name'], $c['value']);
        }
        $this->loggedin = true;
        return true;
    }
    
    function getSiteProfiles($accountID = null) {
        if (!$this->isLoggedIn()) {
            return array();
        }
    
        $url = "https://www.google.com/analytics/settings/home?scid={$accountID}";
        $this->req->setMethod('GET');
        $this->req->setURL($url);
        $this->req->sendRequest();
        $body = $this->req->getResponseBody();
        
        // contrib from rob @ http://dashalytics.rovingrob.com/
        // This should strip out extraneous parts quickly so preg_match_all will run more quickly
			$body = substr($body, strpos($body, "account_profile_selector"));
			$body = substr($body, 0, strpos($body, "</form>"));
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
	
    
    // contrib from rob @ http://dashalytics.rovingrob.com/
    function getAccounts () {
		if ( !$this->isLoggedIn() ) return array();
		
		$url = "https://www.google.com/analytics/settings/";
		$this->req->setMethod('GET');
		$this->req->setURL($url);
		$this->req->sendRequest();
		$body = $this->req->getResponseBody();

		if (!strpos($body, "submitAccountSelectorForm")) {
		    return array();
		}

		// This should strip out extraneous parts quickly so preg_match_all will run more quickly
		$body = substr($body, strpos($body, "submitAccountSelectorForm"));
		$body = substr($body, 0, strpos($body, "<optgroup"));
		preg_match_all("/<option.*value=\"(.*)\".*>(.*)<\/option>/isU", $body, $matches);
		
		$accounts = array();
		foreach ( $matches[1] as $matchNumber => $accountID ) {
			// I am not sure why this is keyed off the siteID then the siteID is then put in the value as well, this could simply be $profiles[$siteID] = $profileName or something. Just seems like an extra dimension for nothing.
			$accountName = ( isset($matches[2][$matchNumber]) ) ? $matches[2][$matchNumber] : null;
			if ((int) $accountID <= 0) {
                $accountName = null;
			}

			if ( !is_null($accountName) ) $accounts[$accountID] = array("id" => $accountID, "name" => $accountName);
		}
		
		return $accounts;
	}
	
    function getReport($profile, $start, $stop, $reportType='') {
        return $this->_parseCSV($this->getReportData($profile, $start, $stop, $reportType, 'CSV'));
        //return $this->_parseXML($this->getReportData($profile, $start, $stop, $reportType, 'XML'));
    }
    
    function getReportData($profile, $start, $stop, $reportType='', $mode='XML') {
        if (!$this->isLoggedIn()) {
            return '';
        }
        if ($mode == 'CSV') {
            $reportMode = 2;
        } else {
            //$reportMode = 7; // xml
			$reportMode = 1;

        }
        $start2 = date('Ymd', strtotime($start) - 691200);//604800
        $stop2 =  date('Ymd', strtotime($stop) - 691200);
		$url = "https://www.google.com/analytics/reporting/export?fmt=$reportMode&id=$profile&pdr=$start-$stop";

        $compare = "&cdr=$start2-$stop2&cmp=date_range";
        switch ($reportType) {
						case 'VisitorsOverviewReport':
						case 'TrafficSourcesReport':
						case 'ContentReport':
						$url .= "&rpt=".$reportType;
						break;
            case 'referals':
            case 'referrals':
                $url .= $compare."&cmp=date_range&rpt=ReferringSourcesReport&trows=25";
            break;
            case 'dailyvisitorscompare':
            case 'dailyvisitors':
				$url .= "&rpt=VisitorsOverviewReport";
            break;
            case 'visitspageviewscompare':
                $url .= $compare;
            case 'visitspageviews':
			case 'visits':
				$url .= "&rpt=VisitsReport";
			break;
			case 'avgpageviews':
				$url .= "&rpt=AveragePageviewsReport";
			break;
			case 'pageviews':
				$url .= "&rpt=PageviewsReport";
            break;
            case 'content':
				$url .= $compare."&q=outgoing&qtyp=1&rpt=TopContentReport&trows=50";
            break;
            case 'outbound':
				$url .= "&q=outgoing&qtyp=0&tst=0&gidx=1&rpt=TopContentReport";
            break;
            case 'entrance':
				$url .= "&rpt=EntrancesReport";
            break;
			case 'newreturn':
				$url .= "&rpt=VisitorTypesReport";
			break;
            case 'executive':
            default:
				$url .= $compare."&trows=100&rpt=DashboardRequest";
            break;
        }

        $this->req->setMethod('GET');
        $this->req->setURL($url);
        $this->req->sendRequest();

        return $this->req->getResponseBody();
    }
    function getTrackingCode($account, $profile) {
        $url = "https://www.google.com/analytics/home/admin?rid=$profile&aid=1121&vid=1104&scid=$account";
        $this->req->setMethod('GET');
        $this->req->setURL($url);
        $this->req->sendRequest();
        $html = $this->req->getResponseBody();
		if (preg_match('/<div id="new_tracking_code"[^>]*>.*<textarea[^>]*>(.*)<\/textarea>.*<\/div>/smU', $html, $matches)) {
			return html_entity_decode($matches[1]);
		} else {
			return false;
		}
    }

	function csv_string_to_array($str){
		$expr="/,(?=(?:[^\"]*\"[^\"]*\")*(?![^\"]*\"))/"; 
		$results=preg_split($expr,trim($str)); 
		$results = preg_replace("/,/", "", $results);
		return preg_replace("/^\"(.*)\"$/","$1",$results);
	}
	
    function _parseCSV($csv) {
        $lines = explode("\n", $csv);
        $reportMeta = array();
        $currentSection = false;
        $sections = array();

        $profileName        = false;
        $reportExportName   = false;
        $dateRange          = false;
        $dateRange2         = false;
        
        $columns            = array();
        $numRecords = 0;
		
		for($i=0; $i<count($lines); $i++) if ($lines[$i] != '') {
            $line           = trim($lines[$i]);
            $isComment      = ($line{0} == '#');
            $isCommentLine  = (strpos($line, '# -') !== false);
            $isDate         = (eregi('^"[a-z]', $line));
            
            
            if (!$reportMeta) {
                if (!$isComment) {
                    if (!$profileName) {
                        $profileName = $line;
                    } elseif (!$reportExportName) {
    				    $reportExportName = $line;
        			} elseif ($isDate && !$dateRange) {
        				$dateRange = $line;
        				list($dateStart, $dateStop) = explode('","', $dateRange, 2);
        				$dateStart = strtotime(trim($dateStart, '"'));
        				$dateStop = strtotime(trim($dateStop, '"'));
        			} elseif ($isDate && !$dateRange2) {
        			}
    			} elseif ($profileName) {
                    $reportMeta = array(
                        'profileName' => $profileName,
                        'reportExport' => $reportExportName,
                        'dateRange' => array($dateStart, $dateStop),
    			    );
    			}
            } else {
                if (!$currentSection) {
                    if (!$isCommentLine) {
                        if ($isComment) $currentSection = substr($line, 2);
                        $columns = array();
                        $section = array();
                        $dateStartGraph_time = false;
                        $i += 1;
						$numRecords = 0;
                    }
                } elseif ($isComment && $section && $columns) {
                    $sections[$currentSection]['records'] = $section;
					$sections[$currentSection] = array_merge( $sections[$currentSection], $this->_prepareRecords($section) );
                    $currentSection = false;
                } elseif ($isComment) {
                } elseif ($isDate) {
                    if (eregi('graph', $currentSection)) { // sometimes date ranges for graph will not match up 100% with requested dates
                        list($dateStartGraph, $dateStopGraph) = explode(' - ', array_pop($this->csv_string_to_array($line)));
                        if ($dateStartGraph) $dateStartGraph_time = strtotime($dateStartGraph);
                    }
                } elseif (!$columns) {
                    $columns = explode(',', $line);
                } elseif (!$isComment) {
                    $isComparison = (strpos($line, ',') === false) && (strpos($lines[$i+3], '% Change') === 0);
                    
                    if ($isComparison) {
                        //$chunks = explode(',', $lines[$i+2]);
						$chunks = $this->csv_string_to_array($lines[$i+2]);
                        $chunks[0] = $line;
                        //$change = explode(',', $lines[$i+3]);
						$change = $this->csv_string_to_array($lines[$i+3]);
                        //$section[] = array_push(array_combine($columns, $chunks), array('Change' => $change));;
                        $tmp = $this->array_combine($columns, $chunks);
                        $tmp[array_shift($change)] = $this->array_combine(array_slice($columns, 1), $change);
						
                        $section[] = $tmp;
                        $i+=3;
                    } else {
                        //$chunks = explode(',', $line);
						$chunks = $this->csv_string_to_array($line);
						$extra = array();
						if (eregi('Graph', $currentSection)) {
							$extra['UnixTime'] = ($dateStartGraph_time ? $dateStartGraph_time : $dateStart) + (86400*$numRecords);
							$extra['Date'] = date('M d', $extra['UnixTime']);
							
						}
						if (!$extra['UnixTime'] || ($extra['UnixTime'] && $extra['UnixTime'] <= $dateStop && $extra['UnixTime'] >= $dateStart)) {
                            $section[] = array_merge($this->array_combine($columns, $chunks), $extra);
						}
                    }
					$numRecords++;
                }
            }
        }
        return $sections;
    }
    // similar to php native, except $values doesnt have to have the same number of entries as $keys
    function array_combine($keys, $values) {
        $res = array();
        if (is_array($keys) && is_array($values)) foreach ($keys as $k=>$v) {
            if ($values[$k]) $res[$v] = $values[$k];
        }
        return $res;
    }
	function _prepareRecords($records) {
		$section = array();
		foreach ($records as $rec) {
            if (is_numeric($rec['Visits'])) {
                if ($rec['Visits'] > $section['MaxVisits']) {
                    $section['MaxVisits'] = $rec['Visits'];
                }
            }
            if (is_numeric($rec['Pageviews'])) {
                if ($rec['Pageviews'] > $section['MaxPageviews']) {
                    $section['MaxPageviews'] = $rec['Pageviews'];
                }
            }
            if (is_numeric($rec['Entrances'])) {
                if ($rec['Entrances'] > $section['MaxEntrances']) {
                    $section['MaxEntrances'] = $rec['Entrances'];
                }
            }
            if (is_numeric($rec['Pages/Visit'])) {
                if ($rec['Pages/Visit'] > $section['MaxPages/Visit']) {
                    $section['MaxPages/Visit'] = $rec['Pages/Visit'];
                }
            }
            if (is_numeric($rec['P/Visit'])) {
                if ($rec['P/Visit'] > $section['MaxP/Visit']) {
                    $section['MaxP/Visit'] = $rec['P/Visit'];
                }
            }
            if (is_numeric($rec['Uniq. Views'])) {
                if ($rec['Uniq. Views'] > $section['MaxUniq. Views']) {
                    $section['MaxUniq. Views'] = $rec['Uniq. Views'];
                }
            }
            if (is_numeric($rec['Unique Pageviews'])) {
                if ($rec['Unique Pageviews'] > $section['MaxUnique Pageviews']) {
                    $section['MaxUnique Pageviews'] = $rec['Unique Pageviews'];
                }
            }

        }
		return $section;
	}
    
    
    function _parseCSVLegacy($csv) {
        $lines = explode("\n", $csv);
        $columns = array();
        $records = array();
		$numRecords = 0;
        $reportExportName = false;
        $reportName = false;
		$profileName = false;
		$dateStart = false;
		$dateStop = false;
        $datasets = array();
        foreach ($lines as $line) {
            if (trim($line) && (strpos($line, '# -') === false)) {
                if ($line{0} == '#') {
                    if ($pos = strpos($line, 'Date Range: ')) {
                        $dateRange = trim(substr($line, $pos + 12));
                    } elseif ($pos = strpos($line, 'Profile Name: ')) {
                        $profileName = trim(substr($line, $pos + 14));
                    } elseif ($pos = strpos($line, 'Report Name: ')) {
                        $reportExportName = trim(substr($line, $pos + 13));
                    } else {
                        if ($reportName) {
                            $datasets[] = array(
                                'title' => $reportName,
                                'ncols' => count($columns),
                                '_cols' => $columns,
                                'record' => $records);
                        }
                        $columns = array();
                        $records = array();
						$numRecords = 0;
                        $reportName = trim(substr($line, 2));
                    }
				} elseif (!$profileName) {
					$profileName = trim($line);
				} elseif (!$reportExportName) {
					$reportExportName = trim($line);
				} elseif (!$dateRange) {
					$dateRange = trim($line);
					list($dateStart, $dateStop) = explode('","', $dateRange, 2);
					$dateStart = strtotime(trim($dateStart, '"'));
					$dateStop = strtotime(trim($dateStop, '"'));
				} elseif ($line{0} == '"') {
                } elseif (count($columns) <= 0) {
                    $columns = explode(',', $line);
                    foreach ($columns as $ck => $cv) {
                        $columns[$ck] = trim($cv);
                    }
					if (eregi('Graph', $reportName)) {
						array_unshift($columns, 'Date Range');
					}
                } else {
                    $record = array();
					if (eregi('Graph', $reportName)) {
						$line = date('M d', $dateStart + (86400*$numRecords)) . ",$line";
					}//print_r($record);
                    //$record['name'] = $columns[$i];
                    $tmpCols = explode(',', $line);
                    foreach($tmpCols as $i => $value) {
                        if ($i > 0) $record['value'.$i] = trim($value);
                        else $record['name'] = trim($value);
                    }
                    $records[] = $record;
					$numRecords++;
                }
            }
        }
        if (!$reportExportName) $reportExportName = $reportName;
        //list($dateStart, $dateStop) = split(' - ', $dateRange);
        $data = array(
            'title' => $reportExportName,
            'date' => array(
                'range' => $dateRange,
                'start' => $dateStart,
                'stop' => $dateStop,
                ),
            );
        $datasets[] = array(
            'title' => $reportName,
            'ncols' => count($columns),
            '_cols' => $columns,
            'record' => $records);
            
        foreach ($datasets as $dk => $dataset) {
            foreach ($dataset['_cols'] as $i => $key) $dataset['column'.($i+1)] = $key;
            unset($dataset['_cols']);
            $data[$dataset['title']] = $this->_dataset($dataset);
        }
            
        // check if more than one dataset??
        return $data;
    }
    
    // returns a properly formatted array for current line
    function _csvLine($line, $columns) {
        
    }
    // parse XML into a report object
    function _parseXML($xml) {
        if (!$this->xmlParser) {
            require_once(dirname(__FILE__).'/lib.xml.php');
            $this->xmlParser = new tantan_xml(false, true, true);
            $this->xmlParser->_replace = array();
            $this->xmlParser->_replaceWith = array();
        }        
        $data = $this->xmlParser->parse($xml);
        $data = $data['urchindata'];
        $report = array();
        $report['title'] = trim($data['report']);
        list($dateStart, $dateStop) = split(' - ', $data['date']);
        $report['date'] = array(
            'range' => $data['date'],
            'start' => $dateStart,
            'stop' => $dateStop,
        );
        
        // more than one dataset
        if (is_array($data['dataset'][0])) {
            foreach ($data['dataset'] as $dataset) {
                $report[trim($dataset['title'])] = $this->_dataset($dataset);
            }
        } else {
            $report[trim($data['dataset']['title'])] = $this->_dataset($data['dataset']);
        }
        return $report;
        
    }
    function _dataset($dataset) {
        $return = array();
        $return['title'] = trim($dataset['title']);
        $return['columns'] = array();
        $return['records'] = array();
        $i = 1;
        while (isset($dataset['column'.$i])) {
            $return['columns'][] = $dataset['column'.$i];
            $i++;
        }
        if (is_array($dataset['record'])) foreach ($dataset['record'] as $record) {
            $return['records'][] = $this->_record($record, $return['columns']);
        }

        foreach ($return['records'] as $rec) {
            if (is_numeric($rec['Visits'])) {
                if ($rec['Visits'] > $return['MaxVisits']) {
                    $return['MaxVisits'] = $rec['Visits'];
                }
            }
            if (is_numeric($rec['Pageviews'])) {
                if ($rec['Pageviews'] > $return['MaxPageviews']) {
                    $return['MaxPageviews'] = $rec['Pageviews'];
                }
            }
            if (is_numeric($rec['Entrances'])) {
                if ($rec['Entrances'] > $return['MaxEntrances']) {
                    $return['MaxEntrances'] = $rec['Entrances'];
                }
            }
            if (is_numeric($rec['Pages/Visit'])) {
                if ($rec['Pages/Visit'] > $return['MaxPages/Visit']) {
                    $return['MaxPages/Visit'] = $rec['Pages/Visit'];
                }
            }
            if (is_numeric($rec['P/Visit'])) {
                if ($rec['P/Visit'] > $return['MaxP/Visit']) {
                    $return['MaxP/Visit'] = $rec['P/Visit'];
                }
            }
            if (is_numeric($rec['Uniq. Views'])) {
                if ($rec['Uniq. Views'] > $return['MaxUniq. Views']) {
                    $return['MaxUniq. Views'] = $rec['Uniq. Views'];
                }
            }
        }
        
        return $return;
    }
    function _record($rec, $cols) {
        $return = array();
        $len = count($cols);
        $return[$cols[0]] = $rec['name'];
        if (ereg('Date Range', $cols[0])) { // parse dates
            $return['Date'] = date('Ymd', strtotime($rec['name']));
        } elseif (ereg('Country/Region/City', $cols[0])) { // parse region info
            list($country, $region, $city) = split("\|", $rec['name']);
            $return['Country'] = $country;
            $return['Region'] = $region;
            $return['City'] = $city;
        }
        for ($i=1;$i<$len;$i++) {
            $return[trim($cols[$i])] = $rec['value'.($i)];
        }
        return $return;
    }
}
class tantan_GACipher {
    function encrypt ($text) {
        if (constant('TANTAN_GA_ENCRYPT_PWD') && function_exists('mcrypt_encrypt')) {
            return base64_encode(@mcrypt_encrypt(MCRYPT_RIJNDAEL_256, DB_PASSWORD, $text, MCRYPT_MODE_ECB));
        } else {
            return $text;
        }
    }
    function decrypt ($text) {
        if (constant('TANTAN_GA_ENCRYPT_PWD') && function_exists('mcrypt_decrypt')) {
            return trim(@mcrypt_decrypt(MCRYPT_RIJNDAEL_256, DB_PASSWORD, base64_decode($text), MCRYPT_MODE_ECB));
        } else {
            return $text;
        }
    }
}
?>