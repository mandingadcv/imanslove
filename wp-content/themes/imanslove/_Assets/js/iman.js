/* jshint browser: true, devel: true */
/* global iman */
(function(window, undefined){
  'use strict';

  var navbar,
      forEach = function (array, callback, scope) {
        for (var i = 0; i < array.length; i++) {
          callback.call(scope, i, array[i]); // passes back stuff we need
        }
      },
      /*
       * STICKY NAV OPTIONS
       */
      defaults = {
        easing: 'linear',
        scrollTime: 1000,
        activeClass: 'active',
        topOffset : 89,
        speed: 25
      },
      isHome = function() {
        if(document.querySelector('body').classList.contains('home')) {
          return true;
        } else {
          return false;
        }
      },
      slideIndex = 1;

  window.iman = {

    noHover : {

      hasTouch : function() {

        return 'ontouchstart' in document.documentElement
           || navigator.maxTouchPoints > 0
           || navigator.msMaxTouchPoints > 0;
      },

      init : function() {

        if (this.hasTouch()) { // remove all :hover stylesheets
          try { // prevent exception on browsers not supporting DOM styleSheets properly
            for (var si in document.styleSheets) {
              var styleSheet = document.styleSheets[si];
              if (!styleSheet.rules) continue;

              for (var ri = styleSheet.rules.length - 1; ri >= 0; ri--) {
                if (!styleSheet.rules[ri].selectorText) continue;

                if (styleSheet.rules[ri].selectorText.match('.image-btn li:hover')) {
                  styleSheet.deleteRule(ri);
                }
              }
            }
          } catch (ex) {}
        }
      }
    },

  	navClickHandler : {

  		navButtonClick : function() {

  			var navbut = document.getElementById('mobileNavBtn'),
            navOverlay = document.getElementById('mobileNavOverlay');

	        navbut.addEventListener('click', function(e){
	          e.preventDefault();

	          iman.navClickHandler.toggleNavMenu();
	        });

          navOverlay.addEventListener('click', function(){
            iman.navClickHandler.toggleNavMenu();
          });
  		},

  		toggleNavMenu : function() {

  			const element = document.querySelector('body');
		    
		    if(element.classList.contains('nav-toggle')) {
	        element.classList.remove('nav-toggle');
		    } else {
	        element.className+=' nav-toggle';
		    }
  		}
  	},

    init: function(/*settings*/){
    	this.noHover.init();
      this.navClickHandler.navButtonClick();
    }
  };
})(this);

window.iman.init();
