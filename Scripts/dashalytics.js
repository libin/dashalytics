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

var siteList = new Object();
var username = "";
var password = "";
var siteID = "0";
var siteName = "";
var lastChecked = 0;
var siteListRetrieved = false;
var siteReport = "visit";
var currentChartMode = '3m';

var updateFreq = 600; // max update every 5 * 60 seconds or 5 minutes
var homeURL = "http://dashalytics.rovingrob.com/";
var donateURL = "http://dashalytics.rovingrob.com/donate/";
var analyticsSiteURL = "https://www.google.com/analytics/reporting/dashboard?id=";
var newVerURL = "http://dashalytics.rovingrob.com/version.txt";
var downloadURL = "http://dashalytics.rovingrob.com/download/";
var timerInterval = 18000; // check for new version update every 5 * 60 minutes * 60 seconds or 18000 seconds or 5 hrs

var loginError = false;
var errorText = "";

var progIndFront;
var progIndBack;

var bigGraphElements = Array('visitov');

var visitOverviewNumber = Array(true,false,true,true,true,true,true);
var visitOverviewNumberFormat = Array('#,#','','#,#','0.000','','0.00%','0.00%');

var contentOverviewNumber = Array(true,true,true);
var contentOverviewNumberFormat = Array('#,#','#,#','0.00%');

var trafficOverviewNumber = Array(true,true,true);
var trafficOverviewNumberFormat = Array('#,#','#,#','#,#');

function doPreloadSetup() {

	progIndFront = new ProgressIndicator($('front-spinner'), "./Images/Spinner.white/");
	progIndBack = new ProgressIndicator($('back-spinner'), "./Images/Spinner.white/");

	window.resizeTo(400, 320);

	setupPrefs();
	setupGliders();
	setupChartController(currentChartMode);
	setupReportController(siteReport);
	
//	setupScrollArea();
	
	// get the current version 
	currentVersion = trim(getPlistProperty("Info", "CFBundleVersion"));
	$('version').innerHTML = "v" + currentVersion;

	// Localize the interface
	$('sitelink').innerText = getLocalizedString('No Site Selected');
	$('username-label').innerText = getLocalizedString('Username:');
	$('password-label').innerText = getLocalizedString('Password:');

	// check the if the widget is the most recent version
	checkForUpdate();
	// check every timerInterval seconds
	timer = setInterval ('checkForUpdate();', (timerInterval * 1000) );

}

function setupPrefs() {
	if (window.widget) {
		KeyChainAccess.setAppName("Dashalytics Widget");

		// get the preference
		// Username
		var pref = widget.preferenceForKey(createkey("username"));
		if (pref != null) {
			username = pref;
			$("username-input").value = username;
		}

		// Password from the KeyChain
		if (username.length != 0 && username != null) {
			password = KeyChainAccess.loadPassword(username);
			$("password-input").value = password;
		}

		// SiteID
		pref = widget.preferenceForKey(createkey("siteID"));
		if (pref != null) {
			siteID = pref;
		}
		
		// SiteName
		pref = widget.preferenceForKey(createkey("siteName"));
		if (pref != null) {
			siteName = pref;
			$('sitelink').innerHTML = siteName;
		}

		// SiteView
		pref = widget.preferenceForKey(createkey("siteView"));
		if (pref != null) {
			siteView = pref;
		}

		// Chartmode
		pref = widget.preferenceForKey(createkey("chartmode"));
		if (pref != null) {
			currentChartMode = pref;
		}

		// SiteList
		pref = widget.preferenceForKey(createkey("siteList"));
		if (pref != null) {
			var siteListEnc = pref;
			siteList = decodeSiteList(siteListEnc);
			populateSiteList();
			siteListRetrieved = true;
		}
	}

}

var everBeenCalled = false;
function specialFirstLoad() {
	doPreloadSetup();

	if (!everBeenCalled) {
		onshow();
		everBeenCalled = true;
	}
}

function onremove() {
	// your widget has just been removed from the layer
	// remove any preferences as needed
	// widget.setPreferenceForKey(null, "your-key");
	if (window.widget) {
		widget.setPreferenceForKey(null,createkey("username"));
		widget.setPreferenceForKey(null,createkey("siteID"));
		widget.setPreferenceForKey(null,createkey("siteName"));
		widget.setPreferenceForKey(null,createkey("siteList"));
		widget.setPreferenceForKey(null,createkey("siteView"));
		widget.setPreferenceForKey(null,createkey("chartmode"));
	}
}

function onhide() {
	// your widget has just been hidden stop any timers to
	// prevent cpu usage

}

function onshow() {
	// your widget has just been shown.  restart any timers
	// and adjust your interface as needed
	// get analytics information

	everBeenCalled = true;

	getAnalyticsData();
}

