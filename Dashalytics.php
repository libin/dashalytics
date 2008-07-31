#!/usr/bin/php
<?php

	/*
	Dashalytics: A Google Analytics Widget for Mac OSX
	Copyright (C) 2006  Robert Scriva (dashalytics@rovingrob.com)

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
	Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
	*/

	include('./lib/xml.php'); 
	require('./lib/OutputFormat.php');
	
	function reportJSONArray($xml)	{
		$outputXML["report"]["attr"] = $xml["AnalyticsReport"]["Report attr"];
		$outputXML["report"]["title"] = $xml["AnalyticsReport"]["Report"]["Title"];

		$outputXML["graph"]["data"] = $xml["AnalyticsReport"]["Report"]["Graph"]["Serie"]["Point"];
		$outputXML["graph"]["daterange"] = $xml["AnalyticsReport"]["Report"]["Title"]["PrimaryDateRange"];
		$outputXML["graph"]["xaxislabel"] = $xml["AnalyticsReport"]["Report"]["Graph"]["XAxisLabel"];
		$outputXML["graph"]["yaxislabel"] = $xml["AnalyticsReport"]["Report"]["Graph"]["YAxisLabel"];
		$outputXML["graph"]["narrative"] = $xml["AnalyticsReport"]["Report"]["Narrative"][0];
		$outputXML["graph"]["title"] = $xml["AnalyticsReport"]["Report"]["Title"];

		$totalRecords = count($xml["AnalyticsReport"]["Report"]["ItemSummary"]) / 2;
		for ($i = 0; $i < $totalRecords; $i++) { 
			$outputXML["sparkline"][$i]["data"] = $xml["AnalyticsReport"]["Report"]["Sparkline"][$i]["PrimaryValue"];
			$outputXML["sparkline"][$i]["summary"] = $xml["AnalyticsReport"]["Report"]["ItemSummary"][$i];
		}

		$totalRecords = count($xml["AnalyticsReport"]["Report"]["MiniTable"]) / 2;

		if ($xml["AnalyticsReport"]["Report"]["MiniTable"][0] == 0) {
			$outputXML["tables"][0]["data"] = $xml["AnalyticsReport"]["Report"]["MiniTable"]["Row"];
			$outputXML["tables"][0]["keycolname"] = $xml["AnalyticsReport"]["Report"]["MiniTable"]["KeyColumnName"];
			$outputXML["tables"][0]["colname"] = $xml["AnalyticsReport"]["Report"]["MiniTable"]["ColumnName"];
		} else {
			for ($i = 0; $i < $totalRecords; $i++) { 
				$outputXML["tables"][$i]["data"] = $xml["AnalyticsReport"]["Report"]["MiniTable"][$i]["Row"];
				$outputXML["tables"][$i]["keycolname"] = $xml["AnalyticsReport"]["Report"]["MiniTable"][$i]["KeyColumnName"];
				$outputXML["tables"][$i]["colname"] = $xml["AnalyticsReport"]["Report"]["MiniTable"][$i]["ColumnName"];
			}
		}

		return($outputXML);
	}

	function getStats($siteProfile, $ga, $widgetid, $daterange) {
		// last 24 hours
		//'1d', '1w', '1m', '3m', '6m', '1y', '2y'
		$gdfmt = "";
		switch ($daterange) {
			case '1d':
				$date1 = mktime(0, 0, 0, date("m"), date("d"),  date("Y"));
				$gdfmt = "nth_day";
				break;
			case '1w':
				$date1 = mktime(0, 0, 0, date("m"), date("d")-6,  date("Y"));
				$gdfmt = "nth_day";
				break;
			case '1m':
				$date1 = mktime(0, 0, 0, date("m")-1, date("d"),  date("Y"));
				$gdfmt = "nth_day";
				break;
			case '3m':
				$date1 = mktime(0, 0, 0, date("m")-3, date("d"),  date("Y"));
				$gdfmt = "nth_week";
				break;
			case '6m':
				$date1 = mktime(0, 0, 0, date("m")-6, date("d"),  date("Y"));
				$gdfmt = "nth_week";
				break;
			case '1y':
				$date1 = mktime(0, 0, 0, date("m"), date("d")+1,  date("Y")-1);
				$gdfmt = "nth_month";
				break;
			case '2y':
				$date1 = mktime(0, 0, 0, date("m"), date("d")+1,  date("Y")-2);
				$gdfmt = "nth_month";
				break;
			default:
				$date1 = mktime(0, 0, 0, date("m")-3, date("d"),  date("Y"));
				$gdfmt = "nth_week";
				break;
		}

		$date2 = mktime(0, 0, 0, date("m"), date("d"),  date("Y"));
		$start = date('Ymd', $date1);
		$stop = date('Ymd', $date2);

		$reports = array("VisitorsOverviewReport","TrafficSourcesReport","ContentReport");

		foreach($reports as $report) {
			$gaReportXML = $ga->getReportXML($siteProfile, $start, $stop, $report, $gdfmt);
			$xmlreports[] = XML_unserialize($gaReportXML); 
		}

		$i = 0;
		foreach($xmlreports as $xmlreport) {
			$outputXML[$i] = reportJSONArray($xmlreport);
			$i++;
		}

		$of = new OutputFormat();
		$json_array = $of->arrayToJSON($outputXML);

		$myFile = "data/dashalytics." . $widgetid . ".json";
		unlink($myFile);
		$fh = fopen($myFile, 'w') or die("can't open file");
		fwrite($fh, $json_array);
		// some output required for the widget to register the output handler event
		if (fclose($fh)) { echo "true";} else { echo "false";} 
	}

	function getSites ( $ga ) {
		$accounts = $ga->getAccounts();
		// get all the profiles for all the accounts
		$i = 0;
		foreach ( $accounts as $account ) {
			$profilelist[] = $ga->getSiteProfiles($account["id"]);
		}

		foreach ( $profilelist as $profiles ) {
			foreach($profiles as $profile){ 
				$siteList[$i] = Array("name"=>$profile["name"], "id"=>$profile["id"]);
				$i++;
			}
		}

		$of = new OutputFormat();
		print_r($of->arrayToJSON($siteList));


	}

	$username = urldecode($argv[1]);
	$password = urldecode($argv[2]);
	$option   = $argv[3];
	$siteid   = $argv[4];
	$widgetid   = $argv[5];
	$daterange   = $argv[6];

	require_once(dirname(__FILE__).'/tantan/lib.googleanalytics.php');

	$ga = new tantan_GoogleAnalytics();

	if (!$ga->login($username,$password)) {
		$error = "There was a problem logging into your Google Analytics account:<br />".$ga->getError();
		
		$loginErr["err"] = true;
		$loginErr["msg"] = $error;
//		echo "loginError = true; errorText = \"$error\";";

		$of = new OutputFormat();
		print_r($of->arrayToJSON($loginErr));

		return;
	} else { // login ok
           $session = $ga->getSession();
  	}

	switch ( $option ) {
		case 'sites':
			getSites($ga);
		break;
		case 'stats':
			getStats($siteid, $ga, $widgetid, $daterange);
		break;
	}

?>