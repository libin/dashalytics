// Effect.Tooltip
// Nick Stakenburg
// requires - prototype     : http://www.prototypejs.org
//          - scriptaculous : http://script.aculo.us

Effect.Tooltip = Class.create();
  Object.extend(Object.extend(Effect.Tooltip.prototype, Effect.Base.prototype), {
  initialize: function(element, content) {
    this.element = $(element);
    if(!this.element) throw(Effect._elementDoesNotExistError);
    var options = Object.extend({
      content: content,
      title: false,
      className: 'tooltip',
      offset: {'x':16, 'y':16}
    }, arguments[2] || {});
    this.start(options);
  },
  setup: function() {
    // add observers
    this.element.observe('mousemove', this.showTip.bind(this));
    this.element.observe('mouseout', this.hideTip.bind(this));
  },
  buildTip: function() {
    // create a wrapper
    this.wrapper = document.createElement('div');
    this.wrapper.className = this.options.className;
    Element.setStyle(this.wrapper, {
      position: 'absolute',
      display: 'none'
    });
    // add the title
    if(this.options.title) {
      var title = document.createElement('div');
      title.className = 'title';
      Element.update(title, this.options.title);
      this.wrapper.appendChild(title);
    }
    // create the actual tooltip
    this.tip = document.createElement('div');
    this.tip.className = 'content';
    Element.update(this.tip, this.options.content);
    this.wrapper.appendChild(this.tip);

    // add wrapper to the body
    document.body.appendChild(this.wrapper);
  },
  showTip: function(event){
    if (!this.wrapper) this.buildTip();
    this.positionTip(event);
    this.wrapper.show();
  },
  hideTip: function(){
    this.wrapper.hide();
  },
  positionTip: function(event){
    var offsets = {'x': this.options.offset['x'],'y': this.options.offset['y']};
    var mouse = {'x': Event.pointerX(event), 'y': Event.pointerY(event)};
    var page = {'x':this.viewportSize()['x'], 'y':this.viewportSize()['y']};
    var tip = {'x': mouse['x'] + this.options.offset['x'] + this.wrapper.getWidth(),
    'y' : mouse['y'] + this.options.offset['y'] + this.wrapper.getHeight()};

    // inverse x or y to keep tooltip within viewport
    if(tip['x']>page['x']) { offsets['x'] = 0-(this.wrapper.getWidth() + this.options.offset['x']); }
    if(tip['y']>page['y']) { offsets['y'] = 0-(this.wrapper.getHeight() + this.options.offset['y']); }

    this.wrapper.setStyle({
      left: mouse['x'] + offsets['x'] + 'px',
      top: mouse['y'] + offsets['y'] + 'px'
    });
  },
  viewportSize : function(){
    var x = self.innerWidth || (document.documentElement.clientWidth || document.body.clientWidth);
    var y = self.innerHeight || (document.documentElement.clientHeight || document.body.clientHeight);
    return {'x': x, 'y': y};
  }
});