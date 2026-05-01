/* platform-workshop v0.0.0 | MIT */
var PlatformWorkshop=(()=>{var Yo=Object.create;var Je=Object.defineProperty;var Uo=Object.getOwnPropertyDescriptor;var Xo=Object.getOwnPropertyNames;var Ko=Object.getPrototypeOf,Vo=Object.prototype.hasOwnProperty;var Wo=(N,j)=>()=>(j||N((j={exports:{}}).exports,j),j.exports),Zo=(N,j)=>{for(var Y in j)Je(N,Y,{get:j[Y],enumerable:!0})},In=(N,j,Y,a)=>{if(j&&typeof j=="object"||typeof j=="function")for(let c of Xo(j))!Vo.call(N,c)&&c!==Y&&Je(N,c,{get:()=>j[c],enumerable:!(a=Uo(j,c))||a.enumerable});return N};var Jo=(N,j,Y)=>(Y=N!=null?Yo(Ko(N)):{},In(j||!N||!N.__esModule?Je(Y,"default",{value:N,enumerable:!0}):Y,N)),Qo=N=>In(Je({},"__esModule",{value:!0}),N);var zn=Wo((Tt,ye)=>{(function(N,j){typeof Tt=="object"&&typeof ye<"u"?ye.exports=j():typeof define=="function"&&define.amd?define(j):(N=typeof globalThis<"u"?globalThis:N||self).interact=j()})(Tt,(function(){"use strict";function N(t,e){var n=Object.keys(t);if(Object.getOwnPropertySymbols){var o=Object.getOwnPropertySymbols(t);e&&(o=o.filter((function(r){return Object.getOwnPropertyDescriptor(t,r).enumerable}))),n.push.apply(n,o)}return n}function j(t){for(var e=1;e<arguments.length;e++){var n=arguments[e]!=null?arguments[e]:{};e%2?N(Object(n),!0).forEach((function(o){h(t,o,n[o])})):Object.getOwnPropertyDescriptors?Object.defineProperties(t,Object.getOwnPropertyDescriptors(n)):N(Object(n)).forEach((function(o){Object.defineProperty(t,o,Object.getOwnPropertyDescriptor(n,o))}))}return t}function Y(t){return Y=typeof Symbol=="function"&&typeof Symbol.iterator=="symbol"?function(e){return typeof e}:function(e){return e&&typeof Symbol=="function"&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},Y(t)}function a(t,e){if(!(t instanceof e))throw new TypeError("Cannot call a class as a function")}function c(t,e){for(var n=0;n<e.length;n++){var o=e[n];o.enumerable=o.enumerable||!1,o.configurable=!0,"value"in o&&(o.writable=!0),Object.defineProperty(t,L(o.key),o)}}function d(t,e,n){return e&&c(t.prototype,e),n&&c(t,n),Object.defineProperty(t,"prototype",{writable:!1}),t}function h(t,e,n){return(e=L(e))in t?Object.defineProperty(t,e,{value:n,enumerable:!0,configurable:!0,writable:!0}):t[e]=n,t}function f(t,e){if(typeof e!="function"&&e!==null)throw new TypeError("Super expression must either be null or a function");t.prototype=Object.create(e&&e.prototype,{constructor:{value:t,writable:!0,configurable:!0}}),Object.defineProperty(t,"prototype",{writable:!1}),e&&b(t,e)}function m(t){return m=Object.setPrototypeOf?Object.getPrototypeOf.bind():function(e){return e.__proto__||Object.getPrototypeOf(e)},m(t)}function b(t,e){return b=Object.setPrototypeOf?Object.setPrototypeOf.bind():function(n,o){return n.__proto__=o,n},b(t,e)}function w(t){if(t===void 0)throw new ReferenceError("this hasn't been initialised - super() hasn't been called");return t}function P(t){var e=(function(){if(typeof Reflect>"u"||!Reflect.construct||Reflect.construct.sham)return!1;if(typeof Proxy=="function")return!0;try{return Boolean.prototype.valueOf.call(Reflect.construct(Boolean,[],(function(){}))),!0}catch{return!1}})();return function(){var n,o=m(t);if(e){var r=m(this).constructor;n=Reflect.construct(o,arguments,r)}else n=o.apply(this,arguments);return(function(i,s){if(s&&(typeof s=="object"||typeof s=="function"))return s;if(s!==void 0)throw new TypeError("Derived constructors may only return object or undefined");return w(i)})(this,n)}}function C(){return C=typeof Reflect<"u"&&Reflect.get?Reflect.get.bind():function(t,e,n){var o=(function(i,s){for(;!Object.prototype.hasOwnProperty.call(i,s)&&(i=m(i))!==null;);return i})(t,e);if(o){var r=Object.getOwnPropertyDescriptor(o,e);return r.get?r.get.call(arguments.length<3?t:n):r.value}},C.apply(this,arguments)}function L(t){var e=(function(n,o){if(typeof n!="object"||n===null)return n;var r=n[Symbol.toPrimitive];if(r!==void 0){var i=r.call(n,o||"default");if(typeof i!="object")return i;throw new TypeError("@@toPrimitive must return a primitive value.")}return(o==="string"?String:Number)(n)})(t,"string");return typeof e=="symbol"?e:e+""}var q=function(t){return!(!t||!t.Window)&&t instanceof t.Window},R=void 0,G=void 0;function H(t){R=t;var e=t.document.createTextNode("");e.ownerDocument!==t.document&&typeof t.wrap=="function"&&t.wrap(e)===e&&(t=t.wrap(t)),G=t}function B(t){return q(t)?t:(t.ownerDocument||t).defaultView||G.window}typeof window<"u"&&window&&H(window);var U=function(t){return!!t&&Y(t)==="object"},Z=function(t){return typeof t=="function"},x={window:function(t){return t===G||q(t)},docFrag:function(t){return U(t)&&t.nodeType===11},object:U,func:Z,number:function(t){return typeof t=="number"},bool:function(t){return typeof t=="boolean"},string:function(t){return typeof t=="string"},element:function(t){if(!t||Y(t)!=="object")return!1;var e=B(t)||G;return/object|function/.test(typeof Element>"u"?"undefined":Y(Element))?t instanceof Element||t instanceof e.Element:t.nodeType===1&&typeof t.nodeName=="string"},plainObject:function(t){return U(t)&&!!t.constructor&&/function Object\b/.test(t.constructor.toString())},array:function(t){return U(t)&&t.length!==void 0&&Z(t.splice)}};function Ae(t){var e=t.interaction;if(e.prepared.name==="drag"){var n=e.prepared.axis;n==="x"?(e.coords.cur.page.y=e.coords.start.page.y,e.coords.cur.client.y=e.coords.start.client.y,e.coords.velocity.client.y=0,e.coords.velocity.page.y=0):n==="y"&&(e.coords.cur.page.x=e.coords.start.page.x,e.coords.cur.client.x=e.coords.start.client.x,e.coords.velocity.client.x=0,e.coords.velocity.page.x=0)}}function xe(t){var e=t.iEvent,n=t.interaction;if(n.prepared.name==="drag"){var o=n.prepared.axis;if(o==="x"||o==="y"){var r=o==="x"?"y":"x";e.page[r]=n.coords.start.page[r],e.client[r]=n.coords.start.client[r],e.delta[r]=0}}}var ie={id:"actions/drag",install:function(t){var e=t.actions,n=t.Interactable,o=t.defaults;n.prototype.draggable=ie.draggable,e.map.drag=ie,e.methodDict.drag="draggable",o.actions.drag=ie.defaults},listeners:{"interactions:before-action-move":Ae,"interactions:action-resume":Ae,"interactions:action-move":xe,"auto-start:check":function(t){var e=t.interaction,n=t.interactable,o=t.buttons,r=n.options.drag;if(r&&r.enabled&&(!e.pointerIsDown||!/mouse|pointer/.test(e.pointerType)||(o&n.options.drag.mouseButtons)!=0))return t.action={name:"drag",axis:r.lockAxis==="start"?r.startAxis:r.lockAxis},!1}},draggable:function(t){return x.object(t)?(this.options.drag.enabled=t.enabled!==!1,this.setPerAction("drag",t),this.setOnEvents("drag",t),/^(xy|x|y|start)$/.test(t.lockAxis)&&(this.options.drag.lockAxis=t.lockAxis),/^(xy|x|y)$/.test(t.startAxis)&&(this.options.drag.startAxis=t.startAxis),this):x.bool(t)?(this.options.drag.enabled=t,this):this.options.drag},beforeMove:Ae,move:xe,defaults:{startAxis:"xy",lockAxis:"xy"},getCursor:function(){return"move"},filterEventType:function(t){return t.search("drag")===0}},Mt=ie,Q={init:function(t){var e=t;Q.document=e.document,Q.DocumentFragment=e.DocumentFragment||we,Q.SVGElement=e.SVGElement||we,Q.SVGSVGElement=e.SVGSVGElement||we,Q.SVGElementInstance=e.SVGElementInstance||we,Q.Element=e.Element||we,Q.HTMLElement=e.HTMLElement||Q.Element,Q.Event=e.Event,Q.Touch=e.Touch||we,Q.PointerEvent=e.PointerEvent||e.MSPointerEvent},document:null,DocumentFragment:null,SVGElement:null,SVGSVGElement:null,SVGElementInstance:null,Element:null,HTMLElement:null,Event:null,Touch:null,PointerEvent:null};function we(){}var X=Q,ee={init:function(t){var e=X.Element,n=t.navigator||{};ee.supportsTouch="ontouchstart"in t||x.func(t.DocumentTouch)&&X.document instanceof t.DocumentTouch,ee.supportsPointerEvent=n.pointerEnabled!==!1&&!!X.PointerEvent,ee.isIOS=/iP(hone|od|ad)/.test(n.platform),ee.isIOS7=/iP(hone|od|ad)/.test(n.platform)&&/OS 7[^\d]/.test(n.appVersion),ee.isIe9=/MSIE 9/.test(n.userAgent),ee.isOperaMobile=n.appName==="Opera"&&ee.supportsTouch&&/Presto/.test(n.userAgent),ee.prefixedMatchesSelector="matches"in e.prototype?"matches":"webkitMatchesSelector"in e.prototype?"webkitMatchesSelector":"mozMatchesSelector"in e.prototype?"mozMatchesSelector":"oMatchesSelector"in e.prototype?"oMatchesSelector":"msMatchesSelector",ee.pEventTypes=ee.supportsPointerEvent?X.PointerEvent===t.MSPointerEvent?{up:"MSPointerUp",down:"MSPointerDown",over:"mouseover",out:"mouseout",move:"MSPointerMove",cancel:"MSPointerCancel"}:{up:"pointerup",down:"pointerdown",over:"pointerover",out:"pointerout",move:"pointermove",cancel:"pointercancel"}:null,ee.wheelEvent=X.document&&"onmousewheel"in X.document?"mousewheel":"wheel"},supportsTouch:null,supportsPointerEvent:null,isIOS7:null,isIOS:null,isIe9:null,isOperaMobile:null,prefixedMatchesSelector:null,pEventTypes:null,wheelEvent:null},ne=ee;function ge(t,e){if(t.contains)return t.contains(e);for(;e;){if(e===t)return!0;e=e.parentNode}return!1}function Dt(t,e){for(;x.element(t);){if(de(t,e))return t;t=ae(t)}return null}function ae(t){var e=t.parentNode;if(x.docFrag(e)){for(;(e=e.host)&&x.docFrag(e););return e}return e}function de(t,e){return G!==R&&(e=e.replace(/\/deep\//g," ")),t[ne.prefixedMatchesSelector](e)}var Qe=function(t){return t.parentNode||t.host};function Pt(t,e){for(var n,o=[],r=t;(n=Qe(r))&&r!==e&&n!==r.ownerDocument;)o.unshift(r),r=n;return o}function et(t,e,n){for(;x.element(t);){if(de(t,e))return!0;if((t=ae(t))===n)return de(t,e)}return!1}function Ct(t){return t.correspondingUseElement||t}function tt(t){var e=t instanceof X.SVGElement?t.getBoundingClientRect():t.getClientRects()[0];return e&&{left:e.left,right:e.right,top:e.top,bottom:e.bottom,width:e.width||e.right-e.left,height:e.height||e.bottom-e.top}}function nt(t){var e,n=tt(t);if(!ne.isIOS7&&n){var o={x:(e=(e=B(t))||G).scrollX||e.document.documentElement.scrollLeft,y:e.scrollY||e.document.documentElement.scrollTop};n.left+=o.x,n.right+=o.x,n.top+=o.y,n.bottom+=o.y}return n}function $t(t){for(var e=[];t;)e.push(t),t=ae(t);return e}function Ot(t){return!!x.string(t)&&(X.document.querySelector(t),!0)}function z(t,e){for(var n in e)t[n]=e[n];return t}function Lt(t,e,n){return t==="parent"?ae(n):t==="self"?e.getRect(n):Dt(n,t)}function Ee(t,e,n,o){var r=t;return x.string(r)?r=Lt(r,e,n):x.func(r)&&(r=r.apply(void 0,o)),x.element(r)&&(r=nt(r)),r}function Ne(t){return t&&{x:"x"in t?t.x:t.left,y:"y"in t?t.y:t.top}}function ot(t){return!t||"x"in t&&"y"in t||((t=z({},t)).x=t.left||0,t.y=t.top||0,t.width=t.width||(t.right||0)-t.x,t.height=t.height||(t.bottom||0)-t.y),t}function qe(t,e,n){t.left&&(e.left+=n.x),t.right&&(e.right+=n.x),t.top&&(e.top+=n.y),t.bottom&&(e.bottom+=n.y),e.width=e.right-e.left,e.height=e.bottom-e.top}function Se(t,e,n){var o=n&&t.options[n];return Ne(Ee(o&&o.origin||t.options.origin,t,e,[t&&e]))||{x:0,y:0}}function ve(t,e){var n=arguments.length>2&&arguments[2]!==void 0?arguments[2]:function(p){return!0},o=arguments.length>3?arguments[3]:void 0;if(o=o||{},x.string(t)&&t.search(" ")!==-1&&(t=At(t)),x.array(t))return t.forEach((function(p){return ve(p,e,n,o)})),o;if(x.object(t)&&(e=t,t=""),x.func(e)&&n(t))o[t]=o[t]||[],o[t].push(e);else if(x.array(e))for(var r=0,i=e;r<i.length;r++){var s=i[r];ve(t,s,n,o)}else if(x.object(e))for(var l in e)ve(At(l).map((function(p){return"".concat(t).concat(p)})),e[l],n,o);return o}function At(t){return t.trim().split(/ +/)}var Te=function(t,e){return Math.sqrt(t*t+e*e)},Pn=["webkit","moz"];function je(t,e){t.__set||(t.__set={});var n=function(r){if(Pn.some((function(i){return r.indexOf(i)===0})))return 1;typeof t[r]!="function"&&r!=="__set"&&Object.defineProperty(t,r,{get:function(){return r in t.__set?t.__set[r]:t.__set[r]=e[r]},set:function(i){t.__set[r]=i},configurable:!0})};for(var o in e)n(o);return t}function Fe(t,e){t.page=t.page||{},t.page.x=e.page.x,t.page.y=e.page.y,t.client=t.client||{},t.client.x=e.client.x,t.client.y=e.client.y,t.timeStamp=e.timeStamp}function Nt(t){t.page.x=0,t.page.y=0,t.client.x=0,t.client.y=0}function qt(t){return t instanceof X.Event||t instanceof X.Touch}function Re(t,e,n){return t=t||"page",(n=n||{}).x=e[t+"X"],n.y=e[t+"Y"],n}function jt(t,e){return e=e||{x:0,y:0},ne.isOperaMobile&&qt(t)?(Re("screen",t,e),e.x+=window.scrollX,e.y+=window.scrollY):Re("page",t,e),e}function Ie(t){return x.number(t.pointerId)?t.pointerId:t.identifier}function Cn(t,e,n){var o=e.length>1?Ft(e):e[0];jt(o,t.page),(function(r,i){i=i||{},ne.isOperaMobile&&qt(r)?Re("screen",r,i):Re("client",r,i)})(o,t.client),t.timeStamp=n}function rt(t){var e=[];return x.array(t)?(e[0]=t[0],e[1]=t[1]):t.type==="touchend"?t.touches.length===1?(e[0]=t.touches[0],e[1]=t.changedTouches[0]):t.touches.length===0&&(e[0]=t.changedTouches[0],e[1]=t.changedTouches[1]):(e[0]=t.touches[0],e[1]=t.touches[1]),e}function Ft(t){for(var e={pageX:0,pageY:0,clientX:0,clientY:0,screenX:0,screenY:0},n=0;n<t.length;n++){var o=t[n];for(var r in e)e[r]+=o[r]}for(var i in e)e[i]/=t.length;return e}function it(t){if(!t.length)return null;var e=rt(t),n=Math.min(e[0].pageX,e[1].pageX),o=Math.min(e[0].pageY,e[1].pageY),r=Math.max(e[0].pageX,e[1].pageX),i=Math.max(e[0].pageY,e[1].pageY);return{x:n,y:o,left:n,top:o,right:r,bottom:i,width:r-n,height:i-o}}function at(t,e){var n=e+"X",o=e+"Y",r=rt(t),i=r[0][n]-r[1][n],s=r[0][o]-r[1][o];return Te(i,s)}function st(t,e){var n=e+"X",o=e+"Y",r=rt(t),i=r[1][n]-r[0][n],s=r[1][o]-r[0][o];return 180*Math.atan2(s,i)/Math.PI}function Rt(t){return x.string(t.pointerType)?t.pointerType:x.number(t.pointerType)?[void 0,void 0,"touch","pen","mouse"][t.pointerType]:/touch/.test(t.type||"")||t instanceof X.Touch?"touch":"mouse"}function Gt(t){var e=x.func(t.composedPath)?t.composedPath():t.path;return[Ct(e?e[0]:t.target),Ct(t.currentTarget)]}var Ge=(function(){function t(e){a(this,t),this.immediatePropagationStopped=!1,this.propagationStopped=!1,this._interaction=e}return d(t,[{key:"preventDefault",value:function(){}},{key:"stopPropagation",value:function(){this.propagationStopped=!0}},{key:"stopImmediatePropagation",value:function(){this.immediatePropagationStopped=this.propagationStopped=!0}}]),t})();Object.defineProperty(Ge.prototype,"interaction",{get:function(){return this._interaction._proxy},set:function(){}});var Bt=function(t,e){for(var n=0;n<e.length;n++){var o=e[n];t.push(o)}return t},Ht=function(t){return Bt([],t)},ze=function(t,e){for(var n=0;n<t.length;n++)if(e(t[n],n,t))return n;return-1},Me=function(t,e){return t[ze(t,e)]},ke=(function(t){f(n,t);var e=P(n);function n(o,r,i){var s;a(this,n),(s=e.call(this,r._interaction)).dropzone=void 0,s.dragEvent=void 0,s.relatedTarget=void 0,s.draggable=void 0,s.propagationStopped=!1,s.immediatePropagationStopped=!1;var l=i==="dragleave"?o.prev:o.cur,p=l.element,g=l.dropzone;return s.type=i,s.target=p,s.currentTarget=p,s.dropzone=g,s.dragEvent=r,s.relatedTarget=r.target,s.draggable=r.interactable,s.timeStamp=r.timeStamp,s}return d(n,[{key:"reject",value:function(){var o=this,r=this._interaction.dropState;if(this.type==="dropactivate"||this.dropzone&&r.cur.dropzone===this.dropzone&&r.cur.element===this.target)if(r.prev.dropzone=this.dropzone,r.prev.element=this.target,r.rejected=!0,r.events.enter=null,this.stopImmediatePropagation(),this.type==="dropactivate"){var i=r.activeDrops,s=ze(i,(function(p){var g=p.dropzone,u=p.element;return g===o.dropzone&&u===o.target}));r.activeDrops.splice(s,1);var l=new n(r,this.dragEvent,"dropdeactivate");l.dropzone=this.dropzone,l.target=this.target,this.dropzone.fire(l)}else this.dropzone.fire(new n(r,this.dragEvent,"dragleave"))}},{key:"preventDefault",value:function(){}},{key:"stopPropagation",value:function(){this.propagationStopped=!0}},{key:"stopImmediatePropagation",value:function(){this.immediatePropagationStopped=this.propagationStopped=!0}}]),n})(Ge);function Yt(t,e){for(var n=0,o=t.slice();n<o.length;n++){var r=o[n],i=r.dropzone,s=r.element;e.dropzone=i,e.target=s,i.fire(e),e.propagationStopped=e.immediatePropagationStopped=!1}}function ct(t,e){for(var n=(function(i,s){for(var l=[],p=0,g=i.interactables.list;p<g.length;p++){var u=g[p];if(u.options.drop.enabled){var v=u.options.drop.accept;if(!(x.element(v)&&v!==s||x.string(v)&&!de(s,v)||x.func(v)&&!v({dropzone:u,draggableElement:s})))for(var y=0,_=u.getAllElements();y<_.length;y++){var k=_[y];k!==s&&l.push({dropzone:u,element:k,rect:u.getRect(k)})}}}return l})(t,e),o=0;o<n.length;o++){var r=n[o];r.rect=r.dropzone.getRect(r.element)}return n}function Ut(t,e,n){for(var o=t.dropState,r=t.interactable,i=t.element,s=[],l=0,p=o.activeDrops;l<p.length;l++){var g=p[l],u=g.dropzone,v=g.element,y=g.rect,_=u.dropCheck(e,n,r,i,v,y);s.push(_?v:null)}var k=(function(E){for(var T,S,I,$=[],A=0;A<E.length;A++){var M=E[A],O=E[T];if(M&&A!==T)if(O){var V=Qe(M),F=Qe(O);if(V!==M.ownerDocument)if(F!==M.ownerDocument)if(V!==F){$=$.length?$:Pt(O);var J=void 0;if(O instanceof X.HTMLElement&&M instanceof X.SVGElement&&!(M instanceof X.SVGSVGElement)){if(M===F)continue;J=M.ownerSVGElement}else J=M;for(var oe=Pt(J,O.ownerDocument),le=0;oe[le]&&oe[le]===$[le];)le++;var Ze=[oe[le-1],oe[le],$[le]];if(Ze[0])for(var Le=Ze[0].lastChild;Le;){if(Le===Ze[1]){T=A,$=oe;break}if(Le===Ze[2])break;Le=Le.previousSibling}}else I=O,(parseInt(B(S=M).getComputedStyle(S).zIndex,10)||0)>=(parseInt(B(I).getComputedStyle(I).zIndex,10)||0)&&(T=A);else T=A}else T=A}return T})(s);return o.activeDrops[k]||null}function lt(t,e,n){var o=t.dropState,r={enter:null,leave:null,activate:null,deactivate:null,move:null,drop:null};return n.type==="dragstart"&&(r.activate=new ke(o,n,"dropactivate"),r.activate.target=null,r.activate.dropzone=null),n.type==="dragend"&&(r.deactivate=new ke(o,n,"dropdeactivate"),r.deactivate.target=null,r.deactivate.dropzone=null),o.rejected||(o.cur.element!==o.prev.element&&(o.prev.dropzone&&(r.leave=new ke(o,n,"dragleave"),n.dragLeave=r.leave.target=o.prev.element,n.prevDropzone=r.leave.dropzone=o.prev.dropzone),o.cur.dropzone&&(r.enter=new ke(o,n,"dragenter"),n.dragEnter=o.cur.element,n.dropzone=o.cur.dropzone)),n.type==="dragend"&&o.cur.dropzone&&(r.drop=new ke(o,n,"drop"),n.dropzone=o.cur.dropzone,n.relatedTarget=o.cur.element),n.type==="dragmove"&&o.cur.dropzone&&(r.move=new ke(o,n,"dropmove"),n.dropzone=o.cur.dropzone)),r}function dt(t,e){var n=t.dropState,o=n.activeDrops,r=n.cur,i=n.prev;e.leave&&i.dropzone.fire(e.leave),e.enter&&r.dropzone.fire(e.enter),e.move&&r.dropzone.fire(e.move),e.drop&&r.dropzone.fire(e.drop),e.deactivate&&Yt(o,e.deactivate),n.prev.dropzone=r.dropzone,n.prev.element=r.element}function Xt(t,e){var n=t.interaction,o=t.iEvent,r=t.event;if(o.type==="dragmove"||o.type==="dragend"){var i=n.dropState;e.dynamicDrop&&(i.activeDrops=ct(e,n.element));var s=o,l=Ut(n,s,r);i.rejected=i.rejected&&!!l&&l.dropzone===i.cur.dropzone&&l.element===i.cur.element,i.cur.dropzone=l&&l.dropzone,i.cur.element=l&&l.element,i.events=lt(n,0,s)}}var Kt={id:"actions/drop",install:function(t){var e=t.actions,n=t.interactStatic,o=t.Interactable,r=t.defaults;t.usePlugin(Mt),o.prototype.dropzone=function(i){return(function(s,l){if(x.object(l)){if(s.options.drop.enabled=l.enabled!==!1,l.listeners){var p=ve(l.listeners),g=Object.keys(p).reduce((function(v,y){return v[/^(enter|leave)/.test(y)?"drag".concat(y):/^(activate|deactivate|move)/.test(y)?"drop".concat(y):y]=p[y],v}),{}),u=s.options.drop.listeners;u&&s.off(u),s.on(g),s.options.drop.listeners=g}return x.func(l.ondrop)&&s.on("drop",l.ondrop),x.func(l.ondropactivate)&&s.on("dropactivate",l.ondropactivate),x.func(l.ondropdeactivate)&&s.on("dropdeactivate",l.ondropdeactivate),x.func(l.ondragenter)&&s.on("dragenter",l.ondragenter),x.func(l.ondragleave)&&s.on("dragleave",l.ondragleave),x.func(l.ondropmove)&&s.on("dropmove",l.ondropmove),/^(pointer|center)$/.test(l.overlap)?s.options.drop.overlap=l.overlap:x.number(l.overlap)&&(s.options.drop.overlap=Math.max(Math.min(1,l.overlap),0)),"accept"in l&&(s.options.drop.accept=l.accept),"checker"in l&&(s.options.drop.checker=l.checker),s}return x.bool(l)?(s.options.drop.enabled=l,s):s.options.drop})(this,i)},o.prototype.dropCheck=function(i,s,l,p,g,u){return(function(v,y,_,k,E,T,S){var I=!1;if(!(S=S||v.getRect(T)))return!!v.options.drop.checker&&v.options.drop.checker(y,_,I,v,T,k,E);var $=v.options.drop.overlap;if($==="pointer"){var A=Se(k,E,"drag"),M=jt(y);M.x+=A.x,M.y+=A.y;var O=M.x>S.left&&M.x<S.right,V=M.y>S.top&&M.y<S.bottom;I=O&&V}var F=k.getRect(E);if(F&&$==="center"){var J=F.left+F.width/2,oe=F.top+F.height/2;I=J>=S.left&&J<=S.right&&oe>=S.top&&oe<=S.bottom}return F&&x.number($)&&(I=Math.max(0,Math.min(S.right,F.right)-Math.max(S.left,F.left))*Math.max(0,Math.min(S.bottom,F.bottom)-Math.max(S.top,F.top))/(F.width*F.height)>=$),v.options.drop.checker&&(I=v.options.drop.checker(y,_,I,v,T,k,E)),I})(this,i,s,l,p,g,u)},n.dynamicDrop=function(i){return x.bool(i)?(t.dynamicDrop=i,n):t.dynamicDrop},z(e.phaselessTypes,{dragenter:!0,dragleave:!0,dropactivate:!0,dropdeactivate:!0,dropmove:!0,drop:!0}),e.methodDict.drop="dropzone",t.dynamicDrop=!1,r.actions.drop=Kt.defaults},listeners:{"interactions:before-action-start":function(t){var e=t.interaction;e.prepared.name==="drag"&&(e.dropState={cur:{dropzone:null,element:null},prev:{dropzone:null,element:null},rejected:null,events:null,activeDrops:[]})},"interactions:after-action-start":function(t,e){var n=t.interaction,o=(t.event,t.iEvent);if(n.prepared.name==="drag"){var r=n.dropState;r.activeDrops=[],r.events={},r.activeDrops=ct(e,n.element),r.events=lt(n,0,o),r.events.activate&&(Yt(r.activeDrops,r.events.activate),e.fire("actions/drop:start",{interaction:n,dragEvent:o}))}},"interactions:action-move":Xt,"interactions:after-action-move":function(t,e){var n=t.interaction,o=t.iEvent;if(n.prepared.name==="drag"){var r=n.dropState;dt(n,r.events),e.fire("actions/drop:move",{interaction:n,dragEvent:o}),r.events={}}},"interactions:action-end":function(t,e){if(t.interaction.prepared.name==="drag"){var n=t.interaction,o=t.iEvent;Xt(t,e),dt(n,n.dropState.events),e.fire("actions/drop:end",{interaction:n,dragEvent:o})}},"interactions:stop":function(t){var e=t.interaction;if(e.prepared.name==="drag"){var n=e.dropState;n&&(n.activeDrops=null,n.events=null,n.cur.dropzone=null,n.cur.element=null,n.prev.dropzone=null,n.prev.element=null,n.rejected=!1)}}},getActiveDrops:ct,getDrop:Ut,getDropEvents:lt,fireDropEvents:dt,filterEventType:function(t){return t.search("drag")===0||t.search("drop")===0},defaults:{enabled:!1,accept:null,overlap:"pointer"}},$n=Kt;function pt(t){var e=t.interaction,n=t.iEvent,o=t.phase;if(e.prepared.name==="gesture"){var r=e.pointers.map((function(g){return g.pointer})),i=o==="start",s=o==="end",l=e.interactable.options.deltaSource;if(n.touches=[r[0],r[1]],i)n.distance=at(r,l),n.box=it(r),n.scale=1,n.ds=0,n.angle=st(r,l),n.da=0,e.gesture.startDistance=n.distance,e.gesture.startAngle=n.angle;else if(s||e.pointers.length<2){var p=e.prevEvent;n.distance=p.distance,n.box=p.box,n.scale=p.scale,n.ds=0,n.angle=p.angle,n.da=0}else n.distance=at(r,l),n.box=it(r),n.scale=n.distance/e.gesture.startDistance,n.angle=st(r,l),n.ds=n.scale-e.gesture.scale,n.da=n.angle-e.gesture.angle;e.gesture.distance=n.distance,e.gesture.angle=n.angle,x.number(n.scale)&&n.scale!==1/0&&!isNaN(n.scale)&&(e.gesture.scale=n.scale)}}var ut={id:"actions/gesture",before:["actions/drag","actions/resize"],install:function(t){var e=t.actions,n=t.Interactable,o=t.defaults;n.prototype.gesturable=function(r){return x.object(r)?(this.options.gesture.enabled=r.enabled!==!1,this.setPerAction("gesture",r),this.setOnEvents("gesture",r),this):x.bool(r)?(this.options.gesture.enabled=r,this):this.options.gesture},e.map.gesture=ut,e.methodDict.gesture="gesturable",o.actions.gesture=ut.defaults},listeners:{"interactions:action-start":pt,"interactions:action-move":pt,"interactions:action-end":pt,"interactions:new":function(t){t.interaction.gesture={angle:0,distance:0,scale:1,startAngle:0,startDistance:0}},"auto-start:check":function(t){if(!(t.interaction.pointers.length<2)){var e=t.interactable.options.gesture;if(e&&e.enabled)return t.action={name:"gesture"},!1}}},defaults:{},getCursor:function(){return""},filterEventType:function(t){return t.search("gesture")===0}},On=ut;function Ln(t,e,n,o,r,i,s){if(!e)return!1;if(e===!0){var l=x.number(i.width)?i.width:i.right-i.left,p=x.number(i.height)?i.height:i.bottom-i.top;if(s=Math.min(s,Math.abs((t==="left"||t==="right"?l:p)/2)),l<0&&(t==="left"?t="right":t==="right"&&(t="left")),p<0&&(t==="top"?t="bottom":t==="bottom"&&(t="top")),t==="left"){var g=l>=0?i.left:i.right;return n.x<g+s}if(t==="top"){var u=p>=0?i.top:i.bottom;return n.y<u+s}if(t==="right")return n.x>(l>=0?i.right:i.left)-s;if(t==="bottom")return n.y>(p>=0?i.bottom:i.top)-s}return!!x.element(o)&&(x.element(e)?e===o:et(o,e,r))}function Vt(t){var e=t.iEvent,n=t.interaction;if(n.prepared.name==="resize"&&n.resizeAxes){var o=e;n.interactable.options.resize.square?(n.resizeAxes==="y"?o.delta.x=o.delta.y:o.delta.y=o.delta.x,o.axes="xy"):(o.axes=n.resizeAxes,n.resizeAxes==="x"?o.delta.y=0:n.resizeAxes==="y"&&(o.delta.x=0))}}var se,me,ce={id:"actions/resize",before:["actions/drag"],install:function(t){var e=t.actions,n=t.browser,o=t.Interactable,r=t.defaults;ce.cursors=(function(i){return i.isIe9?{x:"e-resize",y:"s-resize",xy:"se-resize",top:"n-resize",left:"w-resize",bottom:"s-resize",right:"e-resize",topleft:"se-resize",bottomright:"se-resize",topright:"ne-resize",bottomleft:"ne-resize"}:{x:"ew-resize",y:"ns-resize",xy:"nwse-resize",top:"ns-resize",left:"ew-resize",bottom:"ns-resize",right:"ew-resize",topleft:"nwse-resize",bottomright:"nwse-resize",topright:"nesw-resize",bottomleft:"nesw-resize"}})(n),ce.defaultMargin=n.supportsTouch||n.supportsPointerEvent?20:10,o.prototype.resizable=function(i){return(function(s,l,p){return x.object(l)?(s.options.resize.enabled=l.enabled!==!1,s.setPerAction("resize",l),s.setOnEvents("resize",l),x.string(l.axis)&&/^x$|^y$|^xy$/.test(l.axis)?s.options.resize.axis=l.axis:l.axis===null&&(s.options.resize.axis=p.defaults.actions.resize.axis),x.bool(l.preserveAspectRatio)?s.options.resize.preserveAspectRatio=l.preserveAspectRatio:x.bool(l.square)&&(s.options.resize.square=l.square),s):x.bool(l)?(s.options.resize.enabled=l,s):s.options.resize})(this,i,t)},e.map.resize=ce,e.methodDict.resize="resizable",r.actions.resize=ce.defaults},listeners:{"interactions:new":function(t){t.interaction.resizeAxes="xy"},"interactions:action-start":function(t){(function(e){var n=e.iEvent,o=e.interaction;if(o.prepared.name==="resize"&&o.prepared.edges){var r=n,i=o.rect;o._rects={start:z({},i),corrected:z({},i),previous:z({},i),delta:{left:0,right:0,width:0,top:0,bottom:0,height:0}},r.edges=o.prepared.edges,r.rect=o._rects.corrected,r.deltaRect=o._rects.delta}})(t),Vt(t)},"interactions:action-move":function(t){(function(e){var n=e.iEvent,o=e.interaction;if(o.prepared.name==="resize"&&o.prepared.edges){var r=n,i=o.interactable.options.resize.invert,s=i==="reposition"||i==="negate",l=o.rect,p=o._rects,g=p.start,u=p.corrected,v=p.delta,y=p.previous;if(z(y,u),s){if(z(u,l),i==="reposition"){if(u.top>u.bottom){var _=u.top;u.top=u.bottom,u.bottom=_}if(u.left>u.right){var k=u.left;u.left=u.right,u.right=k}}}else u.top=Math.min(l.top,g.bottom),u.bottom=Math.max(l.bottom,g.top),u.left=Math.min(l.left,g.right),u.right=Math.max(l.right,g.left);for(var E in u.width=u.right-u.left,u.height=u.bottom-u.top,u)v[E]=u[E]-y[E];r.edges=o.prepared.edges,r.rect=u,r.deltaRect=v}})(t),Vt(t)},"interactions:action-end":function(t){var e=t.iEvent,n=t.interaction;if(n.prepared.name==="resize"&&n.prepared.edges){var o=e;o.edges=n.prepared.edges,o.rect=n._rects.corrected,o.deltaRect=n._rects.delta}},"auto-start:check":function(t){var e=t.interaction,n=t.interactable,o=t.element,r=t.rect,i=t.buttons;if(r){var s=z({},e.coords.cur.page),l=n.options.resize;if(l&&l.enabled&&(!e.pointerIsDown||!/mouse|pointer/.test(e.pointerType)||(i&l.mouseButtons)!=0)){if(x.object(l.edges)){var p={left:!1,right:!1,top:!1,bottom:!1};for(var g in p)p[g]=Ln(g,l.edges[g],s,e._latestPointer.eventTarget,o,r,l.margin||ce.defaultMargin);p.left=p.left&&!p.right,p.top=p.top&&!p.bottom,(p.left||p.right||p.top||p.bottom)&&(t.action={name:"resize",edges:p})}else{var u=l.axis!=="y"&&s.x>r.right-ce.defaultMargin,v=l.axis!=="x"&&s.y>r.bottom-ce.defaultMargin;(u||v)&&(t.action={name:"resize",axes:(u?"x":"")+(v?"y":"")})}return!t.action&&void 0}}}},defaults:{square:!1,preserveAspectRatio:!1,axis:"xy",margin:NaN,edges:null,invert:"none"},cursors:null,getCursor:function(t){var e=t.edges,n=t.axis,o=t.name,r=ce.cursors,i=null;if(n)i=r[o+n];else if(e){for(var s="",l=0,p=["top","bottom","left","right"];l<p.length;l++){var g=p[l];e[g]&&(s+=g)}i=r[s]}return i},filterEventType:function(t){return t.search("resize")===0},defaultMargin:null},An=ce,Nn={id:"actions",install:function(t){t.usePlugin(On),t.usePlugin(An),t.usePlugin(Mt),t.usePlugin($n)}},Wt=0,pe={request:function(t){return se(t)},cancel:function(t){return me(t)},init:function(t){if(se=t.requestAnimationFrame,me=t.cancelAnimationFrame,!se)for(var e=["ms","moz","webkit","o"],n=0;n<e.length;n++){var o=e[n];se=t["".concat(o,"RequestAnimationFrame")],me=t["".concat(o,"CancelAnimationFrame")]||t["".concat(o,"CancelRequestAnimationFrame")]}se=se&&se.bind(t),me=me&&me.bind(t),se||(se=function(r){var i=Date.now(),s=Math.max(0,16-(i-Wt)),l=t.setTimeout((function(){r(i+s)}),s);return Wt=i+s,l},me=function(r){return clearTimeout(r)})}},D={defaults:{enabled:!1,margin:60,container:null,speed:300},now:Date.now,interaction:null,i:0,x:0,y:0,isScrolling:!1,prevTime:0,margin:0,speed:0,start:function(t){D.isScrolling=!0,pe.cancel(D.i),t.autoScroll=D,D.interaction=t,D.prevTime=D.now(),D.i=pe.request(D.scroll)},stop:function(){D.isScrolling=!1,D.interaction&&(D.interaction.autoScroll=null),pe.cancel(D.i)},scroll:function(){var t=D.interaction,e=t.interactable,n=t.element,o=t.prepared.name,r=e.options[o].autoScroll,i=Zt(r.container,e,n),s=D.now(),l=(s-D.prevTime)/1e3,p=r.speed*l;if(p>=1){var g={x:D.x*p,y:D.y*p};if(g.x||g.y){var u=Jt(i);x.window(i)?i.scrollBy(g.x,g.y):i&&(i.scrollLeft+=g.x,i.scrollTop+=g.y);var v=Jt(i),y={x:v.x-u.x,y:v.y-u.y};(y.x||y.y)&&e.fire({type:"autoscroll",target:n,interactable:e,delta:y,interaction:t,container:i})}D.prevTime=s}D.isScrolling&&(pe.cancel(D.i),D.i=pe.request(D.scroll))},check:function(t,e){var n;return(n=t.options[e].autoScroll)==null?void 0:n.enabled},onInteractionMove:function(t){var e=t.interaction,n=t.pointer;if(e.interacting()&&D.check(e.interactable,e.prepared.name))if(e.simulation)D.x=D.y=0;else{var o,r,i,s,l=e.interactable,p=e.element,g=e.prepared.name,u=l.options[g].autoScroll,v=Zt(u.container,l,p);if(x.window(v))s=n.clientX<D.margin,o=n.clientY<D.margin,r=n.clientX>v.innerWidth-D.margin,i=n.clientY>v.innerHeight-D.margin;else{var y=tt(v);s=n.clientX<y.left+D.margin,o=n.clientY<y.top+D.margin,r=n.clientX>y.right-D.margin,i=n.clientY>y.bottom-D.margin}D.x=r?1:s?-1:0,D.y=i?1:o?-1:0,D.isScrolling||(D.margin=u.margin,D.speed=u.speed,D.start(e))}}};function Zt(t,e,n){return(x.string(t)?Lt(t,e,n):t)||B(n)}function Jt(t){return x.window(t)&&(t=window.document.body),{x:t.scrollLeft,y:t.scrollTop}}var qn={id:"auto-scroll",install:function(t){var e=t.defaults,n=t.actions;t.autoScroll=D,D.now=function(){return t.now()},n.phaselessTypes.autoscroll=!0,e.perAction.autoScroll=D.defaults},listeners:{"interactions:new":function(t){t.interaction.autoScroll=null},"interactions:destroy":function(t){t.interaction.autoScroll=null,D.stop(),D.interaction&&(D.interaction=null)},"interactions:stop":D.stop,"interactions:action-move":function(t){return D.onInteractionMove(t)}}},jn=qn;function De(t,e){var n=!1;return function(){return n||(G.console.warn(e),n=!0),t.apply(this,arguments)}}function ht(t,e){return t.name=e.name,t.axis=e.axis,t.edges=e.edges,t}function Fn(t){return x.bool(t)?(this.options.styleCursor=t,this):t===null?(delete this.options.styleCursor,this):this.options.styleCursor}function Rn(t){return x.func(t)?(this.options.actionChecker=t,this):t===null?(delete this.options.actionChecker,this):this.options.actionChecker}var Gn={id:"auto-start/interactableMethods",install:function(t){var e=t.Interactable;e.prototype.getAction=function(n,o,r,i){var s=(function(l,p,g,u,v){var y=l.getRect(u),_=p.buttons||{0:1,1:4,3:8,4:16}[p.button],k={action:null,interactable:l,interaction:g,element:u,rect:y,buttons:_};return v.fire("auto-start:check",k),k.action})(this,o,r,i,t);return this.options.actionChecker?this.options.actionChecker(n,o,s,this,i,r):s},e.prototype.ignoreFrom=De((function(n){return this._backCompatOption("ignoreFrom",n)}),"Interactable.ignoreFrom() has been deprecated. Use Interactble.draggable({ignoreFrom: newValue})."),e.prototype.allowFrom=De((function(n){return this._backCompatOption("allowFrom",n)}),"Interactable.allowFrom() has been deprecated. Use Interactble.draggable({allowFrom: newValue})."),e.prototype.actionChecker=Rn,e.prototype.styleCursor=Fn}};function Qt(t,e,n,o,r){return e.testIgnoreAllow(e.options[t.name],n,o)&&e.options[t.name].enabled&&Be(e,n,t,r)?t:null}function Bn(t,e,n,o,r,i,s){for(var l=0,p=o.length;l<p;l++){var g=o[l],u=r[l],v=g.getAction(e,n,t,u);if(v){var y=Qt(v,g,u,i,s);if(y)return{action:y,interactable:g,element:u}}}return{action:null,interactable:null,element:null}}function en(t,e,n,o,r){var i=[],s=[],l=o;function p(u){i.push(u),s.push(l)}for(;x.element(l);){i=[],s=[],r.interactables.forEachMatch(l,p);var g=Bn(t,e,n,i,s,o,r);if(g.action&&!g.interactable.options[g.action.name].manualStart)return g;l=ae(l)}return{action:null,interactable:null,element:null}}function tn(t,e,n){var o=e.action,r=e.interactable,i=e.element;o=o||{name:null},t.interactable=r,t.element=i,ht(t.prepared,o),t.rect=r&&o.name?r.getRect(i):null,on(t,n),n.fire("autoStart:prepared",{interaction:t})}function Be(t,e,n,o){var r=t.options,i=r[n.name].max,s=r[n.name].maxPerElement,l=o.autoStart.maxInteractions,p=0,g=0,u=0;if(!(i&&s&&l))return!1;for(var v=0,y=o.interactions.list;v<y.length;v++){var _=y[v],k=_.prepared.name;if(_.interacting()&&(++p>=l||_.interactable===t&&((g+=k===n.name?1:0)>=i||_.element===e&&(u++,k===n.name&&u>=s))))return!1}return l>0}function nn(t,e){return x.number(t)?(e.autoStart.maxInteractions=t,this):e.autoStart.maxInteractions}function ft(t,e,n){var o=n.autoStart.cursorElement;o&&o!==t&&(o.style.cursor=""),t.ownerDocument.documentElement.style.cursor=e,t.style.cursor=e,n.autoStart.cursorElement=e?t:null}function on(t,e){var n=t.interactable,o=t.element,r=t.prepared;if(t.pointerType==="mouse"&&n&&n.options.styleCursor){var i="";if(r.name){var s=n.options[r.name].cursorChecker;i=x.func(s)?s(r,n,o,t._interacting):e.actions.map[r.name].getCursor(r)}ft(t.element,i||"",e)}else e.autoStart.cursorElement&&ft(e.autoStart.cursorElement,"",e)}var Hn={id:"auto-start/base",before:["actions"],install:function(t){var e=t.interactStatic,n=t.defaults;t.usePlugin(Gn),n.base.actionChecker=null,n.base.styleCursor=!0,z(n.perAction,{manualStart:!1,max:1/0,maxPerElement:1,allowFrom:null,ignoreFrom:null,mouseButtons:1}),e.maxInteractions=function(o){return nn(o,t)},t.autoStart={maxInteractions:1/0,withinInteractionLimit:Be,cursorElement:null}},listeners:{"interactions:down":function(t,e){var n=t.interaction,o=t.pointer,r=t.event,i=t.eventTarget;n.interacting()||tn(n,en(n,o,r,i,e),e)},"interactions:move":function(t,e){(function(n,o){var r=n.interaction,i=n.pointer,s=n.event,l=n.eventTarget;r.pointerType!=="mouse"||r.pointerIsDown||r.interacting()||tn(r,en(r,i,s,l,o),o)})(t,e),(function(n,o){var r=n.interaction;if(r.pointerIsDown&&!r.interacting()&&r.pointerWasMoved&&r.prepared.name){o.fire("autoStart:before-start",n);var i=r.interactable,s=r.prepared.name;s&&i&&(i.options[s].manualStart||!Be(i,r.element,r.prepared,o)?r.stop():(r.start(r.prepared,i,r.element),on(r,o)))}})(t,e)},"interactions:stop":function(t,e){var n=t.interaction,o=n.interactable;o&&o.options.styleCursor&&ft(n.element,"",e)}},maxInteractions:nn,withinInteractionLimit:Be,validateAction:Qt},gt=Hn,Yn={id:"auto-start/dragAxis",listeners:{"autoStart:before-start":function(t,e){var n=t.interaction,o=t.eventTarget,r=t.dx,i=t.dy;if(n.prepared.name==="drag"){var s=Math.abs(r),l=Math.abs(i),p=n.interactable.options.drag,g=p.startAxis,u=s>l?"x":s<l?"y":"xy";if(n.prepared.axis=p.lockAxis==="start"?u[0]:p.lockAxis,u!=="xy"&&g!=="xy"&&g!==u){n.prepared.name=null;for(var v=o,y=function(k){if(k!==n.interactable){var E=n.interactable.options.drag;if(!E.manualStart&&k.testIgnoreAllow(E,v,o)){var T=k.getAction(n.downPointer,n.downEvent,n,v);if(T&&T.name==="drag"&&(function(S,I){if(!I)return!1;var $=I.options.drag.startAxis;return S==="xy"||$==="xy"||$===S})(u,k)&&gt.validateAction(T,k,v,o,e))return k}}};x.element(v);){var _=e.interactables.forEachMatch(v,y);if(_){n.prepared.name="drag",n.interactable=_,n.element=v;break}v=ae(v)}}}}}};function vt(t){var e=t.prepared&&t.prepared.name;if(!e)return null;var n=t.interactable.options;return n[e].hold||n[e].delay}var Un={id:"auto-start/hold",install:function(t){var e=t.defaults;t.usePlugin(gt),e.perAction.hold=0,e.perAction.delay=0},listeners:{"interactions:new":function(t){t.interaction.autoStartHoldTimer=null},"autoStart:prepared":function(t){var e=t.interaction,n=vt(e);n>0&&(e.autoStartHoldTimer=setTimeout((function(){e.start(e.prepared,e.interactable,e.element)}),n))},"interactions:move":function(t){var e=t.interaction,n=t.duplicate;e.autoStartHoldTimer&&e.pointerWasMoved&&!n&&(clearTimeout(e.autoStartHoldTimer),e.autoStartHoldTimer=null)},"autoStart:before-start":function(t){var e=t.interaction;vt(e)>0&&(e.prepared.name=null)}},getHoldDuration:vt},Xn=Un,Kn={id:"auto-start",install:function(t){t.usePlugin(gt),t.usePlugin(Xn),t.usePlugin(Yn)}},Vn=function(t){return/^(always|never|auto)$/.test(t)?(this.options.preventDefault=t,this):x.bool(t)?(this.options.preventDefault=t?"always":"never",this):this.options.preventDefault};function Wn(t){var e=t.interaction,n=t.event;e.interactable&&e.interactable.checkAndPreventDefault(n)}var rn={id:"core/interactablePreventDefault",install:function(t){var e=t.Interactable;e.prototype.preventDefault=Vn,e.prototype.checkAndPreventDefault=function(n){return(function(o,r,i){var s=o.options.preventDefault;if(s!=="never")if(s!=="always"){if(r.events.supportsPassive&&/^touch(start|move)$/.test(i.type)){var l=B(i.target).document,p=r.getDocOptions(l);if(!p||!p.events||p.events.passive!==!1)return}/^(mouse|pointer|touch)*(down|start)/i.test(i.type)||x.element(i.target)&&de(i.target,"input,select,textarea,[contenteditable=true],[contenteditable=true] *")||i.preventDefault()}else i.preventDefault()})(this,t,n)},t.interactions.docEvents.push({type:"dragstart",listener:function(n){for(var o=0,r=t.interactions.list;o<r.length;o++){var i=r[o];if(i.element&&(i.element===n.target||ge(i.element,n.target)))return void i.interactable.checkAndPreventDefault(n)}}})},listeners:["down","move","up","cancel"].reduce((function(t,e){return t["interactions:".concat(e)]=Wn,t}),{})};function He(t,e){if(e.phaselessTypes[t])return!0;for(var n in e.map)if(t.indexOf(n)===0&&t.substr(n.length)in e.phases)return!0;return!1}function _e(t){var e={};for(var n in t){var o=t[n];x.plainObject(o)?e[n]=_e(o):x.array(o)?e[n]=Ht(o):e[n]=o}return e}var mt=(function(){function t(e){a(this,t),this.states=[],this.startOffset={left:0,right:0,top:0,bottom:0},this.startDelta=void 0,this.result=void 0,this.endResult=void 0,this.startEdges=void 0,this.edges=void 0,this.interaction=void 0,this.interaction=e,this.result=Ye(),this.edges={left:!1,right:!1,top:!1,bottom:!1}}return d(t,[{key:"start",value:function(e,n){var o,r,i=e.phase,s=this.interaction,l=(function(g){var u=g.interactable.options[g.prepared.name],v=u.modifiers;return v&&v.length?v:["snap","snapSize","snapEdges","restrict","restrictEdges","restrictSize"].map((function(y){var _=u[y];return _&&_.enabled&&{options:_,methods:_._methods}})).filter((function(y){return!!y}))})(s);this.prepareStates(l),this.startEdges=z({},s.edges),this.edges=z({},this.startEdges),this.startOffset=(o=s.rect,r=n,o?{left:r.x-o.left,top:r.y-o.top,right:o.right-r.x,bottom:o.bottom-r.y}:{left:0,top:0,right:0,bottom:0}),this.startDelta={x:0,y:0};var p=this.fillArg({phase:i,pageCoords:n,preEnd:!1});return this.result=Ye(),this.startAll(p),this.result=this.setAll(p)}},{key:"fillArg",value:function(e){var n=this.interaction;return e.interaction=n,e.interactable=n.interactable,e.element=n.element,e.rect||(e.rect=n.rect),e.edges||(e.edges=this.startEdges),e.startOffset=this.startOffset,e}},{key:"startAll",value:function(e){for(var n=0,o=this.states;n<o.length;n++){var r=o[n];r.methods.start&&(e.state=r,r.methods.start(e))}}},{key:"setAll",value:function(e){var n=e.phase,o=e.preEnd,r=e.skipModifiers,i=e.rect,s=e.edges;e.coords=z({},e.pageCoords),e.rect=z({},i),e.edges=z({},s);for(var l=r?this.states.slice(r):this.states,p=Ye(e.coords,e.rect),g=0;g<l.length;g++){var u,v=l[g],y=v.options,_=z({},e.coords),k=null;(u=v.methods)!=null&&u.set&&this.shouldDo(y,o,n)&&(e.state=v,k=v.methods.set(e),qe(e.edges,e.rect,{x:e.coords.x-_.x,y:e.coords.y-_.y})),p.eventProps.push(k)}z(this.edges,e.edges),p.delta.x=e.coords.x-e.pageCoords.x,p.delta.y=e.coords.y-e.pageCoords.y,p.rectDelta.left=e.rect.left-i.left,p.rectDelta.right=e.rect.right-i.right,p.rectDelta.top=e.rect.top-i.top,p.rectDelta.bottom=e.rect.bottom-i.bottom;var E=this.result.coords,T=this.result.rect;if(E&&T){var S=p.rect.left!==T.left||p.rect.right!==T.right||p.rect.top!==T.top||p.rect.bottom!==T.bottom;p.changed=S||E.x!==p.coords.x||E.y!==p.coords.y}return p}},{key:"applyToInteraction",value:function(e){var n=this.interaction,o=e.phase,r=n.coords.cur,i=n.coords.start,s=this.result,l=this.startDelta,p=s.delta;o==="start"&&z(this.startDelta,s.delta);for(var g=0,u=[[i,l],[r,p]];g<u.length;g++){var v=u[g],y=v[0],_=v[1];y.page.x+=_.x,y.page.y+=_.y,y.client.x+=_.x,y.client.y+=_.y}var k=this.result.rectDelta,E=e.rect||n.rect;E.left+=k.left,E.right+=k.right,E.top+=k.top,E.bottom+=k.bottom,E.width=E.right-E.left,E.height=E.bottom-E.top}},{key:"setAndApply",value:function(e){var n=this.interaction,o=e.phase,r=e.preEnd,i=e.skipModifiers,s=this.setAll(this.fillArg({preEnd:r,phase:o,pageCoords:e.modifiedCoords||n.coords.cur.page}));if(this.result=s,!s.changed&&(!i||i<this.states.length)&&n.interacting())return!1;if(e.modifiedCoords){var l=n.coords.cur.page,p={x:e.modifiedCoords.x-l.x,y:e.modifiedCoords.y-l.y};s.coords.x+=p.x,s.coords.y+=p.y,s.delta.x+=p.x,s.delta.y+=p.y}this.applyToInteraction(e)}},{key:"beforeEnd",value:function(e){var n=e.interaction,o=e.event,r=this.states;if(r&&r.length){for(var i=!1,s=0;s<r.length;s++){var l=r[s];e.state=l;var p=l.options,g=l.methods,u=g.beforeEnd&&g.beforeEnd(e);if(u)return this.endResult=u,!1;i=i||!i&&this.shouldDo(p,!0,e.phase,!0)}i&&n.move({event:o,preEnd:!0})}}},{key:"stop",value:function(e){var n=e.interaction;if(this.states&&this.states.length){var o=z({states:this.states,interactable:n.interactable,element:n.element,rect:null},e);this.fillArg(o);for(var r=0,i=this.states;r<i.length;r++){var s=i[r];o.state=s,s.methods.stop&&s.methods.stop(o)}this.states=null,this.endResult=null}}},{key:"prepareStates",value:function(e){this.states=[];for(var n=0;n<e.length;n++){var o=e[n],r=o.options,i=o.methods,s=o.name;this.states.push({options:r,methods:i,index:n,name:s})}return this.states}},{key:"restoreInteractionCoords",value:function(e){var n=e.interaction,o=n.coords,r=n.rect,i=n.modification;if(i.result){for(var s=i.startDelta,l=i.result,p=l.delta,g=l.rectDelta,u=0,v=[[o.start,s],[o.cur,p]];u<v.length;u++){var y=v[u],_=y[0],k=y[1];_.page.x-=k.x,_.page.y-=k.y,_.client.x-=k.x,_.client.y-=k.y}r.left-=g.left,r.right-=g.right,r.top-=g.top,r.bottom-=g.bottom}}},{key:"shouldDo",value:function(e,n,o,r){return!(!e||e.enabled===!1||r&&!e.endOnly||e.endOnly&&!n||o==="start"&&!e.setStart)}},{key:"copyFrom",value:function(e){this.startOffset=e.startOffset,this.startDelta=e.startDelta,this.startEdges=e.startEdges,this.edges=e.edges,this.states=e.states.map((function(n){return _e(n)})),this.result=Ye(z({},e.result.coords),z({},e.result.rect))}},{key:"destroy",value:function(){for(var e in this)this[e]=null}}]),t})();function Ye(t,e){return{rect:e,coords:t,delta:{x:0,y:0},rectDelta:{left:0,right:0,top:0,bottom:0},eventProps:[],changed:!0}}function ue(t,e){var n=t.defaults,o={start:t.start,set:t.set,beforeEnd:t.beforeEnd,stop:t.stop},r=function(i){var s=i||{};for(var l in s.enabled=s.enabled!==!1,n)l in s||(s[l]=n[l]);var p={options:s,methods:o,name:e,enable:function(){return s.enabled=!0,p},disable:function(){return s.enabled=!1,p}};return p};return e&&typeof e=="string"&&(r._defaults=n,r._methods=o),r}function Pe(t){var e=t.iEvent,n=t.interaction.modification.result;n&&(e.modifiers=n.eventProps)}var Zn={id:"modifiers/base",before:["actions"],install:function(t){t.defaults.perAction.modifiers=[]},listeners:{"interactions:new":function(t){var e=t.interaction;e.modification=new mt(e)},"interactions:before-action-start":function(t){var e=t.interaction,n=t.interaction.modification;n.start(t,e.coords.start.page),e.edges=n.edges,n.applyToInteraction(t)},"interactions:before-action-move":function(t){var e=t.interaction,n=e.modification,o=n.setAndApply(t);return e.edges=n.edges,o},"interactions:before-action-end":function(t){var e=t.interaction,n=e.modification,o=n.beforeEnd(t);return e.edges=n.startEdges,o},"interactions:action-start":Pe,"interactions:action-move":Pe,"interactions:action-end":Pe,"interactions:after-action-start":function(t){return t.interaction.modification.restoreInteractionCoords(t)},"interactions:after-action-move":function(t){return t.interaction.modification.restoreInteractionCoords(t)},"interactions:stop":function(t){return t.interaction.modification.stop(t)}}},an=Zn,sn={base:{preventDefault:"auto",deltaSource:"page"},perAction:{enabled:!1,origin:{x:0,y:0}},actions:{}},bt=(function(t){f(n,t);var e=P(n);function n(o,r,i,s,l,p,g){var u;a(this,n),(u=e.call(this,o)).relatedTarget=null,u.screenX=void 0,u.screenY=void 0,u.button=void 0,u.buttons=void 0,u.ctrlKey=void 0,u.shiftKey=void 0,u.altKey=void 0,u.metaKey=void 0,u.page=void 0,u.client=void 0,u.delta=void 0,u.rect=void 0,u.x0=void 0,u.y0=void 0,u.t0=void 0,u.dt=void 0,u.duration=void 0,u.clientX0=void 0,u.clientY0=void 0,u.velocity=void 0,u.speed=void 0,u.swipe=void 0,u.axes=void 0,u.preEnd=void 0,l=l||o.element;var v=o.interactable,y=(v&&v.options||sn).deltaSource,_=Se(v,l,i),k=s==="start",E=s==="end",T=k?w(u):o.prevEvent,S=k?o.coords.start:E?{page:T.page,client:T.client,timeStamp:o.coords.cur.timeStamp}:o.coords.cur;return u.page=z({},S.page),u.client=z({},S.client),u.rect=z({},o.rect),u.timeStamp=S.timeStamp,E||(u.page.x-=_.x,u.page.y-=_.y,u.client.x-=_.x,u.client.y-=_.y),u.ctrlKey=r.ctrlKey,u.altKey=r.altKey,u.shiftKey=r.shiftKey,u.metaKey=r.metaKey,u.button=r.button,u.buttons=r.buttons,u.target=l,u.currentTarget=l,u.preEnd=p,u.type=g||i+(s||""),u.interactable=v,u.t0=k?o.pointers[o.pointers.length-1].downTime:T.t0,u.x0=o.coords.start.page.x-_.x,u.y0=o.coords.start.page.y-_.y,u.clientX0=o.coords.start.client.x-_.x,u.clientY0=o.coords.start.client.y-_.y,u.delta=k||E?{x:0,y:0}:{x:u[y].x-T[y].x,y:u[y].y-T[y].y},u.dt=o.coords.delta.timeStamp,u.duration=u.timeStamp-u.t0,u.velocity=z({},o.coords.velocity[y]),u.speed=Te(u.velocity.x,u.velocity.y),u.swipe=E||s==="inertiastart"?u.getSwipe():null,u}return d(n,[{key:"getSwipe",value:function(){var o=this._interaction;if(o.prevEvent.speed<600||this.timeStamp-o.prevEvent.timeStamp>150)return null;var r=180*Math.atan2(o.prevEvent.velocityY,o.prevEvent.velocityX)/Math.PI;r<0&&(r+=360);var i=112.5<=r&&r<247.5,s=202.5<=r&&r<337.5;return{up:s,down:!s&&22.5<=r&&r<157.5,left:i,right:!i&&(292.5<=r||r<67.5),angle:r,speed:o.prevEvent.speed,velocity:{x:o.prevEvent.velocityX,y:o.prevEvent.velocityY}}}},{key:"preventDefault",value:function(){}},{key:"stopImmediatePropagation",value:function(){this.immediatePropagationStopped=this.propagationStopped=!0}},{key:"stopPropagation",value:function(){this.propagationStopped=!0}}]),n})(Ge);Object.defineProperties(bt.prototype,{pageX:{get:function(){return this.page.x},set:function(t){this.page.x=t}},pageY:{get:function(){return this.page.y},set:function(t){this.page.y=t}},clientX:{get:function(){return this.client.x},set:function(t){this.client.x=t}},clientY:{get:function(){return this.client.y},set:function(t){this.client.y=t}},dx:{get:function(){return this.delta.x},set:function(t){this.delta.x=t}},dy:{get:function(){return this.delta.y},set:function(t){this.delta.y=t}},velocityX:{get:function(){return this.velocity.x},set:function(t){this.velocity.x=t}},velocityY:{get:function(){return this.velocity.y},set:function(t){this.velocity.y=t}}});var Jn=d((function t(e,n,o,r,i){a(this,t),this.id=void 0,this.pointer=void 0,this.event=void 0,this.downTime=void 0,this.downTarget=void 0,this.id=e,this.pointer=n,this.event=o,this.downTime=r,this.downTarget=i})),Qn=(function(t){return t.interactable="",t.element="",t.prepared="",t.pointerIsDown="",t.pointerWasMoved="",t._proxy="",t})({}),cn=(function(t){return t.start="",t.move="",t.end="",t.stop="",t.interacting="",t})({}),eo=0,to=(function(){function t(e){var n=this,o=e.pointerType,r=e.scopeFire;a(this,t),this.interactable=null,this.element=null,this.rect=null,this._rects=void 0,this.edges=null,this._scopeFire=void 0,this.prepared={name:null,axis:null,edges:null},this.pointerType=void 0,this.pointers=[],this.downEvent=null,this.downPointer={},this._latestPointer={pointer:null,event:null,eventTarget:null},this.prevEvent=null,this.pointerIsDown=!1,this.pointerWasMoved=!1,this._interacting=!1,this._ending=!1,this._stopped=!0,this._proxy=void 0,this.simulation=null,this.doMove=De((function(u){this.move(u)}),"The interaction.doMove() method has been renamed to interaction.move()"),this.coords={start:{page:{x:0,y:0},client:{x:0,y:0},timeStamp:0},prev:{page:{x:0,y:0},client:{x:0,y:0},timeStamp:0},cur:{page:{x:0,y:0},client:{x:0,y:0},timeStamp:0},delta:{page:{x:0,y:0},client:{x:0,y:0},timeStamp:0},velocity:{page:{x:0,y:0},client:{x:0,y:0},timeStamp:0}},this._id=eo++,this._scopeFire=r,this.pointerType=o;var i=this;this._proxy={};var s=function(u){Object.defineProperty(n._proxy,u,{get:function(){return i[u]}})};for(var l in Qn)s(l);var p=function(u){Object.defineProperty(n._proxy,u,{value:function(){return i[u].apply(i,arguments)}})};for(var g in cn)p(g);this._scopeFire("interactions:new",{interaction:this})}return d(t,[{key:"pointerMoveTolerance",get:function(){return 1}},{key:"pointerDown",value:function(e,n,o){var r=this.updatePointer(e,n,o,!0),i=this.pointers[r];this._scopeFire("interactions:down",{pointer:e,event:n,eventTarget:o,pointerIndex:r,pointerInfo:i,type:"down",interaction:this})}},{key:"start",value:function(e,n,o){return!(this.interacting()||!this.pointerIsDown||this.pointers.length<(e.name==="gesture"?2:1)||!n.options[e.name].enabled)&&(ht(this.prepared,e),this.interactable=n,this.element=o,this.rect=n.getRect(o),this.edges=this.prepared.edges?z({},this.prepared.edges):{left:!0,right:!0,top:!0,bottom:!0},this._stopped=!1,this._interacting=this._doPhase({interaction:this,event:this.downEvent,phase:"start"})&&!this._stopped,this._interacting)}},{key:"pointerMove",value:function(e,n,o){this.simulation||this.modification&&this.modification.endResult||this.updatePointer(e,n,o,!1);var r,i,s=this.coords.cur.page.x===this.coords.prev.page.x&&this.coords.cur.page.y===this.coords.prev.page.y&&this.coords.cur.client.x===this.coords.prev.client.x&&this.coords.cur.client.y===this.coords.prev.client.y;this.pointerIsDown&&!this.pointerWasMoved&&(r=this.coords.cur.client.x-this.coords.start.client.x,i=this.coords.cur.client.y-this.coords.start.client.y,this.pointerWasMoved=Te(r,i)>this.pointerMoveTolerance);var l,p,g,u=this.getPointerIndex(e),v={pointer:e,pointerIndex:u,pointerInfo:this.pointers[u],event:n,type:"move",eventTarget:o,dx:r,dy:i,duplicate:s,interaction:this};s||(l=this.coords.velocity,p=this.coords.delta,g=Math.max(p.timeStamp/1e3,.001),l.page.x=p.page.x/g,l.page.y=p.page.y/g,l.client.x=p.client.x/g,l.client.y=p.client.y/g,l.timeStamp=g),this._scopeFire("interactions:move",v),s||this.simulation||(this.interacting()&&(v.type=null,this.move(v)),this.pointerWasMoved&&Fe(this.coords.prev,this.coords.cur))}},{key:"move",value:function(e){e&&e.event||Nt(this.coords.delta),(e=z({pointer:this._latestPointer.pointer,event:this._latestPointer.event,eventTarget:this._latestPointer.eventTarget,interaction:this},e||{})).phase="move",this._doPhase(e)}},{key:"pointerUp",value:function(e,n,o,r){var i=this.getPointerIndex(e);i===-1&&(i=this.updatePointer(e,n,o,!1));var s=/cancel$/i.test(n.type)?"cancel":"up";this._scopeFire("interactions:".concat(s),{pointer:e,pointerIndex:i,pointerInfo:this.pointers[i],event:n,eventTarget:o,type:s,curEventTarget:r,interaction:this}),this.simulation||this.end(n),this.removePointer(e,n)}},{key:"documentBlur",value:function(e){this.end(e),this._scopeFire("interactions:blur",{event:e,type:"blur",interaction:this})}},{key:"end",value:function(e){var n;this._ending=!0,e=e||this._latestPointer.event,this.interacting()&&(n=this._doPhase({event:e,interaction:this,phase:"end"})),this._ending=!1,n===!0&&this.stop()}},{key:"currentAction",value:function(){return this._interacting?this.prepared.name:null}},{key:"interacting",value:function(){return this._interacting}},{key:"stop",value:function(){this._scopeFire("interactions:stop",{interaction:this}),this.interactable=this.element=null,this._interacting=!1,this._stopped=!0,this.prepared.name=this.prevEvent=null}},{key:"getPointerIndex",value:function(e){var n=Ie(e);return this.pointerType==="mouse"||this.pointerType==="pen"?this.pointers.length-1:ze(this.pointers,(function(o){return o.id===n}))}},{key:"getPointerInfo",value:function(e){return this.pointers[this.getPointerIndex(e)]}},{key:"updatePointer",value:function(e,n,o,r){var i,s,l,p=Ie(e),g=this.getPointerIndex(e),u=this.pointers[g];return r=r!==!1&&(r||/(down|start)$/i.test(n.type)),u?u.pointer=e:(u=new Jn(p,e,n,null,null),g=this.pointers.length,this.pointers.push(u)),Cn(this.coords.cur,this.pointers.map((function(v){return v.pointer})),this._now()),i=this.coords.delta,s=this.coords.prev,l=this.coords.cur,i.page.x=l.page.x-s.page.x,i.page.y=l.page.y-s.page.y,i.client.x=l.client.x-s.client.x,i.client.y=l.client.y-s.client.y,i.timeStamp=l.timeStamp-s.timeStamp,r&&(this.pointerIsDown=!0,u.downTime=this.coords.cur.timeStamp,u.downTarget=o,je(this.downPointer,e),this.interacting()||(Fe(this.coords.start,this.coords.cur),Fe(this.coords.prev,this.coords.cur),this.downEvent=n,this.pointerWasMoved=!1)),this._updateLatestPointer(e,n,o),this._scopeFire("interactions:update-pointer",{pointer:e,event:n,eventTarget:o,down:r,pointerInfo:u,pointerIndex:g,interaction:this}),g}},{key:"removePointer",value:function(e,n){var o=this.getPointerIndex(e);if(o!==-1){var r=this.pointers[o];this._scopeFire("interactions:remove-pointer",{pointer:e,event:n,eventTarget:null,pointerIndex:o,pointerInfo:r,interaction:this}),this.pointers.splice(o,1),this.pointerIsDown=!1}}},{key:"_updateLatestPointer",value:function(e,n,o){this._latestPointer.pointer=e,this._latestPointer.event=n,this._latestPointer.eventTarget=o}},{key:"destroy",value:function(){this._latestPointer.pointer=null,this._latestPointer.event=null,this._latestPointer.eventTarget=null}},{key:"_createPreparedEvent",value:function(e,n,o,r){return new bt(this,e,this.prepared.name,n,this.element,o,r)}},{key:"_fireEvent",value:function(e){var n;(n=this.interactable)==null||n.fire(e),(!this.prevEvent||e.timeStamp>=this.prevEvent.timeStamp)&&(this.prevEvent=e)}},{key:"_doPhase",value:function(e){var n=e.event,o=e.phase,r=e.preEnd,i=e.type,s=this.rect;if(s&&o==="move"&&(qe(this.edges,s,this.coords.delta[this.interactable.options.deltaSource]),s.width=s.right-s.left,s.height=s.bottom-s.top),this._scopeFire("interactions:before-action-".concat(o),e)===!1)return!1;var l=e.iEvent=this._createPreparedEvent(n,o,r,i);return this._scopeFire("interactions:action-".concat(o),e),o==="start"&&(this.prevEvent=l),this._fireEvent(l),this._scopeFire("interactions:after-action-".concat(o),e),!0}},{key:"_now",value:function(){return Date.now()}}]),t})();function ln(t){dn(t.interaction)}function dn(t){if(!(function(n){return!(!n.offset.pending.x&&!n.offset.pending.y)})(t))return!1;var e=t.offset.pending;return yt(t.coords.cur,e),yt(t.coords.delta,e),qe(t.edges,t.rect,e),e.x=0,e.y=0,!0}function no(t){var e=t.x,n=t.y;this.offset.pending.x+=e,this.offset.pending.y+=n,this.offset.total.x+=e,this.offset.total.y+=n}function yt(t,e){var n=t.page,o=t.client,r=e.x,i=e.y;n.x+=r,n.y+=i,o.x+=r,o.y+=i}cn.offsetBy="";var oo={id:"offset",before:["modifiers","pointer-events","actions","inertia"],install:function(t){t.Interaction.prototype.offsetBy=no},listeners:{"interactions:new":function(t){t.interaction.offset={total:{x:0,y:0},pending:{x:0,y:0}}},"interactions:update-pointer":function(t){return(function(e){e.pointerIsDown&&(yt(e.coords.cur,e.offset.total),e.offset.pending.x=0,e.offset.pending.y=0)})(t.interaction)},"interactions:before-action-start":ln,"interactions:before-action-move":ln,"interactions:before-action-end":function(t){var e=t.interaction;if(dn(e))return e.move({offset:!0}),e.end(),!1},"interactions:stop":function(t){var e=t.interaction;e.offset.total.x=0,e.offset.total.y=0,e.offset.pending.x=0,e.offset.pending.y=0}}},pn=oo,ro=(function(){function t(e){a(this,t),this.active=!1,this.isModified=!1,this.smoothEnd=!1,this.allowResume=!1,this.modification=void 0,this.modifierCount=0,this.modifierArg=void 0,this.startCoords=void 0,this.t0=0,this.v0=0,this.te=0,this.targetOffset=void 0,this.modifiedOffset=void 0,this.currentOffset=void 0,this.lambda_v0=0,this.one_ve_v0=0,this.timeout=void 0,this.interaction=void 0,this.interaction=e}return d(t,[{key:"start",value:function(e){var n=this.interaction,o=Ue(n);if(!o||!o.enabled)return!1;var r=n.coords.velocity.client,i=Te(r.x,r.y),s=this.modification||(this.modification=new mt(n));if(s.copyFrom(n.modification),this.t0=n._now(),this.allowResume=o.allowResume,this.v0=i,this.currentOffset={x:0,y:0},this.startCoords=n.coords.cur.page,this.modifierArg=s.fillArg({pageCoords:this.startCoords,preEnd:!0,phase:"inertiastart"}),this.t0-n.coords.cur.timeStamp<50&&i>o.minSpeed&&i>o.endSpeed)this.startInertia();else{if(s.result=s.setAll(this.modifierArg),!s.result.changed)return!1;this.startSmoothEnd()}return n.modification.result.rect=null,n.offsetBy(this.targetOffset),n._doPhase({interaction:n,event:e,phase:"inertiastart"}),n.offsetBy({x:-this.targetOffset.x,y:-this.targetOffset.y}),n.modification.result.rect=null,this.active=!0,n.simulation=this,!0}},{key:"startInertia",value:function(){var e=this,n=this.interaction.coords.velocity.client,o=Ue(this.interaction),r=o.resistance,i=-Math.log(o.endSpeed/this.v0)/r;this.targetOffset={x:(n.x-i)/r,y:(n.y-i)/r},this.te=i,this.lambda_v0=r/this.v0,this.one_ve_v0=1-o.endSpeed/this.v0;var s=this.modification,l=this.modifierArg;l.pageCoords={x:this.startCoords.x+this.targetOffset.x,y:this.startCoords.y+this.targetOffset.y},s.result=s.setAll(l),s.result.changed&&(this.isModified=!0,this.modifiedOffset={x:this.targetOffset.x+s.result.delta.x,y:this.targetOffset.y+s.result.delta.y}),this.onNextFrame((function(){return e.inertiaTick()}))}},{key:"startSmoothEnd",value:function(){var e=this;this.smoothEnd=!0,this.isModified=!0,this.targetOffset={x:this.modification.result.delta.x,y:this.modification.result.delta.y},this.onNextFrame((function(){return e.smoothEndTick()}))}},{key:"onNextFrame",value:function(e){var n=this;this.timeout=pe.request((function(){n.active&&e()}))}},{key:"inertiaTick",value:function(){var e,n,o,r,i,s,l,p=this,g=this.interaction,u=Ue(g).resistance,v=(g._now()-this.t0)/1e3;if(v<this.te){var y,_=1-(Math.exp(-u*v)-this.lambda_v0)/this.one_ve_v0;this.isModified?(e=0,n=0,o=this.targetOffset.x,r=this.targetOffset.y,i=this.modifiedOffset.x,s=this.modifiedOffset.y,y={x:un(l=_,e,o,i),y:un(l,n,r,s)}):y={x:this.targetOffset.x*_,y:this.targetOffset.y*_};var k={x:y.x-this.currentOffset.x,y:y.y-this.currentOffset.y};this.currentOffset.x+=k.x,this.currentOffset.y+=k.y,g.offsetBy(k),g.move(),this.onNextFrame((function(){return p.inertiaTick()}))}else g.offsetBy({x:this.modifiedOffset.x-this.currentOffset.x,y:this.modifiedOffset.y-this.currentOffset.y}),this.end()}},{key:"smoothEndTick",value:function(){var e=this,n=this.interaction,o=n._now()-this.t0,r=Ue(n).smoothEndDuration;if(o<r){var i={x:hn(o,0,this.targetOffset.x,r),y:hn(o,0,this.targetOffset.y,r)},s={x:i.x-this.currentOffset.x,y:i.y-this.currentOffset.y};this.currentOffset.x+=s.x,this.currentOffset.y+=s.y,n.offsetBy(s),n.move({skipModifiers:this.modifierCount}),this.onNextFrame((function(){return e.smoothEndTick()}))}else n.offsetBy({x:this.targetOffset.x-this.currentOffset.x,y:this.targetOffset.y-this.currentOffset.y}),this.end()}},{key:"resume",value:function(e){var n=e.pointer,o=e.event,r=e.eventTarget,i=this.interaction;i.offsetBy({x:-this.currentOffset.x,y:-this.currentOffset.y}),i.updatePointer(n,o,r,!0),i._doPhase({interaction:i,event:o,phase:"resume"}),Fe(i.coords.prev,i.coords.cur),this.stop()}},{key:"end",value:function(){this.interaction.move(),this.interaction.end(),this.stop()}},{key:"stop",value:function(){this.active=this.smoothEnd=!1,this.interaction.simulation=null,pe.cancel(this.timeout)}}]),t})();function Ue(t){var e=t.interactable,n=t.prepared;return e&&e.options&&n.name&&e.options[n.name].inertia}var io={id:"inertia",before:["modifiers","actions"],install:function(t){var e=t.defaults;t.usePlugin(pn),t.usePlugin(an),t.actions.phases.inertiastart=!0,t.actions.phases.resume=!0,e.perAction.inertia={enabled:!1,resistance:10,minSpeed:100,endSpeed:10,allowResume:!0,smoothEndDuration:300}},listeners:{"interactions:new":function(t){var e=t.interaction;e.inertia=new ro(e)},"interactions:before-action-end":function(t){var e=t.interaction,n=t.event;return(!e._interacting||e.simulation||!e.inertia.start(n))&&null},"interactions:down":function(t){var e=t.interaction,n=t.eventTarget,o=e.inertia;if(o.active)for(var r=n;x.element(r);){if(r===e.element){o.resume(t);break}r=ae(r)}},"interactions:stop":function(t){var e=t.interaction.inertia;e.active&&e.stop()},"interactions:before-action-resume":function(t){var e=t.interaction.modification;e.stop(t),e.start(t,t.interaction.coords.cur.page),e.applyToInteraction(t)},"interactions:before-action-inertiastart":function(t){return t.interaction.modification.setAndApply(t)},"interactions:action-resume":Pe,"interactions:action-inertiastart":Pe,"interactions:after-action-inertiastart":function(t){return t.interaction.modification.restoreInteractionCoords(t)},"interactions:after-action-resume":function(t){return t.interaction.modification.restoreInteractionCoords(t)}}};function un(t,e,n,o){var r=1-t;return r*r*e+2*r*t*n+t*t*o}function hn(t,e,n,o){return-n*(t/=o)*(t-2)+e}var ao=io;function fn(t,e){for(var n=0;n<e.length;n++){var o=e[n];if(t.immediatePropagationStopped)break;o(t)}}var gn=(function(){function t(e){a(this,t),this.options=void 0,this.types={},this.propagationStopped=!1,this.immediatePropagationStopped=!1,this.global=void 0,this.options=z({},e||{})}return d(t,[{key:"fire",value:function(e){var n,o=this.global;(n=this.types[e.type])&&fn(e,n),!e.propagationStopped&&o&&(n=o[e.type])&&fn(e,n)}},{key:"on",value:function(e,n){var o=ve(e,n);for(e in o)this.types[e]=Bt(this.types[e]||[],o[e])}},{key:"off",value:function(e,n){var o=ve(e,n);for(e in o){var r=this.types[e];if(r&&r.length)for(var i=0,s=o[e];i<s.length;i++){var l=s[i],p=r.indexOf(l);p!==-1&&r.splice(p,1)}}}},{key:"getRect",value:function(e){return null}}]),t})(),so=(function(){function t(e){a(this,t),this.currentTarget=void 0,this.originalEvent=void 0,this.type=void 0,this.originalEvent=e,je(this,e)}return d(t,[{key:"preventOriginalDefault",value:function(){this.originalEvent.preventDefault()}},{key:"stopPropagation",value:function(){this.originalEvent.stopPropagation()}},{key:"stopImmediatePropagation",value:function(){this.originalEvent.stopImmediatePropagation()}}]),t})();function Ce(t){return x.object(t)?{capture:!!t.capture,passive:!!t.passive}:{capture:!!t,passive:!1}}function Xe(t,e){return t===e||(typeof t=="boolean"?!!e.capture===t&&!e.passive:!!t.capture==!!e.capture&&!!t.passive==!!e.passive)}var co={id:"events",install:function(t){var e,n=[],o={},r=[],i={add:s,remove:l,addDelegate:function(u,v,y,_,k){var E=Ce(k);if(!o[y]){o[y]=[];for(var T=0;T<r.length;T++){var S=r[T];s(S,y,p),s(S,y,g,!0)}}var I=o[y],$=Me(I,(function(A){return A.selector===u&&A.context===v}));$||($={selector:u,context:v,listeners:[]},I.push($)),$.listeners.push({func:_,options:E})},removeDelegate:function(u,v,y,_,k){var E,T=Ce(k),S=o[y],I=!1;if(S)for(E=S.length-1;E>=0;E--){var $=S[E];if($.selector===u&&$.context===v){for(var A=$.listeners,M=A.length-1;M>=0;M--){var O=A[M];if(O.func===_&&Xe(O.options,T)){A.splice(M,1),A.length||(S.splice(E,1),l(v,y,p),l(v,y,g,!0)),I=!0;break}}if(I)break}}},delegateListener:p,delegateUseCapture:g,delegatedEvents:o,documents:r,targets:n,supportsOptions:!1,supportsPassive:!1};function s(u,v,y,_){if(u.addEventListener){var k=Ce(_),E=Me(n,(function(T){return T.eventTarget===u}));E||(E={eventTarget:u,events:{}},n.push(E)),E.events[v]||(E.events[v]=[]),Me(E.events[v],(function(T){return T.func===y&&Xe(T.options,k)}))||(u.addEventListener(v,y,i.supportsOptions?k:k.capture),E.events[v].push({func:y,options:k}))}}function l(u,v,y,_){if(u.addEventListener&&u.removeEventListener){var k=ze(n,(function(V){return V.eventTarget===u})),E=n[k];if(E&&E.events)if(v!=="all"){var T=!1,S=E.events[v];if(S){if(y==="all"){for(var I=S.length-1;I>=0;I--){var $=S[I];l(u,v,$.func,$.options)}return}for(var A=Ce(_),M=0;M<S.length;M++){var O=S[M];if(O.func===y&&Xe(O.options,A)){u.removeEventListener(v,y,i.supportsOptions?A:A.capture),S.splice(M,1),S.length===0&&(delete E.events[v],T=!0);break}}}T&&!Object.keys(E.events).length&&n.splice(k,1)}else for(v in E.events)E.events.hasOwnProperty(v)&&l(u,v,"all")}}function p(u,v){for(var y=Ce(v),_=new so(u),k=o[u.type],E=Gt(u)[0],T=E;x.element(T);){for(var S=0;S<k.length;S++){var I=k[S],$=I.selector,A=I.context;if(de(T,$)&&ge(A,E)&&ge(A,T)){var M=I.listeners;_.currentTarget=T;for(var O=0;O<M.length;O++){var V=M[O];Xe(V.options,y)&&V.func(_)}}}T=ae(T)}}function g(u){return p(u,!0)}return(e=t.document)==null||e.createElement("div").addEventListener("test",null,{get capture(){return i.supportsOptions=!0},get passive(){return i.supportsPassive=!0}}),t.events=i,i}},xt={methodOrder:["simulationResume","mouseOrPen","hasPointer","idle"],search:function(t){for(var e=0,n=xt.methodOrder;e<n.length;e++){var o=n[e],r=xt[o](t);if(r)return r}return null},simulationResume:function(t){var e=t.pointerType,n=t.eventType,o=t.eventTarget,r=t.scope;if(!/down|start/i.test(n))return null;for(var i=0,s=r.interactions.list;i<s.length;i++){var l=s[i],p=o;if(l.simulation&&l.simulation.allowResume&&l.pointerType===e)for(;p;){if(p===l.element)return l;p=ae(p)}}return null},mouseOrPen:function(t){var e,n=t.pointerId,o=t.pointerType,r=t.eventType,i=t.scope;if(o!=="mouse"&&o!=="pen")return null;for(var s=0,l=i.interactions.list;s<l.length;s++){var p=l[s];if(p.pointerType===o){if(p.simulation&&!vn(p,n))continue;if(p.interacting())return p;e||(e=p)}}if(e)return e;for(var g=0,u=i.interactions.list;g<u.length;g++){var v=u[g];if(!(v.pointerType!==o||/down/i.test(r)&&v.simulation))return v}return null},hasPointer:function(t){for(var e=t.pointerId,n=0,o=t.scope.interactions.list;n<o.length;n++){var r=o[n];if(vn(r,e))return r}return null},idle:function(t){for(var e=t.pointerType,n=0,o=t.scope.interactions.list;n<o.length;n++){var r=o[n];if(r.pointers.length===1){var i=r.interactable;if(i&&(!i.options.gesture||!i.options.gesture.enabled))continue}else if(r.pointers.length>=2)continue;if(!r.interacting()&&e===r.pointerType)return r}return null}};function vn(t,e){return t.pointers.some((function(n){return n.id===e}))}var lo=xt,wt=["pointerDown","pointerMove","pointerUp","updatePointer","removePointer","windowBlur"];function mn(t,e){return function(n){var o=e.interactions.list,r=Rt(n),i=Gt(n),s=i[0],l=i[1],p=[];if(/^touch/.test(n.type)){e.prevTouchTime=e.now();for(var g=0,u=n.changedTouches;g<u.length;g++){var v=u[g],y={pointer:v,pointerId:Ie(v),pointerType:r,eventType:n.type,eventTarget:s,curEventTarget:l,scope:e},_=bn(y);p.push([y.pointer,y.eventTarget,y.curEventTarget,_])}}else{var k=!1;if(!ne.supportsPointerEvent&&/mouse/.test(n.type)){for(var E=0;E<o.length&&!k;E++)k=o[E].pointerType!=="mouse"&&o[E].pointerIsDown;k=k||e.now()-e.prevTouchTime<500||n.timeStamp===0}if(!k){var T={pointer:n,pointerId:Ie(n),pointerType:r,eventType:n.type,curEventTarget:l,eventTarget:s,scope:e},S=bn(T);p.push([T.pointer,T.eventTarget,T.curEventTarget,S])}}for(var I=0;I<p.length;I++){var $=p[I],A=$[0],M=$[1],O=$[2];$[3][t](A,n,M,O)}}}function bn(t){var e=t.pointerType,n=t.scope,o={interaction:lo.search(t),searchDetails:t};return n.fire("interactions:find",o),o.interaction||n.interactions.new({pointerType:e})}function kt(t,e){var n=t.doc,o=t.scope,r=t.options,i=o.interactions.docEvents,s=o.events,l=s[e];for(var p in o.browser.isIOS&&!r.events&&(r.events={passive:!1}),s.delegatedEvents)l(n,p,s.delegateListener),l(n,p,s.delegateUseCapture,!0);for(var g=r&&r.events,u=0;u<i.length;u++){var v=i[u];l(n,v.type,v.listener,g)}}var po={id:"core/interactions",install:function(t){for(var e={},n=0;n<wt.length;n++){var o=wt[n];e[o]=mn(o,t)}var r,i=ne.pEventTypes;function s(){for(var l=0,p=t.interactions.list;l<p.length;l++){var g=p[l];if(g.pointerIsDown&&g.pointerType==="touch"&&!g._interacting)for(var u=function(){var _=y[v];t.documents.some((function(k){return ge(k.doc,_.downTarget)}))||g.removePointer(_.pointer,_.event)},v=0,y=g.pointers;v<y.length;v++)u()}}(r=X.PointerEvent?[{type:i.down,listener:s},{type:i.down,listener:e.pointerDown},{type:i.move,listener:e.pointerMove},{type:i.up,listener:e.pointerUp},{type:i.cancel,listener:e.pointerUp}]:[{type:"mousedown",listener:e.pointerDown},{type:"mousemove",listener:e.pointerMove},{type:"mouseup",listener:e.pointerUp},{type:"touchstart",listener:s},{type:"touchstart",listener:e.pointerDown},{type:"touchmove",listener:e.pointerMove},{type:"touchend",listener:e.pointerUp},{type:"touchcancel",listener:e.pointerUp}]).push({type:"blur",listener:function(l){for(var p=0,g=t.interactions.list;p<g.length;p++)g[p].documentBlur(l)}}),t.prevTouchTime=0,t.Interaction=(function(l){f(g,l);var p=P(g);function g(){return a(this,g),p.apply(this,arguments)}return d(g,[{key:"pointerMoveTolerance",get:function(){return t.interactions.pointerMoveTolerance},set:function(u){t.interactions.pointerMoveTolerance=u}},{key:"_now",value:function(){return t.now()}}]),g})(to),t.interactions={list:[],new:function(l){l.scopeFire=function(g,u){return t.fire(g,u)};var p=new t.Interaction(l);return t.interactions.list.push(p),p},listeners:e,docEvents:r,pointerMoveTolerance:1},t.usePlugin(rn)},listeners:{"scope:add-document":function(t){return kt(t,"add")},"scope:remove-document":function(t){return kt(t,"remove")},"interactable:unset":function(t,e){for(var n=t.interactable,o=e.interactions.list.length-1;o>=0;o--){var r=e.interactions.list[o];r.interactable===n&&(r.stop(),e.fire("interactions:destroy",{interaction:r}),r.destroy(),e.interactions.list.length>2&&e.interactions.list.splice(o,1))}}},onDocSignal:kt,doOnInteractions:mn,methodNames:wt},uo=po,he=(function(t){return t[t.On=0]="On",t[t.Off=1]="Off",t})(he||{}),ho=(function(){function t(e,n,o,r){a(this,t),this.target=void 0,this.options=void 0,this._actions=void 0,this.events=new gn,this._context=void 0,this._win=void 0,this._doc=void 0,this._scopeEvents=void 0,this._actions=n.actions,this.target=e,this._context=n.context||o,this._win=B(Ot(e)?this._context:e),this._doc=this._win.document,this._scopeEvents=r,this.set(n)}return d(t,[{key:"_defaults",get:function(){return{base:{},perAction:{},actions:{}}}},{key:"setOnEvents",value:function(e,n){return x.func(n.onstart)&&this.on("".concat(e,"start"),n.onstart),x.func(n.onmove)&&this.on("".concat(e,"move"),n.onmove),x.func(n.onend)&&this.on("".concat(e,"end"),n.onend),x.func(n.oninertiastart)&&this.on("".concat(e,"inertiastart"),n.oninertiastart),this}},{key:"updatePerActionListeners",value:function(e,n,o){var r,i=this,s=(r=this._actions.map[e])==null?void 0:r.filterEventType,l=function(p){return(s==null||s(p))&&He(p,i._actions)};(x.array(n)||x.object(n))&&this._onOff(he.Off,e,n,void 0,l),(x.array(o)||x.object(o))&&this._onOff(he.On,e,o,void 0,l)}},{key:"setPerAction",value:function(e,n){var o=this._defaults;for(var r in n){var i=r,s=this.options[e],l=n[i];i==="listeners"&&this.updatePerActionListeners(e,s.listeners,l),x.array(l)?s[i]=Ht(l):x.plainObject(l)?(s[i]=z(s[i]||{},_e(l)),x.object(o.perAction[i])&&"enabled"in o.perAction[i]&&(s[i].enabled=l.enabled!==!1)):x.bool(l)&&x.object(o.perAction[i])?s[i].enabled=l:s[i]=l}}},{key:"getRect",value:function(e){return e=e||(x.element(this.target)?this.target:null),x.string(this.target)&&(e=e||this._context.querySelector(this.target)),nt(e)}},{key:"rectChecker",value:function(e){var n=this;return x.func(e)?(this.getRect=function(o){var r=z({},e.apply(n,o));return"width"in r||(r.width=r.right-r.left,r.height=r.bottom-r.top),r},this):e===null?(delete this.getRect,this):this.getRect}},{key:"_backCompatOption",value:function(e,n){if(Ot(n)||x.object(n)){for(var o in this.options[e]=n,this._actions.map)this.options[o][e]=n;return this}return this.options[e]}},{key:"origin",value:function(e){return this._backCompatOption("origin",e)}},{key:"deltaSource",value:function(e){return e==="page"||e==="client"?(this.options.deltaSource=e,this):this.options.deltaSource}},{key:"getAllElements",value:function(){var e=this.target;return x.string(e)?Array.from(this._context.querySelectorAll(e)):x.func(e)&&e.getAllElements?e.getAllElements():x.element(e)?[e]:[]}},{key:"context",value:function(){return this._context}},{key:"inContext",value:function(e){return this._context===e.ownerDocument||ge(this._context,e)}},{key:"testIgnoreAllow",value:function(e,n,o){return!this.testIgnore(e.ignoreFrom,n,o)&&this.testAllow(e.allowFrom,n,o)}},{key:"testAllow",value:function(e,n,o){return!e||!!x.element(o)&&(x.string(e)?et(o,e,n):!!x.element(e)&&ge(e,o))}},{key:"testIgnore",value:function(e,n,o){return!(!e||!x.element(o))&&(x.string(e)?et(o,e,n):!!x.element(e)&&ge(e,o))}},{key:"fire",value:function(e){return this.events.fire(e),this}},{key:"_onOff",value:function(e,n,o,r,i){x.object(n)&&!x.array(n)&&(r=o,o=null);var s=ve(n,o,i);for(var l in s){l==="wheel"&&(l=ne.wheelEvent);for(var p=0,g=s[l];p<g.length;p++){var u=g[p];He(l,this._actions)?this.events[e===he.On?"on":"off"](l,u):x.string(this.target)?this._scopeEvents[e===he.On?"addDelegate":"removeDelegate"](this.target,this._context,l,u,r):this._scopeEvents[e===he.On?"add":"remove"](this.target,l,u,r)}}return this}},{key:"on",value:function(e,n,o){return this._onOff(he.On,e,n,o)}},{key:"off",value:function(e,n,o){return this._onOff(he.Off,e,n,o)}},{key:"set",value:function(e){var n=this._defaults;for(var o in x.object(e)||(e={}),this.options=_e(n.base),this._actions.methodDict){var r=o,i=this._actions.methodDict[r];this.options[r]={},this.setPerAction(r,z(z({},n.perAction),n.actions[r])),this[i](e[r])}for(var s in e)s!=="getRect"?x.func(this[s])&&this[s](e[s]):this.rectChecker(e.getRect);return this}},{key:"unset",value:function(){if(x.string(this.target))for(var e in this._scopeEvents.delegatedEvents)for(var n=this._scopeEvents.delegatedEvents[e],o=n.length-1;o>=0;o--){var r=n[o],i=r.selector,s=r.context,l=r.listeners;i===this.target&&s===this._context&&n.splice(o,1);for(var p=l.length-1;p>=0;p--)this._scopeEvents.removeDelegate(this.target,this._context,e,l[p][0],l[p][1])}else this._scopeEvents.remove(this.target,"all")}}]),t})(),fo=(function(){function t(e){var n=this;a(this,t),this.list=[],this.selectorMap={},this.scope=void 0,this.scope=e,e.addListeners({"interactable:unset":function(o){var r=o.interactable,i=r.target,s=x.string(i)?n.selectorMap[i]:i[n.scope.id],l=ze(s,(function(p){return p===r}));s.splice(l,1)}})}return d(t,[{key:"new",value:function(e,n){n=z(n||{},{actions:this.scope.actions});var o=new this.scope.Interactable(e,n,this.scope.document,this.scope.events);return this.scope.addDocument(o._doc),this.list.push(o),x.string(e)?(this.selectorMap[e]||(this.selectorMap[e]=[]),this.selectorMap[e].push(o)):(o.target[this.scope.id]||Object.defineProperty(e,this.scope.id,{value:[],configurable:!0}),e[this.scope.id].push(o)),this.scope.fire("interactable:new",{target:e,options:n,interactable:o,win:this.scope._win}),o}},{key:"getExisting",value:function(e,n){var o=n&&n.context||this.scope.document,r=x.string(e),i=r?this.selectorMap[e]:e[this.scope.id];if(i)return Me(i,(function(s){return s._context===o&&(r||s.inContext(e))}))}},{key:"forEachMatch",value:function(e,n){for(var o=0,r=this.list;o<r.length;o++){var i=r[o],s=void 0;if((x.string(i.target)?x.element(e)&&de(e,i.target):e===i.target)&&i.inContext(e)&&(s=n(i)),s!==void 0)return s}}}]),t})(),go=(function(){function t(){var e=this;a(this,t),this.id="__interact_scope_".concat(Math.floor(100*Math.random())),this.isInitialized=!1,this.listenerMaps=[],this.browser=ne,this.defaults=_e(sn),this.Eventable=gn,this.actions={map:{},phases:{start:!0,move:!0,end:!0},methodDict:{},phaselessTypes:{}},this.interactStatic=(function(o){var r=function i(s,l){var p=o.interactables.getExisting(s,l);return p||((p=o.interactables.new(s,l)).events.global=i.globalEvents),p};return r.getPointerAverage=Ft,r.getTouchBBox=it,r.getTouchDistance=at,r.getTouchAngle=st,r.getElementRect=nt,r.getElementClientRect=tt,r.matchesSelector=de,r.closest=Dt,r.globalEvents={},r.version="1.10.27",r.scope=o,r.use=function(i,s){return this.scope.usePlugin(i,s),this},r.isSet=function(i,s){return!!this.scope.interactables.get(i,s&&s.context)},r.on=De((function(i,s,l){if(x.string(i)&&i.search(" ")!==-1&&(i=i.trim().split(/ +/)),x.array(i)){for(var p=0,g=i;p<g.length;p++){var u=g[p];this.on(u,s,l)}return this}if(x.object(i)){for(var v in i)this.on(v,i[v],s);return this}return He(i,this.scope.actions)?this.globalEvents[i]?this.globalEvents[i].push(s):this.globalEvents[i]=[s]:this.scope.events.add(this.scope.document,i,s,{options:l}),this}),"The interact.on() method is being deprecated"),r.off=De((function(i,s,l){if(x.string(i)&&i.search(" ")!==-1&&(i=i.trim().split(/ +/)),x.array(i)){for(var p=0,g=i;p<g.length;p++){var u=g[p];this.off(u,s,l)}return this}if(x.object(i)){for(var v in i)this.off(v,i[v],s);return this}var y;return He(i,this.scope.actions)?i in this.globalEvents&&(y=this.globalEvents[i].indexOf(s))!==-1&&this.globalEvents[i].splice(y,1):this.scope.events.remove(this.scope.document,i,s,l),this}),"The interact.off() method is being deprecated"),r.debug=function(){return this.scope},r.supportsTouch=function(){return ne.supportsTouch},r.supportsPointerEvent=function(){return ne.supportsPointerEvent},r.stop=function(){for(var i=0,s=this.scope.interactions.list;i<s.length;i++)s[i].stop();return this},r.pointerMoveTolerance=function(i){return x.number(i)?(this.scope.interactions.pointerMoveTolerance=i,this):this.scope.interactions.pointerMoveTolerance},r.addDocument=function(i,s){this.scope.addDocument(i,s)},r.removeDocument=function(i){this.scope.removeDocument(i)},r})(this),this.InteractEvent=bt,this.Interactable=void 0,this.interactables=new fo(this),this._win=void 0,this.document=void 0,this.window=void 0,this.documents=[],this._plugins={list:[],map:{}},this.onWindowUnload=function(o){return e.removeDocument(o.target)};var n=this;this.Interactable=(function(o){f(i,o);var r=P(i);function i(){return a(this,i),r.apply(this,arguments)}return d(i,[{key:"_defaults",get:function(){return n.defaults}},{key:"set",value:function(s){return C(m(i.prototype),"set",this).call(this,s),n.fire("interactable:set",{options:s,interactable:this}),this}},{key:"unset",value:function(){C(m(i.prototype),"unset",this).call(this);var s=n.interactables.list.indexOf(this);s<0||(n.interactables.list.splice(s,1),n.fire("interactable:unset",{interactable:this}))}}]),i})(ho)}return d(t,[{key:"addListeners",value:function(e,n){this.listenerMaps.push({id:n,map:e})}},{key:"fire",value:function(e,n){for(var o=0,r=this.listenerMaps;o<r.length;o++){var i=r[o].map[e];if(i&&i(n,this,e)===!1)return!1}}},{key:"init",value:function(e){return this.isInitialized?this:(function(n,o){return n.isInitialized=!0,x.window(o)&&H(o),X.init(o),ne.init(o),pe.init(o),n.window=o,n.document=o.document,n.usePlugin(uo),n.usePlugin(co),n})(this,e)}},{key:"pluginIsInstalled",value:function(e){var n=e.id;return n?!!this._plugins.map[n]:this._plugins.list.indexOf(e)!==-1}},{key:"usePlugin",value:function(e,n){if(!this.isInitialized)return this;if(this.pluginIsInstalled(e))return this;if(e.id&&(this._plugins.map[e.id]=e),this._plugins.list.push(e),e.install&&e.install(this,n),e.listeners&&e.before){for(var o=0,r=this.listenerMaps.length,i=e.before.reduce((function(l,p){return l[p]=!0,l[yn(p)]=!0,l}),{});o<r;o++){var s=this.listenerMaps[o].id;if(s&&(i[s]||i[yn(s)]))break}this.listenerMaps.splice(o,0,{id:e.id,map:e.listeners})}else e.listeners&&this.listenerMaps.push({id:e.id,map:e.listeners});return this}},{key:"addDocument",value:function(e,n){if(this.getDocIndex(e)!==-1)return!1;var o=B(e);n=n?z({},n):{},this.documents.push({doc:e,options:n}),this.events.documents.push(e),e!==this.document&&this.events.add(o,"unload",this.onWindowUnload),this.fire("scope:add-document",{doc:e,window:o,scope:this,options:n})}},{key:"removeDocument",value:function(e){var n=this.getDocIndex(e),o=B(e),r=this.documents[n].options;this.events.remove(o,"unload",this.onWindowUnload),this.documents.splice(n,1),this.events.documents.splice(n,1),this.fire("scope:remove-document",{doc:e,window:o,scope:this,options:r})}},{key:"getDocIndex",value:function(e){for(var n=0;n<this.documents.length;n++)if(this.documents[n].doc===e)return n;return-1}},{key:"getDocOptions",value:function(e){var n=this.getDocIndex(e);return n===-1?null:this.documents[n].options}},{key:"now",value:function(){return(this.window.Date||Date).now()}}]),t})();function yn(t){return t&&t.replace(/\/.*$/,"")}var xn=new go,K=xn.interactStatic,vo=typeof globalThis<"u"?globalThis:window;xn.init(vo);var mo=Object.freeze({__proto__:null,edgeTarget:function(){},elements:function(){},grid:function(t){var e=[["x","y"],["left","top"],["right","bottom"],["width","height"]].filter((function(o){var r=o[0],i=o[1];return r in t||i in t})),n=function(o,r){for(var i=t.range,s=t.limits,l=s===void 0?{left:-1/0,right:1/0,top:-1/0,bottom:1/0}:s,p=t.offset,g=p===void 0?{x:0,y:0}:p,u={range:i,grid:t,x:null,y:null},v=0;v<e.length;v++){var y=e[v],_=y[0],k=y[1],E=Math.round((o-g.x)/t[_]),T=Math.round((r-g.y)/t[k]);u[_]=Math.max(l.left,Math.min(l.right,E*t[_]+g.x)),u[k]=Math.max(l.top,Math.min(l.bottom,T*t[k]+g.y))}return u};return n.grid=t,n.coordFields=e,n}}),bo={id:"snappers",install:function(t){var e=t.interactStatic;e.snappers=z(e.snappers||{},mo),e.createSnapGrid=e.snappers.grid}},yo=bo,xo={start:function(t){var e=t.state,n=t.rect,o=t.edges,r=t.pageCoords,i=e.options,s=i.ratio,l=i.enabled,p=e.options,g=p.equalDelta,u=p.modifiers;s==="preserve"&&(s=n.width/n.height),e.startCoords=z({},r),e.startRect=z({},n),e.ratio=s,e.equalDelta=g;var v=e.linkedEdges={top:o.top||o.left&&!o.bottom,left:o.left||o.top&&!o.right,bottom:o.bottom||o.right&&!o.top,right:o.right||o.bottom&&!o.left};if(e.xIsPrimaryAxis=!(!o.left&&!o.right),e.equalDelta){var y=(v.left?1:-1)*(v.top?1:-1);e.edgeSign={x:y,y}}else e.edgeSign={x:v.left?-1:1,y:v.top?-1:1};if(l!==!1&&z(o,v),u!=null&&u.length){var _=new mt(t.interaction);_.copyFrom(t.interaction.modification),_.prepareStates(u),e.subModification=_,_.startAll(j({},t))}},set:function(t){var e=t.state,n=t.rect,o=t.coords,r=e.linkedEdges,i=z({},o),s=e.equalDelta?wo:ko;if(z(t.edges,r),s(e,e.xIsPrimaryAxis,o,n),!e.subModification)return null;var l=z({},n);qe(r,l,{x:o.x-i.x,y:o.y-i.y});var p=e.subModification.setAll(j(j({},t),{},{rect:l,edges:r,pageCoords:o,prevCoords:o,prevRect:l})),g=p.delta;return p.changed&&(s(e,Math.abs(g.x)>Math.abs(g.y),p.coords,p.rect),z(o,p.coords)),p.eventProps},defaults:{ratio:"preserve",equalDelta:!1,modifiers:[],enabled:!1}};function wo(t,e,n){var o=t.startCoords,r=t.edgeSign;e?n.y=o.y+(n.x-o.x)*r.y:n.x=o.x+(n.y-o.y)*r.x}function ko(t,e,n,o){var r=t.startRect,i=t.startCoords,s=t.ratio,l=t.edgeSign;if(e){var p=o.width/s;n.y=i.y+(p-r.height)*l.y}else{var g=o.height*s;n.x=i.x+(g-r.width)*l.x}}var _o=ue(xo,"aspectRatio"),wn=function(){};wn._defaults={};var Ke=wn;function be(t,e,n){return x.func(t)?Ee(t,e.interactable,e.element,[n.x,n.y,e]):Ee(t,e.interactable,e.element)}var Ve={start:function(t){var e=t.rect,n=t.startOffset,o=t.state,r=t.interaction,i=t.pageCoords,s=o.options,l=s.elementRect,p=z({left:0,top:0,right:0,bottom:0},s.offset||{});if(e&&l){var g=be(s.restriction,r,i);if(g){var u=g.right-g.left-e.width,v=g.bottom-g.top-e.height;u<0&&(p.left+=u,p.right+=u),v<0&&(p.top+=v,p.bottom+=v)}p.left+=n.left-e.width*l.left,p.top+=n.top-e.height*l.top,p.right+=n.right-e.width*(1-l.right),p.bottom+=n.bottom-e.height*(1-l.bottom)}o.offset=p},set:function(t){var e=t.coords,n=t.interaction,o=t.state,r=o.options,i=o.offset,s=be(r.restriction,n,e);if(s){var l=(function(p){return!p||"left"in p&&"top"in p||((p=z({},p)).left=p.x||0,p.top=p.y||0,p.right=p.right||p.left+p.width,p.bottom=p.bottom||p.top+p.height),p})(s);e.x=Math.max(Math.min(l.right-i.right,e.x),l.left+i.left),e.y=Math.max(Math.min(l.bottom-i.bottom,e.y),l.top+i.top)}},defaults:{restriction:null,elementRect:null,offset:null,endOnly:!1,enabled:!1}},Eo=ue(Ve,"restrict"),kn={top:1/0,left:1/0,bottom:-1/0,right:-1/0},_n={top:-1/0,left:-1/0,bottom:1/0,right:1/0};function En(t,e){for(var n=0,o=["top","left","bottom","right"];n<o.length;n++){var r=o[n];r in t||(t[r]=e[r])}return t}var $e={noInner:kn,noOuter:_n,start:function(t){var e,n=t.interaction,o=t.startOffset,r=t.state,i=r.options;i&&(e=Ne(be(i.offset,n,n.coords.start.page))),e=e||{x:0,y:0},r.offset={top:e.y+o.top,left:e.x+o.left,bottom:e.y-o.bottom,right:e.x-o.right}},set:function(t){var e=t.coords,n=t.edges,o=t.interaction,r=t.state,i=r.offset,s=r.options;if(n){var l=z({},e),p=be(s.inner,o,l)||{},g=be(s.outer,o,l)||{};En(p,kn),En(g,_n),n.top?e.y=Math.min(Math.max(g.top+i.top,l.y),p.top+i.top):n.bottom&&(e.y=Math.max(Math.min(g.bottom+i.bottom,l.y),p.bottom+i.bottom)),n.left?e.x=Math.min(Math.max(g.left+i.left,l.x),p.left+i.left):n.right&&(e.x=Math.max(Math.min(g.right+i.right,l.x),p.right+i.right))}},defaults:{inner:null,outer:null,offset:null,endOnly:!1,enabled:!1}},So=ue($e,"restrictEdges"),To=z({get elementRect(){return{top:0,left:0,bottom:1,right:1}},set elementRect(t){}},Ve.defaults),Io=ue({start:Ve.start,set:Ve.set,defaults:To},"restrictRect"),zo={width:-1/0,height:-1/0},Mo={width:1/0,height:1/0},Do=ue({start:function(t){return $e.start(t)},set:function(t){var e=t.interaction,n=t.state,o=t.rect,r=t.edges,i=n.options;if(r){var s=ot(be(i.min,e,t.coords))||zo,l=ot(be(i.max,e,t.coords))||Mo;n.options={endOnly:i.endOnly,inner:z({},$e.noInner),outer:z({},$e.noOuter)},r.top?(n.options.inner.top=o.bottom-s.height,n.options.outer.top=o.bottom-l.height):r.bottom&&(n.options.inner.bottom=o.top+s.height,n.options.outer.bottom=o.top+l.height),r.left?(n.options.inner.left=o.right-s.width,n.options.outer.left=o.right-l.width):r.right&&(n.options.inner.right=o.left+s.width,n.options.outer.right=o.left+l.width),$e.set(t),n.options=i}},defaults:{min:null,max:null,endOnly:!1,enabled:!1}},"restrictSize"),_t={start:function(t){var e,n=t.interaction,o=t.interactable,r=t.element,i=t.rect,s=t.state,l=t.startOffset,p=s.options,g=p.offsetWithOrigin?(function(y){var _=y.interaction.element,k=Ne(Ee(y.state.options.origin,null,null,[_])),E=k||Se(y.interactable,_,y.interaction.prepared.name);return E})(t):{x:0,y:0};if(p.offset==="startCoords")e={x:n.coords.start.page.x,y:n.coords.start.page.y};else{var u=Ee(p.offset,o,r,[n]);(e=Ne(u)||{x:0,y:0}).x+=g.x,e.y+=g.y}var v=p.relativePoints;s.offsets=i&&v&&v.length?v.map((function(y,_){return{index:_,relativePoint:y,x:l.left-i.width*y.x+e.x,y:l.top-i.height*y.y+e.y}})):[{index:0,relativePoint:null,x:e.x,y:e.y}]},set:function(t){var e=t.interaction,n=t.coords,o=t.state,r=o.options,i=o.offsets,s=Se(e.interactable,e.element,e.prepared.name),l=z({},n),p=[];r.offsetWithOrigin||(l.x-=s.x,l.y-=s.y);for(var g=0,u=i;g<u.length;g++)for(var v=u[g],y=l.x-v.x,_=l.y-v.y,k=0,E=r.targets.length;k<E;k++){var T=r.targets[k],S=void 0;(S=x.func(T)?T(y,_,e._proxy,v,k):T)&&p.push({x:(x.number(S.x)?S.x:y)+v.x,y:(x.number(S.y)?S.y:_)+v.y,range:x.number(S.range)?S.range:r.range,source:T,index:k,offset:v})}for(var I={target:null,inRange:!1,distance:0,range:0,delta:{x:0,y:0}},$=0;$<p.length;$++){var A=p[$],M=A.range,O=A.x-l.x,V=A.y-l.y,F=Te(O,V),J=F<=M;M===1/0&&I.inRange&&I.range!==1/0&&(J=!1),I.target&&!(J?I.inRange&&M!==1/0?F/M<I.distance/I.range:M===1/0&&I.range!==1/0||F<I.distance:!I.inRange&&F<I.distance)||(I.target=A,I.distance=F,I.range=M,I.inRange=J,I.delta.x=O,I.delta.y=V)}return I.inRange&&(n.x=I.target.x,n.y=I.target.y),o.closest=I,I},defaults:{range:1/0,targets:null,offset:null,offsetWithOrigin:!0,origin:null,relativePoints:null,endOnly:!1,enabled:!1}},Po=ue(_t,"snap"),We={start:function(t){var e=t.state,n=t.edges,o=e.options;if(!n)return null;t.state={options:{targets:null,relativePoints:[{x:n.left?0:1,y:n.top?0:1}],offset:o.offset||"self",origin:{x:0,y:0},range:o.range}},e.targetFields=e.targetFields||[["width","height"],["x","y"]],_t.start(t),e.offsets=t.state.offsets,t.state=e},set:function(t){var e=t.interaction,n=t.state,o=t.coords,r=n.options,i=n.offsets,s={x:o.x-i[0].x,y:o.y-i[0].y};n.options=z({},r),n.options.targets=[];for(var l=0,p=r.targets||[];l<p.length;l++){var g=p[l],u=void 0;if(u=x.func(g)?g(s.x,s.y,e):g){for(var v=0,y=n.targetFields;v<y.length;v++){var _=y[v],k=_[0],E=_[1];if(k in u||E in u){u.x=u[k],u.y=u[E];break}}n.options.targets.push(u)}}var T=_t.set(t);return n.options=r,T},defaults:{range:1/0,targets:null,offset:null,endOnly:!1,enabled:!1}},Co=ue(We,"snapSize"),Et={aspectRatio:_o,restrictEdges:So,restrict:Eo,restrictRect:Io,restrictSize:Do,snapEdges:ue({start:function(t){var e=t.edges;return e?(t.state.targetFields=t.state.targetFields||[[e.left?"left":"right",e.top?"top":"bottom"]],We.start(t)):null},set:We.set,defaults:z(_e(We.defaults),{targets:void 0,range:void 0,offset:{x:0,y:0}})},"snapEdges"),snap:Po,snapSize:Co,spring:Ke,avoid:Ke,transform:Ke,rubberband:Ke},$o={id:"modifiers",install:function(t){var e=t.interactStatic;for(var n in t.usePlugin(an),t.usePlugin(yo),e.modifiers=Et,Et){var o=Et[n],r=o._defaults,i=o._methods;r._methods=i,t.defaults.perAction[n]=r}}},Oo=$o,Sn=(function(t){f(n,t);var e=P(n);function n(o,r,i,s,l,p){var g;if(a(this,n),je(w(g=e.call(this,l)),i),i!==r&&je(w(g),r),g.timeStamp=p,g.originalEvent=i,g.type=o,g.pointerId=Ie(r),g.pointerType=Rt(r),g.target=s,g.currentTarget=null,o==="tap"){var u=l.getPointerIndex(r);g.dt=g.timeStamp-l.pointers[u].downTime;var v=g.timeStamp-l.tapTime;g.double=!!l.prevTap&&l.prevTap.type!=="doubletap"&&l.prevTap.target===g.target&&v<500}else o==="doubletap"&&(g.dt=r.timeStamp-l.tapTime,g.double=!0);return g}return d(n,[{key:"_subtractOrigin",value:function(o){var r=o.x,i=o.y;return this.pageX-=r,this.pageY-=i,this.clientX-=r,this.clientY-=i,this}},{key:"_addOrigin",value:function(o){var r=o.x,i=o.y;return this.pageX+=r,this.pageY+=i,this.clientX+=r,this.clientY+=i,this}},{key:"preventDefault",value:function(){this.originalEvent.preventDefault()}}]),n})(Ge),Oe={id:"pointer-events/base",before:["inertia","modifiers","auto-start","actions"],install:function(t){t.pointerEvents=Oe,t.defaults.actions.pointerEvents=Oe.defaults,z(t.actions.phaselessTypes,Oe.types)},listeners:{"interactions:new":function(t){var e=t.interaction;e.prevTap=null,e.tapTime=0},"interactions:update-pointer":function(t){var e=t.down,n=t.pointerInfo;!e&&n.hold||(n.hold={duration:1/0,timeout:null})},"interactions:move":function(t,e){var n=t.interaction,o=t.pointer,r=t.event,i=t.eventTarget;t.duplicate||n.pointerIsDown&&!n.pointerWasMoved||(n.pointerIsDown&&St(t),fe({interaction:n,pointer:o,event:r,eventTarget:i,type:"move"},e))},"interactions:down":function(t,e){(function(n,o){for(var r=n.interaction,i=n.pointer,s=n.event,l=n.eventTarget,p=n.pointerIndex,g=r.pointers[p].hold,u=$t(l),v={interaction:r,pointer:i,event:s,eventTarget:l,type:"hold",targets:[],path:u,node:null},y=0;y<u.length;y++){var _=u[y];v.node=_,o.fire("pointerEvents:collect-targets",v)}if(v.targets.length){for(var k=1/0,E=0,T=v.targets;E<T.length;E++){var S=T[E].eventable.options.holdDuration;S<k&&(k=S)}g.duration=k,g.timeout=setTimeout((function(){fe({interaction:r,eventTarget:l,pointer:i,event:s,type:"hold"},o)}),k)}})(t,e),fe(t,e)},"interactions:up":function(t,e){St(t),fe(t,e),(function(n,o){var r=n.interaction,i=n.pointer,s=n.event,l=n.eventTarget;r.pointerWasMoved||fe({interaction:r,eventTarget:l,pointer:i,event:s,type:"tap"},o)})(t,e)},"interactions:cancel":function(t,e){St(t),fe(t,e)}},PointerEvent:Sn,fire:fe,collectEventTargets:Tn,defaults:{holdDuration:600,ignoreFrom:null,allowFrom:null,origin:{x:0,y:0}},types:{down:!0,move:!0,up:!0,cancel:!0,tap:!0,doubletap:!0,hold:!0}};function fe(t,e){var n=t.interaction,o=t.pointer,r=t.event,i=t.eventTarget,s=t.type,l=t.targets,p=l===void 0?Tn(t,e):l,g=new Sn(s,o,r,i,n,e.now());e.fire("pointerEvents:new",{pointerEvent:g});for(var u={interaction:n,pointer:o,event:r,eventTarget:i,targets:p,type:s,pointerEvent:g},v=0;v<p.length;v++){var y=p[v];for(var _ in y.props||{})g[_]=y.props[_];var k=Se(y.eventable,y.node);if(g._subtractOrigin(k),g.eventable=y.eventable,g.currentTarget=y.node,y.eventable.fire(g),g._addOrigin(k),g.immediatePropagationStopped||g.propagationStopped&&v+1<p.length&&p[v+1].node!==g.currentTarget)break}if(e.fire("pointerEvents:fired",u),s==="tap"){var E=g.double?fe({interaction:n,pointer:o,event:r,eventTarget:i,type:"doubletap"},e):g;n.prevTap=E,n.tapTime=E.timeStamp}return g}function Tn(t,e){var n=t.interaction,o=t.pointer,r=t.event,i=t.eventTarget,s=t.type,l=n.getPointerIndex(o),p=n.pointers[l];if(s==="tap"&&(n.pointerWasMoved||!p||p.downTarget!==i))return[];for(var g=$t(i),u={interaction:n,pointer:o,event:r,eventTarget:i,type:s,path:g,targets:[],node:null},v=0;v<g.length;v++){var y=g[v];u.node=y,e.fire("pointerEvents:collect-targets",u)}return s==="hold"&&(u.targets=u.targets.filter((function(_){var k,E;return _.eventable.options.holdDuration===((k=n.pointers[l])==null||(E=k.hold)==null?void 0:E.duration)}))),u.targets}function St(t){var e=t.interaction,n=t.pointerIndex,o=e.pointers[n].hold;o&&o.timeout&&(clearTimeout(o.timeout),o.timeout=null)}var Lo=Object.freeze({__proto__:null,default:Oe});function Ao(t){var e=t.interaction;e.holdIntervalHandle&&(clearInterval(e.holdIntervalHandle),e.holdIntervalHandle=null)}var No={id:"pointer-events/holdRepeat",install:function(t){t.usePlugin(Oe);var e=t.pointerEvents;e.defaults.holdRepeatInterval=0,e.types.holdrepeat=t.actions.phaselessTypes.holdrepeat=!0},listeners:["move","up","cancel","endall"].reduce((function(t,e){return t["pointerEvents:".concat(e)]=Ao,t}),{"pointerEvents:new":function(t){var e=t.pointerEvent;e.type==="hold"&&(e.count=(e.count||0)+1)},"pointerEvents:fired":function(t,e){var n=t.interaction,o=t.pointerEvent,r=t.eventTarget,i=t.targets;if(o.type==="hold"&&i.length){var s=i[0].eventable.options.holdRepeatInterval;s<=0||(n.holdIntervalHandle=setTimeout((function(){e.pointerEvents.fire({interaction:n,eventTarget:r,type:"hold",pointer:o,event:o},e)}),s))}}})},qo=No,jo={id:"pointer-events/interactableTargets",install:function(t){var e=t.Interactable;e.prototype.pointerEvents=function(o){return z(this.events.options,o),this};var n=e.prototype._backCompatOption;e.prototype._backCompatOption=function(o,r){var i=n.call(this,o,r);return i===this&&(this.events.options[o]=r),i}},listeners:{"pointerEvents:collect-targets":function(t,e){var n=t.targets,o=t.node,r=t.type,i=t.eventTarget;e.interactables.forEachMatch(o,(function(s){var l=s.events,p=l.options;l.types[r]&&l.types[r].length&&s.testIgnoreAllow(p,o,i)&&n.push({node:o,eventable:l,props:{interactable:s}})}))},"interactable:new":function(t){var e=t.interactable;e.events.getRect=function(n){return e.getRect(n)}},"interactable:set":function(t,e){var n=t.interactable,o=t.options;z(n.events.options,e.pointerEvents.defaults),z(n.events.options,o.pointerEvents||{})}}},Fo=jo,Ro={id:"pointer-events",install:function(t){t.usePlugin(Lo),t.usePlugin(qo),t.usePlugin(Fo)}},Go=Ro,Bo={id:"reflow",install:function(t){var e=t.Interactable;t.actions.phases.reflow=!0,e.prototype.reflow=function(n){return(function(o,r,i){for(var s=o.getAllElements(),l=i.window.Promise,p=l?[]:null,g=function(){var v=s[u],y=o.getRect(v);if(!y)return 1;var _,k=Me(i.interactions.list,(function(S){return S.interacting()&&S.interactable===o&&S.element===v&&S.prepared.name===r.name}));if(k)k.move(),p&&(_=k._reflowPromise||new l((function(S){k._reflowResolve=S})));else{var E=ot(y),T=(function(S){return{coords:S,get page(){return this.coords.page},get client(){return this.coords.client},get timeStamp(){return this.coords.timeStamp},get pageX(){return this.coords.page.x},get pageY(){return this.coords.page.y},get clientX(){return this.coords.client.x},get clientY(){return this.coords.client.y},get pointerId(){return this.coords.pointerId},get target(){return this.coords.target},get type(){return this.coords.type},get pointerType(){return this.coords.pointerType},get buttons(){return this.coords.buttons},preventDefault:function(){}}})({page:{x:E.x,y:E.y},client:{x:E.x,y:E.y},timeStamp:i.now()});_=(function(S,I,$,A,M){var O=S.interactions.new({pointerType:"reflow"}),V={interaction:O,event:M,pointer:M,eventTarget:$,phase:"reflow"};O.interactable=I,O.element=$,O.prevEvent=M,O.updatePointer(M,M,$,!0),Nt(O.coords.delta),ht(O.prepared,A),O._doPhase(V);var F=S.window,J=F.Promise,oe=J?new J((function(le){O._reflowResolve=le})):void 0;return O._reflowPromise=oe,O.start(A,I,$),O._interacting?(O.move(V),O.end(M)):(O.stop(),O._reflowResolve()),O.removePointer(M,M),oe})(i,o,v,r,T)}p&&p.push(_)},u=0;u<s.length&&!g();u++);return p&&l.all(p).then((function(){return o}))})(this,n,t)}},listeners:{"interactions:stop":function(t,e){var n=t.interaction;n.pointerType==="reflow"&&(n._reflowResolve&&n._reflowResolve(),(function(o,r){o.splice(o.indexOf(r),1)})(e.interactions.list,n))}}},Ho=Bo;if(K.use(rn),K.use(pn),K.use(Go),K.use(ao),K.use(Oo),K.use(Kn),K.use(Nn),K.use(jn),K.use(Ho),K.default=K,(typeof ye>"u"?"undefined":Y(ye))==="object"&&ye)try{ye.exports=K}catch{}return K.default=K,K}))});var nr={};Zo(nr,{workshopBoard:()=>It});var W=Jo(zn()),re={yellow:"#fbbf24",blue:"#60a5fa",green:"#4ade80",pink:"#f472b6",purple:"#a78bfa",orange:"#fb923c",teal:"#2dd4bf",red:"#f87171"};var Mn={note:{width:200,height:150,color:"yellow"},text:{width:300,height:40,color:"yellow"},section:{width:500,height:400,color:"yellow"},shape:{width:120,height:120,color:"blue"},connector:{width:0,height:0,color:"blue"},kanban:{width:600,height:400,color:"blue"},image:{width:300,height:300,color:"yellow"},image_grid:{width:500,height:400,color:"yellow"},video:{width:480,height:300,color:"blue"}},te='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:12px;height:12px;"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>';function It({notes:N=[],canvasBlocks:j=[],gridLayout:Y={}}={}){return{panX:0,panY:0,scale:1,_isPanning:!1,_panStart:null,_panButton:-1,_spaceDown:!1,_listeners:[],_saveTimers:{},_textTimers:{},_kanbanTimers:{},_nextTempId:-1,_pendingUploadTarget:null,_mediaTimers:{},_lockedNoteIds:new Set,colorPickerOpen:null,_connectorMode:!1,_connectorFrom:null,_svgLayer:null,colors:Object.keys(re),isFullscreen:!1,init(){this._initialized||(this._initialized=!0,this.$nextTick(()=>{let a=document.createElementNS("http://www.w3.org/2000/svg","svg");a.classList.add("workshop-connectors-layer"),a.setAttribute("style","position:absolute;inset:0;width:100%;height:100%;pointer-events:none;overflow:visible;"),a.innerHTML=`<defs>
          <marker id="arrowhead" markerWidth="10" markerHeight="7" refX="9" refY="3.5" orient="auto">
            <polygon points="0 0, 10 3.5, 0 7" fill="#6b7280"/>
          </marker>
        </defs>`,this.$refs.board.prepend(a),this._svgLayer=a;let c=document.createElement("input");c.type="file",c.id="workshop-file-input",c.style.display="none",c.accept="image/*,video/*",this.$refs.board.parentElement.appendChild(c),this._fileInput=c,this._uploadBusy=!1,c.addEventListener("change",d=>{let h=d.target.files[0];!h||this._uploadBusy||(this._uploadBusy=!0,this.$wire.upload("workshopFile",h,()=>{},()=>{console.error("Upload failed"),this._uploadBusy=!1,this._pendingUploadTarget=null},f=>{}),c.value="")}),this.$wire.on("workshop-file-uploaded",([d])=>{this._uploadBusy=!1;let h=this._pendingUploadTarget;if(this._pendingUploadTarget=null,!h||!h.noteEl)return;let f=h.noteEl,m=parseInt(f.dataset.noteId);h.type==="image"?(this._applyImageUpload(f,d),m>0&&(this._savePos(m,f),this._saveMediaMetadata(f,m))):h.type==="image_grid"?(this._applyImageGridUpload(f,d),m>0&&this._saveMediaMetadata(f,m)):h.type==="video"&&(this._applyVideoUpload(f,d),m>0&&this._saveMediaMetadata(f,m))}),this.$wire.on("workshop-note-changed",([d])=>{this._handleRemoteChange(d)}),this._renderNotes(N),this._initPanZoom(),this._initInteract(),this._fitGrid(),this._on(document,"keydown",d=>{if(d.key==="Escape"){if(this._connectorMode){d.preventDefault(),this._cancelConnectorMode();return}this.isFullscreen&&(d.preventDefault(),this.isFullscreen=!1,this._fitAfterDelay())}},!1)}))},destroy(){this._listeners.forEach(([a,c,d,h])=>a.removeEventListener(c,d,h)),this._listeners=[],(0,W.default)(".workshop-note").unset(),(0,W.default)(".workshop-text").unset(),(0,W.default)(".workshop-section").unset(),(0,W.default)(".workshop-shape").unset(),(0,W.default)(".workshop-kanban").unset(),(0,W.default)(".workshop-image").unset(),(0,W.default)(".workshop-image-grid").unset(),(0,W.default)(".workshop-video").unset(),(0,W.default)(".workshop-canvas-background").unset(),this._fileInput&&(this._fileInput.remove(),this._fileInput=null)},_on(a,c,d,h){a.addEventListener(c,d,h),this._listeners.push([a,c,d,h])},_renderNotes(a){let c=this.$refs.board;a.forEach(d=>c.appendChild(this._createNoteEl(d)))},_createNoteEl(a){switch(a.type||"note"){case"text":return this._createTextEl(a);case"section":return this._createSectionEl(a);case"shape":return this._createShapeEl(a);case"connector":return this._createConnectorEl(a);case"kanban":return this._createKanbanEl(a);case"image":return this._createImageEl(a);case"image_grid":return this._createImageGridEl(a);case"video":return this._createVideoEl(a);default:return this._createStickyEl(a)}},_createStickyEl(a){let c=a.color||"yellow",d=a.x??0,h=a.y??0,f=a.width??200,m=a.height??150,b=document.createElement("div");return b.className=`workshop-note workshop-note-${c}`,b.dataset.noteId=a.id,b.dataset.noteType="note",b.dataset.x=d,b.dataset.y=h,b.style.cssText=`width:${f}px;height:${m}px;transform:translate(${d}px,${h}px);`,b.innerHTML=`
        <div class="drag-handle">
          <div style="display:flex;align-items:center;gap:6px;">
            <div class="drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></div>
            ${this._colorDotHTML(c)}
          </div>
          <button class="note-delete" data-action="delete" title="Loeschen">${te}</button>
        </div>
        <div class="note-body">
          <input type="text" value="${this._esc(a.title||"")}" placeholder="Titel..." />
          <textarea placeholder="Notiz...">${this._esc(a.content||"")}</textarea>
        </div>
        <div class="resize-handle"></div>
      `,this._bindNoteEvents(b),this._bindTextSave(b),b},_createTextEl(a){let c=a.x??0,d=a.y??0,h=a.width??300,f=a.height??40,m=a.metadata?.fontSize||Math.max(14,Math.round(h/12)),b=document.createElement("div");return b.className="workshop-text",b.dataset.noteId=a.id,b.dataset.noteType="text",b.dataset.x=c,b.dataset.y=d,b.style.cssText=`width:${h}px;height:${f}px;transform:translate(${c}px,${d}px);`,b.innerHTML=`
        <div class="drag-handle text-drag-handle">
          <div class="drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></div>
          <div class="text-body">
            <input type="text" value="${this._esc(a.title||"")}" placeholder="Text eingeben..." style="font-size:${m}px;" />
          </div>
          <button class="note-delete" data-action="delete" title="Loeschen">${te}</button>
        </div>
        <div class="resize-handle"></div>
      `,this._bindDeleteEvent(b),this._bindTextInputSave(b),b},_createSectionEl(a){let c=a.color||"yellow",d=a.x??0,h=a.y??0,f=a.width??500,m=a.height??400,b=document.createElement("div");return b.className=`workshop-section workshop-section-${c}`,b.dataset.noteId=a.id,b.dataset.noteType="section",b.dataset.x=d,b.dataset.y=h,b.style.cssText=`width:${f}px;height:${m}px;transform:translate(${d}px,${h}px);border-color:${re[c]||re.yellow};`,b.innerHTML=`
        <div class="drag-handle section-drag-handle">
          <div style="display:flex;align-items:center;gap:6px;flex:1;min-width:0;">
            <div class="drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></div>
            ${this._colorDotHTML(c)}
            <input type="text" class="section-title" value="${this._esc(a.title||"")}" placeholder="Section..." />
          </div>
          <button class="note-delete" data-action="delete" title="Loeschen">${te}</button>
        </div>
        <div class="resize-handle"></div>
      `,this._bindNoteEvents(b),this._bindSectionTextSave(b),b},_createShapeEl(a){let c=a.color||"blue",d=a.metadata?.shape||"rect",h=a.x??0,f=a.y??0,m=a.width??120,b=a.height??120,w=document.createElement("div");return w.className=`workshop-shape workshop-shape-${d} workshop-shape-color-${c}`,w.dataset.noteId=a.id,w.dataset.noteType="shape",w.dataset.shape=d,w.dataset.x=h,w.dataset.y=f,w.style.cssText=`width:${m}px;height:${b}px;transform:translate(${h}px,${f}px);`,w.innerHTML=`
        <div class="shape-visual"></div>
        <div class="drag-handle shape-drag-handle">
          <div style="display:flex;align-items:center;gap:4px;">
            <div class="drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></div>
            ${this._colorDotHTML(c)}
            <button class="shape-toggle" data-action="toggle-shape" title="Form wechseln">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:10px;height:10px;"><path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H4.598a.75.75 0 00-.75.75v3.634a.75.75 0 001.5 0v-2.033l.312.312a7 7 0 0011.712-3.138.75.75 0 00-1.449-.39zm1.06-7.846a.75.75 0 00-1.5 0v2.034l-.312-.312A7 7 0 002.848 8.438a.75.75 0 001.449.39 5.5 5.5 0 019.201-2.466l.312.311H11.38a.75.75 0 000 1.5h3.634a.75.75 0 00.75-.75V3.578z" clip-rule="evenodd"/></svg>
            </button>
            <button class="note-delete" data-action="delete" title="Loeschen">${te}</button>
          </div>
        </div>
        <div class="shape-body">
          <input type="text" value="${this._esc(a.title||"")}" placeholder="..." />
        </div>
        <div class="resize-handle"></div>
      `,this._bindShapeEvents(w),this._bindShapeTextSave(w),w},_colorDotHTML(a){return`<div class="color-dot-wrap" style="position:relative;">
        <div class="color-dot" style="background:${re[a]||re.yellow};" data-action="color"></div>
        <div class="color-picker-dd" style="display:none;position:absolute;top:100%;left:0;margin-top:4px;padding:4px;background:white;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.15);border:1px solid #e5e7eb;z-index:50;gap:3px;flex-wrap:nowrap;">
          ${this.colors.map(c=>`<div class="color-dot${c===a?" active":""}" style="background:${re[c]};" data-pick-color="${c}"></div>`).join("")}
        </div>
      </div>`},_bindNoteEvents(a){a.addEventListener("click",c=>{if(this._handleConnectorClick(a)){c.stopPropagation();return}let d=c.target.closest("[data-action]")?.dataset.action,h=c.target.closest("[data-pick-color]")?.dataset.pickColor,f=parseInt(a.dataset.noteId);if(h){c.stopPropagation(),this._changeColor(a,f,h);return}if(d==="color"){c.stopPropagation(),this._toggleColorPicker(a);return}if(d==="delete"){c.stopPropagation(),confirm("Element loeschen?")&&this._deleteNote(a,f);return}})},_bindDeleteEvent(a){a.addEventListener("click",c=>{if(this._handleConnectorClick(a)){c.stopPropagation();return}let d=c.target.closest("[data-action]")?.dataset.action,h=parseInt(a.dataset.noteId);d==="delete"&&(c.stopPropagation(),confirm("Element loeschen?")&&this._deleteNote(a,h))})},_bindShapeEvents(a){a.addEventListener("click",c=>{if(this._handleConnectorClick(a)){c.stopPropagation();return}let d=c.target.closest("[data-action]")?.dataset.action,h=c.target.closest("[data-pick-color]")?.dataset.pickColor,f=parseInt(a.dataset.noteId);if(h){c.stopPropagation(),this._changeShapeColor(a,f,h);return}if(d==="color"){c.stopPropagation(),this._toggleColorPicker(a);return}if(d==="toggle-shape"){c.stopPropagation(),this._toggleShape(a,f);return}if(d==="delete"){c.stopPropagation(),confirm("Element loeschen?")&&this._deleteNote(a,f);return}})},_bindTextSave(a){let c=a.querySelector(".note-body input"),d=a.querySelector(".note-body textarea"),h=()=>{let b=parseInt(a.dataset.noteId);b>0&&this._lockedNoteIds.add(b)},f=()=>{let b=parseInt(a.dataset.noteId);b>0&&this._lockedNoteIds.delete(b)},m=()=>{f();let b=parseInt(a.dataset.noteId);b<0||(clearTimeout(this._textTimers[b]),this._textTimers[b]=setTimeout(()=>{this.$wire.call("updateNoteText",b,c.value,d.value)},400))};c.addEventListener("focus",h),d.addEventListener("focus",h),c.addEventListener("blur",m),d.addEventListener("blur",m),c.addEventListener("keydown",b=>{b.key==="Enter"&&b.target.blur()})},_bindTextInputSave(a){let c=a.querySelector(".text-body input"),d=()=>{let m=parseInt(a.dataset.noteId);m>0&&this._lockedNoteIds.add(m)},h=()=>{let m=parseInt(a.dataset.noteId);m>0&&this._lockedNoteIds.delete(m)},f=()=>{h();let m=parseInt(a.dataset.noteId);m<0||(clearTimeout(this._textTimers[m]),this._textTimers[m]=setTimeout(()=>{this.$wire.call("updateNoteText",m,c.value,"")},400))};c.addEventListener("focus",d),c.addEventListener("blur",f),c.addEventListener("keydown",m=>{m.key==="Enter"&&m.target.blur()})},_bindSectionTextSave(a){let c=a.querySelector(".section-title"),d=()=>{let m=parseInt(a.dataset.noteId);m>0&&this._lockedNoteIds.add(m)},h=()=>{let m=parseInt(a.dataset.noteId);m>0&&this._lockedNoteIds.delete(m)},f=()=>{h();let m=parseInt(a.dataset.noteId);m<0||(clearTimeout(this._textTimers[m]),this._textTimers[m]=setTimeout(()=>{this.$wire.call("updateNoteText",m,c.value,"")},400))};c.addEventListener("focus",d),c.addEventListener("blur",f),c.addEventListener("keydown",m=>{m.key==="Enter"&&m.target.blur()})},_bindShapeTextSave(a){let c=a.querySelector(".shape-body input"),d=()=>{let m=parseInt(a.dataset.noteId);m>0&&this._lockedNoteIds.add(m)},h=()=>{let m=parseInt(a.dataset.noteId);m>0&&this._lockedNoteIds.delete(m)},f=()=>{h();let m=parseInt(a.dataset.noteId);m<0||(clearTimeout(this._textTimers[m]),this._textTimers[m]=setTimeout(()=>{this.$wire.call("updateNoteText",m,c.value,"")},400))};c.addEventListener("focus",d),c.addEventListener("blur",f),c.addEventListener("keydown",m=>{m.key==="Enter"&&m.target.blur()})},_createKanbanEl(a){let c=a.color||"blue",d=a.x??0,h=a.y??0,f=a.width??600,m=a.height??400,b=a.metadata?.columns||[],w=document.createElement("div");w.className=`workshop-kanban workshop-kanban-${c}`,w.dataset.noteId=a.id,w.dataset.noteType="kanban",w.dataset.x=d,w.dataset.y=h,w.style.cssText=`width:${f}px;height:${m}px;transform:translate(${d}px,${h}px);`,w._kanbanData={columns:JSON.parse(JSON.stringify(b))},w.innerHTML=`
        <div class="drag-handle kanban-drag-handle">
          <div style="display:flex;align-items:center;gap:6px;flex:1;min-width:0;">
            <div class="drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></div>
            ${this._colorDotHTML(c)}
            <input type="text" class="kanban-board-title" value="${this._esc(a.title||"")}" placeholder="Board..." />
          </div>
          <div style="display:flex;align-items:center;gap:4px;">
            <button class="kanban-add-col-btn" data-kanban-action="add-column" title="Spalte hinzufuegen">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:12px;height:12px;"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/></svg>
            </button>
            <button class="note-delete" data-action="delete" title="Loeschen">${te}</button>
          </div>
        </div>
        <div class="kanban-columns"></div>
        <div class="resize-handle"></div>
      `;let P=w.querySelector(".kanban-columns");return w._kanbanData.columns.forEach(C=>{P.appendChild(this._createKanbanColumnEl(w,C))}),this._bindKanbanEvents(w),this._bindNoteEvents(w),this._bindKanbanTitleSave(w),w},_createKanbanColumnEl(a,c){let d=document.createElement("div");d.className="kanban-column",d.dataset.colId=c.id;let h=c.cards?.length||0,f=c.wipLimit>0?`${h}/${c.wipLimit}`:`${h}`,m=c.wipLimit>0&&h>c.wipLimit;d.innerHTML=`
        <div class="kanban-column-header${m?" wip-exceeded":""}">
          <div style="display:flex;align-items:center;gap:4px;flex:1;min-width:0;">
            <input type="text" class="kanban-col-title" value="${this._esc(c.title||"")}" placeholder="Spalte..." />
            <span class="kanban-card-count">${f}</span>
          </div>
          <button class="kanban-col-delete" data-kanban-action="delete-column" data-col-id="${c.id}" title="Spalte loeschen">${te}</button>
        </div>
        <div class="kanban-cards"></div>
        <button class="kanban-add-card" data-kanban-action="add-card" data-col-id="${c.id}">+ Karte</button>
      `;let b=d.querySelector(".kanban-cards");(c.cards||[]).forEach(P=>{b.appendChild(this._createKanbanCardEl(a,P))});let w=d.querySelector(".kanban-col-title");return w.addEventListener("blur",()=>{c.title=w.value,this._saveKanbanMetadata(a)}),w.addEventListener("keydown",P=>{P.key==="Enter"&&P.target.blur()}),d},_createKanbanCardEl(a,c){let d=document.createElement("div");d.className="kanban-card",d.dataset.cardId=c.id,d.innerHTML=`
        <div style="display:flex;align-items:center;gap:4px;">
          <span class="kanban-card-grip">&#x2630;</span>
          <input type="text" class="kanban-card-title" value="${this._esc(c.title||"")}" placeholder="Karte..." />
          <button class="kanban-card-delete" data-kanban-action="delete-card" data-card-id="${c.id}" title="Karte loeschen">${te}</button>
        </div>
      `,this._bindKanbanCardDrag(a,d,c);let h=d.querySelector(".kanban-card-title");return h.addEventListener("blur",()=>{c.title=h.value,this._saveKanbanMetadata(a)}),h.addEventListener("keydown",f=>{f.key==="Enter"&&f.target.blur()}),d},_bindKanbanCardDrag(a,c,d){let h=!1,f=null,m=0,b=0,w=null,C=c.querySelector(".kanban-card-grip")||c;C.addEventListener("pointerdown",q=>{if(q.button!==0||q.target.closest("input, button"))return;q.preventDefault(),q.stopPropagation(),h=!0,w=c.closest(".kanban-column")?.dataset.colId||"";let R=c.getBoundingClientRect();m=q.clientY,b=q.clientY-R.top,f=document.createElement("div"),f.className="kanban-card-placeholder",f.style.height=R.height+"px",c.parentNode.insertBefore(f,c),c.classList.add("kanban-card-floating"),c.style.width=R.width+"px",c.style.position="fixed",c.style.left=R.left+"px",c.style.top=R.top+"px",c.style.zIndex="9999",C.setPointerCapture(q.pointerId)}),C.addEventListener("pointermove",q=>{if(!h)return;q.preventDefault(),q.stopPropagation(),c.style.top=q.clientY-b+"px";let R=a.querySelectorAll(".kanban-column"),G=null;for(let H of R){let B=H.getBoundingClientRect();if(q.clientX>=B.left&&q.clientX<=B.right){G=H;break}}if(R.forEach(H=>H.classList.remove("kanban-drop-target")),G){G.classList.add("kanban-drop-target");let H=G.querySelector(".kanban-cards"),B=[...H.querySelectorAll(".kanban-card:not(.kanban-card-floating)")],U=null;for(let Z of B){let x=Z.getBoundingClientRect();if(q.clientY<x.top+x.height/2){U=Z;break}}(f.parentNode!==H||f.nextSibling!==U)&&H.insertBefore(f,U)}});let L=q=>{if(!h)return;h=!1,q?.stopPropagation();let R=f?.closest(".kanban-column"),G=R?.dataset.colId||w;c.classList.remove("kanban-card-floating"),c.style.position="",c.style.left="",c.style.top="",c.style.width="",c.style.zIndex="",f?.parentNode&&(f.parentNode.insertBefore(c,f),f.remove()),f=null,a.querySelectorAll(".kanban-column").forEach(Z=>Z.classList.remove("kanban-drop-target"));let H=a._kanbanData,B=H.columns.find(Z=>Z.id===w),U=H.columns.find(Z=>Z.id===G);if(B&&U){if(U.wipLimit>0&&U.cards.length>=U.wipLimit&&w!==G){let ie=a.querySelector(`[data-col-id="${w}"] .kanban-cards`);ie&&ie.appendChild(c);return}let Z=B.cards.findIndex(ie=>ie.id===d.id);Z!==-1&&B.cards.splice(Z,1);let xe=[...R.querySelector(".kanban-cards").querySelectorAll(".kanban-card")].indexOf(c);xe>=0&&xe<U.cards.length?U.cards.splice(xe,0,d):U.cards.push(d),this._updateKanbanCounts(a),this._saveKanbanMetadata(a)}w=null};C.addEventListener("pointerup",L),C.addEventListener("pointercancel",L)},_bindKanbanEvents(a){a.addEventListener("click",c=>{let d=c.target.closest("[data-kanban-action]")?.dataset.kanbanAction;if(d){if(d==="add-column"){c.stopPropagation();let h={id:"col_"+Date.now().toString(36),title:"",wipLimit:0,cards:[]};a._kanbanData.columns.push(h);let f=this._createKanbanColumnEl(a,h);a.querySelector(".kanban-columns").appendChild(f),this._saveKanbanMetadata(a),setTimeout(()=>f.querySelector(".kanban-col-title")?.focus(),50);return}if(d==="add-card"){c.stopPropagation();let h=c.target.closest("[data-col-id]")?.dataset.colId,f=a._kanbanData.columns.find(P=>P.id===h);if(!f||f.wipLimit>0&&f.cards.length>=f.wipLimit)return;let m={id:"card_"+Date.now().toString(36),title:"",content:""};f.cards.push(m);let b=a.querySelector(`[data-col-id="${h}"]`),w=this._createKanbanCardEl(a,m);b.querySelector(".kanban-cards").appendChild(w),this._updateKanbanCounts(a),this._saveKanbanMetadata(a),setTimeout(()=>w.querySelector(".kanban-card-title")?.focus(),50);return}if(d==="delete-column"){c.stopPropagation();let h=c.target.closest("[data-col-id]")?.dataset.colId;a._kanbanData.columns=a._kanbanData.columns.filter(f=>f.id!==h),a.querySelector(`[data-col-id="${h}"]`)?.remove(),this._saveKanbanMetadata(a);return}if(d==="delete-card"){c.stopPropagation();let h=c.target.closest("[data-card-id]")?.dataset.cardId;for(let f of a._kanbanData.columns)f.cards=f.cards.filter(m=>m.id!==h);a.querySelector(`[data-card-id="${h}"]`)?.remove(),this._updateKanbanCounts(a),this._saveKanbanMetadata(a);return}}})},_bindKanbanTitleSave(a){let c=a.querySelector(".kanban-board-title"),d=()=>{let m=parseInt(a.dataset.noteId);m>0&&this._lockedNoteIds.add(m)},h=()=>{let m=parseInt(a.dataset.noteId);m>0&&this._lockedNoteIds.delete(m)},f=()=>{h();let m=parseInt(a.dataset.noteId);m<0||(clearTimeout(this._textTimers[m]),this._textTimers[m]=setTimeout(()=>{this.$wire.call("updateNoteText",m,c.value,"")},400))};c.addEventListener("focus",d),c.addEventListener("blur",f),c.addEventListener("keydown",m=>{m.key==="Enter"&&m.target.blur()})},_saveKanbanMetadata(a){let c=parseInt(a.dataset.noteId);c<0||(clearTimeout(this._kanbanTimers[c]),this._kanbanTimers[c]=setTimeout(()=>{this.$wire.call("updateNoteMetadata",c,{columns:a._kanbanData.columns})},400))},_updateKanbanCounts(a){a._kanbanData.columns.forEach(d=>{let h=a.querySelector(`[data-col-id="${d.id}"]`);if(!h)return;let f=d.cards.length,m=d.wipLimit>0?`${f}/${d.wipLimit}`:`${f}`,b=h.querySelector(".kanban-column-header"),w=h.querySelector(".kanban-card-count");w&&(w.textContent=m),b&&b.classList.toggle("wip-exceeded",d.wipLimit>0&&f>d.wipLimit)})},_createImageEl(a){let c=a.color||"yellow",d=a.x??0,h=a.y??0,f=a.width??300,m=a.height??300,b=a.metadata||{},w=document.createElement("div");w.className=`workshop-image workshop-image-${c}`,w.dataset.noteId=a.id,w.dataset.noteType="image",w.dataset.x=d,w.dataset.y=h,w.style.cssText=`width:${f}px;height:${m}px;transform:translate(${d}px,${h}px);`,w._imageData={contextFileId:b.contextFileId||null,src:b.src||"",alt:b.alt||"",objectFit:b.objectFit||"cover"};let P=!!b.src;return w.innerHTML=`
        <div class="drag-handle image-drag-handle">
          <div style="display:flex;align-items:center;gap:6px;">
            <div class="drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></div>
            ${this._colorDotHTML(c)}
          </div>
          <div style="display:flex;align-items:center;gap:4px;">
            <input type="text" class="image-alt-input" value="${this._esc(b.alt||"")}" placeholder="Alt..." title="Bildbeschreibung" />
            <button class="note-delete" data-action="delete" title="Loeschen">${te}</button>
          </div>
        </div>
        <div class="image-container">
          ${P?`<img src="${this._esc(b.src)}" alt="${this._esc(b.alt||"")}" style="object-fit:${b.objectFit||"cover"};" />`:`<div class="image-upload-zone" data-action="upload-image">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:32px;height:32px;"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" /></svg>
                <span>Bild hochladen</span>
              </div>`}
        </div>
        <div class="resize-handle"></div>
      `,this._bindNoteEvents(w),this._bindImageEvents(w),w},_bindImageEvents(a){a.addEventListener("click",d=>{if(d.target.closest('[data-action="upload-image"]')){if(d.stopPropagation(),this._uploadBusy)return;this._pendingUploadTarget={noteEl:a,type:"image"},this._fileInput.accept="image/*",this._fileInput.click()}});let c=a.querySelector(".image-alt-input");c&&(c.addEventListener("blur",()=>{a._imageData.alt=c.value;let d=a.querySelector(".image-container img");d&&(d.alt=c.value);let h=parseInt(a.dataset.noteId);h>0&&this._saveMediaMetadata(a,h)}),c.addEventListener("keydown",d=>{d.key==="Enter"&&d.target.blur()}))},_applyImageUpload(a,c){a._imageData.contextFileId=c.contextFileId,a._imageData.src=c.url;let d=a.querySelector(".image-container");if(d.innerHTML=`<img src="${this._esc(c.url)}" alt="${this._esc(a._imageData.alt)}" style="object-fit:${a._imageData.objectFit};" />`,c.width&&c.height){let h=parseInt(a.style.width)||300,f=c.width/c.height,m=Math.round(h/f);a.style.height=m+"px"}},_createImageGridEl(a){let c=a.color||"yellow",d=a.x??0,h=a.y??0,f=a.width??500,m=a.height??400,b=a.metadata||{},w=b.images||[],P=b.columns||2,C=b.gap||4,L=document.createElement("div");L.className=`workshop-image-grid workshop-image-grid-${c}`,L.dataset.noteId=a.id,L.dataset.noteType="image_grid",L.dataset.x=d,L.dataset.y=h,L.style.cssText=`width:${f}px;height:${m}px;transform:translate(${d}px,${h}px);`,L._imageGridData={images:JSON.parse(JSON.stringify(w)),columns:P,gap:C},L.innerHTML=`
        <div class="drag-handle image-grid-drag-handle">
          <div style="display:flex;align-items:center;gap:6px;">
            <div class="drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></div>
            ${this._colorDotHTML(c)}
          </div>
          <div style="display:flex;align-items:center;gap:4px;">
            <div class="image-grid-cols-control">
              <button data-grid-action="cols-dec" title="Weniger Spalten">-</button>
              <span class="image-grid-cols-count">${P}</span>
              <button data-grid-action="cols-inc" title="Mehr Spalten">+</button>
            </div>
            <button class="image-grid-add-btn" data-grid-action="add-image" title="Bild hinzufuegen">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:12px;height:12px;"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/></svg>
            </button>
            <button class="note-delete" data-action="delete" title="Loeschen">${te}</button>
          </div>
        </div>
        <div class="image-grid-body">
          <div class="image-grid-container" style="grid-template-columns:repeat(${P},1fr);gap:${C}px;"></div>
          <div class="image-grid-empty" data-grid-action="add-image">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:28px;height:28px;"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" /></svg>
            <span>Klicken oder + zum Hinzufuegen</span>
          </div>
        </div>
        <div class="resize-handle"></div>
      `;let q=L.querySelector(".image-grid-container");return w.forEach(R=>q.appendChild(this._createGridItemEl(L,R))),this._updateGridEmptyState(L),this._bindNoteEvents(L),this._bindImageGridEvents(L),L},_createGridItemEl(a,c){let d=document.createElement("div");return d.className="image-grid-item",d.dataset.imageId=c.id,d.innerHTML=`
        <img src="${this._esc(c.src)}" alt="${this._esc(c.alt||"")}" />
        <button class="image-grid-item-delete" data-grid-action="delete-image" data-image-id="${c.id}" title="Entfernen">${te}</button>
      `,d},_bindImageGridEvents(a){a.addEventListener("click",c=>{let d=c.target.closest("[data-grid-action]")?.dataset.gridAction;if(d){if(d==="add-image"){if(c.stopPropagation(),this._uploadBusy)return;this._pendingUploadTarget={noteEl:a,type:"image_grid"},this._fileInput.accept="image/*",this._fileInput.click();return}if(d==="cols-dec"){if(c.stopPropagation(),a._imageGridData.columns>1){a._imageGridData.columns--,this._updateGridLayout(a);let h=parseInt(a.dataset.noteId);h>0&&this._saveMediaMetadata(a,h)}return}if(d==="cols-inc"){if(c.stopPropagation(),a._imageGridData.columns<6){a._imageGridData.columns++,this._updateGridLayout(a);let h=parseInt(a.dataset.noteId);h>0&&this._saveMediaMetadata(a,h)}return}if(d==="delete-image"){c.stopPropagation();let h=c.target.closest("[data-image-id]")?.dataset.imageId;a._imageGridData.images=a._imageGridData.images.filter(m=>m.id!==h),a.querySelector(`[data-image-id="${h}"]`)?.closest(".image-grid-item")?.remove(),this._updateGridEmptyState(a);let f=parseInt(a.dataset.noteId);f>0&&this._saveMediaMetadata(a,f);return}}})},_updateGridLayout(a){let c=a.querySelector(".image-grid-container");c.style.gridTemplateColumns=`repeat(${a._imageGridData.columns},1fr)`,c.style.gap=`${a._imageGridData.gap}px`,a.querySelector(".image-grid-cols-count").textContent=a._imageGridData.columns},_updateGridEmptyState(a){let c=a.querySelector(".image-grid-empty"),d=a.querySelector(".image-grid-container");if(!c)return;let h=a._imageGridData.images.length>0;c.style.display=h?"none":"",d.style.display=h?"":"none"},_applyImageGridUpload(a,c){let h={id:"img_"+Date.now().toString(36),contextFileId:c.contextFileId,src:c.url,alt:""};a._imageGridData.images.push(h);let f=a.querySelector(".image-grid-container");f.style.display="",f.appendChild(this._createGridItemEl(a,h)),this._updateGridEmptyState(a)},_createVideoEl(a){let c=a.color||"blue",d=a.x??0,h=a.y??0,f=a.width??480,m=a.height??300,b=a.metadata||{},w=document.createElement("div");w.className=`workshop-video workshop-video-${c}`,w.dataset.noteId=a.id,w.dataset.noteType="video",w.dataset.x=d,w.dataset.y=h,w.style.cssText=`width:${f}px;height:${m}px;transform:translate(${d}px,${h}px);`,w._videoData={src:b.src||"",provider:b.provider||"",embedUrl:b.embedUrl||"",contextFileId:b.contextFileId||null};let P=!!(b.embedUrl||b.src),C;return b.embedUrl?C=`<iframe src="${this._esc(b.embedUrl)}" allowfullscreen allow="autoplay; encrypted-media"></iframe>`:b.src&&b.provider==="upload"?C=`<video src="${this._esc(b.src)}" controls></video>`:b.src?C=`<video src="${this._esc(b.src)}" controls></video>`:C=`
          <div class="video-upload-zone">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:32px;height:32px;"><path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
            <input type="text" class="video-url-input" placeholder="YouTube/Vimeo URL einfuegen..." />
            <div style="display:flex;align-items:center;gap:6px;margin-top:4px;">
              <span style="font-size:10px;color:#9ca3af;">oder</span>
              <button class="video-upload-btn" data-action="upload-video">Datei hochladen</button>
            </div>
          </div>
        `,w.innerHTML=`
        <div class="drag-handle video-drag-handle">
          <div style="display:flex;align-items:center;gap:6px;">
            <div class="drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></div>
            ${this._colorDotHTML(c)}
          </div>
          <button class="note-delete" data-action="delete" title="Loeschen">${te}</button>
        </div>
        <div class="video-container">${C}</div>
        <div class="resize-handle"></div>
      `,this._bindNoteEvents(w),this._bindVideoEvents(w),w},_bindVideoEvents(a){let c=a.querySelector(".video-url-input");if(c){let d=()=>{let h=c.value.trim();if(!h)return;let f=this._parseVideoUrl(h);a._videoData={...a._videoData,...f};let m=a.querySelector(".video-container");f.embedUrl?m.innerHTML=`<iframe src="${this._esc(f.embedUrl)}" allowfullscreen allow="autoplay; encrypted-media"></iframe>`:f.src&&(m.innerHTML=`<video src="${this._esc(f.src)}" controls></video>`);let b=parseInt(a.dataset.noteId);b>0&&this._saveMediaMetadata(a,b)};c.addEventListener("keydown",h=>{h.key==="Enter"&&(h.preventDefault(),d())}),c.addEventListener("blur",d)}a.addEventListener("click",d=>{if(d.target.closest('[data-action="upload-video"]')){if(d.stopPropagation(),this._uploadBusy)return;this._pendingUploadTarget={noteEl:a,type:"video"},this._fileInput.accept="video/*",this._fileInput.click()}})},_applyVideoUpload(a,c){a._videoData={src:c.url,provider:"upload",embedUrl:"",contextFileId:c.contextFileId};let d=a.querySelector(".video-container");d.innerHTML=`<video src="${this._esc(c.url)}" controls></video>`},_parseVideoUrl(a){let c=a.match(/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/);return c?{provider:"youtube",embedUrl:`https://www.youtube.com/embed/${c[1]}`,src:a}:(c=a.match(/vimeo\.com\/(\d+)/),c?{provider:"vimeo",embedUrl:`https://player.vimeo.com/video/${c[1]}`,src:a}:{provider:"direct",src:a,embedUrl:""})},_saveMediaMetadata(a,c){clearTimeout(this._mediaTimers[c]),this._mediaTimers[c]=setTimeout(()=>{let d=a.dataset.noteType,h={};d==="image"?h={...a._imageData}:d==="image_grid"?h={images:a._imageGridData.images,columns:a._imageGridData.columns,gap:a._imageGridData.gap}:d==="video"&&(h={...a._videoData}),this.$wire.call("updateNoteMetadata",c,h)},400)},_handleRemoteChange(a){let{action:c,noteId:d,data:h}=a;switch(c){case"created":this._handleRemoteCreate(h);break;case"moved":this._handleRemoteMove(d,h);break;case"text_updated":this._handleRemoteTextUpdate(d,h);break;case"color_updated":this._handleRemoteColorUpdate(d,h);break;case"metadata_updated":this._handleRemoteMetadataUpdate(d,h);break;case"deleted":this._handleRemoteDelete(d);break}},_handleRemoteCreate(a){if(!a||!a.id||this.$refs.board.querySelector(`[data-note-id="${a.id}"]`))return;let c=this._createNoteEl(a);this.$refs.board.appendChild(c)},_handleRemoteMove(a,c){if(this._lockedNoteIds.has(a))return;let d=this.$refs.board.querySelector(`[data-note-id="${a}"]`);if(!d)return;let h=c.x??(parseFloat(d.dataset.x)||0),f=c.y??(parseFloat(d.dataset.y)||0);d.dataset.x=h,d.dataset.y=f,d.style.transform=`translate(${h}px,${f}px)`,c.width!=null&&(d.style.width=c.width+"px"),c.height!=null&&(d.style.height=c.height+"px"),this._updateConnectors()},_handleRemoteTextUpdate(a,c){if(this._lockedNoteIds.has(a))return;let d=this.$refs.board.querySelector(`[data-note-id="${a}"]`);if(!d)return;let h=d.dataset.noteType||"note";if(h==="note"){let f=d.querySelector(".note-body input"),m=d.querySelector(".note-body textarea");f&&c.title!=null&&(f.value=c.title),m&&c.content!=null&&(m.value=c.content)}else if(h==="text"){let f=d.querySelector(".text-body input");f&&c.title!=null&&(f.value=c.title)}else if(h==="section"){let f=d.querySelector(".section-title");f&&c.title!=null&&(f.value=c.title)}else if(h==="shape"){let f=d.querySelector(".shape-body input");f&&c.title!=null&&(f.value=c.title)}else if(h==="kanban"){let f=d.querySelector(".kanban-board-title");f&&c.title!=null&&(f.value=c.title)}},_handleRemoteColorUpdate(a,c){let d=this.$refs.board.querySelector(`[data-note-id="${a}"]`);if(!d||!c.color)return;(d.dataset.noteType||"note")==="shape"?this._changeShapeColor(d,a,c.color):this._changeColor(d,a,c.color)},_handleRemoteMetadataUpdate(a,c){if(this._lockedNoteIds.has(a))return;let d=this.$refs.board.querySelector(`[data-note-id="${a}"]`);if(!d||!c.metadata)return;let h=d.dataset.noteType||"note";if(h==="shape"&&c.metadata.shape)d.dataset.shape=c.metadata.shape,d.className=d.className.replace(/workshop-shape-(?:rect|circle|diamond)/,`workshop-shape-${c.metadata.shape}`);else if(h==="kanban"&&c.metadata.columns){d._kanbanData={columns:JSON.parse(JSON.stringify(c.metadata.columns))};let f=d.querySelector(".kanban-columns");f&&(f.innerHTML="",d._kanbanData.columns.forEach(m=>{f.appendChild(this._createKanbanColumnEl(d,m))}))}else if(h==="image"&&c.metadata)d._imageData={...d._imageData,...c.metadata};else if(h==="image_grid"&&c.metadata){if(d._imageGridData={...d._imageGridData,...c.metadata},c.metadata.images){let f=d.querySelector(".image-grid-container");f&&(f.innerHTML="",c.metadata.images.forEach(m=>f.appendChild(this._createGridItemEl(d,m))),this._updateGridEmptyState(d))}c.metadata.columns&&this._updateGridLayout(d)}else h==="video"&&c.metadata&&(d._videoData={...d._videoData,...c.metadata})},_handleRemoteDelete(a){let c=this.$refs.board.querySelector(`[data-note-id="${a}"]`);if(!c)return;if(c.dataset.noteType==="connector"){if(this._svgLayer){let h=this._svgLayer.querySelector(`.workshop-connector-path[data-connector-id="${a}"]`),f=this._svgLayer.querySelector(`.connector-delete-fo[data-connector-id="${a}"]`);h&&h.remove(),f&&f.remove()}}else if(this._svgLayer){let h=String(a);this._svgLayer.querySelectorAll(".workshop-connector-path").forEach(f=>{if(f.dataset.fromNoteId===h||f.dataset.toNoteId===h){let m=this._svgLayer.querySelector(`.connector-delete-fo[data-connector-id="${f.dataset.connectorId}"]`);m&&m.remove();let b=this.$refs.board.querySelector(`[data-note-id="${f.dataset.connectorId}"][data-note-type="connector"]`);b&&b.remove(),f.remove()}})}c.remove()},_esc(a){let c=document.createElement("div");return c.textContent=a,c.innerHTML},_applyTransform(){let a=this.$refs.board;a&&(a.style.transform=`translate(${this.panX}px,${this.panY}px) scale(${this.scale})`)},_screenToBoard(a,c){return{x:(a-this.panX)/this.scale,y:(c-this.panY)/this.scale}},_zoomTo(a,c,d){let h=this.$refs.board?.parentElement;if(!h)return;let f=h.getBoundingClientRect(),m=c-f.left,b=d-f.top,w=Math.max(.1,Math.min(4,a)),P=w/this.scale;this.panX=m-(m-this.panX)*P,this.panY=b-(b-this.panY)*P,this.scale=w,this._applyTransform()},_initPanZoom(){let a=this.$refs.board;if(!a)return;let c=a.parentElement;a.style.transformOrigin="0 0",this._on(c,"wheel",d=>{d.preventDefault(),d.ctrlKey||d.metaKey?this._zoomTo(this.scale*(1-d.deltaY*.003),d.clientX,d.clientY):(this.panX-=d.deltaX,this.panY-=d.deltaY,this._applyTransform())},{passive:!1}),this._on(c,"pointerdown",d=>{d.target.closest(".workshop-note, .workshop-text, .workshop-section, .workshop-shape, .workshop-kanban, .workshop-image, .workshop-image-grid, .workshop-video, .workshop-toolbar, .workshop-zoom-controls")||(d.button===1||d.button===0&&this._spaceDown)&&(this._isPanning=!0,this._panButton=d.button,this._panStart={x:d.clientX,y:d.clientY,px:this.panX,py:this.panY},c.style.cursor="grabbing",c.setPointerCapture(d.pointerId),d.preventDefault())},!1),this._on(c,"pointermove",d=>{if(this._isPanning&&this._panStart&&(this.panX=this._panStart.px+(d.clientX-this._panStart.x),this.panY=this._panStart.py+(d.clientY-this._panStart.y),this._applyTransform()),this._previewLine&&this._connectorMode&&this._connectorFrom){let h=this._screenToBoard(d.clientX,d.clientY);this._previewLine.setAttribute("x2",h.x),this._previewLine.setAttribute("y2",h.y)}},!1),this._on(c,"pointerup",d=>{this._isPanning&&(this._isPanning=!1,this._panStart=null,c.style.cursor=this._spaceDown?"grab":"")},!1),this._on(c,"contextmenu",d=>{this._panButton===1&&d.preventDefault()},!1),this._on(document,"keydown",d=>{d.code==="Space"&&!d.repeat&&!d.target.matches("input,textarea,[contenteditable]")&&(d.preventDefault(),this._spaceDown=!0,c.style.cursor="grab")},!1),this._on(document,"keyup",d=>{d.code==="Space"&&(this._spaceDown=!1,this._isPanning||(c.style.cursor=""))},!1),this._on(document,"click",()=>{a.querySelectorAll('.color-picker-dd[style*="flex"]').forEach(d=>d.style.display="none")},!1)},zoomIn(){this._zoomToCenter(this.scale*1.3)},zoomOut(){this._zoomToCenter(this.scale/1.3)},resetZoom(){this.scale=1,this.panX=0,this.panY=0,this._applyTransform()},fitToScreen(){this._fitGrid()},toggleFullscreen(){this.isFullscreen=!this.isFullscreen,this._fitAfterDelay()},_fitAfterDelay(){setTimeout(()=>this._fitGrid(),50),setTimeout(()=>this._fitGrid(),200),setTimeout(()=>this._fitGrid(),500)},_zoomToCenter(a){let c=this.$refs.board?.parentElement;if(!c)return;let d=c.getBoundingClientRect();this._zoomTo(a,d.left+c.clientWidth/2,d.top+c.clientHeight/2)},_fitGrid(){let a=this.$refs.board,c=a?.parentElement,d=a?.querySelector(".workshop-canvas-background");if(!d||!c)return;let h=d.offsetWidth,f=d.offsetHeight,m=d.offsetLeft,b=d.offsetTop,w=c.clientWidth,P=c.clientHeight,C=40,L=Math.min((w-C*2)/h,(P-C*2)/f,1);this.scale=L,this.panX=(w-h*L)/2-m*L,this.panY=(P-f*L)/2-b*L,this._applyTransform()},_initInteract(){let a=this,c=".workshop-note, .workshop-text, .workshop-section, .workshop-shape, .workshop-kanban, .workshop-image, .workshop-image-grid, .workshop-video",d=c;(0,W.default)(c).draggable({allowFrom:".drag-handle",ignoreFrom:"input, textarea, .note-delete, .shape-toggle, .color-dot, .color-picker-dd, .kanban-cards, .kanban-card, .kanban-column, .kanban-add-card, .kanban-add-col-btn, .kanban-col-title, .kanban-col-delete, .kanban-card-title, .kanban-card-delete, .image-upload-zone, .image-grid-add-btn, .image-grid-container, .image-grid-cols-control, .video-url-input, .video-upload-btn, .video-upload-zone, .video-container iframe, .video-container video",inertia:!1,listeners:{start(h){h.target.classList.add("dragging");let f=parseInt(h.target.dataset.noteId);f>0&&a._lockedNoteIds.add(f)},move(h){let f=h.target,m=(parseFloat(f.dataset.x)||0)+h.dx/a.scale,b=(parseFloat(f.dataset.y)||0)+h.dy/a.scale;f.style.transform=`translate(${m}px,${b}px)`,f.dataset.x=m,f.dataset.y=b,a._updateConnectors()},end(h){h.target.classList.remove("dragging");let f=h.target,m=parseInt(f.dataset.noteId);m>0&&a._lockedNoteIds.delete(m),!(m<0)&&a._savePos(m,f)}}}),(0,W.default)(d).resizable({edges:{right:".resize-handle",bottom:".resize-handle"},modifiers:[W.default.modifiers.restrictSize({min:{width:60,height:30}})],listeners:{start(h){let f=parseInt(h.target.dataset.noteId);f>0&&a._lockedNoteIds.add(f)},move(h){let f=h.target,m=parseFloat(f.dataset.x)||0,b=parseFloat(f.dataset.y)||0,w=h.rect.width/a.scale,P=h.rect.height/a.scale;if(f.style.width=w+"px",f.style.height=P+"px",m+=h.deltaRect.left/a.scale,b+=h.deltaRect.top/a.scale,f.style.transform=`translate(${m}px,${b}px)`,f.dataset.x=m,f.dataset.y=b,f.dataset.noteType==="text"){let C=Math.max(14,Math.round(w/12)),L=f.querySelector(".text-body input");L&&(L.style.fontSize=C+"px")}a._updateConnectors()},end(h){let f=h.target,m=parseInt(f.dataset.noteId);m>0&&a._lockedNoteIds.delete(m),!(m<0)&&a._savePos(m,f)}}}),(0,W.default)(".workshop-canvas-background").resizable({edges:{right:!0,bottom:!0},modifiers:[W.default.modifiers.restrictSize({min:{width:400,height:300}})],listeners:{move(h){let f=h.target;f.style.width=h.rect.width/a.scale+"px",f.style.minHeight=h.rect.height/a.scale+"px"},end(h){let f=h.target,m=parseInt(f.style.width)||1200,b=parseInt(f.style.minHeight)||800;clearTimeout(a._gridSaveTimer),a._gridSaveTimer=setTimeout(()=>{a.$wire.call("updateWorkshopSettings",{gridWidth:m,gridHeight:b})},400)}}})},_savePos(a,c){clearTimeout(this._saveTimers[a]),this._saveTimers[a]=setTimeout(()=>{let d=this._detectBlock(c);this.$wire.call("updateNotePosition",a,{x:parseFloat(c.dataset.x)||0,y:parseFloat(c.dataset.y)||0,width:parseInt(c.style.width)||200,height:parseInt(c.style.height)||150,blockId:d})},300)},_detectBlock(a){let c=parseFloat(a.dataset.x)||0,d=parseFloat(a.dataset.y)||0,h=c+(parseInt(a.style.width)||0)/2,f=d+(parseInt(a.style.height)||0)/2,m=this.$refs.board?.querySelectorAll(".workshop-grid-block[data-block-id]");if(!m)return null;for(let b of m){let w=b.offsetParent,P=b.offsetLeft+(w?.offsetLeft||0),C=b.offsetTop+(w?.offsetTop||0),L=b.offsetWidth,q=b.offsetHeight;if(h>=P&&h<=P+L&&f>=C&&f<=C+q)return parseInt(b.dataset.blockId)||null}return null},addElement(a="note"){let c=this.$refs.board?.parentElement;if(!c)return;let d=c.getBoundingClientRect(),h=Mn[a]||Mn.note,f=(d.width/2-this.panX)/this.scale,m=(d.height/2-this.panY)/this.scale,b=Math.round(f-h.width/2),w=Math.round(m-h.height/2),P=this._nextTempId--,C=a==="shape"?{shape:"rect"}:a==="kanban"?{columns:[{id:"col_"+Date.now().toString(36)+"a",title:"To Do",wipLimit:0,cards:[]},{id:"col_"+Date.now().toString(36)+"b",title:"In Progress",wipLimit:3,cards:[]},{id:"col_"+Date.now().toString(36)+"c",title:"Done",wipLimit:0,cards:[]}]}:a==="image_grid"?{images:[],columns:2,gap:4}:null,L=this._createNoteEl({id:P,type:a,title:"",content:"",color:h.color,x:b,y:w,width:h.width,height:h.height,metadata:C});this.$refs.board.appendChild(L),this.$wire.call("addWorkshopNote",{x:b,y:w},a).then(()=>{this.$wire.call("getWorkshopNotes").then(q=>{if(Array.isArray(q)&&q.length>0){let R=q.reduce((G,H)=>G.id>H.id?G:H);L.dataset.noteId=R.id}})}),setTimeout(()=>{L.querySelector(".note-body input, .text-body input, .section-title, .shape-body input, .kanban-board-title, .image-alt-input, .video-url-input")?.focus()},100)},addNote(){this.addElement("note")},_deleteNote(a,c){if(a.remove(),this._svgLayer){let d=String(c);this._svgLayer.querySelectorAll(".workshop-connector-path").forEach(h=>{if(h.dataset.fromNoteId===d||h.dataset.toNoteId===d){let f=this._svgLayer.querySelector(`.connector-delete-fo[data-connector-id="${h.dataset.connectorId}"]`);f&&f.remove();let m=this.$refs.board.querySelector(`[data-note-id="${h.dataset.connectorId}"][data-note-type="connector"]`);m&&m.remove(),h.remove()}})}c>0&&this.$wire.call("deleteWorkshopNote",c)},_changeColor(a,c,d){let h=a.dataset.noteType||"note";h==="note"?a.className=a.className.replace(/workshop-note-\w+/,`workshop-note-${d}`):h==="section"?(a.className=a.className.replace(/workshop-section-\w+/,`workshop-section-${d}`),a.style.borderColor=re[d]||re.yellow):h==="kanban"?a.className=a.className.replace(/workshop-kanban-\w+/,`workshop-kanban-${d}`):h==="image"?a.className=a.className.replace(/workshop-image-\w+/,`workshop-image-${d}`):h==="image_grid"?a.className=a.className.replace(/workshop-image-grid-\w+/,`workshop-image-grid-${d}`):h==="video"&&(a.className=a.className.replace(/workshop-video-\w+/,`workshop-video-${d}`)),a.querySelector(".drag-handle .color-dot")?.setAttribute("style",`background:${re[d]}`),a.querySelector(".color-picker-dd").style.display="none",a.querySelectorAll(".color-picker-dd .color-dot").forEach(f=>{f.classList.toggle("active",f.dataset.pickColor===d)}),c>0&&this.$wire.call("updateNoteColor",c,d)},_changeShapeColor(a,c,d){a.className=a.className.replace(/workshop-shape-color-\w+/,`workshop-shape-color-${d}`);let h=a.querySelector(".shape-visual");h&&(h.className="shape-visual"),a.querySelector(".color-dot")?.setAttribute("style",`background:${re[d]}`),a.querySelector(".color-picker-dd").style.display="none",a.querySelectorAll(".color-picker-dd .color-dot").forEach(f=>{f.classList.toggle("active",f.dataset.pickColor===d)}),c>0&&this.$wire.call("updateNoteColor",c,d)},_toggleShape(a,c){let d=["rect","circle","diamond"],h=a.dataset.shape||"rect",f=d[(d.indexOf(h)+1)%d.length];a.dataset.shape=f,a.className=a.className.replace(/workshop-shape-(?:rect|circle|diamond)/,`workshop-shape-${f}`),c>0&&this.$wire.call("updateNoteMetadata",c,{shape:f})},_toggleColorPicker(a){let c=a.querySelector(".color-picker-dd");if(!c)return;let d=c.style.display==="flex";this.$refs.board.querySelectorAll(".color-picker-dd").forEach(h=>h.style.display="none"),c.style.display=d?"none":"flex"},_createConnectorEl(a){let c=a.metadata||{},d=document.createElement("div");if(d.style.cssText="position:absolute;width:0;height:0;pointer-events:none;",d.dataset.noteId=a.id,d.dataset.noteType="connector",d.dataset.fromNoteId=c.fromNoteId||"",d.dataset.toNoteId=c.toNoteId||"",this._svgLayer){let h=document.createElementNS("http://www.w3.org/2000/svg","path");h.classList.add("workshop-connector-path"),h.dataset.connectorId=a.id,h.dataset.fromNoteId=c.fromNoteId||"",h.dataset.toNoteId=c.toNoteId||"",h.setAttribute("marker-end","url(#arrowhead)"),h.setAttribute("fill","none"),h.setAttribute("stroke","#6b7280"),h.setAttribute("stroke-width","2"),h.style.pointerEvents="stroke",h.style.cursor="pointer",this._svgLayer.appendChild(h);let f=document.createElementNS("http://www.w3.org/2000/svg","foreignObject");f.classList.add("connector-delete-fo"),f.dataset.connectorId=a.id,f.setAttribute("width","24"),f.setAttribute("height","24"),f.style.overflow="visible",f.style.display="none",f.innerHTML=`<button xmlns="http://www.w3.org/1999/xhtml" class="connector-delete-btn" title="Loeschen">${te}</button>`,this._svgLayer.appendChild(f),h.addEventListener("mouseenter",()=>{f.style.display="",h.classList.add("hovered")}),h.addEventListener("mouseleave",()=>{setTimeout(()=>{f.matches(":hover")||(f.style.display="none",h.classList.remove("hovered"))},200)}),f.addEventListener("mouseleave",()=>{f.style.display="none",h.classList.remove("hovered")}),f.querySelector(".connector-delete-btn").addEventListener("click",m=>{m.stopPropagation();let b=parseInt(a.id);h.remove(),f.remove(),d.remove(),b>0&&this.$wire.call("deleteWorkshopNote",b)}),this._updateSingleConnector(h,f)}return d},_getAnchorPoint(a){let c=this.$refs.board.querySelector(`[data-note-id="${a}"]:not([data-note-type="connector"])`);if(!c)return null;let d=parseFloat(c.dataset.x)||0,h=parseFloat(c.dataset.y)||0,f=parseInt(c.style.width)||0,m=parseInt(c.style.height)||0;return{x:d,y:h,w:f,h:m,cx:d+f/2,cy:h+m/2}},_bestAnchors(a,c){let d=c.cx-a.cx,h=c.cy-a.cy,f,m;return Math.abs(d)>Math.abs(h)?d>0?(f={x:a.x+a.w,y:a.cy},m={x:c.x,y:c.cy}):(f={x:a.x,y:a.cy},m={x:c.x+c.w,y:c.cy}):h>0?(f={x:a.cx,y:a.y+a.h},m={x:c.cx,y:c.y}):(f={x:a.cx,y:a.y},m={x:c.cx,y:c.y+c.h}),{from:f,to:m}},_buildConnectorPath(a,c){let d=c.x-a.x,h=c.y-a.y,f=Math.sqrt(d*d+h*h),m=Math.min(f*.4,80),b,w,P,C;return Math.abs(d)>Math.abs(h)?(b=a.x+m*Math.sign(d),w=a.y,P=c.x-m*Math.sign(d),C=c.y):(b=a.x,w=a.y+m*Math.sign(h),P=c.x,C=c.y-m*Math.sign(h)),`M ${a.x},${a.y} C ${b},${w} ${P},${C} ${c.x},${c.y}`},_updateSingleConnector(a,c){let d=a.dataset.fromNoteId,h=a.dataset.toNoteId;if(!d||!h)return;let f=this._getAnchorPoint(d),m=this._getAnchorPoint(h);if(!f||!m){a.setAttribute("d","");return}let{from:b,to:w}=this._bestAnchors(f,m);a.setAttribute("d",this._buildConnectorPath(b,w));let P=(b.x+w.x)/2-12,C=(b.y+w.y)/2-12;c.setAttribute("x",P),c.setAttribute("y",C)},_updateConnectors(){if(!this._svgLayer)return;this._svgLayer.querySelectorAll(".workshop-connector-path").forEach(c=>{let d=this._svgLayer.querySelector(`.connector-delete-fo[data-connector-id="${c.dataset.connectorId}"]`);d&&this._updateSingleConnector(c,d)})},startConnectorMode(){if(this._connectorMode){this._cancelConnectorMode();return}this._connectorMode=!0,this._connectorFrom=null,this.$refs.board.parentElement.classList.add("connector-mode")},_cancelConnectorMode(){this._connectorMode=!1,this._connectorFrom=null,this.$refs.board.parentElement.classList.remove("connector-mode"),this.$refs.board.querySelectorAll(".connector-source-selected").forEach(a=>a.classList.remove("connector-source-selected")),this._previewLine&&(this._previewLine.remove(),this._previewLine=null)},_handleConnectorClick(a){if(!this._connectorMode)return!1;let c=parseInt(a.dataset.noteId);if(a.dataset.noteType==="connector")return!1;if(this._connectorFrom){if(c===this._connectorFrom)return!0;let h=this._connectorFrom,f=c,m=this._nextTempId--,b=this._createConnectorEl({id:m,type:"connector",title:"",content:"",color:"blue",x:0,y:0,width:0,height:0,metadata:{fromNoteId:h,toNoteId:f,style:"solid",arrowHead:"end"}});return this.$refs.board.appendChild(b),this.$wire.call("addConnector",h,f).then(()=>{this.$wire.call("getWorkshopNotes").then(w=>{if(Array.isArray(w)){let P=w.filter(C=>C.type==="connector");if(P.length>0){let C=P.reduce((R,G)=>R.id>G.id?R:G);b.dataset.noteId=C.id;let L=this._svgLayer.querySelector(`.workshop-connector-path[data-connector-id="${m}"]`),q=this._svgLayer.querySelector(`.connector-delete-fo[data-connector-id="${m}"]`);L&&(L.dataset.connectorId=C.id),q&&(q.dataset.connectorId=C.id)}}})}),this._cancelConnectorMode(),!0}else{this._connectorFrom=c,a.classList.add("connector-source-selected");let h=document.createElementNS("http://www.w3.org/2000/svg","line");h.classList.add("workshop-connector-preview"),h.setAttribute("stroke","#f2ca52"),h.setAttribute("stroke-width","2"),h.setAttribute("stroke-dasharray","6 4"),h.style.pointerEvents="none";let f=this._getAnchorPoint(c);return f&&(h.setAttribute("x1",f.cx),h.setAttribute("y1",f.cy),h.setAttribute("x2",f.cx),h.setAttribute("y2",f.cy)),this._svgLayer.appendChild(h),this._previewLine=h,!0}}}}var Dn=`/* \u2500\u2500\u2500 Board (infinite canvas) \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500 */
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
.workshop-shape:hover .note-delete,
.workshop-kanban:hover > .drag-handle .note-delete,
.workshop-image:hover .note-delete,
.workshop-image-grid:hover > .drag-handle .note-delete,
.workshop-video:hover .note-delete {
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
.workshop-shape:hover .resize-handle,
.workshop-kanban:hover .resize-handle,
.workshop-image:hover .resize-handle,
.workshop-image-grid:hover .resize-handle,
.workshop-video:hover .resize-handle {
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
.connector-mode .workshop-shape,
.connector-mode .workshop-kanban,
.connector-mode .workshop-image,
.connector-mode .workshop-image-grid,
.connector-mode .workshop-video {
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

/* \u2500\u2500\u2500 Kanban Board \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500 */
.workshop-kanban {
  position: absolute;
  background: white;
  border-radius: 0.5rem;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06);
  cursor: default;
  display: flex;
  flex-direction: column;
  z-index: 10;
  touch-action: none;
  border-top: 3px solid #60a5fa;
  overflow: hidden;
}

.workshop-kanban:hover {
  box-shadow: 0 4px 16px rgba(0,0,0,0.12), 0 2px 4px rgba(0,0,0,0.08);
  z-index: 15;
}

.workshop-kanban.dragging {
  opacity: 0.9;
  z-index: 1000 !important;
  box-shadow: 0 12px 32px rgba(0,0,0,0.18);
}

/* Kanban color variants (border-top) */
.workshop-kanban-yellow { border-top-color: #fbbf24; }
.workshop-kanban-blue   { border-top-color: #60a5fa; }
.workshop-kanban-green  { border-top-color: #4ade80; }
.workshop-kanban-pink   { border-top-color: #f472b6; }
.workshop-kanban-purple { border-top-color: #a78bfa; }
.workshop-kanban-orange { border-top-color: #fb923c; }
.workshop-kanban-teal   { border-top-color: #2dd4bf; }
.workshop-kanban-red    { border-top-color: #f87171; }

/* Kanban drag handle */
.kanban-drag-handle {
  padding: 0.375rem 0.5rem;
  border-bottom: 1px solid #e5e7eb;
}

.kanban-board-title {
  font-size: 0.75rem;
  font-weight: 600;
  color: #1a1a2e;
  background: transparent;
  border: none;
  outline: none;
  flex: 1;
  min-width: 0;
  padding: 0;
  cursor: text;
}

.kanban-board-title::placeholder {
  color: rgba(0,0,0,0.2);
}

.kanban-add-col-btn {
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

.kanban-add-col-btn:hover {
  background: #f3f4f6;
  color: #1a1a2e;
}

/* Columns container */
.kanban-columns {
  display: flex;
  flex: 1;
  gap: 6px;
  padding: 6px;
  overflow-x: auto;
  overflow-y: hidden;
  background: #f3f4f6;
}

/* Single column */
.kanban-column {
  flex: 1;
  min-width: 120px;
  background: #e5e7eb;
  border-radius: 6px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.kanban-column.kanban-drop-target {
  background: #dbeafe;
  outline: 2px dashed #60a5fa;
  outline-offset: -2px;
}

/* Column header */
.kanban-column-header {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 6px 8px;
  border-bottom: 1px solid rgba(0,0,0,0.06);
}

.kanban-column-header.wip-exceeded {
  background: #fecaca;
}

.kanban-col-title {
  font-size: 0.6875rem;
  font-weight: 600;
  color: #374151;
  background: transparent;
  border: none;
  outline: none;
  flex: 1;
  min-width: 0;
  padding: 0;
  cursor: text;
}

.kanban-col-title::placeholder {
  color: rgba(0,0,0,0.2);
}

.kanban-card-count {
  font-size: 0.625rem;
  color: #9ca3af;
  font-weight: 500;
  flex-shrink: 0;
}

.kanban-col-delete {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1rem;
  height: 1rem;
  border-radius: 9999px;
  border: none;
  background: transparent;
  color: #d1d5db;
  cursor: pointer;
  opacity: 0;
  transition: all 0.15s;
  flex-shrink: 0;
}

.kanban-column:hover .kanban-col-delete {
  opacity: 1;
}

.kanban-col-delete:hover {
  background: #fecaca;
  color: #dc2626;
}

/* Cards container */
.kanban-cards {
  flex: 1;
  padding: 4px 6px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-height: 20px;
}

/* Single card */
.kanban-card {
  background: white;
  border-radius: 4px;
  padding: 6px 8px;
  box-shadow: 0 1px 2px rgba(0,0,0,0.06);
  cursor: grab;
  transition: opacity 0.15s, box-shadow 0.15s;
}

.kanban-card:hover {
  box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}

.kanban-card.kanban-card-floating {
  box-shadow: 0 8px 24px rgba(0,0,0,0.2);
  opacity: 0.95;
  pointer-events: none;
  z-index: 9999;
  transform: rotate(2deg);
  transition: box-shadow 0.15s, transform 0.1s;
}

.kanban-card-placeholder {
  border: 2px dashed #d1d5db;
  border-radius: 4px;
  background: rgba(242, 202, 82, 0.08);
  flex-shrink: 0;
}

.kanban-card-grip {
  cursor: grab;
  color: #d1d5db;
  font-size: 0.625rem;
  line-height: 1;
  flex-shrink: 0;
  user-select: none;
  touch-action: none;
  transition: color 0.15s;
}

.kanban-card:hover .kanban-card-grip {
  color: #9ca3af;
}

.kanban-card-grip:active {
  cursor: grabbing;
}

.kanban-card-title {
  font-size: 0.6875rem;
  color: #374151;
  background: transparent;
  border: none;
  outline: none;
  flex: 1;
  min-width: 0;
  padding: 0;
  cursor: text;
}

.kanban-card-title::placeholder {
  color: rgba(0,0,0,0.2);
}

.kanban-card-delete {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1rem;
  height: 1rem;
  border-radius: 9999px;
  border: none;
  background: transparent;
  color: #d1d5db;
  cursor: pointer;
  opacity: 0;
  transition: all 0.15s;
  flex-shrink: 0;
}

.kanban-card:hover .kanban-card-delete {
  opacity: 1;
}

.kanban-card-delete:hover {
  background: #fecaca;
  color: #dc2626;
}

/* Add card button */
.kanban-add-card {
  display: block;
  width: calc(100% - 12px);
  margin: 2px 6px 6px;
  padding: 4px;
  border: 1px dashed #d1d5db;
  border-radius: 4px;
  background: transparent;
  color: #9ca3af;
  font-size: 0.625rem;
  font-weight: 500;
  cursor: pointer;
  text-align: center;
  transition: all 0.15s;
  flex-shrink: 0;
}

.kanban-add-card:hover {
  border-color: #9ca3af;
  background: rgba(0,0,0,0.02);
  color: #374151;
}

/* \u2500\u2500\u2500 Image Element \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500 */
.workshop-image {
  position: absolute;
  border-radius: 0.5rem;
  box-shadow: 0 2px 6px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06);
  cursor: default;
  display: flex;
  flex-direction: column;
  z-index: 10;
  touch-action: none;
  overflow: hidden;
  background: white;
}

.workshop-image:hover {
  box-shadow: 0 4px 16px rgba(0,0,0,0.12), 0 2px 4px rgba(0,0,0,0.08);
  z-index: 15;
}

.workshop-image.dragging {
  opacity: 0.9;
  z-index: 1000 !important;
  box-shadow: 0 12px 32px rgba(0,0,0,0.18);
}

.workshop-image .image-drag-handle {
  padding: 0.25rem 0.5rem;
  border-bottom: 1px solid rgba(0,0,0,0.06);
  background: white;
  z-index: 2;
}

.workshop-image .image-alt-input {
  font-size: 0.625rem;
  color: #9ca3af;
  background: transparent;
  border: none;
  outline: none;
  width: 60px;
  padding: 0;
  cursor: text;
  opacity: 0;
  transition: opacity 0.15s;
}

.workshop-image:hover .image-alt-input {
  opacity: 1;
}

.workshop-image .image-alt-input::placeholder {
  color: #d1d5db;
}

.workshop-image .image-container {
  flex: 1;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
}

.workshop-image .image-container img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.workshop-image .image-upload-zone {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  border: 2px dashed #d1d5db;
  border-radius: 0;
  color: #9ca3af;
  cursor: pointer;
  gap: 6px;
  font-size: 0.6875rem;
  transition: all 0.15s;
}

.workshop-image .image-upload-zone:hover {
  border-color: #f2ca52;
  color: #6b7280;
  background: rgba(242, 202, 82, 0.05);
}

/* Image color variants (subtle top border) */
.workshop-image-yellow { border-top: 3px solid #fbbf24; }
.workshop-image-blue   { border-top: 3px solid #60a5fa; }
.workshop-image-green  { border-top: 3px solid #4ade80; }
.workshop-image-pink   { border-top: 3px solid #f472b6; }
.workshop-image-purple { border-top: 3px solid #a78bfa; }
.workshop-image-orange { border-top: 3px solid #fb923c; }
.workshop-image-teal   { border-top: 3px solid #2dd4bf; }
.workshop-image-red    { border-top: 3px solid #f87171; }

/* \u2500\u2500\u2500 Image Grid Element \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500 */
.workshop-image-grid {
  position: absolute;
  background: white;
  border-radius: 0.5rem;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06);
  cursor: default;
  display: flex;
  flex-direction: column;
  z-index: 10;
  touch-action: none;
  overflow: hidden;
}

.workshop-image-grid:hover {
  box-shadow: 0 4px 16px rgba(0,0,0,0.12), 0 2px 4px rgba(0,0,0,0.08);
  z-index: 15;
}

.workshop-image-grid.dragging {
  opacity: 0.9;
  z-index: 1000 !important;
  box-shadow: 0 12px 32px rgba(0,0,0,0.18);
}

.workshop-image-grid .image-grid-drag-handle {
  padding: 0.375rem 0.5rem;
  border-bottom: 1px solid #e5e7eb;
}

.workshop-image-grid .image-grid-cols-control {
  display: flex;
  align-items: center;
  gap: 2px;
  font-size: 0.625rem;
  color: #6b7280;
}

.workshop-image-grid .image-grid-cols-control button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.125rem;
  height: 1.125rem;
  border-radius: 4px;
  border: none;
  background: #f3f4f6;
  color: #6b7280;
  cursor: pointer;
  font-size: 0.75rem;
  font-weight: 700;
  transition: all 0.15s;
}

.workshop-image-grid .image-grid-cols-control button:hover {
  background: #e5e7eb;
  color: #1a1a2e;
}

.workshop-image-grid .image-grid-cols-count {
  min-width: 14px;
  text-align: center;
  font-weight: 600;
}

.workshop-image-grid .image-grid-add-btn {
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

.workshop-image-grid .image-grid-add-btn:hover {
  background: #f3f4f6;
  color: #1a1a2e;
}

.workshop-image-grid .image-grid-body {
  flex: 1;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  position: relative;
}

.workshop-image-grid .image-grid-container {
  flex: 1;
  display: grid;
  padding: 6px;
  overflow-y: auto;
  grid-auto-rows: auto;
  align-content: start;
}

.workshop-image-grid .image-grid-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  flex: 1;
  color: #9ca3af;
  gap: 8px;
  font-size: 0.6875rem;
  cursor: pointer;
  border: 2px dashed #e5e7eb;
  border-radius: 0 0 0.5rem 0.5rem;
  margin: 0 6px 6px;
  transition: all 0.15s;
}

.workshop-image-grid .image-grid-empty:hover {
  border-color: #f2ca52;
  color: #6b7280;
  background: rgba(242, 202, 82, 0.05);
}

.workshop-image-grid .image-grid-item {
  position: relative;
  overflow: hidden;
  border-radius: 4px;
  background: #f3f4f6;
}

.workshop-image-grid .image-grid-item img {
  width: 100%;
  height: auto;
  display: block;
  border-radius: 4px;
}

.workshop-image-grid .image-grid-item-delete {
  position: absolute;
  top: 4px;
  right: 4px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.25rem;
  height: 1.25rem;
  border-radius: 9999px;
  border: none;
  background: rgba(255,255,255,0.9);
  color: #d1d5db;
  cursor: pointer;
  opacity: 0;
  transition: all 0.15s;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.workshop-image-grid .image-grid-item:hover .image-grid-item-delete {
  opacity: 1;
}

.workshop-image-grid .image-grid-item-delete:hover {
  background: #fecaca;
  color: #dc2626;
}

/* Image Grid color variants */
.workshop-image-grid-yellow { border-top: 3px solid #fbbf24; }
.workshop-image-grid-blue   { border-top: 3px solid #60a5fa; }
.workshop-image-grid-green  { border-top: 3px solid #4ade80; }
.workshop-image-grid-pink   { border-top: 3px solid #f472b6; }
.workshop-image-grid-purple { border-top: 3px solid #a78bfa; }
.workshop-image-grid-orange { border-top: 3px solid #fb923c; }
.workshop-image-grid-teal   { border-top: 3px solid #2dd4bf; }
.workshop-image-grid-red    { border-top: 3px solid #f87171; }

/* \u2500\u2500\u2500 Video Element \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500 */
.workshop-video {
  position: absolute;
  background: #1a1a2e;
  border-radius: 0.5rem;
  box-shadow: 0 2px 8px rgba(0,0,0,0.15), 0 1px 2px rgba(0,0,0,0.1);
  cursor: default;
  display: flex;
  flex-direction: column;
  z-index: 10;
  touch-action: none;
  overflow: hidden;
}

.workshop-video:hover {
  box-shadow: 0 4px 16px rgba(0,0,0,0.2), 0 2px 4px rgba(0,0,0,0.12);
  z-index: 15;
}

.workshop-video.dragging {
  opacity: 0.9;
  z-index: 1000 !important;
  box-shadow: 0 12px 32px rgba(0,0,0,0.25);
}

.workshop-video .video-drag-handle {
  padding: 0.25rem 0.5rem;
  border-bottom: 1px solid rgba(255,255,255,0.1);
  color: rgba(255,255,255,0.6);
}

.workshop-video .video-drag-handle .drag-dots span {
  background: rgba(255,255,255,0.4);
}

.workshop-video .video-container {
  flex: 1;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
}

.workshop-video .video-container iframe {
  width: 100%;
  height: 100%;
  border: none;
}

.workshop-video .video-container video {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.workshop-video .video-upload-zone {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  color: rgba(255,255,255,0.5);
  gap: 8px;
  padding: 1rem;
}

.workshop-video .video-url-input {
  background: rgba(255,255,255,0.1);
  border: 1px solid rgba(255,255,255,0.2);
  border-radius: 6px;
  color: white;
  font-size: 0.6875rem;
  padding: 6px 10px;
  width: 100%;
  max-width: 280px;
  outline: none;
  transition: all 0.15s;
}

.workshop-video .video-url-input:focus {
  border-color: #f2ca52;
  background: rgba(255,255,255,0.15);
}

.workshop-video .video-url-input::placeholder {
  color: rgba(255,255,255,0.3);
}

.workshop-video .video-upload-btn {
  background: rgba(255,255,255,0.1);
  border: 1px solid rgba(255,255,255,0.2);
  border-radius: 6px;
  color: rgba(255,255,255,0.6);
  font-size: 0.625rem;
  padding: 4px 10px;
  cursor: pointer;
  transition: all 0.15s;
}

.workshop-video .video-upload-btn:hover {
  background: rgba(255,255,255,0.2);
  color: white;
}

/* Video color variants (subtle accent) */
.workshop-video-yellow { border-top: 3px solid #fbbf24; }
.workshop-video-blue   { border-top: 3px solid #60a5fa; }
.workshop-video-green  { border-top: 3px solid #4ade80; }
.workshop-video-pink   { border-top: 3px solid #f472b6; }
.workshop-video-purple { border-top: 3px solid #a78bfa; }
.workshop-video-orange { border-top: 3px solid #fb923c; }
.workshop-video-teal   { border-top: 3px solid #2dd4bf; }
.workshop-video-red    { border-top: 3px solid #f87171; }

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
`;function tr(){if(document.getElementById("platform-workshop-styles"))return;let N=document.createElement("style");N.id="platform-workshop-styles",N.textContent=Dn,document.head.appendChild(N)}function zt(){let N=window.Alpine;N&&N.data("workshopBoard",It)}typeof document<"u"&&(tr(),document.addEventListener("livewire:init",zt),document.readyState!=="loading"?setTimeout(zt,0):document.addEventListener("DOMContentLoaded",zt));return Qo(nr);})();