function showBack(event) {
	// your widget needs to show the back

	var front = $("front");
	var back = $("back");

	if (window.widget)
		widget.prepareForTransition("ToBack");
	
	front.style.display="none";
	back.style.display="block";
	
	if (window.widget)
		setTimeout('widget.performTransition();', 0);
}

function showFront(event) {
	// your widget needs to show the front
	var front = $("front");
	var back = $("back");

	if (window.widget) 
		widget.prepareForTransition("ToFront");

	front.style.display="block";
	back.style.display="none";

	if (window.widget)
		setTimeout('widget.performTransition();', 0);

	if (siteListRetrieved) {	
		var newSiteID = $("sitelist").options[$("sitelist").options.selectedIndex].value;
		var newSiteName = $("sitelist").options[$("sitelist").options.selectedIndex].text;

		if (newSiteID != siteID) {
			siteID = newSiteID;
			siteName = newSiteName;
			lastChecked = 0;
			// clear the front side
//			emptyStatTable();
		}

		if (window.widget) {
			widget.setPreferenceForKey(siteID,createkey("siteID"));
			widget.setPreferenceForKey(siteName,createkey("siteName"));
			widget.setPreferenceForKey(encodeSiteList(siteList),createkey("siteList"));
		}
	}

	getAnalyticsData();

}

if (window.widget) {
	widget.onremove = onremove;
	widget.onhide = onhide;
	widget.onshow = onshow;
}

function setupGliders () {
	var vo_glider = new Glider($('glider'), {duration:0.4});
	Event.observe($('but_next'), 'click', function(event){ vo_glider.next() });
	Event.observe($('but_previous'), 'click', function(event){ vo_glider.previous() });
}

function setupScrollArea () {
	var gMyScrollbar = new AppleHorizontalScrollbar(
        document.getElementById("visitov-scroller")
    );
 
    var gMyScrollArea = new AppleScrollArea(
        document.getElementById("visitov-graph")
    );
 
    gMyScrollArea.addScrollbar(gMyScrollbar);
    
}

function setupChartController (mode) {
	var array = ['1d', '1w', '1m', '3m', '6m', '1y', '2y'];
	var c = array.length;
	
	for (var i = 0; i < c; ++i) {
		var m = array[i];
		var div = document.getElementById(m+'text');
		div.innerHTML = getLocalizedString(m);
		
		if (m == mode) {
			currentChartSelect = div.parentNode;
			currentChartSelect.setAttribute ("class", "selected");
		}
	}
}

function setupReportController(mode) {
	var array = ['visit', 'content', 'traffic'];
	var c = array.length;
	
	for (var i = 0; i < c; ++i) {
		var m = array[i];
		var div = document.getElementById(m+'_report');
		div.innerHTML = getLocalizedString(m);
		
		if (m == mode) {
			$('current_report_name').innerHTML = getLocalizedString(m);
		}
	}
}


/********** Get The List Of Sites **********/
function getSiteList() {
	username = $("username-input").value;
	password = $("password-input").value;

	$("error").innerHTML = "&nbsp;";

	myList = $("sitelist");
  	for	(var i = myList.options.length; i >= 0; i--) myList.options[i] = null;

	siteList = new Array();

	if (window.widget)
		widget.system("./Dashalytics.php \"" + encodeURIComponent(username) + "\" \"" + encodeURIComponent(password) + "\" sites ",getSiteListEH).onreadoutput = getSiteListOH;

	progIndBack.start();
}

function getSiteListEH() {
	progIndBack.stop();
}

function getSiteListOH(php_out) {
	progIndBack.stop();
	siteList = eval(php_out);

	if (siteList.err == true) {
		$("error").innerHTML = siteList.msg;
		siteList.err = false;
		return(0);
	}

	if (window.widget) {
		widget.setPreferenceForKey(username,createkey("username"));
//		widget.setPreferenceForKey(password,createkey("password"));
		KeyChainAccess.savePassword(username,password);
	}

	populateSiteList();
	siteListRetrieved = true;
}

function populateSiteList() {
	myList = $("sitelist");

 	for	(var i = myList.options.length; i >= 0; i--) myList.options[i] = null;

	var i = 0;
	siteList.each(function(item) {
		
		if (i == 0) { defaultSelected = true; Selected = true; } else { defaultSelected = false; Selected = false; }
		if (item.id == siteID) { defaultSelected = true; Selected = true; } else { defaultSelected = false; Selected = false; }
		myOption = new Option(item.name, item.id, defaultSelected, Selected);
		myList.options[myList.options.length] = myOption;
		i++;
	});


	return(0);
}

