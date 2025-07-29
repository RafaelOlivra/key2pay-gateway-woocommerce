/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "@wordpress/element":
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
/***/ ((module) => {

module.exports = window["wp"]["element"];

/***/ }),

/***/ "@wordpress/i18n":
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["i18n"];

/***/ }),

/***/ "react/jsx-runtime":
/*!**********************************!*\
  !*** external "ReactJSXRuntime" ***!
  \**********************************/
/***/ ((module) => {

module.exports = window["ReactJSXRuntime"];

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
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
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
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
(() => {
/*!**********************!*\
  !*** ./src/index.js ***!
  \**********************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__);
const {
  registerPaymentMethod
} = window.wc.wcBlocksRegistry;



/**
 * ### Thai Debit Setup ###
 */

const ThaiDebitFieldsContent = props => {
  const {
    onPaymentSetup,
    components
  } = props;
  const {
    PaymentMethodLabel
  } = components;
  const [accountNo, setAccountNo] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)("");
  const [accountName, setAccountName] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)("");
  const [bankCode, setBankCode] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)("");
  const isValid = accountNo && accountName && bankCode;
  console.log(props);
  if (onPaymentSetup) {
    onPaymentSetup(isValid ? {
      type: "complete",
      data: {
        payer_account_no: accountNo,
        payer_account_name: accountName,
        payer_bank_code: bankCode
      }
    } : {
      type: "incomplete"
    });
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("div", {
    className: "k2p-thai-debit",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(PaymentMethodLabel, {
      text: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)("Pay securely with Thai Debit via Key2Pay. Please enter your bank details below.", "key2pay")
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("div", {
      className: "k2p-field-group",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("p", {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("label", {
          children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)("Bank Code", "key2pay"), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("input", {
            type: "text",
            value: bankCode,
            onChange: e => setBankCode(e.target.value),
            placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)("e.g., 014", "key2pay"),
            required: true
          })]
        })
      })
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("div", {
      className: "k2p-field-group",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("p", {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("label", {
          children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)("Bank Account Number", "key2pay"), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("input", {
            type: "text",
            value: accountNo,
            onChange: e => setAccountNo(e.target.value),
            placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)("Enter account number", "key2pay"),
            required: true
          })]
        })
      })
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("div", {
      className: "k2p-field-group",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("p", {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("label", {
          children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)("Bank Account Name", "key2pay"), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("input", {
            type: "text",
            value: accountName,
            onChange: e => setAccountName(e.target.value),
            placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)("Name on account", "key2pay"),
            required: true
          })]
        })
      })
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("input", {
      type: "hidden",
      name: "payer_account_no",
      value: accountNo
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("input", {
      type: "hidden",
      name: "payer_account_name",
      value: accountName
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("input", {
      type: "hidden",
      name: "payer_bank_code",
      value: bankCode
    })]
  });
};
registerPaymentMethod({
  name: "key2pay_thai_debit",
  paymentMethodId: "key2pay_thai_debit",
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)("Key2Pay Thai Debit (QR Payment)", "key2pay"),
  content: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(ThaiDebitFieldsContent, {}),
  edit: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(ThaiDebitFieldsContent, {}),
  canMakePayment: () => true,
  ariaLabel: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)("Key2Pay Thai Debit (QR Payment)", "key2pay"),
  supports: {
    features: ["products", "refunds"]
  }
});

/**
 * ### Credit Card Setup ###
 */

const ThaiCreditFieldsContent = props => {
  const {
    onPaymentSetup,
    components
  } = props;
  const {
    PaymentMethodLabel
  } = components;
  console.log(PaymentMethodLabel);
  console.log(props);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("div", {
    className: "k2p-credit-card",
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(PaymentMethodLabel, {
      text: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)("Pay securely with your credit card via Key2Pay. You will be redirected to complete your payment securely.", "key2pay")
    })
  });
};
registerPaymentMethod({
  name: "key2pay_credit",
  paymentMethodId: "key2pay_credit",
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)("Key2Pay Credit Card", "key2pay"),
  content: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(ThaiCreditFieldsContent, {}),
  edit: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(ThaiCreditFieldsContent, {}),
  canMakePayment: () => true,
  ariaLabel: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)("Key2Pay Credit Card", "key2pay"),
  supports: {
    features: ["products", "refunds"]
  }
});
})();

/******/ })()
;
//# sourceMappingURL=index.js.map