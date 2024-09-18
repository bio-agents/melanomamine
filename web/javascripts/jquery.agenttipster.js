/*

Agenttipster 3.0.5 | 2014-01-15
A rockin' custom agenttip jQuery plugin

Developed by Caleb Jacob under the MIT license http://opensource.org/licenses/MIT

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

*/

;(function ($, window, document) {

	var pluginName = "agenttipster",
		defaults = {
			animation: 'fade',
			arrow: true,
			arrowColor: '',
			autoClose: true,
			content: null,
			contentAsHTML: false,
			contentCloning: true,
			delay: 200,
			fixedWidth: 0,
			maxWidth: 0,
			functionInit: function(origin, content) {},
			functionBefore: function(origin, continueAgenttip) {
				continueAgenttip();
			},
			functionReady: function(origin, agenttip) {},
			functionAfter: function(origin) {},
			icon: '(?)',
			iconCloning: true,
			iconDesktop: false,
			iconTouch: false,
			iconTheme: 'agenttipster-icon',
			interactive: false,
			interactiveTolerance: 350,
			offsetX: 0,
			offsetY: 0,
			onlyOne: false,
			position: 'top',
			positionTracker: false,
			speed: 350,
			timer: 0,
			theme: 'agenttipster-default',
			touchDevices: true,
			trigger: 'hover',
			updateAnimation: true
		};
	
	function Plugin(element, options) {
		
		// list of instance variables
		
		this.bodyOverflowX;
		this.checkInterval = null;
		// this will be the user content shown in the agenttip
		this.content;
		// this is the original element which is being applied the agenttipster plugin
		this.$el = $(element);
		this.elProxyPosition;
		// this will be the element which triggers the appearance of the agenttip on hover/click/custom events.
		// it will be the same as this.$el if icons are not used (see in the options), otherwise it will correspond to the created icon
		this.$elProxy;
		this.enabled = true;
		this.options = $.extend({}, defaults, options);
		this.mouseIsOverProxy = false;
		// a unique namespace per instance, for easy selective unbinding
		this.namespace = 'agenttipster-'+ Math.round(Math.random()*100000);
		// status can be either : appearing, shown, disappearing, hidden
		this.status = 'hidden';
		this.timerHide = null;
		this.timerShow = null;
		// this will be the agenttip element (jQuery wrapped HTML element)
		this.$agenttip;
		
		// for backward compatibility
		this.options.iconTheme = this.options.iconTheme.replace('.', '');
		this.options.theme = this.options.theme.replace('.', '');
		
		// launch
		
		this.init();
	}
	
	Plugin.prototype = {
		
		init: function() {
			
			var self = this;
			
			// disable the plugin on old browsers (including IE7 and lower)
			if (document.querySelector) {
				
				// note : the content is null (empty) by default and can stay that way if the plugin remains initialized but not fed any content. The agenttip will just not appear.
				
				// if content is provided in the options, its has precedence over the title attribute. Remark : an empty string is considered content, only 'null' represents the absence of content.
				if (self.options.content !== null){
					self.setContent(self.options.content);
				}
				else {
					// the same remark as above applies : empty strings (like title="") are considered content and will be shown. Do not define any attribute at all if you want to initialize the plugin without content at start.
					var t = self.$el.attr('title');
					if(typeof t === 'undefined') t = null;
					
					self.setContent(t);
				}
				
				var c = self.options.functionInit(self.$el, self.content);
				if(typeof c !== 'undefined') self.setContent(c);
				
				self.$el
					// strip the title off of the element to prevent the default agenttips from popping up
					.removeAttr('title')
					// to be able to find all instances on the page later (upon window events in particular)
					.addClass('agenttipstered');

				// detect if we're changing the agenttip origin to an icon
				// note about this condition : if the device has touch capability and self.options.iconTouch is false, you'll have no icons event though you may consider your device as a desktop if it also has a mouse. Not sure why someone would have this use case though.
				if ((!deviceHasTouchCapability && self.options.iconDesktop) || (deviceHasTouchCapability && self.options.iconTouch)) {
					
					// TODO : the agenttip should be automatically be given an absolute position to be near the origin. Otherwise, when the origin is floating or what, it's going to be nowhere near it and disturb the position flow of the page elements. It will imply that the icon also detects when its origin moves, to follow it : not trivial.
					// Until it's done, the icon feature does not really make sense since the user still has most of the work to do by himself
					
					// if the icon provided is in the form of a string
					if(typeof self.options.icon === 'string'){
						// wrap it in a span with the icon class
						self.$elProxy = $('<span class="'+ self.options.iconTheme +'"></span>');
						self.$elProxy.text(self.options.icon);
					}
					// if it is an object (sensible choice)
					else {
						// (deep) clone the object if iconCloning == true, to make sure every instance has its own proxy. We use the icon without wrapping, no need to. We do not give it a class either, as the user will undoubtedly style the object on his own and since our css properties may conflict with his own
						if (self.options.iconCloning) self.$elProxy = self.options.icon.clone(true);
						else self.$elProxy = self.options.icon;
					}
					
					self.$elProxy.insertAfter(self.$el);
				}
				else {
					self.$elProxy = self.$el;
				}
				
				// for 'click' and 'hover' triggers : bind on events to open the agenttip. Closing is now handled in showAgenttipNow() because of its bindings.
				// Notes about touch events :
					// - mouseenter, mouseleave and clicks happen even on pure touch devices because they are emulated. deviceIsPureTouch() is a simple attempt to detect them.
					// - on hybrid devices, we do not prevent touch gesture from opening agenttips. It would be too complex to differentiate real mouse events from emulated ones.
					// - we check deviceIsPureTouch() at each event rather than prior to binding because the situation may change during browsing
				if (self.options.trigger == 'hover') {
					
					// these binding are for mouse interaction only
					self.$elProxy
						.on('mouseenter.'+ self.namespace, function() {
							if (!deviceIsPureTouch() || self.options.touchDevices) {
								self.mouseIsOverProxy = true;
								self.showAgenttip();
							}
						})
						.on('mouseleave.'+ self.namespace, function() {
							if (!deviceIsPureTouch() || self.options.touchDevices) {
								self.mouseIsOverProxy = false;
							}
						});
					
					// for touch interaction only
					if (deviceHasTouchCapability && self.options.touchDevices) {
						
						// for touch devices, we immediately display the agenttip because we cannot rely on mouseleave to handle the delay
						self.$elProxy.on('touchstart.'+ self.namespace, function() {
							self.showAgenttipNow();
						});
					}
				}
				else if (self.options.trigger == 'click') {
					
					// note : for touch devices, we do not bind on touchstart, we only rely on the emulated clicks (triggered by taps)
					self.$elProxy.on('click.'+ self.namespace, function() {
						if (!deviceIsPureTouch() || self.options.touchDevices) {
							self.showAgenttip();
						}
					});
				}
			}
		},
		
		// this function will schedule the opening of the agenttip after the delay, if there is one
		showAgenttip: function() {
			
			var self = this;
			
			if (self.status != 'shown' && self.status != 'appearing') {
				
				if (self.options.delay) {
					self.timerShow = setTimeout(function(){
						
						// for hover trigger, we check if the mouse is still over the proxy, otherwise we do not show anything
						if (self.options.trigger == 'click' || (self.options.trigger == 'hover' && self.mouseIsOverProxy)) {
							self.showAgenttipNow();
						}
					}, self.options.delay);
				}
				else self.showAgenttipNow();
			}
		},
		
		// this function will open the agenttip right away
		showAgenttipNow: function() {
			
			var self = this;
			
			//get rid of any appearance timer
			clearTimeout(self.timerShow);
			self.timerShow = null;
			clearTimeout(self.timerHide);
			self.timerHide = null;
			
			// continue only if the agenttip is enabled and has any content
			if (self.enabled && self.content !== null) {
				
				// if we only want one agenttip open at a time, close all auto-closing agenttips currently open and not already disappearing
				if (self.options.onlyOne) {
					$('.agenttipstered').not(self.$el).each(function(i,el) {
						
						// we have to use the public methods here
						var $el = $(el),
							s = $el[pluginName]('status'),
							ac = $el[pluginName]('option', 'autoClose');
						
						if (s !== 'hidden' && s !== 'disappearing' && ac) {
							$el[pluginName]('hide');
						}
					});
				}
				
				// call our custom function before continuing
				self.options.functionBefore(self.$elProxy, function() {
					
					// if this origin already has its agenttip open
					if (self.status !== 'hidden') {
						
						// the timer (if any) will start (or restart) right now
						var extraTime = 0;
						
						// if it was disappearing, cancel that
						if (self.status === 'disappearing') {
							
							self.status = 'appearing';
							
							if (supportsTransitions()) {
								
								self.$agenttip
									.clearQueue()
									.removeClass('agenttipster-dying')
									.addClass('agenttipster-'+ self.options.animation +'-show');
								
								if(self.options.speed > 0) self.$agenttip.delay(self.options.speed);
								
								self.$agenttip.queue(function(){
									self.status = 'shown';
								});
							}
							else {
								// in case the agenttip was currently fading out, bring it back to life
								self.$agenttip
									.stop()
									.fadeIn(function(){
										self.status = 'shown';
									});
							}
						}
					}
					// if the agenttip isn't already open, open that sucker up!
					else {
						
						self.status = 'appearing';
						
						// the timer (if any) will start when the agenttip has fully appeared after its transition
						var extraTime = self.options.speed;
						
						// disable horizontal scrollbar to keep overflowing agenttips from jacking with it and then restore it to its previous value
						self.bodyOverflowX = $('body').css('overflow-x');
						$('body').css('overflow-x', 'hidden');
						
						// get some other settings related to building the agenttip
						var animation = 'agenttipster-' + self.options.animation,
							animationSpeed = '-webkit-transition-duration: '+ self.options.speed +'ms; -webkit-animation-duration: '+ self.options.speed +'ms; -moz-transition-duration: '+ self.options.speed +'ms; -moz-animation-duration: '+ self.options.speed +'ms; -o-transition-duration: '+ self.options.speed +'ms; -o-animation-duration: '+ self.options.speed +'ms; -ms-transition-duration: '+ self.options.speed +'ms; -ms-animation-duration: '+ self.options.speed +'ms; transition-duration: '+ self.options.speed +'ms; animation-duration: '+ self.options.speed +'ms;',
							fixedWidth = self.options.fixedWidth > 0 ? 'width:'+ Math.round(self.options.fixedWidth) +'px;' : '',
							maxWidth = self.options.maxWidth > 0 ? 'max-width:'+ Math.round(self.options.maxWidth) +'px;' : '',
							pointerEvents = self.options.interactive ? 'pointer-events: auto;' : '';
						
						// build the base of our agenttip
						self.$agenttip = $('<div class="agenttipster-base '+ self.options.theme +'" style="'+ fixedWidth +' '+ maxWidth +' '+ pointerEvents +' '+ animationSpeed +'"><div class="agenttipster-content"></div></div>');
						
						// only add the animation class if the user has a browser that supports animations
						if (supportsTransitions()) self.$agenttip.addClass(animation);
						
						// insert the content
						self.insertContent();
						
						// attach
						self.$agenttip.appendTo('body');
						
						// do all the crazy calculations and positioning
						self.positionAgenttip();
						
						// call our custom callback since the content of the agenttip is now part of the DOM
						self.options.functionReady(self.$el, self.$agenttip);
						
						// animate in the agenttip
						if (supportsTransitions()) {
							
							self.$agenttip.addClass(animation + '-show');
							
							if(self.options.speed > 0) self.$agenttip.delay(self.options.speed);
							
							self.$agenttip.queue(function(){
								self.status = 'shown';
							});
						}
						else {
							self.$agenttip.css('display', 'none').fadeIn(self.options.speed, function() {
								self.status = 'shown';
							});
						}
						
						// will check if our agenttip origin is removed while the agenttip is shown
						self.setCheckInterval();
						
						// reposition on scroll (otherwise position:fixed element's agenttips will move away form their origin) and on resize (in case position can/has to be changed)
						$(window).on('scroll.'+ self.namespace +' resize.'+ self.namespace, function() {
							self.positionAgenttip();
						});
						
						// auto-close bindings
						if (self.options.autoClose) {
							
							// in case a listener is already bound for autoclosing (mouse or touch, hover or click), unbind it first
							$('body').off('.'+ self.namespace);
							
							// here we'll have to set different sets of bindings for both touch and mouse
							if (self.options.trigger == 'hover') {
								
								// if the user touches the body, hide
								if (deviceHasTouchCapability) {
									// timeout 0 : explanation below in click section
									setTimeout(function() {
										// we don't want to bind on click here because the initial touchstart event has not yet triggered its click event, which is thus about to happen
										$('body').on('touchstart.'+ self.namespace, function() {
											self.hideAgenttip();
										});
									}, 0);
								}
								
								// if we have to allow interaction
								if (self.options.interactive) {
									
									// touch events inside the agenttip must not close it
									if (deviceHasTouchCapability) {
										self.$agenttip.on('touchstart.'+ self.namespace, function(event) {
											event.stopPropagation();
										});
									}
									
									// as for mouse interaction, we get rid of the agenttip only after the mouse has spent some time out of it
									var tolerance = null;
									
									self.$elProxy.add(self.$agenttip)
										// hide after some time out of the proxy and the agenttip
										.on('mouseleave.'+ self.namespace + '-autoClose', function() {
											clearTimeout(tolerance);
											tolerance = setTimeout(function(){
												self.hideAgenttip();
											}, self.options.interactiveTolerance);
										})
										// suspend timeout when the mouse is over the proxy or the agenttip
										.on('mouseenter.'+ self.namespace + '-autoClose', function() {
											clearTimeout(tolerance);
										});
								}
								// if this is a non-interactive agenttip, get rid of it if the mouse leaves
								else {
									self.$elProxy.on('mouseleave.'+ self.namespace + '-autoClose', function() {
										self.hideAgenttip();
									});
								}
							}
							// here we'll set the same bindings for both clicks and touch on the body to hide the agenttip
							else if(self.options.trigger == 'click'){
								
								// use a timeout to prevent immediate closing if the method was called on a click event and if options.delay == 0 (because of bubbling)
								setTimeout(function() {
									$('body').on('click.'+ self.namespace +' touchstart.'+ self.namespace, function() {
										self.hideAgenttip();
									});
								}, 0);
								
								// if interactive, we'll stop the events that were emitted from inside the agenttip to stop autoClosing
								if (self.options.interactive) {
									
									// note : the touch events will just not be used if the plugin is not enabled on touch devices
									self.$agenttip.on('click.'+ self.namespace +' touchstart.'+ self.namespace, function(event) {
										event.stopPropagation();
									});
								}
							}
						}
					}
					
					// if we have a timer set, let the countdown begin
					if (self.options.timer > 0) {
						
						self.timerHide = setTimeout(function() {
							self.timerHide = null;
							self.hideAgenttip();
						}, self.options.timer + extraTime);
					}
				});
			}
		},
		
		setCheckInterval: function(){
			
			var self = this;
			
			self.checkInterval = setInterval(function() {
				
				// if the agenttip and/or its interval should be stopped
				if (
						// if the origin has been removed
						$('body').find(self.$el).length === 0
						// if the elProxy has been removed
					||	$('body').find(self.$elProxy).length === 0
						// if the agenttip has been closed
					||	self.status == 'hidden'
						// if the agenttip has somehow been removed
					||	$('body').find(self.$agenttip).length === 0
				) {
					// remove the agenttip if it's still here
					if (self.status == 'shown' || self.status == 'appearing') self.hideAgenttip();
					
					// clear this interval as it is no longer necessary
					self.cancelCheckInterval();
				}
				// if everything is alright
				else {
					// compare the former and current positions of the elProxy to reposition the agenttip if need be
					if(self.options.positionTracker){
						
						var p = self.positionInfo(self.$elProxy),
							identical = false;
						
						// compare size first (a change requires repositioning too)
						if(areEqual(p.dimension, self.elProxyPosition.dimension)){
							
							// for elements with a fixed position, we track the top and left properties (relative to window)
							if(self.$elProxy.css('position') === 'fixed'){
								if(areEqual(p.position, self.elProxyPosition.position)) identical = true;
							}
							// otherwise, track total offset (relative to document)
							else {
								if(areEqual(p.offset, self.elProxyPosition.offset)) identical = true;
							}
						}
						
						if(!identical){
							self.positionAgenttip();
						}
					}
				}
			}, 200);
		},
		
		cancelCheckInterval: function(){
			clearInterval(this.checkInterval);
			// clean delete
			this.checkInterval = null;
		},
		
		hideAgenttip: function() {
			
			var self = this;
			
			// get rid of any appearance timeout
			clearTimeout(self.timerShow);
			self.timerShow = null;
			clearTimeout(self.timerHide);
			self.timerHide = null;
			
			// hide
			if (self.status == 'shown' || self.status == 'appearing') {
				
				self.status = 'disappearing';
				
				var finish = function() {
					
					self.status = 'hidden';
					
					self.$agenttip.remove();
					self.$agenttip = null;
					
					// unbind orientationchange, scroll and resize listeners
					$(window).off('.'+ self.namespace);
					
					$('body')
						// unbind any auto-closing click/touch listeners
						.off('.'+ self.namespace)
						.css('overflow-x', self.bodyOverflowX);
					
					// unbind any auto-closing hover listeners
					self.$elProxy.off('.'+ self.namespace + '-autoClose');
					
					// finally, call our custom callback function
					self.options.functionAfter(self.$elProxy);
				};
				
				if (supportsTransitions()) {
					
					self.$agenttip
						.clearQueue()
						.removeClass('agenttipster-' + self.options.animation + '-show')
						// for transitions only
						.addClass('agenttipster-dying');
					
					if(self.options.speed > 0) self.$agenttip.delay(self.options.speed);
					
					self.$agenttip.queue(finish);
				}
				else {
					self.$agenttip
						.stop()
						.fadeOut(self.options.speed, finish);
				}
			}
		},
		
		setContent: function(content) {
			// clone if asked. Cloning the object makes sure that each instance has its own version of the content (in case a same object were provided for several instances)
			// reminder : typeof null === object
			if (typeof content === 'object' && content !== null && this.options.contentCloning) {
				content = content.clone(true);
			}
			this.content = content;
		},
		
		insertContent: function() {
			
			var self = this,
				$d = this.$agenttip.find('.agenttipster-content');
			
			if (typeof self.content === 'string' && !self.options.contentAsHTML) {
				$d.text(self.content);
			}
			else {
				$d
					.empty()
					.append(self.content);
			}
		},
		
		updateAgenttip: function(content) {
			
			var self = this;
			
			// change the content
			self.setContent(content);
			
			if (self.content !== null) {
				
				// update the agenttip if it is open
				if (self.status !== 'hidden') {
					
					// reset the content in the agenttip
					self.insertContent();
					
					// reposition and resize the agenttip
					self.positionAgenttip();
					
					// if we want to play a little animation showing the content changed
					if (self.options.updateAnimation) {
						
						if (supportsTransitions()) {
							
							self.$agenttip.css({
								'width': '',
								'-webkit-transition': 'all ' + self.options.speed + 'ms, width 0ms, height 0ms, left 0ms, top 0ms',
								'-moz-transition': 'all ' + self.options.speed + 'ms, width 0ms, height 0ms, left 0ms, top 0ms',
								'-o-transition': 'all ' + self.options.speed + 'ms, width 0ms, height 0ms, left 0ms, top 0ms',
								'-ms-transition': 'all ' + self.options.speed + 'ms, width 0ms, height 0ms, left 0ms, top 0ms',
								'transition': 'all ' + self.options.speed + 'ms, width 0ms, height 0ms, left 0ms, top 0ms'
							}).addClass('agenttipster-content-changing');
							
							// reset the CSS transitions and finish the change animation
							setTimeout(function() {
								
								if(self.status != 'hidden'){
									
									self.$agenttip.removeClass('agenttipster-content-changing');
									
									// after the changing animation has completed, reset the CSS transitions
									setTimeout(function() {
										
										if(self.status !== 'hidden'){
											self.$agenttip.css({
												'-webkit-transition': self.options.speed + 'ms',
												'-moz-transition': self.options.speed + 'ms',
												'-o-transition': self.options.speed + 'ms',
												'-ms-transition': self.options.speed + 'ms',
												'transition': self.options.speed + 'ms'
											});
										}
									}, self.options.speed);
								}
							}, self.options.speed);
						}
						else {
							self.$agenttip.fadeTo(self.options.speed, 0.5, function() {
								if(self.status != 'hidden'){
									self.$agenttip.fadeTo(self.options.speed, 1);
								}
							});
						}
					}
				}
			}
			else {
				self.hideAgenttip();
			}
		},
		
		positionInfo: function($el){
			return {
				dimension: {
					height: $el.outerHeight(false),
					width: $el.outerWidth(false)
				},
				offset: $el.offset(),
				position: {
					left: parseInt($el.css('left')),
					top: parseInt($el.css('top'))
				}
			};
		},
		
		positionAgenttip: function() {
			
			var self = this;
			
			// in case the agenttip has been removed from DOM manually
			if ($('body').find(self.$agenttip).length !== 0) {
				
				// reset width
				self.$agenttip.css('width', '');
				
				// find variables to determine placement
				self.elProxyPosition = self.positionInfo(self.$elProxy);
				var arrowReposition = null,
					windowWidth = $(window).width(),
					// shorthand
					proxy = self.elProxyPosition,
					agenttipWidth = self.$agenttip.outerWidth(false),
					agenttipInnerWidth = self.$agenttip.innerWidth() + 1, // this +1 stops FireFox from sometimes forcing an additional text line
					agenttipHeight = self.$agenttip.outerHeight(false),
					resetPosition = null;
				
				// if this is an <area> tag inside a <map>, all hell breaks loose. Recaclulate all the measurements based on coordinates
				if (self.$elProxy.is('area')) {
					var areaShape = self.$elProxy.attr('shape'),
						mapName = self.$elProxy.parent().attr('name'),
						map = $('img[usemap="#'+ mapName +'"]'),
						mapOffsetLeft = map.offset().left,
						mapOffsetTop = map.offset().top,
						areaMeasurements = self.$elProxy.attr('coords') !== undefined ? self.$elProxy.attr('coords').split(',') : undefined;
					
					if (areaShape == 'circle') {
						var areaLeft = parseInt(areaMeasurements[0]),
							areaTop = parseInt(areaMeasurements[1]),
							areaWidth = parseInt(areaMeasurements[2]);
						proxy.dimension.height = areaWidth * 2;
						proxy.dimension.width = areaWidth * 2;
						proxy.offset.top = mapOffsetTop + areaTop - areaWidth;
						proxy.offset.left = mapOffsetLeft + areaLeft - areaWidth;
					}
					else if (areaShape == 'rect') {
						var areaLeft = parseInt(areaMeasurements[0]),
							areaTop = parseInt(areaMeasurements[1]),
							areaRight = parseInt(areaMeasurements[2]),
							areaBottom = parseInt(areaMeasurements[3]);
						proxy.dimension.height = areaBottom - areaTop;
						proxy.dimension.width = areaRight - areaLeft;
						proxy.offset.top = mapOffsetTop + areaTop;
						proxy.offset.left = mapOffsetLeft + areaLeft;
					}
					else if (areaShape == 'poly') {
						var areaXs = [],
							areaYs = [],
							areaSmallestX = 0,
							areaSmallestY = 0,
							areaGreatestX = 0,
							areaGreatestY = 0,
							arrayAlternate = 'even';
						
						for (i = 0; i < areaMeasurements.length; i++) {
							var areaNumber = parseInt(areaMeasurements[i]);
							
							if (arrayAlternate == 'even') {
								if (areaNumber > areaGreatestX) {
									areaGreatestX = areaNumber;
									if (i === 0) {
										areaSmallestX = areaGreatestX;
									}
								}
								
								if (areaNumber < areaSmallestX) {
									areaSmallestX = areaNumber;
								}
								
								arrayAlternate = 'odd';
							}
							else {
								if (areaNumber > areaGreatestY) {
									areaGreatestY = areaNumber;
									if (i == 1) {
										areaSmallestY = areaGreatestY;
									}
								}
								
								if (areaNumber < areaSmallestY) {
									areaSmallestY = areaNumber;
								}
								
								arrayAlternate = 'even';
							}
						}
					
						proxy.dimension.height = areaGreatestY - areaSmallestY;
						proxy.dimension.width = areaGreatestX - areaSmallestX;
						proxy.offset.top = mapOffsetTop + areaSmallestY;
						proxy.offset.left = mapOffsetLeft + areaSmallestX;
					}
					else {
						proxy.dimension.height = map.outerHeight(false);
						proxy.dimension.width = map.outerWidth(false);
						proxy.offset.top = mapOffsetTop;
						proxy.offset.left = mapOffsetLeft;
					}
				}
				
				// hardcoding the width and removing the padding fixed an issue with the agenttip width collapsing when the window size is small
				if(self.options.fixedWidth === 0) {
					self.$agenttip.css({
						'width': Math.round(agenttipInnerWidth) + 'px',
						'padding-left': '0px',
						'padding-right': '0px'
					});
				}
				
				// our function and global vars for positioning our agenttip
				var myLeft = 0,
					myLeftMirror = 0,
					myTop = 0,
					offsetY = parseInt(self.options.offsetY),
					offsetX = parseInt(self.options.offsetX),
					// this is the arrow position that will eventually be used. It may differ from the position option if the agenttip cannot be displayed in this position
					practicalPosition = self.options.position;
				
				// a function to detect if the agenttip is going off the screen horizontally. If so, reposition the crap out of it!
				function dontGoOffScreenX() {
				
					var windowLeft = $(window).scrollLeft();
					
					// if the agenttip goes off the left side of the screen, line it up with the left side of the window
					if((myLeft - windowLeft) < 0) {
						arrowReposition = myLeft - windowLeft;
						myLeft = windowLeft;
					}
					
					// if the agenttip goes off the right of the screen, line it up with the right side of the window
					if (((myLeft + agenttipWidth) - windowLeft) > windowWidth) {
						arrowReposition = myLeft - ((windowWidth + windowLeft) - agenttipWidth);
						myLeft = (windowWidth + windowLeft) - agenttipWidth;
					}
				}
				
				// a function to detect if the agenttip is going off the screen vertically. If so, switch to the opposite!
				function dontGoOffScreenY(switchTo, switchFrom) {
					// if it goes off the top off the page
					if(((proxy.offset.top - $(window).scrollTop() - agenttipHeight - offsetY - 12) < 0) && (switchFrom.indexOf('top') > -1)) {
						practicalPosition = switchTo;
					}
					
					// if it goes off the bottom of the page
					if (((proxy.offset.top + proxy.dimension.height + agenttipHeight + 12 + offsetY) > ($(window).scrollTop() + $(window).height())) && (switchFrom.indexOf('bottom') > -1)) {
						practicalPosition = switchTo;
						myTop = (proxy.offset.top - agenttipHeight) - offsetY - 12;
					}
				}
				
				if(practicalPosition == 'top') {
					var leftDifference = (proxy.offset.left + agenttipWidth) - (proxy.offset.left + proxy.dimension.width);
					myLeft = (proxy.offset.left + offsetX) - (leftDifference / 2);
					myTop = (proxy.offset.top - agenttipHeight) - offsetY - 12;
					dontGoOffScreenX();
					dontGoOffScreenY('bottom', 'top');
				}
				
				if(practicalPosition == 'top-left') {
					myLeft = proxy.offset.left + offsetX;
					myTop = (proxy.offset.top - agenttipHeight) - offsetY - 12;
					dontGoOffScreenX();
					dontGoOffScreenY('bottom-left', 'top-left');
				}
				
				if(practicalPosition == 'top-right') {
					myLeft = (proxy.offset.left + proxy.dimension.width + offsetX) - agenttipWidth;
					myTop = (proxy.offset.top - agenttipHeight) - offsetY - 12;
					dontGoOffScreenX();
					dontGoOffScreenY('bottom-right', 'top-right');
				}
				
				if(practicalPosition == 'bottom') {
					var leftDifference = (proxy.offset.left + agenttipWidth) - (proxy.offset.left + proxy.dimension.width);
					myLeft = proxy.offset.left - (leftDifference / 2) + offsetX;
					myTop = (proxy.offset.top + proxy.dimension.height) + offsetY + 12;
					dontGoOffScreenX();
					dontGoOffScreenY('top', 'bottom');
				}
				
				if(practicalPosition == 'bottom-left') {
					myLeft = proxy.offset.left + offsetX;
					myTop = (proxy.offset.top + proxy.dimension.height) + offsetY + 12;
					dontGoOffScreenX();
					dontGoOffScreenY('top-left', 'bottom-left');
				}
				
				if(practicalPosition == 'bottom-right') {
					myLeft = (proxy.offset.left + proxy.dimension.width + offsetX) - agenttipWidth;
					myTop = (proxy.offset.top + proxy.dimension.height) + offsetY + 12;
					dontGoOffScreenX();
					dontGoOffScreenY('top-right', 'bottom-right');
				}
				
				if(practicalPosition == 'left') {
					myLeft = proxy.offset.left - offsetX - agenttipWidth - 12;
					myLeftMirror = proxy.offset.left + offsetX + proxy.dimension.width + 12;
					var topDifference = (proxy.offset.top + agenttipHeight) - (proxy.offset.top + self.$elProxy.outerHeight(false));
					myTop = proxy.offset.top - (topDifference / 2) - offsetY;
					
					// if the agenttip goes off boths sides of the page
					if((myLeft < 0) && ((myLeftMirror + agenttipWidth) > windowWidth)) {
						var borderWidth = parseFloat(self.$agenttip.css('border-width')) * 2,
							newWidth = (agenttipWidth + myLeft) - borderWidth;
						self.$agenttip.css('width', newWidth + 'px');
						
						agenttipHeight = self.$agenttip.outerHeight(false);
						myLeft = proxy.offset.left - offsetX - newWidth - 12 - borderWidth;
						topDifference = (proxy.offset.top + agenttipHeight) - (proxy.offset.top + self.$elProxy.outerHeight(false));
						myTop = proxy.offset.top - (topDifference / 2) - offsetY;
					}
					
					// if it only goes off one side, flip it to the other side
					else if(myLeft < 0) {
						myLeft = proxy.offset.left + offsetX + proxy.dimension.width + 12;
						arrowReposition = 'left';
					}
				}
				
				if(practicalPosition == 'right') {
					myLeft = proxy.offset.left + offsetX + proxy.dimension.width + 12;
					myLeftMirror = proxy.offset.left - offsetX - agenttipWidth - 12;
					var topDifference = (proxy.offset.top + agenttipHeight) - (proxy.offset.top + self.$elProxy.outerHeight(false));
					myTop = proxy.offset.top - (topDifference / 2) - offsetY;
					
					// if the agenttip goes off boths sides of the page
					if(((myLeft + agenttipWidth) > windowWidth) && (myLeftMirror < 0)) {
						var borderWidth = parseFloat(self.$agenttip.css('border-width')) * 2,
							newWidth = (windowWidth - myLeft) - borderWidth;
						self.$agenttip.css('width', newWidth + 'px');
						
						agenttipHeight = self.$agenttip.outerHeight(false);
						topDifference = (proxy.offset.top + agenttipHeight) - (proxy.offset.top + self.$elProxy.outerHeight(false));
						myTop = proxy.offset.top - (topDifference / 2) - offsetY;
					}
						
					// if it only goes off one side, flip it to the other side
					else if((myLeft + agenttipWidth) > windowWidth) {
						myLeft = proxy.offset.left - offsetX - agenttipWidth - 12;
						arrowReposition = 'right';
					}
				}
				
				// if arrow is set true, style it and append it
				if (self.options.arrow) {
	
					var arrowClass = 'agenttipster-arrow-' + practicalPosition;
					
					// set color of the arrow
					if(self.options.arrowColor.length < 1) {
						var arrowColor = self.$agenttip.css('background-color');
					}
					else {
						var arrowColor = self.options.arrowColor;
					}
					
					// if the agenttip was going off the page and had to re-adjust, we need to update the arrow's position
					if (!arrowReposition) {
						arrowReposition = '';
					}
					else if (arrowReposition == 'left') {
						arrowClass = 'agenttipster-arrow-right';
						arrowReposition = '';
					}
					else if (arrowReposition == 'right') {
						arrowClass = 'agenttipster-arrow-left';
						arrowReposition = '';
					}
					else {
						arrowReposition = 'left:'+ Math.round(arrowReposition) +'px;';
					}
					
					// building the logic to create the border around the arrow of the agenttip
					if ((practicalPosition == 'top') || (practicalPosition == 'top-left') || (practicalPosition == 'top-right')) {
						var agenttipBorderWidth = parseFloat(self.$agenttip.css('border-bottom-width')),
							agenttipBorderColor = self.$agenttip.css('border-bottom-color');
					}
					else if ((practicalPosition == 'bottom') || (practicalPosition == 'bottom-left') || (practicalPosition == 'bottom-right')) {
						var agenttipBorderWidth = parseFloat(self.$agenttip.css('border-top-width')),
							agenttipBorderColor = self.$agenttip.css('border-top-color');
					}
					else if (practicalPosition == 'left') {
						var agenttipBorderWidth = parseFloat(self.$agenttip.css('border-right-width')),
							agenttipBorderColor = self.$agenttip.css('border-right-color');
					}
					else if (practicalPosition == 'right') {
						var agenttipBorderWidth = parseFloat(self.$agenttip.css('border-left-width')),
							agenttipBorderColor = self.$agenttip.css('border-left-color');
					}
					else {
						var agenttipBorderWidth = parseFloat(self.$agenttip.css('border-bottom-width')),
							agenttipBorderColor = self.$agenttip.css('border-bottom-color');
					}
					
					if (agenttipBorderWidth > 1) {
						agenttipBorderWidth++;
					}
					
					var arrowBorder = '';
					if (agenttipBorderWidth !== 0) {
						var arrowBorderSize = '',
							arrowBorderColor = 'border-color: '+ agenttipBorderColor +';';
						if (arrowClass.indexOf('bottom') !== -1) {
							arrowBorderSize = 'margin-top: -'+ Math.round(agenttipBorderWidth) +'px;';
						}
						else if (arrowClass.indexOf('top') !== -1) {
							arrowBorderSize = 'margin-bottom: -'+ Math.round(agenttipBorderWidth) +'px;';
						}
						else if (arrowClass.indexOf('left') !== -1) {
							arrowBorderSize = 'margin-right: -'+ Math.round(agenttipBorderWidth) +'px;';
						}
						else if (arrowClass.indexOf('right') !== -1) {
							arrowBorderSize = 'margin-left: -'+ Math.round(agenttipBorderWidth) +'px;';
						}
						arrowBorder = '<span class="agenttipster-arrow-border" style="'+ arrowBorderSize +' '+ arrowBorderColor +';"></span>';
					}
					
					// if the arrow already exists, remove and replace it
					self.$agenttip.find('.agenttipster-arrow').remove();
					
					// build out the arrow and append it		
					var arrowConstruct = '<div class="'+ arrowClass +' agenttipster-arrow" style="'+ arrowReposition +'">'+ arrowBorder +'<span style="border-color:'+ arrowColor +';"></span></div>';
					self.$agenttip.append(arrowConstruct);
				}
				
				// position the agenttip
				self.$agenttip.css({'top': Math.round(myTop) + 'px', 'left': Math.round(myLeft) + 'px'});
			}
		}
	};
	
	$.fn[pluginName] = function () {
		
		// for using in closures
		var args = arguments;
		
		// if we are not in the context of jQuery wrapped HTML element(s) :
		// this happens when calling static methods in the form $.fn.agenttipster('methodName'), or when calling $(sel).agenttipster('methodName or options') where $(sel) does not match anything
		if (this.length === 0) {
			
			// if the first argument is a method name
			if (typeof args[0] === 'string') {
				
				var methodIsStatic = true;
				
				// list static methods here (usable by calling $.fn.agenttipster('methodName');)
				switch (args[0]) {
					
					case 'setDefaults':
						// change default options for all future instances
						$.extend(defaults, args[1]);
						break;
					
					default:
						methodIsStatic = false;
						break;
				}
				
				// $.fn.agenttipster('methodName') calls will return true
				if (methodIsStatic) return true;
				// $(sel).agenttipster('methodName') calls will return the list of objects event though it's empty because chaining should work on empty lists
				else return this;
			}
			// the first argument is undefined or an object of options : we are initalizing but there is no element matched by selector
			else {
				// still chainable : same as above
				return this;
			}
		}
		// this happens when calling $(sel).agenttipster('methodName or options') where $(sel) matches one or more elements
		else {
			
			// method calls
			if (typeof args[0] === 'string') {
				
				var v = '#*$~&';
				
				this.each(function() {
					
					// self represent the instance of the agenttipster plugin associated to the current HTML object of the loop
					var self = $(this).data('agenttipster');
					
					// if the current element is a agenttipster instance
					if(self){
						switch (args[0]) {

							case 'content':
							// 'update' is deprecated but kept for retrocompatibility
							case 'update':
								// getter method
								if(typeof args[1] === 'undefined'){
									v = self.content;
									// return false to stop .each iteration on the first element matched by the selector
									return false;
								}
								// setter method
								else {
									self.updateAgenttip(args[1]);
									break;
								}
			
							case 'destroy':
								self.hideAgenttip();
								
								if(self.$el[0] !== self.$elProxy[0]) self.$elProxy.remove();
								
								// old school technique when outerHTML is not supported
								var stringifiedContent = (typeof self.content === 'string') ? self.content : $('<div></div>').append(self.content).html();
								
								self.$el
									.removeClass('agenttipstered')
									.attr('title', stringifiedContent)
									.removeData('agenttipster')
									.off('.'+ self.namespace);
								break;
							
							case 'disable':
								// hide first, in case the agenttip would not disappear on its own (autoClose false)
								self.hideAgenttip();
								self.enabled = false;
								break;
								
							case 'elementIcon':
								v = (self.$el[0] !== self.$elProxy[0]) ? self.$elProxy[0] : undefined;
								// return false : same as above
								return false;
								
							case 'elementAgenttip':
								v = self.$agenttip ? self.$agenttip[0] : undefined;
								// return false : same as above
								return false;
							
							case 'enable':
								self.enabled = true;
								break;
			
							case 'hide':
								self.hideAgenttip();
								break;
							
							// for internal use only
							case 'option':
								v = self.options[args[1]];
								break;
							
							case 'reposition':
								self.positionAgenttip();
								break;
							
							case 'show':
								self.showAgenttipNow();
								break;
							
							// for internal use use only, not part of the public API
							case 'status':
								v = self.status;
								return false;
							
							default:
								throw new Error('Unknown method .agenttipster("' + args[0] + '")');
								break;
						}
					}
					else {
						throw new Error('You called Agenttipster\'s "' + args[0] + '" method on an uninitialized element');
					}
				});
				
				return (v !== '#*$~&') ? v : this;
			}
			// first argument is undefined or an object : the agenttip is initializing
			else {
				// initialize a agenttipster instance for each element if it doesn't already have one, and attach the object to it
				return this.each(function () {

					if (!$(this).data('agenttipster')) {
						$(this).data('agenttipster', new Plugin( this, args[0] ));
					}
				});
			}
		}
	};
	
	// quick & dirty compare function (not bijective nor multidimensional)
	function areEqual(a,b) {
		var same = true;
		$.each(a, function(i, el){
			if(typeof b[i] === 'undefined' || a[i] !== b[i]){
				same = false;
				return false;
			}
		});
		return same;
	}
	
	// detect if this device can trigger touch events
	var deviceHasTouchCapability = !!('ontouchstart' in window);
	
	// we'll assume the device has no mouse until we detect any mouse movement
	var deviceHasMouse = false;
	$('body').one('mousemove', function() {
		deviceHasMouse = true;
	});
	
	function deviceIsPureTouch() {
		return (!deviceHasMouse && deviceHasTouchCapability);
	}
	
	// detecting support for CSS transitions
	function supportsTransitions() {
		var b = document.body || document.documentElement,
			s = b.style,
			p = 'transition';
		
		if(typeof s[p] == 'string') {return true; }

		v = ['Moz', 'Webkit', 'Khtml', 'O', 'ms'],
		p = p.charAt(0).toUpperCase() + p.substr(1);
		for(var i=0; i<v.length; i++) {
			if(typeof s[v[i] + p] == 'string') { return true; }
		}
		return false;
	}
})( jQuery, window, document );
