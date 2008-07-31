/*
 Copyright © 2005, Apple Computer, Inc.  All rights reserved.
 NOTE:  Use of this source code is subject to the terms of the Software
 License Agreement for Mac OS X, which accompanies the code.  Your use
 of this source code signifies your agreement to such license terms and
 conditions.  Except as expressly granted in the Software License Agreement
 for Mac OS X, no other copyright, patent, or other intellectual property
 license or right is granted, either expressly or by implication, by Apple.
 */

/* FIXME : remove before shippng: Any Javascript in this file will be loaded. It is best not to put any setInterval in here till rdar://4169480 is resolved */

function AppleCreateGlassButton(buttonID, text, enabled, onclick)
{
    var buttonElement = document.getElementById(buttonID);
	if (!buttonElement.loaded) {
        buttonElement.loaded = true;
        var localizedText = getLocalizedString(text);
        try { onclick = eval(onclick); } catch (e) { onclick = null; }
		buttonElement.object = new AppleGlassButton(buttonElement, localizedText, onclick);
		buttonElement.object.setEnabled(enabled);
		
	    var kids = buttonElement.childNodes;
        var scripts = new Array;
        var j = 0;
	    for (i=kids.length-1; i>=0; i--) {
	    	if (kids[i].tagName != "DIV" && kids[i].tagName != "IMG") {
                if (kids[i].tagName == "SCRIPT") {
                    scripts[j++] = kids[i];
                }
	    		buttonElement.removeChild(kids[i]);
	    	}
	    }
        for (i=0; i<scripts.length; i++) {
            buttonElement.appendChild(scripts[i]);
        }
		
		return buttonElement.object;
	}
}

function AppleCreateButton(buttonID, text, enabled, onclick)
{
    var buttonElement = document.getElementById(buttonID);
	if (!buttonElement.loaded) {
        buttonElement.loaded = true;
        var localizedText = getLocalizedString(text);
		var imagePrefix = "Images/" + buttonID + "_";
		var height = 20;
		if (buttonElement.offsetHeight > 0) {
			height = buttonElement.offsetHeight;
		}
        try { onclick = eval(onclick); } catch (e) { onclick = null; }
		buttonElement.object = new AppleButton(buttonElement, localizedText, height, imagePrefix + "left.png", imagePrefix + "left_clicked.png", 20, imagePrefix + "middle.png", imagePrefix + "middle_clicked.png", imagePrefix + "right.png", imagePrefix + "right_clicked.png", 20, onclick);
		buttonElement.object.setEnabled(enabled);
		
	    var kids = buttonElement.childNodes;
        var scripts = new Array;
        var j = 0;
	    for (i=kids.length-1; i>=0; i--) {
	    	if (kids[i].tagName != "DIV" && kids[i].tagName != "IMG") {
                if (kids[i].tagName == "SCRIPT") {
                    scripts[j++] = kids[i];
                }
	    		buttonElement.removeChild(kids[i]);
	    	}
	    }
        for (i=0; i<scripts.length; i++) {
            buttonElement.appendChild(scripts[i]);
        }
		
		return buttonElement.object;
	}
}

function AppleCreateInfoButton(flipperID, frontID, foregroundStyle, backgroundStyle, onclick)
{
	var flipElement = document.getElementById(flipperID);
	if (!flipElement.loaded) {
		flipElement.loaded = true;
        try { onclick = eval(onclick); } catch (e) { onclick = null; }
		flipElement.object = new AppleInfoButton(flipElement, document.getElementById(frontID), foregroundStyle, backgroundStyle, onclick);
		return flipElement.object;
	}
}

function AppleCreateScrollArea(contentID, scrollbarID)
{
    var contentElement = document.getElementById(contentID);
	if (!contentElement.loaded) {
        contentElement.loaded = true;
		
		var scrollBar = new AppleVerticalScrollbar(document.getElementById(scrollbarID));
		var scrollArea = new AppleScrollArea(contentElement, scrollBar);
		scrollArea.refresh();
		
		return scrollArea;
	}
}

function AppleCreateHorizontalSlider(sliderID, continuous, currentValue, onchanged)
{
	var sliderElement = document.getElementById(sliderID);
	if (!sliderElement.loaded) {
		sliderElement.loaded = true;
        try { onchanged = eval(onchanged); } catch (e) { onchanged = null; }
		sliderElement.object = new AppleHorizontalSlider(sliderElement, onchanged);
		sliderElement.object.continuous = continuous;
		sliderElement.object.setValue(currentValue);
		return sliderElement.object;
	}
}

function AppleCreateVerticalSlider(sliderID, continuous, currentValue, onchanged)
{
	var sliderElement = document.getElementById(sliderID);
	if (!sliderElement.loaded) {
		sliderElement.loaded = true;
        try { onchanged = eval(onchanged); } catch (e) { onchanged = null; }
		sliderElement.object = new AppleVerticalSlider(sliderElement, onchanged);
		sliderElement.object.continuous = continuous;
		sliderElement.object.setValue(currentValue);
		return sliderElement.object;
	}
}

/*
 getLocalizedString() pulls a string out an array named localizedStrings.  Each language project directory in this widget contains a file named "localizedStrings.js", which, in turn, contains an array called localizedStrings.  This method queries the array of the file of whichever language has highest precidence, according to the International pane of System Preferences.
*/
function getLocalizedString(key)
{
    try {
        var ret = localizedStrings[key];
        if (ret === undefined) {
            ret = key;
        }
        return ret;
    } catch (ex) {}
    return key;
}

function uniquePrefKey(key)
{
	return widget.identifier + "-" + key;
}

function trim(string) {
    var result = string;
    var obj = /^(\s*)([\W\w]*)(\b\s*$)/;
    if (obj.test(result)) {
    	result = result.replace(obj, '$2');
	}
   	return result;
}

function limit_3(a, b, c) 
{
    return a < b ? b : (a > c ? c : a);
}

function computeNextFloat(from, to, ease) 
{
    return from + (to - from) * ease;
}