/********** Get The Selected Sites Stats (Say That 3 Times Fast) **********/

function getAnalyticsData() {

	if (siteID && siteID > 0 && timeDiff(lastChecked, getCurrentTime()) > updateFreq ) {
		$('sitelink').innerHTML = siteName;

		if (window.widget)
			widget.system("./Dashalytics.php \"" + encodeURIComponent(username) + "\" \"" + encodeURIComponent(password) + "\" stats " + siteID + " " + widget.identifier + " " + currentChartMode,getSiteStatsEH).onreadoutput = getSiteStatsOH;
		
		//document.getElementById("dashinfo").style.display = "none";
		progIndFront.start();
	}
}

function getAnalyticsDataDayClick() {

	if (siteID && siteID > 0 ) {
		$('sitelink').innerHTML = siteName;

		if (window.widget)
			widget.system("./Dashalytics.php \"" + encodeURIComponent(username) + "\" \"" + encodeURIComponent(password) + "\" stats " + siteID + " " + widget.identifier + " " + currentChartMode,getSiteStatsEH).onreadoutput = getSiteStatsOH;
		
		//document.getElementById("dashinfo").style.display = "none";
		progIndFront.start();
	}
}

function getSiteStatsEH(php_out) {
	progIndFront.stop();
}

function getSiteStatsOH(php_out) {
	lastChecked = getCurrentTime();

// Pass current time parmeter to stop caching!! Stupid ajax
	new Ajax.Request('data/dashalytics.'+ widget.identifier +'.json', {
		method: 'get',
		parameters: {currenttime: getCurrentTime(), widgetid: widget.identifier, random: Math.random() },
		onSuccess: function(transport) {
			progIndFront.stop();
			if (php_out) {
				lastChecked = getCurrentTime();
			}
			var siteData = transport.responseText.evalJSON();					
			populateStatTable(siteData);
		},
		onFailure: function(){ 
			progIndFront.stop();
			alert('Something went wrong...');
		}
	});
	progIndFront.stop();
}

function populateStatTable(data) {
	
	switch(siteReport){
		case "visit":
			drawBigGraphs(data[0].graph);
			drawSparklines(data[0].sparkline, visitOverviewNumber, visitOverviewNumberFormat);
			drawTables(data[0].tables);
			break;

		case "traffic":
			drawBigGraphs(data[1].graph);
			drawSparklines(data[1].sparkline, trafficOverviewNumber, trafficOverviewNumberFormat);
			drawTables(data[1].tables);
			break;

		case "content":
			drawBigGraphs(data[2].graph);
			drawSparklines(data[2].sparkline, contentOverviewNumber, contentOverviewNumberFormat);
			drawTables(data[2].tables);
			break;

	}
	
}

function switch_report(reportname) {
	$('current_report_name').innerHTML = $(reportname + '_report').innerHTML;
	siteReport = reportname;
	getSiteStatsOH(null);
}

function drop_down_menu(menu, event) {
	if (!menu.visible()) {
		menu.show();
		var justChanged = true;

		menu.offclick = function (e) {
			if (!justChanged) {
				this.hide();
				Event.stopObserving(document,'click',this.offclick);
			} else {
				justChanged = false;
			}
		}.bind(menu);
		Event.observe(document, 'click', menu.offclick);
	}
	return false;
}

var currentChartSelect = null;
function dayclick(event, td, tag) {
	if (td == currentChartSelect && td != null) return;
	
	if (currentChartSelect != null) {
		currentChartSelect.setAttribute ("class", "");
	}
	
	if (currentChartSelect != td) {
		td.setAttribute ("class", "selected");
		currentChartSelect = td;
	
		currentChartMode = tag;
		getAnalyticsDataDayClick();
		
		if (window.widget)
			widget.setPreferenceForKey(tag, createkey("chartmode"));
		
	}
}


/******** Preferences *********/
function createkey(key) {
	return widget.identifier + "-" + key;
} 

function encodeSiteList(decSiteList) {
	return (decSiteList.toJSON());
}

function decodeSiteList(encSiteList) {
	var sitelistJSON = encSiteList.evalJSON()
	return (sitelistJSON.toArray());
}

/******** Other Functions *********/
function addCommas(nStr) {
	nStr += '';
	x = nStr.split('.');
	x1 = x[0];
	x2 = x.length > 1 ? '.' + x[1] : '';
	var rgx = /(\d+)(\d{3})/;
	while (rgx.test(x1)) {
		x1 = x1.replace(rgx, '$1' + ',' + '$2');
	}
	return x1 + x2;
}

function removeCommas(nStr) {
	//remove any commas
	nStr = nStr.replace(/,/g,"");

	//remove any spaces
	nStr = nStr.replace(/\s/g,"");

	return nStr;
}

