/******/ (() => { // webpackBootstrap
/******/ 	var __webpack_modules__ = ({

/***/ "./src/assets/js/fluentform-signature.js":
/*!***********************************************!*\
  !*** ./src/assets/js/fluentform-signature.js ***!
  \***********************************************/
/***/ (() => {

// jQuery('document.body').on('fluentform_init', function (e, $theForm, form) {
//     console.log($theForm)
// });
jQuery(document).ready(function ($) {
  window.setTimeout(function () {
    var form = $('body').find('form'); // The canvas and signature pad store. We'll use this to maintain the state.

    var store = {
      canvases: [],
      signaturePads: [],
      signatures: []
    };
    window.store = store; // Initialize the signature pad, store & register events.

    function initSignature(canvas, iteration) {
      var canvasWidth = $(canvas).closest('fieldset').innerWidth();
      var isPreviewForm = $(canvas).closest('.ff_form_preview').length;
      var screenType = $('.ff_device_control.active').data('type');

      if (isPreviewForm) {
        screenType = screenType || window.localStorage.getItem('ff_window_type');

        if (screenType === 'monitor') {
          canvasWidth = 955;
        } else if (screenType === 'mobile') {
          canvasWidth = 335;
        } else if (screenType === 'tablet') {
          canvasWidth = 728;
        }

        if (screenType === 'monitor' && jQuery('.ff_preview_body').hasClass('ff_preview_only')) {
          canvasWidth = window.innerWidth - 100;
        }
      }

      var cellCount = parseInt($(canvas).closest('.ff-t-container').children('.ff-t-cell').length);

      if (cellCount > 0) {
        var containerCell = $(canvas).closest('.ff-t-cell');
        var flexBasis = parseInt(containerCell.css('flex-basis'), 10) / 100;

        if (isPreviewForm && screenType === 'mobile' || !isPreviewForm && window.innerWidth <= 425) {
          isAddFlexWrap(canvas);
        } else if (isPreviewForm && (screenType === 'monitor' || screenType === 'tablet') || !isPreviewForm && window.innerWidth >= 768) {
          canvasWidth = setContainerCellCanvasWidth(canvas, canvasWidth, flexBasis);
          canvasWidth -= cellCount * 7.5;
        }
      }

      $(canvas).attr('width', canvasWidth);
      var signaturePad = new SignaturePad(canvas, {
        // The stroke end event to populate the
        // data URI and pushing it to the input.
        onEnd: function onEnd() {
          setDataURI(this._canvas.id, this.toDataURL());
        },
        penColor: canvas.dataset.penColor,
        minWidth: Math.abs(canvas.dataset.penSize) / 10
      });
      store.canvases.push(canvas);
      store.signaturePads.push(signaturePad);
      $signaturePadActions = $("#" + canvas.id).siblings(":last"); // Register clear signature event.

      $signaturePadActions.find(".fluentform-signature-clear").on("click", function () {
        store.signatures[iteration] = signaturePad.toData();
        signaturePad.clear();
        setDataURI(canvas.id, "");
      }); // Register undo signature event.

      $signaturePadActions.find(".fluentform-signature-undo").on("click", function () {
        var data = signaturePad.toData();

        if (data && data.length) {
          var pop = data.pop(); // remove the last dot or line
          // Store the popped out data to allow the user to redo the signature.
          // We should use the current iteration to maintain the store's data.

          if (!store.signatures[iteration]) {
            store.signatures[iteration] = [];
          }

          store.signatures[iteration].push(pop);
          signaturePad.fromData(data);
        }
      }); // Register redo signature event.

      $signaturePadActions.find(".fluentform-signature-redo").on("click", function () {
        var redos = store.signatures[iteration];

        if (redos && redos.length) {
          var data = signaturePad.toData();
          data.push(redos.pop());
          signaturePad.fromData(data);
        }
      });
    }

    function isAddFlexWrap(canvas) {
      $(canvas).parents('.ff-t-container').css('flex-wrap', 'wrap');
    }

    function setContainerCellCanvasWidth(canvas, canvasWidth, flexBasis) {
      $(canvas).parents('.ff-t-container').css('flex-wrap', 'nowrap');
      return parseInt(canvasWidth * flexBasis, 10);
    } // Loop through each of the signature fields and initialize the signature pad.


    $.each($(".fluentform-signature-pad"), function (index, canvas) {
      initSignature(canvas, index);
      form.on('screen-change', function (e, width) {
        initSignature(canvas, index);
      });
      form.on('fluentform-preview-mode-change', function (e, status) {
        initSignature(canvas, index);
      }); // Clear signature pads when fluentform asks to reset.

      $("#fluentform_" + canvas.dataset.formId).on("reset", function () {
        store.signaturePads[index].clear();
        setDataURI(canvas.id, "");
      });
    });
    /**
     * To resize the canvas when needed.
     */

    function resizeCanvas() {
      $.each(store.canvases, function (i, canvas) {
        var $parent = $(this).parent();
        store.signaturePads[i].clear();
        setDataURI(canvas.id, "");
      });
    }
    /**
     * Set the data URI.
     * @param String canvasId
     * @param String value
     */


    function setDataURI(canvasId, value) {
      $("#" + canvasId).parent().siblings().first().val(value);
    }

    resizeCanvas();
  }, 10);
});

/***/ }),

/***/ "./src/assets/scss/styles.scss":
/*!*************************************!*\
  !*** ./src/assets/scss/styles.scss ***!
  \*************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = __webpack_modules__;
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/chunk loaded */
/******/ 	(() => {
/******/ 		var deferred = [];
/******/ 		__webpack_require__.O = (result, chunkIds, fn, priority) => {
/******/ 			if(chunkIds) {
/******/ 				priority = priority || 0;
/******/ 				for(var i = deferred.length; i > 0 && deferred[i - 1][2] > priority; i--) deferred[i] = deferred[i - 1];
/******/ 				deferred[i] = [chunkIds, fn, priority];
/******/ 				return;
/******/ 			}
/******/ 			var notFulfilled = Infinity;
/******/ 			for (var i = 0; i < deferred.length; i++) {
/******/ 				var [chunkIds, fn, priority] = deferred[i];
/******/ 				var fulfilled = true;
/******/ 				for (var j = 0; j < chunkIds.length; j++) {
/******/ 					if ((priority & 1 === 0 || notFulfilled >= priority) && Object.keys(__webpack_require__.O).every((key) => (__webpack_require__.O[key](chunkIds[j])))) {
/******/ 						chunkIds.splice(j--, 1);
/******/ 					} else {
/******/ 						fulfilled = false;
/******/ 						if(priority < notFulfilled) notFulfilled = priority;
/******/ 					}
/******/ 				}
/******/ 				if(fulfilled) {
/******/ 					deferred.splice(i--, 1)
/******/ 					var r = fn();
/******/ 					if (r !== undefined) result = r;
/******/ 				}
/******/ 			}
/******/ 			return result;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/jsonp chunk loading */
/******/ 	(() => {
/******/ 		// no baseURI
/******/ 		
/******/ 		// object to store loaded and loading chunks
/******/ 		// undefined = chunk not loaded, null = chunk preloaded/prefetched
/******/ 		// [resolve, reject, Promise] = chunk loading, 0 = chunk loaded
/******/ 		var installedChunks = {
/******/ 			"/public/js/fluentform-signature": 0,
/******/ 			"public/css/fluentform-signature": 0
/******/ 		};
/******/ 		
/******/ 		// no chunk on demand loading
/******/ 		
/******/ 		// no prefetching
/******/ 		
/******/ 		// no preloaded
/******/ 		
/******/ 		// no HMR
/******/ 		
/******/ 		// no HMR manifest
/******/ 		
/******/ 		__webpack_require__.O.j = (chunkId) => (installedChunks[chunkId] === 0);
/******/ 		
/******/ 		// install a JSONP callback for chunk loading
/******/ 		var webpackJsonpCallback = (parentChunkLoadingFunction, data) => {
/******/ 			var [chunkIds, moreModules, runtime] = data;
/******/ 			// add "moreModules" to the modules object,
/******/ 			// then flag all "chunkIds" as loaded and fire callback
/******/ 			var moduleId, chunkId, i = 0;
/******/ 			if(chunkIds.some((id) => (installedChunks[id] !== 0))) {
/******/ 				for(moduleId in moreModules) {
/******/ 					if(__webpack_require__.o(moreModules, moduleId)) {
/******/ 						__webpack_require__.m[moduleId] = moreModules[moduleId];
/******/ 					}
/******/ 				}
/******/ 				if(runtime) var result = runtime(__webpack_require__);
/******/ 			}
/******/ 			if(parentChunkLoadingFunction) parentChunkLoadingFunction(data);
/******/ 			for(;i < chunkIds.length; i++) {
/******/ 				chunkId = chunkIds[i];
/******/ 				if(__webpack_require__.o(installedChunks, chunkId) && installedChunks[chunkId]) {
/******/ 					installedChunks[chunkId][0]();
/******/ 				}
/******/ 				installedChunks[chunkId] = 0;
/******/ 			}
/******/ 			return __webpack_require__.O(result);
/******/ 		}
/******/ 		
/******/ 		var chunkLoadingGlobal = self["webpackChunk"] = self["webpackChunk"] || [];
/******/ 		chunkLoadingGlobal.forEach(webpackJsonpCallback.bind(null, 0));
/******/ 		chunkLoadingGlobal.push = webpackJsonpCallback.bind(null, chunkLoadingGlobal.push.bind(chunkLoadingGlobal));
/******/ 	})();
/******/ 	
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module depends on other loaded chunks and execution need to be delayed
/******/ 	__webpack_require__.O(undefined, ["public/css/fluentform-signature"], () => (__webpack_require__("./src/assets/js/fluentform-signature.js")))
/******/ 	var __webpack_exports__ = __webpack_require__.O(undefined, ["public/css/fluentform-signature"], () => (__webpack_require__("./src/assets/scss/styles.scss")))
/******/ 	__webpack_exports__ = __webpack_require__.O(__webpack_exports__);
/******/ 	
/******/ })()
;