/* platform-workshop v0.0.0 | MIT */
var PlatformWorkshop=(()=>{var Br=Object.create;var Ve=Object.defineProperty;var Hr=Object.getOwnPropertyDescriptor;var Wr=Object.getOwnPropertyNames;var Gr=Object.getPrototypeOf,Vr=Object.prototype.hasOwnProperty;var Ur=(A,$)=>()=>($||A(($={exports:{}}).exports,$),$.exports),Kr=(A,$)=>{for(var L in $)Ve(A,L,{get:$[L],enumerable:!0})},Pn=(A,$,L,d)=>{if($&&typeof $=="object"||typeof $=="function")for(let v of Wr($))!Vr.call(A,v)&&v!==L&&Ve(A,v,{get:()=>$[v],enumerable:!(d=Hr($,v))||d.enumerable});return A};var Zr=(A,$,L)=>(L=A!=null?Br(Gr(A)):{},Pn($||!A||!A.__esModule?Ve(L,"default",{value:A,enumerable:!0}):L,A)),Qr=A=>Pn(Ve({},"__esModule",{value:!0}),A);var zn=Ur((Et,he)=>{(function(A,$){typeof Et=="object"&&typeof he<"u"?he.exports=$():typeof define=="function"&&define.amd?define($):(A=typeof globalThis<"u"?globalThis:A||self).interact=$()})(Et,(function(){"use strict";function A(t,e){var n=Object.keys(t);if(Object.getOwnPropertySymbols){var r=Object.getOwnPropertySymbols(t);e&&(r=r.filter((function(o){return Object.getOwnPropertyDescriptor(t,o).enumerable}))),n.push.apply(n,r)}return n}function $(t){for(var e=1;e<arguments.length;e++){var n=arguments[e]!=null?arguments[e]:{};e%2?A(Object(n),!0).forEach((function(r){m(t,r,n[r])})):Object.getOwnPropertyDescriptors?Object.defineProperties(t,Object.getOwnPropertyDescriptors(n)):A(Object(n)).forEach((function(r){Object.defineProperty(t,r,Object.getOwnPropertyDescriptor(n,r))}))}return t}function L(t){return L=typeof Symbol=="function"&&typeof Symbol.iterator=="symbol"?function(e){return typeof e}:function(e){return e&&typeof Symbol=="function"&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},L(t)}function d(t,e){if(!(t instanceof e))throw new TypeError("Cannot call a class as a function")}function v(t,e){for(var n=0;n<e.length;n++){var r=e[n];r.enumerable=r.enumerable||!1,r.configurable=!0,"value"in r&&(r.writable=!0),Object.defineProperty(t,F(r.key),r)}}function f(t,e,n){return e&&v(t.prototype,e),n&&v(t,n),Object.defineProperty(t,"prototype",{writable:!1}),t}function m(t,e,n){return(e=F(e))in t?Object.defineProperty(t,e,{value:n,enumerable:!0,configurable:!0,writable:!0}):t[e]=n,t}function w(t,e){if(typeof e!="function"&&e!==null)throw new TypeError("Super expression must either be null or a function");t.prototype=Object.create(e&&e.prototype,{constructor:{value:t,writable:!0,configurable:!0}}),Object.defineProperty(t,"prototype",{writable:!1}),e&&E(t,e)}function D(t){return D=Object.setPrototypeOf?Object.getPrototypeOf.bind():function(e){return e.__proto__||Object.getPrototypeOf(e)},D(t)}function E(t,e){return E=Object.setPrototypeOf?Object.setPrototypeOf.bind():function(n,r){return n.__proto__=r,n},E(t,e)}function C(t){if(t===void 0)throw new ReferenceError("this hasn't been initialised - super() hasn't been called");return t}function j(t){var e=(function(){if(typeof Reflect>"u"||!Reflect.construct||Reflect.construct.sham)return!1;if(typeof Proxy=="function")return!0;try{return Boolean.prototype.valueOf.call(Reflect.construct(Boolean,[],(function(){}))),!0}catch{return!1}})();return function(){var n,r=D(t);if(e){var o=D(this).constructor;n=Reflect.construct(r,arguments,o)}else n=r.apply(this,arguments);return(function(i,a){if(a&&(typeof a=="object"||typeof a=="function"))return a;if(a!==void 0)throw new TypeError("Derived constructors may only return object or undefined");return C(i)})(this,n)}}function q(){return q=typeof Reflect<"u"&&Reflect.get?Reflect.get.bind():function(t,e,n){var r=(function(i,a){for(;!Object.prototype.hasOwnProperty.call(i,a)&&(i=D(i))!==null;);return i})(t,e);if(r){var o=Object.getOwnPropertyDescriptor(r,e);return o.get?o.get.call(arguments.length<3?t:n):o.value}},q.apply(this,arguments)}function F(t){var e=(function(n,r){if(typeof n!="object"||n===null)return n;var o=n[Symbol.toPrimitive];if(o!==void 0){var i=o.call(n,r||"default");if(typeof i!="object")return i;throw new TypeError("@@toPrimitive must return a primitive value.")}return(r==="string"?String:Number)(n)})(t,"string");return typeof e=="symbol"?e:e+""}var ce=function(t){return!(!t||!t.Window)&&t instanceof t.Window},Oe=void 0,K=void 0;function me(t){Oe=t;var e=t.document.createTextNode("");e.ownerDocument!==t.document&&typeof t.wrap=="function"&&t.wrap(e)===e&&(t=t.wrap(t)),K=t}function Q(t){return ce(t)?t:(t.ownerDocument||t).defaultView||K.window}typeof window<"u"&&window&&me(window);var De=function(t){return!!t&&L(t)==="object"},Pt=function(t){return typeof t=="function"},g={window:function(t){return t===K||ce(t)},docFrag:function(t){return De(t)&&t.nodeType===11},object:De,func:Pt,number:function(t){return typeof t=="number"},bool:function(t){return typeof t=="boolean"},string:function(t){return typeof t=="string"},element:function(t){if(!t||L(t)!=="object")return!1;var e=Q(t)||K;return/object|function/.test(typeof Element>"u"?"undefined":L(Element))?t instanceof Element||t instanceof e.Element:t.nodeType===1&&typeof t.nodeName=="string"},plainObject:function(t){return De(t)&&!!t.constructor&&/function Object\b/.test(t.constructor.toString())},array:function(t){return De(t)&&t.length!==void 0&&Pt(t.splice)}};function Ke(t){var e=t.interaction;if(e.prepared.name==="drag"){var n=e.prepared.axis;n==="x"?(e.coords.cur.page.y=e.coords.start.page.y,e.coords.cur.client.y=e.coords.start.client.y,e.coords.velocity.client.y=0,e.coords.velocity.page.y=0):n==="y"&&(e.coords.cur.page.x=e.coords.start.page.x,e.coords.cur.client.x=e.coords.start.client.x,e.coords.velocity.client.x=0,e.coords.velocity.page.x=0)}}function zt(t){var e=t.iEvent,n=t.interaction;if(n.prepared.name==="drag"){var r=n.prepared.axis;if(r==="x"||r==="y"){var o=r==="x"?"y":"x";e.page[o]=n.coords.start.page[o],e.client[o]=n.coords.start.client[o],e.delta[o]=0}}}var Me={id:"actions/drag",install:function(t){var e=t.actions,n=t.Interactable,r=t.defaults;n.prototype.draggable=Me.draggable,e.map.drag=Me,e.methodDict.drag="draggable",r.actions.drag=Me.defaults},listeners:{"interactions:before-action-move":Ke,"interactions:action-resume":Ke,"interactions:action-move":zt,"auto-start:check":function(t){var e=t.interaction,n=t.interactable,r=t.buttons,o=n.options.drag;if(o&&o.enabled&&(!e.pointerIsDown||!/mouse|pointer/.test(e.pointerType)||(r&n.options.drag.mouseButtons)!=0))return t.action={name:"drag",axis:o.lockAxis==="start"?o.startAxis:o.lockAxis},!1}},draggable:function(t){return g.object(t)?(this.options.drag.enabled=t.enabled!==!1,this.setPerAction("drag",t),this.setOnEvents("drag",t),/^(xy|x|y|start)$/.test(t.lockAxis)&&(this.options.drag.lockAxis=t.lockAxis),/^(xy|x|y)$/.test(t.startAxis)&&(this.options.drag.startAxis=t.startAxis),this):g.bool(t)?(this.options.drag.enabled=t,this):this.options.drag},beforeMove:Ke,move:zt,defaults:{startAxis:"xy",lockAxis:"xy"},getCursor:function(){return"move"},filterEventType:function(t){return t.search("drag")===0}},It=Me,H={init:function(t){var e=t;H.document=e.document,H.DocumentFragment=e.DocumentFragment||fe,H.SVGElement=e.SVGElement||fe,H.SVGSVGElement=e.SVGSVGElement||fe,H.SVGElementInstance=e.SVGElementInstance||fe,H.Element=e.Element||fe,H.HTMLElement=e.HTMLElement||H.Element,H.Event=e.Event,H.Touch=e.Touch||fe,H.PointerEvent=e.PointerEvent||e.MSPointerEvent},document:null,DocumentFragment:null,SVGElement:null,SVGSVGElement:null,SVGElementInstance:null,Element:null,HTMLElement:null,Event:null,Touch:null,PointerEvent:null};function fe(){}var N=H,W={init:function(t){var e=N.Element,n=t.navigator||{};W.supportsTouch="ontouchstart"in t||g.func(t.DocumentTouch)&&N.document instanceof t.DocumentTouch,W.supportsPointerEvent=n.pointerEnabled!==!1&&!!N.PointerEvent,W.isIOS=/iP(hone|od|ad)/.test(n.platform),W.isIOS7=/iP(hone|od|ad)/.test(n.platform)&&/OS 7[^\d]/.test(n.appVersion),W.isIe9=/MSIE 9/.test(n.userAgent),W.isOperaMobile=n.appName==="Opera"&&W.supportsTouch&&/Presto/.test(n.userAgent),W.prefixedMatchesSelector="matches"in e.prototype?"matches":"webkitMatchesSelector"in e.prototype?"webkitMatchesSelector":"mozMatchesSelector"in e.prototype?"mozMatchesSelector":"oMatchesSelector"in e.prototype?"oMatchesSelector":"msMatchesSelector",W.pEventTypes=W.supportsPointerEvent?N.PointerEvent===t.MSPointerEvent?{up:"MSPointerUp",down:"MSPointerDown",over:"mouseover",out:"mouseout",move:"MSPointerMove",cancel:"MSPointerCancel"}:{up:"pointerup",down:"pointerdown",over:"pointerover",out:"pointerout",move:"pointermove",cancel:"pointercancel"}:null,W.wheelEvent=N.document&&"onmousewheel"in N.document?"mousewheel":"wheel"},supportsTouch:null,supportsPointerEvent:null,isIOS7:null,isIOS:null,isIe9:null,isOperaMobile:null,prefixedMatchesSelector:null,pEventTypes:null,wheelEvent:null},G=W;function le(t,e){if(t.contains)return t.contains(e);for(;e;){if(e===t)return!0;e=e.parentNode}return!1}function Ot(t,e){for(;g.element(t);){if(re(t,e))return t;t=J(t)}return null}function J(t){var e=t.parentNode;if(g.docFrag(e)){for(;(e=e.host)&&g.docFrag(e););return e}return e}function re(t,e){return K!==Oe&&(e=e.replace(/\/deep\//g," ")),t[G.prefixedMatchesSelector](e)}var Ze=function(t){return t.parentNode||t.host};function Dt(t,e){for(var n,r=[],o=t;(n=Ze(o))&&o!==e&&n!==o.ownerDocument;)r.unshift(o),o=n;return r}function Qe(t,e,n){for(;g.element(t);){if(re(t,e))return!0;if((t=J(t))===n)return re(t,e)}return!1}function Mt(t){return t.correspondingUseElement||t}function Je(t){var e=t instanceof N.SVGElement?t.getBoundingClientRect():t.getClientRects()[0];return e&&{left:e.left,right:e.right,top:e.top,bottom:e.bottom,width:e.width||e.right-e.left,height:e.height||e.bottom-e.top}}function et(t){var e,n=Je(t);if(!G.isIOS7&&n){var r={x:(e=(e=Q(t))||K).scrollX||e.document.documentElement.scrollLeft,y:e.scrollY||e.document.documentElement.scrollTop};n.left+=r.x,n.right+=r.x,n.top+=r.y,n.bottom+=r.y}return n}function At(t){for(var e=[];t;)e.push(t),t=J(t);return e}function Ct(t){return!!g.string(t)&&(N.document.querySelector(t),!0)}function S(t,e){for(var n in e)t[n]=e[n];return t}function $t(t,e,n){return t==="parent"?J(n):t==="self"?e.getRect(n):Ot(n,t)}function ye(t,e,n,r){var o=t;return g.string(o)?o=$t(o,e,n):g.func(o)&&(o=o.apply(void 0,r)),g.element(o)&&(o=et(o)),o}function Ae(t){return t&&{x:"x"in t?t.x:t.left,y:"y"in t?t.y:t.top}}function tt(t){return!t||"x"in t&&"y"in t||((t=S({},t)).x=t.left||0,t.y=t.top||0,t.width=t.width||(t.right||0)-t.x,t.height=t.height||(t.bottom||0)-t.y),t}function Ce(t,e,n){t.left&&(e.left+=n.x),t.right&&(e.right+=n.x),t.top&&(e.top+=n.y),t.bottom&&(e.bottom+=n.y),e.width=e.right-e.left,e.height=e.bottom-e.top}function be(t,e,n){var r=n&&t.options[n];return Ae(ye(r&&r.origin||t.options.origin,t,e,[t&&e]))||{x:0,y:0}}function pe(t,e){var n=arguments.length>2&&arguments[2]!==void 0?arguments[2]:function(c){return!0},r=arguments.length>3?arguments[3]:void 0;if(r=r||{},g.string(t)&&t.search(" ")!==-1&&(t=Rt(t)),g.array(t))return t.forEach((function(c){return pe(c,e,n,r)})),r;if(g.object(t)&&(e=t,t=""),g.func(e)&&n(t))r[t]=r[t]||[],r[t].push(e);else if(g.array(e))for(var o=0,i=e;o<i.length;o++){var a=i[o];pe(t,a,n,r)}else if(g.object(e))for(var s in e)pe(Rt(s).map((function(c){return"".concat(t).concat(c)})),e[s],n,r);return r}function Rt(t){return t.trim().split(/ +/)}var xe=function(t,e){return Math.sqrt(t*t+e*e)},Dn=["webkit","moz"];function $e(t,e){t.__set||(t.__set={});var n=function(o){if(Dn.some((function(i){return o.indexOf(i)===0})))return 1;typeof t[o]!="function"&&o!=="__set"&&Object.defineProperty(t,o,{get:function(){return o in t.__set?t.__set[o]:t.__set[o]=e[o]},set:function(i){t.__set[o]=i},configurable:!0})};for(var r in e)n(r);return t}function Re(t,e){t.page=t.page||{},t.page.x=e.page.x,t.page.y=e.page.y,t.client=t.client||{},t.client.x=e.client.x,t.client.y=e.client.y,t.timeStamp=e.timeStamp}function jt(t){t.page.x=0,t.page.y=0,t.client.x=0,t.client.y=0}function Ft(t){return t instanceof N.Event||t instanceof N.Touch}function je(t,e,n){return t=t||"page",(n=n||{}).x=e[t+"X"],n.y=e[t+"Y"],n}function Lt(t,e){return e=e||{x:0,y:0},G.isOperaMobile&&Ft(t)?(je("screen",t,e),e.x+=window.scrollX,e.y+=window.scrollY):je("page",t,e),e}function we(t){return g.number(t.pointerId)?t.pointerId:t.identifier}function Mn(t,e,n){var r=e.length>1?Nt(e):e[0];Lt(r,t.page),(function(o,i){i=i||{},G.isOperaMobile&&Ft(o)?je("screen",o,i):je("client",o,i)})(r,t.client),t.timeStamp=n}function nt(t){var e=[];return g.array(t)?(e[0]=t[0],e[1]=t[1]):t.type==="touchend"?t.touches.length===1?(e[0]=t.touches[0],e[1]=t.changedTouches[0]):t.touches.length===0&&(e[0]=t.changedTouches[0],e[1]=t.changedTouches[1]):(e[0]=t.touches[0],e[1]=t.touches[1]),e}function Nt(t){for(var e={pageX:0,pageY:0,clientX:0,clientY:0,screenX:0,screenY:0},n=0;n<t.length;n++){var r=t[n];for(var o in e)e[o]+=r[o]}for(var i in e)e[i]/=t.length;return e}function rt(t){if(!t.length)return null;var e=nt(t),n=Math.min(e[0].pageX,e[1].pageX),r=Math.min(e[0].pageY,e[1].pageY),o=Math.max(e[0].pageX,e[1].pageX),i=Math.max(e[0].pageY,e[1].pageY);return{x:n,y:r,left:n,top:r,right:o,bottom:i,width:o-n,height:i-r}}function ot(t,e){var n=e+"X",r=e+"Y",o=nt(t),i=o[0][n]-o[1][n],a=o[0][r]-o[1][r];return xe(i,a)}function it(t,e){var n=e+"X",r=e+"Y",o=nt(t),i=o[1][n]-o[0][n],a=o[1][r]-o[0][r];return 180*Math.atan2(a,i)/Math.PI}function Xt(t){return g.string(t.pointerType)?t.pointerType:g.number(t.pointerType)?[void 0,void 0,"touch","pen","mouse"][t.pointerType]:/touch/.test(t.type||"")||t instanceof N.Touch?"touch":"mouse"}function Yt(t){var e=g.func(t.composedPath)?t.composedPath():t.path;return[Mt(e?e[0]:t.target),Mt(t.currentTarget)]}var Fe=(function(){function t(e){d(this,t),this.immediatePropagationStopped=!1,this.propagationStopped=!1,this._interaction=e}return f(t,[{key:"preventDefault",value:function(){}},{key:"stopPropagation",value:function(){this.propagationStopped=!0}},{key:"stopImmediatePropagation",value:function(){this.immediatePropagationStopped=this.propagationStopped=!0}}]),t})();Object.defineProperty(Fe.prototype,"interaction",{get:function(){return this._interaction._proxy},set:function(){}});var qt=function(t,e){for(var n=0;n<e.length;n++){var r=e[n];t.push(r)}return t},Bt=function(t){return qt([],t)},ke=function(t,e){for(var n=0;n<t.length;n++)if(e(t[n],n,t))return n;return-1},_e=function(t,e){return t[ke(t,e)]},ve=(function(t){w(n,t);var e=j(n);function n(r,o,i){var a;d(this,n),(a=e.call(this,o._interaction)).dropzone=void 0,a.dragEvent=void 0,a.relatedTarget=void 0,a.draggable=void 0,a.propagationStopped=!1,a.immediatePropagationStopped=!1;var s=i==="dragleave"?r.prev:r.cur,c=s.element,p=s.dropzone;return a.type=i,a.target=c,a.currentTarget=c,a.dropzone=p,a.dragEvent=o,a.relatedTarget=o.target,a.draggable=o.interactable,a.timeStamp=o.timeStamp,a}return f(n,[{key:"reject",value:function(){var r=this,o=this._interaction.dropState;if(this.type==="dropactivate"||this.dropzone&&o.cur.dropzone===this.dropzone&&o.cur.element===this.target)if(o.prev.dropzone=this.dropzone,o.prev.element=this.target,o.rejected=!0,o.events.enter=null,this.stopImmediatePropagation(),this.type==="dropactivate"){var i=o.activeDrops,a=ke(i,(function(c){var p=c.dropzone,l=c.element;return p===r.dropzone&&l===r.target}));o.activeDrops.splice(a,1);var s=new n(o,this.dragEvent,"dropdeactivate");s.dropzone=this.dropzone,s.target=this.target,this.dropzone.fire(s)}else this.dropzone.fire(new n(o,this.dragEvent,"dragleave"))}},{key:"preventDefault",value:function(){}},{key:"stopPropagation",value:function(){this.propagationStopped=!0}},{key:"stopImmediatePropagation",value:function(){this.immediatePropagationStopped=this.propagationStopped=!0}}]),n})(Fe);function Ht(t,e){for(var n=0,r=t.slice();n<r.length;n++){var o=r[n],i=o.dropzone,a=o.element;e.dropzone=i,e.target=a,i.fire(e),e.propagationStopped=e.immediatePropagationStopped=!1}}function at(t,e){for(var n=(function(i,a){for(var s=[],c=0,p=i.interactables.list;c<p.length;c++){var l=p[c];if(l.options.drop.enabled){var u=l.options.drop.accept;if(!(g.element(u)&&u!==a||g.string(u)&&!re(a,u)||g.func(u)&&!u({dropzone:l,draggableElement:a})))for(var h=0,b=l.getAllElements();h<b.length;h++){var y=b[h];y!==a&&s.push({dropzone:l,element:y,rect:l.getRect(y)})}}}return s})(t,e),r=0;r<n.length;r++){var o=n[r];o.rect=o.dropzone.getRect(o.element)}return n}function Wt(t,e,n){for(var r=t.dropState,o=t.interactable,i=t.element,a=[],s=0,c=r.activeDrops;s<c.length;s++){var p=c[s],l=p.dropzone,u=p.element,h=p.rect,b=l.dropCheck(e,n,o,i,u,h);a.push(b?u:null)}var y=(function(x){for(var _,k,T,I=[],M=0;M<x.length;M++){var P=x[M],O=x[_];if(P&&M!==_)if(O){var Y=Ze(P),R=Ze(O);if(Y!==P.ownerDocument)if(R!==P.ownerDocument)if(Y!==R){I=I.length?I:Dt(O);var B=void 0;if(O instanceof N.HTMLElement&&P instanceof N.SVGElement&&!(P instanceof N.SVGSVGElement)){if(P===R)continue;B=P.ownerSVGElement}else B=P;for(var V=Dt(B,O.ownerDocument),ne=0;V[ne]&&V[ne]===I[ne];)ne++;var Ge=[V[ne-1],V[ne],I[ne]];if(Ge[0])for(var Ie=Ge[0].lastChild;Ie;){if(Ie===Ge[1]){_=M,I=V;break}if(Ie===Ge[2])break;Ie=Ie.previousSibling}}else T=O,(parseInt(Q(k=P).getComputedStyle(k).zIndex,10)||0)>=(parseInt(Q(T).getComputedStyle(T).zIndex,10)||0)&&(_=M);else _=M}else _=M}return _})(a);return r.activeDrops[y]||null}function st(t,e,n){var r=t.dropState,o={enter:null,leave:null,activate:null,deactivate:null,move:null,drop:null};return n.type==="dragstart"&&(o.activate=new ve(r,n,"dropactivate"),o.activate.target=null,o.activate.dropzone=null),n.type==="dragend"&&(o.deactivate=new ve(r,n,"dropdeactivate"),o.deactivate.target=null,o.deactivate.dropzone=null),r.rejected||(r.cur.element!==r.prev.element&&(r.prev.dropzone&&(o.leave=new ve(r,n,"dragleave"),n.dragLeave=o.leave.target=r.prev.element,n.prevDropzone=o.leave.dropzone=r.prev.dropzone),r.cur.dropzone&&(o.enter=new ve(r,n,"dragenter"),n.dragEnter=r.cur.element,n.dropzone=r.cur.dropzone)),n.type==="dragend"&&r.cur.dropzone&&(o.drop=new ve(r,n,"drop"),n.dropzone=r.cur.dropzone,n.relatedTarget=r.cur.element),n.type==="dragmove"&&r.cur.dropzone&&(o.move=new ve(r,n,"dropmove"),n.dropzone=r.cur.dropzone)),o}function ct(t,e){var n=t.dropState,r=n.activeDrops,o=n.cur,i=n.prev;e.leave&&i.dropzone.fire(e.leave),e.enter&&o.dropzone.fire(e.enter),e.move&&o.dropzone.fire(e.move),e.drop&&o.dropzone.fire(e.drop),e.deactivate&&Ht(r,e.deactivate),n.prev.dropzone=o.dropzone,n.prev.element=o.element}function Gt(t,e){var n=t.interaction,r=t.iEvent,o=t.event;if(r.type==="dragmove"||r.type==="dragend"){var i=n.dropState;e.dynamicDrop&&(i.activeDrops=at(e,n.element));var a=r,s=Wt(n,a,o);i.rejected=i.rejected&&!!s&&s.dropzone===i.cur.dropzone&&s.element===i.cur.element,i.cur.dropzone=s&&s.dropzone,i.cur.element=s&&s.element,i.events=st(n,0,a)}}var Vt={id:"actions/drop",install:function(t){var e=t.actions,n=t.interactStatic,r=t.Interactable,o=t.defaults;t.usePlugin(It),r.prototype.dropzone=function(i){return(function(a,s){if(g.object(s)){if(a.options.drop.enabled=s.enabled!==!1,s.listeners){var c=pe(s.listeners),p=Object.keys(c).reduce((function(u,h){return u[/^(enter|leave)/.test(h)?"drag".concat(h):/^(activate|deactivate|move)/.test(h)?"drop".concat(h):h]=c[h],u}),{}),l=a.options.drop.listeners;l&&a.off(l),a.on(p),a.options.drop.listeners=p}return g.func(s.ondrop)&&a.on("drop",s.ondrop),g.func(s.ondropactivate)&&a.on("dropactivate",s.ondropactivate),g.func(s.ondropdeactivate)&&a.on("dropdeactivate",s.ondropdeactivate),g.func(s.ondragenter)&&a.on("dragenter",s.ondragenter),g.func(s.ondragleave)&&a.on("dragleave",s.ondragleave),g.func(s.ondropmove)&&a.on("dropmove",s.ondropmove),/^(pointer|center)$/.test(s.overlap)?a.options.drop.overlap=s.overlap:g.number(s.overlap)&&(a.options.drop.overlap=Math.max(Math.min(1,s.overlap),0)),"accept"in s&&(a.options.drop.accept=s.accept),"checker"in s&&(a.options.drop.checker=s.checker),a}return g.bool(s)?(a.options.drop.enabled=s,a):a.options.drop})(this,i)},r.prototype.dropCheck=function(i,a,s,c,p,l){return(function(u,h,b,y,x,_,k){var T=!1;if(!(k=k||u.getRect(_)))return!!u.options.drop.checker&&u.options.drop.checker(h,b,T,u,_,y,x);var I=u.options.drop.overlap;if(I==="pointer"){var M=be(y,x,"drag"),P=Lt(h);P.x+=M.x,P.y+=M.y;var O=P.x>k.left&&P.x<k.right,Y=P.y>k.top&&P.y<k.bottom;T=O&&Y}var R=y.getRect(x);if(R&&I==="center"){var B=R.left+R.width/2,V=R.top+R.height/2;T=B>=k.left&&B<=k.right&&V>=k.top&&V<=k.bottom}return R&&g.number(I)&&(T=Math.max(0,Math.min(k.right,R.right)-Math.max(k.left,R.left))*Math.max(0,Math.min(k.bottom,R.bottom)-Math.max(k.top,R.top))/(R.width*R.height)>=I),u.options.drop.checker&&(T=u.options.drop.checker(h,b,T,u,_,y,x)),T})(this,i,a,s,c,p,l)},n.dynamicDrop=function(i){return g.bool(i)?(t.dynamicDrop=i,n):t.dynamicDrop},S(e.phaselessTypes,{dragenter:!0,dragleave:!0,dropactivate:!0,dropdeactivate:!0,dropmove:!0,drop:!0}),e.methodDict.drop="dropzone",t.dynamicDrop=!1,o.actions.drop=Vt.defaults},listeners:{"interactions:before-action-start":function(t){var e=t.interaction;e.prepared.name==="drag"&&(e.dropState={cur:{dropzone:null,element:null},prev:{dropzone:null,element:null},rejected:null,events:null,activeDrops:[]})},"interactions:after-action-start":function(t,e){var n=t.interaction,r=(t.event,t.iEvent);if(n.prepared.name==="drag"){var o=n.dropState;o.activeDrops=[],o.events={},o.activeDrops=at(e,n.element),o.events=st(n,0,r),o.events.activate&&(Ht(o.activeDrops,o.events.activate),e.fire("actions/drop:start",{interaction:n,dragEvent:r}))}},"interactions:action-move":Gt,"interactions:after-action-move":function(t,e){var n=t.interaction,r=t.iEvent;if(n.prepared.name==="drag"){var o=n.dropState;ct(n,o.events),e.fire("actions/drop:move",{interaction:n,dragEvent:r}),o.events={}}},"interactions:action-end":function(t,e){if(t.interaction.prepared.name==="drag"){var n=t.interaction,r=t.iEvent;Gt(t,e),ct(n,n.dropState.events),e.fire("actions/drop:end",{interaction:n,dragEvent:r})}},"interactions:stop":function(t){var e=t.interaction;if(e.prepared.name==="drag"){var n=e.dropState;n&&(n.activeDrops=null,n.events=null,n.cur.dropzone=null,n.cur.element=null,n.prev.dropzone=null,n.prev.element=null,n.rejected=!1)}}},getActiveDrops:at,getDrop:Wt,getDropEvents:st,fireDropEvents:ct,filterEventType:function(t){return t.search("drag")===0||t.search("drop")===0},defaults:{enabled:!1,accept:null,overlap:"pointer"}},An=Vt;function lt(t){var e=t.interaction,n=t.iEvent,r=t.phase;if(e.prepared.name==="gesture"){var o=e.pointers.map((function(p){return p.pointer})),i=r==="start",a=r==="end",s=e.interactable.options.deltaSource;if(n.touches=[o[0],o[1]],i)n.distance=ot(o,s),n.box=rt(o),n.scale=1,n.ds=0,n.angle=it(o,s),n.da=0,e.gesture.startDistance=n.distance,e.gesture.startAngle=n.angle;else if(a||e.pointers.length<2){var c=e.prevEvent;n.distance=c.distance,n.box=c.box,n.scale=c.scale,n.ds=0,n.angle=c.angle,n.da=0}else n.distance=ot(o,s),n.box=rt(o),n.scale=n.distance/e.gesture.startDistance,n.angle=it(o,s),n.ds=n.scale-e.gesture.scale,n.da=n.angle-e.gesture.angle;e.gesture.distance=n.distance,e.gesture.angle=n.angle,g.number(n.scale)&&n.scale!==1/0&&!isNaN(n.scale)&&(e.gesture.scale=n.scale)}}var pt={id:"actions/gesture",before:["actions/drag","actions/resize"],install:function(t){var e=t.actions,n=t.Interactable,r=t.defaults;n.prototype.gesturable=function(o){return g.object(o)?(this.options.gesture.enabled=o.enabled!==!1,this.setPerAction("gesture",o),this.setOnEvents("gesture",o),this):g.bool(o)?(this.options.gesture.enabled=o,this):this.options.gesture},e.map.gesture=pt,e.methodDict.gesture="gesturable",r.actions.gesture=pt.defaults},listeners:{"interactions:action-start":lt,"interactions:action-move":lt,"interactions:action-end":lt,"interactions:new":function(t){t.interaction.gesture={angle:0,distance:0,scale:1,startAngle:0,startDistance:0}},"auto-start:check":function(t){if(!(t.interaction.pointers.length<2)){var e=t.interactable.options.gesture;if(e&&e.enabled)return t.action={name:"gesture"},!1}}},defaults:{},getCursor:function(){return""},filterEventType:function(t){return t.search("gesture")===0}},Cn=pt;function $n(t,e,n,r,o,i,a){if(!e)return!1;if(e===!0){var s=g.number(i.width)?i.width:i.right-i.left,c=g.number(i.height)?i.height:i.bottom-i.top;if(a=Math.min(a,Math.abs((t==="left"||t==="right"?s:c)/2)),s<0&&(t==="left"?t="right":t==="right"&&(t="left")),c<0&&(t==="top"?t="bottom":t==="bottom"&&(t="top")),t==="left"){var p=s>=0?i.left:i.right;return n.x<p+a}if(t==="top"){var l=c>=0?i.top:i.bottom;return n.y<l+a}if(t==="right")return n.x>(s>=0?i.right:i.left)-a;if(t==="bottom")return n.y>(c>=0?i.bottom:i.top)-a}return!!g.element(r)&&(g.element(e)?e===r:Qe(r,e,o))}function Ut(t){var e=t.iEvent,n=t.interaction;if(n.prepared.name==="resize"&&n.resizeAxes){var r=e;n.interactable.options.resize.square?(n.resizeAxes==="y"?r.delta.x=r.delta.y:r.delta.y=r.delta.x,r.axes="xy"):(r.axes=n.resizeAxes,n.resizeAxes==="x"?r.delta.y=0:n.resizeAxes==="y"&&(r.delta.x=0))}}var ee,ue,te={id:"actions/resize",before:["actions/drag"],install:function(t){var e=t.actions,n=t.browser,r=t.Interactable,o=t.defaults;te.cursors=(function(i){return i.isIe9?{x:"e-resize",y:"s-resize",xy:"se-resize",top:"n-resize",left:"w-resize",bottom:"s-resize",right:"e-resize",topleft:"se-resize",bottomright:"se-resize",topright:"ne-resize",bottomleft:"ne-resize"}:{x:"ew-resize",y:"ns-resize",xy:"nwse-resize",top:"ns-resize",left:"ew-resize",bottom:"ns-resize",right:"ew-resize",topleft:"nwse-resize",bottomright:"nwse-resize",topright:"nesw-resize",bottomleft:"nesw-resize"}})(n),te.defaultMargin=n.supportsTouch||n.supportsPointerEvent?20:10,r.prototype.resizable=function(i){return(function(a,s,c){return g.object(s)?(a.options.resize.enabled=s.enabled!==!1,a.setPerAction("resize",s),a.setOnEvents("resize",s),g.string(s.axis)&&/^x$|^y$|^xy$/.test(s.axis)?a.options.resize.axis=s.axis:s.axis===null&&(a.options.resize.axis=c.defaults.actions.resize.axis),g.bool(s.preserveAspectRatio)?a.options.resize.preserveAspectRatio=s.preserveAspectRatio:g.bool(s.square)&&(a.options.resize.square=s.square),a):g.bool(s)?(a.options.resize.enabled=s,a):a.options.resize})(this,i,t)},e.map.resize=te,e.methodDict.resize="resizable",o.actions.resize=te.defaults},listeners:{"interactions:new":function(t){t.interaction.resizeAxes="xy"},"interactions:action-start":function(t){(function(e){var n=e.iEvent,r=e.interaction;if(r.prepared.name==="resize"&&r.prepared.edges){var o=n,i=r.rect;r._rects={start:S({},i),corrected:S({},i),previous:S({},i),delta:{left:0,right:0,width:0,top:0,bottom:0,height:0}},o.edges=r.prepared.edges,o.rect=r._rects.corrected,o.deltaRect=r._rects.delta}})(t),Ut(t)},"interactions:action-move":function(t){(function(e){var n=e.iEvent,r=e.interaction;if(r.prepared.name==="resize"&&r.prepared.edges){var o=n,i=r.interactable.options.resize.invert,a=i==="reposition"||i==="negate",s=r.rect,c=r._rects,p=c.start,l=c.corrected,u=c.delta,h=c.previous;if(S(h,l),a){if(S(l,s),i==="reposition"){if(l.top>l.bottom){var b=l.top;l.top=l.bottom,l.bottom=b}if(l.left>l.right){var y=l.left;l.left=l.right,l.right=y}}}else l.top=Math.min(s.top,p.bottom),l.bottom=Math.max(s.bottom,p.top),l.left=Math.min(s.left,p.right),l.right=Math.max(s.right,p.left);for(var x in l.width=l.right-l.left,l.height=l.bottom-l.top,l)u[x]=l[x]-h[x];o.edges=r.prepared.edges,o.rect=l,o.deltaRect=u}})(t),Ut(t)},"interactions:action-end":function(t){var e=t.iEvent,n=t.interaction;if(n.prepared.name==="resize"&&n.prepared.edges){var r=e;r.edges=n.prepared.edges,r.rect=n._rects.corrected,r.deltaRect=n._rects.delta}},"auto-start:check":function(t){var e=t.interaction,n=t.interactable,r=t.element,o=t.rect,i=t.buttons;if(o){var a=S({},e.coords.cur.page),s=n.options.resize;if(s&&s.enabled&&(!e.pointerIsDown||!/mouse|pointer/.test(e.pointerType)||(i&s.mouseButtons)!=0)){if(g.object(s.edges)){var c={left:!1,right:!1,top:!1,bottom:!1};for(var p in c)c[p]=$n(p,s.edges[p],a,e._latestPointer.eventTarget,r,o,s.margin||te.defaultMargin);c.left=c.left&&!c.right,c.top=c.top&&!c.bottom,(c.left||c.right||c.top||c.bottom)&&(t.action={name:"resize",edges:c})}else{var l=s.axis!=="y"&&a.x>o.right-te.defaultMargin,u=s.axis!=="x"&&a.y>o.bottom-te.defaultMargin;(l||u)&&(t.action={name:"resize",axes:(l?"x":"")+(u?"y":"")})}return!t.action&&void 0}}}},defaults:{square:!1,preserveAspectRatio:!1,axis:"xy",margin:NaN,edges:null,invert:"none"},cursors:null,getCursor:function(t){var e=t.edges,n=t.axis,r=t.name,o=te.cursors,i=null;if(n)i=o[r+n];else if(e){for(var a="",s=0,c=["top","bottom","left","right"];s<c.length;s++){var p=c[s];e[p]&&(a+=p)}i=o[a]}return i},filterEventType:function(t){return t.search("resize")===0},defaultMargin:null},Rn=te,jn={id:"actions",install:function(t){t.usePlugin(Cn),t.usePlugin(Rn),t.usePlugin(It),t.usePlugin(An)}},Kt=0,oe={request:function(t){return ee(t)},cancel:function(t){return ue(t)},init:function(t){if(ee=t.requestAnimationFrame,ue=t.cancelAnimationFrame,!ee)for(var e=["ms","moz","webkit","o"],n=0;n<e.length;n++){var r=e[n];ee=t["".concat(r,"RequestAnimationFrame")],ue=t["".concat(r,"CancelAnimationFrame")]||t["".concat(r,"CancelRequestAnimationFrame")]}ee=ee&&ee.bind(t),ue=ue&&ue.bind(t),ee||(ee=function(o){var i=Date.now(),a=Math.max(0,16-(i-Kt)),s=t.setTimeout((function(){o(i+a)}),a);return Kt=i+a,s},ue=function(o){return clearTimeout(o)})}},z={defaults:{enabled:!1,margin:60,container:null,speed:300},now:Date.now,interaction:null,i:0,x:0,y:0,isScrolling:!1,prevTime:0,margin:0,speed:0,start:function(t){z.isScrolling=!0,oe.cancel(z.i),t.autoScroll=z,z.interaction=t,z.prevTime=z.now(),z.i=oe.request(z.scroll)},stop:function(){z.isScrolling=!1,z.interaction&&(z.interaction.autoScroll=null),oe.cancel(z.i)},scroll:function(){var t=z.interaction,e=t.interactable,n=t.element,r=t.prepared.name,o=e.options[r].autoScroll,i=Zt(o.container,e,n),a=z.now(),s=(a-z.prevTime)/1e3,c=o.speed*s;if(c>=1){var p={x:z.x*c,y:z.y*c};if(p.x||p.y){var l=Qt(i);g.window(i)?i.scrollBy(p.x,p.y):i&&(i.scrollLeft+=p.x,i.scrollTop+=p.y);var u=Qt(i),h={x:u.x-l.x,y:u.y-l.y};(h.x||h.y)&&e.fire({type:"autoscroll",target:n,interactable:e,delta:h,interaction:t,container:i})}z.prevTime=a}z.isScrolling&&(oe.cancel(z.i),z.i=oe.request(z.scroll))},check:function(t,e){var n;return(n=t.options[e].autoScroll)==null?void 0:n.enabled},onInteractionMove:function(t){var e=t.interaction,n=t.pointer;if(e.interacting()&&z.check(e.interactable,e.prepared.name))if(e.simulation)z.x=z.y=0;else{var r,o,i,a,s=e.interactable,c=e.element,p=e.prepared.name,l=s.options[p].autoScroll,u=Zt(l.container,s,c);if(g.window(u))a=n.clientX<z.margin,r=n.clientY<z.margin,o=n.clientX>u.innerWidth-z.margin,i=n.clientY>u.innerHeight-z.margin;else{var h=Je(u);a=n.clientX<h.left+z.margin,r=n.clientY<h.top+z.margin,o=n.clientX>h.right-z.margin,i=n.clientY>h.bottom-z.margin}z.x=o?1:a?-1:0,z.y=i?1:r?-1:0,z.isScrolling||(z.margin=l.margin,z.speed=l.speed,z.start(e))}}};function Zt(t,e,n){return(g.string(t)?$t(t,e,n):t)||Q(n)}function Qt(t){return g.window(t)&&(t=window.document.body),{x:t.scrollLeft,y:t.scrollTop}}var Fn={id:"auto-scroll",install:function(t){var e=t.defaults,n=t.actions;t.autoScroll=z,z.now=function(){return t.now()},n.phaselessTypes.autoscroll=!0,e.perAction.autoScroll=z.defaults},listeners:{"interactions:new":function(t){t.interaction.autoScroll=null},"interactions:destroy":function(t){t.interaction.autoScroll=null,z.stop(),z.interaction&&(z.interaction=null)},"interactions:stop":z.stop,"interactions:action-move":function(t){return z.onInteractionMove(t)}}},Ln=Fn;function Ee(t,e){var n=!1;return function(){return n||(K.console.warn(e),n=!0),t.apply(this,arguments)}}function ut(t,e){return t.name=e.name,t.axis=e.axis,t.edges=e.edges,t}function Nn(t){return g.bool(t)?(this.options.styleCursor=t,this):t===null?(delete this.options.styleCursor,this):this.options.styleCursor}function Xn(t){return g.func(t)?(this.options.actionChecker=t,this):t===null?(delete this.options.actionChecker,this):this.options.actionChecker}var Yn={id:"auto-start/interactableMethods",install:function(t){var e=t.Interactable;e.prototype.getAction=function(n,r,o,i){var a=(function(s,c,p,l,u){var h=s.getRect(l),b=c.buttons||{0:1,1:4,3:8,4:16}[c.button],y={action:null,interactable:s,interaction:p,element:l,rect:h,buttons:b};return u.fire("auto-start:check",y),y.action})(this,r,o,i,t);return this.options.actionChecker?this.options.actionChecker(n,r,a,this,i,o):a},e.prototype.ignoreFrom=Ee((function(n){return this._backCompatOption("ignoreFrom",n)}),"Interactable.ignoreFrom() has been deprecated. Use Interactble.draggable({ignoreFrom: newValue})."),e.prototype.allowFrom=Ee((function(n){return this._backCompatOption("allowFrom",n)}),"Interactable.allowFrom() has been deprecated. Use Interactble.draggable({allowFrom: newValue})."),e.prototype.actionChecker=Xn,e.prototype.styleCursor=Nn}};function Jt(t,e,n,r,o){return e.testIgnoreAllow(e.options[t.name],n,r)&&e.options[t.name].enabled&&Le(e,n,t,o)?t:null}function qn(t,e,n,r,o,i,a){for(var s=0,c=r.length;s<c;s++){var p=r[s],l=o[s],u=p.getAction(e,n,t,l);if(u){var h=Jt(u,p,l,i,a);if(h)return{action:h,interactable:p,element:l}}}return{action:null,interactable:null,element:null}}function en(t,e,n,r,o){var i=[],a=[],s=r;function c(l){i.push(l),a.push(s)}for(;g.element(s);){i=[],a=[],o.interactables.forEachMatch(s,c);var p=qn(t,e,n,i,a,r,o);if(p.action&&!p.interactable.options[p.action.name].manualStart)return p;s=J(s)}return{action:null,interactable:null,element:null}}function tn(t,e,n){var r=e.action,o=e.interactable,i=e.element;r=r||{name:null},t.interactable=o,t.element=i,ut(t.prepared,r),t.rect=o&&r.name?o.getRect(i):null,rn(t,n),n.fire("autoStart:prepared",{interaction:t})}function Le(t,e,n,r){var o=t.options,i=o[n.name].max,a=o[n.name].maxPerElement,s=r.autoStart.maxInteractions,c=0,p=0,l=0;if(!(i&&a&&s))return!1;for(var u=0,h=r.interactions.list;u<h.length;u++){var b=h[u],y=b.prepared.name;if(b.interacting()&&(++c>=s||b.interactable===t&&((p+=y===n.name?1:0)>=i||b.element===e&&(l++,y===n.name&&l>=a))))return!1}return s>0}function nn(t,e){return g.number(t)?(e.autoStart.maxInteractions=t,this):e.autoStart.maxInteractions}function dt(t,e,n){var r=n.autoStart.cursorElement;r&&r!==t&&(r.style.cursor=""),t.ownerDocument.documentElement.style.cursor=e,t.style.cursor=e,n.autoStart.cursorElement=e?t:null}function rn(t,e){var n=t.interactable,r=t.element,o=t.prepared;if(t.pointerType==="mouse"&&n&&n.options.styleCursor){var i="";if(o.name){var a=n.options[o.name].cursorChecker;i=g.func(a)?a(o,n,r,t._interacting):e.actions.map[o.name].getCursor(o)}dt(t.element,i||"",e)}else e.autoStart.cursorElement&&dt(e.autoStart.cursorElement,"",e)}var Bn={id:"auto-start/base",before:["actions"],install:function(t){var e=t.interactStatic,n=t.defaults;t.usePlugin(Yn),n.base.actionChecker=null,n.base.styleCursor=!0,S(n.perAction,{manualStart:!1,max:1/0,maxPerElement:1,allowFrom:null,ignoreFrom:null,mouseButtons:1}),e.maxInteractions=function(r){return nn(r,t)},t.autoStart={maxInteractions:1/0,withinInteractionLimit:Le,cursorElement:null}},listeners:{"interactions:down":function(t,e){var n=t.interaction,r=t.pointer,o=t.event,i=t.eventTarget;n.interacting()||tn(n,en(n,r,o,i,e),e)},"interactions:move":function(t,e){(function(n,r){var o=n.interaction,i=n.pointer,a=n.event,s=n.eventTarget;o.pointerType!=="mouse"||o.pointerIsDown||o.interacting()||tn(o,en(o,i,a,s,r),r)})(t,e),(function(n,r){var o=n.interaction;if(o.pointerIsDown&&!o.interacting()&&o.pointerWasMoved&&o.prepared.name){r.fire("autoStart:before-start",n);var i=o.interactable,a=o.prepared.name;a&&i&&(i.options[a].manualStart||!Le(i,o.element,o.prepared,r)?o.stop():(o.start(o.prepared,i,o.element),rn(o,r)))}})(t,e)},"interactions:stop":function(t,e){var n=t.interaction,r=n.interactable;r&&r.options.styleCursor&&dt(n.element,"",e)}},maxInteractions:nn,withinInteractionLimit:Le,validateAction:Jt},ht=Bn,Hn={id:"auto-start/dragAxis",listeners:{"autoStart:before-start":function(t,e){var n=t.interaction,r=t.eventTarget,o=t.dx,i=t.dy;if(n.prepared.name==="drag"){var a=Math.abs(o),s=Math.abs(i),c=n.interactable.options.drag,p=c.startAxis,l=a>s?"x":a<s?"y":"xy";if(n.prepared.axis=c.lockAxis==="start"?l[0]:c.lockAxis,l!=="xy"&&p!=="xy"&&p!==l){n.prepared.name=null;for(var u=r,h=function(y){if(y!==n.interactable){var x=n.interactable.options.drag;if(!x.manualStart&&y.testIgnoreAllow(x,u,r)){var _=y.getAction(n.downPointer,n.downEvent,n,u);if(_&&_.name==="drag"&&(function(k,T){if(!T)return!1;var I=T.options.drag.startAxis;return k==="xy"||I==="xy"||I===k})(l,y)&&ht.validateAction(_,y,u,r,e))return y}}};g.element(u);){var b=e.interactables.forEachMatch(u,h);if(b){n.prepared.name="drag",n.interactable=b,n.element=u;break}u=J(u)}}}}}};function ft(t){var e=t.prepared&&t.prepared.name;if(!e)return null;var n=t.interactable.options;return n[e].hold||n[e].delay}var Wn={id:"auto-start/hold",install:function(t){var e=t.defaults;t.usePlugin(ht),e.perAction.hold=0,e.perAction.delay=0},listeners:{"interactions:new":function(t){t.interaction.autoStartHoldTimer=null},"autoStart:prepared":function(t){var e=t.interaction,n=ft(e);n>0&&(e.autoStartHoldTimer=setTimeout((function(){e.start(e.prepared,e.interactable,e.element)}),n))},"interactions:move":function(t){var e=t.interaction,n=t.duplicate;e.autoStartHoldTimer&&e.pointerWasMoved&&!n&&(clearTimeout(e.autoStartHoldTimer),e.autoStartHoldTimer=null)},"autoStart:before-start":function(t){var e=t.interaction;ft(e)>0&&(e.prepared.name=null)}},getHoldDuration:ft},Gn=Wn,Vn={id:"auto-start",install:function(t){t.usePlugin(ht),t.usePlugin(Gn),t.usePlugin(Hn)}},Un=function(t){return/^(always|never|auto)$/.test(t)?(this.options.preventDefault=t,this):g.bool(t)?(this.options.preventDefault=t?"always":"never",this):this.options.preventDefault};function Kn(t){var e=t.interaction,n=t.event;e.interactable&&e.interactable.checkAndPreventDefault(n)}var on={id:"core/interactablePreventDefault",install:function(t){var e=t.Interactable;e.prototype.preventDefault=Un,e.prototype.checkAndPreventDefault=function(n){return(function(r,o,i){var a=r.options.preventDefault;if(a!=="never")if(a!=="always"){if(o.events.supportsPassive&&/^touch(start|move)$/.test(i.type)){var s=Q(i.target).document,c=o.getDocOptions(s);if(!c||!c.events||c.events.passive!==!1)return}/^(mouse|pointer|touch)*(down|start)/i.test(i.type)||g.element(i.target)&&re(i.target,"input,select,textarea,[contenteditable=true],[contenteditable=true] *")||i.preventDefault()}else i.preventDefault()})(this,t,n)},t.interactions.docEvents.push({type:"dragstart",listener:function(n){for(var r=0,o=t.interactions.list;r<o.length;r++){var i=o[r];if(i.element&&(i.element===n.target||le(i.element,n.target)))return void i.interactable.checkAndPreventDefault(n)}}})},listeners:["down","move","up","cancel"].reduce((function(t,e){return t["interactions:".concat(e)]=Kn,t}),{})};function Ne(t,e){if(e.phaselessTypes[t])return!0;for(var n in e.map)if(t.indexOf(n)===0&&t.substr(n.length)in e.phases)return!0;return!1}function ge(t){var e={};for(var n in t){var r=t[n];g.plainObject(r)?e[n]=ge(r):g.array(r)?e[n]=Bt(r):e[n]=r}return e}var vt=(function(){function t(e){d(this,t),this.states=[],this.startOffset={left:0,right:0,top:0,bottom:0},this.startDelta=void 0,this.result=void 0,this.endResult=void 0,this.startEdges=void 0,this.edges=void 0,this.interaction=void 0,this.interaction=e,this.result=Xe(),this.edges={left:!1,right:!1,top:!1,bottom:!1}}return f(t,[{key:"start",value:function(e,n){var r,o,i=e.phase,a=this.interaction,s=(function(p){var l=p.interactable.options[p.prepared.name],u=l.modifiers;return u&&u.length?u:["snap","snapSize","snapEdges","restrict","restrictEdges","restrictSize"].map((function(h){var b=l[h];return b&&b.enabled&&{options:b,methods:b._methods}})).filter((function(h){return!!h}))})(a);this.prepareStates(s),this.startEdges=S({},a.edges),this.edges=S({},this.startEdges),this.startOffset=(r=a.rect,o=n,r?{left:o.x-r.left,top:o.y-r.top,right:r.right-o.x,bottom:r.bottom-o.y}:{left:0,top:0,right:0,bottom:0}),this.startDelta={x:0,y:0};var c=this.fillArg({phase:i,pageCoords:n,preEnd:!1});return this.result=Xe(),this.startAll(c),this.result=this.setAll(c)}},{key:"fillArg",value:function(e){var n=this.interaction;return e.interaction=n,e.interactable=n.interactable,e.element=n.element,e.rect||(e.rect=n.rect),e.edges||(e.edges=this.startEdges),e.startOffset=this.startOffset,e}},{key:"startAll",value:function(e){for(var n=0,r=this.states;n<r.length;n++){var o=r[n];o.methods.start&&(e.state=o,o.methods.start(e))}}},{key:"setAll",value:function(e){var n=e.phase,r=e.preEnd,o=e.skipModifiers,i=e.rect,a=e.edges;e.coords=S({},e.pageCoords),e.rect=S({},i),e.edges=S({},a);for(var s=o?this.states.slice(o):this.states,c=Xe(e.coords,e.rect),p=0;p<s.length;p++){var l,u=s[p],h=u.options,b=S({},e.coords),y=null;(l=u.methods)!=null&&l.set&&this.shouldDo(h,r,n)&&(e.state=u,y=u.methods.set(e),Ce(e.edges,e.rect,{x:e.coords.x-b.x,y:e.coords.y-b.y})),c.eventProps.push(y)}S(this.edges,e.edges),c.delta.x=e.coords.x-e.pageCoords.x,c.delta.y=e.coords.y-e.pageCoords.y,c.rectDelta.left=e.rect.left-i.left,c.rectDelta.right=e.rect.right-i.right,c.rectDelta.top=e.rect.top-i.top,c.rectDelta.bottom=e.rect.bottom-i.bottom;var x=this.result.coords,_=this.result.rect;if(x&&_){var k=c.rect.left!==_.left||c.rect.right!==_.right||c.rect.top!==_.top||c.rect.bottom!==_.bottom;c.changed=k||x.x!==c.coords.x||x.y!==c.coords.y}return c}},{key:"applyToInteraction",value:function(e){var n=this.interaction,r=e.phase,o=n.coords.cur,i=n.coords.start,a=this.result,s=this.startDelta,c=a.delta;r==="start"&&S(this.startDelta,a.delta);for(var p=0,l=[[i,s],[o,c]];p<l.length;p++){var u=l[p],h=u[0],b=u[1];h.page.x+=b.x,h.page.y+=b.y,h.client.x+=b.x,h.client.y+=b.y}var y=this.result.rectDelta,x=e.rect||n.rect;x.left+=y.left,x.right+=y.right,x.top+=y.top,x.bottom+=y.bottom,x.width=x.right-x.left,x.height=x.bottom-x.top}},{key:"setAndApply",value:function(e){var n=this.interaction,r=e.phase,o=e.preEnd,i=e.skipModifiers,a=this.setAll(this.fillArg({preEnd:o,phase:r,pageCoords:e.modifiedCoords||n.coords.cur.page}));if(this.result=a,!a.changed&&(!i||i<this.states.length)&&n.interacting())return!1;if(e.modifiedCoords){var s=n.coords.cur.page,c={x:e.modifiedCoords.x-s.x,y:e.modifiedCoords.y-s.y};a.coords.x+=c.x,a.coords.y+=c.y,a.delta.x+=c.x,a.delta.y+=c.y}this.applyToInteraction(e)}},{key:"beforeEnd",value:function(e){var n=e.interaction,r=e.event,o=this.states;if(o&&o.length){for(var i=!1,a=0;a<o.length;a++){var s=o[a];e.state=s;var c=s.options,p=s.methods,l=p.beforeEnd&&p.beforeEnd(e);if(l)return this.endResult=l,!1;i=i||!i&&this.shouldDo(c,!0,e.phase,!0)}i&&n.move({event:r,preEnd:!0})}}},{key:"stop",value:function(e){var n=e.interaction;if(this.states&&this.states.length){var r=S({states:this.states,interactable:n.interactable,element:n.element,rect:null},e);this.fillArg(r);for(var o=0,i=this.states;o<i.length;o++){var a=i[o];r.state=a,a.methods.stop&&a.methods.stop(r)}this.states=null,this.endResult=null}}},{key:"prepareStates",value:function(e){this.states=[];for(var n=0;n<e.length;n++){var r=e[n],o=r.options,i=r.methods,a=r.name;this.states.push({options:o,methods:i,index:n,name:a})}return this.states}},{key:"restoreInteractionCoords",value:function(e){var n=e.interaction,r=n.coords,o=n.rect,i=n.modification;if(i.result){for(var a=i.startDelta,s=i.result,c=s.delta,p=s.rectDelta,l=0,u=[[r.start,a],[r.cur,c]];l<u.length;l++){var h=u[l],b=h[0],y=h[1];b.page.x-=y.x,b.page.y-=y.y,b.client.x-=y.x,b.client.y-=y.y}o.left-=p.left,o.right-=p.right,o.top-=p.top,o.bottom-=p.bottom}}},{key:"shouldDo",value:function(e,n,r,o){return!(!e||e.enabled===!1||o&&!e.endOnly||e.endOnly&&!n||r==="start"&&!e.setStart)}},{key:"copyFrom",value:function(e){this.startOffset=e.startOffset,this.startDelta=e.startDelta,this.startEdges=e.startEdges,this.edges=e.edges,this.states=e.states.map((function(n){return ge(n)})),this.result=Xe(S({},e.result.coords),S({},e.result.rect))}},{key:"destroy",value:function(){for(var e in this)this[e]=null}}]),t})();function Xe(t,e){return{rect:e,coords:t,delta:{x:0,y:0},rectDelta:{left:0,right:0,top:0,bottom:0},eventProps:[],changed:!0}}function ie(t,e){var n=t.defaults,r={start:t.start,set:t.set,beforeEnd:t.beforeEnd,stop:t.stop},o=function(i){var a=i||{};for(var s in a.enabled=a.enabled!==!1,n)s in a||(a[s]=n[s]);var c={options:a,methods:r,name:e,enable:function(){return a.enabled=!0,c},disable:function(){return a.enabled=!1,c}};return c};return e&&typeof e=="string"&&(o._defaults=n,o._methods=r),o}function Te(t){var e=t.iEvent,n=t.interaction.modification.result;n&&(e.modifiers=n.eventProps)}var Zn={id:"modifiers/base",before:["actions"],install:function(t){t.defaults.perAction.modifiers=[]},listeners:{"interactions:new":function(t){var e=t.interaction;e.modification=new vt(e)},"interactions:before-action-start":function(t){var e=t.interaction,n=t.interaction.modification;n.start(t,e.coords.start.page),e.edges=n.edges,n.applyToInteraction(t)},"interactions:before-action-move":function(t){var e=t.interaction,n=e.modification,r=n.setAndApply(t);return e.edges=n.edges,r},"interactions:before-action-end":function(t){var e=t.interaction,n=e.modification,r=n.beforeEnd(t);return e.edges=n.startEdges,r},"interactions:action-start":Te,"interactions:action-move":Te,"interactions:action-end":Te,"interactions:after-action-start":function(t){return t.interaction.modification.restoreInteractionCoords(t)},"interactions:after-action-move":function(t){return t.interaction.modification.restoreInteractionCoords(t)},"interactions:stop":function(t){return t.interaction.modification.stop(t)}}},an=Zn,sn={base:{preventDefault:"auto",deltaSource:"page"},perAction:{enabled:!1,origin:{x:0,y:0}},actions:{}},gt=(function(t){w(n,t);var e=j(n);function n(r,o,i,a,s,c,p){var l;d(this,n),(l=e.call(this,r)).relatedTarget=null,l.screenX=void 0,l.screenY=void 0,l.button=void 0,l.buttons=void 0,l.ctrlKey=void 0,l.shiftKey=void 0,l.altKey=void 0,l.metaKey=void 0,l.page=void 0,l.client=void 0,l.delta=void 0,l.rect=void 0,l.x0=void 0,l.y0=void 0,l.t0=void 0,l.dt=void 0,l.duration=void 0,l.clientX0=void 0,l.clientY0=void 0,l.velocity=void 0,l.speed=void 0,l.swipe=void 0,l.axes=void 0,l.preEnd=void 0,s=s||r.element;var u=r.interactable,h=(u&&u.options||sn).deltaSource,b=be(u,s,i),y=a==="start",x=a==="end",_=y?C(l):r.prevEvent,k=y?r.coords.start:x?{page:_.page,client:_.client,timeStamp:r.coords.cur.timeStamp}:r.coords.cur;return l.page=S({},k.page),l.client=S({},k.client),l.rect=S({},r.rect),l.timeStamp=k.timeStamp,x||(l.page.x-=b.x,l.page.y-=b.y,l.client.x-=b.x,l.client.y-=b.y),l.ctrlKey=o.ctrlKey,l.altKey=o.altKey,l.shiftKey=o.shiftKey,l.metaKey=o.metaKey,l.button=o.button,l.buttons=o.buttons,l.target=s,l.currentTarget=s,l.preEnd=c,l.type=p||i+(a||""),l.interactable=u,l.t0=y?r.pointers[r.pointers.length-1].downTime:_.t0,l.x0=r.coords.start.page.x-b.x,l.y0=r.coords.start.page.y-b.y,l.clientX0=r.coords.start.client.x-b.x,l.clientY0=r.coords.start.client.y-b.y,l.delta=y||x?{x:0,y:0}:{x:l[h].x-_[h].x,y:l[h].y-_[h].y},l.dt=r.coords.delta.timeStamp,l.duration=l.timeStamp-l.t0,l.velocity=S({},r.coords.velocity[h]),l.speed=xe(l.velocity.x,l.velocity.y),l.swipe=x||a==="inertiastart"?l.getSwipe():null,l}return f(n,[{key:"getSwipe",value:function(){var r=this._interaction;if(r.prevEvent.speed<600||this.timeStamp-r.prevEvent.timeStamp>150)return null;var o=180*Math.atan2(r.prevEvent.velocityY,r.prevEvent.velocityX)/Math.PI;o<0&&(o+=360);var i=112.5<=o&&o<247.5,a=202.5<=o&&o<337.5;return{up:a,down:!a&&22.5<=o&&o<157.5,left:i,right:!i&&(292.5<=o||o<67.5),angle:o,speed:r.prevEvent.speed,velocity:{x:r.prevEvent.velocityX,y:r.prevEvent.velocityY}}}},{key:"preventDefault",value:function(){}},{key:"stopImmediatePropagation",value:function(){this.immediatePropagationStopped=this.propagationStopped=!0}},{key:"stopPropagation",value:function(){this.propagationStopped=!0}}]),n})(Fe);Object.defineProperties(gt.prototype,{pageX:{get:function(){return this.page.x},set:function(t){this.page.x=t}},pageY:{get:function(){return this.page.y},set:function(t){this.page.y=t}},clientX:{get:function(){return this.client.x},set:function(t){this.client.x=t}},clientY:{get:function(){return this.client.y},set:function(t){this.client.y=t}},dx:{get:function(){return this.delta.x},set:function(t){this.delta.x=t}},dy:{get:function(){return this.delta.y},set:function(t){this.delta.y=t}},velocityX:{get:function(){return this.velocity.x},set:function(t){this.velocity.x=t}},velocityY:{get:function(){return this.velocity.y},set:function(t){this.velocity.y=t}}});var Qn=f((function t(e,n,r,o,i){d(this,t),this.id=void 0,this.pointer=void 0,this.event=void 0,this.downTime=void 0,this.downTarget=void 0,this.id=e,this.pointer=n,this.event=r,this.downTime=o,this.downTarget=i})),Jn=(function(t){return t.interactable="",t.element="",t.prepared="",t.pointerIsDown="",t.pointerWasMoved="",t._proxy="",t})({}),cn=(function(t){return t.start="",t.move="",t.end="",t.stop="",t.interacting="",t})({}),er=0,tr=(function(){function t(e){var n=this,r=e.pointerType,o=e.scopeFire;d(this,t),this.interactable=null,this.element=null,this.rect=null,this._rects=void 0,this.edges=null,this._scopeFire=void 0,this.prepared={name:null,axis:null,edges:null},this.pointerType=void 0,this.pointers=[],this.downEvent=null,this.downPointer={},this._latestPointer={pointer:null,event:null,eventTarget:null},this.prevEvent=null,this.pointerIsDown=!1,this.pointerWasMoved=!1,this._interacting=!1,this._ending=!1,this._stopped=!0,this._proxy=void 0,this.simulation=null,this.doMove=Ee((function(l){this.move(l)}),"The interaction.doMove() method has been renamed to interaction.move()"),this.coords={start:{page:{x:0,y:0},client:{x:0,y:0},timeStamp:0},prev:{page:{x:0,y:0},client:{x:0,y:0},timeStamp:0},cur:{page:{x:0,y:0},client:{x:0,y:0},timeStamp:0},delta:{page:{x:0,y:0},client:{x:0,y:0},timeStamp:0},velocity:{page:{x:0,y:0},client:{x:0,y:0},timeStamp:0}},this._id=er++,this._scopeFire=o,this.pointerType=r;var i=this;this._proxy={};var a=function(l){Object.defineProperty(n._proxy,l,{get:function(){return i[l]}})};for(var s in Jn)a(s);var c=function(l){Object.defineProperty(n._proxy,l,{value:function(){return i[l].apply(i,arguments)}})};for(var p in cn)c(p);this._scopeFire("interactions:new",{interaction:this})}return f(t,[{key:"pointerMoveTolerance",get:function(){return 1}},{key:"pointerDown",value:function(e,n,r){var o=this.updatePointer(e,n,r,!0),i=this.pointers[o];this._scopeFire("interactions:down",{pointer:e,event:n,eventTarget:r,pointerIndex:o,pointerInfo:i,type:"down",interaction:this})}},{key:"start",value:function(e,n,r){return!(this.interacting()||!this.pointerIsDown||this.pointers.length<(e.name==="gesture"?2:1)||!n.options[e.name].enabled)&&(ut(this.prepared,e),this.interactable=n,this.element=r,this.rect=n.getRect(r),this.edges=this.prepared.edges?S({},this.prepared.edges):{left:!0,right:!0,top:!0,bottom:!0},this._stopped=!1,this._interacting=this._doPhase({interaction:this,event:this.downEvent,phase:"start"})&&!this._stopped,this._interacting)}},{key:"pointerMove",value:function(e,n,r){this.simulation||this.modification&&this.modification.endResult||this.updatePointer(e,n,r,!1);var o,i,a=this.coords.cur.page.x===this.coords.prev.page.x&&this.coords.cur.page.y===this.coords.prev.page.y&&this.coords.cur.client.x===this.coords.prev.client.x&&this.coords.cur.client.y===this.coords.prev.client.y;this.pointerIsDown&&!this.pointerWasMoved&&(o=this.coords.cur.client.x-this.coords.start.client.x,i=this.coords.cur.client.y-this.coords.start.client.y,this.pointerWasMoved=xe(o,i)>this.pointerMoveTolerance);var s,c,p,l=this.getPointerIndex(e),u={pointer:e,pointerIndex:l,pointerInfo:this.pointers[l],event:n,type:"move",eventTarget:r,dx:o,dy:i,duplicate:a,interaction:this};a||(s=this.coords.velocity,c=this.coords.delta,p=Math.max(c.timeStamp/1e3,.001),s.page.x=c.page.x/p,s.page.y=c.page.y/p,s.client.x=c.client.x/p,s.client.y=c.client.y/p,s.timeStamp=p),this._scopeFire("interactions:move",u),a||this.simulation||(this.interacting()&&(u.type=null,this.move(u)),this.pointerWasMoved&&Re(this.coords.prev,this.coords.cur))}},{key:"move",value:function(e){e&&e.event||jt(this.coords.delta),(e=S({pointer:this._latestPointer.pointer,event:this._latestPointer.event,eventTarget:this._latestPointer.eventTarget,interaction:this},e||{})).phase="move",this._doPhase(e)}},{key:"pointerUp",value:function(e,n,r,o){var i=this.getPointerIndex(e);i===-1&&(i=this.updatePointer(e,n,r,!1));var a=/cancel$/i.test(n.type)?"cancel":"up";this._scopeFire("interactions:".concat(a),{pointer:e,pointerIndex:i,pointerInfo:this.pointers[i],event:n,eventTarget:r,type:a,curEventTarget:o,interaction:this}),this.simulation||this.end(n),this.removePointer(e,n)}},{key:"documentBlur",value:function(e){this.end(e),this._scopeFire("interactions:blur",{event:e,type:"blur",interaction:this})}},{key:"end",value:function(e){var n;this._ending=!0,e=e||this._latestPointer.event,this.interacting()&&(n=this._doPhase({event:e,interaction:this,phase:"end"})),this._ending=!1,n===!0&&this.stop()}},{key:"currentAction",value:function(){return this._interacting?this.prepared.name:null}},{key:"interacting",value:function(){return this._interacting}},{key:"stop",value:function(){this._scopeFire("interactions:stop",{interaction:this}),this.interactable=this.element=null,this._interacting=!1,this._stopped=!0,this.prepared.name=this.prevEvent=null}},{key:"getPointerIndex",value:function(e){var n=we(e);return this.pointerType==="mouse"||this.pointerType==="pen"?this.pointers.length-1:ke(this.pointers,(function(r){return r.id===n}))}},{key:"getPointerInfo",value:function(e){return this.pointers[this.getPointerIndex(e)]}},{key:"updatePointer",value:function(e,n,r,o){var i,a,s,c=we(e),p=this.getPointerIndex(e),l=this.pointers[p];return o=o!==!1&&(o||/(down|start)$/i.test(n.type)),l?l.pointer=e:(l=new Qn(c,e,n,null,null),p=this.pointers.length,this.pointers.push(l)),Mn(this.coords.cur,this.pointers.map((function(u){return u.pointer})),this._now()),i=this.coords.delta,a=this.coords.prev,s=this.coords.cur,i.page.x=s.page.x-a.page.x,i.page.y=s.page.y-a.page.y,i.client.x=s.client.x-a.client.x,i.client.y=s.client.y-a.client.y,i.timeStamp=s.timeStamp-a.timeStamp,o&&(this.pointerIsDown=!0,l.downTime=this.coords.cur.timeStamp,l.downTarget=r,$e(this.downPointer,e),this.interacting()||(Re(this.coords.start,this.coords.cur),Re(this.coords.prev,this.coords.cur),this.downEvent=n,this.pointerWasMoved=!1)),this._updateLatestPointer(e,n,r),this._scopeFire("interactions:update-pointer",{pointer:e,event:n,eventTarget:r,down:o,pointerInfo:l,pointerIndex:p,interaction:this}),p}},{key:"removePointer",value:function(e,n){var r=this.getPointerIndex(e);if(r!==-1){var o=this.pointers[r];this._scopeFire("interactions:remove-pointer",{pointer:e,event:n,eventTarget:null,pointerIndex:r,pointerInfo:o,interaction:this}),this.pointers.splice(r,1),this.pointerIsDown=!1}}},{key:"_updateLatestPointer",value:function(e,n,r){this._latestPointer.pointer=e,this._latestPointer.event=n,this._latestPointer.eventTarget=r}},{key:"destroy",value:function(){this._latestPointer.pointer=null,this._latestPointer.event=null,this._latestPointer.eventTarget=null}},{key:"_createPreparedEvent",value:function(e,n,r,o){return new gt(this,e,this.prepared.name,n,this.element,r,o)}},{key:"_fireEvent",value:function(e){var n;(n=this.interactable)==null||n.fire(e),(!this.prevEvent||e.timeStamp>=this.prevEvent.timeStamp)&&(this.prevEvent=e)}},{key:"_doPhase",value:function(e){var n=e.event,r=e.phase,o=e.preEnd,i=e.type,a=this.rect;if(a&&r==="move"&&(Ce(this.edges,a,this.coords.delta[this.interactable.options.deltaSource]),a.width=a.right-a.left,a.height=a.bottom-a.top),this._scopeFire("interactions:before-action-".concat(r),e)===!1)return!1;var s=e.iEvent=this._createPreparedEvent(n,r,o,i);return this._scopeFire("interactions:action-".concat(r),e),r==="start"&&(this.prevEvent=s),this._fireEvent(s),this._scopeFire("interactions:after-action-".concat(r),e),!0}},{key:"_now",value:function(){return Date.now()}}]),t})();function ln(t){pn(t.interaction)}function pn(t){if(!(function(n){return!(!n.offset.pending.x&&!n.offset.pending.y)})(t))return!1;var e=t.offset.pending;return mt(t.coords.cur,e),mt(t.coords.delta,e),Ce(t.edges,t.rect,e),e.x=0,e.y=0,!0}function nr(t){var e=t.x,n=t.y;this.offset.pending.x+=e,this.offset.pending.y+=n,this.offset.total.x+=e,this.offset.total.y+=n}function mt(t,e){var n=t.page,r=t.client,o=e.x,i=e.y;n.x+=o,n.y+=i,r.x+=o,r.y+=i}cn.offsetBy="";var rr={id:"offset",before:["modifiers","pointer-events","actions","inertia"],install:function(t){t.Interaction.prototype.offsetBy=nr},listeners:{"interactions:new":function(t){t.interaction.offset={total:{x:0,y:0},pending:{x:0,y:0}}},"interactions:update-pointer":function(t){return(function(e){e.pointerIsDown&&(mt(e.coords.cur,e.offset.total),e.offset.pending.x=0,e.offset.pending.y=0)})(t.interaction)},"interactions:before-action-start":ln,"interactions:before-action-move":ln,"interactions:before-action-end":function(t){var e=t.interaction;if(pn(e))return e.move({offset:!0}),e.end(),!1},"interactions:stop":function(t){var e=t.interaction;e.offset.total.x=0,e.offset.total.y=0,e.offset.pending.x=0,e.offset.pending.y=0}}},un=rr,or=(function(){function t(e){d(this,t),this.active=!1,this.isModified=!1,this.smoothEnd=!1,this.allowResume=!1,this.modification=void 0,this.modifierCount=0,this.modifierArg=void 0,this.startCoords=void 0,this.t0=0,this.v0=0,this.te=0,this.targetOffset=void 0,this.modifiedOffset=void 0,this.currentOffset=void 0,this.lambda_v0=0,this.one_ve_v0=0,this.timeout=void 0,this.interaction=void 0,this.interaction=e}return f(t,[{key:"start",value:function(e){var n=this.interaction,r=Ye(n);if(!r||!r.enabled)return!1;var o=n.coords.velocity.client,i=xe(o.x,o.y),a=this.modification||(this.modification=new vt(n));if(a.copyFrom(n.modification),this.t0=n._now(),this.allowResume=r.allowResume,this.v0=i,this.currentOffset={x:0,y:0},this.startCoords=n.coords.cur.page,this.modifierArg=a.fillArg({pageCoords:this.startCoords,preEnd:!0,phase:"inertiastart"}),this.t0-n.coords.cur.timeStamp<50&&i>r.minSpeed&&i>r.endSpeed)this.startInertia();else{if(a.result=a.setAll(this.modifierArg),!a.result.changed)return!1;this.startSmoothEnd()}return n.modification.result.rect=null,n.offsetBy(this.targetOffset),n._doPhase({interaction:n,event:e,phase:"inertiastart"}),n.offsetBy({x:-this.targetOffset.x,y:-this.targetOffset.y}),n.modification.result.rect=null,this.active=!0,n.simulation=this,!0}},{key:"startInertia",value:function(){var e=this,n=this.interaction.coords.velocity.client,r=Ye(this.interaction),o=r.resistance,i=-Math.log(r.endSpeed/this.v0)/o;this.targetOffset={x:(n.x-i)/o,y:(n.y-i)/o},this.te=i,this.lambda_v0=o/this.v0,this.one_ve_v0=1-r.endSpeed/this.v0;var a=this.modification,s=this.modifierArg;s.pageCoords={x:this.startCoords.x+this.targetOffset.x,y:this.startCoords.y+this.targetOffset.y},a.result=a.setAll(s),a.result.changed&&(this.isModified=!0,this.modifiedOffset={x:this.targetOffset.x+a.result.delta.x,y:this.targetOffset.y+a.result.delta.y}),this.onNextFrame((function(){return e.inertiaTick()}))}},{key:"startSmoothEnd",value:function(){var e=this;this.smoothEnd=!0,this.isModified=!0,this.targetOffset={x:this.modification.result.delta.x,y:this.modification.result.delta.y},this.onNextFrame((function(){return e.smoothEndTick()}))}},{key:"onNextFrame",value:function(e){var n=this;this.timeout=oe.request((function(){n.active&&e()}))}},{key:"inertiaTick",value:function(){var e,n,r,o,i,a,s,c=this,p=this.interaction,l=Ye(p).resistance,u=(p._now()-this.t0)/1e3;if(u<this.te){var h,b=1-(Math.exp(-l*u)-this.lambda_v0)/this.one_ve_v0;this.isModified?(e=0,n=0,r=this.targetOffset.x,o=this.targetOffset.y,i=this.modifiedOffset.x,a=this.modifiedOffset.y,h={x:dn(s=b,e,r,i),y:dn(s,n,o,a)}):h={x:this.targetOffset.x*b,y:this.targetOffset.y*b};var y={x:h.x-this.currentOffset.x,y:h.y-this.currentOffset.y};this.currentOffset.x+=y.x,this.currentOffset.y+=y.y,p.offsetBy(y),p.move(),this.onNextFrame((function(){return c.inertiaTick()}))}else p.offsetBy({x:this.modifiedOffset.x-this.currentOffset.x,y:this.modifiedOffset.y-this.currentOffset.y}),this.end()}},{key:"smoothEndTick",value:function(){var e=this,n=this.interaction,r=n._now()-this.t0,o=Ye(n).smoothEndDuration;if(r<o){var i={x:hn(r,0,this.targetOffset.x,o),y:hn(r,0,this.targetOffset.y,o)},a={x:i.x-this.currentOffset.x,y:i.y-this.currentOffset.y};this.currentOffset.x+=a.x,this.currentOffset.y+=a.y,n.offsetBy(a),n.move({skipModifiers:this.modifierCount}),this.onNextFrame((function(){return e.smoothEndTick()}))}else n.offsetBy({x:this.targetOffset.x-this.currentOffset.x,y:this.targetOffset.y-this.currentOffset.y}),this.end()}},{key:"resume",value:function(e){var n=e.pointer,r=e.event,o=e.eventTarget,i=this.interaction;i.offsetBy({x:-this.currentOffset.x,y:-this.currentOffset.y}),i.updatePointer(n,r,o,!0),i._doPhase({interaction:i,event:r,phase:"resume"}),Re(i.coords.prev,i.coords.cur),this.stop()}},{key:"end",value:function(){this.interaction.move(),this.interaction.end(),this.stop()}},{key:"stop",value:function(){this.active=this.smoothEnd=!1,this.interaction.simulation=null,oe.cancel(this.timeout)}}]),t})();function Ye(t){var e=t.interactable,n=t.prepared;return e&&e.options&&n.name&&e.options[n.name].inertia}var ir={id:"inertia",before:["modifiers","actions"],install:function(t){var e=t.defaults;t.usePlugin(un),t.usePlugin(an),t.actions.phases.inertiastart=!0,t.actions.phases.resume=!0,e.perAction.inertia={enabled:!1,resistance:10,minSpeed:100,endSpeed:10,allowResume:!0,smoothEndDuration:300}},listeners:{"interactions:new":function(t){var e=t.interaction;e.inertia=new or(e)},"interactions:before-action-end":function(t){var e=t.interaction,n=t.event;return(!e._interacting||e.simulation||!e.inertia.start(n))&&null},"interactions:down":function(t){var e=t.interaction,n=t.eventTarget,r=e.inertia;if(r.active)for(var o=n;g.element(o);){if(o===e.element){r.resume(t);break}o=J(o)}},"interactions:stop":function(t){var e=t.interaction.inertia;e.active&&e.stop()},"interactions:before-action-resume":function(t){var e=t.interaction.modification;e.stop(t),e.start(t,t.interaction.coords.cur.page),e.applyToInteraction(t)},"interactions:before-action-inertiastart":function(t){return t.interaction.modification.setAndApply(t)},"interactions:action-resume":Te,"interactions:action-inertiastart":Te,"interactions:after-action-inertiastart":function(t){return t.interaction.modification.restoreInteractionCoords(t)},"interactions:after-action-resume":function(t){return t.interaction.modification.restoreInteractionCoords(t)}}};function dn(t,e,n,r){var o=1-t;return o*o*e+2*o*t*n+t*t*r}function hn(t,e,n,r){return-n*(t/=r)*(t-2)+e}var ar=ir;function fn(t,e){for(var n=0;n<e.length;n++){var r=e[n];if(t.immediatePropagationStopped)break;r(t)}}var vn=(function(){function t(e){d(this,t),this.options=void 0,this.types={},this.propagationStopped=!1,this.immediatePropagationStopped=!1,this.global=void 0,this.options=S({},e||{})}return f(t,[{key:"fire",value:function(e){var n,r=this.global;(n=this.types[e.type])&&fn(e,n),!e.propagationStopped&&r&&(n=r[e.type])&&fn(e,n)}},{key:"on",value:function(e,n){var r=pe(e,n);for(e in r)this.types[e]=qt(this.types[e]||[],r[e])}},{key:"off",value:function(e,n){var r=pe(e,n);for(e in r){var o=this.types[e];if(o&&o.length)for(var i=0,a=r[e];i<a.length;i++){var s=a[i],c=o.indexOf(s);c!==-1&&o.splice(c,1)}}}},{key:"getRect",value:function(e){return null}}]),t})(),sr=(function(){function t(e){d(this,t),this.currentTarget=void 0,this.originalEvent=void 0,this.type=void 0,this.originalEvent=e,$e(this,e)}return f(t,[{key:"preventOriginalDefault",value:function(){this.originalEvent.preventDefault()}},{key:"stopPropagation",value:function(){this.originalEvent.stopPropagation()}},{key:"stopImmediatePropagation",value:function(){this.originalEvent.stopImmediatePropagation()}}]),t})();function Se(t){return g.object(t)?{capture:!!t.capture,passive:!!t.passive}:{capture:!!t,passive:!1}}function qe(t,e){return t===e||(typeof t=="boolean"?!!e.capture===t&&!e.passive:!!t.capture==!!e.capture&&!!t.passive==!!e.passive)}var cr={id:"events",install:function(t){var e,n=[],r={},o=[],i={add:a,remove:s,addDelegate:function(l,u,h,b,y){var x=Se(y);if(!r[h]){r[h]=[];for(var _=0;_<o.length;_++){var k=o[_];a(k,h,c),a(k,h,p,!0)}}var T=r[h],I=_e(T,(function(M){return M.selector===l&&M.context===u}));I||(I={selector:l,context:u,listeners:[]},T.push(I)),I.listeners.push({func:b,options:x})},removeDelegate:function(l,u,h,b,y){var x,_=Se(y),k=r[h],T=!1;if(k)for(x=k.length-1;x>=0;x--){var I=k[x];if(I.selector===l&&I.context===u){for(var M=I.listeners,P=M.length-1;P>=0;P--){var O=M[P];if(O.func===b&&qe(O.options,_)){M.splice(P,1),M.length||(k.splice(x,1),s(u,h,c),s(u,h,p,!0)),T=!0;break}}if(T)break}}},delegateListener:c,delegateUseCapture:p,delegatedEvents:r,documents:o,targets:n,supportsOptions:!1,supportsPassive:!1};function a(l,u,h,b){if(l.addEventListener){var y=Se(b),x=_e(n,(function(_){return _.eventTarget===l}));x||(x={eventTarget:l,events:{}},n.push(x)),x.events[u]||(x.events[u]=[]),_e(x.events[u],(function(_){return _.func===h&&qe(_.options,y)}))||(l.addEventListener(u,h,i.supportsOptions?y:y.capture),x.events[u].push({func:h,options:y}))}}function s(l,u,h,b){if(l.addEventListener&&l.removeEventListener){var y=ke(n,(function(Y){return Y.eventTarget===l})),x=n[y];if(x&&x.events)if(u!=="all"){var _=!1,k=x.events[u];if(k){if(h==="all"){for(var T=k.length-1;T>=0;T--){var I=k[T];s(l,u,I.func,I.options)}return}for(var M=Se(b),P=0;P<k.length;P++){var O=k[P];if(O.func===h&&qe(O.options,M)){l.removeEventListener(u,h,i.supportsOptions?M:M.capture),k.splice(P,1),k.length===0&&(delete x.events[u],_=!0);break}}}_&&!Object.keys(x.events).length&&n.splice(y,1)}else for(u in x.events)x.events.hasOwnProperty(u)&&s(l,u,"all")}}function c(l,u){for(var h=Se(u),b=new sr(l),y=r[l.type],x=Yt(l)[0],_=x;g.element(_);){for(var k=0;k<y.length;k++){var T=y[k],I=T.selector,M=T.context;if(re(_,I)&&le(M,x)&&le(M,_)){var P=T.listeners;b.currentTarget=_;for(var O=0;O<P.length;O++){var Y=P[O];qe(Y.options,h)&&Y.func(b)}}}_=J(_)}}function p(l){return c(l,!0)}return(e=t.document)==null||e.createElement("div").addEventListener("test",null,{get capture(){return i.supportsOptions=!0},get passive(){return i.supportsPassive=!0}}),t.events=i,i}},yt={methodOrder:["simulationResume","mouseOrPen","hasPointer","idle"],search:function(t){for(var e=0,n=yt.methodOrder;e<n.length;e++){var r=n[e],o=yt[r](t);if(o)return o}return null},simulationResume:function(t){var e=t.pointerType,n=t.eventType,r=t.eventTarget,o=t.scope;if(!/down|start/i.test(n))return null;for(var i=0,a=o.interactions.list;i<a.length;i++){var s=a[i],c=r;if(s.simulation&&s.simulation.allowResume&&s.pointerType===e)for(;c;){if(c===s.element)return s;c=J(c)}}return null},mouseOrPen:function(t){var e,n=t.pointerId,r=t.pointerType,o=t.eventType,i=t.scope;if(r!=="mouse"&&r!=="pen")return null;for(var a=0,s=i.interactions.list;a<s.length;a++){var c=s[a];if(c.pointerType===r){if(c.simulation&&!gn(c,n))continue;if(c.interacting())return c;e||(e=c)}}if(e)return e;for(var p=0,l=i.interactions.list;p<l.length;p++){var u=l[p];if(!(u.pointerType!==r||/down/i.test(o)&&u.simulation))return u}return null},hasPointer:function(t){for(var e=t.pointerId,n=0,r=t.scope.interactions.list;n<r.length;n++){var o=r[n];if(gn(o,e))return o}return null},idle:function(t){for(var e=t.pointerType,n=0,r=t.scope.interactions.list;n<r.length;n++){var o=r[n];if(o.pointers.length===1){var i=o.interactable;if(i&&(!i.options.gesture||!i.options.gesture.enabled))continue}else if(o.pointers.length>=2)continue;if(!o.interacting()&&e===o.pointerType)return o}return null}};function gn(t,e){return t.pointers.some((function(n){return n.id===e}))}var lr=yt,bt=["pointerDown","pointerMove","pointerUp","updatePointer","removePointer","windowBlur"];function mn(t,e){return function(n){var r=e.interactions.list,o=Xt(n),i=Yt(n),a=i[0],s=i[1],c=[];if(/^touch/.test(n.type)){e.prevTouchTime=e.now();for(var p=0,l=n.changedTouches;p<l.length;p++){var u=l[p],h={pointer:u,pointerId:we(u),pointerType:o,eventType:n.type,eventTarget:a,curEventTarget:s,scope:e},b=yn(h);c.push([h.pointer,h.eventTarget,h.curEventTarget,b])}}else{var y=!1;if(!G.supportsPointerEvent&&/mouse/.test(n.type)){for(var x=0;x<r.length&&!y;x++)y=r[x].pointerType!=="mouse"&&r[x].pointerIsDown;y=y||e.now()-e.prevTouchTime<500||n.timeStamp===0}if(!y){var _={pointer:n,pointerId:we(n),pointerType:o,eventType:n.type,curEventTarget:s,eventTarget:a,scope:e},k=yn(_);c.push([_.pointer,_.eventTarget,_.curEventTarget,k])}}for(var T=0;T<c.length;T++){var I=c[T],M=I[0],P=I[1],O=I[2];I[3][t](M,n,P,O)}}}function yn(t){var e=t.pointerType,n=t.scope,r={interaction:lr.search(t),searchDetails:t};return n.fire("interactions:find",r),r.interaction||n.interactions.new({pointerType:e})}function xt(t,e){var n=t.doc,r=t.scope,o=t.options,i=r.interactions.docEvents,a=r.events,s=a[e];for(var c in r.browser.isIOS&&!o.events&&(o.events={passive:!1}),a.delegatedEvents)s(n,c,a.delegateListener),s(n,c,a.delegateUseCapture,!0);for(var p=o&&o.events,l=0;l<i.length;l++){var u=i[l];s(n,u.type,u.listener,p)}}var pr={id:"core/interactions",install:function(t){for(var e={},n=0;n<bt.length;n++){var r=bt[n];e[r]=mn(r,t)}var o,i=G.pEventTypes;function a(){for(var s=0,c=t.interactions.list;s<c.length;s++){var p=c[s];if(p.pointerIsDown&&p.pointerType==="touch"&&!p._interacting)for(var l=function(){var b=h[u];t.documents.some((function(y){return le(y.doc,b.downTarget)}))||p.removePointer(b.pointer,b.event)},u=0,h=p.pointers;u<h.length;u++)l()}}(o=N.PointerEvent?[{type:i.down,listener:a},{type:i.down,listener:e.pointerDown},{type:i.move,listener:e.pointerMove},{type:i.up,listener:e.pointerUp},{type:i.cancel,listener:e.pointerUp}]:[{type:"mousedown",listener:e.pointerDown},{type:"mousemove",listener:e.pointerMove},{type:"mouseup",listener:e.pointerUp},{type:"touchstart",listener:a},{type:"touchstart",listener:e.pointerDown},{type:"touchmove",listener:e.pointerMove},{type:"touchend",listener:e.pointerUp},{type:"touchcancel",listener:e.pointerUp}]).push({type:"blur",listener:function(s){for(var c=0,p=t.interactions.list;c<p.length;c++)p[c].documentBlur(s)}}),t.prevTouchTime=0,t.Interaction=(function(s){w(p,s);var c=j(p);function p(){return d(this,p),c.apply(this,arguments)}return f(p,[{key:"pointerMoveTolerance",get:function(){return t.interactions.pointerMoveTolerance},set:function(l){t.interactions.pointerMoveTolerance=l}},{key:"_now",value:function(){return t.now()}}]),p})(tr),t.interactions={list:[],new:function(s){s.scopeFire=function(p,l){return t.fire(p,l)};var c=new t.Interaction(s);return t.interactions.list.push(c),c},listeners:e,docEvents:o,pointerMoveTolerance:1},t.usePlugin(on)},listeners:{"scope:add-document":function(t){return xt(t,"add")},"scope:remove-document":function(t){return xt(t,"remove")},"interactable:unset":function(t,e){for(var n=t.interactable,r=e.interactions.list.length-1;r>=0;r--){var o=e.interactions.list[r];o.interactable===n&&(o.stop(),e.fire("interactions:destroy",{interaction:o}),o.destroy(),e.interactions.list.length>2&&e.interactions.list.splice(r,1))}}},onDocSignal:xt,doOnInteractions:mn,methodNames:bt},ur=pr,ae=(function(t){return t[t.On=0]="On",t[t.Off=1]="Off",t})(ae||{}),dr=(function(){function t(e,n,r,o){d(this,t),this.target=void 0,this.options=void 0,this._actions=void 0,this.events=new vn,this._context=void 0,this._win=void 0,this._doc=void 0,this._scopeEvents=void 0,this._actions=n.actions,this.target=e,this._context=n.context||r,this._win=Q(Ct(e)?this._context:e),this._doc=this._win.document,this._scopeEvents=o,this.set(n)}return f(t,[{key:"_defaults",get:function(){return{base:{},perAction:{},actions:{}}}},{key:"setOnEvents",value:function(e,n){return g.func(n.onstart)&&this.on("".concat(e,"start"),n.onstart),g.func(n.onmove)&&this.on("".concat(e,"move"),n.onmove),g.func(n.onend)&&this.on("".concat(e,"end"),n.onend),g.func(n.oninertiastart)&&this.on("".concat(e,"inertiastart"),n.oninertiastart),this}},{key:"updatePerActionListeners",value:function(e,n,r){var o,i=this,a=(o=this._actions.map[e])==null?void 0:o.filterEventType,s=function(c){return(a==null||a(c))&&Ne(c,i._actions)};(g.array(n)||g.object(n))&&this._onOff(ae.Off,e,n,void 0,s),(g.array(r)||g.object(r))&&this._onOff(ae.On,e,r,void 0,s)}},{key:"setPerAction",value:function(e,n){var r=this._defaults;for(var o in n){var i=o,a=this.options[e],s=n[i];i==="listeners"&&this.updatePerActionListeners(e,a.listeners,s),g.array(s)?a[i]=Bt(s):g.plainObject(s)?(a[i]=S(a[i]||{},ge(s)),g.object(r.perAction[i])&&"enabled"in r.perAction[i]&&(a[i].enabled=s.enabled!==!1)):g.bool(s)&&g.object(r.perAction[i])?a[i].enabled=s:a[i]=s}}},{key:"getRect",value:function(e){return e=e||(g.element(this.target)?this.target:null),g.string(this.target)&&(e=e||this._context.querySelector(this.target)),et(e)}},{key:"rectChecker",value:function(e){var n=this;return g.func(e)?(this.getRect=function(r){var o=S({},e.apply(n,r));return"width"in o||(o.width=o.right-o.left,o.height=o.bottom-o.top),o},this):e===null?(delete this.getRect,this):this.getRect}},{key:"_backCompatOption",value:function(e,n){if(Ct(n)||g.object(n)){for(var r in this.options[e]=n,this._actions.map)this.options[r][e]=n;return this}return this.options[e]}},{key:"origin",value:function(e){return this._backCompatOption("origin",e)}},{key:"deltaSource",value:function(e){return e==="page"||e==="client"?(this.options.deltaSource=e,this):this.options.deltaSource}},{key:"getAllElements",value:function(){var e=this.target;return g.string(e)?Array.from(this._context.querySelectorAll(e)):g.func(e)&&e.getAllElements?e.getAllElements():g.element(e)?[e]:[]}},{key:"context",value:function(){return this._context}},{key:"inContext",value:function(e){return this._context===e.ownerDocument||le(this._context,e)}},{key:"testIgnoreAllow",value:function(e,n,r){return!this.testIgnore(e.ignoreFrom,n,r)&&this.testAllow(e.allowFrom,n,r)}},{key:"testAllow",value:function(e,n,r){return!e||!!g.element(r)&&(g.string(e)?Qe(r,e,n):!!g.element(e)&&le(e,r))}},{key:"testIgnore",value:function(e,n,r){return!(!e||!g.element(r))&&(g.string(e)?Qe(r,e,n):!!g.element(e)&&le(e,r))}},{key:"fire",value:function(e){return this.events.fire(e),this}},{key:"_onOff",value:function(e,n,r,o,i){g.object(n)&&!g.array(n)&&(o=r,r=null);var a=pe(n,r,i);for(var s in a){s==="wheel"&&(s=G.wheelEvent);for(var c=0,p=a[s];c<p.length;c++){var l=p[c];Ne(s,this._actions)?this.events[e===ae.On?"on":"off"](s,l):g.string(this.target)?this._scopeEvents[e===ae.On?"addDelegate":"removeDelegate"](this.target,this._context,s,l,o):this._scopeEvents[e===ae.On?"add":"remove"](this.target,s,l,o)}}return this}},{key:"on",value:function(e,n,r){return this._onOff(ae.On,e,n,r)}},{key:"off",value:function(e,n,r){return this._onOff(ae.Off,e,n,r)}},{key:"set",value:function(e){var n=this._defaults;for(var r in g.object(e)||(e={}),this.options=ge(n.base),this._actions.methodDict){var o=r,i=this._actions.methodDict[o];this.options[o]={},this.setPerAction(o,S(S({},n.perAction),n.actions[o])),this[i](e[o])}for(var a in e)a!=="getRect"?g.func(this[a])&&this[a](e[a]):this.rectChecker(e.getRect);return this}},{key:"unset",value:function(){if(g.string(this.target))for(var e in this._scopeEvents.delegatedEvents)for(var n=this._scopeEvents.delegatedEvents[e],r=n.length-1;r>=0;r--){var o=n[r],i=o.selector,a=o.context,s=o.listeners;i===this.target&&a===this._context&&n.splice(r,1);for(var c=s.length-1;c>=0;c--)this._scopeEvents.removeDelegate(this.target,this._context,e,s[c][0],s[c][1])}else this._scopeEvents.remove(this.target,"all")}}]),t})(),hr=(function(){function t(e){var n=this;d(this,t),this.list=[],this.selectorMap={},this.scope=void 0,this.scope=e,e.addListeners({"interactable:unset":function(r){var o=r.interactable,i=o.target,a=g.string(i)?n.selectorMap[i]:i[n.scope.id],s=ke(a,(function(c){return c===o}));a.splice(s,1)}})}return f(t,[{key:"new",value:function(e,n){n=S(n||{},{actions:this.scope.actions});var r=new this.scope.Interactable(e,n,this.scope.document,this.scope.events);return this.scope.addDocument(r._doc),this.list.push(r),g.string(e)?(this.selectorMap[e]||(this.selectorMap[e]=[]),this.selectorMap[e].push(r)):(r.target[this.scope.id]||Object.defineProperty(e,this.scope.id,{value:[],configurable:!0}),e[this.scope.id].push(r)),this.scope.fire("interactable:new",{target:e,options:n,interactable:r,win:this.scope._win}),r}},{key:"getExisting",value:function(e,n){var r=n&&n.context||this.scope.document,o=g.string(e),i=o?this.selectorMap[e]:e[this.scope.id];if(i)return _e(i,(function(a){return a._context===r&&(o||a.inContext(e))}))}},{key:"forEachMatch",value:function(e,n){for(var r=0,o=this.list;r<o.length;r++){var i=o[r],a=void 0;if((g.string(i.target)?g.element(e)&&re(e,i.target):e===i.target)&&i.inContext(e)&&(a=n(i)),a!==void 0)return a}}}]),t})(),fr=(function(){function t(){var e=this;d(this,t),this.id="__interact_scope_".concat(Math.floor(100*Math.random())),this.isInitialized=!1,this.listenerMaps=[],this.browser=G,this.defaults=ge(sn),this.Eventable=vn,this.actions={map:{},phases:{start:!0,move:!0,end:!0},methodDict:{},phaselessTypes:{}},this.interactStatic=(function(r){var o=function i(a,s){var c=r.interactables.getExisting(a,s);return c||((c=r.interactables.new(a,s)).events.global=i.globalEvents),c};return o.getPointerAverage=Nt,o.getTouchBBox=rt,o.getTouchDistance=ot,o.getTouchAngle=it,o.getElementRect=et,o.getElementClientRect=Je,o.matchesSelector=re,o.closest=Ot,o.globalEvents={},o.version="1.10.27",o.scope=r,o.use=function(i,a){return this.scope.usePlugin(i,a),this},o.isSet=function(i,a){return!!this.scope.interactables.get(i,a&&a.context)},o.on=Ee((function(i,a,s){if(g.string(i)&&i.search(" ")!==-1&&(i=i.trim().split(/ +/)),g.array(i)){for(var c=0,p=i;c<p.length;c++){var l=p[c];this.on(l,a,s)}return this}if(g.object(i)){for(var u in i)this.on(u,i[u],a);return this}return Ne(i,this.scope.actions)?this.globalEvents[i]?this.globalEvents[i].push(a):this.globalEvents[i]=[a]:this.scope.events.add(this.scope.document,i,a,{options:s}),this}),"The interact.on() method is being deprecated"),o.off=Ee((function(i,a,s){if(g.string(i)&&i.search(" ")!==-1&&(i=i.trim().split(/ +/)),g.array(i)){for(var c=0,p=i;c<p.length;c++){var l=p[c];this.off(l,a,s)}return this}if(g.object(i)){for(var u in i)this.off(u,i[u],a);return this}var h;return Ne(i,this.scope.actions)?i in this.globalEvents&&(h=this.globalEvents[i].indexOf(a))!==-1&&this.globalEvents[i].splice(h,1):this.scope.events.remove(this.scope.document,i,a,s),this}),"The interact.off() method is being deprecated"),o.debug=function(){return this.scope},o.supportsTouch=function(){return G.supportsTouch},o.supportsPointerEvent=function(){return G.supportsPointerEvent},o.stop=function(){for(var i=0,a=this.scope.interactions.list;i<a.length;i++)a[i].stop();return this},o.pointerMoveTolerance=function(i){return g.number(i)?(this.scope.interactions.pointerMoveTolerance=i,this):this.scope.interactions.pointerMoveTolerance},o.addDocument=function(i,a){this.scope.addDocument(i,a)},o.removeDocument=function(i){this.scope.removeDocument(i)},o})(this),this.InteractEvent=gt,this.Interactable=void 0,this.interactables=new hr(this),this._win=void 0,this.document=void 0,this.window=void 0,this.documents=[],this._plugins={list:[],map:{}},this.onWindowUnload=function(r){return e.removeDocument(r.target)};var n=this;this.Interactable=(function(r){w(i,r);var o=j(i);function i(){return d(this,i),o.apply(this,arguments)}return f(i,[{key:"_defaults",get:function(){return n.defaults}},{key:"set",value:function(a){return q(D(i.prototype),"set",this).call(this,a),n.fire("interactable:set",{options:a,interactable:this}),this}},{key:"unset",value:function(){q(D(i.prototype),"unset",this).call(this);var a=n.interactables.list.indexOf(this);a<0||(n.interactables.list.splice(a,1),n.fire("interactable:unset",{interactable:this}))}}]),i})(dr)}return f(t,[{key:"addListeners",value:function(e,n){this.listenerMaps.push({id:n,map:e})}},{key:"fire",value:function(e,n){for(var r=0,o=this.listenerMaps;r<o.length;r++){var i=o[r].map[e];if(i&&i(n,this,e)===!1)return!1}}},{key:"init",value:function(e){return this.isInitialized?this:(function(n,r){return n.isInitialized=!0,g.window(r)&&me(r),N.init(r),G.init(r),oe.init(r),n.window=r,n.document=r.document,n.usePlugin(ur),n.usePlugin(cr),n})(this,e)}},{key:"pluginIsInstalled",value:function(e){var n=e.id;return n?!!this._plugins.map[n]:this._plugins.list.indexOf(e)!==-1}},{key:"usePlugin",value:function(e,n){if(!this.isInitialized)return this;if(this.pluginIsInstalled(e))return this;if(e.id&&(this._plugins.map[e.id]=e),this._plugins.list.push(e),e.install&&e.install(this,n),e.listeners&&e.before){for(var r=0,o=this.listenerMaps.length,i=e.before.reduce((function(s,c){return s[c]=!0,s[bn(c)]=!0,s}),{});r<o;r++){var a=this.listenerMaps[r].id;if(a&&(i[a]||i[bn(a)]))break}this.listenerMaps.splice(r,0,{id:e.id,map:e.listeners})}else e.listeners&&this.listenerMaps.push({id:e.id,map:e.listeners});return this}},{key:"addDocument",value:function(e,n){if(this.getDocIndex(e)!==-1)return!1;var r=Q(e);n=n?S({},n):{},this.documents.push({doc:e,options:n}),this.events.documents.push(e),e!==this.document&&this.events.add(r,"unload",this.onWindowUnload),this.fire("scope:add-document",{doc:e,window:r,scope:this,options:n})}},{key:"removeDocument",value:function(e){var n=this.getDocIndex(e),r=Q(e),o=this.documents[n].options;this.events.remove(r,"unload",this.onWindowUnload),this.documents.splice(n,1),this.events.documents.splice(n,1),this.fire("scope:remove-document",{doc:e,window:r,scope:this,options:o})}},{key:"getDocIndex",value:function(e){for(var n=0;n<this.documents.length;n++)if(this.documents[n].doc===e)return n;return-1}},{key:"getDocOptions",value:function(e){var n=this.getDocIndex(e);return n===-1?null:this.documents[n].options}},{key:"now",value:function(){return(this.window.Date||Date).now()}}]),t})();function bn(t){return t&&t.replace(/\/.*$/,"")}var xn=new fr,X=xn.interactStatic,vr=typeof globalThis<"u"?globalThis:window;xn.init(vr);var gr=Object.freeze({__proto__:null,edgeTarget:function(){},elements:function(){},grid:function(t){var e=[["x","y"],["left","top"],["right","bottom"],["width","height"]].filter((function(r){var o=r[0],i=r[1];return o in t||i in t})),n=function(r,o){for(var i=t.range,a=t.limits,s=a===void 0?{left:-1/0,right:1/0,top:-1/0,bottom:1/0}:a,c=t.offset,p=c===void 0?{x:0,y:0}:c,l={range:i,grid:t,x:null,y:null},u=0;u<e.length;u++){var h=e[u],b=h[0],y=h[1],x=Math.round((r-p.x)/t[b]),_=Math.round((o-p.y)/t[y]);l[b]=Math.max(s.left,Math.min(s.right,x*t[b]+p.x)),l[y]=Math.max(s.top,Math.min(s.bottom,_*t[y]+p.y))}return l};return n.grid=t,n.coordFields=e,n}}),mr={id:"snappers",install:function(t){var e=t.interactStatic;e.snappers=S(e.snappers||{},gr),e.createSnapGrid=e.snappers.grid}},yr=mr,br={start:function(t){var e=t.state,n=t.rect,r=t.edges,o=t.pageCoords,i=e.options,a=i.ratio,s=i.enabled,c=e.options,p=c.equalDelta,l=c.modifiers;a==="preserve"&&(a=n.width/n.height),e.startCoords=S({},o),e.startRect=S({},n),e.ratio=a,e.equalDelta=p;var u=e.linkedEdges={top:r.top||r.left&&!r.bottom,left:r.left||r.top&&!r.right,bottom:r.bottom||r.right&&!r.top,right:r.right||r.bottom&&!r.left};if(e.xIsPrimaryAxis=!(!r.left&&!r.right),e.equalDelta){var h=(u.left?1:-1)*(u.top?1:-1);e.edgeSign={x:h,y:h}}else e.edgeSign={x:u.left?-1:1,y:u.top?-1:1};if(s!==!1&&S(r,u),l!=null&&l.length){var b=new vt(t.interaction);b.copyFrom(t.interaction.modification),b.prepareStates(l),e.subModification=b,b.startAll($({},t))}},set:function(t){var e=t.state,n=t.rect,r=t.coords,o=e.linkedEdges,i=S({},r),a=e.equalDelta?xr:wr;if(S(t.edges,o),a(e,e.xIsPrimaryAxis,r,n),!e.subModification)return null;var s=S({},n);Ce(o,s,{x:r.x-i.x,y:r.y-i.y});var c=e.subModification.setAll($($({},t),{},{rect:s,edges:o,pageCoords:r,prevCoords:r,prevRect:s})),p=c.delta;return c.changed&&(a(e,Math.abs(p.x)>Math.abs(p.y),c.coords,c.rect),S(r,c.coords)),c.eventProps},defaults:{ratio:"preserve",equalDelta:!1,modifiers:[],enabled:!1}};function xr(t,e,n){var r=t.startCoords,o=t.edgeSign;e?n.y=r.y+(n.x-r.x)*o.y:n.x=r.x+(n.y-r.y)*o.x}function wr(t,e,n,r){var o=t.startRect,i=t.startCoords,a=t.ratio,s=t.edgeSign;if(e){var c=r.width/a;n.y=i.y+(c-o.height)*s.y}else{var p=r.height*a;n.x=i.x+(p-o.width)*s.x}}var kr=ie(br,"aspectRatio"),wn=function(){};wn._defaults={};var Be=wn;function de(t,e,n){return g.func(t)?ye(t,e.interactable,e.element,[n.x,n.y,e]):ye(t,e.interactable,e.element)}var He={start:function(t){var e=t.rect,n=t.startOffset,r=t.state,o=t.interaction,i=t.pageCoords,a=r.options,s=a.elementRect,c=S({left:0,top:0,right:0,bottom:0},a.offset||{});if(e&&s){var p=de(a.restriction,o,i);if(p){var l=p.right-p.left-e.width,u=p.bottom-p.top-e.height;l<0&&(c.left+=l,c.right+=l),u<0&&(c.top+=u,c.bottom+=u)}c.left+=n.left-e.width*s.left,c.top+=n.top-e.height*s.top,c.right+=n.right-e.width*(1-s.right),c.bottom+=n.bottom-e.height*(1-s.bottom)}r.offset=c},set:function(t){var e=t.coords,n=t.interaction,r=t.state,o=r.options,i=r.offset,a=de(o.restriction,n,e);if(a){var s=(function(c){return!c||"left"in c&&"top"in c||((c=S({},c)).left=c.x||0,c.top=c.y||0,c.right=c.right||c.left+c.width,c.bottom=c.bottom||c.top+c.height),c})(a);e.x=Math.max(Math.min(s.right-i.right,e.x),s.left+i.left),e.y=Math.max(Math.min(s.bottom-i.bottom,e.y),s.top+i.top)}},defaults:{restriction:null,elementRect:null,offset:null,endOnly:!1,enabled:!1}},_r=ie(He,"restrict"),kn={top:1/0,left:1/0,bottom:-1/0,right:-1/0},_n={top:-1/0,left:-1/0,bottom:1/0,right:1/0};function En(t,e){for(var n=0,r=["top","left","bottom","right"];n<r.length;n++){var o=r[n];o in t||(t[o]=e[o])}return t}var Pe={noInner:kn,noOuter:_n,start:function(t){var e,n=t.interaction,r=t.startOffset,o=t.state,i=o.options;i&&(e=Ae(de(i.offset,n,n.coords.start.page))),e=e||{x:0,y:0},o.offset={top:e.y+r.top,left:e.x+r.left,bottom:e.y-r.bottom,right:e.x-r.right}},set:function(t){var e=t.coords,n=t.edges,r=t.interaction,o=t.state,i=o.offset,a=o.options;if(n){var s=S({},e),c=de(a.inner,r,s)||{},p=de(a.outer,r,s)||{};En(c,kn),En(p,_n),n.top?e.y=Math.min(Math.max(p.top+i.top,s.y),c.top+i.top):n.bottom&&(e.y=Math.max(Math.min(p.bottom+i.bottom,s.y),c.bottom+i.bottom)),n.left?e.x=Math.min(Math.max(p.left+i.left,s.x),c.left+i.left):n.right&&(e.x=Math.max(Math.min(p.right+i.right,s.x),c.right+i.right))}},defaults:{inner:null,outer:null,offset:null,endOnly:!1,enabled:!1}},Er=ie(Pe,"restrictEdges"),Tr=S({get elementRect(){return{top:0,left:0,bottom:1,right:1}},set elementRect(t){}},He.defaults),Sr=ie({start:He.start,set:He.set,defaults:Tr},"restrictRect"),Pr={width:-1/0,height:-1/0},zr={width:1/0,height:1/0},Ir=ie({start:function(t){return Pe.start(t)},set:function(t){var e=t.interaction,n=t.state,r=t.rect,o=t.edges,i=n.options;if(o){var a=tt(de(i.min,e,t.coords))||Pr,s=tt(de(i.max,e,t.coords))||zr;n.options={endOnly:i.endOnly,inner:S({},Pe.noInner),outer:S({},Pe.noOuter)},o.top?(n.options.inner.top=r.bottom-a.height,n.options.outer.top=r.bottom-s.height):o.bottom&&(n.options.inner.bottom=r.top+a.height,n.options.outer.bottom=r.top+s.height),o.left?(n.options.inner.left=r.right-a.width,n.options.outer.left=r.right-s.width):o.right&&(n.options.inner.right=r.left+a.width,n.options.outer.right=r.left+s.width),Pe.set(t),n.options=i}},defaults:{min:null,max:null,endOnly:!1,enabled:!1}},"restrictSize"),wt={start:function(t){var e,n=t.interaction,r=t.interactable,o=t.element,i=t.rect,a=t.state,s=t.startOffset,c=a.options,p=c.offsetWithOrigin?(function(h){var b=h.interaction.element,y=Ae(ye(h.state.options.origin,null,null,[b])),x=y||be(h.interactable,b,h.interaction.prepared.name);return x})(t):{x:0,y:0};if(c.offset==="startCoords")e={x:n.coords.start.page.x,y:n.coords.start.page.y};else{var l=ye(c.offset,r,o,[n]);(e=Ae(l)||{x:0,y:0}).x+=p.x,e.y+=p.y}var u=c.relativePoints;a.offsets=i&&u&&u.length?u.map((function(h,b){return{index:b,relativePoint:h,x:s.left-i.width*h.x+e.x,y:s.top-i.height*h.y+e.y}})):[{index:0,relativePoint:null,x:e.x,y:e.y}]},set:function(t){var e=t.interaction,n=t.coords,r=t.state,o=r.options,i=r.offsets,a=be(e.interactable,e.element,e.prepared.name),s=S({},n),c=[];o.offsetWithOrigin||(s.x-=a.x,s.y-=a.y);for(var p=0,l=i;p<l.length;p++)for(var u=l[p],h=s.x-u.x,b=s.y-u.y,y=0,x=o.targets.length;y<x;y++){var _=o.targets[y],k=void 0;(k=g.func(_)?_(h,b,e._proxy,u,y):_)&&c.push({x:(g.number(k.x)?k.x:h)+u.x,y:(g.number(k.y)?k.y:b)+u.y,range:g.number(k.range)?k.range:o.range,source:_,index:y,offset:u})}for(var T={target:null,inRange:!1,distance:0,range:0,delta:{x:0,y:0}},I=0;I<c.length;I++){var M=c[I],P=M.range,O=M.x-s.x,Y=M.y-s.y,R=xe(O,Y),B=R<=P;P===1/0&&T.inRange&&T.range!==1/0&&(B=!1),T.target&&!(B?T.inRange&&P!==1/0?R/P<T.distance/T.range:P===1/0&&T.range!==1/0||R<T.distance:!T.inRange&&R<T.distance)||(T.target=M,T.distance=R,T.range=P,T.inRange=B,T.delta.x=O,T.delta.y=Y)}return T.inRange&&(n.x=T.target.x,n.y=T.target.y),r.closest=T,T},defaults:{range:1/0,targets:null,offset:null,offsetWithOrigin:!0,origin:null,relativePoints:null,endOnly:!1,enabled:!1}},Or=ie(wt,"snap"),We={start:function(t){var e=t.state,n=t.edges,r=e.options;if(!n)return null;t.state={options:{targets:null,relativePoints:[{x:n.left?0:1,y:n.top?0:1}],offset:r.offset||"self",origin:{x:0,y:0},range:r.range}},e.targetFields=e.targetFields||[["width","height"],["x","y"]],wt.start(t),e.offsets=t.state.offsets,t.state=e},set:function(t){var e=t.interaction,n=t.state,r=t.coords,o=n.options,i=n.offsets,a={x:r.x-i[0].x,y:r.y-i[0].y};n.options=S({},o),n.options.targets=[];for(var s=0,c=o.targets||[];s<c.length;s++){var p=c[s],l=void 0;if(l=g.func(p)?p(a.x,a.y,e):p){for(var u=0,h=n.targetFields;u<h.length;u++){var b=h[u],y=b[0],x=b[1];if(y in l||x in l){l.x=l[y],l.y=l[x];break}}n.options.targets.push(l)}}var _=wt.set(t);return n.options=o,_},defaults:{range:1/0,targets:null,offset:null,endOnly:!1,enabled:!1}},Dr=ie(We,"snapSize"),kt={aspectRatio:kr,restrictEdges:Er,restrict:_r,restrictRect:Sr,restrictSize:Ir,snapEdges:ie({start:function(t){var e=t.edges;return e?(t.state.targetFields=t.state.targetFields||[[e.left?"left":"right",e.top?"top":"bottom"]],We.start(t)):null},set:We.set,defaults:S(ge(We.defaults),{targets:void 0,range:void 0,offset:{x:0,y:0}})},"snapEdges"),snap:Or,snapSize:Dr,spring:Be,avoid:Be,transform:Be,rubberband:Be},Mr={id:"modifiers",install:function(t){var e=t.interactStatic;for(var n in t.usePlugin(an),t.usePlugin(yr),e.modifiers=kt,kt){var r=kt[n],o=r._defaults,i=r._methods;o._methods=i,t.defaults.perAction[n]=o}}},Ar=Mr,Tn=(function(t){w(n,t);var e=j(n);function n(r,o,i,a,s,c){var p;if(d(this,n),$e(C(p=e.call(this,s)),i),i!==o&&$e(C(p),o),p.timeStamp=c,p.originalEvent=i,p.type=r,p.pointerId=we(o),p.pointerType=Xt(o),p.target=a,p.currentTarget=null,r==="tap"){var l=s.getPointerIndex(o);p.dt=p.timeStamp-s.pointers[l].downTime;var u=p.timeStamp-s.tapTime;p.double=!!s.prevTap&&s.prevTap.type!=="doubletap"&&s.prevTap.target===p.target&&u<500}else r==="doubletap"&&(p.dt=o.timeStamp-s.tapTime,p.double=!0);return p}return f(n,[{key:"_subtractOrigin",value:function(r){var o=r.x,i=r.y;return this.pageX-=o,this.pageY-=i,this.clientX-=o,this.clientY-=i,this}},{key:"_addOrigin",value:function(r){var o=r.x,i=r.y;return this.pageX+=o,this.pageY+=i,this.clientX+=o,this.clientY+=i,this}},{key:"preventDefault",value:function(){this.originalEvent.preventDefault()}}]),n})(Fe),ze={id:"pointer-events/base",before:["inertia","modifiers","auto-start","actions"],install:function(t){t.pointerEvents=ze,t.defaults.actions.pointerEvents=ze.defaults,S(t.actions.phaselessTypes,ze.types)},listeners:{"interactions:new":function(t){var e=t.interaction;e.prevTap=null,e.tapTime=0},"interactions:update-pointer":function(t){var e=t.down,n=t.pointerInfo;!e&&n.hold||(n.hold={duration:1/0,timeout:null})},"interactions:move":function(t,e){var n=t.interaction,r=t.pointer,o=t.event,i=t.eventTarget;t.duplicate||n.pointerIsDown&&!n.pointerWasMoved||(n.pointerIsDown&&_t(t),se({interaction:n,pointer:r,event:o,eventTarget:i,type:"move"},e))},"interactions:down":function(t,e){(function(n,r){for(var o=n.interaction,i=n.pointer,a=n.event,s=n.eventTarget,c=n.pointerIndex,p=o.pointers[c].hold,l=At(s),u={interaction:o,pointer:i,event:a,eventTarget:s,type:"hold",targets:[],path:l,node:null},h=0;h<l.length;h++){var b=l[h];u.node=b,r.fire("pointerEvents:collect-targets",u)}if(u.targets.length){for(var y=1/0,x=0,_=u.targets;x<_.length;x++){var k=_[x].eventable.options.holdDuration;k<y&&(y=k)}p.duration=y,p.timeout=setTimeout((function(){se({interaction:o,eventTarget:s,pointer:i,event:a,type:"hold"},r)}),y)}})(t,e),se(t,e)},"interactions:up":function(t,e){_t(t),se(t,e),(function(n,r){var o=n.interaction,i=n.pointer,a=n.event,s=n.eventTarget;o.pointerWasMoved||se({interaction:o,eventTarget:s,pointer:i,event:a,type:"tap"},r)})(t,e)},"interactions:cancel":function(t,e){_t(t),se(t,e)}},PointerEvent:Tn,fire:se,collectEventTargets:Sn,defaults:{holdDuration:600,ignoreFrom:null,allowFrom:null,origin:{x:0,y:0}},types:{down:!0,move:!0,up:!0,cancel:!0,tap:!0,doubletap:!0,hold:!0}};function se(t,e){var n=t.interaction,r=t.pointer,o=t.event,i=t.eventTarget,a=t.type,s=t.targets,c=s===void 0?Sn(t,e):s,p=new Tn(a,r,o,i,n,e.now());e.fire("pointerEvents:new",{pointerEvent:p});for(var l={interaction:n,pointer:r,event:o,eventTarget:i,targets:c,type:a,pointerEvent:p},u=0;u<c.length;u++){var h=c[u];for(var b in h.props||{})p[b]=h.props[b];var y=be(h.eventable,h.node);if(p._subtractOrigin(y),p.eventable=h.eventable,p.currentTarget=h.node,h.eventable.fire(p),p._addOrigin(y),p.immediatePropagationStopped||p.propagationStopped&&u+1<c.length&&c[u+1].node!==p.currentTarget)break}if(e.fire("pointerEvents:fired",l),a==="tap"){var x=p.double?se({interaction:n,pointer:r,event:o,eventTarget:i,type:"doubletap"},e):p;n.prevTap=x,n.tapTime=x.timeStamp}return p}function Sn(t,e){var n=t.interaction,r=t.pointer,o=t.event,i=t.eventTarget,a=t.type,s=n.getPointerIndex(r),c=n.pointers[s];if(a==="tap"&&(n.pointerWasMoved||!c||c.downTarget!==i))return[];for(var p=At(i),l={interaction:n,pointer:r,event:o,eventTarget:i,type:a,path:p,targets:[],node:null},u=0;u<p.length;u++){var h=p[u];l.node=h,e.fire("pointerEvents:collect-targets",l)}return a==="hold"&&(l.targets=l.targets.filter((function(b){var y,x;return b.eventable.options.holdDuration===((y=n.pointers[s])==null||(x=y.hold)==null?void 0:x.duration)}))),l.targets}function _t(t){var e=t.interaction,n=t.pointerIndex,r=e.pointers[n].hold;r&&r.timeout&&(clearTimeout(r.timeout),r.timeout=null)}var Cr=Object.freeze({__proto__:null,default:ze});function $r(t){var e=t.interaction;e.holdIntervalHandle&&(clearInterval(e.holdIntervalHandle),e.holdIntervalHandle=null)}var Rr={id:"pointer-events/holdRepeat",install:function(t){t.usePlugin(ze);var e=t.pointerEvents;e.defaults.holdRepeatInterval=0,e.types.holdrepeat=t.actions.phaselessTypes.holdrepeat=!0},listeners:["move","up","cancel","endall"].reduce((function(t,e){return t["pointerEvents:".concat(e)]=$r,t}),{"pointerEvents:new":function(t){var e=t.pointerEvent;e.type==="hold"&&(e.count=(e.count||0)+1)},"pointerEvents:fired":function(t,e){var n=t.interaction,r=t.pointerEvent,o=t.eventTarget,i=t.targets;if(r.type==="hold"&&i.length){var a=i[0].eventable.options.holdRepeatInterval;a<=0||(n.holdIntervalHandle=setTimeout((function(){e.pointerEvents.fire({interaction:n,eventTarget:o,type:"hold",pointer:r,event:r},e)}),a))}}})},jr=Rr,Fr={id:"pointer-events/interactableTargets",install:function(t){var e=t.Interactable;e.prototype.pointerEvents=function(r){return S(this.events.options,r),this};var n=e.prototype._backCompatOption;e.prototype._backCompatOption=function(r,o){var i=n.call(this,r,o);return i===this&&(this.events.options[r]=o),i}},listeners:{"pointerEvents:collect-targets":function(t,e){var n=t.targets,r=t.node,o=t.type,i=t.eventTarget;e.interactables.forEachMatch(r,(function(a){var s=a.events,c=s.options;s.types[o]&&s.types[o].length&&a.testIgnoreAllow(c,r,i)&&n.push({node:r,eventable:s,props:{interactable:a}})}))},"interactable:new":function(t){var e=t.interactable;e.events.getRect=function(n){return e.getRect(n)}},"interactable:set":function(t,e){var n=t.interactable,r=t.options;S(n.events.options,e.pointerEvents.defaults),S(n.events.options,r.pointerEvents||{})}}},Lr=Fr,Nr={id:"pointer-events",install:function(t){t.usePlugin(Cr),t.usePlugin(jr),t.usePlugin(Lr)}},Xr=Nr,Yr={id:"reflow",install:function(t){var e=t.Interactable;t.actions.phases.reflow=!0,e.prototype.reflow=function(n){return(function(r,o,i){for(var a=r.getAllElements(),s=i.window.Promise,c=s?[]:null,p=function(){var u=a[l],h=r.getRect(u);if(!h)return 1;var b,y=_e(i.interactions.list,(function(k){return k.interacting()&&k.interactable===r&&k.element===u&&k.prepared.name===o.name}));if(y)y.move(),c&&(b=y._reflowPromise||new s((function(k){y._reflowResolve=k})));else{var x=tt(h),_=(function(k){return{coords:k,get page(){return this.coords.page},get client(){return this.coords.client},get timeStamp(){return this.coords.timeStamp},get pageX(){return this.coords.page.x},get pageY(){return this.coords.page.y},get clientX(){return this.coords.client.x},get clientY(){return this.coords.client.y},get pointerId(){return this.coords.pointerId},get target(){return this.coords.target},get type(){return this.coords.type},get pointerType(){return this.coords.pointerType},get buttons(){return this.coords.buttons},preventDefault:function(){}}})({page:{x:x.x,y:x.y},client:{x:x.x,y:x.y},timeStamp:i.now()});b=(function(k,T,I,M,P){var O=k.interactions.new({pointerType:"reflow"}),Y={interaction:O,event:P,pointer:P,eventTarget:I,phase:"reflow"};O.interactable=T,O.element=I,O.prevEvent=P,O.updatePointer(P,P,I,!0),jt(O.coords.delta),ut(O.prepared,M),O._doPhase(Y);var R=k.window,B=R.Promise,V=B?new B((function(ne){O._reflowResolve=ne})):void 0;return O._reflowPromise=V,O.start(M,T,I),O._interacting?(O.move(Y),O.end(P)):(O.stop(),O._reflowResolve()),O.removePointer(P,P),V})(i,r,u,o,_)}c&&c.push(b)},l=0;l<a.length&&!p();l++);return c&&s.all(c).then((function(){return r}))})(this,n,t)}},listeners:{"interactions:stop":function(t,e){var n=t.interaction;n.pointerType==="reflow"&&(n._reflowResolve&&n._reflowResolve(),(function(r,o){r.splice(r.indexOf(o),1)})(e.interactions.list,n))}}},qr=Yr;if(X.use(on),X.use(un),X.use(Xr),X.use(ar),X.use(Ar),X.use(Vn),X.use(jn),X.use(Ln),X.use(qr),X.default=X,(typeof he>"u"?"undefined":L(he))==="object"&&he)try{he.exports=X}catch{}return X.default=X,X}))});var to={};Kr(to,{workshopBoard:()=>Tt});var U=Zr(zn()),Z={yellow:"#fbbf24",blue:"#60a5fa",green:"#4ade80",pink:"#f472b6",purple:"#a78bfa",orange:"#fb923c",teal:"#2dd4bf",red:"#f87171"};var In={note:{width:200,height:150,color:"yellow"},text:{width:300,height:40,color:"yellow"},section:{width:500,height:400,color:"yellow"},shape:{width:120,height:120,color:"blue"}},Ue='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:12px;height:12px;"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>';function Tt({notes:A=[],canvasBlocks:$=[],gridLayout:L={}}={}){return{panX:0,panY:0,scale:1,_isPanning:!1,_panStart:null,_panButton:-1,_spaceDown:!1,_listeners:[],_saveTimers:{},_textTimers:{},_nextTempId:-1,colorPickerOpen:null,colors:Object.keys(Z),isFullscreen:!1,init(){this._initialized||(this._initialized=!0,this.$nextTick(()=>{this._renderNotes(A),this._initPanZoom(),this._initInteract(),this._fitGrid(),this._on(document,"keydown",d=>{d.key==="Escape"&&this.isFullscreen&&(d.preventDefault(),this.isFullscreen=!1,this._fitAfterDelay())},!1)}))},destroy(){this._listeners.forEach(([d,v,f,m])=>d.removeEventListener(v,f,m)),this._listeners=[],(0,U.default)(".workshop-note").unset(),(0,U.default)(".workshop-text").unset(),(0,U.default)(".workshop-section").unset(),(0,U.default)(".workshop-shape").unset(),(0,U.default)(".workshop-canvas-background").unset()},_on(d,v,f,m){d.addEventListener(v,f,m),this._listeners.push([d,v,f,m])},_renderNotes(d){let v=this.$refs.board;d.forEach(f=>v.appendChild(this._createNoteEl(f)))},_createNoteEl(d){switch(d.type||"note"){case"text":return this._createTextEl(d);case"section":return this._createSectionEl(d);case"shape":return this._createShapeEl(d);default:return this._createStickyEl(d)}},_createStickyEl(d){let v=d.color||"yellow",f=d.x??0,m=d.y??0,w=d.width??200,D=d.height??150,E=document.createElement("div");return E.className=`workshop-note workshop-note-${v}`,E.dataset.noteId=d.id,E.dataset.noteType="note",E.dataset.x=f,E.dataset.y=m,E.style.cssText=`width:${w}px;height:${D}px;transform:translate(${f}px,${m}px);`,E.innerHTML=`
        <div class="drag-handle">
          <div style="display:flex;align-items:center;gap:6px;">
            <div class="drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></div>
            ${this._colorDotHTML(v)}
          </div>
          <button class="note-delete" data-action="delete" title="Loeschen">${Ue}</button>
        </div>
        <div class="note-body">
          <input type="text" value="${this._esc(d.title||"")}" placeholder="Titel..." />
          <textarea placeholder="Notiz...">${this._esc(d.content||"")}</textarea>
        </div>
        <div class="resize-handle"></div>
      `,this._bindNoteEvents(E),this._bindTextSave(E),E},_createTextEl(d){let v=d.x??0,f=d.y??0,m=d.width??300,w=d.height??40,D=d.metadata?.fontSize||Math.max(14,Math.round(m/12)),E=document.createElement("div");return E.className="workshop-text",E.dataset.noteId=d.id,E.dataset.noteType="text",E.dataset.x=v,E.dataset.y=f,E.style.cssText=`width:${m}px;height:${w}px;transform:translate(${v}px,${f}px);`,E.innerHTML=`
        <div class="drag-handle text-drag-handle">
          <div class="drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></div>
          <div class="text-body">
            <input type="text" value="${this._esc(d.title||"")}" placeholder="Text eingeben..." style="font-size:${D}px;" />
          </div>
          <button class="note-delete" data-action="delete" title="Loeschen">${Ue}</button>
        </div>
        <div class="resize-handle"></div>
      `,this._bindDeleteEvent(E),this._bindTextInputSave(E),E},_createSectionEl(d){let v=d.color||"yellow",f=d.x??0,m=d.y??0,w=d.width??500,D=d.height??400,E=document.createElement("div");return E.className=`workshop-section workshop-section-${v}`,E.dataset.noteId=d.id,E.dataset.noteType="section",E.dataset.x=f,E.dataset.y=m,E.style.cssText=`width:${w}px;height:${D}px;transform:translate(${f}px,${m}px);border-color:${Z[v]||Z.yellow};`,E.innerHTML=`
        <div class="drag-handle section-drag-handle">
          <div style="display:flex;align-items:center;gap:6px;flex:1;min-width:0;">
            <div class="drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></div>
            ${this._colorDotHTML(v)}
            <input type="text" class="section-title" value="${this._esc(d.title||"")}" placeholder="Section..." />
          </div>
          <button class="note-delete" data-action="delete" title="Loeschen">${Ue}</button>
        </div>
        <div class="resize-handle"></div>
      `,this._bindNoteEvents(E),this._bindSectionTextSave(E),E},_createShapeEl(d){let v=d.color||"blue",f=d.metadata?.shape||"rect",m=d.x??0,w=d.y??0,D=d.width??120,E=d.height??120,C=document.createElement("div");return C.className=`workshop-shape workshop-shape-${f} workshop-shape-color-${v}`,C.dataset.noteId=d.id,C.dataset.noteType="shape",C.dataset.shape=f,C.dataset.x=m,C.dataset.y=w,C.style.cssText=`width:${D}px;height:${E}px;transform:translate(${m}px,${w}px);`,C.innerHTML=`
        <div class="shape-visual"></div>
        <div class="drag-handle shape-drag-handle">
          <div style="display:flex;align-items:center;gap:4px;">
            <div class="drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></div>
            ${this._colorDotHTML(v)}
            <button class="shape-toggle" data-action="toggle-shape" title="Form wechseln">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:10px;height:10px;"><path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H4.598a.75.75 0 00-.75.75v3.634a.75.75 0 001.5 0v-2.033l.312.312a7 7 0 0011.712-3.138.75.75 0 00-1.449-.39zm1.06-7.846a.75.75 0 00-1.5 0v2.034l-.312-.312A7 7 0 002.848 8.438a.75.75 0 001.449.39 5.5 5.5 0 019.201-2.466l.312.311H11.38a.75.75 0 000 1.5h3.634a.75.75 0 00.75-.75V3.578z" clip-rule="evenodd"/></svg>
            </button>
            <button class="note-delete" data-action="delete" title="Loeschen">${Ue}</button>
          </div>
        </div>
        <div class="shape-body">
          <input type="text" value="${this._esc(d.title||"")}" placeholder="..." />
        </div>
        <div class="resize-handle"></div>
      `,this._bindShapeEvents(C),this._bindShapeTextSave(C),C},_colorDotHTML(d){return`<div class="color-dot-wrap" style="position:relative;">
        <div class="color-dot" style="background:${Z[d]||Z.yellow};" data-action="color"></div>
        <div class="color-picker-dd" style="display:none;position:absolute;top:100%;left:0;margin-top:4px;padding:4px;background:white;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.15);border:1px solid #e5e7eb;z-index:50;gap:3px;flex-wrap:nowrap;">
          ${this.colors.map(v=>`<div class="color-dot${v===d?" active":""}" style="background:${Z[v]};" data-pick-color="${v}"></div>`).join("")}
        </div>
      </div>`},_bindNoteEvents(d){d.addEventListener("click",v=>{let f=v.target.closest("[data-action]")?.dataset.action,m=v.target.closest("[data-pick-color]")?.dataset.pickColor,w=parseInt(d.dataset.noteId);if(m){v.stopPropagation(),this._changeColor(d,w,m);return}if(f==="color"){v.stopPropagation(),this._toggleColorPicker(d);return}if(f==="delete"){v.stopPropagation(),confirm("Element loeschen?")&&this._deleteNote(d,w);return}})},_bindDeleteEvent(d){d.addEventListener("click",v=>{let f=v.target.closest("[data-action]")?.dataset.action,m=parseInt(d.dataset.noteId);f==="delete"&&(v.stopPropagation(),confirm("Element loeschen?")&&this._deleteNote(d,m))})},_bindShapeEvents(d){d.addEventListener("click",v=>{let f=v.target.closest("[data-action]")?.dataset.action,m=v.target.closest("[data-pick-color]")?.dataset.pickColor,w=parseInt(d.dataset.noteId);if(m){v.stopPropagation(),this._changeShapeColor(d,w,m);return}if(f==="color"){v.stopPropagation(),this._toggleColorPicker(d);return}if(f==="toggle-shape"){v.stopPropagation(),this._toggleShape(d,w);return}if(f==="delete"){v.stopPropagation(),confirm("Element loeschen?")&&this._deleteNote(d,w);return}})},_bindTextSave(d){let v=d.querySelector(".note-body input"),f=d.querySelector(".note-body textarea"),m=()=>{let w=parseInt(d.dataset.noteId);w<0||(clearTimeout(this._textTimers[w]),this._textTimers[w]=setTimeout(()=>{this.$wire.call("updateNoteText",w,v.value,f.value)},400))};v.addEventListener("blur",m),f.addEventListener("blur",m),v.addEventListener("keydown",w=>{w.key==="Enter"&&w.target.blur()})},_bindTextInputSave(d){let v=d.querySelector(".text-body input"),f=()=>{let m=parseInt(d.dataset.noteId);m<0||(clearTimeout(this._textTimers[m]),this._textTimers[m]=setTimeout(()=>{this.$wire.call("updateNoteText",m,v.value,"")},400))};v.addEventListener("blur",f),v.addEventListener("keydown",m=>{m.key==="Enter"&&m.target.blur()})},_bindSectionTextSave(d){let v=d.querySelector(".section-title"),f=()=>{let m=parseInt(d.dataset.noteId);m<0||(clearTimeout(this._textTimers[m]),this._textTimers[m]=setTimeout(()=>{this.$wire.call("updateNoteText",m,v.value,"")},400))};v.addEventListener("blur",f),v.addEventListener("keydown",m=>{m.key==="Enter"&&m.target.blur()})},_bindShapeTextSave(d){let v=d.querySelector(".shape-body input"),f=()=>{let m=parseInt(d.dataset.noteId);m<0||(clearTimeout(this._textTimers[m]),this._textTimers[m]=setTimeout(()=>{this.$wire.call("updateNoteText",m,v.value,"")},400))};v.addEventListener("blur",f),v.addEventListener("keydown",m=>{m.key==="Enter"&&m.target.blur()})},_esc(d){let v=document.createElement("div");return v.textContent=d,v.innerHTML},_applyTransform(){let d=this.$refs.board;d&&(d.style.transform=`translate(${this.panX}px,${this.panY}px) scale(${this.scale})`)},_screenToBoard(d,v){return{x:(d-this.panX)/this.scale,y:(v-this.panY)/this.scale}},_zoomTo(d,v,f){let m=this.$refs.board?.parentElement;if(!m)return;let w=m.getBoundingClientRect(),D=v-w.left,E=f-w.top,C=Math.max(.1,Math.min(4,d)),j=C/this.scale;this.panX=D-(D-this.panX)*j,this.panY=E-(E-this.panY)*j,this.scale=C,this._applyTransform()},_initPanZoom(){let d=this.$refs.board;if(!d)return;let v=d.parentElement;d.style.transformOrigin="0 0",this._on(v,"wheel",f=>{f.preventDefault(),f.ctrlKey||f.metaKey?this._zoomTo(this.scale*(1-f.deltaY*.003),f.clientX,f.clientY):(this.panX-=f.deltaX,this.panY-=f.deltaY,this._applyTransform())},{passive:!1}),this._on(v,"pointerdown",f=>{f.target.closest(".workshop-note, .workshop-text, .workshop-section, .workshop-shape, .workshop-toolbar, .workshop-zoom-controls")||(f.button===1||f.button===0&&this._spaceDown)&&(this._isPanning=!0,this._panButton=f.button,this._panStart={x:f.clientX,y:f.clientY,px:this.panX,py:this.panY},v.style.cursor="grabbing",v.setPointerCapture(f.pointerId),f.preventDefault())},!1),this._on(v,"pointermove",f=>{this._isPanning&&this._panStart&&(this.panX=this._panStart.px+(f.clientX-this._panStart.x),this.panY=this._panStart.py+(f.clientY-this._panStart.y),this._applyTransform())},!1),this._on(v,"pointerup",f=>{this._isPanning&&(this._isPanning=!1,this._panStart=null,v.style.cursor=this._spaceDown?"grab":"")},!1),this._on(v,"contextmenu",f=>{this._panButton===1&&f.preventDefault()},!1),this._on(document,"keydown",f=>{f.code==="Space"&&!f.repeat&&!f.target.matches("input,textarea,[contenteditable]")&&(f.preventDefault(),this._spaceDown=!0,v.style.cursor="grab")},!1),this._on(document,"keyup",f=>{f.code==="Space"&&(this._spaceDown=!1,this._isPanning||(v.style.cursor=""))},!1),this._on(document,"click",()=>{d.querySelectorAll('.color-picker-dd[style*="flex"]').forEach(f=>f.style.display="none")},!1)},zoomIn(){this._zoomToCenter(this.scale*1.3)},zoomOut(){this._zoomToCenter(this.scale/1.3)},resetZoom(){this.scale=1,this.panX=0,this.panY=0,this._applyTransform()},fitToScreen(){this._fitGrid()},toggleFullscreen(){this.isFullscreen=!this.isFullscreen,this._fitAfterDelay()},_fitAfterDelay(){setTimeout(()=>this._fitGrid(),50),setTimeout(()=>this._fitGrid(),200),setTimeout(()=>this._fitGrid(),500)},_zoomToCenter(d){let v=this.$refs.board?.parentElement;if(!v)return;let f=v.getBoundingClientRect();this._zoomTo(d,f.left+v.clientWidth/2,f.top+v.clientHeight/2)},_fitGrid(){let d=this.$refs.board,v=d?.parentElement,f=d?.querySelector(".workshop-canvas-background");if(!f||!v)return;let m=f.offsetWidth,w=f.offsetHeight,D=f.offsetLeft,E=f.offsetTop,C=v.clientWidth,j=v.clientHeight,q=40,F=Math.min((C-q*2)/m,(j-q*2)/w,1);this.scale=F,this.panX=(C-m*F)/2-D*F,this.panY=(j-w*F)/2-E*F,this._applyTransform()},_initInteract(){let d=this,v=".workshop-note, .workshop-text, .workshop-section, .workshop-shape",f=v;(0,U.default)(v).draggable({allowFrom:".drag-handle",ignoreFrom:"input, textarea, .note-delete, .shape-toggle, .color-dot, .color-picker-dd",inertia:!1,listeners:{start(m){m.target.classList.add("dragging")},move(m){let w=m.target,D=(parseFloat(w.dataset.x)||0)+m.dx/d.scale,E=(parseFloat(w.dataset.y)||0)+m.dy/d.scale;w.style.transform=`translate(${D}px,${E}px)`,w.dataset.x=D,w.dataset.y=E},end(m){m.target.classList.remove("dragging");let w=m.target,D=parseInt(w.dataset.noteId);D<0||d._savePos(D,w)}}}),(0,U.default)(f).resizable({edges:{right:".resize-handle",bottom:".resize-handle"},modifiers:[U.default.modifiers.restrictSize({min:{width:60,height:30}})],listeners:{move(m){let w=m.target,D=parseFloat(w.dataset.x)||0,E=parseFloat(w.dataset.y)||0,C=m.rect.width/d.scale,j=m.rect.height/d.scale;if(w.style.width=C+"px",w.style.height=j+"px",D+=m.deltaRect.left/d.scale,E+=m.deltaRect.top/d.scale,w.style.transform=`translate(${D}px,${E}px)`,w.dataset.x=D,w.dataset.y=E,w.dataset.noteType==="text"){let q=Math.max(14,Math.round(C/12)),F=w.querySelector(".text-body input");F&&(F.style.fontSize=q+"px")}},end(m){let w=m.target,D=parseInt(w.dataset.noteId);D<0||d._savePos(D,w)}}}),(0,U.default)(".workshop-canvas-background").resizable({edges:{right:!0,bottom:!0},modifiers:[U.default.modifiers.restrictSize({min:{width:400,height:300}})],listeners:{move(m){let w=m.target;w.style.width=m.rect.width/d.scale+"px",w.style.minHeight=m.rect.height/d.scale+"px"},end(m){let w=m.target,D=parseInt(w.style.width)||1200,E=parseInt(w.style.minHeight)||800;clearTimeout(d._gridSaveTimer),d._gridSaveTimer=setTimeout(()=>{d.$wire.call("updateWorkshopSettings",{gridWidth:D,gridHeight:E})},400)}}})},_savePos(d,v){clearTimeout(this._saveTimers[d]),this._saveTimers[d]=setTimeout(()=>{let f=this._detectBlock(v);this.$wire.call("updateNotePosition",d,{x:parseFloat(v.dataset.x)||0,y:parseFloat(v.dataset.y)||0,width:parseInt(v.style.width)||200,height:parseInt(v.style.height)||150,blockId:f})},300)},_detectBlock(d){let v=parseFloat(d.dataset.x)||0,f=parseFloat(d.dataset.y)||0,m=v+(parseInt(d.style.width)||0)/2,w=f+(parseInt(d.style.height)||0)/2,D=this.$refs.board?.querySelectorAll(".workshop-grid-block[data-block-id]");if(!D)return null;for(let E of D){let C=E.offsetLeft+E.offsetParent?.offsetLeft||0,j=E.offsetTop+E.offsetParent?.offsetTop||0,q=E.offsetWidth,F=E.offsetHeight;if(m>=C&&m<=C+q&&w>=j&&w<=j+F)return parseInt(E.dataset.blockId)||null}return null},addElement(d="note"){let v=this.$refs.board?.parentElement;if(!v)return;let f=v.getBoundingClientRect(),m=In[d]||In.note,w=(f.width/2-this.panX)/this.scale,D=(f.height/2-this.panY)/this.scale,E=Math.round(w-m.width/2),C=Math.round(D-m.height/2),j=this._nextTempId--,q=d==="shape"?{shape:"rect"}:null,F=this._createNoteEl({id:j,type:d,title:"",content:"",color:m.color,x:E,y:C,width:m.width,height:m.height,metadata:q});this.$refs.board.appendChild(F),this.$wire.call("addWorkshopNote",{x:E,y:C},d).then(()=>{this.$wire.call("getWorkshopNotes").then(ce=>{if(Array.isArray(ce)&&ce.length>0){let Oe=ce.reduce((K,me)=>K.id>me.id?K:me);F.dataset.noteId=Oe.id}})}),setTimeout(()=>{F.querySelector(".note-body input, .text-body input, .section-title, .shape-body input")?.focus()},100)},addNote(){this.addElement("note")},_deleteNote(d,v){d.remove(),v>0&&this.$wire.call("deleteWorkshopNote",v)},_changeColor(d,v,f){let m=d.dataset.noteType||"note";m==="note"?d.className=d.className.replace(/workshop-note-\w+/,`workshop-note-${f}`):m==="section"&&(d.className=d.className.replace(/workshop-section-\w+/,`workshop-section-${f}`),d.style.borderColor=Z[f]||Z.yellow),d.querySelector(".drag-handle .color-dot")?.setAttribute("style",`background:${Z[f]}`),d.querySelector(".color-picker-dd").style.display="none",d.querySelectorAll(".color-picker-dd .color-dot").forEach(w=>{w.classList.toggle("active",w.dataset.pickColor===f)}),v>0&&this.$wire.call("updateNoteColor",v,f)},_changeShapeColor(d,v,f){d.className=d.className.replace(/workshop-shape-color-\w+/,`workshop-shape-color-${f}`);let m=d.querySelector(".shape-visual");m&&(m.className="shape-visual"),d.querySelector(".color-dot")?.setAttribute("style",`background:${Z[f]}`),d.querySelector(".color-picker-dd").style.display="none",d.querySelectorAll(".color-picker-dd .color-dot").forEach(w=>{w.classList.toggle("active",w.dataset.pickColor===f)}),v>0&&this.$wire.call("updateNoteColor",v,f)},_toggleShape(d,v){let f=["rect","circle","diamond"],m=d.dataset.shape||"rect",w=f[(f.indexOf(m)+1)%f.length];d.dataset.shape=w,d.className=d.className.replace(/workshop-shape-(?:rect|circle|diamond)/,`workshop-shape-${w}`),v>0&&this.$wire.call("updateNoteMetadata",v,{shape:w})},_toggleColorPicker(d){let v=d.querySelector(".color-picker-dd");if(!v)return;let f=v.style.display==="flex";this.$refs.board.querySelectorAll(".color-picker-dd").forEach(m=>m.style.display="none"),v.style.display=f?"none":"flex"}}}var On=`/* \u2500\u2500\u2500 Board (infinite canvas) \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500 */
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
`;function eo(){if(document.getElementById("platform-workshop-styles"))return;let A=document.createElement("style");A.id="platform-workshop-styles",A.textContent=On,document.head.appendChild(A)}function St(){let A=window.Alpine;A&&A.data("workshopBoard",Tt)}typeof document<"u"&&(eo(),document.addEventListener("livewire:init",St),document.readyState!=="loading"?setTimeout(St,0):document.addEventListener("DOMContentLoaded",St));return Qr(to);})();
