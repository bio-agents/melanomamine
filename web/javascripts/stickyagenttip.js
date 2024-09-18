/* Sticky Agenttip script (v1.0)
* Created: Nov 25th, 2009. This notice must stay intact for usage
* Author: Dynamic Drive at http://www.dynamicdrive.com/
* Visit http://www.dynamicdrive.com/ for full source code
*/


var stickyagenttip={
	agenttipoffsets: [60, 10], //additional x and y offset from mouse cursor for agenttips
	fadeinspeed: 200, //duration of fade effect in milliseconds
	rightclickstick: true, //sticky agenttip when user right clicks over the triggering element (apart from pressing "s" key) ?
	stickybordercolors: ["black", "#FF6600"], //border color of agenttip depending on sticky state
	stickynotice1: ["Press \"s\"", "or right click", "to sticky box"], //customize agenttip status message
	stickynotice2: "Click outside this box to hide it", //customize agenttip status message

	//***** NO NEED TO EDIT BEYOND HERE

	isdocked: false,

	positionagenttip:function($, $agenttip, e){
		var x=e.pageX+this.agenttipoffsets[0], y=e.pageY+this.agenttipoffsets[1]
		var tipw=$agenttip.outerWidth(), tiph=$agenttip.outerHeight(),
		x=(x+tipw>$(document).scrollLeft()+$(window).width())? x-tipw-(stickyagenttip.agenttipoffsets[0]*2) : x
		y=(y+tiph>$(document).scrollTop()+$(window).height())? $(document).scrollTop()+$(window).height()-tiph-10 : y
		$agenttip.css({left:x, top:y})
	},

	showbox:function($, $agenttip, e){
		$agenttip.fadeIn(this.fadeinspeed)
		this.positionagenttip($, $agenttip, e)
	},

	hidebox:function($, $agenttip){
		if (!this.isdocked){
			$agenttip.stop(false, true).hide()
			$agenttip.css({borderColor:'black'}).find('.stickystatus:eq(0)').css({background:this.stickybordercolors[0]}).html(this.stickynotice1)
		}
	},

	dockagenttip:function($, $agenttip, e){
		this.isdocked=true
		$agenttip.css({borderColor:'#FF6600'}).find('.stickystatus:eq(0)').css({background:this.stickybordercolors[1]}).html(this.stickynotice2)
	},


	init:function(targetselector, tipid){
		jQuery(document).ready(function($){
			var $targets=$(targetselector)
			var $agenttip=$('#'+tipid).appendTo(document.body)
			if ($targets.length==0)
				return
			var $alltips=$agenttip.find('div.atip')
			if (!stickyagenttip.rightclickstick)
				stickyagenttip.stickynotice1[1]=''
			stickyagenttip.stickynotice1=stickyagenttip.stickynotice1.join(' ')
			stickyagenttip.hidebox($, $agenttip)
			$targets.bind('mouseenter', function(e){
				$alltips.hide().filter('#'+$(this).attr('data-agenttip')).show()
				stickyagenttip.showbox($, $agenttip, e)
			})
			$targets.bind('mouseleave', function(e){
				stickyagenttip.hidebox($, $agenttip)
			})
			$targets.bind('mousemove', function(e){
				if (!stickyagenttip.isdocked){
					stickyagenttip.positionagenttip($, $agenttip, e)
				}
			})
			$agenttip.bind("mouseenter", function(){
				stickyagenttip.hidebox($, $agenttip)
			})
			$agenttip.bind("click", function(e){
				e.stopPropagation()
			})
			$(this).bind("click", function(e){
				if (e.button==0){
					stickyagenttip.isdocked=false
					stickyagenttip.hidebox($, $agenttip)
				}
			})
			$(this).bind("contextmenu", function(e){
				if (stickyagenttip.rightclickstick && $(e.target).parents().andSelf().filter(targetselector).length==1){ //if oncontextmenu over a target element
					stickyagenttip.dockagenttip($, $agenttip, e)
					return false
				}
			})
			$(this).bind('keypress', function(e){
				var keyunicode=e.charCode || e.keyCode
				if (keyunicode==115){ //if "s" key was pressed
					stickyagenttip.dockagenttip($, $agenttip, e)
				}
			})
		}) //end dom ready
	}
}

//stickyagenttip.init("targetElementSelector", "agenttipcontainer")
stickyagenttip.init("*[data-agenttip]", "mystickyagenttip")