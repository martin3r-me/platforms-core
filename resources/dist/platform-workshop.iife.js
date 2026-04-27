/* platform-workshop v0.0.0 | MIT */
var PlatformWorkshop=(()=>{var Ho=Object.create;var Ue=Object.defineProperty;var Wo=Object.getOwnPropertyDescriptor;var Go=Object.getOwnPropertyNames;var Vo=Object.getPrototypeOf,Uo=Object.prototype.hasOwnProperty;var Ko=(C,F)=>()=>(F||C((F={exports:{}}).exports,F),F.exports),Zo=(C,F)=>{for(var R in F)Ue(C,R,{get:F[R],enumerable:!0})},In=(C,F,R,p)=>{if(F&&typeof F=="object"||typeof F=="function")for(let h of Go(F))!Uo.call(C,h)&&h!==R&&Ue(C,h,{get:()=>F[h],enumerable:!(p=Wo(F,h))||p.enumerable});return C};var Qo=(C,F,R)=>(R=C!=null?Ho(Vo(C)):{},In(F||!C||!C.__esModule?Ue(R,"default",{value:C,enumerable:!0}):R,C)),Jo=C=>In(Ue({},"__esModule",{value:!0}),C);var Pn=Ko((Et,fe)=>{(function(C,F){typeof Et=="object"&&typeof fe<"u"?fe.exports=F():typeof define=="function"&&define.amd?define(F):(C=typeof globalThis<"u"?globalThis:C||self).interact=F()})(Et,(function(){"use strict";function C(t,e){var n=Object.keys(t);if(Object.getOwnPropertySymbols){var o=Object.getOwnPropertySymbols(t);e&&(o=o.filter((function(r){return Object.getOwnPropertyDescriptor(t,r).enumerable}))),n.push.apply(n,o)}return n}function F(t){for(var e=1;e<arguments.length;e++){var n=arguments[e]!=null?arguments[e]:{};e%2?C(Object(n),!0).forEach((function(o){f(t,o,n[o])})):Object.getOwnPropertyDescriptors?Object.defineProperties(t,Object.getOwnPropertyDescriptors(n)):C(Object(n)).forEach((function(o){Object.defineProperty(t,o,Object.getOwnPropertyDescriptor(n,o))}))}return t}function R(t){return R=typeof Symbol=="function"&&typeof Symbol.iterator=="symbol"?function(e){return typeof e}:function(e){return e&&typeof Symbol=="function"&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},R(t)}function p(t,e){if(!(t instanceof e))throw new TypeError("Cannot call a class as a function")}function h(t,e){for(var n=0;n<e.length;n++){var o=e[n];o.enumerable=o.enumerable||!1,o.configurable=!0,"value"in o&&(o.writable=!0),Object.defineProperty(t,N(o.key),o)}}function v(t,e,n){return e&&h(t.prototype,e),n&&h(t,n),Object.defineProperty(t,"prototype",{writable:!1}),t}function f(t,e,n){return(e=N(e))in t?Object.defineProperty(t,e,{value:n,enumerable:!0,configurable:!0,writable:!0}):t[e]=n,t}function m(t,e){if(typeof e!="function"&&e!==null)throw new TypeError("Super expression must either be null or a function");t.prototype=Object.create(e&&e.prototype,{constructor:{value:t,writable:!0,configurable:!0}}),Object.defineProperty(t,"prototype",{writable:!1}),e&&k(t,e)}function T(t){return T=Object.setPrototypeOf?Object.getPrototypeOf.bind():function(e){return e.__proto__||Object.getPrototypeOf(e)},T(t)}function k(t,e){return k=Object.setPrototypeOf?Object.setPrototypeOf.bind():function(n,o){return n.__proto__=o,n},k(t,e)}function M(t){if(t===void 0)throw new ReferenceError("this hasn't been initialised - super() hasn't been called");return t}function $(t){var e=(function(){if(typeof Reflect>"u"||!Reflect.construct||Reflect.construct.sham)return!1;if(typeof Proxy=="function")return!0;try{return Boolean.prototype.valueOf.call(Reflect.construct(Boolean,[],(function(){}))),!0}catch{return!1}})();return function(){var n,o=T(t);if(e){var r=T(this).constructor;n=Reflect.construct(o,arguments,r)}else n=o.apply(this,arguments);return(function(i,a){if(a&&(typeof a=="object"||typeof a=="function"))return a;if(a!==void 0)throw new TypeError("Derived constructors may only return object or undefined");return M(i)})(this,n)}}function L(){return L=typeof Reflect<"u"&&Reflect.get?Reflect.get.bind():function(t,e,n){var o=(function(i,a){for(;!Object.prototype.hasOwnProperty.call(i,a)&&(i=T(i))!==null;);return i})(t,e);if(o){var r=Object.getOwnPropertyDescriptor(o,e);return r.get?r.get.call(arguments.length<3?t:n):r.value}},L.apply(this,arguments)}function N(t){var e=(function(n,o){if(typeof n!="object"||n===null)return n;var r=n[Symbol.toPrimitive];if(r!==void 0){var i=r.call(n,o||"default");if(typeof i!="object")return i;throw new TypeError("@@toPrimitive must return a primitive value.")}return(o==="string"?String:Number)(n)})(t,"string");return typeof e=="symbol"?e:e+""}var W=function(t){return!(!t||!t.Window)&&t instanceof t.Window},le=void 0,B=void 0;function ye(t){le=t;var e=t.document.createTextNode("");e.ownerDocument!==t.document&&typeof t.wrap=="function"&&t.wrap(e)===e&&(t=t.wrap(t)),B=t}function J(t){return W(t)?t:(t.ownerDocument||t).defaultView||B.window}typeof window<"u"&&window&&ye(window);var De=function(t){return!!t&&R(t)==="object"},It=function(t){return typeof t=="function"},y={window:function(t){return t===B||W(t)},docFrag:function(t){return De(t)&&t.nodeType===11},object:De,func:It,number:function(t){return typeof t=="number"},bool:function(t){return typeof t=="boolean"},string:function(t){return typeof t=="string"},element:function(t){if(!t||R(t)!=="object")return!1;var e=J(t)||B;return/object|function/.test(typeof Element>"u"?"undefined":R(Element))?t instanceof Element||t instanceof e.Element:t.nodeType===1&&typeof t.nodeName=="string"},plainObject:function(t){return De(t)&&!!t.constructor&&/function Object\b/.test(t.constructor.toString())},array:function(t){return De(t)&&t.length!==void 0&&It(t.splice)}};function Ke(t){var e=t.interaction;if(e.prepared.name==="drag"){var n=e.prepared.axis;n==="x"?(e.coords.cur.page.y=e.coords.start.page.y,e.coords.cur.client.y=e.coords.start.client.y,e.coords.velocity.client.y=0,e.coords.velocity.page.y=0):n==="y"&&(e.coords.cur.page.x=e.coords.start.page.x,e.coords.cur.client.x=e.coords.start.client.x,e.coords.velocity.client.x=0,e.coords.velocity.page.x=0)}}function Pt(t){var e=t.iEvent,n=t.interaction;if(n.prepared.name==="drag"){var o=n.prepared.axis;if(o==="x"||o==="y"){var r=o==="x"?"y":"x";e.page[r]=n.coords.start.page[r],e.client[r]=n.coords.start.client[r],e.delta[r]=0}}}var Ae={id:"actions/drag",install:function(t){var e=t.actions,n=t.Interactable,o=t.defaults;n.prototype.draggable=Ae.draggable,e.map.drag=Ae,e.methodDict.drag="draggable",o.actions.drag=Ae.defaults},listeners:{"interactions:before-action-move":Ke,"interactions:action-resume":Ke,"interactions:action-move":Pt,"auto-start:check":function(t){var e=t.interaction,n=t.interactable,o=t.buttons,r=n.options.drag;if(r&&r.enabled&&(!e.pointerIsDown||!/mouse|pointer/.test(e.pointerType)||(o&n.options.drag.mouseButtons)!=0))return t.action={name:"drag",axis:r.lockAxis==="start"?r.startAxis:r.lockAxis},!1}},draggable:function(t){return y.object(t)?(this.options.drag.enabled=t.enabled!==!1,this.setPerAction("drag",t),this.setOnEvents("drag",t),/^(xy|x|y|start)$/.test(t.lockAxis)&&(this.options.drag.lockAxis=t.lockAxis),/^(xy|x|y)$/.test(t.startAxis)&&(this.options.drag.startAxis=t.startAxis),this):y.bool(t)?(this.options.drag.enabled=t,this):this.options.drag},beforeMove:Ke,move:Pt,defaults:{startAxis:"xy",lockAxis:"xy"},getCursor:function(){return"move"},filterEventType:function(t){return t.search("drag")===0}},zt=Ae,G={init:function(t){var e=t;G.document=e.document,G.DocumentFragment=e.DocumentFragment||ve,G.SVGElement=e.SVGElement||ve,G.SVGSVGElement=e.SVGSVGElement||ve,G.SVGElementInstance=e.SVGElementInstance||ve,G.Element=e.Element||ve,G.HTMLElement=e.HTMLElement||G.Element,G.Event=e.Event,G.Touch=e.Touch||ve,G.PointerEvent=e.PointerEvent||e.MSPointerEvent},document:null,DocumentFragment:null,SVGElement:null,SVGSVGElement:null,SVGElementInstance:null,Element:null,HTMLElement:null,Event:null,Touch:null,PointerEvent:null};function ve(){}var q=G,V={init:function(t){var e=q.Element,n=t.navigator||{};V.supportsTouch="ontouchstart"in t||y.func(t.DocumentTouch)&&q.document instanceof t.DocumentTouch,V.supportsPointerEvent=n.pointerEnabled!==!1&&!!q.PointerEvent,V.isIOS=/iP(hone|od|ad)/.test(n.platform),V.isIOS7=/iP(hone|od|ad)/.test(n.platform)&&/OS 7[^\d]/.test(n.appVersion),V.isIe9=/MSIE 9/.test(n.userAgent),V.isOperaMobile=n.appName==="Opera"&&V.supportsTouch&&/Presto/.test(n.userAgent),V.prefixedMatchesSelector="matches"in e.prototype?"matches":"webkitMatchesSelector"in e.prototype?"webkitMatchesSelector":"mozMatchesSelector"in e.prototype?"mozMatchesSelector":"oMatchesSelector"in e.prototype?"oMatchesSelector":"msMatchesSelector",V.pEventTypes=V.supportsPointerEvent?q.PointerEvent===t.MSPointerEvent?{up:"MSPointerUp",down:"MSPointerDown",over:"mouseover",out:"mouseout",move:"MSPointerMove",cancel:"MSPointerCancel"}:{up:"pointerup",down:"pointerdown",over:"pointerover",out:"pointerout",move:"pointermove",cancel:"pointercancel"}:null,V.wheelEvent=q.document&&"onmousewheel"in q.document?"mousewheel":"wheel"},supportsTouch:null,supportsPointerEvent:null,isIOS7:null,isIOS:null,isIe9:null,isOperaMobile:null,prefixedMatchesSelector:null,pEventTypes:null,wheelEvent:null},U=V;function pe(t,e){if(t.contains)return t.contains(e);for(;e;){if(e===t)return!0;e=e.parentNode}return!1}function Mt(t,e){for(;y.element(t);){if(re(t,e))return t;t=ee(t)}return null}function ee(t){var e=t.parentNode;if(y.docFrag(e)){for(;(e=e.host)&&y.docFrag(e););return e}return e}function re(t,e){return B!==le&&(e=e.replace(/\/deep\//g," ")),t[U.prefixedMatchesSelector](e)}var Ze=function(t){return t.parentNode||t.host};function Ot(t,e){for(var n,o=[],r=t;(n=Ze(r))&&r!==e&&n!==r.ownerDocument;)o.unshift(r),r=n;return o}function Qe(t,e,n){for(;y.element(t);){if(re(t,e))return!0;if((t=ee(t))===n)return re(t,e)}return!1}function Dt(t){return t.correspondingUseElement||t}function Je(t){var e=t instanceof q.SVGElement?t.getBoundingClientRect():t.getClientRects()[0];return e&&{left:e.left,right:e.right,top:e.top,bottom:e.bottom,width:e.width||e.right-e.left,height:e.height||e.bottom-e.top}}function et(t){var e,n=Je(t);if(!U.isIOS7&&n){var o={x:(e=(e=J(t))||B).scrollX||e.document.documentElement.scrollLeft,y:e.scrollY||e.document.documentElement.scrollTop};n.left+=o.x,n.right+=o.x,n.top+=o.y,n.bottom+=o.y}return n}function At(t){for(var e=[];t;)e.push(t),t=ee(t);return e}function Ct(t){return!!y.string(t)&&(q.document.querySelector(t),!0)}function I(t,e){for(var n in e)t[n]=e[n];return t}function $t(t,e,n){return t==="parent"?ee(n):t==="self"?e.getRect(n):Mt(n,t)}function be(t,e,n,o){var r=t;return y.string(r)?r=$t(r,e,n):y.func(r)&&(r=r.apply(void 0,o)),y.element(r)&&(r=et(r)),r}function Ce(t){return t&&{x:"x"in t?t.x:t.left,y:"y"in t?t.y:t.top}}function tt(t){return!t||"x"in t&&"y"in t||((t=I({},t)).x=t.left||0,t.y=t.top||0,t.width=t.width||(t.right||0)-t.x,t.height=t.height||(t.bottom||0)-t.y),t}function $e(t,e,n){t.left&&(e.left+=n.x),t.right&&(e.right+=n.x),t.top&&(e.top+=n.y),t.bottom&&(e.bottom+=n.y),e.width=e.right-e.left,e.height=e.bottom-e.top}function xe(t,e,n){var o=n&&t.options[n];return Ce(be(o&&o.origin||t.options.origin,t,e,[t&&e]))||{x:0,y:0}}function de(t,e){var n=arguments.length>2&&arguments[2]!==void 0?arguments[2]:function(c){return!0},o=arguments.length>3?arguments[3]:void 0;if(o=o||{},y.string(t)&&t.search(" ")!==-1&&(t=Lt(t)),y.array(t))return t.forEach((function(c){return de(c,e,n,o)})),o;if(y.object(t)&&(e=t,t=""),y.func(e)&&n(t))o[t]=o[t]||[],o[t].push(e);else if(y.array(e))for(var r=0,i=e;r<i.length;r++){var a=i[r];de(t,a,n,o)}else if(y.object(e))for(var s in e)de(Lt(s).map((function(c){return"".concat(t).concat(c)})),e[s],n,o);return o}function Lt(t){return t.trim().split(/ +/)}var we=function(t,e){return Math.sqrt(t*t+e*e)},On=["webkit","moz"];function Le(t,e){t.__set||(t.__set={});var n=function(r){if(On.some((function(i){return r.indexOf(i)===0})))return 1;typeof t[r]!="function"&&r!=="__set"&&Object.defineProperty(t,r,{get:function(){return r in t.__set?t.__set[r]:t.__set[r]=e[r]},set:function(i){t.__set[r]=i},configurable:!0})};for(var o in e)n(o);return t}function Fe(t,e){t.page=t.page||{},t.page.x=e.page.x,t.page.y=e.page.y,t.client=t.client||{},t.client.x=e.client.x,t.client.y=e.client.y,t.timeStamp=e.timeStamp}function Ft(t){t.page.x=0,t.page.y=0,t.client.x=0,t.client.y=0}function jt(t){return t instanceof q.Event||t instanceof q.Touch}function je(t,e,n){return t=t||"page",(n=n||{}).x=e[t+"X"],n.y=e[t+"Y"],n}function Nt(t,e){return e=e||{x:0,y:0},U.isOperaMobile&&jt(t)?(je("screen",t,e),e.x+=window.scrollX,e.y+=window.scrollY):je("page",t,e),e}function ke(t){return y.number(t.pointerId)?t.pointerId:t.identifier}function Dn(t,e,n){var o=e.length>1?Rt(e):e[0];Nt(o,t.page),(function(r,i){i=i||{},U.isOperaMobile&&jt(r)?je("screen",r,i):je("client",r,i)})(o,t.client),t.timeStamp=n}function nt(t){var e=[];return y.array(t)?(e[0]=t[0],e[1]=t[1]):t.type==="touchend"?t.touches.length===1?(e[0]=t.touches[0],e[1]=t.changedTouches[0]):t.touches.length===0&&(e[0]=t.changedTouches[0],e[1]=t.changedTouches[1]):(e[0]=t.touches[0],e[1]=t.touches[1]),e}function Rt(t){for(var e={pageX:0,pageY:0,clientX:0,clientY:0,screenX:0,screenY:0},n=0;n<t.length;n++){var o=t[n];for(var r in e)e[r]+=o[r]}for(var i in e)e[i]/=t.length;return e}function ot(t){if(!t.length)return null;var e=nt(t),n=Math.min(e[0].pageX,e[1].pageX),o=Math.min(e[0].pageY,e[1].pageY),r=Math.max(e[0].pageX,e[1].pageX),i=Math.max(e[0].pageY,e[1].pageY);return{x:n,y:o,left:n,top:o,right:r,bottom:i,width:r-n,height:i-o}}function rt(t,e){var n=e+"X",o=e+"Y",r=nt(t),i=r[0][n]-r[1][n],a=r[0][o]-r[1][o];return we(i,a)}function it(t,e){var n=e+"X",o=e+"Y",r=nt(t),i=r[1][n]-r[0][n],a=r[1][o]-r[0][o];return 180*Math.atan2(a,i)/Math.PI}function qt(t){return y.string(t.pointerType)?t.pointerType:y.number(t.pointerType)?[void 0,void 0,"touch","pen","mouse"][t.pointerType]:/touch/.test(t.type||"")||t instanceof q.Touch?"touch":"mouse"}function Xt(t){var e=y.func(t.composedPath)?t.composedPath():t.path;return[Dt(e?e[0]:t.target),Dt(t.currentTarget)]}var Ne=(function(){function t(e){p(this,t),this.immediatePropagationStopped=!1,this.propagationStopped=!1,this._interaction=e}return v(t,[{key:"preventDefault",value:function(){}},{key:"stopPropagation",value:function(){this.propagationStopped=!0}},{key:"stopImmediatePropagation",value:function(){this.immediatePropagationStopped=this.propagationStopped=!0}}]),t})();Object.defineProperty(Ne.prototype,"interaction",{get:function(){return this._interaction._proxy},set:function(){}});var Yt=function(t,e){for(var n=0;n<e.length;n++){var o=e[n];t.push(o)}return t},Bt=function(t){return Yt([],t)},_e=function(t,e){for(var n=0;n<t.length;n++)if(e(t[n],n,t))return n;return-1},Ee=function(t,e){return t[_e(t,e)]},ge=(function(t){m(n,t);var e=$(n);function n(o,r,i){var a;p(this,n),(a=e.call(this,r._interaction)).dropzone=void 0,a.dragEvent=void 0,a.relatedTarget=void 0,a.draggable=void 0,a.propagationStopped=!1,a.immediatePropagationStopped=!1;var s=i==="dragleave"?o.prev:o.cur,c=s.element,d=s.dropzone;return a.type=i,a.target=c,a.currentTarget=c,a.dropzone=d,a.dragEvent=r,a.relatedTarget=r.target,a.draggable=r.interactable,a.timeStamp=r.timeStamp,a}return v(n,[{key:"reject",value:function(){var o=this,r=this._interaction.dropState;if(this.type==="dropactivate"||this.dropzone&&r.cur.dropzone===this.dropzone&&r.cur.element===this.target)if(r.prev.dropzone=this.dropzone,r.prev.element=this.target,r.rejected=!0,r.events.enter=null,this.stopImmediatePropagation(),this.type==="dropactivate"){var i=r.activeDrops,a=_e(i,(function(c){var d=c.dropzone,l=c.element;return d===o.dropzone&&l===o.target}));r.activeDrops.splice(a,1);var s=new n(r,this.dragEvent,"dropdeactivate");s.dropzone=this.dropzone,s.target=this.target,this.dropzone.fire(s)}else this.dropzone.fire(new n(r,this.dragEvent,"dragleave"))}},{key:"preventDefault",value:function(){}},{key:"stopPropagation",value:function(){this.propagationStopped=!0}},{key:"stopImmediatePropagation",value:function(){this.immediatePropagationStopped=this.propagationStopped=!0}}]),n})(Ne);function Ht(t,e){for(var n=0,o=t.slice();n<o.length;n++){var r=o[n],i=r.dropzone,a=r.element;e.dropzone=i,e.target=a,i.fire(e),e.propagationStopped=e.immediatePropagationStopped=!1}}function at(t,e){for(var n=(function(i,a){for(var s=[],c=0,d=i.interactables.list;c<d.length;c++){var l=d[c];if(l.options.drop.enabled){var u=l.options.drop.accept;if(!(y.element(u)&&u!==a||y.string(u)&&!re(a,u)||y.func(u)&&!u({dropzone:l,draggableElement:a})))for(var g=0,x=l.getAllElements();g<x.length;g++){var b=x[g];b!==a&&s.push({dropzone:l,element:b,rect:l.getRect(b)})}}}return s})(t,e),o=0;o<n.length;o++){var r=n[o];r.rect=r.dropzone.getRect(r.element)}return n}function Wt(t,e,n){for(var o=t.dropState,r=t.interactable,i=t.element,a=[],s=0,c=o.activeDrops;s<c.length;s++){var d=c[s],l=d.dropzone,u=d.element,g=d.rect,x=l.dropCheck(e,n,r,i,u,g);a.push(x?u:null)}var b=(function(w){for(var E,_,S,O=[],A=0;A<w.length;A++){var P=w[A],D=w[E];if(P&&A!==E)if(D){var Y=Ze(P),j=Ze(D);if(Y!==P.ownerDocument)if(j!==P.ownerDocument)if(Y!==j){O=O.length?O:Ot(D);var H=void 0;if(D instanceof q.HTMLElement&&P instanceof q.SVGElement&&!(P instanceof q.SVGSVGElement)){if(P===j)continue;H=P.ownerSVGElement}else H=P;for(var K=Ot(H,D.ownerDocument),oe=0;K[oe]&&K[oe]===O[oe];)oe++;var Ve=[K[oe-1],K[oe],O[oe]];if(Ve[0])for(var Me=Ve[0].lastChild;Me;){if(Me===Ve[1]){E=A,O=K;break}if(Me===Ve[2])break;Me=Me.previousSibling}}else S=D,(parseInt(J(_=P).getComputedStyle(_).zIndex,10)||0)>=(parseInt(J(S).getComputedStyle(S).zIndex,10)||0)&&(E=A);else E=A}else E=A}return E})(a);return o.activeDrops[b]||null}function st(t,e,n){var o=t.dropState,r={enter:null,leave:null,activate:null,deactivate:null,move:null,drop:null};return n.type==="dragstart"&&(r.activate=new ge(o,n,"dropactivate"),r.activate.target=null,r.activate.dropzone=null),n.type==="dragend"&&(r.deactivate=new ge(o,n,"dropdeactivate"),r.deactivate.target=null,r.deactivate.dropzone=null),o.rejected||(o.cur.element!==o.prev.element&&(o.prev.dropzone&&(r.leave=new ge(o,n,"dragleave"),n.dragLeave=r.leave.target=o.prev.element,n.prevDropzone=r.leave.dropzone=o.prev.dropzone),o.cur.dropzone&&(r.enter=new ge(o,n,"dragenter"),n.dragEnter=o.cur.element,n.dropzone=o.cur.dropzone)),n.type==="dragend"&&o.cur.dropzone&&(r.drop=new ge(o,n,"drop"),n.dropzone=o.cur.dropzone,n.relatedTarget=o.cur.element),n.type==="dragmove"&&o.cur.dropzone&&(r.move=new ge(o,n,"dropmove"),n.dropzone=o.cur.dropzone)),r}function ct(t,e){var n=t.dropState,o=n.activeDrops,r=n.cur,i=n.prev;e.leave&&i.dropzone.fire(e.leave),e.enter&&r.dropzone.fire(e.enter),e.move&&r.dropzone.fire(e.move),e.drop&&r.dropzone.fire(e.drop),e.deactivate&&Ht(o,e.deactivate),n.prev.dropzone=r.dropzone,n.prev.element=r.element}function Gt(t,e){var n=t.interaction,o=t.iEvent,r=t.event;if(o.type==="dragmove"||o.type==="dragend"){var i=n.dropState;e.dynamicDrop&&(i.activeDrops=at(e,n.element));var a=o,s=Wt(n,a,r);i.rejected=i.rejected&&!!s&&s.dropzone===i.cur.dropzone&&s.element===i.cur.element,i.cur.dropzone=s&&s.dropzone,i.cur.element=s&&s.element,i.events=st(n,0,a)}}var Vt={id:"actions/drop",install:function(t){var e=t.actions,n=t.interactStatic,o=t.Interactable,r=t.defaults;t.usePlugin(zt),o.prototype.dropzone=function(i){return(function(a,s){if(y.object(s)){if(a.options.drop.enabled=s.enabled!==!1,s.listeners){var c=de(s.listeners),d=Object.keys(c).reduce((function(u,g){return u[/^(enter|leave)/.test(g)?"drag".concat(g):/^(activate|deactivate|move)/.test(g)?"drop".concat(g):g]=c[g],u}),{}),l=a.options.drop.listeners;l&&a.off(l),a.on(d),a.options.drop.listeners=d}return y.func(s.ondrop)&&a.on("drop",s.ondrop),y.func(s.ondropactivate)&&a.on("dropactivate",s.ondropactivate),y.func(s.ondropdeactivate)&&a.on("dropdeactivate",s.ondropdeactivate),y.func(s.ondragenter)&&a.on("dragenter",s.ondragenter),y.func(s.ondragleave)&&a.on("dragleave",s.ondragleave),y.func(s.ondropmove)&&a.on("dropmove",s.ondropmove),/^(pointer|center)$/.test(s.overlap)?a.options.drop.overlap=s.overlap:y.number(s.overlap)&&(a.options.drop.overlap=Math.max(Math.min(1,s.overlap),0)),"accept"in s&&(a.options.drop.accept=s.accept),"checker"in s&&(a.options.drop.checker=s.checker),a}return y.bool(s)?(a.options.drop.enabled=s,a):a.options.drop})(this,i)},o.prototype.dropCheck=function(i,a,s,c,d,l){return(function(u,g,x,b,w,E,_){var S=!1;if(!(_=_||u.getRect(E)))return!!u.options.drop.checker&&u.options.drop.checker(g,x,S,u,E,b,w);var O=u.options.drop.overlap;if(O==="pointer"){var A=xe(b,w,"drag"),P=Nt(g);P.x+=A.x,P.y+=A.y;var D=P.x>_.left&&P.x<_.right,Y=P.y>_.top&&P.y<_.bottom;S=D&&Y}var j=b.getRect(w);if(j&&O==="center"){var H=j.left+j.width/2,K=j.top+j.height/2;S=H>=_.left&&H<=_.right&&K>=_.top&&K<=_.bottom}return j&&y.number(O)&&(S=Math.max(0,Math.min(_.right,j.right)-Math.max(_.left,j.left))*Math.max(0,Math.min(_.bottom,j.bottom)-Math.max(_.top,j.top))/(j.width*j.height)>=O),u.options.drop.checker&&(S=u.options.drop.checker(g,x,S,u,E,b,w)),S})(this,i,a,s,c,d,l)},n.dynamicDrop=function(i){return y.bool(i)?(t.dynamicDrop=i,n):t.dynamicDrop},I(e.phaselessTypes,{dragenter:!0,dragleave:!0,dropactivate:!0,dropdeactivate:!0,dropmove:!0,drop:!0}),e.methodDict.drop="dropzone",t.dynamicDrop=!1,r.actions.drop=Vt.defaults},listeners:{"interactions:before-action-start":function(t){var e=t.interaction;e.prepared.name==="drag"&&(e.dropState={cur:{dropzone:null,element:null},prev:{dropzone:null,element:null},rejected:null,events:null,activeDrops:[]})},"interactions:after-action-start":function(t,e){var n=t.interaction,o=(t.event,t.iEvent);if(n.prepared.name==="drag"){var r=n.dropState;r.activeDrops=[],r.events={},r.activeDrops=at(e,n.element),r.events=st(n,0,o),r.events.activate&&(Ht(r.activeDrops,r.events.activate),e.fire("actions/drop:start",{interaction:n,dragEvent:o}))}},"interactions:action-move":Gt,"interactions:after-action-move":function(t,e){var n=t.interaction,o=t.iEvent;if(n.prepared.name==="drag"){var r=n.dropState;ct(n,r.events),e.fire("actions/drop:move",{interaction:n,dragEvent:o}),r.events={}}},"interactions:action-end":function(t,e){if(t.interaction.prepared.name==="drag"){var n=t.interaction,o=t.iEvent;Gt(t,e),ct(n,n.dropState.events),e.fire("actions/drop:end",{interaction:n,dragEvent:o})}},"interactions:stop":function(t){var e=t.interaction;if(e.prepared.name==="drag"){var n=e.dropState;n&&(n.activeDrops=null,n.events=null,n.cur.dropzone=null,n.cur.element=null,n.prev.dropzone=null,n.prev.element=null,n.rejected=!1)}}},getActiveDrops:at,getDrop:Wt,getDropEvents:st,fireDropEvents:ct,filterEventType:function(t){return t.search("drag")===0||t.search("drop")===0},defaults:{enabled:!1,accept:null,overlap:"pointer"}},An=Vt;function lt(t){var e=t.interaction,n=t.iEvent,o=t.phase;if(e.prepared.name==="gesture"){var r=e.pointers.map((function(d){return d.pointer})),i=o==="start",a=o==="end",s=e.interactable.options.deltaSource;if(n.touches=[r[0],r[1]],i)n.distance=rt(r,s),n.box=ot(r),n.scale=1,n.ds=0,n.angle=it(r,s),n.da=0,e.gesture.startDistance=n.distance,e.gesture.startAngle=n.angle;else if(a||e.pointers.length<2){var c=e.prevEvent;n.distance=c.distance,n.box=c.box,n.scale=c.scale,n.ds=0,n.angle=c.angle,n.da=0}else n.distance=rt(r,s),n.box=ot(r),n.scale=n.distance/e.gesture.startDistance,n.angle=it(r,s),n.ds=n.scale-e.gesture.scale,n.da=n.angle-e.gesture.angle;e.gesture.distance=n.distance,e.gesture.angle=n.angle,y.number(n.scale)&&n.scale!==1/0&&!isNaN(n.scale)&&(e.gesture.scale=n.scale)}}var pt={id:"actions/gesture",before:["actions/drag","actions/resize"],install:function(t){var e=t.actions,n=t.Interactable,o=t.defaults;n.prototype.gesturable=function(r){return y.object(r)?(this.options.gesture.enabled=r.enabled!==!1,this.setPerAction("gesture",r),this.setOnEvents("gesture",r),this):y.bool(r)?(this.options.gesture.enabled=r,this):this.options.gesture},e.map.gesture=pt,e.methodDict.gesture="gesturable",o.actions.gesture=pt.defaults},listeners:{"interactions:action-start":lt,"interactions:action-move":lt,"interactions:action-end":lt,"interactions:new":function(t){t.interaction.gesture={angle:0,distance:0,scale:1,startAngle:0,startDistance:0}},"auto-start:check":function(t){if(!(t.interaction.pointers.length<2)){var e=t.interactable.options.gesture;if(e&&e.enabled)return t.action={name:"gesture"},!1}}},defaults:{},getCursor:function(){return""},filterEventType:function(t){return t.search("gesture")===0}},Cn=pt;function $n(t,e,n,o,r,i,a){if(!e)return!1;if(e===!0){var s=y.number(i.width)?i.width:i.right-i.left,c=y.number(i.height)?i.height:i.bottom-i.top;if(a=Math.min(a,Math.abs((t==="left"||t==="right"?s:c)/2)),s<0&&(t==="left"?t="right":t==="right"&&(t="left")),c<0&&(t==="top"?t="bottom":t==="bottom"&&(t="top")),t==="left"){var d=s>=0?i.left:i.right;return n.x<d+a}if(t==="top"){var l=c>=0?i.top:i.bottom;return n.y<l+a}if(t==="right")return n.x>(s>=0?i.right:i.left)-a;if(t==="bottom")return n.y>(c>=0?i.bottom:i.top)-a}return!!y.element(o)&&(y.element(e)?e===o:Qe(o,e,r))}function Ut(t){var e=t.iEvent,n=t.interaction;if(n.prepared.name==="resize"&&n.resizeAxes){var o=e;n.interactable.options.resize.square?(n.resizeAxes==="y"?o.delta.x=o.delta.y:o.delta.y=o.delta.x,o.axes="xy"):(o.axes=n.resizeAxes,n.resizeAxes==="x"?o.delta.y=0:n.resizeAxes==="y"&&(o.delta.x=0))}}var te,ue,ne={id:"actions/resize",before:["actions/drag"],install:function(t){var e=t.actions,n=t.browser,o=t.Interactable,r=t.defaults;ne.cursors=(function(i){return i.isIe9?{x:"e-resize",y:"s-resize",xy:"se-resize",top:"n-resize",left:"w-resize",bottom:"s-resize",right:"e-resize",topleft:"se-resize",bottomright:"se-resize",topright:"ne-resize",bottomleft:"ne-resize"}:{x:"ew-resize",y:"ns-resize",xy:"nwse-resize",top:"ns-resize",left:"ew-resize",bottom:"ns-resize",right:"ew-resize",topleft:"nwse-resize",bottomright:"nwse-resize",topright:"nesw-resize",bottomleft:"nesw-resize"}})(n),ne.defaultMargin=n.supportsTouch||n.supportsPointerEvent?20:10,o.prototype.resizable=function(i){return(function(a,s,c){return y.object(s)?(a.options.resize.enabled=s.enabled!==!1,a.setPerAction("resize",s),a.setOnEvents("resize",s),y.string(s.axis)&&/^x$|^y$|^xy$/.test(s.axis)?a.options.resize.axis=s.axis:s.axis===null&&(a.options.resize.axis=c.defaults.actions.resize.axis),y.bool(s.preserveAspectRatio)?a.options.resize.preserveAspectRatio=s.preserveAspectRatio:y.bool(s.square)&&(a.options.resize.square=s.square),a):y.bool(s)?(a.options.resize.enabled=s,a):a.options.resize})(this,i,t)},e.map.resize=ne,e.methodDict.resize="resizable",r.actions.resize=ne.defaults},listeners:{"interactions:new":function(t){t.interaction.resizeAxes="xy"},"interactions:action-start":function(t){(function(e){var n=e.iEvent,o=e.interaction;if(o.prepared.name==="resize"&&o.prepared.edges){var r=n,i=o.rect;o._rects={start:I({},i),corrected:I({},i),previous:I({},i),delta:{left:0,right:0,width:0,top:0,bottom:0,height:0}},r.edges=o.prepared.edges,r.rect=o._rects.corrected,r.deltaRect=o._rects.delta}})(t),Ut(t)},"interactions:action-move":function(t){(function(e){var n=e.iEvent,o=e.interaction;if(o.prepared.name==="resize"&&o.prepared.edges){var r=n,i=o.interactable.options.resize.invert,a=i==="reposition"||i==="negate",s=o.rect,c=o._rects,d=c.start,l=c.corrected,u=c.delta,g=c.previous;if(I(g,l),a){if(I(l,s),i==="reposition"){if(l.top>l.bottom){var x=l.top;l.top=l.bottom,l.bottom=x}if(l.left>l.right){var b=l.left;l.left=l.right,l.right=b}}}else l.top=Math.min(s.top,d.bottom),l.bottom=Math.max(s.bottom,d.top),l.left=Math.min(s.left,d.right),l.right=Math.max(s.right,d.left);for(var w in l.width=l.right-l.left,l.height=l.bottom-l.top,l)u[w]=l[w]-g[w];r.edges=o.prepared.edges,r.rect=l,r.deltaRect=u}})(t),Ut(t)},"interactions:action-end":function(t){var e=t.iEvent,n=t.interaction;if(n.prepared.name==="resize"&&n.prepared.edges){var o=e;o.edges=n.prepared.edges,o.rect=n._rects.corrected,o.deltaRect=n._rects.delta}},"auto-start:check":function(t){var e=t.interaction,n=t.interactable,o=t.element,r=t.rect,i=t.buttons;if(r){var a=I({},e.coords.cur.page),s=n.options.resize;if(s&&s.enabled&&(!e.pointerIsDown||!/mouse|pointer/.test(e.pointerType)||(i&s.mouseButtons)!=0)){if(y.object(s.edges)){var c={left:!1,right:!1,top:!1,bottom:!1};for(var d in c)c[d]=$n(d,s.edges[d],a,e._latestPointer.eventTarget,o,r,s.margin||ne.defaultMargin);c.left=c.left&&!c.right,c.top=c.top&&!c.bottom,(c.left||c.right||c.top||c.bottom)&&(t.action={name:"resize",edges:c})}else{var l=s.axis!=="y"&&a.x>r.right-ne.defaultMargin,u=s.axis!=="x"&&a.y>r.bottom-ne.defaultMargin;(l||u)&&(t.action={name:"resize",axes:(l?"x":"")+(u?"y":"")})}return!t.action&&void 0}}}},defaults:{square:!1,preserveAspectRatio:!1,axis:"xy",margin:NaN,edges:null,invert:"none"},cursors:null,getCursor:function(t){var e=t.edges,n=t.axis,o=t.name,r=ne.cursors,i=null;if(n)i=r[o+n];else if(e){for(var a="",s=0,c=["top","bottom","left","right"];s<c.length;s++){var d=c[s];e[d]&&(a+=d)}i=r[a]}return i},filterEventType:function(t){return t.search("resize")===0},defaultMargin:null},Ln=ne,Fn={id:"actions",install:function(t){t.usePlugin(Cn),t.usePlugin(Ln),t.usePlugin(zt),t.usePlugin(An)}},Kt=0,ie={request:function(t){return te(t)},cancel:function(t){return ue(t)},init:function(t){if(te=t.requestAnimationFrame,ue=t.cancelAnimationFrame,!te)for(var e=["ms","moz","webkit","o"],n=0;n<e.length;n++){var o=e[n];te=t["".concat(o,"RequestAnimationFrame")],ue=t["".concat(o,"CancelAnimationFrame")]||t["".concat(o,"CancelRequestAnimationFrame")]}te=te&&te.bind(t),ue=ue&&ue.bind(t),te||(te=function(r){var i=Date.now(),a=Math.max(0,16-(i-Kt)),s=t.setTimeout((function(){r(i+a)}),a);return Kt=i+a,s},ue=function(r){return clearTimeout(r)})}},z={defaults:{enabled:!1,margin:60,container:null,speed:300},now:Date.now,interaction:null,i:0,x:0,y:0,isScrolling:!1,prevTime:0,margin:0,speed:0,start:function(t){z.isScrolling=!0,ie.cancel(z.i),t.autoScroll=z,z.interaction=t,z.prevTime=z.now(),z.i=ie.request(z.scroll)},stop:function(){z.isScrolling=!1,z.interaction&&(z.interaction.autoScroll=null),ie.cancel(z.i)},scroll:function(){var t=z.interaction,e=t.interactable,n=t.element,o=t.prepared.name,r=e.options[o].autoScroll,i=Zt(r.container,e,n),a=z.now(),s=(a-z.prevTime)/1e3,c=r.speed*s;if(c>=1){var d={x:z.x*c,y:z.y*c};if(d.x||d.y){var l=Qt(i);y.window(i)?i.scrollBy(d.x,d.y):i&&(i.scrollLeft+=d.x,i.scrollTop+=d.y);var u=Qt(i),g={x:u.x-l.x,y:u.y-l.y};(g.x||g.y)&&e.fire({type:"autoscroll",target:n,interactable:e,delta:g,interaction:t,container:i})}z.prevTime=a}z.isScrolling&&(ie.cancel(z.i),z.i=ie.request(z.scroll))},check:function(t,e){var n;return(n=t.options[e].autoScroll)==null?void 0:n.enabled},onInteractionMove:function(t){var e=t.interaction,n=t.pointer;if(e.interacting()&&z.check(e.interactable,e.prepared.name))if(e.simulation)z.x=z.y=0;else{var o,r,i,a,s=e.interactable,c=e.element,d=e.prepared.name,l=s.options[d].autoScroll,u=Zt(l.container,s,c);if(y.window(u))a=n.clientX<z.margin,o=n.clientY<z.margin,r=n.clientX>u.innerWidth-z.margin,i=n.clientY>u.innerHeight-z.margin;else{var g=Je(u);a=n.clientX<g.left+z.margin,o=n.clientY<g.top+z.margin,r=n.clientX>g.right-z.margin,i=n.clientY>g.bottom-z.margin}z.x=r?1:a?-1:0,z.y=i?1:o?-1:0,z.isScrolling||(z.margin=l.margin,z.speed=l.speed,z.start(e))}}};function Zt(t,e,n){return(y.string(t)?$t(t,e,n):t)||J(n)}function Qt(t){return y.window(t)&&(t=window.document.body),{x:t.scrollLeft,y:t.scrollTop}}var jn={id:"auto-scroll",install:function(t){var e=t.defaults,n=t.actions;t.autoScroll=z,z.now=function(){return t.now()},n.phaselessTypes.autoscroll=!0,e.perAction.autoScroll=z.defaults},listeners:{"interactions:new":function(t){t.interaction.autoScroll=null},"interactions:destroy":function(t){t.interaction.autoScroll=null,z.stop(),z.interaction&&(z.interaction=null)},"interactions:stop":z.stop,"interactions:action-move":function(t){return z.onInteractionMove(t)}}},Nn=jn;function Te(t,e){var n=!1;return function(){return n||(B.console.warn(e),n=!0),t.apply(this,arguments)}}function dt(t,e){return t.name=e.name,t.axis=e.axis,t.edges=e.edges,t}function Rn(t){return y.bool(t)?(this.options.styleCursor=t,this):t===null?(delete this.options.styleCursor,this):this.options.styleCursor}function qn(t){return y.func(t)?(this.options.actionChecker=t,this):t===null?(delete this.options.actionChecker,this):this.options.actionChecker}var Xn={id:"auto-start/interactableMethods",install:function(t){var e=t.Interactable;e.prototype.getAction=function(n,o,r,i){var a=(function(s,c,d,l,u){var g=s.getRect(l),x=c.buttons||{0:1,1:4,3:8,4:16}[c.button],b={action:null,interactable:s,interaction:d,element:l,rect:g,buttons:x};return u.fire("auto-start:check",b),b.action})(this,o,r,i,t);return this.options.actionChecker?this.options.actionChecker(n,o,a,this,i,r):a},e.prototype.ignoreFrom=Te((function(n){return this._backCompatOption("ignoreFrom",n)}),"Interactable.ignoreFrom() has been deprecated. Use Interactble.draggable({ignoreFrom: newValue})."),e.prototype.allowFrom=Te((function(n){return this._backCompatOption("allowFrom",n)}),"Interactable.allowFrom() has been deprecated. Use Interactble.draggable({allowFrom: newValue})."),e.prototype.actionChecker=qn,e.prototype.styleCursor=Rn}};function Jt(t,e,n,o,r){return e.testIgnoreAllow(e.options[t.name],n,o)&&e.options[t.name].enabled&&Re(e,n,t,r)?t:null}function Yn(t,e,n,o,r,i,a){for(var s=0,c=o.length;s<c;s++){var d=o[s],l=r[s],u=d.getAction(e,n,t,l);if(u){var g=Jt(u,d,l,i,a);if(g)return{action:g,interactable:d,element:l}}}return{action:null,interactable:null,element:null}}function en(t,e,n,o,r){var i=[],a=[],s=o;function c(l){i.push(l),a.push(s)}for(;y.element(s);){i=[],a=[],r.interactables.forEachMatch(s,c);var d=Yn(t,e,n,i,a,o,r);if(d.action&&!d.interactable.options[d.action.name].manualStart)return d;s=ee(s)}return{action:null,interactable:null,element:null}}function tn(t,e,n){var o=e.action,r=e.interactable,i=e.element;o=o||{name:null},t.interactable=r,t.element=i,dt(t.prepared,o),t.rect=r&&o.name?r.getRect(i):null,on(t,n),n.fire("autoStart:prepared",{interaction:t})}function Re(t,e,n,o){var r=t.options,i=r[n.name].max,a=r[n.name].maxPerElement,s=o.autoStart.maxInteractions,c=0,d=0,l=0;if(!(i&&a&&s))return!1;for(var u=0,g=o.interactions.list;u<g.length;u++){var x=g[u],b=x.prepared.name;if(x.interacting()&&(++c>=s||x.interactable===t&&((d+=b===n.name?1:0)>=i||x.element===e&&(l++,b===n.name&&l>=a))))return!1}return s>0}function nn(t,e){return y.number(t)?(e.autoStart.maxInteractions=t,this):e.autoStart.maxInteractions}function ut(t,e,n){var o=n.autoStart.cursorElement;o&&o!==t&&(o.style.cursor=""),t.ownerDocument.documentElement.style.cursor=e,t.style.cursor=e,n.autoStart.cursorElement=e?t:null}function on(t,e){var n=t.interactable,o=t.element,r=t.prepared;if(t.pointerType==="mouse"&&n&&n.options.styleCursor){var i="";if(r.name){var a=n.options[r.name].cursorChecker;i=y.func(a)?a(r,n,o,t._interacting):e.actions.map[r.name].getCursor(r)}ut(t.element,i||"",e)}else e.autoStart.cursorElement&&ut(e.autoStart.cursorElement,"",e)}var Bn={id:"auto-start/base",before:["actions"],install:function(t){var e=t.interactStatic,n=t.defaults;t.usePlugin(Xn),n.base.actionChecker=null,n.base.styleCursor=!0,I(n.perAction,{manualStart:!1,max:1/0,maxPerElement:1,allowFrom:null,ignoreFrom:null,mouseButtons:1}),e.maxInteractions=function(o){return nn(o,t)},t.autoStart={maxInteractions:1/0,withinInteractionLimit:Re,cursorElement:null}},listeners:{"interactions:down":function(t,e){var n=t.interaction,o=t.pointer,r=t.event,i=t.eventTarget;n.interacting()||tn(n,en(n,o,r,i,e),e)},"interactions:move":function(t,e){(function(n,o){var r=n.interaction,i=n.pointer,a=n.event,s=n.eventTarget;r.pointerType!=="mouse"||r.pointerIsDown||r.interacting()||tn(r,en(r,i,a,s,o),o)})(t,e),(function(n,o){var r=n.interaction;if(r.pointerIsDown&&!r.interacting()&&r.pointerWasMoved&&r.prepared.name){o.fire("autoStart:before-start",n);var i=r.interactable,a=r.prepared.name;a&&i&&(i.options[a].manualStart||!Re(i,r.element,r.prepared,o)?r.stop():(r.start(r.prepared,i,r.element),on(r,o)))}})(t,e)},"interactions:stop":function(t,e){var n=t.interaction,o=n.interactable;o&&o.options.styleCursor&&ut(n.element,"",e)}},maxInteractions:nn,withinInteractionLimit:Re,validateAction:Jt},ht=Bn,Hn={id:"auto-start/dragAxis",listeners:{"autoStart:before-start":function(t,e){var n=t.interaction,o=t.eventTarget,r=t.dx,i=t.dy;if(n.prepared.name==="drag"){var a=Math.abs(r),s=Math.abs(i),c=n.interactable.options.drag,d=c.startAxis,l=a>s?"x":a<s?"y":"xy";if(n.prepared.axis=c.lockAxis==="start"?l[0]:c.lockAxis,l!=="xy"&&d!=="xy"&&d!==l){n.prepared.name=null;for(var u=o,g=function(b){if(b!==n.interactable){var w=n.interactable.options.drag;if(!w.manualStart&&b.testIgnoreAllow(w,u,o)){var E=b.getAction(n.downPointer,n.downEvent,n,u);if(E&&E.name==="drag"&&(function(_,S){if(!S)return!1;var O=S.options.drag.startAxis;return _==="xy"||O==="xy"||O===_})(l,b)&&ht.validateAction(E,b,u,o,e))return b}}};y.element(u);){var x=e.interactables.forEachMatch(u,g);if(x){n.prepared.name="drag",n.interactable=x,n.element=u;break}u=ee(u)}}}}}};function ft(t){var e=t.prepared&&t.prepared.name;if(!e)return null;var n=t.interactable.options;return n[e].hold||n[e].delay}var Wn={id:"auto-start/hold",install:function(t){var e=t.defaults;t.usePlugin(ht),e.perAction.hold=0,e.perAction.delay=0},listeners:{"interactions:new":function(t){t.interaction.autoStartHoldTimer=null},"autoStart:prepared":function(t){var e=t.interaction,n=ft(e);n>0&&(e.autoStartHoldTimer=setTimeout((function(){e.start(e.prepared,e.interactable,e.element)}),n))},"interactions:move":function(t){var e=t.interaction,n=t.duplicate;e.autoStartHoldTimer&&e.pointerWasMoved&&!n&&(clearTimeout(e.autoStartHoldTimer),e.autoStartHoldTimer=null)},"autoStart:before-start":function(t){var e=t.interaction;ft(e)>0&&(e.prepared.name=null)}},getHoldDuration:ft},Gn=Wn,Vn={id:"auto-start",install:function(t){t.usePlugin(ht),t.usePlugin(Gn),t.usePlugin(Hn)}},Un=function(t){return/^(always|never|auto)$/.test(t)?(this.options.preventDefault=t,this):y.bool(t)?(this.options.preventDefault=t?"always":"never",this):this.options.preventDefault};function Kn(t){var e=t.interaction,n=t.event;e.interactable&&e.interactable.checkAndPreventDefault(n)}var rn={id:"core/interactablePreventDefault",install:function(t){var e=t.Interactable;e.prototype.preventDefault=Un,e.prototype.checkAndPreventDefault=function(n){return(function(o,r,i){var a=o.options.preventDefault;if(a!=="never")if(a!=="always"){if(r.events.supportsPassive&&/^touch(start|move)$/.test(i.type)){var s=J(i.target).document,c=r.getDocOptions(s);if(!c||!c.events||c.events.passive!==!1)return}/^(mouse|pointer|touch)*(down|start)/i.test(i.type)||y.element(i.target)&&re(i.target,"input,select,textarea,[contenteditable=true],[contenteditable=true] *")||i.preventDefault()}else i.preventDefault()})(this,t,n)},t.interactions.docEvents.push({type:"dragstart",listener:function(n){for(var o=0,r=t.interactions.list;o<r.length;o++){var i=r[o];if(i.element&&(i.element===n.target||pe(i.element,n.target)))return void i.interactable.checkAndPreventDefault(n)}}})},listeners:["down","move","up","cancel"].reduce((function(t,e){return t["interactions:".concat(e)]=Kn,t}),{})};function qe(t,e){if(e.phaselessTypes[t])return!0;for(var n in e.map)if(t.indexOf(n)===0&&t.substr(n.length)in e.phases)return!0;return!1}function me(t){var e={};for(var n in t){var o=t[n];y.plainObject(o)?e[n]=me(o):y.array(o)?e[n]=Bt(o):e[n]=o}return e}var vt=(function(){function t(e){p(this,t),this.states=[],this.startOffset={left:0,right:0,top:0,bottom:0},this.startDelta=void 0,this.result=void 0,this.endResult=void 0,this.startEdges=void 0,this.edges=void 0,this.interaction=void 0,this.interaction=e,this.result=Xe(),this.edges={left:!1,right:!1,top:!1,bottom:!1}}return v(t,[{key:"start",value:function(e,n){var o,r,i=e.phase,a=this.interaction,s=(function(d){var l=d.interactable.options[d.prepared.name],u=l.modifiers;return u&&u.length?u:["snap","snapSize","snapEdges","restrict","restrictEdges","restrictSize"].map((function(g){var x=l[g];return x&&x.enabled&&{options:x,methods:x._methods}})).filter((function(g){return!!g}))})(a);this.prepareStates(s),this.startEdges=I({},a.edges),this.edges=I({},this.startEdges),this.startOffset=(o=a.rect,r=n,o?{left:r.x-o.left,top:r.y-o.top,right:o.right-r.x,bottom:o.bottom-r.y}:{left:0,top:0,right:0,bottom:0}),this.startDelta={x:0,y:0};var c=this.fillArg({phase:i,pageCoords:n,preEnd:!1});return this.result=Xe(),this.startAll(c),this.result=this.setAll(c)}},{key:"fillArg",value:function(e){var n=this.interaction;return e.interaction=n,e.interactable=n.interactable,e.element=n.element,e.rect||(e.rect=n.rect),e.edges||(e.edges=this.startEdges),e.startOffset=this.startOffset,e}},{key:"startAll",value:function(e){for(var n=0,o=this.states;n<o.length;n++){var r=o[n];r.methods.start&&(e.state=r,r.methods.start(e))}}},{key:"setAll",value:function(e){var n=e.phase,o=e.preEnd,r=e.skipModifiers,i=e.rect,a=e.edges;e.coords=I({},e.pageCoords),e.rect=I({},i),e.edges=I({},a);for(var s=r?this.states.slice(r):this.states,c=Xe(e.coords,e.rect),d=0;d<s.length;d++){var l,u=s[d],g=u.options,x=I({},e.coords),b=null;(l=u.methods)!=null&&l.set&&this.shouldDo(g,o,n)&&(e.state=u,b=u.methods.set(e),$e(e.edges,e.rect,{x:e.coords.x-x.x,y:e.coords.y-x.y})),c.eventProps.push(b)}I(this.edges,e.edges),c.delta.x=e.coords.x-e.pageCoords.x,c.delta.y=e.coords.y-e.pageCoords.y,c.rectDelta.left=e.rect.left-i.left,c.rectDelta.right=e.rect.right-i.right,c.rectDelta.top=e.rect.top-i.top,c.rectDelta.bottom=e.rect.bottom-i.bottom;var w=this.result.coords,E=this.result.rect;if(w&&E){var _=c.rect.left!==E.left||c.rect.right!==E.right||c.rect.top!==E.top||c.rect.bottom!==E.bottom;c.changed=_||w.x!==c.coords.x||w.y!==c.coords.y}return c}},{key:"applyToInteraction",value:function(e){var n=this.interaction,o=e.phase,r=n.coords.cur,i=n.coords.start,a=this.result,s=this.startDelta,c=a.delta;o==="start"&&I(this.startDelta,a.delta);for(var d=0,l=[[i,s],[r,c]];d<l.length;d++){var u=l[d],g=u[0],x=u[1];g.page.x+=x.x,g.page.y+=x.y,g.client.x+=x.x,g.client.y+=x.y}var b=this.result.rectDelta,w=e.rect||n.rect;w.left+=b.left,w.right+=b.right,w.top+=b.top,w.bottom+=b.bottom,w.width=w.right-w.left,w.height=w.bottom-w.top}},{key:"setAndApply",value:function(e){var n=this.interaction,o=e.phase,r=e.preEnd,i=e.skipModifiers,a=this.setAll(this.fillArg({preEnd:r,phase:o,pageCoords:e.modifiedCoords||n.coords.cur.page}));if(this.result=a,!a.changed&&(!i||i<this.states.length)&&n.interacting())return!1;if(e.modifiedCoords){var s=n.coords.cur.page,c={x:e.modifiedCoords.x-s.x,y:e.modifiedCoords.y-s.y};a.coords.x+=c.x,a.coords.y+=c.y,a.delta.x+=c.x,a.delta.y+=c.y}this.applyToInteraction(e)}},{key:"beforeEnd",value:function(e){var n=e.interaction,o=e.event,r=this.states;if(r&&r.length){for(var i=!1,a=0;a<r.length;a++){var s=r[a];e.state=s;var c=s.options,d=s.methods,l=d.beforeEnd&&d.beforeEnd(e);if(l)return this.endResult=l,!1;i=i||!i&&this.shouldDo(c,!0,e.phase,!0)}i&&n.move({event:o,preEnd:!0})}}},{key:"stop",value:function(e){var n=e.interaction;if(this.states&&this.states.length){var o=I({states:this.states,interactable:n.interactable,element:n.element,rect:null},e);this.fillArg(o);for(var r=0,i=this.states;r<i.length;r++){var a=i[r];o.state=a,a.methods.stop&&a.methods.stop(o)}this.states=null,this.endResult=null}}},{key:"prepareStates",value:function(e){this.states=[];for(var n=0;n<e.length;n++){var o=e[n],r=o.options,i=o.methods,a=o.name;this.states.push({options:r,methods:i,index:n,name:a})}return this.states}},{key:"restoreInteractionCoords",value:function(e){var n=e.interaction,o=n.coords,r=n.rect,i=n.modification;if(i.result){for(var a=i.startDelta,s=i.result,c=s.delta,d=s.rectDelta,l=0,u=[[o.start,a],[o.cur,c]];l<u.length;l++){var g=u[l],x=g[0],b=g[1];x.page.x-=b.x,x.page.y-=b.y,x.client.x-=b.x,x.client.y-=b.y}r.left-=d.left,r.right-=d.right,r.top-=d.top,r.bottom-=d.bottom}}},{key:"shouldDo",value:function(e,n,o,r){return!(!e||e.enabled===!1||r&&!e.endOnly||e.endOnly&&!n||o==="start"&&!e.setStart)}},{key:"copyFrom",value:function(e){this.startOffset=e.startOffset,this.startDelta=e.startDelta,this.startEdges=e.startEdges,this.edges=e.edges,this.states=e.states.map((function(n){return me(n)})),this.result=Xe(I({},e.result.coords),I({},e.result.rect))}},{key:"destroy",value:function(){for(var e in this)this[e]=null}}]),t})();function Xe(t,e){return{rect:e,coords:t,delta:{x:0,y:0},rectDelta:{left:0,right:0,top:0,bottom:0},eventProps:[],changed:!0}}function ae(t,e){var n=t.defaults,o={start:t.start,set:t.set,beforeEnd:t.beforeEnd,stop:t.stop},r=function(i){var a=i||{};for(var s in a.enabled=a.enabled!==!1,n)s in a||(a[s]=n[s]);var c={options:a,methods:o,name:e,enable:function(){return a.enabled=!0,c},disable:function(){return a.enabled=!1,c}};return c};return e&&typeof e=="string"&&(r._defaults=n,r._methods=o),r}function Se(t){var e=t.iEvent,n=t.interaction.modification.result;n&&(e.modifiers=n.eventProps)}var Zn={id:"modifiers/base",before:["actions"],install:function(t){t.defaults.perAction.modifiers=[]},listeners:{"interactions:new":function(t){var e=t.interaction;e.modification=new vt(e)},"interactions:before-action-start":function(t){var e=t.interaction,n=t.interaction.modification;n.start(t,e.coords.start.page),e.edges=n.edges,n.applyToInteraction(t)},"interactions:before-action-move":function(t){var e=t.interaction,n=e.modification,o=n.setAndApply(t);return e.edges=n.edges,o},"interactions:before-action-end":function(t){var e=t.interaction,n=e.modification,o=n.beforeEnd(t);return e.edges=n.startEdges,o},"interactions:action-start":Se,"interactions:action-move":Se,"interactions:action-end":Se,"interactions:after-action-start":function(t){return t.interaction.modification.restoreInteractionCoords(t)},"interactions:after-action-move":function(t){return t.interaction.modification.restoreInteractionCoords(t)},"interactions:stop":function(t){return t.interaction.modification.stop(t)}}},an=Zn,sn={base:{preventDefault:"auto",deltaSource:"page"},perAction:{enabled:!1,origin:{x:0,y:0}},actions:{}},gt=(function(t){m(n,t);var e=$(n);function n(o,r,i,a,s,c,d){var l;p(this,n),(l=e.call(this,o)).relatedTarget=null,l.screenX=void 0,l.screenY=void 0,l.button=void 0,l.buttons=void 0,l.ctrlKey=void 0,l.shiftKey=void 0,l.altKey=void 0,l.metaKey=void 0,l.page=void 0,l.client=void 0,l.delta=void 0,l.rect=void 0,l.x0=void 0,l.y0=void 0,l.t0=void 0,l.dt=void 0,l.duration=void 0,l.clientX0=void 0,l.clientY0=void 0,l.velocity=void 0,l.speed=void 0,l.swipe=void 0,l.axes=void 0,l.preEnd=void 0,s=s||o.element;var u=o.interactable,g=(u&&u.options||sn).deltaSource,x=xe(u,s,i),b=a==="start",w=a==="end",E=b?M(l):o.prevEvent,_=b?o.coords.start:w?{page:E.page,client:E.client,timeStamp:o.coords.cur.timeStamp}:o.coords.cur;return l.page=I({},_.page),l.client=I({},_.client),l.rect=I({},o.rect),l.timeStamp=_.timeStamp,w||(l.page.x-=x.x,l.page.y-=x.y,l.client.x-=x.x,l.client.y-=x.y),l.ctrlKey=r.ctrlKey,l.altKey=r.altKey,l.shiftKey=r.shiftKey,l.metaKey=r.metaKey,l.button=r.button,l.buttons=r.buttons,l.target=s,l.currentTarget=s,l.preEnd=c,l.type=d||i+(a||""),l.interactable=u,l.t0=b?o.pointers[o.pointers.length-1].downTime:E.t0,l.x0=o.coords.start.page.x-x.x,l.y0=o.coords.start.page.y-x.y,l.clientX0=o.coords.start.client.x-x.x,l.clientY0=o.coords.start.client.y-x.y,l.delta=b||w?{x:0,y:0}:{x:l[g].x-E[g].x,y:l[g].y-E[g].y},l.dt=o.coords.delta.timeStamp,l.duration=l.timeStamp-l.t0,l.velocity=I({},o.coords.velocity[g]),l.speed=we(l.velocity.x,l.velocity.y),l.swipe=w||a==="inertiastart"?l.getSwipe():null,l}return v(n,[{key:"getSwipe",value:function(){var o=this._interaction;if(o.prevEvent.speed<600||this.timeStamp-o.prevEvent.timeStamp>150)return null;var r=180*Math.atan2(o.prevEvent.velocityY,o.prevEvent.velocityX)/Math.PI;r<0&&(r+=360);var i=112.5<=r&&r<247.5,a=202.5<=r&&r<337.5;return{up:a,down:!a&&22.5<=r&&r<157.5,left:i,right:!i&&(292.5<=r||r<67.5),angle:r,speed:o.prevEvent.speed,velocity:{x:o.prevEvent.velocityX,y:o.prevEvent.velocityY}}}},{key:"preventDefault",value:function(){}},{key:"stopImmediatePropagation",value:function(){this.immediatePropagationStopped=this.propagationStopped=!0}},{key:"stopPropagation",value:function(){this.propagationStopped=!0}}]),n})(Ne);Object.defineProperties(gt.prototype,{pageX:{get:function(){return this.page.x},set:function(t){this.page.x=t}},pageY:{get:function(){return this.page.y},set:function(t){this.page.y=t}},clientX:{get:function(){return this.client.x},set:function(t){this.client.x=t}},clientY:{get:function(){return this.client.y},set:function(t){this.client.y=t}},dx:{get:function(){return this.delta.x},set:function(t){this.delta.x=t}},dy:{get:function(){return this.delta.y},set:function(t){this.delta.y=t}},velocityX:{get:function(){return this.velocity.x},set:function(t){this.velocity.x=t}},velocityY:{get:function(){return this.velocity.y},set:function(t){this.velocity.y=t}}});var Qn=v((function t(e,n,o,r,i){p(this,t),this.id=void 0,this.pointer=void 0,this.event=void 0,this.downTime=void 0,this.downTarget=void 0,this.id=e,this.pointer=n,this.event=o,this.downTime=r,this.downTarget=i})),Jn=(function(t){return t.interactable="",t.element="",t.prepared="",t.pointerIsDown="",t.pointerWasMoved="",t._proxy="",t})({}),cn=(function(t){return t.start="",t.move="",t.end="",t.stop="",t.interacting="",t})({}),eo=0,to=(function(){function t(e){var n=this,o=e.pointerType,r=e.scopeFire;p(this,t),this.interactable=null,this.element=null,this.rect=null,this._rects=void 0,this.edges=null,this._scopeFire=void 0,this.prepared={name:null,axis:null,edges:null},this.pointerType=void 0,this.pointers=[],this.downEvent=null,this.downPointer={},this._latestPointer={pointer:null,event:null,eventTarget:null},this.prevEvent=null,this.pointerIsDown=!1,this.pointerWasMoved=!1,this._interacting=!1,this._ending=!1,this._stopped=!0,this._proxy=void 0,this.simulation=null,this.doMove=Te((function(l){this.move(l)}),"The interaction.doMove() method has been renamed to interaction.move()"),this.coords={start:{page:{x:0,y:0},client:{x:0,y:0},timeStamp:0},prev:{page:{x:0,y:0},client:{x:0,y:0},timeStamp:0},cur:{page:{x:0,y:0},client:{x:0,y:0},timeStamp:0},delta:{page:{x:0,y:0},client:{x:0,y:0},timeStamp:0},velocity:{page:{x:0,y:0},client:{x:0,y:0},timeStamp:0}},this._id=eo++,this._scopeFire=r,this.pointerType=o;var i=this;this._proxy={};var a=function(l){Object.defineProperty(n._proxy,l,{get:function(){return i[l]}})};for(var s in Jn)a(s);var c=function(l){Object.defineProperty(n._proxy,l,{value:function(){return i[l].apply(i,arguments)}})};for(var d in cn)c(d);this._scopeFire("interactions:new",{interaction:this})}return v(t,[{key:"pointerMoveTolerance",get:function(){return 1}},{key:"pointerDown",value:function(e,n,o){var r=this.updatePointer(e,n,o,!0),i=this.pointers[r];this._scopeFire("interactions:down",{pointer:e,event:n,eventTarget:o,pointerIndex:r,pointerInfo:i,type:"down",interaction:this})}},{key:"start",value:function(e,n,o){return!(this.interacting()||!this.pointerIsDown||this.pointers.length<(e.name==="gesture"?2:1)||!n.options[e.name].enabled)&&(dt(this.prepared,e),this.interactable=n,this.element=o,this.rect=n.getRect(o),this.edges=this.prepared.edges?I({},this.prepared.edges):{left:!0,right:!0,top:!0,bottom:!0},this._stopped=!1,this._interacting=this._doPhase({interaction:this,event:this.downEvent,phase:"start"})&&!this._stopped,this._interacting)}},{key:"pointerMove",value:function(e,n,o){this.simulation||this.modification&&this.modification.endResult||this.updatePointer(e,n,o,!1);var r,i,a=this.coords.cur.page.x===this.coords.prev.page.x&&this.coords.cur.page.y===this.coords.prev.page.y&&this.coords.cur.client.x===this.coords.prev.client.x&&this.coords.cur.client.y===this.coords.prev.client.y;this.pointerIsDown&&!this.pointerWasMoved&&(r=this.coords.cur.client.x-this.coords.start.client.x,i=this.coords.cur.client.y-this.coords.start.client.y,this.pointerWasMoved=we(r,i)>this.pointerMoveTolerance);var s,c,d,l=this.getPointerIndex(e),u={pointer:e,pointerIndex:l,pointerInfo:this.pointers[l],event:n,type:"move",eventTarget:o,dx:r,dy:i,duplicate:a,interaction:this};a||(s=this.coords.velocity,c=this.coords.delta,d=Math.max(c.timeStamp/1e3,.001),s.page.x=c.page.x/d,s.page.y=c.page.y/d,s.client.x=c.client.x/d,s.client.y=c.client.y/d,s.timeStamp=d),this._scopeFire("interactions:move",u),a||this.simulation||(this.interacting()&&(u.type=null,this.move(u)),this.pointerWasMoved&&Fe(this.coords.prev,this.coords.cur))}},{key:"move",value:function(e){e&&e.event||Ft(this.coords.delta),(e=I({pointer:this._latestPointer.pointer,event:this._latestPointer.event,eventTarget:this._latestPointer.eventTarget,interaction:this},e||{})).phase="move",this._doPhase(e)}},{key:"pointerUp",value:function(e,n,o,r){var i=this.getPointerIndex(e);i===-1&&(i=this.updatePointer(e,n,o,!1));var a=/cancel$/i.test(n.type)?"cancel":"up";this._scopeFire("interactions:".concat(a),{pointer:e,pointerIndex:i,pointerInfo:this.pointers[i],event:n,eventTarget:o,type:a,curEventTarget:r,interaction:this}),this.simulation||this.end(n),this.removePointer(e,n)}},{key:"documentBlur",value:function(e){this.end(e),this._scopeFire("interactions:blur",{event:e,type:"blur",interaction:this})}},{key:"end",value:function(e){var n;this._ending=!0,e=e||this._latestPointer.event,this.interacting()&&(n=this._doPhase({event:e,interaction:this,phase:"end"})),this._ending=!1,n===!0&&this.stop()}},{key:"currentAction",value:function(){return this._interacting?this.prepared.name:null}},{key:"interacting",value:function(){return this._interacting}},{key:"stop",value:function(){this._scopeFire("interactions:stop",{interaction:this}),this.interactable=this.element=null,this._interacting=!1,this._stopped=!0,this.prepared.name=this.prevEvent=null}},{key:"getPointerIndex",value:function(e){var n=ke(e);return this.pointerType==="mouse"||this.pointerType==="pen"?this.pointers.length-1:_e(this.pointers,(function(o){return o.id===n}))}},{key:"getPointerInfo",value:function(e){return this.pointers[this.getPointerIndex(e)]}},{key:"updatePointer",value:function(e,n,o,r){var i,a,s,c=ke(e),d=this.getPointerIndex(e),l=this.pointers[d];return r=r!==!1&&(r||/(down|start)$/i.test(n.type)),l?l.pointer=e:(l=new Qn(c,e,n,null,null),d=this.pointers.length,this.pointers.push(l)),Dn(this.coords.cur,this.pointers.map((function(u){return u.pointer})),this._now()),i=this.coords.delta,a=this.coords.prev,s=this.coords.cur,i.page.x=s.page.x-a.page.x,i.page.y=s.page.y-a.page.y,i.client.x=s.client.x-a.client.x,i.client.y=s.client.y-a.client.y,i.timeStamp=s.timeStamp-a.timeStamp,r&&(this.pointerIsDown=!0,l.downTime=this.coords.cur.timeStamp,l.downTarget=o,Le(this.downPointer,e),this.interacting()||(Fe(this.coords.start,this.coords.cur),Fe(this.coords.prev,this.coords.cur),this.downEvent=n,this.pointerWasMoved=!1)),this._updateLatestPointer(e,n,o),this._scopeFire("interactions:update-pointer",{pointer:e,event:n,eventTarget:o,down:r,pointerInfo:l,pointerIndex:d,interaction:this}),d}},{key:"removePointer",value:function(e,n){var o=this.getPointerIndex(e);if(o!==-1){var r=this.pointers[o];this._scopeFire("interactions:remove-pointer",{pointer:e,event:n,eventTarget:null,pointerIndex:o,pointerInfo:r,interaction:this}),this.pointers.splice(o,1),this.pointerIsDown=!1}}},{key:"_updateLatestPointer",value:function(e,n,o){this._latestPointer.pointer=e,this._latestPointer.event=n,this._latestPointer.eventTarget=o}},{key:"destroy",value:function(){this._latestPointer.pointer=null,this._latestPointer.event=null,this._latestPointer.eventTarget=null}},{key:"_createPreparedEvent",value:function(e,n,o,r){return new gt(this,e,this.prepared.name,n,this.element,o,r)}},{key:"_fireEvent",value:function(e){var n;(n=this.interactable)==null||n.fire(e),(!this.prevEvent||e.timeStamp>=this.prevEvent.timeStamp)&&(this.prevEvent=e)}},{key:"_doPhase",value:function(e){var n=e.event,o=e.phase,r=e.preEnd,i=e.type,a=this.rect;if(a&&o==="move"&&($e(this.edges,a,this.coords.delta[this.interactable.options.deltaSource]),a.width=a.right-a.left,a.height=a.bottom-a.top),this._scopeFire("interactions:before-action-".concat(o),e)===!1)return!1;var s=e.iEvent=this._createPreparedEvent(n,o,r,i);return this._scopeFire("interactions:action-".concat(o),e),o==="start"&&(this.prevEvent=s),this._fireEvent(s),this._scopeFire("interactions:after-action-".concat(o),e),!0}},{key:"_now",value:function(){return Date.now()}}]),t})();function ln(t){pn(t.interaction)}function pn(t){if(!(function(n){return!(!n.offset.pending.x&&!n.offset.pending.y)})(t))return!1;var e=t.offset.pending;return mt(t.coords.cur,e),mt(t.coords.delta,e),$e(t.edges,t.rect,e),e.x=0,e.y=0,!0}function no(t){var e=t.x,n=t.y;this.offset.pending.x+=e,this.offset.pending.y+=n,this.offset.total.x+=e,this.offset.total.y+=n}function mt(t,e){var n=t.page,o=t.client,r=e.x,i=e.y;n.x+=r,n.y+=i,o.x+=r,o.y+=i}cn.offsetBy="";var oo={id:"offset",before:["modifiers","pointer-events","actions","inertia"],install:function(t){t.Interaction.prototype.offsetBy=no},listeners:{"interactions:new":function(t){t.interaction.offset={total:{x:0,y:0},pending:{x:0,y:0}}},"interactions:update-pointer":function(t){return(function(e){e.pointerIsDown&&(mt(e.coords.cur,e.offset.total),e.offset.pending.x=0,e.offset.pending.y=0)})(t.interaction)},"interactions:before-action-start":ln,"interactions:before-action-move":ln,"interactions:before-action-end":function(t){var e=t.interaction;if(pn(e))return e.move({offset:!0}),e.end(),!1},"interactions:stop":function(t){var e=t.interaction;e.offset.total.x=0,e.offset.total.y=0,e.offset.pending.x=0,e.offset.pending.y=0}}},dn=oo,ro=(function(){function t(e){p(this,t),this.active=!1,this.isModified=!1,this.smoothEnd=!1,this.allowResume=!1,this.modification=void 0,this.modifierCount=0,this.modifierArg=void 0,this.startCoords=void 0,this.t0=0,this.v0=0,this.te=0,this.targetOffset=void 0,this.modifiedOffset=void 0,this.currentOffset=void 0,this.lambda_v0=0,this.one_ve_v0=0,this.timeout=void 0,this.interaction=void 0,this.interaction=e}return v(t,[{key:"start",value:function(e){var n=this.interaction,o=Ye(n);if(!o||!o.enabled)return!1;var r=n.coords.velocity.client,i=we(r.x,r.y),a=this.modification||(this.modification=new vt(n));if(a.copyFrom(n.modification),this.t0=n._now(),this.allowResume=o.allowResume,this.v0=i,this.currentOffset={x:0,y:0},this.startCoords=n.coords.cur.page,this.modifierArg=a.fillArg({pageCoords:this.startCoords,preEnd:!0,phase:"inertiastart"}),this.t0-n.coords.cur.timeStamp<50&&i>o.minSpeed&&i>o.endSpeed)this.startInertia();else{if(a.result=a.setAll(this.modifierArg),!a.result.changed)return!1;this.startSmoothEnd()}return n.modification.result.rect=null,n.offsetBy(this.targetOffset),n._doPhase({interaction:n,event:e,phase:"inertiastart"}),n.offsetBy({x:-this.targetOffset.x,y:-this.targetOffset.y}),n.modification.result.rect=null,this.active=!0,n.simulation=this,!0}},{key:"startInertia",value:function(){var e=this,n=this.interaction.coords.velocity.client,o=Ye(this.interaction),r=o.resistance,i=-Math.log(o.endSpeed/this.v0)/r;this.targetOffset={x:(n.x-i)/r,y:(n.y-i)/r},this.te=i,this.lambda_v0=r/this.v0,this.one_ve_v0=1-o.endSpeed/this.v0;var a=this.modification,s=this.modifierArg;s.pageCoords={x:this.startCoords.x+this.targetOffset.x,y:this.startCoords.y+this.targetOffset.y},a.result=a.setAll(s),a.result.changed&&(this.isModified=!0,this.modifiedOffset={x:this.targetOffset.x+a.result.delta.x,y:this.targetOffset.y+a.result.delta.y}),this.onNextFrame((function(){return e.inertiaTick()}))}},{key:"startSmoothEnd",value:function(){var e=this;this.smoothEnd=!0,this.isModified=!0,this.targetOffset={x:this.modification.result.delta.x,y:this.modification.result.delta.y},this.onNextFrame((function(){return e.smoothEndTick()}))}},{key:"onNextFrame",value:function(e){var n=this;this.timeout=ie.request((function(){n.active&&e()}))}},{key:"inertiaTick",value:function(){var e,n,o,r,i,a,s,c=this,d=this.interaction,l=Ye(d).resistance,u=(d._now()-this.t0)/1e3;if(u<this.te){var g,x=1-(Math.exp(-l*u)-this.lambda_v0)/this.one_ve_v0;this.isModified?(e=0,n=0,o=this.targetOffset.x,r=this.targetOffset.y,i=this.modifiedOffset.x,a=this.modifiedOffset.y,g={x:un(s=x,e,o,i),y:un(s,n,r,a)}):g={x:this.targetOffset.x*x,y:this.targetOffset.y*x};var b={x:g.x-this.currentOffset.x,y:g.y-this.currentOffset.y};this.currentOffset.x+=b.x,this.currentOffset.y+=b.y,d.offsetBy(b),d.move(),this.onNextFrame((function(){return c.inertiaTick()}))}else d.offsetBy({x:this.modifiedOffset.x-this.currentOffset.x,y:this.modifiedOffset.y-this.currentOffset.y}),this.end()}},{key:"smoothEndTick",value:function(){var e=this,n=this.interaction,o=n._now()-this.t0,r=Ye(n).smoothEndDuration;if(o<r){var i={x:hn(o,0,this.targetOffset.x,r),y:hn(o,0,this.targetOffset.y,r)},a={x:i.x-this.currentOffset.x,y:i.y-this.currentOffset.y};this.currentOffset.x+=a.x,this.currentOffset.y+=a.y,n.offsetBy(a),n.move({skipModifiers:this.modifierCount}),this.onNextFrame((function(){return e.smoothEndTick()}))}else n.offsetBy({x:this.targetOffset.x-this.currentOffset.x,y:this.targetOffset.y-this.currentOffset.y}),this.end()}},{key:"resume",value:function(e){var n=e.pointer,o=e.event,r=e.eventTarget,i=this.interaction;i.offsetBy({x:-this.currentOffset.x,y:-this.currentOffset.y}),i.updatePointer(n,o,r,!0),i._doPhase({interaction:i,event:o,phase:"resume"}),Fe(i.coords.prev,i.coords.cur),this.stop()}},{key:"end",value:function(){this.interaction.move(),this.interaction.end(),this.stop()}},{key:"stop",value:function(){this.active=this.smoothEnd=!1,this.interaction.simulation=null,ie.cancel(this.timeout)}}]),t})();function Ye(t){var e=t.interactable,n=t.prepared;return e&&e.options&&n.name&&e.options[n.name].inertia}var io={id:"inertia",before:["modifiers","actions"],install:function(t){var e=t.defaults;t.usePlugin(dn),t.usePlugin(an),t.actions.phases.inertiastart=!0,t.actions.phases.resume=!0,e.perAction.inertia={enabled:!1,resistance:10,minSpeed:100,endSpeed:10,allowResume:!0,smoothEndDuration:300}},listeners:{"interactions:new":function(t){var e=t.interaction;e.inertia=new ro(e)},"interactions:before-action-end":function(t){var e=t.interaction,n=t.event;return(!e._interacting||e.simulation||!e.inertia.start(n))&&null},"interactions:down":function(t){var e=t.interaction,n=t.eventTarget,o=e.inertia;if(o.active)for(var r=n;y.element(r);){if(r===e.element){o.resume(t);break}r=ee(r)}},"interactions:stop":function(t){var e=t.interaction.inertia;e.active&&e.stop()},"interactions:before-action-resume":function(t){var e=t.interaction.modification;e.stop(t),e.start(t,t.interaction.coords.cur.page),e.applyToInteraction(t)},"interactions:before-action-inertiastart":function(t){return t.interaction.modification.setAndApply(t)},"interactions:action-resume":Se,"interactions:action-inertiastart":Se,"interactions:after-action-inertiastart":function(t){return t.interaction.modification.restoreInteractionCoords(t)},"interactions:after-action-resume":function(t){return t.interaction.modification.restoreInteractionCoords(t)}}};function un(t,e,n,o){var r=1-t;return r*r*e+2*r*t*n+t*t*o}function hn(t,e,n,o){return-n*(t/=o)*(t-2)+e}var ao=io;function fn(t,e){for(var n=0;n<e.length;n++){var o=e[n];if(t.immediatePropagationStopped)break;o(t)}}var vn=(function(){function t(e){p(this,t),this.options=void 0,this.types={},this.propagationStopped=!1,this.immediatePropagationStopped=!1,this.global=void 0,this.options=I({},e||{})}return v(t,[{key:"fire",value:function(e){var n,o=this.global;(n=this.types[e.type])&&fn(e,n),!e.propagationStopped&&o&&(n=o[e.type])&&fn(e,n)}},{key:"on",value:function(e,n){var o=de(e,n);for(e in o)this.types[e]=Yt(this.types[e]||[],o[e])}},{key:"off",value:function(e,n){var o=de(e,n);for(e in o){var r=this.types[e];if(r&&r.length)for(var i=0,a=o[e];i<a.length;i++){var s=a[i],c=r.indexOf(s);c!==-1&&r.splice(c,1)}}}},{key:"getRect",value:function(e){return null}}]),t})(),so=(function(){function t(e){p(this,t),this.currentTarget=void 0,this.originalEvent=void 0,this.type=void 0,this.originalEvent=e,Le(this,e)}return v(t,[{key:"preventOriginalDefault",value:function(){this.originalEvent.preventDefault()}},{key:"stopPropagation",value:function(){this.originalEvent.stopPropagation()}},{key:"stopImmediatePropagation",value:function(){this.originalEvent.stopImmediatePropagation()}}]),t})();function Ie(t){return y.object(t)?{capture:!!t.capture,passive:!!t.passive}:{capture:!!t,passive:!1}}function Be(t,e){return t===e||(typeof t=="boolean"?!!e.capture===t&&!e.passive:!!t.capture==!!e.capture&&!!t.passive==!!e.passive)}var co={id:"events",install:function(t){var e,n=[],o={},r=[],i={add:a,remove:s,addDelegate:function(l,u,g,x,b){var w=Ie(b);if(!o[g]){o[g]=[];for(var E=0;E<r.length;E++){var _=r[E];a(_,g,c),a(_,g,d,!0)}}var S=o[g],O=Ee(S,(function(A){return A.selector===l&&A.context===u}));O||(O={selector:l,context:u,listeners:[]},S.push(O)),O.listeners.push({func:x,options:w})},removeDelegate:function(l,u,g,x,b){var w,E=Ie(b),_=o[g],S=!1;if(_)for(w=_.length-1;w>=0;w--){var O=_[w];if(O.selector===l&&O.context===u){for(var A=O.listeners,P=A.length-1;P>=0;P--){var D=A[P];if(D.func===x&&Be(D.options,E)){A.splice(P,1),A.length||(_.splice(w,1),s(u,g,c),s(u,g,d,!0)),S=!0;break}}if(S)break}}},delegateListener:c,delegateUseCapture:d,delegatedEvents:o,documents:r,targets:n,supportsOptions:!1,supportsPassive:!1};function a(l,u,g,x){if(l.addEventListener){var b=Ie(x),w=Ee(n,(function(E){return E.eventTarget===l}));w||(w={eventTarget:l,events:{}},n.push(w)),w.events[u]||(w.events[u]=[]),Ee(w.events[u],(function(E){return E.func===g&&Be(E.options,b)}))||(l.addEventListener(u,g,i.supportsOptions?b:b.capture),w.events[u].push({func:g,options:b}))}}function s(l,u,g,x){if(l.addEventListener&&l.removeEventListener){var b=_e(n,(function(Y){return Y.eventTarget===l})),w=n[b];if(w&&w.events)if(u!=="all"){var E=!1,_=w.events[u];if(_){if(g==="all"){for(var S=_.length-1;S>=0;S--){var O=_[S];s(l,u,O.func,O.options)}return}for(var A=Ie(x),P=0;P<_.length;P++){var D=_[P];if(D.func===g&&Be(D.options,A)){l.removeEventListener(u,g,i.supportsOptions?A:A.capture),_.splice(P,1),_.length===0&&(delete w.events[u],E=!0);break}}}E&&!Object.keys(w.events).length&&n.splice(b,1)}else for(u in w.events)w.events.hasOwnProperty(u)&&s(l,u,"all")}}function c(l,u){for(var g=Ie(u),x=new so(l),b=o[l.type],w=Xt(l)[0],E=w;y.element(E);){for(var _=0;_<b.length;_++){var S=b[_],O=S.selector,A=S.context;if(re(E,O)&&pe(A,w)&&pe(A,E)){var P=S.listeners;x.currentTarget=E;for(var D=0;D<P.length;D++){var Y=P[D];Be(Y.options,g)&&Y.func(x)}}}E=ee(E)}}function d(l){return c(l,!0)}return(e=t.document)==null||e.createElement("div").addEventListener("test",null,{get capture(){return i.supportsOptions=!0},get passive(){return i.supportsPassive=!0}}),t.events=i,i}},yt={methodOrder:["simulationResume","mouseOrPen","hasPointer","idle"],search:function(t){for(var e=0,n=yt.methodOrder;e<n.length;e++){var o=n[e],r=yt[o](t);if(r)return r}return null},simulationResume:function(t){var e=t.pointerType,n=t.eventType,o=t.eventTarget,r=t.scope;if(!/down|start/i.test(n))return null;for(var i=0,a=r.interactions.list;i<a.length;i++){var s=a[i],c=o;if(s.simulation&&s.simulation.allowResume&&s.pointerType===e)for(;c;){if(c===s.element)return s;c=ee(c)}}return null},mouseOrPen:function(t){var e,n=t.pointerId,o=t.pointerType,r=t.eventType,i=t.scope;if(o!=="mouse"&&o!=="pen")return null;for(var a=0,s=i.interactions.list;a<s.length;a++){var c=s[a];if(c.pointerType===o){if(c.simulation&&!gn(c,n))continue;if(c.interacting())return c;e||(e=c)}}if(e)return e;for(var d=0,l=i.interactions.list;d<l.length;d++){var u=l[d];if(!(u.pointerType!==o||/down/i.test(r)&&u.simulation))return u}return null},hasPointer:function(t){for(var e=t.pointerId,n=0,o=t.scope.interactions.list;n<o.length;n++){var r=o[n];if(gn(r,e))return r}return null},idle:function(t){for(var e=t.pointerType,n=0,o=t.scope.interactions.list;n<o.length;n++){var r=o[n];if(r.pointers.length===1){var i=r.interactable;if(i&&(!i.options.gesture||!i.options.gesture.enabled))continue}else if(r.pointers.length>=2)continue;if(!r.interacting()&&e===r.pointerType)return r}return null}};function gn(t,e){return t.pointers.some((function(n){return n.id===e}))}var lo=yt,bt=["pointerDown","pointerMove","pointerUp","updatePointer","removePointer","windowBlur"];function mn(t,e){return function(n){var o=e.interactions.list,r=qt(n),i=Xt(n),a=i[0],s=i[1],c=[];if(/^touch/.test(n.type)){e.prevTouchTime=e.now();for(var d=0,l=n.changedTouches;d<l.length;d++){var u=l[d],g={pointer:u,pointerId:ke(u),pointerType:r,eventType:n.type,eventTarget:a,curEventTarget:s,scope:e},x=yn(g);c.push([g.pointer,g.eventTarget,g.curEventTarget,x])}}else{var b=!1;if(!U.supportsPointerEvent&&/mouse/.test(n.type)){for(var w=0;w<o.length&&!b;w++)b=o[w].pointerType!=="mouse"&&o[w].pointerIsDown;b=b||e.now()-e.prevTouchTime<500||n.timeStamp===0}if(!b){var E={pointer:n,pointerId:ke(n),pointerType:r,eventType:n.type,curEventTarget:s,eventTarget:a,scope:e},_=yn(E);c.push([E.pointer,E.eventTarget,E.curEventTarget,_])}}for(var S=0;S<c.length;S++){var O=c[S],A=O[0],P=O[1],D=O[2];O[3][t](A,n,P,D)}}}function yn(t){var e=t.pointerType,n=t.scope,o={interaction:lo.search(t),searchDetails:t};return n.fire("interactions:find",o),o.interaction||n.interactions.new({pointerType:e})}function xt(t,e){var n=t.doc,o=t.scope,r=t.options,i=o.interactions.docEvents,a=o.events,s=a[e];for(var c in o.browser.isIOS&&!r.events&&(r.events={passive:!1}),a.delegatedEvents)s(n,c,a.delegateListener),s(n,c,a.delegateUseCapture,!0);for(var d=r&&r.events,l=0;l<i.length;l++){var u=i[l];s(n,u.type,u.listener,d)}}var po={id:"core/interactions",install:function(t){for(var e={},n=0;n<bt.length;n++){var o=bt[n];e[o]=mn(o,t)}var r,i=U.pEventTypes;function a(){for(var s=0,c=t.interactions.list;s<c.length;s++){var d=c[s];if(d.pointerIsDown&&d.pointerType==="touch"&&!d._interacting)for(var l=function(){var x=g[u];t.documents.some((function(b){return pe(b.doc,x.downTarget)}))||d.removePointer(x.pointer,x.event)},u=0,g=d.pointers;u<g.length;u++)l()}}(r=q.PointerEvent?[{type:i.down,listener:a},{type:i.down,listener:e.pointerDown},{type:i.move,listener:e.pointerMove},{type:i.up,listener:e.pointerUp},{type:i.cancel,listener:e.pointerUp}]:[{type:"mousedown",listener:e.pointerDown},{type:"mousemove",listener:e.pointerMove},{type:"mouseup",listener:e.pointerUp},{type:"touchstart",listener:a},{type:"touchstart",listener:e.pointerDown},{type:"touchmove",listener:e.pointerMove},{type:"touchend",listener:e.pointerUp},{type:"touchcancel",listener:e.pointerUp}]).push({type:"blur",listener:function(s){for(var c=0,d=t.interactions.list;c<d.length;c++)d[c].documentBlur(s)}}),t.prevTouchTime=0,t.Interaction=(function(s){m(d,s);var c=$(d);function d(){return p(this,d),c.apply(this,arguments)}return v(d,[{key:"pointerMoveTolerance",get:function(){return t.interactions.pointerMoveTolerance},set:function(l){t.interactions.pointerMoveTolerance=l}},{key:"_now",value:function(){return t.now()}}]),d})(to),t.interactions={list:[],new:function(s){s.scopeFire=function(d,l){return t.fire(d,l)};var c=new t.Interaction(s);return t.interactions.list.push(c),c},listeners:e,docEvents:r,pointerMoveTolerance:1},t.usePlugin(rn)},listeners:{"scope:add-document":function(t){return xt(t,"add")},"scope:remove-document":function(t){return xt(t,"remove")},"interactable:unset":function(t,e){for(var n=t.interactable,o=e.interactions.list.length-1;o>=0;o--){var r=e.interactions.list[o];r.interactable===n&&(r.stop(),e.fire("interactions:destroy",{interaction:r}),r.destroy(),e.interactions.list.length>2&&e.interactions.list.splice(o,1))}}},onDocSignal:xt,doOnInteractions:mn,methodNames:bt},uo=po,se=(function(t){return t[t.On=0]="On",t[t.Off=1]="Off",t})(se||{}),ho=(function(){function t(e,n,o,r){p(this,t),this.target=void 0,this.options=void 0,this._actions=void 0,this.events=new vn,this._context=void 0,this._win=void 0,this._doc=void 0,this._scopeEvents=void 0,this._actions=n.actions,this.target=e,this._context=n.context||o,this._win=J(Ct(e)?this._context:e),this._doc=this._win.document,this._scopeEvents=r,this.set(n)}return v(t,[{key:"_defaults",get:function(){return{base:{},perAction:{},actions:{}}}},{key:"setOnEvents",value:function(e,n){return y.func(n.onstart)&&this.on("".concat(e,"start"),n.onstart),y.func(n.onmove)&&this.on("".concat(e,"move"),n.onmove),y.func(n.onend)&&this.on("".concat(e,"end"),n.onend),y.func(n.oninertiastart)&&this.on("".concat(e,"inertiastart"),n.oninertiastart),this}},{key:"updatePerActionListeners",value:function(e,n,o){var r,i=this,a=(r=this._actions.map[e])==null?void 0:r.filterEventType,s=function(c){return(a==null||a(c))&&qe(c,i._actions)};(y.array(n)||y.object(n))&&this._onOff(se.Off,e,n,void 0,s),(y.array(o)||y.object(o))&&this._onOff(se.On,e,o,void 0,s)}},{key:"setPerAction",value:function(e,n){var o=this._defaults;for(var r in n){var i=r,a=this.options[e],s=n[i];i==="listeners"&&this.updatePerActionListeners(e,a.listeners,s),y.array(s)?a[i]=Bt(s):y.plainObject(s)?(a[i]=I(a[i]||{},me(s)),y.object(o.perAction[i])&&"enabled"in o.perAction[i]&&(a[i].enabled=s.enabled!==!1)):y.bool(s)&&y.object(o.perAction[i])?a[i].enabled=s:a[i]=s}}},{key:"getRect",value:function(e){return e=e||(y.element(this.target)?this.target:null),y.string(this.target)&&(e=e||this._context.querySelector(this.target)),et(e)}},{key:"rectChecker",value:function(e){var n=this;return y.func(e)?(this.getRect=function(o){var r=I({},e.apply(n,o));return"width"in r||(r.width=r.right-r.left,r.height=r.bottom-r.top),r},this):e===null?(delete this.getRect,this):this.getRect}},{key:"_backCompatOption",value:function(e,n){if(Ct(n)||y.object(n)){for(var o in this.options[e]=n,this._actions.map)this.options[o][e]=n;return this}return this.options[e]}},{key:"origin",value:function(e){return this._backCompatOption("origin",e)}},{key:"deltaSource",value:function(e){return e==="page"||e==="client"?(this.options.deltaSource=e,this):this.options.deltaSource}},{key:"getAllElements",value:function(){var e=this.target;return y.string(e)?Array.from(this._context.querySelectorAll(e)):y.func(e)&&e.getAllElements?e.getAllElements():y.element(e)?[e]:[]}},{key:"context",value:function(){return this._context}},{key:"inContext",value:function(e){return this._context===e.ownerDocument||pe(this._context,e)}},{key:"testIgnoreAllow",value:function(e,n,o){return!this.testIgnore(e.ignoreFrom,n,o)&&this.testAllow(e.allowFrom,n,o)}},{key:"testAllow",value:function(e,n,o){return!e||!!y.element(o)&&(y.string(e)?Qe(o,e,n):!!y.element(e)&&pe(e,o))}},{key:"testIgnore",value:function(e,n,o){return!(!e||!y.element(o))&&(y.string(e)?Qe(o,e,n):!!y.element(e)&&pe(e,o))}},{key:"fire",value:function(e){return this.events.fire(e),this}},{key:"_onOff",value:function(e,n,o,r,i){y.object(n)&&!y.array(n)&&(r=o,o=null);var a=de(n,o,i);for(var s in a){s==="wheel"&&(s=U.wheelEvent);for(var c=0,d=a[s];c<d.length;c++){var l=d[c];qe(s,this._actions)?this.events[e===se.On?"on":"off"](s,l):y.string(this.target)?this._scopeEvents[e===se.On?"addDelegate":"removeDelegate"](this.target,this._context,s,l,r):this._scopeEvents[e===se.On?"add":"remove"](this.target,s,l,r)}}return this}},{key:"on",value:function(e,n,o){return this._onOff(se.On,e,n,o)}},{key:"off",value:function(e,n,o){return this._onOff(se.Off,e,n,o)}},{key:"set",value:function(e){var n=this._defaults;for(var o in y.object(e)||(e={}),this.options=me(n.base),this._actions.methodDict){var r=o,i=this._actions.methodDict[r];this.options[r]={},this.setPerAction(r,I(I({},n.perAction),n.actions[r])),this[i](e[r])}for(var a in e)a!=="getRect"?y.func(this[a])&&this[a](e[a]):this.rectChecker(e.getRect);return this}},{key:"unset",value:function(){if(y.string(this.target))for(var e in this._scopeEvents.delegatedEvents)for(var n=this._scopeEvents.delegatedEvents[e],o=n.length-1;o>=0;o--){var r=n[o],i=r.selector,a=r.context,s=r.listeners;i===this.target&&a===this._context&&n.splice(o,1);for(var c=s.length-1;c>=0;c--)this._scopeEvents.removeDelegate(this.target,this._context,e,s[c][0],s[c][1])}else this._scopeEvents.remove(this.target,"all")}}]),t})(),fo=(function(){function t(e){var n=this;p(this,t),this.list=[],this.selectorMap={},this.scope=void 0,this.scope=e,e.addListeners({"interactable:unset":function(o){var r=o.interactable,i=r.target,a=y.string(i)?n.selectorMap[i]:i[n.scope.id],s=_e(a,(function(c){return c===r}));a.splice(s,1)}})}return v(t,[{key:"new",value:function(e,n){n=I(n||{},{actions:this.scope.actions});var o=new this.scope.Interactable(e,n,this.scope.document,this.scope.events);return this.scope.addDocument(o._doc),this.list.push(o),y.string(e)?(this.selectorMap[e]||(this.selectorMap[e]=[]),this.selectorMap[e].push(o)):(o.target[this.scope.id]||Object.defineProperty(e,this.scope.id,{value:[],configurable:!0}),e[this.scope.id].push(o)),this.scope.fire("interactable:new",{target:e,options:n,interactable:o,win:this.scope._win}),o}},{key:"getExisting",value:function(e,n){var o=n&&n.context||this.scope.document,r=y.string(e),i=r?this.selectorMap[e]:e[this.scope.id];if(i)return Ee(i,(function(a){return a._context===o&&(r||a.inContext(e))}))}},{key:"forEachMatch",value:function(e,n){for(var o=0,r=this.list;o<r.length;o++){var i=r[o],a=void 0;if((y.string(i.target)?y.element(e)&&re(e,i.target):e===i.target)&&i.inContext(e)&&(a=n(i)),a!==void 0)return a}}}]),t})(),vo=(function(){function t(){var e=this;p(this,t),this.id="__interact_scope_".concat(Math.floor(100*Math.random())),this.isInitialized=!1,this.listenerMaps=[],this.browser=U,this.defaults=me(sn),this.Eventable=vn,this.actions={map:{},phases:{start:!0,move:!0,end:!0},methodDict:{},phaselessTypes:{}},this.interactStatic=(function(o){var r=function i(a,s){var c=o.interactables.getExisting(a,s);return c||((c=o.interactables.new(a,s)).events.global=i.globalEvents),c};return r.getPointerAverage=Rt,r.getTouchBBox=ot,r.getTouchDistance=rt,r.getTouchAngle=it,r.getElementRect=et,r.getElementClientRect=Je,r.matchesSelector=re,r.closest=Mt,r.globalEvents={},r.version="1.10.27",r.scope=o,r.use=function(i,a){return this.scope.usePlugin(i,a),this},r.isSet=function(i,a){return!!this.scope.interactables.get(i,a&&a.context)},r.on=Te((function(i,a,s){if(y.string(i)&&i.search(" ")!==-1&&(i=i.trim().split(/ +/)),y.array(i)){for(var c=0,d=i;c<d.length;c++){var l=d[c];this.on(l,a,s)}return this}if(y.object(i)){for(var u in i)this.on(u,i[u],a);return this}return qe(i,this.scope.actions)?this.globalEvents[i]?this.globalEvents[i].push(a):this.globalEvents[i]=[a]:this.scope.events.add(this.scope.document,i,a,{options:s}),this}),"The interact.on() method is being deprecated"),r.off=Te((function(i,a,s){if(y.string(i)&&i.search(" ")!==-1&&(i=i.trim().split(/ +/)),y.array(i)){for(var c=0,d=i;c<d.length;c++){var l=d[c];this.off(l,a,s)}return this}if(y.object(i)){for(var u in i)this.off(u,i[u],a);return this}var g;return qe(i,this.scope.actions)?i in this.globalEvents&&(g=this.globalEvents[i].indexOf(a))!==-1&&this.globalEvents[i].splice(g,1):this.scope.events.remove(this.scope.document,i,a,s),this}),"The interact.off() method is being deprecated"),r.debug=function(){return this.scope},r.supportsTouch=function(){return U.supportsTouch},r.supportsPointerEvent=function(){return U.supportsPointerEvent},r.stop=function(){for(var i=0,a=this.scope.interactions.list;i<a.length;i++)a[i].stop();return this},r.pointerMoveTolerance=function(i){return y.number(i)?(this.scope.interactions.pointerMoveTolerance=i,this):this.scope.interactions.pointerMoveTolerance},r.addDocument=function(i,a){this.scope.addDocument(i,a)},r.removeDocument=function(i){this.scope.removeDocument(i)},r})(this),this.InteractEvent=gt,this.Interactable=void 0,this.interactables=new fo(this),this._win=void 0,this.document=void 0,this.window=void 0,this.documents=[],this._plugins={list:[],map:{}},this.onWindowUnload=function(o){return e.removeDocument(o.target)};var n=this;this.Interactable=(function(o){m(i,o);var r=$(i);function i(){return p(this,i),r.apply(this,arguments)}return v(i,[{key:"_defaults",get:function(){return n.defaults}},{key:"set",value:function(a){return L(T(i.prototype),"set",this).call(this,a),n.fire("interactable:set",{options:a,interactable:this}),this}},{key:"unset",value:function(){L(T(i.prototype),"unset",this).call(this);var a=n.interactables.list.indexOf(this);a<0||(n.interactables.list.splice(a,1),n.fire("interactable:unset",{interactable:this}))}}]),i})(ho)}return v(t,[{key:"addListeners",value:function(e,n){this.listenerMaps.push({id:n,map:e})}},{key:"fire",value:function(e,n){for(var o=0,r=this.listenerMaps;o<r.length;o++){var i=r[o].map[e];if(i&&i(n,this,e)===!1)return!1}}},{key:"init",value:function(e){return this.isInitialized?this:(function(n,o){return n.isInitialized=!0,y.window(o)&&ye(o),q.init(o),U.init(o),ie.init(o),n.window=o,n.document=o.document,n.usePlugin(uo),n.usePlugin(co),n})(this,e)}},{key:"pluginIsInstalled",value:function(e){var n=e.id;return n?!!this._plugins.map[n]:this._plugins.list.indexOf(e)!==-1}},{key:"usePlugin",value:function(e,n){if(!this.isInitialized)return this;if(this.pluginIsInstalled(e))return this;if(e.id&&(this._plugins.map[e.id]=e),this._plugins.list.push(e),e.install&&e.install(this,n),e.listeners&&e.before){for(var o=0,r=this.listenerMaps.length,i=e.before.reduce((function(s,c){return s[c]=!0,s[bn(c)]=!0,s}),{});o<r;o++){var a=this.listenerMaps[o].id;if(a&&(i[a]||i[bn(a)]))break}this.listenerMaps.splice(o,0,{id:e.id,map:e.listeners})}else e.listeners&&this.listenerMaps.push({id:e.id,map:e.listeners});return this}},{key:"addDocument",value:function(e,n){if(this.getDocIndex(e)!==-1)return!1;var o=J(e);n=n?I({},n):{},this.documents.push({doc:e,options:n}),this.events.documents.push(e),e!==this.document&&this.events.add(o,"unload",this.onWindowUnload),this.fire("scope:add-document",{doc:e,window:o,scope:this,options:n})}},{key:"removeDocument",value:function(e){var n=this.getDocIndex(e),o=J(e),r=this.documents[n].options;this.events.remove(o,"unload",this.onWindowUnload),this.documents.splice(n,1),this.events.documents.splice(n,1),this.fire("scope:remove-document",{doc:e,window:o,scope:this,options:r})}},{key:"getDocIndex",value:function(e){for(var n=0;n<this.documents.length;n++)if(this.documents[n].doc===e)return n;return-1}},{key:"getDocOptions",value:function(e){var n=this.getDocIndex(e);return n===-1?null:this.documents[n].options}},{key:"now",value:function(){return(this.window.Date||Date).now()}}]),t})();function bn(t){return t&&t.replace(/\/.*$/,"")}var xn=new vo,X=xn.interactStatic,go=typeof globalThis<"u"?globalThis:window;xn.init(go);var mo=Object.freeze({__proto__:null,edgeTarget:function(){},elements:function(){},grid:function(t){var e=[["x","y"],["left","top"],["right","bottom"],["width","height"]].filter((function(o){var r=o[0],i=o[1];return r in t||i in t})),n=function(o,r){for(var i=t.range,a=t.limits,s=a===void 0?{left:-1/0,right:1/0,top:-1/0,bottom:1/0}:a,c=t.offset,d=c===void 0?{x:0,y:0}:c,l={range:i,grid:t,x:null,y:null},u=0;u<e.length;u++){var g=e[u],x=g[0],b=g[1],w=Math.round((o-d.x)/t[x]),E=Math.round((r-d.y)/t[b]);l[x]=Math.max(s.left,Math.min(s.right,w*t[x]+d.x)),l[b]=Math.max(s.top,Math.min(s.bottom,E*t[b]+d.y))}return l};return n.grid=t,n.coordFields=e,n}}),yo={id:"snappers",install:function(t){var e=t.interactStatic;e.snappers=I(e.snappers||{},mo),e.createSnapGrid=e.snappers.grid}},bo=yo,xo={start:function(t){var e=t.state,n=t.rect,o=t.edges,r=t.pageCoords,i=e.options,a=i.ratio,s=i.enabled,c=e.options,d=c.equalDelta,l=c.modifiers;a==="preserve"&&(a=n.width/n.height),e.startCoords=I({},r),e.startRect=I({},n),e.ratio=a,e.equalDelta=d;var u=e.linkedEdges={top:o.top||o.left&&!o.bottom,left:o.left||o.top&&!o.right,bottom:o.bottom||o.right&&!o.top,right:o.right||o.bottom&&!o.left};if(e.xIsPrimaryAxis=!(!o.left&&!o.right),e.equalDelta){var g=(u.left?1:-1)*(u.top?1:-1);e.edgeSign={x:g,y:g}}else e.edgeSign={x:u.left?-1:1,y:u.top?-1:1};if(s!==!1&&I(o,u),l!=null&&l.length){var x=new vt(t.interaction);x.copyFrom(t.interaction.modification),x.prepareStates(l),e.subModification=x,x.startAll(F({},t))}},set:function(t){var e=t.state,n=t.rect,o=t.coords,r=e.linkedEdges,i=I({},o),a=e.equalDelta?wo:ko;if(I(t.edges,r),a(e,e.xIsPrimaryAxis,o,n),!e.subModification)return null;var s=I({},n);$e(r,s,{x:o.x-i.x,y:o.y-i.y});var c=e.subModification.setAll(F(F({},t),{},{rect:s,edges:r,pageCoords:o,prevCoords:o,prevRect:s})),d=c.delta;return c.changed&&(a(e,Math.abs(d.x)>Math.abs(d.y),c.coords,c.rect),I(o,c.coords)),c.eventProps},defaults:{ratio:"preserve",equalDelta:!1,modifiers:[],enabled:!1}};function wo(t,e,n){var o=t.startCoords,r=t.edgeSign;e?n.y=o.y+(n.x-o.x)*r.y:n.x=o.x+(n.y-o.y)*r.x}function ko(t,e,n,o){var r=t.startRect,i=t.startCoords,a=t.ratio,s=t.edgeSign;if(e){var c=o.width/a;n.y=i.y+(c-r.height)*s.y}else{var d=o.height*a;n.x=i.x+(d-r.width)*s.x}}var _o=ae(xo,"aspectRatio"),wn=function(){};wn._defaults={};var He=wn;function he(t,e,n){return y.func(t)?be(t,e.interactable,e.element,[n.x,n.y,e]):be(t,e.interactable,e.element)}var We={start:function(t){var e=t.rect,n=t.startOffset,o=t.state,r=t.interaction,i=t.pageCoords,a=o.options,s=a.elementRect,c=I({left:0,top:0,right:0,bottom:0},a.offset||{});if(e&&s){var d=he(a.restriction,r,i);if(d){var l=d.right-d.left-e.width,u=d.bottom-d.top-e.height;l<0&&(c.left+=l,c.right+=l),u<0&&(c.top+=u,c.bottom+=u)}c.left+=n.left-e.width*s.left,c.top+=n.top-e.height*s.top,c.right+=n.right-e.width*(1-s.right),c.bottom+=n.bottom-e.height*(1-s.bottom)}o.offset=c},set:function(t){var e=t.coords,n=t.interaction,o=t.state,r=o.options,i=o.offset,a=he(r.restriction,n,e);if(a){var s=(function(c){return!c||"left"in c&&"top"in c||((c=I({},c)).left=c.x||0,c.top=c.y||0,c.right=c.right||c.left+c.width,c.bottom=c.bottom||c.top+c.height),c})(a);e.x=Math.max(Math.min(s.right-i.right,e.x),s.left+i.left),e.y=Math.max(Math.min(s.bottom-i.bottom,e.y),s.top+i.top)}},defaults:{restriction:null,elementRect:null,offset:null,endOnly:!1,enabled:!1}},Eo=ae(We,"restrict"),kn={top:1/0,left:1/0,bottom:-1/0,right:-1/0},_n={top:-1/0,left:-1/0,bottom:1/0,right:1/0};function En(t,e){for(var n=0,o=["top","left","bottom","right"];n<o.length;n++){var r=o[n];r in t||(t[r]=e[r])}return t}var Pe={noInner:kn,noOuter:_n,start:function(t){var e,n=t.interaction,o=t.startOffset,r=t.state,i=r.options;i&&(e=Ce(he(i.offset,n,n.coords.start.page))),e=e||{x:0,y:0},r.offset={top:e.y+o.top,left:e.x+o.left,bottom:e.y-o.bottom,right:e.x-o.right}},set:function(t){var e=t.coords,n=t.edges,o=t.interaction,r=t.state,i=r.offset,a=r.options;if(n){var s=I({},e),c=he(a.inner,o,s)||{},d=he(a.outer,o,s)||{};En(c,kn),En(d,_n),n.top?e.y=Math.min(Math.max(d.top+i.top,s.y),c.top+i.top):n.bottom&&(e.y=Math.max(Math.min(d.bottom+i.bottom,s.y),c.bottom+i.bottom)),n.left?e.x=Math.min(Math.max(d.left+i.left,s.x),c.left+i.left):n.right&&(e.x=Math.max(Math.min(d.right+i.right,s.x),c.right+i.right))}},defaults:{inner:null,outer:null,offset:null,endOnly:!1,enabled:!1}},To=ae(Pe,"restrictEdges"),So=I({get elementRect(){return{top:0,left:0,bottom:1,right:1}},set elementRect(t){}},We.defaults),Io=ae({start:We.start,set:We.set,defaults:So},"restrictRect"),Po={width:-1/0,height:-1/0},zo={width:1/0,height:1/0},Mo=ae({start:function(t){return Pe.start(t)},set:function(t){var e=t.interaction,n=t.state,o=t.rect,r=t.edges,i=n.options;if(r){var a=tt(he(i.min,e,t.coords))||Po,s=tt(he(i.max,e,t.coords))||zo;n.options={endOnly:i.endOnly,inner:I({},Pe.noInner),outer:I({},Pe.noOuter)},r.top?(n.options.inner.top=o.bottom-a.height,n.options.outer.top=o.bottom-s.height):r.bottom&&(n.options.inner.bottom=o.top+a.height,n.options.outer.bottom=o.top+s.height),r.left?(n.options.inner.left=o.right-a.width,n.options.outer.left=o.right-s.width):r.right&&(n.options.inner.right=o.left+a.width,n.options.outer.right=o.left+s.width),Pe.set(t),n.options=i}},defaults:{min:null,max:null,endOnly:!1,enabled:!1}},"restrictSize"),wt={start:function(t){var e,n=t.interaction,o=t.interactable,r=t.element,i=t.rect,a=t.state,s=t.startOffset,c=a.options,d=c.offsetWithOrigin?(function(g){var x=g.interaction.element,b=Ce(be(g.state.options.origin,null,null,[x])),w=b||xe(g.interactable,x,g.interaction.prepared.name);return w})(t):{x:0,y:0};if(c.offset==="startCoords")e={x:n.coords.start.page.x,y:n.coords.start.page.y};else{var l=be(c.offset,o,r,[n]);(e=Ce(l)||{x:0,y:0}).x+=d.x,e.y+=d.y}var u=c.relativePoints;a.offsets=i&&u&&u.length?u.map((function(g,x){return{index:x,relativePoint:g,x:s.left-i.width*g.x+e.x,y:s.top-i.height*g.y+e.y}})):[{index:0,relativePoint:null,x:e.x,y:e.y}]},set:function(t){var e=t.interaction,n=t.coords,o=t.state,r=o.options,i=o.offsets,a=xe(e.interactable,e.element,e.prepared.name),s=I({},n),c=[];r.offsetWithOrigin||(s.x-=a.x,s.y-=a.y);for(var d=0,l=i;d<l.length;d++)for(var u=l[d],g=s.x-u.x,x=s.y-u.y,b=0,w=r.targets.length;b<w;b++){var E=r.targets[b],_=void 0;(_=y.func(E)?E(g,x,e._proxy,u,b):E)&&c.push({x:(y.number(_.x)?_.x:g)+u.x,y:(y.number(_.y)?_.y:x)+u.y,range:y.number(_.range)?_.range:r.range,source:E,index:b,offset:u})}for(var S={target:null,inRange:!1,distance:0,range:0,delta:{x:0,y:0}},O=0;O<c.length;O++){var A=c[O],P=A.range,D=A.x-s.x,Y=A.y-s.y,j=we(D,Y),H=j<=P;P===1/0&&S.inRange&&S.range!==1/0&&(H=!1),S.target&&!(H?S.inRange&&P!==1/0?j/P<S.distance/S.range:P===1/0&&S.range!==1/0||j<S.distance:!S.inRange&&j<S.distance)||(S.target=A,S.distance=j,S.range=P,S.inRange=H,S.delta.x=D,S.delta.y=Y)}return S.inRange&&(n.x=S.target.x,n.y=S.target.y),o.closest=S,S},defaults:{range:1/0,targets:null,offset:null,offsetWithOrigin:!0,origin:null,relativePoints:null,endOnly:!1,enabled:!1}},Oo=ae(wt,"snap"),Ge={start:function(t){var e=t.state,n=t.edges,o=e.options;if(!n)return null;t.state={options:{targets:null,relativePoints:[{x:n.left?0:1,y:n.top?0:1}],offset:o.offset||"self",origin:{x:0,y:0},range:o.range}},e.targetFields=e.targetFields||[["width","height"],["x","y"]],wt.start(t),e.offsets=t.state.offsets,t.state=e},set:function(t){var e=t.interaction,n=t.state,o=t.coords,r=n.options,i=n.offsets,a={x:o.x-i[0].x,y:o.y-i[0].y};n.options=I({},r),n.options.targets=[];for(var s=0,c=r.targets||[];s<c.length;s++){var d=c[s],l=void 0;if(l=y.func(d)?d(a.x,a.y,e):d){for(var u=0,g=n.targetFields;u<g.length;u++){var x=g[u],b=x[0],w=x[1];if(b in l||w in l){l.x=l[b],l.y=l[w];break}}n.options.targets.push(l)}}var E=wt.set(t);return n.options=r,E},defaults:{range:1/0,targets:null,offset:null,endOnly:!1,enabled:!1}},Do=ae(Ge,"snapSize"),kt={aspectRatio:_o,restrictEdges:To,restrict:Eo,restrictRect:Io,restrictSize:Mo,snapEdges:ae({start:function(t){var e=t.edges;return e?(t.state.targetFields=t.state.targetFields||[[e.left?"left":"right",e.top?"top":"bottom"]],Ge.start(t)):null},set:Ge.set,defaults:I(me(Ge.defaults),{targets:void 0,range:void 0,offset:{x:0,y:0}})},"snapEdges"),snap:Oo,snapSize:Do,spring:He,avoid:He,transform:He,rubberband:He},Ao={id:"modifiers",install:function(t){var e=t.interactStatic;for(var n in t.usePlugin(an),t.usePlugin(bo),e.modifiers=kt,kt){var o=kt[n],r=o._defaults,i=o._methods;r._methods=i,t.defaults.perAction[n]=r}}},Co=Ao,Tn=(function(t){m(n,t);var e=$(n);function n(o,r,i,a,s,c){var d;if(p(this,n),Le(M(d=e.call(this,s)),i),i!==r&&Le(M(d),r),d.timeStamp=c,d.originalEvent=i,d.type=o,d.pointerId=ke(r),d.pointerType=qt(r),d.target=a,d.currentTarget=null,o==="tap"){var l=s.getPointerIndex(r);d.dt=d.timeStamp-s.pointers[l].downTime;var u=d.timeStamp-s.tapTime;d.double=!!s.prevTap&&s.prevTap.type!=="doubletap"&&s.prevTap.target===d.target&&u<500}else o==="doubletap"&&(d.dt=r.timeStamp-s.tapTime,d.double=!0);return d}return v(n,[{key:"_subtractOrigin",value:function(o){var r=o.x,i=o.y;return this.pageX-=r,this.pageY-=i,this.clientX-=r,this.clientY-=i,this}},{key:"_addOrigin",value:function(o){var r=o.x,i=o.y;return this.pageX+=r,this.pageY+=i,this.clientX+=r,this.clientY+=i,this}},{key:"preventDefault",value:function(){this.originalEvent.preventDefault()}}]),n})(Ne),ze={id:"pointer-events/base",before:["inertia","modifiers","auto-start","actions"],install:function(t){t.pointerEvents=ze,t.defaults.actions.pointerEvents=ze.defaults,I(t.actions.phaselessTypes,ze.types)},listeners:{"interactions:new":function(t){var e=t.interaction;e.prevTap=null,e.tapTime=0},"interactions:update-pointer":function(t){var e=t.down,n=t.pointerInfo;!e&&n.hold||(n.hold={duration:1/0,timeout:null})},"interactions:move":function(t,e){var n=t.interaction,o=t.pointer,r=t.event,i=t.eventTarget;t.duplicate||n.pointerIsDown&&!n.pointerWasMoved||(n.pointerIsDown&&_t(t),ce({interaction:n,pointer:o,event:r,eventTarget:i,type:"move"},e))},"interactions:down":function(t,e){(function(n,o){for(var r=n.interaction,i=n.pointer,a=n.event,s=n.eventTarget,c=n.pointerIndex,d=r.pointers[c].hold,l=At(s),u={interaction:r,pointer:i,event:a,eventTarget:s,type:"hold",targets:[],path:l,node:null},g=0;g<l.length;g++){var x=l[g];u.node=x,o.fire("pointerEvents:collect-targets",u)}if(u.targets.length){for(var b=1/0,w=0,E=u.targets;w<E.length;w++){var _=E[w].eventable.options.holdDuration;_<b&&(b=_)}d.duration=b,d.timeout=setTimeout((function(){ce({interaction:r,eventTarget:s,pointer:i,event:a,type:"hold"},o)}),b)}})(t,e),ce(t,e)},"interactions:up":function(t,e){_t(t),ce(t,e),(function(n,o){var r=n.interaction,i=n.pointer,a=n.event,s=n.eventTarget;r.pointerWasMoved||ce({interaction:r,eventTarget:s,pointer:i,event:a,type:"tap"},o)})(t,e)},"interactions:cancel":function(t,e){_t(t),ce(t,e)}},PointerEvent:Tn,fire:ce,collectEventTargets:Sn,defaults:{holdDuration:600,ignoreFrom:null,allowFrom:null,origin:{x:0,y:0}},types:{down:!0,move:!0,up:!0,cancel:!0,tap:!0,doubletap:!0,hold:!0}};function ce(t,e){var n=t.interaction,o=t.pointer,r=t.event,i=t.eventTarget,a=t.type,s=t.targets,c=s===void 0?Sn(t,e):s,d=new Tn(a,o,r,i,n,e.now());e.fire("pointerEvents:new",{pointerEvent:d});for(var l={interaction:n,pointer:o,event:r,eventTarget:i,targets:c,type:a,pointerEvent:d},u=0;u<c.length;u++){var g=c[u];for(var x in g.props||{})d[x]=g.props[x];var b=xe(g.eventable,g.node);if(d._subtractOrigin(b),d.eventable=g.eventable,d.currentTarget=g.node,g.eventable.fire(d),d._addOrigin(b),d.immediatePropagationStopped||d.propagationStopped&&u+1<c.length&&c[u+1].node!==d.currentTarget)break}if(e.fire("pointerEvents:fired",l),a==="tap"){var w=d.double?ce({interaction:n,pointer:o,event:r,eventTarget:i,type:"doubletap"},e):d;n.prevTap=w,n.tapTime=w.timeStamp}return d}function Sn(t,e){var n=t.interaction,o=t.pointer,r=t.event,i=t.eventTarget,a=t.type,s=n.getPointerIndex(o),c=n.pointers[s];if(a==="tap"&&(n.pointerWasMoved||!c||c.downTarget!==i))return[];for(var d=At(i),l={interaction:n,pointer:o,event:r,eventTarget:i,type:a,path:d,targets:[],node:null},u=0;u<d.length;u++){var g=d[u];l.node=g,e.fire("pointerEvents:collect-targets",l)}return a==="hold"&&(l.targets=l.targets.filter((function(x){var b,w;return x.eventable.options.holdDuration===((b=n.pointers[s])==null||(w=b.hold)==null?void 0:w.duration)}))),l.targets}function _t(t){var e=t.interaction,n=t.pointerIndex,o=e.pointers[n].hold;o&&o.timeout&&(clearTimeout(o.timeout),o.timeout=null)}var $o=Object.freeze({__proto__:null,default:ze});function Lo(t){var e=t.interaction;e.holdIntervalHandle&&(clearInterval(e.holdIntervalHandle),e.holdIntervalHandle=null)}var Fo={id:"pointer-events/holdRepeat",install:function(t){t.usePlugin(ze);var e=t.pointerEvents;e.defaults.holdRepeatInterval=0,e.types.holdrepeat=t.actions.phaselessTypes.holdrepeat=!0},listeners:["move","up","cancel","endall"].reduce((function(t,e){return t["pointerEvents:".concat(e)]=Lo,t}),{"pointerEvents:new":function(t){var e=t.pointerEvent;e.type==="hold"&&(e.count=(e.count||0)+1)},"pointerEvents:fired":function(t,e){var n=t.interaction,o=t.pointerEvent,r=t.eventTarget,i=t.targets;if(o.type==="hold"&&i.length){var a=i[0].eventable.options.holdRepeatInterval;a<=0||(n.holdIntervalHandle=setTimeout((function(){e.pointerEvents.fire({interaction:n,eventTarget:r,type:"hold",pointer:o,event:o},e)}),a))}}})},jo=Fo,No={id:"pointer-events/interactableTargets",install:function(t){var e=t.Interactable;e.prototype.pointerEvents=function(o){return I(this.events.options,o),this};var n=e.prototype._backCompatOption;e.prototype._backCompatOption=function(o,r){var i=n.call(this,o,r);return i===this&&(this.events.options[o]=r),i}},listeners:{"pointerEvents:collect-targets":function(t,e){var n=t.targets,o=t.node,r=t.type,i=t.eventTarget;e.interactables.forEachMatch(o,(function(a){var s=a.events,c=s.options;s.types[r]&&s.types[r].length&&a.testIgnoreAllow(c,o,i)&&n.push({node:o,eventable:s,props:{interactable:a}})}))},"interactable:new":function(t){var e=t.interactable;e.events.getRect=function(n){return e.getRect(n)}},"interactable:set":function(t,e){var n=t.interactable,o=t.options;I(n.events.options,e.pointerEvents.defaults),I(n.events.options,o.pointerEvents||{})}}},Ro=No,qo={id:"pointer-events",install:function(t){t.usePlugin($o),t.usePlugin(jo),t.usePlugin(Ro)}},Xo=qo,Yo={id:"reflow",install:function(t){var e=t.Interactable;t.actions.phases.reflow=!0,e.prototype.reflow=function(n){return(function(o,r,i){for(var a=o.getAllElements(),s=i.window.Promise,c=s?[]:null,d=function(){var u=a[l],g=o.getRect(u);if(!g)return 1;var x,b=Ee(i.interactions.list,(function(_){return _.interacting()&&_.interactable===o&&_.element===u&&_.prepared.name===r.name}));if(b)b.move(),c&&(x=b._reflowPromise||new s((function(_){b._reflowResolve=_})));else{var w=tt(g),E=(function(_){return{coords:_,get page(){return this.coords.page},get client(){return this.coords.client},get timeStamp(){return this.coords.timeStamp},get pageX(){return this.coords.page.x},get pageY(){return this.coords.page.y},get clientX(){return this.coords.client.x},get clientY(){return this.coords.client.y},get pointerId(){return this.coords.pointerId},get target(){return this.coords.target},get type(){return this.coords.type},get pointerType(){return this.coords.pointerType},get buttons(){return this.coords.buttons},preventDefault:function(){}}})({page:{x:w.x,y:w.y},client:{x:w.x,y:w.y},timeStamp:i.now()});x=(function(_,S,O,A,P){var D=_.interactions.new({pointerType:"reflow"}),Y={interaction:D,event:P,pointer:P,eventTarget:O,phase:"reflow"};D.interactable=S,D.element=O,D.prevEvent=P,D.updatePointer(P,P,O,!0),Ft(D.coords.delta),dt(D.prepared,A),D._doPhase(Y);var j=_.window,H=j.Promise,K=H?new H((function(oe){D._reflowResolve=oe})):void 0;return D._reflowPromise=K,D.start(A,S,O),D._interacting?(D.move(Y),D.end(P)):(D.stop(),D._reflowResolve()),D.removePointer(P,P),K})(i,o,u,r,E)}c&&c.push(x)},l=0;l<a.length&&!d();l++);return c&&s.all(c).then((function(){return o}))})(this,n,t)}},listeners:{"interactions:stop":function(t,e){var n=t.interaction;n.pointerType==="reflow"&&(n._reflowResolve&&n._reflowResolve(),(function(o,r){o.splice(o.indexOf(r),1)})(e.interactions.list,n))}}},Bo=Yo;if(X.use(rn),X.use(dn),X.use(Xo),X.use(ao),X.use(Co),X.use(Vn),X.use(Fn),X.use(Nn),X.use(Bo),X.default=X,(typeof fe>"u"?"undefined":R(fe))==="object"&&fe)try{fe.exports=X}catch{}return X.default=X,X}))});var nr={};Zo(nr,{workshopBoard:()=>Tt});var Z=Qo(Pn()),Q={yellow:"#fbbf24",blue:"#60a5fa",green:"#4ade80",pink:"#f472b6",purple:"#a78bfa",orange:"#fb923c",teal:"#2dd4bf",red:"#f87171"};var zn={note:{width:200,height:150,color:"yellow"},text:{width:300,height:40,color:"yellow"},section:{width:500,height:400,color:"yellow"},shape:{width:120,height:120,color:"blue"},connector:{width:0,height:0,color:"blue"}},Oe='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:12px;height:12px;"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>';function Tt({notes:C=[],canvasBlocks:F=[],gridLayout:R={}}={}){return{panX:0,panY:0,scale:1,_isPanning:!1,_panStart:null,_panButton:-1,_spaceDown:!1,_listeners:[],_saveTimers:{},_textTimers:{},_nextTempId:-1,colorPickerOpen:null,_connectorMode:!1,_connectorFrom:null,_svgLayer:null,colors:Object.keys(Q),isFullscreen:!1,init(){this._initialized||(this._initialized=!0,this.$nextTick(()=>{let p=document.createElementNS("http://www.w3.org/2000/svg","svg");p.classList.add("workshop-connectors-layer"),p.setAttribute("style","position:absolute;inset:0;width:100%;height:100%;pointer-events:none;overflow:visible;"),p.innerHTML=`<defs>
          <marker id="arrowhead" markerWidth="10" markerHeight="7" refX="9" refY="3.5" orient="auto">
            <polygon points="0 0, 10 3.5, 0 7" fill="#6b7280"/>
          </marker>
        </defs>`,this.$refs.board.prepend(p),this._svgLayer=p,this._renderNotes(C),this._initPanZoom(),this._initInteract(),this._fitGrid(),this._on(document,"keydown",h=>{if(h.key==="Escape"){if(this._connectorMode){h.preventDefault(),this._cancelConnectorMode();return}this.isFullscreen&&(h.preventDefault(),this.isFullscreen=!1,this._fitAfterDelay())}},!1)}))},destroy(){this._listeners.forEach(([p,h,v,f])=>p.removeEventListener(h,v,f)),this._listeners=[],(0,Z.default)(".workshop-note").unset(),(0,Z.default)(".workshop-text").unset(),(0,Z.default)(".workshop-section").unset(),(0,Z.default)(".workshop-shape").unset(),(0,Z.default)(".workshop-canvas-background").unset()},_on(p,h,v,f){p.addEventListener(h,v,f),this._listeners.push([p,h,v,f])},_renderNotes(p){let h=this.$refs.board;p.forEach(v=>h.appendChild(this._createNoteEl(v)))},_createNoteEl(p){switch(p.type||"note"){case"text":return this._createTextEl(p);case"section":return this._createSectionEl(p);case"shape":return this._createShapeEl(p);case"connector":return this._createConnectorEl(p);default:return this._createStickyEl(p)}},_createStickyEl(p){let h=p.color||"yellow",v=p.x??0,f=p.y??0,m=p.width??200,T=p.height??150,k=document.createElement("div");return k.className=`workshop-note workshop-note-${h}`,k.dataset.noteId=p.id,k.dataset.noteType="note",k.dataset.x=v,k.dataset.y=f,k.style.cssText=`width:${m}px;height:${T}px;transform:translate(${v}px,${f}px);`,k.innerHTML=`
        <div class="drag-handle">
          <div style="display:flex;align-items:center;gap:6px;">
            <div class="drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></div>
            ${this._colorDotHTML(h)}
          </div>
          <button class="note-delete" data-action="delete" title="Loeschen">${Oe}</button>
        </div>
        <div class="note-body">
          <input type="text" value="${this._esc(p.title||"")}" placeholder="Titel..." />
          <textarea placeholder="Notiz...">${this._esc(p.content||"")}</textarea>
        </div>
        <div class="resize-handle"></div>
      `,this._bindNoteEvents(k),this._bindTextSave(k),k},_createTextEl(p){let h=p.x??0,v=p.y??0,f=p.width??300,m=p.height??40,T=p.metadata?.fontSize||Math.max(14,Math.round(f/12)),k=document.createElement("div");return k.className="workshop-text",k.dataset.noteId=p.id,k.dataset.noteType="text",k.dataset.x=h,k.dataset.y=v,k.style.cssText=`width:${f}px;height:${m}px;transform:translate(${h}px,${v}px);`,k.innerHTML=`
        <div class="drag-handle text-drag-handle">
          <div class="drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></div>
          <div class="text-body">
            <input type="text" value="${this._esc(p.title||"")}" placeholder="Text eingeben..." style="font-size:${T}px;" />
          </div>
          <button class="note-delete" data-action="delete" title="Loeschen">${Oe}</button>
        </div>
        <div class="resize-handle"></div>
      `,this._bindDeleteEvent(k),this._bindTextInputSave(k),k},_createSectionEl(p){let h=p.color||"yellow",v=p.x??0,f=p.y??0,m=p.width??500,T=p.height??400,k=document.createElement("div");return k.className=`workshop-section workshop-section-${h}`,k.dataset.noteId=p.id,k.dataset.noteType="section",k.dataset.x=v,k.dataset.y=f,k.style.cssText=`width:${m}px;height:${T}px;transform:translate(${v}px,${f}px);border-color:${Q[h]||Q.yellow};`,k.innerHTML=`
        <div class="drag-handle section-drag-handle">
          <div style="display:flex;align-items:center;gap:6px;flex:1;min-width:0;">
            <div class="drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></div>
            ${this._colorDotHTML(h)}
            <input type="text" class="section-title" value="${this._esc(p.title||"")}" placeholder="Section..." />
          </div>
          <button class="note-delete" data-action="delete" title="Loeschen">${Oe}</button>
        </div>
        <div class="resize-handle"></div>
      `,this._bindNoteEvents(k),this._bindSectionTextSave(k),k},_createShapeEl(p){let h=p.color||"blue",v=p.metadata?.shape||"rect",f=p.x??0,m=p.y??0,T=p.width??120,k=p.height??120,M=document.createElement("div");return M.className=`workshop-shape workshop-shape-${v} workshop-shape-color-${h}`,M.dataset.noteId=p.id,M.dataset.noteType="shape",M.dataset.shape=v,M.dataset.x=f,M.dataset.y=m,M.style.cssText=`width:${T}px;height:${k}px;transform:translate(${f}px,${m}px);`,M.innerHTML=`
        <div class="shape-visual"></div>
        <div class="drag-handle shape-drag-handle">
          <div style="display:flex;align-items:center;gap:4px;">
            <div class="drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></div>
            ${this._colorDotHTML(h)}
            <button class="shape-toggle" data-action="toggle-shape" title="Form wechseln">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:10px;height:10px;"><path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H4.598a.75.75 0 00-.75.75v3.634a.75.75 0 001.5 0v-2.033l.312.312a7 7 0 0011.712-3.138.75.75 0 00-1.449-.39zm1.06-7.846a.75.75 0 00-1.5 0v2.034l-.312-.312A7 7 0 002.848 8.438a.75.75 0 001.449.39 5.5 5.5 0 019.201-2.466l.312.311H11.38a.75.75 0 000 1.5h3.634a.75.75 0 00.75-.75V3.578z" clip-rule="evenodd"/></svg>
            </button>
            <button class="note-delete" data-action="delete" title="Loeschen">${Oe}</button>
          </div>
        </div>
        <div class="shape-body">
          <input type="text" value="${this._esc(p.title||"")}" placeholder="..." />
        </div>
        <div class="resize-handle"></div>
      `,this._bindShapeEvents(M),this._bindShapeTextSave(M),M},_colorDotHTML(p){return`<div class="color-dot-wrap" style="position:relative;">
        <div class="color-dot" style="background:${Q[p]||Q.yellow};" data-action="color"></div>
        <div class="color-picker-dd" style="display:none;position:absolute;top:100%;left:0;margin-top:4px;padding:4px;background:white;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.15);border:1px solid #e5e7eb;z-index:50;gap:3px;flex-wrap:nowrap;">
          ${this.colors.map(h=>`<div class="color-dot${h===p?" active":""}" style="background:${Q[h]};" data-pick-color="${h}"></div>`).join("")}
        </div>
      </div>`},_bindNoteEvents(p){p.addEventListener("click",h=>{if(this._handleConnectorClick(p)){h.stopPropagation();return}let v=h.target.closest("[data-action]")?.dataset.action,f=h.target.closest("[data-pick-color]")?.dataset.pickColor,m=parseInt(p.dataset.noteId);if(f){h.stopPropagation(),this._changeColor(p,m,f);return}if(v==="color"){h.stopPropagation(),this._toggleColorPicker(p);return}if(v==="delete"){h.stopPropagation(),confirm("Element loeschen?")&&this._deleteNote(p,m);return}})},_bindDeleteEvent(p){p.addEventListener("click",h=>{if(this._handleConnectorClick(p)){h.stopPropagation();return}let v=h.target.closest("[data-action]")?.dataset.action,f=parseInt(p.dataset.noteId);v==="delete"&&(h.stopPropagation(),confirm("Element loeschen?")&&this._deleteNote(p,f))})},_bindShapeEvents(p){p.addEventListener("click",h=>{if(this._handleConnectorClick(p)){h.stopPropagation();return}let v=h.target.closest("[data-action]")?.dataset.action,f=h.target.closest("[data-pick-color]")?.dataset.pickColor,m=parseInt(p.dataset.noteId);if(f){h.stopPropagation(),this._changeShapeColor(p,m,f);return}if(v==="color"){h.stopPropagation(),this._toggleColorPicker(p);return}if(v==="toggle-shape"){h.stopPropagation(),this._toggleShape(p,m);return}if(v==="delete"){h.stopPropagation(),confirm("Element loeschen?")&&this._deleteNote(p,m);return}})},_bindTextSave(p){let h=p.querySelector(".note-body input"),v=p.querySelector(".note-body textarea"),f=()=>{let m=parseInt(p.dataset.noteId);m<0||(clearTimeout(this._textTimers[m]),this._textTimers[m]=setTimeout(()=>{this.$wire.call("updateNoteText",m,h.value,v.value)},400))};h.addEventListener("blur",f),v.addEventListener("blur",f),h.addEventListener("keydown",m=>{m.key==="Enter"&&m.target.blur()})},_bindTextInputSave(p){let h=p.querySelector(".text-body input"),v=()=>{let f=parseInt(p.dataset.noteId);f<0||(clearTimeout(this._textTimers[f]),this._textTimers[f]=setTimeout(()=>{this.$wire.call("updateNoteText",f,h.value,"")},400))};h.addEventListener("blur",v),h.addEventListener("keydown",f=>{f.key==="Enter"&&f.target.blur()})},_bindSectionTextSave(p){let h=p.querySelector(".section-title"),v=()=>{let f=parseInt(p.dataset.noteId);f<0||(clearTimeout(this._textTimers[f]),this._textTimers[f]=setTimeout(()=>{this.$wire.call("updateNoteText",f,h.value,"")},400))};h.addEventListener("blur",v),h.addEventListener("keydown",f=>{f.key==="Enter"&&f.target.blur()})},_bindShapeTextSave(p){let h=p.querySelector(".shape-body input"),v=()=>{let f=parseInt(p.dataset.noteId);f<0||(clearTimeout(this._textTimers[f]),this._textTimers[f]=setTimeout(()=>{this.$wire.call("updateNoteText",f,h.value,"")},400))};h.addEventListener("blur",v),h.addEventListener("keydown",f=>{f.key==="Enter"&&f.target.blur()})},_esc(p){let h=document.createElement("div");return h.textContent=p,h.innerHTML},_applyTransform(){let p=this.$refs.board;p&&(p.style.transform=`translate(${this.panX}px,${this.panY}px) scale(${this.scale})`)},_screenToBoard(p,h){return{x:(p-this.panX)/this.scale,y:(h-this.panY)/this.scale}},_zoomTo(p,h,v){let f=this.$refs.board?.parentElement;if(!f)return;let m=f.getBoundingClientRect(),T=h-m.left,k=v-m.top,M=Math.max(.1,Math.min(4,p)),$=M/this.scale;this.panX=T-(T-this.panX)*$,this.panY=k-(k-this.panY)*$,this.scale=M,this._applyTransform()},_initPanZoom(){let p=this.$refs.board;if(!p)return;let h=p.parentElement;p.style.transformOrigin="0 0",this._on(h,"wheel",v=>{v.preventDefault(),v.ctrlKey||v.metaKey?this._zoomTo(this.scale*(1-v.deltaY*.003),v.clientX,v.clientY):(this.panX-=v.deltaX,this.panY-=v.deltaY,this._applyTransform())},{passive:!1}),this._on(h,"pointerdown",v=>{v.target.closest(".workshop-note, .workshop-text, .workshop-section, .workshop-shape, .workshop-toolbar, .workshop-zoom-controls")||(v.button===1||v.button===0&&this._spaceDown)&&(this._isPanning=!0,this._panButton=v.button,this._panStart={x:v.clientX,y:v.clientY,px:this.panX,py:this.panY},h.style.cursor="grabbing",h.setPointerCapture(v.pointerId),v.preventDefault())},!1),this._on(h,"pointermove",v=>{if(this._isPanning&&this._panStart&&(this.panX=this._panStart.px+(v.clientX-this._panStart.x),this.panY=this._panStart.py+(v.clientY-this._panStart.y),this._applyTransform()),this._previewLine&&this._connectorMode&&this._connectorFrom){let f=this._screenToBoard(v.clientX,v.clientY);this._previewLine.setAttribute("x2",f.x),this._previewLine.setAttribute("y2",f.y)}},!1),this._on(h,"pointerup",v=>{this._isPanning&&(this._isPanning=!1,this._panStart=null,h.style.cursor=this._spaceDown?"grab":"")},!1),this._on(h,"contextmenu",v=>{this._panButton===1&&v.preventDefault()},!1),this._on(document,"keydown",v=>{v.code==="Space"&&!v.repeat&&!v.target.matches("input,textarea,[contenteditable]")&&(v.preventDefault(),this._spaceDown=!0,h.style.cursor="grab")},!1),this._on(document,"keyup",v=>{v.code==="Space"&&(this._spaceDown=!1,this._isPanning||(h.style.cursor=""))},!1),this._on(document,"click",()=>{p.querySelectorAll('.color-picker-dd[style*="flex"]').forEach(v=>v.style.display="none")},!1)},zoomIn(){this._zoomToCenter(this.scale*1.3)},zoomOut(){this._zoomToCenter(this.scale/1.3)},resetZoom(){this.scale=1,this.panX=0,this.panY=0,this._applyTransform()},fitToScreen(){this._fitGrid()},toggleFullscreen(){this.isFullscreen=!this.isFullscreen,this._fitAfterDelay()},_fitAfterDelay(){setTimeout(()=>this._fitGrid(),50),setTimeout(()=>this._fitGrid(),200),setTimeout(()=>this._fitGrid(),500)},_zoomToCenter(p){let h=this.$refs.board?.parentElement;if(!h)return;let v=h.getBoundingClientRect();this._zoomTo(p,v.left+h.clientWidth/2,v.top+h.clientHeight/2)},_fitGrid(){let p=this.$refs.board,h=p?.parentElement,v=p?.querySelector(".workshop-canvas-background");if(!v||!h)return;let f=v.offsetWidth,m=v.offsetHeight,T=v.offsetLeft,k=v.offsetTop,M=h.clientWidth,$=h.clientHeight,L=40,N=Math.min((M-L*2)/f,($-L*2)/m,1);this.scale=N,this.panX=(M-f*N)/2-T*N,this.panY=($-m*N)/2-k*N,this._applyTransform()},_initInteract(){let p=this,h=".workshop-note, .workshop-text, .workshop-section, .workshop-shape",v=h;(0,Z.default)(h).draggable({allowFrom:".drag-handle",ignoreFrom:"input, textarea, .note-delete, .shape-toggle, .color-dot, .color-picker-dd",inertia:!1,listeners:{start(f){f.target.classList.add("dragging")},move(f){let m=f.target,T=(parseFloat(m.dataset.x)||0)+f.dx/p.scale,k=(parseFloat(m.dataset.y)||0)+f.dy/p.scale;m.style.transform=`translate(${T}px,${k}px)`,m.dataset.x=T,m.dataset.y=k,p._updateConnectors()},end(f){f.target.classList.remove("dragging");let m=f.target,T=parseInt(m.dataset.noteId);T<0||p._savePos(T,m)}}}),(0,Z.default)(v).resizable({edges:{right:".resize-handle",bottom:".resize-handle"},modifiers:[Z.default.modifiers.restrictSize({min:{width:60,height:30}})],listeners:{move(f){let m=f.target,T=parseFloat(m.dataset.x)||0,k=parseFloat(m.dataset.y)||0,M=f.rect.width/p.scale,$=f.rect.height/p.scale;if(m.style.width=M+"px",m.style.height=$+"px",T+=f.deltaRect.left/p.scale,k+=f.deltaRect.top/p.scale,m.style.transform=`translate(${T}px,${k}px)`,m.dataset.x=T,m.dataset.y=k,m.dataset.noteType==="text"){let L=Math.max(14,Math.round(M/12)),N=m.querySelector(".text-body input");N&&(N.style.fontSize=L+"px")}p._updateConnectors()},end(f){let m=f.target,T=parseInt(m.dataset.noteId);T<0||p._savePos(T,m)}}}),(0,Z.default)(".workshop-canvas-background").resizable({edges:{right:!0,bottom:!0},modifiers:[Z.default.modifiers.restrictSize({min:{width:400,height:300}})],listeners:{move(f){let m=f.target;m.style.width=f.rect.width/p.scale+"px",m.style.minHeight=f.rect.height/p.scale+"px"},end(f){let m=f.target,T=parseInt(m.style.width)||1200,k=parseInt(m.style.minHeight)||800;clearTimeout(p._gridSaveTimer),p._gridSaveTimer=setTimeout(()=>{p.$wire.call("updateWorkshopSettings",{gridWidth:T,gridHeight:k})},400)}}})},_savePos(p,h){clearTimeout(this._saveTimers[p]),this._saveTimers[p]=setTimeout(()=>{let v=this._detectBlock(h);this.$wire.call("updateNotePosition",p,{x:parseFloat(h.dataset.x)||0,y:parseFloat(h.dataset.y)||0,width:parseInt(h.style.width)||200,height:parseInt(h.style.height)||150,blockId:v})},300)},_detectBlock(p){let h=parseFloat(p.dataset.x)||0,v=parseFloat(p.dataset.y)||0,f=h+(parseInt(p.style.width)||0)/2,m=v+(parseInt(p.style.height)||0)/2,T=this.$refs.board?.querySelectorAll(".workshop-grid-block[data-block-id]");if(!T)return null;for(let k of T){let M=k.offsetParent,$=k.offsetLeft+(M?.offsetLeft||0),L=k.offsetTop+(M?.offsetTop||0),N=k.offsetWidth,W=k.offsetHeight;if(f>=$&&f<=$+N&&m>=L&&m<=L+W)return parseInt(k.dataset.blockId)||null}return null},addElement(p="note"){let h=this.$refs.board?.parentElement;if(!h)return;let v=h.getBoundingClientRect(),f=zn[p]||zn.note,m=(v.width/2-this.panX)/this.scale,T=(v.height/2-this.panY)/this.scale,k=Math.round(m-f.width/2),M=Math.round(T-f.height/2),$=this._nextTempId--,L=p==="shape"?{shape:"rect"}:null,N=this._createNoteEl({id:$,type:p,title:"",content:"",color:f.color,x:k,y:M,width:f.width,height:f.height,metadata:L});this.$refs.board.appendChild(N),this.$wire.call("addWorkshopNote",{x:k,y:M},p).then(()=>{this.$wire.call("getWorkshopNotes").then(W=>{if(Array.isArray(W)&&W.length>0){let le=W.reduce((B,ye)=>B.id>ye.id?B:ye);N.dataset.noteId=le.id}})}),setTimeout(()=>{N.querySelector(".note-body input, .text-body input, .section-title, .shape-body input")?.focus()},100)},addNote(){this.addElement("note")},_deleteNote(p,h){if(p.remove(),this._svgLayer){let v=String(h);this._svgLayer.querySelectorAll(".workshop-connector-path").forEach(f=>{if(f.dataset.fromNoteId===v||f.dataset.toNoteId===v){let m=this._svgLayer.querySelector(`.connector-delete-fo[data-connector-id="${f.dataset.connectorId}"]`);m&&m.remove();let T=this.$refs.board.querySelector(`[data-note-id="${f.dataset.connectorId}"][data-note-type="connector"]`);T&&T.remove(),f.remove()}})}h>0&&this.$wire.call("deleteWorkshopNote",h)},_changeColor(p,h,v){let f=p.dataset.noteType||"note";f==="note"?p.className=p.className.replace(/workshop-note-\w+/,`workshop-note-${v}`):f==="section"&&(p.className=p.className.replace(/workshop-section-\w+/,`workshop-section-${v}`),p.style.borderColor=Q[v]||Q.yellow),p.querySelector(".drag-handle .color-dot")?.setAttribute("style",`background:${Q[v]}`),p.querySelector(".color-picker-dd").style.display="none",p.querySelectorAll(".color-picker-dd .color-dot").forEach(m=>{m.classList.toggle("active",m.dataset.pickColor===v)}),h>0&&this.$wire.call("updateNoteColor",h,v)},_changeShapeColor(p,h,v){p.className=p.className.replace(/workshop-shape-color-\w+/,`workshop-shape-color-${v}`);let f=p.querySelector(".shape-visual");f&&(f.className="shape-visual"),p.querySelector(".color-dot")?.setAttribute("style",`background:${Q[v]}`),p.querySelector(".color-picker-dd").style.display="none",p.querySelectorAll(".color-picker-dd .color-dot").forEach(m=>{m.classList.toggle("active",m.dataset.pickColor===v)}),h>0&&this.$wire.call("updateNoteColor",h,v)},_toggleShape(p,h){let v=["rect","circle","diamond"],f=p.dataset.shape||"rect",m=v[(v.indexOf(f)+1)%v.length];p.dataset.shape=m,p.className=p.className.replace(/workshop-shape-(?:rect|circle|diamond)/,`workshop-shape-${m}`),h>0&&this.$wire.call("updateNoteMetadata",h,{shape:m})},_toggleColorPicker(p){let h=p.querySelector(".color-picker-dd");if(!h)return;let v=h.style.display==="flex";this.$refs.board.querySelectorAll(".color-picker-dd").forEach(f=>f.style.display="none"),h.style.display=v?"none":"flex"},_createConnectorEl(p){let h=p.metadata||{},v=document.createElement("div");if(v.style.cssText="position:absolute;width:0;height:0;pointer-events:none;",v.dataset.noteId=p.id,v.dataset.noteType="connector",v.dataset.fromNoteId=h.fromNoteId||"",v.dataset.toNoteId=h.toNoteId||"",this._svgLayer){let f=document.createElementNS("http://www.w3.org/2000/svg","path");f.classList.add("workshop-connector-path"),f.dataset.connectorId=p.id,f.dataset.fromNoteId=h.fromNoteId||"",f.dataset.toNoteId=h.toNoteId||"",f.setAttribute("marker-end","url(#arrowhead)"),f.setAttribute("fill","none"),f.setAttribute("stroke","#6b7280"),f.setAttribute("stroke-width","2"),f.style.pointerEvents="stroke",f.style.cursor="pointer",this._svgLayer.appendChild(f);let m=document.createElementNS("http://www.w3.org/2000/svg","foreignObject");m.classList.add("connector-delete-fo"),m.dataset.connectorId=p.id,m.setAttribute("width","24"),m.setAttribute("height","24"),m.style.overflow="visible",m.style.display="none",m.innerHTML=`<button xmlns="http://www.w3.org/1999/xhtml" class="connector-delete-btn" title="Loeschen">${Oe}</button>`,this._svgLayer.appendChild(m),f.addEventListener("mouseenter",()=>{m.style.display="",f.classList.add("hovered")}),f.addEventListener("mouseleave",()=>{setTimeout(()=>{m.matches(":hover")||(m.style.display="none",f.classList.remove("hovered"))},200)}),m.addEventListener("mouseleave",()=>{m.style.display="none",f.classList.remove("hovered")}),m.querySelector(".connector-delete-btn").addEventListener("click",T=>{T.stopPropagation();let k=parseInt(p.id);f.remove(),m.remove(),v.remove(),k>0&&this.$wire.call("deleteWorkshopNote",k)}),this._updateSingleConnector(f,m)}return v},_getAnchorPoint(p){let h=this.$refs.board.querySelector(`[data-note-id="${p}"]:not([data-note-type="connector"])`);if(!h)return null;let v=parseFloat(h.dataset.x)||0,f=parseFloat(h.dataset.y)||0,m=parseInt(h.style.width)||0,T=parseInt(h.style.height)||0;return{x:v,y:f,w:m,h:T,cx:v+m/2,cy:f+T/2}},_bestAnchors(p,h){let v=h.cx-p.cx,f=h.cy-p.cy,m,T;return Math.abs(v)>Math.abs(f)?v>0?(m={x:p.x+p.w,y:p.cy},T={x:h.x,y:h.cy}):(m={x:p.x,y:p.cy},T={x:h.x+h.w,y:h.cy}):f>0?(m={x:p.cx,y:p.y+p.h},T={x:h.cx,y:h.y}):(m={x:p.cx,y:p.y},T={x:h.cx,y:h.y+h.h}),{from:m,to:T}},_buildConnectorPath(p,h){let v=h.x-p.x,f=h.y-p.y,m=Math.sqrt(v*v+f*f),T=Math.min(m*.4,80),k,M,$,L;return Math.abs(v)>Math.abs(f)?(k=p.x+T*Math.sign(v),M=p.y,$=h.x-T*Math.sign(v),L=h.y):(k=p.x,M=p.y+T*Math.sign(f),$=h.x,L=h.y-T*Math.sign(f)),`M ${p.x},${p.y} C ${k},${M} ${$},${L} ${h.x},${h.y}`},_updateSingleConnector(p,h){let v=p.dataset.fromNoteId,f=p.dataset.toNoteId;if(!v||!f)return;let m=this._getAnchorPoint(v),T=this._getAnchorPoint(f);if(!m||!T){p.setAttribute("d","");return}let{from:k,to:M}=this._bestAnchors(m,T);p.setAttribute("d",this._buildConnectorPath(k,M));let $=(k.x+M.x)/2-12,L=(k.y+M.y)/2-12;h.setAttribute("x",$),h.setAttribute("y",L)},_updateConnectors(){if(!this._svgLayer)return;this._svgLayer.querySelectorAll(".workshop-connector-path").forEach(h=>{let v=this._svgLayer.querySelector(`.connector-delete-fo[data-connector-id="${h.dataset.connectorId}"]`);v&&this._updateSingleConnector(h,v)})},startConnectorMode(){if(this._connectorMode){this._cancelConnectorMode();return}this._connectorMode=!0,this._connectorFrom=null,this.$refs.board.parentElement.classList.add("connector-mode")},_cancelConnectorMode(){this._connectorMode=!1,this._connectorFrom=null,this.$refs.board.parentElement.classList.remove("connector-mode"),this.$refs.board.querySelectorAll(".connector-source-selected").forEach(p=>p.classList.remove("connector-source-selected")),this._previewLine&&(this._previewLine.remove(),this._previewLine=null)},_handleConnectorClick(p){if(!this._connectorMode)return!1;let h=parseInt(p.dataset.noteId);if(p.dataset.noteType==="connector")return!1;if(this._connectorFrom){if(h===this._connectorFrom)return!0;let f=this._connectorFrom,m=h,T=this._nextTempId--,k=this._createConnectorEl({id:T,type:"connector",title:"",content:"",color:"blue",x:0,y:0,width:0,height:0,metadata:{fromNoteId:f,toNoteId:m,style:"solid",arrowHead:"end"}});return this.$refs.board.appendChild(k),this.$wire.call("addConnector",f,m).then(()=>{this.$wire.call("getWorkshopNotes").then(M=>{if(Array.isArray(M)){let $=M.filter(L=>L.type==="connector");if($.length>0){let L=$.reduce((le,B)=>le.id>B.id?le:B);k.dataset.noteId=L.id;let N=this._svgLayer.querySelector(`.workshop-connector-path[data-connector-id="${T}"]`),W=this._svgLayer.querySelector(`.connector-delete-fo[data-connector-id="${T}"]`);N&&(N.dataset.connectorId=L.id),W&&(W.dataset.connectorId=L.id)}}})}),this._cancelConnectorMode(),!0}else{this._connectorFrom=h,p.classList.add("connector-source-selected");let f=document.createElementNS("http://www.w3.org/2000/svg","line");f.classList.add("workshop-connector-preview"),f.setAttribute("stroke","#f2ca52"),f.setAttribute("stroke-width","2"),f.setAttribute("stroke-dasharray","6 4"),f.style.pointerEvents="none";let m=this._getAnchorPoint(h);return m&&(f.setAttribute("x1",m.cx),f.setAttribute("y1",m.cy),f.setAttribute("x2",m.cx),f.setAttribute("y2",m.cy)),this._svgLayer.appendChild(f),this._previewLine=f,!0}}}}var Mn=`/* \u2500\u2500\u2500 Board (infinite canvas) \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500 */
.workshop-board {
  position: relative;
  touch-action: none;
  user-select: none;
  will-change: transform;
  transform-origin: 0 0;
  background: #f0f2f5;
  background-image: radial-gradient(circle, rgba(0,0,0,0.05) 1px, transparent 1px);
  background-size: 24px 24px;
}

/* \u2500\u2500\u2500 Canvas Grid (read-only background) \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500 */
.workshop-canvas-background {
  position: absolute;
  background: white;
  border-radius: 4px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.06);
  cursor: default;
  user-select: none;
  -webkit-user-select: none;
}

.workshop-canvas-background::after {
  content: '';
  position: absolute;
  right: 0;
  bottom: 0;
  width: 16px;
  height: 16px;
  cursor: se-resize;
  border-right: 3px solid #d1d5db;
  border-bottom: 3px solid #d1d5db;
  border-radius: 0 0 4px 0;
  opacity: 0;
  transition: opacity 0.15s;
}

.workshop-canvas-background:hover::after {
  opacity: 1;
}

.workshop-grid-block {
  background: white;
  display: flex;
  flex-direction: column;
  transition: box-shadow 0.2s;
}

.workshop-grid-block.adopt-highlight {
  border-color: #f2ca52;
  box-shadow: 0 0 0 3px rgba(242, 202, 82, 0.3), 0 0 20px rgba(242, 202, 82, 0.15);
  background: #fffef8;
  z-index: 2;
}

/* Header: Title left, Icon right */
.workshop-grid-block-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: 0.75rem 0.875rem 0.5rem;
  gap: 0.5rem;
}

.workshop-grid-block-header h4 {
  font-size: 0.8125rem;
  font-weight: 700;
  color: #1a1a2e;
  margin: 0;
  line-height: 1.3;
  letter-spacing: -0.01em;
}

.workshop-grid-block-header svg {
  flex-shrink: 0;
  opacity: 0.2;
  margin-top: 1px;
}

/* Body */
.workshop-grid-block-body {
  flex: 1;
  padding: 0 0.875rem 0.75rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

/* Guiding Questions */
.workshop-grid-block-body .guiding-questions {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
}

.workshop-grid-block-body .guiding-question {
  font-size: 0.625rem;
  color: #b0b7c3;
  line-height: 1.4;
  font-style: italic;
}

/* Entries */
.workshop-grid-block-body .grid-entries {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  margin-top: 0.25rem;
}

.workshop-grid-block-body .grid-entry {
  padding: 0.25rem 0;
  display: flex;
  flex-direction: column;
  gap: 0.0625rem;
}

.workshop-grid-block-body .grid-entry-title {
  font-size: 0.6875rem;
  font-weight: 600;
  color: #374151;
  line-height: 1.3;
}

.workshop-grid-block-body .grid-entry-content {
  font-size: 0.625rem;
  color: #9ca3af;
  line-height: 1.3;
}

/* \u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550
   Shared Element Styles (Notes, Text, Section, Shape)
   \u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550 */

/* Shared drag handle base */
.drag-handle {
  display: flex;
  align-items: center;
  justify-content: space-between;
  cursor: grab;
  min-height: 1.75rem;
  gap: 0.25rem;
}

.drag-handle:active {
  cursor: grabbing;
}

.drag-handle .drag-dots {
  display: grid;
  grid-template-columns: repeat(3, 3px);
  gap: 1.5px;
  opacity: 0.25;
}

.drag-handle .drag-dots span {
  width: 3px;
  height: 3px;
  border-radius: 50%;
  background: currentColor;
}

/* Shared delete button */
.note-delete {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.25rem;
  height: 1.25rem;
  border-radius: 9999px;
  border: none;
  background: transparent;
  color: #d1d5db;
  cursor: pointer;
  font-size: 0.75rem;
  opacity: 0;
  transition: all 0.15s;
  flex-shrink: 0;
}

.workshop-note:hover .note-delete,
.workshop-text:hover .note-delete,
.workshop-section:hover .note-delete,
.workshop-shape:hover .note-delete {
  opacity: 1;
}

.note-delete:hover {
  background: #fecaca;
  color: #dc2626;
}

/* Shared Color Picker */
.color-dot {
  width: 0.625rem;
  height: 0.625rem;
  border-radius: 50%;
  cursor: pointer;
  border: 1.5px solid rgba(0,0,0,0.1);
  flex-shrink: 0;
  transition: transform 0.1s;
}

.color-dot:hover {
  transform: scale(1.3);
}

.color-dot.active {
  border-color: #1a1a2e;
  box-shadow: 0 0 0 1.5px white, 0 0 0 3px #1a1a2e;
}

/* Shared Resize Handle */
.resize-handle {
  position: absolute;
  right: 0;
  bottom: 0;
  width: 14px;
  height: 14px;
  cursor: se-resize;
  opacity: 0;
  transition: opacity 0.15s;
}

.workshop-note:hover .resize-handle,
.workshop-text:hover .resize-handle,
.workshop-section:hover .resize-handle,
.workshop-shape:hover .resize-handle {
  opacity: 0.4;
}

.resize-handle::after {
  content: '';
  position: absolute;
  right: 3px;
  bottom: 3px;
  width: 6px;
  height: 6px;
  border-right: 2px solid currentColor;
  border-bottom: 2px solid currentColor;
}

/* \u2500\u2500\u2500 Sticky Notes \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500 */
.workshop-note {
  position: absolute;
  border-radius: 0.5rem;
  box-shadow: 0 2px 6px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06);
  cursor: default;
  display: flex;
  flex-direction: column;
  transition: box-shadow 0.15s, opacity 0.15s;
  z-index: 10;
  touch-action: none;
  opacity: 0.85;
}

.workshop-note:hover {
  opacity: 1;
  box-shadow: 0 4px 16px rgba(0,0,0,0.12), 0 2px 4px rgba(0,0,0,0.08);
  z-index: 15;
}

.workshop-note.dragging {
  opacity: 0.9;
  z-index: 1000 !important;
  box-shadow: 0 12px 32px rgba(0,0,0,0.18);
}

.workshop-note .drag-handle {
  padding: 0.375rem 0.5rem;
  border-bottom: 1px solid rgba(0,0,0,0.06);
}

/* Note Content */
.workshop-note .note-body {
  flex: 1;
  padding: 0.375rem 0.5rem;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  overflow: hidden;
}

.workshop-note .note-body input {
  font-size: 0.75rem;
  font-weight: 600;
  color: #1a1a2e;
  background: transparent;
  border: none;
  outline: none;
  width: 100%;
  padding: 0;
  cursor: text;
}

.workshop-note .note-body input::placeholder {
  color: rgba(0,0,0,0.2);
}

.workshop-note .note-body textarea {
  font-size: 0.6875rem;
  color: #374151;
  background: transparent;
  border: none;
  outline: none;
  width: 100%;
  padding: 0;
  resize: none;
  flex: 1;
  min-height: 2rem;
  line-height: 1.4;
  cursor: text;
}

.workshop-note .note-body textarea::placeholder {
  color: rgba(0,0,0,0.15);
}

/* Note Color Variants */
.workshop-note-yellow  { background: #fef9c3; }
.workshop-note-blue    { background: #dbeafe; }
.workshop-note-green   { background: #dcfce7; }
.workshop-note-pink    { background: #fce7f3; }
.workshop-note-purple  { background: #f3e8ff; }
.workshop-note-orange  { background: #ffedd5; }
.workshop-note-teal    { background: #ccfbf1; }
.workshop-note-red     { background: #fee2e2; }

/* Note resize handle corner */
.workshop-note .resize-handle {
  border-radius: 0 0 0.5rem 0;
}

/* \u2500\u2500\u2500 Text Element \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500 */
.workshop-text {
  position: absolute;
  cursor: default;
  display: flex;
  flex-direction: column;
  z-index: 10;
  touch-action: none;
}

.workshop-text:hover {
  z-index: 15;
}

.workshop-text.dragging {
  opacity: 0.8;
  z-index: 1000 !important;
}

/* Text: entire element is the drag handle, contains the input inside */
.workshop-text .text-drag-handle {
  flex: 1;
  display: flex;
  align-items: center;
  cursor: grab;
  padding: 0.25rem 0.375rem;
  border-radius: 4px;
  transition: background 0.15s;
  position: relative;
}

.workshop-text:hover .text-drag-handle {
  background: rgba(0,0,0,0.02);
}

/* Delete floats top-right outside the element */
.workshop-text .note-delete {
  position: absolute;
  top: -6px;
  right: -6px;
  background: white;
  border-radius: 9999px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.15);
  width: 1.25rem;
  height: 1.25rem;
}

.workshop-text .text-body {
  flex: 1;
  display: flex;
  align-items: center;
  min-width: 0;
}

.workshop-text .text-body input {
  font-size: 24px;
  font-weight: 700;
  color: #1a1a2e;
  background: transparent;
  border: none;
  outline: none;
  width: 100%;
  padding: 0;
  cursor: text;
  line-height: 1.2;
}

.workshop-text .text-body input::placeholder {
  color: rgba(0,0,0,0.15);
}

/* \u2500\u2500\u2500 Section (Frame) \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500 */
.workshop-section {
  position: absolute;
  border: 2px dashed #fbbf24;
  border-radius: 0.75rem;
  background: rgba(251, 191, 36, 0.05);
  cursor: default;
  display: flex;
  flex-direction: column;
  z-index: 5;
  touch-action: none;
  transition: box-shadow 0.15s;
}

.workshop-section:hover {
  box-shadow: 0 2px 12px rgba(0,0,0,0.06);
  z-index: 6;
}

.workshop-section.dragging {
  opacity: 0.8;
  z-index: 999 !important;
  box-shadow: 0 8px 24px rgba(0,0,0,0.12);
}

.workshop-section .section-drag-handle {
  padding: 0.5rem 0.625rem;
  border-bottom: none;
  cursor: grab;
}

.workshop-section .section-title {
  font-size: 0.8125rem;
  font-weight: 700;
  color: #1a1a2e;
  background: transparent;
  border: none;
  outline: none;
  flex: 1;
  min-width: 0;
  padding: 0;
  cursor: text;
}

.workshop-section .section-title::placeholder {
  color: rgba(0,0,0,0.2);
}

/* Section Color Variants (border + background tint) */
.workshop-section-yellow { border-color: #fbbf24; background: rgba(251, 191, 36, 0.05); }
.workshop-section-blue   { border-color: #60a5fa; background: rgba(96, 165, 250, 0.05); }
.workshop-section-green  { border-color: #4ade80; background: rgba(74, 222, 128, 0.05); }
.workshop-section-pink   { border-color: #f472b6; background: rgba(244, 114, 182, 0.05); }
.workshop-section-purple { border-color: #a78bfa; background: rgba(167, 139, 250, 0.05); }
.workshop-section-orange { border-color: #fb923c; background: rgba(251, 146, 60, 0.05); }
.workshop-section-teal   { border-color: #2dd4bf; background: rgba(45, 212, 191, 0.05); }
.workshop-section-red    { border-color: #f87171; background: rgba(248, 113, 113, 0.05); }

/* \u2500\u2500\u2500 Shape \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500 */
.workshop-shape {
  position: absolute;
  cursor: default;
  z-index: 10;
  touch-action: none;
}

.workshop-shape:hover {
  z-index: 15;
}

.workshop-shape.dragging {
  opacity: 0.8;
  z-index: 1000 !important;
}

/* Inner visual \u2014 takes the color + clip/radius, fills the parent */
.workshop-shape .shape-visual {
  position: absolute;
  inset: 0;
  z-index: 0;
}

/* Shape variant clips/radius on the visual layer only */
.workshop-shape-rect .shape-visual  { border-radius: 0.5rem; }
.workshop-shape-circle .shape-visual { border-radius: 50%; }
.workshop-shape-diamond .shape-visual { clip-path: polygon(50% 0%, 100% 50%, 50% 100%, 0% 50%); }

/* Color on visual */
.workshop-shape-color-yellow .shape-visual { background: #fbbf24; }
.workshop-shape-color-blue   .shape-visual { background: #60a5fa; }
.workshop-shape-color-green  .shape-visual { background: #4ade80; }
.workshop-shape-color-pink   .shape-visual { background: #f472b6; }
.workshop-shape-color-purple .shape-visual { background: #a78bfa; }
.workshop-shape-color-orange .shape-visual { background: #fb923c; }
.workshop-shape-color-teal   .shape-visual { background: #2dd4bf; }
.workshop-shape-color-red    .shape-visual { background: #f87171; }

/* Controls toolbar \u2014 floats above the shape, always accessible */
.workshop-shape .shape-drag-handle {
  position: absolute;
  top: -28px;
  left: 50%;
  transform: translateX(-50%);
  padding: 3px 6px;
  opacity: 0;
  transition: opacity 0.15s;
  cursor: grab;
  background: white;
  border-radius: 6px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.15);
  z-index: 3;
  display: flex;
  align-items: center;
  gap: 4px;
  white-space: nowrap;
}

.workshop-shape:hover .shape-drag-handle {
  opacity: 1;
}

.workshop-shape .shape-toggle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.25rem;
  height: 1.25rem;
  border-radius: 4px;
  border: none;
  background: transparent;
  color: #9ca3af;
  cursor: pointer;
  transition: all 0.15s;
}

.workshop-shape .shape-toggle:hover {
  background: #f3f4f6;
  color: #1a1a2e;
}

/* Text label centered over the shape */
.workshop-shape .shape-body {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1;
  pointer-events: none;
}

.workshop-shape .shape-body input {
  font-size: 0.75rem;
  font-weight: 600;
  color: #1a1a2e;
  background: transparent;
  border: none;
  outline: none;
  text-align: center;
  width: 70%;
  padding: 0;
  cursor: text;
  pointer-events: auto;
}

/* \u2500\u2500\u2500 Element Toolbar \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500 */
.workshop-toolbar {
  position: absolute;
  bottom: 1.5rem;
  right: 1.5rem;
  z-index: 50;
  display: flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.25rem;
  background: white;
  border-radius: 9999px;
  border: 1px solid #e5e7eb;
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.workshop-toolbar-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.5rem 0.875rem;
  border-radius: 9999px;
  border: none;
  background: transparent;
  color: #6b7280;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
  white-space: nowrap;
}

.workshop-toolbar-btn:hover {
  background: #f2ca52;
  color: #1a1a2e;
  box-shadow: 0 2px 8px rgba(242, 202, 82, 0.3);
}

/* \u2500\u2500\u2500 Zoom Controls \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500 */
.workshop-zoom-controls {
  position: absolute;
  top: 0.75rem;
  right: 0.75rem;
  z-index: 50;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  background: white;
  border-radius: 0.75rem;
  border: 1px solid #e5e7eb;
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
  padding: 0.25rem;
}

.workshop-zoom-controls button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border-radius: 0.5rem;
  border: none;
  background: transparent;
  color: #6b7280;
  cursor: pointer;
  font-size: 0.875rem;
  transition: all 0.15s;
}

.workshop-zoom-controls button:hover {
  background: #f3f4f6;
  color: #1a1a2e;
}

.workshop-zoom-controls .zoom-level {
  text-align: center;
  font-size: 0.625rem;
  font-weight: 600;
  color: #9ca3af;
  padding: 0.125rem 0;
}

/* \u2500\u2500\u2500 Connector SVG Layer \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500 */
.workshop-connectors-layer { z-index: 8; pointer-events: none; }

.workshop-connector-path {
  fill: none;
  stroke: #6b7280;
  stroke-width: 2;
  pointer-events: stroke;
  cursor: pointer;
  transition: stroke 0.15s, stroke-width 0.15s;
}

.workshop-connector-path:hover,
.workshop-connector-path.hovered {
  stroke: #f2ca52;
  stroke-width: 3;
}

.workshop-connector-preview {
  pointer-events: none;
}

.connector-delete-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border-radius: 9999px;
  border: none;
  background: white;
  color: #6b7280;
  cursor: pointer;
  box-shadow: 0 1px 4px rgba(0,0,0,0.15);
  transition: all 0.15s;
  pointer-events: auto;
}

.connector-delete-btn:hover {
  background: #fecaca;
  color: #dc2626;
}

/* Connector Mode \u2014 visual feedback */
.connector-mode .workshop-note,
.connector-mode .workshop-text,
.connector-mode .workshop-section,
.connector-mode .workshop-shape {
  cursor: crosshair;
}

.connector-mode .drag-handle {
  cursor: crosshair;
}

.connector-source-selected {
  outline: 2px solid #f2ca52;
  outline-offset: 4px;
}

/* Active toolbar button */
.workshop-toolbar-btn.active {
  background: #f2ca52;
  color: #1a1a2e;
}

/* \u2500\u2500\u2500 Fullscreen (CSS class, not Fullscreen API) \u2500\u2500\u2500\u2500\u2500\u2500\u2500 */
.workshop-fullscreen {
  position: fixed !important;
  top: 0 !important;
  left: 0 !important;
  height: 100vh !important;
  height: 100dvh !important;
  max-height: none !important;
  width: 100vw !important;
  background: #eef0f4 !important;
  overflow: hidden !important;
  z-index: 99999 !important;
  margin: 0 !important;
  padding: 0 !important;
  border: none !important;
  border-radius: 0 !important;
}

/* \u2500\u2500\u2500 Touch \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500 */
@media (pointer: coarse) {
  .workshop-note .drag-handle { min-height: 2.5rem; }
  .note-delete { width: 1.75rem; height: 1.75rem; opacity: 1; }
  .resize-handle { width: 20px; height: 20px; opacity: 0.3; }
  .workshop-toolbar { bottom: 1rem; right: 1rem; }
  .workshop-toolbar-btn { padding: 0.625rem 1rem; }
  .workshop-text .text-drag-handle { opacity: 1; }
  .workshop-shape .shape-drag-handle { opacity: 1; }
}
`;function tr(){if(document.getElementById("platform-workshop-styles"))return;let C=document.createElement("style");C.id="platform-workshop-styles",C.textContent=Mn,document.head.appendChild(C)}function St(){let C=window.Alpine;C&&C.data("workshopBoard",Tt)}typeof document<"u"&&(tr(),document.addEventListener("livewire:init",St),document.readyState!=="loading"?setTimeout(St,0):document.addEventListener("DOMContentLoaded",St));return Jo(nr);})();