function sort_desc(a, b) {
	return b - a;
}

function sort_asc(a, b) {
	return a - b;
}

function drawBars(e,barWidth, barData, barDataMax){
	// get the canvas element and then get an instance of that canvas
	var canvas = $(e);
	var context = canvas.getContext("2d");

	context.clearRect(0, 0, canvas.width, canvas.height); // clear & reset the canvas

	var barSpacing = 1; // bar spacing at 1px
	var barDataArr = barData.split(","); // split the incoming data string into an array
	var cnt;

	for (var i in barDataArr) {
		bs = (barWidth + barSpacing) * i; // lets start drawing this bar from here
		cnt = barDataMax > 0 ? Math.round((barDataArr[i] / barDataMax) * canvas.height): 0; // work out the % height of the bar
		cnt = cnt == 0 ? 0.2 : cnt; // if it's a 0 then make a tiny mark

		if (barDataArr[i] == barDataMax) { 
			context.fillStyle = DarkOrange; // set the fill color to rgb
		} else if (cnt == 0.2) {
			context.fillStyle = Black; // set the fill color to rgb
		} else {
			context.fillStyle = LightOrange; // set the fill color to rgb
		}

		context.fillRect(bs, canvas.height - cnt, barWidth, cnt); // paint n a rectangle
	}
}

function getCurrentTime() {
	var d = new Date();
	return(d.getTime());
}

function getCurrentDateForm(t) {
	var d = new Date(t);
	var day = d.getDate();
	var month = d.getMonth() + 1;
	var year = d.getFullYear();
	var hour = d.getHours();
	var minutes = checkTime(d.getMinutes());
	var seconds = checkTime(d.getSeconds());

	var formattedDate = getLocalizedString('Last Updated:') + " " + hour + ":" + minutes;

	return(formattedDate);
}

function checkTime(i) {
	if ( i < 10) {
		i="0" + i
	}
	return i
}

function timeDiff(start,end) {
	var difference = end - start;
	return(Math.round(difference / 1000)); // only return milliseconds / 1000 ie seconds
}

function goToURL(url) {
	if (window.widget) {
		widget.openURL(url);
	}
}

function gotoSiteURL() {
	if (siteID && siteID > 0) {
		siteURL = analyticsSiteURL + siteID;
		goToURL(siteURL);
	}
}

// Removes leading whitespaces
function LTrim( value ) {
	var re = /\s*((\S+\s*)*)/;
	return value.replace(re, "$1");
}

// Removes ending whitespaces
function RTrim( value ) {
	var re = /((\s*\S+)*)\s*/;
	return value.replace(re, "$1");
}

// Removes leading and ending whitespaces
function trim( value ) {
	return LTrim(RTrim(value));
}

/* Thanks to Sebastien Vallon see here : http://www.dashboardwidgets.com/forums/viewtopic.php?t=1178 
   I've made changes to make it check against my own page
*/

function checkForUpdate() {
    var req = new XMLHttpRequest(); 
    req.onreadystatechange = function() {compareVersion(req)}; 
    req.open("GET", newVerURL, true); 
    req.setRequestHeader("Cache-Control", "no-cache"); 
	req.send();
}

function compareVersion(request) { 
    if (request.readyState == 4) {
        if (request.status == 200) {
			var serverVersion = trim(request.responseText);
			if ((currentVersion < serverVersion) && !isNaN(parseFloat(serverVersion))) { 
				needUpdate(serverVersion);
			} else { 
				dontNeedUpdate();
			} 
        }
    }
}

function needUpdate(serverVersion) {
	$("getver").innerHTML = "Get v" + serverVersion;
	$("front-updateavailimg").style.display = "block";
	$("back-updateavailimg").style.display = "block";
}

function dontNeedUpdate() {
	$("front-updateavailimg").style.display = "none";
	$("back-updateavailimg").style.display = "none";
}

function getPlistProperty(filename, property){
	
	// check the parameters
	if( (filename == null) || (filename == "") ){ return null; }
	if( (property == null) || (property == "") ){ return null; }
	
	// retrieve the value with the defaults command
	return trim(widget.system('/bin/sh -c "defaults read `pwd`/'+ filename + ' ' + property + '"',null).outputString);
}

function inputKeyPress (event) {
	switch (event.keyCode) {
		case 13: // return
		case 3:  // enter
			getSiteList();
		break;
		case 9:  // tab
		break;
	}
}

function getLocalizedString (key) {
    try {
        var ret = localizedStrings[key];
        if (ret === undefined)
            ret = key;
        return ret;
    } catch (ex) {}
 
    return key;
}
