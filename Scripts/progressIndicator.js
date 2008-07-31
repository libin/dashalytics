/*
ProgressIndicator and all associated images care of Sean Billig of the Wikipedida Widget fame
the copyright that applies is from his widget - it is as follows:

Copyright (c) 2005 Sean Billig
 sbillig@whatsinthehouse.com

Permission is hereby granted, free of charge, to any person obtaining a copy of this software
and associated documentation files (the "Software"), to deal in the Software without restriction, 
including without limitation the rights to use, copy, modify, merge, publish, distribute, and/or
sublicense copies of the Software, and to permit persons to whom the Software is furnished to do 
so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or 
substantial portions of the Software.

[In other words, feel free to use any portion, no matter how large or small, of the code below in
your own projects.  You are also welcome to use any of the other files found within the 
Wikipedia.wdgt bundle however you feel fit.  You don't need to ask me, or credit me unless you
want to, but I am interested in hearing where the code is being used, just to satisfy my curiosity.  
There are a handful of (modified) Apple scripts in the Scripts folder, which have their own 
license (a very open one, similar to this).]

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT
NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. 
IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, 
WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION 
WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

function ProgressIndicator(element, imageBaseURL) {
    this.count = 0;
    this.timer = null;
    this.element = element;
    this.element.style.display = "none";
    this.imageBaseURL = imageBaseURL;
}

ProgressIndicator.prototype = {
    start : function () {
        this.element.style.display = "block";        
        if (this.timer) clearInterval(this.timer);
        this.tick();
        var localThis = this;
        this.timer = setInterval (function() { localThis.tick() }, 60);
    },

    stop : function () {
        clearInterval(this.timer);
        this.element.style.display = "none";
    },

    tick : function () {
        var imageURL = this.imageBaseURL + (this.count + 1) + ".png";
        this.element.src = imageURL;
        this.count = (this.count + 1) % 12;
    }
}