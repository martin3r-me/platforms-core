/* platform-workshop v0.0.0 | MIT */
var PlatformWorkshop=(()=>{var Yo=Object.create;var Ve=Object.defineProperty;var Xo=Object.getOwnPropertyDescriptor;var Ko=Object.getOwnPropertyNames;var Uo=Object.getPrototypeOf,Vo=Object.prototype.hasOwnProperty;var Wo=(N,j)=>()=>(j||N((j={exports:{}}).exports,j),j.exports),Zo=(N,j)=>{for(var R in j)Ve(N,R,{get:j[R],enumerable:!0})},In=(N,j,R,a)=>{if(j&&typeof j=="object"||typeof j=="function")for(let l of Ko(j))!Vo.call(N,l)&&l!==R&&Ve(N,l,{get:()=>j[l],enumerable:!(a=Xo(j,l))||a.enumerable});return N};var Jo=(N,j,R)=>(R=N!=null?Yo(Uo(N)):{},In(j||!N||!N.__esModule?Ve(R,"default",{value:N,enumerable:!0}):R,N)),Qo=N=>In(Ve({},"__esModule",{value:!0}),N);var zn=Wo((Et,ve)=>{(function(N,j){typeof Et=="object"&&typeof ve<"u"?ve.exports=j():typeof define=="function"&&define.amd?define(j):(N=typeof globalThis<"u"?globalThis:N||self).interact=j()})(Et,(function(){"use strict";function N(t,e){var n=Object.keys(t);if(Object.getOwnPropertySymbols){var o=Object.getOwnPropertySymbols(t);e&&(o=o.filter((function(r){return Object.getOwnPropertyDescriptor(t,r).enumerable}))),n.push.apply(n,o)}return n}function j(t){for(var e=1;e<arguments.length;e++){var n=arguments[e]!=null?arguments[e]:{};e%2?N(Object(n),!0).forEach((function(o){h(t,o,n[o])})):Object.getOwnPropertyDescriptors?Object.defineProperties(t,Object.getOwnPropertyDescriptors(n)):N(Object(n)).forEach((function(o){Object.defineProperty(t,o,Object.getOwnPropertyDescriptor(n,o))}))}return t}function R(t){return R=typeof Symbol=="function"&&typeof Symbol.iterator=="symbol"?function(e){return typeof e}:function(e){return e&&typeof Symbol=="function"&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},R(t)}function a(t,e){if(!(t instanceof e))throw new TypeError("Cannot call a class as a function")}function l(t,e){for(var n=0;n<e.length;n++){var o=e[n];o.enumerable=o.enumerable||!1,o.configurable=!0,"value"in o&&(o.writable=!0),Object.defineProperty(t,L(o.key),o)}}function u(t,e,n){return e&&l(t.prototype,e),n&&l(t,n),Object.defineProperty(t,"prototype",{writable:!1}),t}function h(t,e,n){return(e=L(e))in t?Object.defineProperty(t,e,{value:n,enumerable:!0,configurable:!0,writable:!0}):t[e]=n,t}function g(t,e){if(typeof e!="function"&&e!==null)throw new TypeError("Super expression must either be null or a function");t.prototype=Object.create(e&&e.prototype,{constructor:{value:t,writable:!0,configurable:!0}}),Object.defineProperty(t,"prototype",{writable:!1}),e&&b(t,e)}function w(t){return w=Object.setPrototypeOf?Object.getPrototypeOf.bind():function(e){return e.__proto__||Object.getPrototypeOf(e)},w(t)}function b(t,e){return b=Object.setPrototypeOf?Object.setPrototypeOf.bind():function(n,o){return n.__proto__=o,n},b(t,e)}function k(t){if(t===void 0)throw new ReferenceError("this hasn't been initialised - super() hasn't been called");return t}function D(t){var e=(function(){if(typeof Reflect>"u"||!Reflect.construct||Reflect.construct.sham)return!1;if(typeof Proxy=="function")return!0;try{return Boolean.prototype.valueOf.call(Reflect.construct(Boolean,[],(function(){}))),!0}catch{return!1}})();return function(){var n,o=w(t);if(e){var r=w(this).constructor;n=Reflect.construct(o,arguments,r)}else n=o.apply(this,arguments);return(function(i,s){if(s&&(typeof s=="object"||typeof s=="function"))return s;if(s!==void 0)throw new TypeError("Derived constructors may only return object or undefined");return k(i)})(this,n)}}function C(){return C=typeof Reflect<"u"&&Reflect.get?Reflect.get.bind():function(t,e,n){var o=(function(i,s){for(;!Object.prototype.hasOwnProperty.call(i,s)&&(i=w(i))!==null;);return i})(t,e);if(o){var r=Object.getOwnPropertyDescriptor(o,e);return r.get?r.get.call(arguments.length<3?t:n):r.value}},C.apply(this,arguments)}function L(t){var e=(function(n,o){if(typeof n!="object"||n===null)return n;var r=n[Symbol.toPrimitive];if(r!==void 0){var i=r.call(n,o||"default");if(typeof i!="object")return i;throw new TypeError("@@toPrimitive must return a primitive value.")}return(o==="string"?String:Number)(n)})(t,"string");return typeof e=="symbol"?e:e+""}var q=function(t){return!(!t||!t.Window)&&t instanceof t.Window},Z=void 0,H=void 0;function J(t){Z=t;var e=t.document.createTextNode("");e.ownerDocument!==t.document&&typeof t.wrap=="function"&&t.wrap(e)===e&&(t=t.wrap(t)),H=t}function ne(t){return q(t)?t:(t.ownerDocument||t).defaultView||H.window}typeof window<"u"&&window&&J(window);var $e=function(t){return!!t&&R(t)==="object"},It=function(t){return typeof t=="function"},y={window:function(t){return t===H||q(t)},docFrag:function(t){return $e(t)&&t.nodeType===11},object:$e,func:It,number:function(t){return typeof t=="number"},bool:function(t){return typeof t=="boolean"},string:function(t){return typeof t=="string"},element:function(t){if(!t||R(t)!=="object")return!1;var e=ne(t)||H;return/object|function/.test(typeof Element>"u"?"undefined":R(Element))?t instanceof Element||t instanceof e.Element:t.nodeType===1&&typeof t.nodeName=="string"},plainObject:function(t){return $e(t)&&!!t.constructor&&/function Object\b/.test(t.constructor.toString())},array:function(t){return $e(t)&&t.length!==void 0&&It(t.splice)}};function We(t){var e=t.interaction;if(e.prepared.name==="drag"){var n=e.prepared.axis;n==="x"?(e.coords.cur.page.y=e.coords.start.page.y,e.coords.cur.client.y=e.coords.start.client.y,e.coords.velocity.client.y=0,e.coords.velocity.page.y=0):n==="y"&&(e.coords.cur.page.x=e.coords.start.page.x,e.coords.cur.client.x=e.coords.start.client.x,e.coords.velocity.client.x=0,e.coords.velocity.page.x=0)}}function zt(t){var e=t.iEvent,n=t.interaction;if(n.prepared.name==="drag"){var o=n.prepared.axis;if(o==="x"||o==="y"){var r=o==="x"?"y":"x";e.page[r]=n.coords.start.page[r],e.client[r]=n.coords.start.client[r],e.delta[r]=0}}}var Ce={id:"actions/drag",install:function(t){var e=t.actions,n=t.Interactable,o=t.defaults;n.prototype.draggable=Ce.draggable,e.map.drag=Ce,e.methodDict.drag="draggable",o.actions.drag=Ce.defaults},listeners:{"interactions:before-action-move":We,"interactions:action-resume":We,"interactions:action-move":zt,"auto-start:check":function(t){var e=t.interaction,n=t.interactable,o=t.buttons,r=n.options.drag;if(r&&r.enabled&&(!e.pointerIsDown||!/mouse|pointer/.test(e.pointerType)||(o&n.options.drag.mouseButtons)!=0))return t.action={name:"drag",axis:r.lockAxis==="start"?r.startAxis:r.lockAxis},!1}},draggable:function(t){return y.object(t)?(this.options.drag.enabled=t.enabled!==!1,this.setPerAction("drag",t),this.setOnEvents("drag",t),/^(xy|x|y|start)$/.test(t.lockAxis)&&(this.options.drag.lockAxis=t.lockAxis),/^(xy|x|y)$/.test(t.startAxis)&&(this.options.drag.startAxis=t.startAxis),this):y.bool(t)?(this.options.drag.enabled=t,this):this.options.drag},beforeMove:We,move:zt,defaults:{startAxis:"xy",lockAxis:"xy"},getCursor:function(){return"move"},filterEventType:function(t){return t.search("drag")===0}},Mt=Ce,U={init:function(t){var e=t;U.document=e.document,U.DocumentFragment=e.DocumentFragment||me,U.SVGElement=e.SVGElement||me,U.SVGSVGElement=e.SVGSVGElement||me,U.SVGElementInstance=e.SVGElementInstance||me,U.Element=e.Element||me,U.HTMLElement=e.HTMLElement||U.Element,U.Event=e.Event,U.Touch=e.Touch||me,U.PointerEvent=e.PointerEvent||e.MSPointerEvent},document:null,DocumentFragment:null,SVGElement:null,SVGSVGElement:null,SVGElementInstance:null,Element:null,HTMLElement:null,Event:null,Touch:null,PointerEvent:null};function me(){}var G=U,V={init:function(t){var e=G.Element,n=t.navigator||{};V.supportsTouch="ontouchstart"in t||y.func(t.DocumentTouch)&&G.document instanceof t.DocumentTouch,V.supportsPointerEvent=n.pointerEnabled!==!1&&!!G.PointerEvent,V.isIOS=/iP(hone|od|ad)/.test(n.platform),V.isIOS7=/iP(hone|od|ad)/.test(n.platform)&&/OS 7[^\d]/.test(n.appVersion),V.isIe9=/MSIE 9/.test(n.userAgent),V.isOperaMobile=n.appName==="Opera"&&V.supportsTouch&&/Presto/.test(n.userAgent),V.prefixedMatchesSelector="matches"in e.prototype?"matches":"webkitMatchesSelector"in e.prototype?"webkitMatchesSelector":"mozMatchesSelector"in e.prototype?"mozMatchesSelector":"oMatchesSelector"in e.prototype?"oMatchesSelector":"msMatchesSelector",V.pEventTypes=V.supportsPointerEvent?G.PointerEvent===t.MSPointerEvent?{up:"MSPointerUp",down:"MSPointerDown",over:"mouseover",out:"mouseout",move:"MSPointerMove",cancel:"MSPointerCancel"}:{up:"pointerup",down:"pointerdown",over:"pointerover",out:"pointerout",move:"pointermove",cancel:"pointercancel"}:null,V.wheelEvent=G.document&&"onmousewheel"in G.document?"mousewheel":"wheel"},supportsTouch:null,supportsPointerEvent:null,isIOS7:null,isIOS:null,isIe9:null,isOperaMobile:null,prefixedMatchesSelector:null,pEventTypes:null,wheelEvent:null},Q=V;function ue(t,e){if(t.contains)return t.contains(e);for(;e;){if(e===t)return!0;e=e.parentNode}return!1}function Pt(t,e){for(;y.element(t);){if(se(t,e))return t;t=oe(t)}return null}function oe(t){var e=t.parentNode;if(y.docFrag(e)){for(;(e=e.host)&&y.docFrag(e););return e}return e}function se(t,e){return H!==Z&&(e=e.replace(/\/deep\//g," ")),t[Q.prefixedMatchesSelector](e)}var Ze=function(t){return t.parentNode||t.host};function Dt(t,e){for(var n,o=[],r=t;(n=Ze(r))&&r!==e&&n!==r.ownerDocument;)o.unshift(r),r=n;return o}function Je(t,e,n){for(;y.element(t);){if(se(t,e))return!0;if((t=oe(t))===n)return se(t,e)}return!1}function $t(t){return t.correspondingUseElement||t}function Qe(t){var e=t instanceof G.SVGElement?t.getBoundingClientRect():t.getClientRects()[0];return e&&{left:e.left,right:e.right,top:e.top,bottom:e.bottom,width:e.width||e.right-e.left,height:e.height||e.bottom-e.top}}function et(t){var e,n=Qe(t);if(!Q.isIOS7&&n){var o={x:(e=(e=ne(t))||H).scrollX||e.document.documentElement.scrollLeft,y:e.scrollY||e.document.documentElement.scrollTop};n.left+=o.x,n.right+=o.x,n.top+=o.y,n.bottom+=o.y}return n}function Ct(t){for(var e=[];t;)e.push(t),t=oe(t);return e}function Ot(t){return!!y.string(t)&&(G.document.querySelector(t),!0)}function z(t,e){for(var n in e)t[n]=e[n];return t}function Lt(t,e,n){return t==="parent"?oe(n):t==="self"?e.getRect(n):Pt(n,t)}function xe(t,e,n,o){var r=t;return y.string(r)?r=Lt(r,e,n):y.func(r)&&(r=r.apply(void 0,o)),y.element(r)&&(r=et(r)),r}function Oe(t){return t&&{x:"x"in t?t.x:t.left,y:"y"in t?t.y:t.top}}function tt(t){return!t||"x"in t&&"y"in t||((t=z({},t)).x=t.left||0,t.y=t.top||0,t.width=t.width||(t.right||0)-t.x,t.height=t.height||(t.bottom||0)-t.y),t}function Le(t,e,n){t.left&&(e.left+=n.x),t.right&&(e.right+=n.x),t.top&&(e.top+=n.y),t.bottom&&(e.bottom+=n.y),e.width=e.right-e.left,e.height=e.bottom-e.top}function we(t,e,n){var o=n&&t.options[n];return Oe(xe(o&&o.origin||t.options.origin,t,e,[t&&e]))||{x:0,y:0}}function he(t,e){var n=arguments.length>2&&arguments[2]!==void 0?arguments[2]:function(d){return!0},o=arguments.length>3?arguments[3]:void 0;if(o=o||{},y.string(t)&&t.search(" ")!==-1&&(t=At(t)),y.array(t))return t.forEach((function(d){return he(d,e,n,o)})),o;if(y.object(t)&&(e=t,t=""),y.func(e)&&n(t))o[t]=o[t]||[],o[t].push(e);else if(y.array(e))for(var r=0,i=e;r<i.length;r++){var s=i[r];he(t,s,n,o)}else if(y.object(e))for(var c in e)he(At(c).map((function(d){return"".concat(t).concat(d)})),e[c],n,o);return o}function At(t){return t.trim().split(/ +/)}var ke=function(t,e){return Math.sqrt(t*t+e*e)},Dn=["webkit","moz"];function Ae(t,e){t.__set||(t.__set={});var n=function(r){if(Dn.some((function(i){return r.indexOf(i)===0})))return 1;typeof t[r]!="function"&&r!=="__set"&&Object.defineProperty(t,r,{get:function(){return r in t.__set?t.__set[r]:t.__set[r]=e[r]},set:function(i){t.__set[r]=i},configurable:!0})};for(var o in e)n(o);return t}function Ne(t,e){t.page=t.page||{},t.page.x=e.page.x,t.page.y=e.page.y,t.client=t.client||{},t.client.x=e.client.x,t.client.y=e.client.y,t.timeStamp=e.timeStamp}function Nt(t){t.page.x=0,t.page.y=0,t.client.x=0,t.client.y=0}function jt(t){return t instanceof G.Event||t instanceof G.Touch}function je(t,e,n){return t=t||"page",(n=n||{}).x=e[t+"X"],n.y=e[t+"Y"],n}function Ft(t,e){return e=e||{x:0,y:0},Q.isOperaMobile&&jt(t)?(je("screen",t,e),e.x+=window.scrollX,e.y+=window.scrollY):je("page",t,e),e}function _e(t){return y.number(t.pointerId)?t.pointerId:t.identifier}function $n(t,e,n){var o=e.length>1?qt(e):e[0];Ft(o,t.page),(function(r,i){i=i||{},Q.isOperaMobile&&jt(r)?je("screen",r,i):je("client",r,i)})(o,t.client),t.timeStamp=n}function nt(t){var e=[];return y.array(t)?(e[0]=t[0],e[1]=t[1]):t.type==="touchend"?t.touches.length===1?(e[0]=t.touches[0],e[1]=t.changedTouches[0]):t.touches.length===0&&(e[0]=t.changedTouches[0],e[1]=t.changedTouches[1]):(e[0]=t.touches[0],e[1]=t.touches[1]),e}function qt(t){for(var e={pageX:0,pageY:0,clientX:0,clientY:0,screenX:0,screenY:0},n=0;n<t.length;n++){var o=t[n];for(var r in e)e[r]+=o[r]}for(var i in e)e[i]/=t.length;return e}function ot(t){if(!t.length)return null;var e=nt(t),n=Math.min(e[0].pageX,e[1].pageX),o=Math.min(e[0].pageY,e[1].pageY),r=Math.max(e[0].pageX,e[1].pageX),i=Math.max(e[0].pageY,e[1].pageY);return{x:n,y:o,left:n,top:o,right:r,bottom:i,width:r-n,height:i-o}}function rt(t,e){var n=e+"X",o=e+"Y",r=nt(t),i=r[0][n]-r[1][n],s=r[0][o]-r[1][o];return ke(i,s)}function it(t,e){var n=e+"X",o=e+"Y",r=nt(t),i=r[1][n]-r[0][n],s=r[1][o]-r[0][o];return 180*Math.atan2(s,i)/Math.PI}function Rt(t){return y.string(t.pointerType)?t.pointerType:y.number(t.pointerType)?[void 0,void 0,"touch","pen","mouse"][t.pointerType]:/touch/.test(t.type||"")||t instanceof G.Touch?"touch":"mouse"}function Ht(t){var e=y.func(t.composedPath)?t.composedPath():t.path;return[$t(e?e[0]:t.target),$t(t.currentTarget)]}var Fe=(function(){function t(e){a(this,t),this.immediatePropagationStopped=!1,this.propagationStopped=!1,this._interaction=e}return u(t,[{key:"preventDefault",value:function(){}},{key:"stopPropagation",value:function(){this.propagationStopped=!0}},{key:"stopImmediatePropagation",value:function(){this.immediatePropagationStopped=this.propagationStopped=!0}}]),t})();Object.defineProperty(Fe.prototype,"interaction",{get:function(){return this._interaction._proxy},set:function(){}});var Gt=function(t,e){for(var n=0;n<e.length;n++){var o=e[n];t.push(o)}return t},Bt=function(t){return Gt([],t)},Ee=function(t,e){for(var n=0;n<t.length;n++)if(e(t[n],n,t))return n;return-1},Te=function(t,e){return t[Ee(t,e)]},be=(function(t){g(n,t);var e=D(n);function n(o,r,i){var s;a(this,n),(s=e.call(this,r._interaction)).dropzone=void 0,s.dragEvent=void 0,s.relatedTarget=void 0,s.draggable=void 0,s.propagationStopped=!1,s.immediatePropagationStopped=!1;var c=i==="dragleave"?o.prev:o.cur,d=c.element,f=c.dropzone;return s.type=i,s.target=d,s.currentTarget=d,s.dropzone=f,s.dragEvent=r,s.relatedTarget=r.target,s.draggable=r.interactable,s.timeStamp=r.timeStamp,s}return u(n,[{key:"reject",value:function(){var o=this,r=this._interaction.dropState;if(this.type==="dropactivate"||this.dropzone&&r.cur.dropzone===this.dropzone&&r.cur.element===this.target)if(r.prev.dropzone=this.dropzone,r.prev.element=this.target,r.rejected=!0,r.events.enter=null,this.stopImmediatePropagation(),this.type==="dropactivate"){var i=r.activeDrops,s=Ee(i,(function(d){var f=d.dropzone,p=d.element;return f===o.dropzone&&p===o.target}));r.activeDrops.splice(s,1);var c=new n(r,this.dragEvent,"dropdeactivate");c.dropzone=this.dropzone,c.target=this.target,this.dropzone.fire(c)}else this.dropzone.fire(new n(r,this.dragEvent,"dragleave"))}},{key:"preventDefault",value:function(){}},{key:"stopPropagation",value:function(){this.propagationStopped=!0}},{key:"stopImmediatePropagation",value:function(){this.immediatePropagationStopped=this.propagationStopped=!0}}]),n})(Fe);function Yt(t,e){for(var n=0,o=t.slice();n<o.length;n++){var r=o[n],i=r.dropzone,s=r.element;e.dropzone=i,e.target=s,i.fire(e),e.propagationStopped=e.immediatePropagationStopped=!1}}function at(t,e){for(var n=(function(i,s){for(var c=[],d=0,f=i.interactables.list;d<f.length;d++){var p=f[d];if(p.options.drop.enabled){var v=p.options.drop.accept;if(!(y.element(v)&&v!==s||y.string(v)&&!se(s,v)||y.func(v)&&!v({dropzone:p,draggableElement:s})))for(var m=0,_=p.getAllElements();m<_.length;m++){var x=_[m];x!==s&&c.push({dropzone:p,element:x,rect:p.getRect(x)})}}}return c})(t,e),o=0;o<n.length;o++){var r=n[o];r.rect=r.dropzone.getRect(r.element)}return n}function Xt(t,e,n){for(var o=t.dropState,r=t.interactable,i=t.element,s=[],c=0,d=o.activeDrops;c<d.length;c++){var f=d[c],p=f.dropzone,v=f.element,m=f.rect,_=p.dropCheck(e,n,r,i,v,m);s.push(_?v:null)}var x=(function(E){for(var S,T,I,$=[],A=0;A<E.length;A++){var M=E[A],O=E[S];if(M&&A!==S)if(O){var Y=Ze(M),F=Ze(O);if(Y!==M.ownerDocument)if(F!==M.ownerDocument)if(Y!==F){$=$.length?$:Dt(O);var K=void 0;if(O instanceof G.HTMLElement&&M instanceof G.SVGElement&&!(M instanceof G.SVGSVGElement)){if(M===F)continue;K=M.ownerSVGElement}else K=M;for(var ee=Dt(K,O.ownerDocument),ae=0;ee[ae]&&ee[ae]===$[ae];)ae++;var Ue=[ee[ae-1],ee[ae],$[ae]];if(Ue[0])for(var De=Ue[0].lastChild;De;){if(De===Ue[1]){S=A,$=ee;break}if(De===Ue[2])break;De=De.previousSibling}}else I=O,(parseInt(ne(T=M).getComputedStyle(T).zIndex,10)||0)>=(parseInt(ne(I).getComputedStyle(I).zIndex,10)||0)&&(S=A);else S=A}else S=A}return S})(s);return o.activeDrops[x]||null}function st(t,e,n){var o=t.dropState,r={enter:null,leave:null,activate:null,deactivate:null,move:null,drop:null};return n.type==="dragstart"&&(r.activate=new be(o,n,"dropactivate"),r.activate.target=null,r.activate.dropzone=null),n.type==="dragend"&&(r.deactivate=new be(o,n,"dropdeactivate"),r.deactivate.target=null,r.deactivate.dropzone=null),o.rejected||(o.cur.element!==o.prev.element&&(o.prev.dropzone&&(r.leave=new be(o,n,"dragleave"),n.dragLeave=r.leave.target=o.prev.element,n.prevDropzone=r.leave.dropzone=o.prev.dropzone),o.cur.dropzone&&(r.enter=new be(o,n,"dragenter"),n.dragEnter=o.cur.element,n.dropzone=o.cur.dropzone)),n.type==="dragend"&&o.cur.dropzone&&(r.drop=new be(o,n,"drop"),n.dropzone=o.cur.dropzone,n.relatedTarget=o.cur.element),n.type==="dragmove"&&o.cur.dropzone&&(r.move=new be(o,n,"dropmove"),n.dropzone=o.cur.dropzone)),r}function ct(t,e){var n=t.dropState,o=n.activeDrops,r=n.cur,i=n.prev;e.leave&&i.dropzone.fire(e.leave),e.enter&&r.dropzone.fire(e.enter),e.move&&r.dropzone.fire(e.move),e.drop&&r.dropzone.fire(e.drop),e.deactivate&&Yt(o,e.deactivate),n.prev.dropzone=r.dropzone,n.prev.element=r.element}function Kt(t,e){var n=t.interaction,o=t.iEvent,r=t.event;if(o.type==="dragmove"||o.type==="dragend"){var i=n.dropState;e.dynamicDrop&&(i.activeDrops=at(e,n.element));var s=o,c=Xt(n,s,r);i.rejected=i.rejected&&!!c&&c.dropzone===i.cur.dropzone&&c.element===i.cur.element,i.cur.dropzone=c&&c.dropzone,i.cur.element=c&&c.element,i.events=st(n,0,s)}}var Ut={id:"actions/drop",install:function(t){var e=t.actions,n=t.interactStatic,o=t.Interactable,r=t.defaults;t.usePlugin(Mt),o.prototype.dropzone=function(i){return(function(s,c){if(y.object(c)){if(s.options.drop.enabled=c.enabled!==!1,c.listeners){var d=he(c.listeners),f=Object.keys(d).reduce((function(v,m){return v[/^(enter|leave)/.test(m)?"drag".concat(m):/^(activate|deactivate|move)/.test(m)?"drop".concat(m):m]=d[m],v}),{}),p=s.options.drop.listeners;p&&s.off(p),s.on(f),s.options.drop.listeners=f}return y.func(c.ondrop)&&s.on("drop",c.ondrop),y.func(c.ondropactivate)&&s.on("dropactivate",c.ondropactivate),y.func(c.ondropdeactivate)&&s.on("dropdeactivate",c.ondropdeactivate),y.func(c.ondragenter)&&s.on("dragenter",c.ondragenter),y.func(c.ondragleave)&&s.on("dragleave",c.ondragleave),y.func(c.ondropmove)&&s.on("dropmove",c.ondropmove),/^(pointer|center)$/.test(c.overlap)?s.options.drop.overlap=c.overlap:y.number(c.overlap)&&(s.options.drop.overlap=Math.max(Math.min(1,c.overlap),0)),"accept"in c&&(s.options.drop.accept=c.accept),"checker"in c&&(s.options.drop.checker=c.checker),s}return y.bool(c)?(s.options.drop.enabled=c,s):s.options.drop})(this,i)},o.prototype.dropCheck=function(i,s,c,d,f,p){return(function(v,m,_,x,E,S,T){var I=!1;if(!(T=T||v.getRect(S)))return!!v.options.drop.checker&&v.options.drop.checker(m,_,I,v,S,x,E);var $=v.options.drop.overlap;if($==="pointer"){var A=we(x,E,"drag"),M=Ft(m);M.x+=A.x,M.y+=A.y;var O=M.x>T.left&&M.x<T.right,Y=M.y>T.top&&M.y<T.bottom;I=O&&Y}var F=x.getRect(E);if(F&&$==="center"){var K=F.left+F.width/2,ee=F.top+F.height/2;I=K>=T.left&&K<=T.right&&ee>=T.top&&ee<=T.bottom}return F&&y.number($)&&(I=Math.max(0,Math.min(T.right,F.right)-Math.max(T.left,F.left))*Math.max(0,Math.min(T.bottom,F.bottom)-Math.max(T.top,F.top))/(F.width*F.height)>=$),v.options.drop.checker&&(I=v.options.drop.checker(m,_,I,v,S,x,E)),I})(this,i,s,c,d,f,p)},n.dynamicDrop=function(i){return y.bool(i)?(t.dynamicDrop=i,n):t.dynamicDrop},z(e.phaselessTypes,{dragenter:!0,dragleave:!0,dropactivate:!0,dropdeactivate:!0,dropmove:!0,drop:!0}),e.methodDict.drop="dropzone",t.dynamicDrop=!1,r.actions.drop=Ut.defaults},listeners:{"interactions:before-action-start":function(t){var e=t.interaction;e.prepared.name==="drag"&&(e.dropState={cur:{dropzone:null,element:null},prev:{dropzone:null,element:null},rejected:null,events:null,activeDrops:[]})},"interactions:after-action-start":function(t,e){var n=t.interaction,o=(t.event,t.iEvent);if(n.prepared.name==="drag"){var r=n.dropState;r.activeDrops=[],r.events={},r.activeDrops=at(e,n.element),r.events=st(n,0,o),r.events.activate&&(Yt(r.activeDrops,r.events.activate),e.fire("actions/drop:start",{interaction:n,dragEvent:o}))}},"interactions:action-move":Kt,"interactions:after-action-move":function(t,e){var n=t.interaction,o=t.iEvent;if(n.prepared.name==="drag"){var r=n.dropState;ct(n,r.events),e.fire("actions/drop:move",{interaction:n,dragEvent:o}),r.events={}}},"interactions:action-end":function(t,e){if(t.interaction.prepared.name==="drag"){var n=t.interaction,o=t.iEvent;Kt(t,e),ct(n,n.dropState.events),e.fire("actions/drop:end",{interaction:n,dragEvent:o})}},"interactions:stop":function(t){var e=t.interaction;if(e.prepared.name==="drag"){var n=e.dropState;n&&(n.activeDrops=null,n.events=null,n.cur.dropzone=null,n.cur.element=null,n.prev.dropzone=null,n.prev.element=null,n.rejected=!1)}}},getActiveDrops:at,getDrop:Xt,getDropEvents:st,fireDropEvents:ct,filterEventType:function(t){return t.search("drag")===0||t.search("drop")===0},defaults:{enabled:!1,accept:null,overlap:"pointer"}},Cn=Ut;function lt(t){var e=t.interaction,n=t.iEvent,o=t.phase;if(e.prepared.name==="gesture"){var r=e.pointers.map((function(f){return f.pointer})),i=o==="start",s=o==="end",c=e.interactable.options.deltaSource;if(n.touches=[r[0],r[1]],i)n.distance=rt(r,c),n.box=ot(r),n.scale=1,n.ds=0,n.angle=it(r,c),n.da=0,e.gesture.startDistance=n.distance,e.gesture.startAngle=n.angle;else if(s||e.pointers.length<2){var d=e.prevEvent;n.distance=d.distance,n.box=d.box,n.scale=d.scale,n.ds=0,n.angle=d.angle,n.da=0}else n.distance=rt(r,c),n.box=ot(r),n.scale=n.distance/e.gesture.startDistance,n.angle=it(r,c),n.ds=n.scale-e.gesture.scale,n.da=n.angle-e.gesture.angle;e.gesture.distance=n.distance,e.gesture.angle=n.angle,y.number(n.scale)&&n.scale!==1/0&&!isNaN(n.scale)&&(e.gesture.scale=n.scale)}}var dt={id:"actions/gesture",before:["actions/drag","actions/resize"],install:function(t){var e=t.actions,n=t.Interactable,o=t.defaults;n.prototype.gesturable=function(r){return y.object(r)?(this.options.gesture.enabled=r.enabled!==!1,this.setPerAction("gesture",r),this.setOnEvents("gesture",r),this):y.bool(r)?(this.options.gesture.enabled=r,this):this.options.gesture},e.map.gesture=dt,e.methodDict.gesture="gesturable",o.actions.gesture=dt.defaults},listeners:{"interactions:action-start":lt,"interactions:action-move":lt,"interactions:action-end":lt,"interactions:new":function(t){t.interaction.gesture={angle:0,distance:0,scale:1,startAngle:0,startDistance:0}},"auto-start:check":function(t){if(!(t.interaction.pointers.length<2)){var e=t.interactable.options.gesture;if(e&&e.enabled)return t.action={name:"gesture"},!1}}},defaults:{},getCursor:function(){return""},filterEventType:function(t){return t.search("gesture")===0}},On=dt;function Ln(t,e,n,o,r,i,s){if(!e)return!1;if(e===!0){var c=y.number(i.width)?i.width:i.right-i.left,d=y.number(i.height)?i.height:i.bottom-i.top;if(s=Math.min(s,Math.abs((t==="left"||t==="right"?c:d)/2)),c<0&&(t==="left"?t="right":t==="right"&&(t="left")),d<0&&(t==="top"?t="bottom":t==="bottom"&&(t="top")),t==="left"){var f=c>=0?i.left:i.right;return n.x<f+s}if(t==="top"){var p=d>=0?i.top:i.bottom;return n.y<p+s}if(t==="right")return n.x>(c>=0?i.right:i.left)-s;if(t==="bottom")return n.y>(d>=0?i.bottom:i.top)-s}return!!y.element(o)&&(y.element(e)?e===o:Je(o,e,r))}function Vt(t){var e=t.iEvent,n=t.interaction;if(n.prepared.name==="resize"&&n.resizeAxes){var o=e;n.interactable.options.resize.square?(n.resizeAxes==="y"?o.delta.x=o.delta.y:o.delta.y=o.delta.x,o.axes="xy"):(o.axes=n.resizeAxes,n.resizeAxes==="x"?o.delta.y=0:n.resizeAxes==="y"&&(o.delta.x=0))}}var re,fe,ie={id:"actions/resize",before:["actions/drag"],install:function(t){var e=t.actions,n=t.browser,o=t.Interactable,r=t.defaults;ie.cursors=(function(i){return i.isIe9?{x:"e-resize",y:"s-resize",xy:"se-resize",top:"n-resize",left:"w-resize",bottom:"s-resize",right:"e-resize",topleft:"se-resize",bottomright:"se-resize",topright:"ne-resize",bottomleft:"ne-resize"}:{x:"ew-resize",y:"ns-resize",xy:"nwse-resize",top:"ns-resize",left:"ew-resize",bottom:"ns-resize",right:"ew-resize",topleft:"nwse-resize",bottomright:"nwse-resize",topright:"nesw-resize",bottomleft:"nesw-resize"}})(n),ie.defaultMargin=n.supportsTouch||n.supportsPointerEvent?20:10,o.prototype.resizable=function(i){return(function(s,c,d){return y.object(c)?(s.options.resize.enabled=c.enabled!==!1,s.setPerAction("resize",c),s.setOnEvents("resize",c),y.string(c.axis)&&/^x$|^y$|^xy$/.test(c.axis)?s.options.resize.axis=c.axis:c.axis===null&&(s.options.resize.axis=d.defaults.actions.resize.axis),y.bool(c.preserveAspectRatio)?s.options.resize.preserveAspectRatio=c.preserveAspectRatio:y.bool(c.square)&&(s.options.resize.square=c.square),s):y.bool(c)?(s.options.resize.enabled=c,s):s.options.resize})(this,i,t)},e.map.resize=ie,e.methodDict.resize="resizable",r.actions.resize=ie.defaults},listeners:{"interactions:new":function(t){t.interaction.resizeAxes="xy"},"interactions:action-start":function(t){(function(e){var n=e.iEvent,o=e.interaction;if(o.prepared.name==="resize"&&o.prepared.edges){var r=n,i=o.rect;o._rects={start:z({},i),corrected:z({},i),previous:z({},i),delta:{left:0,right:0,width:0,top:0,bottom:0,height:0}},r.edges=o.prepared.edges,r.rect=o._rects.corrected,r.deltaRect=o._rects.delta}})(t),Vt(t)},"interactions:action-move":function(t){(function(e){var n=e.iEvent,o=e.interaction;if(o.prepared.name==="resize"&&o.prepared.edges){var r=n,i=o.interactable.options.resize.invert,s=i==="reposition"||i==="negate",c=o.rect,d=o._rects,f=d.start,p=d.corrected,v=d.delta,m=d.previous;if(z(m,p),s){if(z(p,c),i==="reposition"){if(p.top>p.bottom){var _=p.top;p.top=p.bottom,p.bottom=_}if(p.left>p.right){var x=p.left;p.left=p.right,p.right=x}}}else p.top=Math.min(c.top,f.bottom),p.bottom=Math.max(c.bottom,f.top),p.left=Math.min(c.left,f.right),p.right=Math.max(c.right,f.left);for(var E in p.width=p.right-p.left,p.height=p.bottom-p.top,p)v[E]=p[E]-m[E];r.edges=o.prepared.edges,r.rect=p,r.deltaRect=v}})(t),Vt(t)},"interactions:action-end":function(t){var e=t.iEvent,n=t.interaction;if(n.prepared.name==="resize"&&n.prepared.edges){var o=e;o.edges=n.prepared.edges,o.rect=n._rects.corrected,o.deltaRect=n._rects.delta}},"auto-start:check":function(t){var e=t.interaction,n=t.interactable,o=t.element,r=t.rect,i=t.buttons;if(r){var s=z({},e.coords.cur.page),c=n.options.resize;if(c&&c.enabled&&(!e.pointerIsDown||!/mouse|pointer/.test(e.pointerType)||(i&c.mouseButtons)!=0)){if(y.object(c.edges)){var d={left:!1,right:!1,top:!1,bottom:!1};for(var f in d)d[f]=Ln(f,c.edges[f],s,e._latestPointer.eventTarget,o,r,c.margin||ie.defaultMargin);d.left=d.left&&!d.right,d.top=d.top&&!d.bottom,(d.left||d.right||d.top||d.bottom)&&(t.action={name:"resize",edges:d})}else{var p=c.axis!=="y"&&s.x>r.right-ie.defaultMargin,v=c.axis!=="x"&&s.y>r.bottom-ie.defaultMargin;(p||v)&&(t.action={name:"resize",axes:(p?"x":"")+(v?"y":"")})}return!t.action&&void 0}}}},defaults:{square:!1,preserveAspectRatio:!1,axis:"xy",margin:NaN,edges:null,invert:"none"},cursors:null,getCursor:function(t){var e=t.edges,n=t.axis,o=t.name,r=ie.cursors,i=null;if(n)i=r[o+n];else if(e){for(var s="",c=0,d=["top","bottom","left","right"];c<d.length;c++){var f=d[c];e[f]&&(s+=f)}i=r[s]}return i},filterEventType:function(t){return t.search("resize")===0},defaultMargin:null},An=ie,Nn={id:"actions",install:function(t){t.usePlugin(On),t.usePlugin(An),t.usePlugin(Mt),t.usePlugin(Cn)}},Wt=0,ce={request:function(t){return re(t)},cancel:function(t){return fe(t)},init:function(t){if(re=t.requestAnimationFrame,fe=t.cancelAnimationFrame,!re)for(var e=["ms","moz","webkit","o"],n=0;n<e.length;n++){var o=e[n];re=t["".concat(o,"RequestAnimationFrame")],fe=t["".concat(o,"CancelAnimationFrame")]||t["".concat(o,"CancelRequestAnimationFrame")]}re=re&&re.bind(t),fe=fe&&fe.bind(t),re||(re=function(r){var i=Date.now(),s=Math.max(0,16-(i-Wt)),c=t.setTimeout((function(){r(i+s)}),s);return Wt=i+s,c},fe=function(r){return clearTimeout(r)})}},P={defaults:{enabled:!1,margin:60,container:null,speed:300},now:Date.now,interaction:null,i:0,x:0,y:0,isScrolling:!1,prevTime:0,margin:0,speed:0,start:function(t){P.isScrolling=!0,ce.cancel(P.i),t.autoScroll=P,P.interaction=t,P.prevTime=P.now(),P.i=ce.request(P.scroll)},stop:function(){P.isScrolling=!1,P.interaction&&(P.interaction.autoScroll=null),ce.cancel(P.i)},scroll:function(){var t=P.interaction,e=t.interactable,n=t.element,o=t.prepared.name,r=e.options[o].autoScroll,i=Zt(r.container,e,n),s=P.now(),c=(s-P.prevTime)/1e3,d=r.speed*c;if(d>=1){var f={x:P.x*d,y:P.y*d};if(f.x||f.y){var p=Jt(i);y.window(i)?i.scrollBy(f.x,f.y):i&&(i.scrollLeft+=f.x,i.scrollTop+=f.y);var v=Jt(i),m={x:v.x-p.x,y:v.y-p.y};(m.x||m.y)&&e.fire({type:"autoscroll",target:n,interactable:e,delta:m,interaction:t,container:i})}P.prevTime=s}P.isScrolling&&(ce.cancel(P.i),P.i=ce.request(P.scroll))},check:function(t,e){var n;return(n=t.options[e].autoScroll)==null?void 0:n.enabled},onInteractionMove:function(t){var e=t.interaction,n=t.pointer;if(e.interacting()&&P.check(e.interactable,e.prepared.name))if(e.simulation)P.x=P.y=0;else{var o,r,i,s,c=e.interactable,d=e.element,f=e.prepared.name,p=c.options[f].autoScroll,v=Zt(p.container,c,d);if(y.window(v))s=n.clientX<P.margin,o=n.clientY<P.margin,r=n.clientX>v.innerWidth-P.margin,i=n.clientY>v.innerHeight-P.margin;else{var m=Qe(v);s=n.clientX<m.left+P.margin,o=n.clientY<m.top+P.margin,r=n.clientX>m.right-P.margin,i=n.clientY>m.bottom-P.margin}P.x=r?1:s?-1:0,P.y=i?1:o?-1:0,P.isScrolling||(P.margin=p.margin,P.speed=p.speed,P.start(e))}}};function Zt(t,e,n){return(y.string(t)?Lt(t,e,n):t)||ne(n)}function Jt(t){return y.window(t)&&(t=window.document.body),{x:t.scrollLeft,y:t.scrollTop}}var jn={id:"auto-scroll",install:function(t){var e=t.defaults,n=t.actions;t.autoScroll=P,P.now=function(){return t.now()},n.phaselessTypes.autoscroll=!0,e.perAction.autoScroll=P.defaults},listeners:{"interactions:new":function(t){t.interaction.autoScroll=null},"interactions:destroy":function(t){t.interaction.autoScroll=null,P.stop(),P.interaction&&(P.interaction=null)},"interactions:stop":P.stop,"interactions:action-move":function(t){return P.onInteractionMove(t)}}},Fn=jn;function Se(t,e){var n=!1;return function(){return n||(H.console.warn(e),n=!0),t.apply(this,arguments)}}function pt(t,e){return t.name=e.name,t.axis=e.axis,t.edges=e.edges,t}function qn(t){return y.bool(t)?(this.options.styleCursor=t,this):t===null?(delete this.options.styleCursor,this):this.options.styleCursor}function Rn(t){return y.func(t)?(this.options.actionChecker=t,this):t===null?(delete this.options.actionChecker,this):this.options.actionChecker}var Hn={id:"auto-start/interactableMethods",install:function(t){var e=t.Interactable;e.prototype.getAction=function(n,o,r,i){var s=(function(c,d,f,p,v){var m=c.getRect(p),_=d.buttons||{0:1,1:4,3:8,4:16}[d.button],x={action:null,interactable:c,interaction:f,element:p,rect:m,buttons:_};return v.fire("auto-start:check",x),x.action})(this,o,r,i,t);return this.options.actionChecker?this.options.actionChecker(n,o,s,this,i,r):s},e.prototype.ignoreFrom=Se((function(n){return this._backCompatOption("ignoreFrom",n)}),"Interactable.ignoreFrom() has been deprecated. Use Interactble.draggable({ignoreFrom: newValue})."),e.prototype.allowFrom=Se((function(n){return this._backCompatOption("allowFrom",n)}),"Interactable.allowFrom() has been deprecated. Use Interactble.draggable({allowFrom: newValue})."),e.prototype.actionChecker=Rn,e.prototype.styleCursor=qn}};function Qt(t,e,n,o,r){return e.testIgnoreAllow(e.options[t.name],n,o)&&e.options[t.name].enabled&&qe(e,n,t,r)?t:null}function Gn(t,e,n,o,r,i,s){for(var c=0,d=o.length;c<d;c++){var f=o[c],p=r[c],v=f.getAction(e,n,t,p);if(v){var m=Qt(v,f,p,i,s);if(m)return{action:m,interactable:f,element:p}}}return{action:null,interactable:null,element:null}}function en(t,e,n,o,r){var i=[],s=[],c=o;function d(p){i.push(p),s.push(c)}for(;y.element(c);){i=[],s=[],r.interactables.forEachMatch(c,d);var f=Gn(t,e,n,i,s,o,r);if(f.action&&!f.interactable.options[f.action.name].manualStart)return f;c=oe(c)}return{action:null,interactable:null,element:null}}function tn(t,e,n){var o=e.action,r=e.interactable,i=e.element;o=o||{name:null},t.interactable=r,t.element=i,pt(t.prepared,o),t.rect=r&&o.name?r.getRect(i):null,on(t,n),n.fire("autoStart:prepared",{interaction:t})}function qe(t,e,n,o){var r=t.options,i=r[n.name].max,s=r[n.name].maxPerElement,c=o.autoStart.maxInteractions,d=0,f=0,p=0;if(!(i&&s&&c))return!1;for(var v=0,m=o.interactions.list;v<m.length;v++){var _=m[v],x=_.prepared.name;if(_.interacting()&&(++d>=c||_.interactable===t&&((f+=x===n.name?1:0)>=i||_.element===e&&(p++,x===n.name&&p>=s))))return!1}return c>0}function nn(t,e){return y.number(t)?(e.autoStart.maxInteractions=t,this):e.autoStart.maxInteractions}function ut(t,e,n){var o=n.autoStart.cursorElement;o&&o!==t&&(o.style.cursor=""),t.ownerDocument.documentElement.style.cursor=e,t.style.cursor=e,n.autoStart.cursorElement=e?t:null}function on(t,e){var n=t.interactable,o=t.element,r=t.prepared;if(t.pointerType==="mouse"&&n&&n.options.styleCursor){var i="";if(r.name){var s=n.options[r.name].cursorChecker;i=y.func(s)?s(r,n,o,t._interacting):e.actions.map[r.name].getCursor(r)}ut(t.element,i||"",e)}else e.autoStart.cursorElement&&ut(e.autoStart.cursorElement,"",e)}var Bn={id:"auto-start/base",before:["actions"],install:function(t){var e=t.interactStatic,n=t.defaults;t.usePlugin(Hn),n.base.actionChecker=null,n.base.styleCursor=!0,z(n.perAction,{manualStart:!1,max:1/0,maxPerElement:1,allowFrom:null,ignoreFrom:null,mouseButtons:1}),e.maxInteractions=function(o){return nn(o,t)},t.autoStart={maxInteractions:1/0,withinInteractionLimit:qe,cursorElement:null}},listeners:{"interactions:down":function(t,e){var n=t.interaction,o=t.pointer,r=t.event,i=t.eventTarget;n.interacting()||tn(n,en(n,o,r,i,e),e)},"interactions:move":function(t,e){(function(n,o){var r=n.interaction,i=n.pointer,s=n.event,c=n.eventTarget;r.pointerType!=="mouse"||r.pointerIsDown||r.interacting()||tn(r,en(r,i,s,c,o),o)})(t,e),(function(n,o){var r=n.interaction;if(r.pointerIsDown&&!r.interacting()&&r.pointerWasMoved&&r.prepared.name){o.fire("autoStart:before-start",n);var i=r.interactable,s=r.prepared.name;s&&i&&(i.options[s].manualStart||!qe(i,r.element,r.prepared,o)?r.stop():(r.start(r.prepared,i,r.element),on(r,o)))}})(t,e)},"interactions:stop":function(t,e){var n=t.interaction,o=n.interactable;o&&o.options.styleCursor&&ut(n.element,"",e)}},maxInteractions:nn,withinInteractionLimit:qe,validateAction:Qt},ht=Bn,Yn={id:"auto-start/dragAxis",listeners:{"autoStart:before-start":function(t,e){var n=t.interaction,o=t.eventTarget,r=t.dx,i=t.dy;if(n.prepared.name==="drag"){var s=Math.abs(r),c=Math.abs(i),d=n.interactable.options.drag,f=d.startAxis,p=s>c?"x":s<c?"y":"xy";if(n.prepared.axis=d.lockAxis==="start"?p[0]:d.lockAxis,p!=="xy"&&f!=="xy"&&f!==p){n.prepared.name=null;for(var v=o,m=function(x){if(x!==n.interactable){var E=n.interactable.options.drag;if(!E.manualStart&&x.testIgnoreAllow(E,v,o)){var S=x.getAction(n.downPointer,n.downEvent,n,v);if(S&&S.name==="drag"&&(function(T,I){if(!I)return!1;var $=I.options.drag.startAxis;return T==="xy"||$==="xy"||$===T})(p,x)&&ht.validateAction(S,x,v,o,e))return x}}};y.element(v);){var _=e.interactables.forEachMatch(v,m);if(_){n.prepared.name="drag",n.interactable=_,n.element=v;break}v=oe(v)}}}}}};function ft(t){var e=t.prepared&&t.prepared.name;if(!e)return null;var n=t.interactable.options;return n[e].hold||n[e].delay}var Xn={id:"auto-start/hold",install:function(t){var e=t.defaults;t.usePlugin(ht),e.perAction.hold=0,e.perAction.delay=0},listeners:{"interactions:new":function(t){t.interaction.autoStartHoldTimer=null},"autoStart:prepared":function(t){var e=t.interaction,n=ft(e);n>0&&(e.autoStartHoldTimer=setTimeout((function(){e.start(e.prepared,e.interactable,e.element)}),n))},"interactions:move":function(t){var e=t.interaction,n=t.duplicate;e.autoStartHoldTimer&&e.pointerWasMoved&&!n&&(clearTimeout(e.autoStartHoldTimer),e.autoStartHoldTimer=null)},"autoStart:before-start":function(t){var e=t.interaction;ft(e)>0&&(e.prepared.name=null)}},getHoldDuration:ft},Kn=Xn,Un={id:"auto-start",install:function(t){t.usePlugin(ht),t.usePlugin(Kn),t.usePlugin(Yn)}},Vn=function(t){return/^(always|never|auto)$/.test(t)?(this.options.preventDefault=t,this):y.bool(t)?(this.options.preventDefault=t?"always":"never",this):this.options.preventDefault};function Wn(t){var e=t.interaction,n=t.event;e.interactable&&e.interactable.checkAndPreventDefault(n)}var rn={id:"core/interactablePreventDefault",install:function(t){var e=t.Interactable;e.prototype.preventDefault=Vn,e.prototype.checkAndPreventDefault=function(n){return(function(o,r,i){var s=o.options.preventDefault;if(s!=="never")if(s!=="always"){if(r.events.supportsPassive&&/^touch(start|move)$/.test(i.type)){var c=ne(i.target).document,d=r.getDocOptions(c);if(!d||!d.events||d.events.passive!==!1)return}/^(mouse|pointer|touch)*(down|start)/i.test(i.type)||y.element(i.target)&&se(i.target,"input,select,textarea,[contenteditable=true],[contenteditable=true] *")||i.preventDefault()}else i.preventDefault()})(this,t,n)},t.interactions.docEvents.push({type:"dragstart",listener:function(n){for(var o=0,r=t.interactions.list;o<r.length;o++){var i=r[o];if(i.element&&(i.element===n.target||ue(i.element,n.target)))return void i.interactable.checkAndPreventDefault(n)}}})},listeners:["down","move","up","cancel"].reduce((function(t,e){return t["interactions:".concat(e)]=Wn,t}),{})};function Re(t,e){if(e.phaselessTypes[t])return!0;for(var n in e.map)if(t.indexOf(n)===0&&t.substr(n.length)in e.phases)return!0;return!1}function ye(t){var e={};for(var n in t){var o=t[n];y.plainObject(o)?e[n]=ye(o):y.array(o)?e[n]=Bt(o):e[n]=o}return e}var gt=(function(){function t(e){a(this,t),this.states=[],this.startOffset={left:0,right:0,top:0,bottom:0},this.startDelta=void 0,this.result=void 0,this.endResult=void 0,this.startEdges=void 0,this.edges=void 0,this.interaction=void 0,this.interaction=e,this.result=He(),this.edges={left:!1,right:!1,top:!1,bottom:!1}}return u(t,[{key:"start",value:function(e,n){var o,r,i=e.phase,s=this.interaction,c=(function(f){var p=f.interactable.options[f.prepared.name],v=p.modifiers;return v&&v.length?v:["snap","snapSize","snapEdges","restrict","restrictEdges","restrictSize"].map((function(m){var _=p[m];return _&&_.enabled&&{options:_,methods:_._methods}})).filter((function(m){return!!m}))})(s);this.prepareStates(c),this.startEdges=z({},s.edges),this.edges=z({},this.startEdges),this.startOffset=(o=s.rect,r=n,o?{left:r.x-o.left,top:r.y-o.top,right:o.right-r.x,bottom:o.bottom-r.y}:{left:0,top:0,right:0,bottom:0}),this.startDelta={x:0,y:0};var d=this.fillArg({phase:i,pageCoords:n,preEnd:!1});return this.result=He(),this.startAll(d),this.result=this.setAll(d)}},{key:"fillArg",value:function(e){var n=this.interaction;return e.interaction=n,e.interactable=n.interactable,e.element=n.element,e.rect||(e.rect=n.rect),e.edges||(e.edges=this.startEdges),e.startOffset=this.startOffset,e}},{key:"startAll",value:function(e){for(var n=0,o=this.states;n<o.length;n++){var r=o[n];r.methods.start&&(e.state=r,r.methods.start(e))}}},{key:"setAll",value:function(e){var n=e.phase,o=e.preEnd,r=e.skipModifiers,i=e.rect,s=e.edges;e.coords=z({},e.pageCoords),e.rect=z({},i),e.edges=z({},s);for(var c=r?this.states.slice(r):this.states,d=He(e.coords,e.rect),f=0;f<c.length;f++){var p,v=c[f],m=v.options,_=z({},e.coords),x=null;(p=v.methods)!=null&&p.set&&this.shouldDo(m,o,n)&&(e.state=v,x=v.methods.set(e),Le(e.edges,e.rect,{x:e.coords.x-_.x,y:e.coords.y-_.y})),d.eventProps.push(x)}z(this.edges,e.edges),d.delta.x=e.coords.x-e.pageCoords.x,d.delta.y=e.coords.y-e.pageCoords.y,d.rectDelta.left=e.rect.left-i.left,d.rectDelta.right=e.rect.right-i.right,d.rectDelta.top=e.rect.top-i.top,d.rectDelta.bottom=e.rect.bottom-i.bottom;var E=this.result.coords,S=this.result.rect;if(E&&S){var T=d.rect.left!==S.left||d.rect.right!==S.right||d.rect.top!==S.top||d.rect.bottom!==S.bottom;d.changed=T||E.x!==d.coords.x||E.y!==d.coords.y}return d}},{key:"applyToInteraction",value:function(e){var n=this.interaction,o=e.phase,r=n.coords.cur,i=n.coords.start,s=this.result,c=this.startDelta,d=s.delta;o==="start"&&z(this.startDelta,s.delta);for(var f=0,p=[[i,c],[r,d]];f<p.length;f++){var v=p[f],m=v[0],_=v[1];m.page.x+=_.x,m.page.y+=_.y,m.client.x+=_.x,m.client.y+=_.y}var x=this.result.rectDelta,E=e.rect||n.rect;E.left+=x.left,E.right+=x.right,E.top+=x.top,E.bottom+=x.bottom,E.width=E.right-E.left,E.height=E.bottom-E.top}},{key:"setAndApply",value:function(e){var n=this.interaction,o=e.phase,r=e.preEnd,i=e.skipModifiers,s=this.setAll(this.fillArg({preEnd:r,phase:o,pageCoords:e.modifiedCoords||n.coords.cur.page}));if(this.result=s,!s.changed&&(!i||i<this.states.length)&&n.interacting())return!1;if(e.modifiedCoords){var c=n.coords.cur.page,d={x:e.modifiedCoords.x-c.x,y:e.modifiedCoords.y-c.y};s.coords.x+=d.x,s.coords.y+=d.y,s.delta.x+=d.x,s.delta.y+=d.y}this.applyToInteraction(e)}},{key:"beforeEnd",value:function(e){var n=e.interaction,o=e.event,r=this.states;if(r&&r.length){for(var i=!1,s=0;s<r.length;s++){var c=r[s];e.state=c;var d=c.options,f=c.methods,p=f.beforeEnd&&f.beforeEnd(e);if(p)return this.endResult=p,!1;i=i||!i&&this.shouldDo(d,!0,e.phase,!0)}i&&n.move({event:o,preEnd:!0})}}},{key:"stop",value:function(e){var n=e.interaction;if(this.states&&this.states.length){var o=z({states:this.states,interactable:n.interactable,element:n.element,rect:null},e);this.fillArg(o);for(var r=0,i=this.states;r<i.length;r++){var s=i[r];o.state=s,s.methods.stop&&s.methods.stop(o)}this.states=null,this.endResult=null}}},{key:"prepareStates",value:function(e){this.states=[];for(var n=0;n<e.length;n++){var o=e[n],r=o.options,i=o.methods,s=o.name;this.states.push({options:r,methods:i,index:n,name:s})}return this.states}},{key:"restoreInteractionCoords",value:function(e){var n=e.interaction,o=n.coords,r=n.rect,i=n.modification;if(i.result){for(var s=i.startDelta,c=i.result,d=c.delta,f=c.rectDelta,p=0,v=[[o.start,s],[o.cur,d]];p<v.length;p++){var m=v[p],_=m[0],x=m[1];_.page.x-=x.x,_.page.y-=x.y,_.client.x-=x.x,_.client.y-=x.y}r.left-=f.left,r.right-=f.right,r.top-=f.top,r.bottom-=f.bottom}}},{key:"shouldDo",value:function(e,n,o,r){return!(!e||e.enabled===!1||r&&!e.endOnly||e.endOnly&&!n||o==="start"&&!e.setStart)}},{key:"copyFrom",value:function(e){this.startOffset=e.startOffset,this.startDelta=e.startDelta,this.startEdges=e.startEdges,this.edges=e.edges,this.states=e.states.map((function(n){return ye(n)})),this.result=He(z({},e.result.coords),z({},e.result.rect))}},{key:"destroy",value:function(){for(var e in this)this[e]=null}}]),t})();function He(t,e){return{rect:e,coords:t,delta:{x:0,y:0},rectDelta:{left:0,right:0,top:0,bottom:0},eventProps:[],changed:!0}}function le(t,e){var n=t.defaults,o={start:t.start,set:t.set,beforeEnd:t.beforeEnd,stop:t.stop},r=function(i){var s=i||{};for(var c in s.enabled=s.enabled!==!1,n)c in s||(s[c]=n[c]);var d={options:s,methods:o,name:e,enable:function(){return s.enabled=!0,d},disable:function(){return s.enabled=!1,d}};return d};return e&&typeof e=="string"&&(r._defaults=n,r._methods=o),r}function Ie(t){var e=t.iEvent,n=t.interaction.modification.result;n&&(e.modifiers=n.eventProps)}var Zn={id:"modifiers/base",before:["actions"],install:function(t){t.defaults.perAction.modifiers=[]},listeners:{"interactions:new":function(t){var e=t.interaction;e.modification=new gt(e)},"interactions:before-action-start":function(t){var e=t.interaction,n=t.interaction.modification;n.start(t,e.coords.start.page),e.edges=n.edges,n.applyToInteraction(t)},"interactions:before-action-move":function(t){var e=t.interaction,n=e.modification,o=n.setAndApply(t);return e.edges=n.edges,o},"interactions:before-action-end":function(t){var e=t.interaction,n=e.modification,o=n.beforeEnd(t);return e.edges=n.startEdges,o},"interactions:action-start":Ie,"interactions:action-move":Ie,"interactions:action-end":Ie,"interactions:after-action-start":function(t){return t.interaction.modification.restoreInteractionCoords(t)},"interactions:after-action-move":function(t){return t.interaction.modification.restoreInteractionCoords(t)},"interactions:stop":function(t){return t.interaction.modification.stop(t)}}},an=Zn,sn={base:{preventDefault:"auto",deltaSource:"page"},perAction:{enabled:!1,origin:{x:0,y:0}},actions:{}},vt=(function(t){g(n,t);var e=D(n);function n(o,r,i,s,c,d,f){var p;a(this,n),(p=e.call(this,o)).relatedTarget=null,p.screenX=void 0,p.screenY=void 0,p.button=void 0,p.buttons=void 0,p.ctrlKey=void 0,p.shiftKey=void 0,p.altKey=void 0,p.metaKey=void 0,p.page=void 0,p.client=void 0,p.delta=void 0,p.rect=void 0,p.x0=void 0,p.y0=void 0,p.t0=void 0,p.dt=void 0,p.duration=void 0,p.clientX0=void 0,p.clientY0=void 0,p.velocity=void 0,p.speed=void 0,p.swipe=void 0,p.axes=void 0,p.preEnd=void 0,c=c||o.element;var v=o.interactable,m=(v&&v.options||sn).deltaSource,_=we(v,c,i),x=s==="start",E=s==="end",S=x?k(p):o.prevEvent,T=x?o.coords.start:E?{page:S.page,client:S.client,timeStamp:o.coords.cur.timeStamp}:o.coords.cur;return p.page=z({},T.page),p.client=z({},T.client),p.rect=z({},o.rect),p.timeStamp=T.timeStamp,E||(p.page.x-=_.x,p.page.y-=_.y,p.client.x-=_.x,p.client.y-=_.y),p.ctrlKey=r.ctrlKey,p.altKey=r.altKey,p.shiftKey=r.shiftKey,p.metaKey=r.metaKey,p.button=r.button,p.buttons=r.buttons,p.target=c,p.currentTarget=c,p.preEnd=d,p.type=f||i+(s||""),p.interactable=v,p.t0=x?o.pointers[o.pointers.length-1].downTime:S.t0,p.x0=o.coords.start.page.x-_.x,p.y0=o.coords.start.page.y-_.y,p.clientX0=o.coords.start.client.x-_.x,p.clientY0=o.coords.start.client.y-_.y,p.delta=x||E?{x:0,y:0}:{x:p[m].x-S[m].x,y:p[m].y-S[m].y},p.dt=o.coords.delta.timeStamp,p.duration=p.timeStamp-p.t0,p.velocity=z({},o.coords.velocity[m]),p.speed=ke(p.velocity.x,p.velocity.y),p.swipe=E||s==="inertiastart"?p.getSwipe():null,p}return u(n,[{key:"getSwipe",value:function(){var o=this._interaction;if(o.prevEvent.speed<600||this.timeStamp-o.prevEvent.timeStamp>150)return null;var r=180*Math.atan2(o.prevEvent.velocityY,o.prevEvent.velocityX)/Math.PI;r<0&&(r+=360);var i=112.5<=r&&r<247.5,s=202.5<=r&&r<337.5;return{up:s,down:!s&&22.5<=r&&r<157.5,left:i,right:!i&&(292.5<=r||r<67.5),angle:r,speed:o.prevEvent.speed,velocity:{x:o.prevEvent.velocityX,y:o.prevEvent.velocityY}}}},{key:"preventDefault",value:function(){}},{key:"stopImmediatePropagation",value:function(){this.immediatePropagationStopped=this.propagationStopped=!0}},{key:"stopPropagation",value:function(){this.propagationStopped=!0}}]),n})(Fe);Object.defineProperties(vt.prototype,{pageX:{get:function(){return this.page.x},set:function(t){this.page.x=t}},pageY:{get:function(){return this.page.y},set:function(t){this.page.y=t}},clientX:{get:function(){return this.client.x},set:function(t){this.client.x=t}},clientY:{get:function(){return this.client.y},set:function(t){this.client.y=t}},dx:{get:function(){return this.delta.x},set:function(t){this.delta.x=t}},dy:{get:function(){return this.delta.y},set:function(t){this.delta.y=t}},velocityX:{get:function(){return this.velocity.x},set:function(t){this.velocity.x=t}},velocityY:{get:function(){return this.velocity.y},set:function(t){this.velocity.y=t}}});var Jn=u((function t(e,n,o,r,i){a(this,t),this.id=void 0,this.pointer=void 0,this.event=void 0,this.downTime=void 0,this.downTarget=void 0,this.id=e,this.pointer=n,this.event=o,this.downTime=r,this.downTarget=i})),Qn=(function(t){return t.interactable="",t.element="",t.prepared="",t.pointerIsDown="",t.pointerWasMoved="",t._proxy="",t})({}),cn=(function(t){return t.start="",t.move="",t.end="",t.stop="",t.interacting="",t})({}),eo=0,to=(function(){function t(e){var n=this,o=e.pointerType,r=e.scopeFire;a(this,t),this.interactable=null,this.element=null,this.rect=null,this._rects=void 0,this.edges=null,this._scopeFire=void 0,this.prepared={name:null,axis:null,edges:null},this.pointerType=void 0,this.pointers=[],this.downEvent=null,this.downPointer={},this._latestPointer={pointer:null,event:null,eventTarget:null},this.prevEvent=null,this.pointerIsDown=!1,this.pointerWasMoved=!1,this._interacting=!1,this._ending=!1,this._stopped=!0,this._proxy=void 0,this.simulation=null,this.doMove=Se((function(p){this.move(p)}),"The interaction.doMove() method has been renamed to interaction.move()"),this.coords={start:{page:{x:0,y:0},client:{x:0,y:0},timeStamp:0},prev:{page:{x:0,y:0},client:{x:0,y:0},timeStamp:0},cur:{page:{x:0,y:0},client:{x:0,y:0},timeStamp:0},delta:{page:{x:0,y:0},client:{x:0,y:0},timeStamp:0},velocity:{page:{x:0,y:0},client:{x:0,y:0},timeStamp:0}},this._id=eo++,this._scopeFire=r,this.pointerType=o;var i=this;this._proxy={};var s=function(p){Object.defineProperty(n._proxy,p,{get:function(){return i[p]}})};for(var c in Qn)s(c);var d=function(p){Object.defineProperty(n._proxy,p,{value:function(){return i[p].apply(i,arguments)}})};for(var f in cn)d(f);this._scopeFire("interactions:new",{interaction:this})}return u(t,[{key:"pointerMoveTolerance",get:function(){return 1}},{key:"pointerDown",value:function(e,n,o){var r=this.updatePointer(e,n,o,!0),i=this.pointers[r];this._scopeFire("interactions:down",{pointer:e,event:n,eventTarget:o,pointerIndex:r,pointerInfo:i,type:"down",interaction:this})}},{key:"start",value:function(e,n,o){return!(this.interacting()||!this.pointerIsDown||this.pointers.length<(e.name==="gesture"?2:1)||!n.options[e.name].enabled)&&(pt(this.prepared,e),this.interactable=n,this.element=o,this.rect=n.getRect(o),this.edges=this.prepared.edges?z({},this.prepared.edges):{left:!0,right:!0,top:!0,bottom:!0},this._stopped=!1,this._interacting=this._doPhase({interaction:this,event:this.downEvent,phase:"start"})&&!this._stopped,this._interacting)}},{key:"pointerMove",value:function(e,n,o){this.simulation||this.modification&&this.modification.endResult||this.updatePointer(e,n,o,!1);var r,i,s=this.coords.cur.page.x===this.coords.prev.page.x&&this.coords.cur.page.y===this.coords.prev.page.y&&this.coords.cur.client.x===this.coords.prev.client.x&&this.coords.cur.client.y===this.coords.prev.client.y;this.pointerIsDown&&!this.pointerWasMoved&&(r=this.coords.cur.client.x-this.coords.start.client.x,i=this.coords.cur.client.y-this.coords.start.client.y,this.pointerWasMoved=ke(r,i)>this.pointerMoveTolerance);var c,d,f,p=this.getPointerIndex(e),v={pointer:e,pointerIndex:p,pointerInfo:this.pointers[p],event:n,type:"move",eventTarget:o,dx:r,dy:i,duplicate:s,interaction:this};s||(c=this.coords.velocity,d=this.coords.delta,f=Math.max(d.timeStamp/1e3,.001),c.page.x=d.page.x/f,c.page.y=d.page.y/f,c.client.x=d.client.x/f,c.client.y=d.client.y/f,c.timeStamp=f),this._scopeFire("interactions:move",v),s||this.simulation||(this.interacting()&&(v.type=null,this.move(v)),this.pointerWasMoved&&Ne(this.coords.prev,this.coords.cur))}},{key:"move",value:function(e){e&&e.event||Nt(this.coords.delta),(e=z({pointer:this._latestPointer.pointer,event:this._latestPointer.event,eventTarget:this._latestPointer.eventTarget,interaction:this},e||{})).phase="move",this._doPhase(e)}},{key:"pointerUp",value:function(e,n,o,r){var i=this.getPointerIndex(e);i===-1&&(i=this.updatePointer(e,n,o,!1));var s=/cancel$/i.test(n.type)?"cancel":"up";this._scopeFire("interactions:".concat(s),{pointer:e,pointerIndex:i,pointerInfo:this.pointers[i],event:n,eventTarget:o,type:s,curEventTarget:r,interaction:this}),this.simulation||this.end(n),this.removePointer(e,n)}},{key:"documentBlur",value:function(e){this.end(e),this._scopeFire("interactions:blur",{event:e,type:"blur",interaction:this})}},{key:"end",value:function(e){var n;this._ending=!0,e=e||this._latestPointer.event,this.interacting()&&(n=this._doPhase({event:e,interaction:this,phase:"end"})),this._ending=!1,n===!0&&this.stop()}},{key:"currentAction",value:function(){return this._interacting?this.prepared.name:null}},{key:"interacting",value:function(){return this._interacting}},{key:"stop",value:function(){this._scopeFire("interactions:stop",{interaction:this}),this.interactable=this.element=null,this._interacting=!1,this._stopped=!0,this.prepared.name=this.prevEvent=null}},{key:"getPointerIndex",value:function(e){var n=_e(e);return this.pointerType==="mouse"||this.pointerType==="pen"?this.pointers.length-1:Ee(this.pointers,(function(o){return o.id===n}))}},{key:"getPointerInfo",value:function(e){return this.pointers[this.getPointerIndex(e)]}},{key:"updatePointer",value:function(e,n,o,r){var i,s,c,d=_e(e),f=this.getPointerIndex(e),p=this.pointers[f];return r=r!==!1&&(r||/(down|start)$/i.test(n.type)),p?p.pointer=e:(p=new Jn(d,e,n,null,null),f=this.pointers.length,this.pointers.push(p)),$n(this.coords.cur,this.pointers.map((function(v){return v.pointer})),this._now()),i=this.coords.delta,s=this.coords.prev,c=this.coords.cur,i.page.x=c.page.x-s.page.x,i.page.y=c.page.y-s.page.y,i.client.x=c.client.x-s.client.x,i.client.y=c.client.y-s.client.y,i.timeStamp=c.timeStamp-s.timeStamp,r&&(this.pointerIsDown=!0,p.downTime=this.coords.cur.timeStamp,p.downTarget=o,Ae(this.downPointer,e),this.interacting()||(Ne(this.coords.start,this.coords.cur),Ne(this.coords.prev,this.coords.cur),this.downEvent=n,this.pointerWasMoved=!1)),this._updateLatestPointer(e,n,o),this._scopeFire("interactions:update-pointer",{pointer:e,event:n,eventTarget:o,down:r,pointerInfo:p,pointerIndex:f,interaction:this}),f}},{key:"removePointer",value:function(e,n){var o=this.getPointerIndex(e);if(o!==-1){var r=this.pointers[o];this._scopeFire("interactions:remove-pointer",{pointer:e,event:n,eventTarget:null,pointerIndex:o,pointerInfo:r,interaction:this}),this.pointers.splice(o,1),this.pointerIsDown=!1}}},{key:"_updateLatestPointer",value:function(e,n,o){this._latestPointer.pointer=e,this._latestPointer.event=n,this._latestPointer.eventTarget=o}},{key:"destroy",value:function(){this._latestPointer.pointer=null,this._latestPointer.event=null,this._latestPointer.eventTarget=null}},{key:"_createPreparedEvent",value:function(e,n,o,r){return new vt(this,e,this.prepared.name,n,this.element,o,r)}},{key:"_fireEvent",value:function(e){var n;(n=this.interactable)==null||n.fire(e),(!this.prevEvent||e.timeStamp>=this.prevEvent.timeStamp)&&(this.prevEvent=e)}},{key:"_doPhase",value:function(e){var n=e.event,o=e.phase,r=e.preEnd,i=e.type,s=this.rect;if(s&&o==="move"&&(Le(this.edges,s,this.coords.delta[this.interactable.options.deltaSource]),s.width=s.right-s.left,s.height=s.bottom-s.top),this._scopeFire("interactions:before-action-".concat(o),e)===!1)return!1;var c=e.iEvent=this._createPreparedEvent(n,o,r,i);return this._scopeFire("interactions:action-".concat(o),e),o==="start"&&(this.prevEvent=c),this._fireEvent(c),this._scopeFire("interactions:after-action-".concat(o),e),!0}},{key:"_now",value:function(){return Date.now()}}]),t})();function ln(t){dn(t.interaction)}function dn(t){if(!(function(n){return!(!n.offset.pending.x&&!n.offset.pending.y)})(t))return!1;var e=t.offset.pending;return mt(t.coords.cur,e),mt(t.coords.delta,e),Le(t.edges,t.rect,e),e.x=0,e.y=0,!0}function no(t){var e=t.x,n=t.y;this.offset.pending.x+=e,this.offset.pending.y+=n,this.offset.total.x+=e,this.offset.total.y+=n}function mt(t,e){var n=t.page,o=t.client,r=e.x,i=e.y;n.x+=r,n.y+=i,o.x+=r,o.y+=i}cn.offsetBy="";var oo={id:"offset",before:["modifiers","pointer-events","actions","inertia"],install:function(t){t.Interaction.prototype.offsetBy=no},listeners:{"interactions:new":function(t){t.interaction.offset={total:{x:0,y:0},pending:{x:0,y:0}}},"interactions:update-pointer":function(t){return(function(e){e.pointerIsDown&&(mt(e.coords.cur,e.offset.total),e.offset.pending.x=0,e.offset.pending.y=0)})(t.interaction)},"interactions:before-action-start":ln,"interactions:before-action-move":ln,"interactions:before-action-end":function(t){var e=t.interaction;if(dn(e))return e.move({offset:!0}),e.end(),!1},"interactions:stop":function(t){var e=t.interaction;e.offset.total.x=0,e.offset.total.y=0,e.offset.pending.x=0,e.offset.pending.y=0}}},pn=oo,ro=(function(){function t(e){a(this,t),this.active=!1,this.isModified=!1,this.smoothEnd=!1,this.allowResume=!1,this.modification=void 0,this.modifierCount=0,this.modifierArg=void 0,this.startCoords=void 0,this.t0=0,this.v0=0,this.te=0,this.targetOffset=void 0,this.modifiedOffset=void 0,this.currentOffset=void 0,this.lambda_v0=0,this.one_ve_v0=0,this.timeout=void 0,this.interaction=void 0,this.interaction=e}return u(t,[{key:"start",value:function(e){var n=this.interaction,o=Ge(n);if(!o||!o.enabled)return!1;var r=n.coords.velocity.client,i=ke(r.x,r.y),s=this.modification||(this.modification=new gt(n));if(s.copyFrom(n.modification),this.t0=n._now(),this.allowResume=o.allowResume,this.v0=i,this.currentOffset={x:0,y:0},this.startCoords=n.coords.cur.page,this.modifierArg=s.fillArg({pageCoords:this.startCoords,preEnd:!0,phase:"inertiastart"}),this.t0-n.coords.cur.timeStamp<50&&i>o.minSpeed&&i>o.endSpeed)this.startInertia();else{if(s.result=s.setAll(this.modifierArg),!s.result.changed)return!1;this.startSmoothEnd()}return n.modification.result.rect=null,n.offsetBy(this.targetOffset),n._doPhase({interaction:n,event:e,phase:"inertiastart"}),n.offsetBy({x:-this.targetOffset.x,y:-this.targetOffset.y}),n.modification.result.rect=null,this.active=!0,n.simulation=this,!0}},{key:"startInertia",value:function(){var e=this,n=this.interaction.coords.velocity.client,o=Ge(this.interaction),r=o.resistance,i=-Math.log(o.endSpeed/this.v0)/r;this.targetOffset={x:(n.x-i)/r,y:(n.y-i)/r},this.te=i,this.lambda_v0=r/this.v0,this.one_ve_v0=1-o.endSpeed/this.v0;var s=this.modification,c=this.modifierArg;c.pageCoords={x:this.startCoords.x+this.targetOffset.x,y:this.startCoords.y+this.targetOffset.y},s.result=s.setAll(c),s.result.changed&&(this.isModified=!0,this.modifiedOffset={x:this.targetOffset.x+s.result.delta.x,y:this.targetOffset.y+s.result.delta.y}),this.onNextFrame((function(){return e.inertiaTick()}))}},{key:"startSmoothEnd",value:function(){var e=this;this.smoothEnd=!0,this.isModified=!0,this.targetOffset={x:this.modification.result.delta.x,y:this.modification.result.delta.y},this.onNextFrame((function(){return e.smoothEndTick()}))}},{key:"onNextFrame",value:function(e){var n=this;this.timeout=ce.request((function(){n.active&&e()}))}},{key:"inertiaTick",value:function(){var e,n,o,r,i,s,c,d=this,f=this.interaction,p=Ge(f).resistance,v=(f._now()-this.t0)/1e3;if(v<this.te){var m,_=1-(Math.exp(-p*v)-this.lambda_v0)/this.one_ve_v0;this.isModified?(e=0,n=0,o=this.targetOffset.x,r=this.targetOffset.y,i=this.modifiedOffset.x,s=this.modifiedOffset.y,m={x:un(c=_,e,o,i),y:un(c,n,r,s)}):m={x:this.targetOffset.x*_,y:this.targetOffset.y*_};var x={x:m.x-this.currentOffset.x,y:m.y-this.currentOffset.y};this.currentOffset.x+=x.x,this.currentOffset.y+=x.y,f.offsetBy(x),f.move(),this.onNextFrame((function(){return d.inertiaTick()}))}else f.offsetBy({x:this.modifiedOffset.x-this.currentOffset.x,y:this.modifiedOffset.y-this.currentOffset.y}),this.end()}},{key:"smoothEndTick",value:function(){var e=this,n=this.interaction,o=n._now()-this.t0,r=Ge(n).smoothEndDuration;if(o<r){var i={x:hn(o,0,this.targetOffset.x,r),y:hn(o,0,this.targetOffset.y,r)},s={x:i.x-this.currentOffset.x,y:i.y-this.currentOffset.y};this.currentOffset.x+=s.x,this.currentOffset.y+=s.y,n.offsetBy(s),n.move({skipModifiers:this.modifierCount}),this.onNextFrame((function(){return e.smoothEndTick()}))}else n.offsetBy({x:this.targetOffset.x-this.currentOffset.x,y:this.targetOffset.y-this.currentOffset.y}),this.end()}},{key:"resume",value:function(e){var n=e.pointer,o=e.event,r=e.eventTarget,i=this.interaction;i.offsetBy({x:-this.currentOffset.x,y:-this.currentOffset.y}),i.updatePointer(n,o,r,!0),i._doPhase({interaction:i,event:o,phase:"resume"}),Ne(i.coords.prev,i.coords.cur),this.stop()}},{key:"end",value:function(){this.interaction.move(),this.interaction.end(),this.stop()}},{key:"stop",value:function(){this.active=this.smoothEnd=!1,this.interaction.simulation=null,ce.cancel(this.timeout)}}]),t})();function Ge(t){var e=t.interactable,n=t.prepared;return e&&e.options&&n.name&&e.options[n.name].inertia}var io={id:"inertia",before:["modifiers","actions"],install:function(t){var e=t.defaults;t.usePlugin(pn),t.usePlugin(an),t.actions.phases.inertiastart=!0,t.actions.phases.resume=!0,e.perAction.inertia={enabled:!1,resistance:10,minSpeed:100,endSpeed:10,allowResume:!0,smoothEndDuration:300}},listeners:{"interactions:new":function(t){var e=t.interaction;e.inertia=new ro(e)},"interactions:before-action-end":function(t){var e=t.interaction,n=t.event;return(!e._interacting||e.simulation||!e.inertia.start(n))&&null},"interactions:down":function(t){var e=t.interaction,n=t.eventTarget,o=e.inertia;if(o.active)for(var r=n;y.element(r);){if(r===e.element){o.resume(t);break}r=oe(r)}},"interactions:stop":function(t){var e=t.interaction.inertia;e.active&&e.stop()},"interactions:before-action-resume":function(t){var e=t.interaction.modification;e.stop(t),e.start(t,t.interaction.coords.cur.page),e.applyToInteraction(t)},"interactions:before-action-inertiastart":function(t){return t.interaction.modification.setAndApply(t)},"interactions:action-resume":Ie,"interactions:action-inertiastart":Ie,"interactions:after-action-inertiastart":function(t){return t.interaction.modification.restoreInteractionCoords(t)},"interactions:after-action-resume":function(t){return t.interaction.modification.restoreInteractionCoords(t)}}};function un(t,e,n,o){var r=1-t;return r*r*e+2*r*t*n+t*t*o}function hn(t,e,n,o){return-n*(t/=o)*(t-2)+e}var ao=io;function fn(t,e){for(var n=0;n<e.length;n++){var o=e[n];if(t.immediatePropagationStopped)break;o(t)}}var gn=(function(){function t(e){a(this,t),this.options=void 0,this.types={},this.propagationStopped=!1,this.immediatePropagationStopped=!1,this.global=void 0,this.options=z({},e||{})}return u(t,[{key:"fire",value:function(e){var n,o=this.global;(n=this.types[e.type])&&fn(e,n),!e.propagationStopped&&o&&(n=o[e.type])&&fn(e,n)}},{key:"on",value:function(e,n){var o=he(e,n);for(e in o)this.types[e]=Gt(this.types[e]||[],o[e])}},{key:"off",value:function(e,n){var o=he(e,n);for(e in o){var r=this.types[e];if(r&&r.length)for(var i=0,s=o[e];i<s.length;i++){var c=s[i],d=r.indexOf(c);d!==-1&&r.splice(d,1)}}}},{key:"getRect",value:function(e){return null}}]),t})(),so=(function(){function t(e){a(this,t),this.currentTarget=void 0,this.originalEvent=void 0,this.type=void 0,this.originalEvent=e,Ae(this,e)}return u(t,[{key:"preventOriginalDefault",value:function(){this.originalEvent.preventDefault()}},{key:"stopPropagation",value:function(){this.originalEvent.stopPropagation()}},{key:"stopImmediatePropagation",value:function(){this.originalEvent.stopImmediatePropagation()}}]),t})();function ze(t){return y.object(t)?{capture:!!t.capture,passive:!!t.passive}:{capture:!!t,passive:!1}}function Be(t,e){return t===e||(typeof t=="boolean"?!!e.capture===t&&!e.passive:!!t.capture==!!e.capture&&!!t.passive==!!e.passive)}var co={id:"events",install:function(t){var e,n=[],o={},r=[],i={add:s,remove:c,addDelegate:function(p,v,m,_,x){var E=ze(x);if(!o[m]){o[m]=[];for(var S=0;S<r.length;S++){var T=r[S];s(T,m,d),s(T,m,f,!0)}}var I=o[m],$=Te(I,(function(A){return A.selector===p&&A.context===v}));$||($={selector:p,context:v,listeners:[]},I.push($)),$.listeners.push({func:_,options:E})},removeDelegate:function(p,v,m,_,x){var E,S=ze(x),T=o[m],I=!1;if(T)for(E=T.length-1;E>=0;E--){var $=T[E];if($.selector===p&&$.context===v){for(var A=$.listeners,M=A.length-1;M>=0;M--){var O=A[M];if(O.func===_&&Be(O.options,S)){A.splice(M,1),A.length||(T.splice(E,1),c(v,m,d),c(v,m,f,!0)),I=!0;break}}if(I)break}}},delegateListener:d,delegateUseCapture:f,delegatedEvents:o,documents:r,targets:n,supportsOptions:!1,supportsPassive:!1};function s(p,v,m,_){if(p.addEventListener){var x=ze(_),E=Te(n,(function(S){return S.eventTarget===p}));E||(E={eventTarget:p,events:{}},n.push(E)),E.events[v]||(E.events[v]=[]),Te(E.events[v],(function(S){return S.func===m&&Be(S.options,x)}))||(p.addEventListener(v,m,i.supportsOptions?x:x.capture),E.events[v].push({func:m,options:x}))}}function c(p,v,m,_){if(p.addEventListener&&p.removeEventListener){var x=Ee(n,(function(Y){return Y.eventTarget===p})),E=n[x];if(E&&E.events)if(v!=="all"){var S=!1,T=E.events[v];if(T){if(m==="all"){for(var I=T.length-1;I>=0;I--){var $=T[I];c(p,v,$.func,$.options)}return}for(var A=ze(_),M=0;M<T.length;M++){var O=T[M];if(O.func===m&&Be(O.options,A)){p.removeEventListener(v,m,i.supportsOptions?A:A.capture),T.splice(M,1),T.length===0&&(delete E.events[v],S=!0);break}}}S&&!Object.keys(E.events).length&&n.splice(x,1)}else for(v in E.events)E.events.hasOwnProperty(v)&&c(p,v,"all")}}function d(p,v){for(var m=ze(v),_=new so(p),x=o[p.type],E=Ht(p)[0],S=E;y.element(S);){for(var T=0;T<x.length;T++){var I=x[T],$=I.selector,A=I.context;if(se(S,$)&&ue(A,E)&&ue(A,S)){var M=I.listeners;_.currentTarget=S;for(var O=0;O<M.length;O++){var Y=M[O];Be(Y.options,m)&&Y.func(_)}}}S=oe(S)}}function f(p){return d(p,!0)}return(e=t.document)==null||e.createElement("div").addEventListener("test",null,{get capture(){return i.supportsOptions=!0},get passive(){return i.supportsPassive=!0}}),t.events=i,i}},bt={methodOrder:["simulationResume","mouseOrPen","hasPointer","idle"],search:function(t){for(var e=0,n=bt.methodOrder;e<n.length;e++){var o=n[e],r=bt[o](t);if(r)return r}return null},simulationResume:function(t){var e=t.pointerType,n=t.eventType,o=t.eventTarget,r=t.scope;if(!/down|start/i.test(n))return null;for(var i=0,s=r.interactions.list;i<s.length;i++){var c=s[i],d=o;if(c.simulation&&c.simulation.allowResume&&c.pointerType===e)for(;d;){if(d===c.element)return c;d=oe(d)}}return null},mouseOrPen:function(t){var e,n=t.pointerId,o=t.pointerType,r=t.eventType,i=t.scope;if(o!=="mouse"&&o!=="pen")return null;for(var s=0,c=i.interactions.list;s<c.length;s++){var d=c[s];if(d.pointerType===o){if(d.simulation&&!vn(d,n))continue;if(d.interacting())return d;e||(e=d)}}if(e)return e;for(var f=0,p=i.interactions.list;f<p.length;f++){var v=p[f];if(!(v.pointerType!==o||/down/i.test(r)&&v.simulation))return v}return null},hasPointer:function(t){for(var e=t.pointerId,n=0,o=t.scope.interactions.list;n<o.length;n++){var r=o[n];if(vn(r,e))return r}return null},idle:function(t){for(var e=t.pointerType,n=0,o=t.scope.interactions.list;n<o.length;n++){var r=o[n];if(r.pointers.length===1){var i=r.interactable;if(i&&(!i.options.gesture||!i.options.gesture.enabled))continue}else if(r.pointers.length>=2)continue;if(!r.interacting()&&e===r.pointerType)return r}return null}};function vn(t,e){return t.pointers.some((function(n){return n.id===e}))}var lo=bt,yt=["pointerDown","pointerMove","pointerUp","updatePointer","removePointer","windowBlur"];function mn(t,e){return function(n){var o=e.interactions.list,r=Rt(n),i=Ht(n),s=i[0],c=i[1],d=[];if(/^touch/.test(n.type)){e.prevTouchTime=e.now();for(var f=0,p=n.changedTouches;f<p.length;f++){var v=p[f],m={pointer:v,pointerId:_e(v),pointerType:r,eventType:n.type,eventTarget:s,curEventTarget:c,scope:e},_=bn(m);d.push([m.pointer,m.eventTarget,m.curEventTarget,_])}}else{var x=!1;if(!Q.supportsPointerEvent&&/mouse/.test(n.type)){for(var E=0;E<o.length&&!x;E++)x=o[E].pointerType!=="mouse"&&o[E].pointerIsDown;x=x||e.now()-e.prevTouchTime<500||n.timeStamp===0}if(!x){var S={pointer:n,pointerId:_e(n),pointerType:r,eventType:n.type,curEventTarget:c,eventTarget:s,scope:e},T=bn(S);d.push([S.pointer,S.eventTarget,S.curEventTarget,T])}}for(var I=0;I<d.length;I++){var $=d[I],A=$[0],M=$[1],O=$[2];$[3][t](A,n,M,O)}}}function bn(t){var e=t.pointerType,n=t.scope,o={interaction:lo.search(t),searchDetails:t};return n.fire("interactions:find",o),o.interaction||n.interactions.new({pointerType:e})}function xt(t,e){var n=t.doc,o=t.scope,r=t.options,i=o.interactions.docEvents,s=o.events,c=s[e];for(var d in o.browser.isIOS&&!r.events&&(r.events={passive:!1}),s.delegatedEvents)c(n,d,s.delegateListener),c(n,d,s.delegateUseCapture,!0);for(var f=r&&r.events,p=0;p<i.length;p++){var v=i[p];c(n,v.type,v.listener,f)}}var po={id:"core/interactions",install:function(t){for(var e={},n=0;n<yt.length;n++){var o=yt[n];e[o]=mn(o,t)}var r,i=Q.pEventTypes;function s(){for(var c=0,d=t.interactions.list;c<d.length;c++){var f=d[c];if(f.pointerIsDown&&f.pointerType==="touch"&&!f._interacting)for(var p=function(){var _=m[v];t.documents.some((function(x){return ue(x.doc,_.downTarget)}))||f.removePointer(_.pointer,_.event)},v=0,m=f.pointers;v<m.length;v++)p()}}(r=G.PointerEvent?[{type:i.down,listener:s},{type:i.down,listener:e.pointerDown},{type:i.move,listener:e.pointerMove},{type:i.up,listener:e.pointerUp},{type:i.cancel,listener:e.pointerUp}]:[{type:"mousedown",listener:e.pointerDown},{type:"mousemove",listener:e.pointerMove},{type:"mouseup",listener:e.pointerUp},{type:"touchstart",listener:s},{type:"touchstart",listener:e.pointerDown},{type:"touchmove",listener:e.pointerMove},{type:"touchend",listener:e.pointerUp},{type:"touchcancel",listener:e.pointerUp}]).push({type:"blur",listener:function(c){for(var d=0,f=t.interactions.list;d<f.length;d++)f[d].documentBlur(c)}}),t.prevTouchTime=0,t.Interaction=(function(c){g(f,c);var d=D(f);function f(){return a(this,f),d.apply(this,arguments)}return u(f,[{key:"pointerMoveTolerance",get:function(){return t.interactions.pointerMoveTolerance},set:function(p){t.interactions.pointerMoveTolerance=p}},{key:"_now",value:function(){return t.now()}}]),f})(to),t.interactions={list:[],new:function(c){c.scopeFire=function(f,p){return t.fire(f,p)};var d=new t.Interaction(c);return t.interactions.list.push(d),d},listeners:e,docEvents:r,pointerMoveTolerance:1},t.usePlugin(rn)},listeners:{"scope:add-document":function(t){return xt(t,"add")},"scope:remove-document":function(t){return xt(t,"remove")},"interactable:unset":function(t,e){for(var n=t.interactable,o=e.interactions.list.length-1;o>=0;o--){var r=e.interactions.list[o];r.interactable===n&&(r.stop(),e.fire("interactions:destroy",{interaction:r}),r.destroy(),e.interactions.list.length>2&&e.interactions.list.splice(o,1))}}},onDocSignal:xt,doOnInteractions:mn,methodNames:yt},uo=po,de=(function(t){return t[t.On=0]="On",t[t.Off=1]="Off",t})(de||{}),ho=(function(){function t(e,n,o,r){a(this,t),this.target=void 0,this.options=void 0,this._actions=void 0,this.events=new gn,this._context=void 0,this._win=void 0,this._doc=void 0,this._scopeEvents=void 0,this._actions=n.actions,this.target=e,this._context=n.context||o,this._win=ne(Ot(e)?this._context:e),this._doc=this._win.document,this._scopeEvents=r,this.set(n)}return u(t,[{key:"_defaults",get:function(){return{base:{},perAction:{},actions:{}}}},{key:"setOnEvents",value:function(e,n){return y.func(n.onstart)&&this.on("".concat(e,"start"),n.onstart),y.func(n.onmove)&&this.on("".concat(e,"move"),n.onmove),y.func(n.onend)&&this.on("".concat(e,"end"),n.onend),y.func(n.oninertiastart)&&this.on("".concat(e,"inertiastart"),n.oninertiastart),this}},{key:"updatePerActionListeners",value:function(e,n,o){var r,i=this,s=(r=this._actions.map[e])==null?void 0:r.filterEventType,c=function(d){return(s==null||s(d))&&Re(d,i._actions)};(y.array(n)||y.object(n))&&this._onOff(de.Off,e,n,void 0,c),(y.array(o)||y.object(o))&&this._onOff(de.On,e,o,void 0,c)}},{key:"setPerAction",value:function(e,n){var o=this._defaults;for(var r in n){var i=r,s=this.options[e],c=n[i];i==="listeners"&&this.updatePerActionListeners(e,s.listeners,c),y.array(c)?s[i]=Bt(c):y.plainObject(c)?(s[i]=z(s[i]||{},ye(c)),y.object(o.perAction[i])&&"enabled"in o.perAction[i]&&(s[i].enabled=c.enabled!==!1)):y.bool(c)&&y.object(o.perAction[i])?s[i].enabled=c:s[i]=c}}},{key:"getRect",value:function(e){return e=e||(y.element(this.target)?this.target:null),y.string(this.target)&&(e=e||this._context.querySelector(this.target)),et(e)}},{key:"rectChecker",value:function(e){var n=this;return y.func(e)?(this.getRect=function(o){var r=z({},e.apply(n,o));return"width"in r||(r.width=r.right-r.left,r.height=r.bottom-r.top),r},this):e===null?(delete this.getRect,this):this.getRect}},{key:"_backCompatOption",value:function(e,n){if(Ot(n)||y.object(n)){for(var o in this.options[e]=n,this._actions.map)this.options[o][e]=n;return this}return this.options[e]}},{key:"origin",value:function(e){return this._backCompatOption("origin",e)}},{key:"deltaSource",value:function(e){return e==="page"||e==="client"?(this.options.deltaSource=e,this):this.options.deltaSource}},{key:"getAllElements",value:function(){var e=this.target;return y.string(e)?Array.from(this._context.querySelectorAll(e)):y.func(e)&&e.getAllElements?e.getAllElements():y.element(e)?[e]:[]}},{key:"context",value:function(){return this._context}},{key:"inContext",value:function(e){return this._context===e.ownerDocument||ue(this._context,e)}},{key:"testIgnoreAllow",value:function(e,n,o){return!this.testIgnore(e.ignoreFrom,n,o)&&this.testAllow(e.allowFrom,n,o)}},{key:"testAllow",value:function(e,n,o){return!e||!!y.element(o)&&(y.string(e)?Je(o,e,n):!!y.element(e)&&ue(e,o))}},{key:"testIgnore",value:function(e,n,o){return!(!e||!y.element(o))&&(y.string(e)?Je(o,e,n):!!y.element(e)&&ue(e,o))}},{key:"fire",value:function(e){return this.events.fire(e),this}},{key:"_onOff",value:function(e,n,o,r,i){y.object(n)&&!y.array(n)&&(r=o,o=null);var s=he(n,o,i);for(var c in s){c==="wheel"&&(c=Q.wheelEvent);for(var d=0,f=s[c];d<f.length;d++){var p=f[d];Re(c,this._actions)?this.events[e===de.On?"on":"off"](c,p):y.string(this.target)?this._scopeEvents[e===de.On?"addDelegate":"removeDelegate"](this.target,this._context,c,p,r):this._scopeEvents[e===de.On?"add":"remove"](this.target,c,p,r)}}return this}},{key:"on",value:function(e,n,o){return this._onOff(de.On,e,n,o)}},{key:"off",value:function(e,n,o){return this._onOff(de.Off,e,n,o)}},{key:"set",value:function(e){var n=this._defaults;for(var o in y.object(e)||(e={}),this.options=ye(n.base),this._actions.methodDict){var r=o,i=this._actions.methodDict[r];this.options[r]={},this.setPerAction(r,z(z({},n.perAction),n.actions[r])),this[i](e[r])}for(var s in e)s!=="getRect"?y.func(this[s])&&this[s](e[s]):this.rectChecker(e.getRect);return this}},{key:"unset",value:function(){if(y.string(this.target))for(var e in this._scopeEvents.delegatedEvents)for(var n=this._scopeEvents.delegatedEvents[e],o=n.length-1;o>=0;o--){var r=n[o],i=r.selector,s=r.context,c=r.listeners;i===this.target&&s===this._context&&n.splice(o,1);for(var d=c.length-1;d>=0;d--)this._scopeEvents.removeDelegate(this.target,this._context,e,c[d][0],c[d][1])}else this._scopeEvents.remove(this.target,"all")}}]),t})(),fo=(function(){function t(e){var n=this;a(this,t),this.list=[],this.selectorMap={},this.scope=void 0,this.scope=e,e.addListeners({"interactable:unset":function(o){var r=o.interactable,i=r.target,s=y.string(i)?n.selectorMap[i]:i[n.scope.id],c=Ee(s,(function(d){return d===r}));s.splice(c,1)}})}return u(t,[{key:"new",value:function(e,n){n=z(n||{},{actions:this.scope.actions});var o=new this.scope.Interactable(e,n,this.scope.document,this.scope.events);return this.scope.addDocument(o._doc),this.list.push(o),y.string(e)?(this.selectorMap[e]||(this.selectorMap[e]=[]),this.selectorMap[e].push(o)):(o.target[this.scope.id]||Object.defineProperty(e,this.scope.id,{value:[],configurable:!0}),e[this.scope.id].push(o)),this.scope.fire("interactable:new",{target:e,options:n,interactable:o,win:this.scope._win}),o}},{key:"getExisting",value:function(e,n){var o=n&&n.context||this.scope.document,r=y.string(e),i=r?this.selectorMap[e]:e[this.scope.id];if(i)return Te(i,(function(s){return s._context===o&&(r||s.inContext(e))}))}},{key:"forEachMatch",value:function(e,n){for(var o=0,r=this.list;o<r.length;o++){var i=r[o],s=void 0;if((y.string(i.target)?y.element(e)&&se(e,i.target):e===i.target)&&i.inContext(e)&&(s=n(i)),s!==void 0)return s}}}]),t})(),go=(function(){function t(){var e=this;a(this,t),this.id="__interact_scope_".concat(Math.floor(100*Math.random())),this.isInitialized=!1,this.listenerMaps=[],this.browser=Q,this.defaults=ye(sn),this.Eventable=gn,this.actions={map:{},phases:{start:!0,move:!0,end:!0},methodDict:{},phaselessTypes:{}},this.interactStatic=(function(o){var r=function i(s,c){var d=o.interactables.getExisting(s,c);return d||((d=o.interactables.new(s,c)).events.global=i.globalEvents),d};return r.getPointerAverage=qt,r.getTouchBBox=ot,r.getTouchDistance=rt,r.getTouchAngle=it,r.getElementRect=et,r.getElementClientRect=Qe,r.matchesSelector=se,r.closest=Pt,r.globalEvents={},r.version="1.10.27",r.scope=o,r.use=function(i,s){return this.scope.usePlugin(i,s),this},r.isSet=function(i,s){return!!this.scope.interactables.get(i,s&&s.context)},r.on=Se((function(i,s,c){if(y.string(i)&&i.search(" ")!==-1&&(i=i.trim().split(/ +/)),y.array(i)){for(var d=0,f=i;d<f.length;d++){var p=f[d];this.on(p,s,c)}return this}if(y.object(i)){for(var v in i)this.on(v,i[v],s);return this}return Re(i,this.scope.actions)?this.globalEvents[i]?this.globalEvents[i].push(s):this.globalEvents[i]=[s]:this.scope.events.add(this.scope.document,i,s,{options:c}),this}),"The interact.on() method is being deprecated"),r.off=Se((function(i,s,c){if(y.string(i)&&i.search(" ")!==-1&&(i=i.trim().split(/ +/)),y.array(i)){for(var d=0,f=i;d<f.length;d++){var p=f[d];this.off(p,s,c)}return this}if(y.object(i)){for(var v in i)this.off(v,i[v],s);return this}var m;return Re(i,this.scope.actions)?i in this.globalEvents&&(m=this.globalEvents[i].indexOf(s))!==-1&&this.globalEvents[i].splice(m,1):this.scope.events.remove(this.scope.document,i,s,c),this}),"The interact.off() method is being deprecated"),r.debug=function(){return this.scope},r.supportsTouch=function(){return Q.supportsTouch},r.supportsPointerEvent=function(){return Q.supportsPointerEvent},r.stop=function(){for(var i=0,s=this.scope.interactions.list;i<s.length;i++)s[i].stop();return this},r.pointerMoveTolerance=function(i){return y.number(i)?(this.scope.interactions.pointerMoveTolerance=i,this):this.scope.interactions.pointerMoveTolerance},r.addDocument=function(i,s){this.scope.addDocument(i,s)},r.removeDocument=function(i){this.scope.removeDocument(i)},r})(this),this.InteractEvent=vt,this.Interactable=void 0,this.interactables=new fo(this),this._win=void 0,this.document=void 0,this.window=void 0,this.documents=[],this._plugins={list:[],map:{}},this.onWindowUnload=function(o){return e.removeDocument(o.target)};var n=this;this.Interactable=(function(o){g(i,o);var r=D(i);function i(){return a(this,i),r.apply(this,arguments)}return u(i,[{key:"_defaults",get:function(){return n.defaults}},{key:"set",value:function(s){return C(w(i.prototype),"set",this).call(this,s),n.fire("interactable:set",{options:s,interactable:this}),this}},{key:"unset",value:function(){C(w(i.prototype),"unset",this).call(this);var s=n.interactables.list.indexOf(this);s<0||(n.interactables.list.splice(s,1),n.fire("interactable:unset",{interactable:this}))}}]),i})(ho)}return u(t,[{key:"addListeners",value:function(e,n){this.listenerMaps.push({id:n,map:e})}},{key:"fire",value:function(e,n){for(var o=0,r=this.listenerMaps;o<r.length;o++){var i=r[o].map[e];if(i&&i(n,this,e)===!1)return!1}}},{key:"init",value:function(e){return this.isInitialized?this:(function(n,o){return n.isInitialized=!0,y.window(o)&&J(o),G.init(o),Q.init(o),ce.init(o),n.window=o,n.document=o.document,n.usePlugin(uo),n.usePlugin(co),n})(this,e)}},{key:"pluginIsInstalled",value:function(e){var n=e.id;return n?!!this._plugins.map[n]:this._plugins.list.indexOf(e)!==-1}},{key:"usePlugin",value:function(e,n){if(!this.isInitialized)return this;if(this.pluginIsInstalled(e))return this;if(e.id&&(this._plugins.map[e.id]=e),this._plugins.list.push(e),e.install&&e.install(this,n),e.listeners&&e.before){for(var o=0,r=this.listenerMaps.length,i=e.before.reduce((function(c,d){return c[d]=!0,c[yn(d)]=!0,c}),{});o<r;o++){var s=this.listenerMaps[o].id;if(s&&(i[s]||i[yn(s)]))break}this.listenerMaps.splice(o,0,{id:e.id,map:e.listeners})}else e.listeners&&this.listenerMaps.push({id:e.id,map:e.listeners});return this}},{key:"addDocument",value:function(e,n){if(this.getDocIndex(e)!==-1)return!1;var o=ne(e);n=n?z({},n):{},this.documents.push({doc:e,options:n}),this.events.documents.push(e),e!==this.document&&this.events.add(o,"unload",this.onWindowUnload),this.fire("scope:add-document",{doc:e,window:o,scope:this,options:n})}},{key:"removeDocument",value:function(e){var n=this.getDocIndex(e),o=ne(e),r=this.documents[n].options;this.events.remove(o,"unload",this.onWindowUnload),this.documents.splice(n,1),this.events.documents.splice(n,1),this.fire("scope:remove-document",{doc:e,window:o,scope:this,options:r})}},{key:"getDocIndex",value:function(e){for(var n=0;n<this.documents.length;n++)if(this.documents[n].doc===e)return n;return-1}},{key:"getDocOptions",value:function(e){var n=this.getDocIndex(e);return n===-1?null:this.documents[n].options}},{key:"now",value:function(){return(this.window.Date||Date).now()}}]),t})();function yn(t){return t&&t.replace(/\/.*$/,"")}var xn=new go,B=xn.interactStatic,vo=typeof globalThis<"u"?globalThis:window;xn.init(vo);var mo=Object.freeze({__proto__:null,edgeTarget:function(){},elements:function(){},grid:function(t){var e=[["x","y"],["left","top"],["right","bottom"],["width","height"]].filter((function(o){var r=o[0],i=o[1];return r in t||i in t})),n=function(o,r){for(var i=t.range,s=t.limits,c=s===void 0?{left:-1/0,right:1/0,top:-1/0,bottom:1/0}:s,d=t.offset,f=d===void 0?{x:0,y:0}:d,p={range:i,grid:t,x:null,y:null},v=0;v<e.length;v++){var m=e[v],_=m[0],x=m[1],E=Math.round((o-f.x)/t[_]),S=Math.round((r-f.y)/t[x]);p[_]=Math.max(c.left,Math.min(c.right,E*t[_]+f.x)),p[x]=Math.max(c.top,Math.min(c.bottom,S*t[x]+f.y))}return p};return n.grid=t,n.coordFields=e,n}}),bo={id:"snappers",install:function(t){var e=t.interactStatic;e.snappers=z(e.snappers||{},mo),e.createSnapGrid=e.snappers.grid}},yo=bo,xo={start:function(t){var e=t.state,n=t.rect,o=t.edges,r=t.pageCoords,i=e.options,s=i.ratio,c=i.enabled,d=e.options,f=d.equalDelta,p=d.modifiers;s==="preserve"&&(s=n.width/n.height),e.startCoords=z({},r),e.startRect=z({},n),e.ratio=s,e.equalDelta=f;var v=e.linkedEdges={top:o.top||o.left&&!o.bottom,left:o.left||o.top&&!o.right,bottom:o.bottom||o.right&&!o.top,right:o.right||o.bottom&&!o.left};if(e.xIsPrimaryAxis=!(!o.left&&!o.right),e.equalDelta){var m=(v.left?1:-1)*(v.top?1:-1);e.edgeSign={x:m,y:m}}else e.edgeSign={x:v.left?-1:1,y:v.top?-1:1};if(c!==!1&&z(o,v),p!=null&&p.length){var _=new gt(t.interaction);_.copyFrom(t.interaction.modification),_.prepareStates(p),e.subModification=_,_.startAll(j({},t))}},set:function(t){var e=t.state,n=t.rect,o=t.coords,r=e.linkedEdges,i=z({},o),s=e.equalDelta?wo:ko;if(z(t.edges,r),s(e,e.xIsPrimaryAxis,o,n),!e.subModification)return null;var c=z({},n);Le(r,c,{x:o.x-i.x,y:o.y-i.y});var d=e.subModification.setAll(j(j({},t),{},{rect:c,edges:r,pageCoords:o,prevCoords:o,prevRect:c})),f=d.delta;return d.changed&&(s(e,Math.abs(f.x)>Math.abs(f.y),d.coords,d.rect),z(o,d.coords)),d.eventProps},defaults:{ratio:"preserve",equalDelta:!1,modifiers:[],enabled:!1}};function wo(t,e,n){var o=t.startCoords,r=t.edgeSign;e?n.y=o.y+(n.x-o.x)*r.y:n.x=o.x+(n.y-o.y)*r.x}function ko(t,e,n,o){var r=t.startRect,i=t.startCoords,s=t.ratio,c=t.edgeSign;if(e){var d=o.width/s;n.y=i.y+(d-r.height)*c.y}else{var f=o.height*s;n.x=i.x+(f-r.width)*c.x}}var _o=le(xo,"aspectRatio"),wn=function(){};wn._defaults={};var Ye=wn;function ge(t,e,n){return y.func(t)?xe(t,e.interactable,e.element,[n.x,n.y,e]):xe(t,e.interactable,e.element)}var Xe={start:function(t){var e=t.rect,n=t.startOffset,o=t.state,r=t.interaction,i=t.pageCoords,s=o.options,c=s.elementRect,d=z({left:0,top:0,right:0,bottom:0},s.offset||{});if(e&&c){var f=ge(s.restriction,r,i);if(f){var p=f.right-f.left-e.width,v=f.bottom-f.top-e.height;p<0&&(d.left+=p,d.right+=p),v<0&&(d.top+=v,d.bottom+=v)}d.left+=n.left-e.width*c.left,d.top+=n.top-e.height*c.top,d.right+=n.right-e.width*(1-c.right),d.bottom+=n.bottom-e.height*(1-c.bottom)}o.offset=d},set:function(t){var e=t.coords,n=t.interaction,o=t.state,r=o.options,i=o.offset,s=ge(r.restriction,n,e);if(s){var c=(function(d){return!d||"left"in d&&"top"in d||((d=z({},d)).left=d.x||0,d.top=d.y||0,d.right=d.right||d.left+d.width,d.bottom=d.bottom||d.top+d.height),d})(s);e.x=Math.max(Math.min(c.right-i.right,e.x),c.left+i.left),e.y=Math.max(Math.min(c.bottom-i.bottom,e.y),c.top+i.top)}},defaults:{restriction:null,elementRect:null,offset:null,endOnly:!1,enabled:!1}},Eo=le(Xe,"restrict"),kn={top:1/0,left:1/0,bottom:-1/0,right:-1/0},_n={top:-1/0,left:-1/0,bottom:1/0,right:1/0};function En(t,e){for(var n=0,o=["top","left","bottom","right"];n<o.length;n++){var r=o[n];r in t||(t[r]=e[r])}return t}var Me={noInner:kn,noOuter:_n,start:function(t){var e,n=t.interaction,o=t.startOffset,r=t.state,i=r.options;i&&(e=Oe(ge(i.offset,n,n.coords.start.page))),e=e||{x:0,y:0},r.offset={top:e.y+o.top,left:e.x+o.left,bottom:e.y-o.bottom,right:e.x-o.right}},set:function(t){var e=t.coords,n=t.edges,o=t.interaction,r=t.state,i=r.offset,s=r.options;if(n){var c=z({},e),d=ge(s.inner,o,c)||{},f=ge(s.outer,o,c)||{};En(d,kn),En(f,_n),n.top?e.y=Math.min(Math.max(f.top+i.top,c.y),d.top+i.top):n.bottom&&(e.y=Math.max(Math.min(f.bottom+i.bottom,c.y),d.bottom+i.bottom)),n.left?e.x=Math.min(Math.max(f.left+i.left,c.x),d.left+i.left):n.right&&(e.x=Math.max(Math.min(f.right+i.right,c.x),d.right+i.right))}},defaults:{inner:null,outer:null,offset:null,endOnly:!1,enabled:!1}},To=le(Me,"restrictEdges"),So=z({get elementRect(){return{top:0,left:0,bottom:1,right:1}},set elementRect(t){}},Xe.defaults),Io=le({start:Xe.start,set:Xe.set,defaults:So},"restrictRect"),zo={width:-1/0,height:-1/0},Mo={width:1/0,height:1/0},Po=le({start:function(t){return Me.start(t)},set:function(t){var e=t.interaction,n=t.state,o=t.rect,r=t.edges,i=n.options;if(r){var s=tt(ge(i.min,e,t.coords))||zo,c=tt(ge(i.max,e,t.coords))||Mo;n.options={endOnly:i.endOnly,inner:z({},Me.noInner),outer:z({},Me.noOuter)},r.top?(n.options.inner.top=o.bottom-s.height,n.options.outer.top=o.bottom-c.height):r.bottom&&(n.options.inner.bottom=o.top+s.height,n.options.outer.bottom=o.top+c.height),r.left?(n.options.inner.left=o.right-s.width,n.options.outer.left=o.right-c.width):r.right&&(n.options.inner.right=o.left+s.width,n.options.outer.right=o.left+c.width),Me.set(t),n.options=i}},defaults:{min:null,max:null,endOnly:!1,enabled:!1}},"restrictSize"),wt={start:function(t){var e,n=t.interaction,o=t.interactable,r=t.element,i=t.rect,s=t.state,c=t.startOffset,d=s.options,f=d.offsetWithOrigin?(function(m){var _=m.interaction.element,x=Oe(xe(m.state.options.origin,null,null,[_])),E=x||we(m.interactable,_,m.interaction.prepared.name);return E})(t):{x:0,y:0};if(d.offset==="startCoords")e={x:n.coords.start.page.x,y:n.coords.start.page.y};else{var p=xe(d.offset,o,r,[n]);(e=Oe(p)||{x:0,y:0}).x+=f.x,e.y+=f.y}var v=d.relativePoints;s.offsets=i&&v&&v.length?v.map((function(m,_){return{index:_,relativePoint:m,x:c.left-i.width*m.x+e.x,y:c.top-i.height*m.y+e.y}})):[{index:0,relativePoint:null,x:e.x,y:e.y}]},set:function(t){var e=t.interaction,n=t.coords,o=t.state,r=o.options,i=o.offsets,s=we(e.interactable,e.element,e.prepared.name),c=z({},n),d=[];r.offsetWithOrigin||(c.x-=s.x,c.y-=s.y);for(var f=0,p=i;f<p.length;f++)for(var v=p[f],m=c.x-v.x,_=c.y-v.y,x=0,E=r.targets.length;x<E;x++){var S=r.targets[x],T=void 0;(T=y.func(S)?S(m,_,e._proxy,v,x):S)&&d.push({x:(y.number(T.x)?T.x:m)+v.x,y:(y.number(T.y)?T.y:_)+v.y,range:y.number(T.range)?T.range:r.range,source:S,index:x,offset:v})}for(var I={target:null,inRange:!1,distance:0,range:0,delta:{x:0,y:0}},$=0;$<d.length;$++){var A=d[$],M=A.range,O=A.x-c.x,Y=A.y-c.y,F=ke(O,Y),K=F<=M;M===1/0&&I.inRange&&I.range!==1/0&&(K=!1),I.target&&!(K?I.inRange&&M!==1/0?F/M<I.distance/I.range:M===1/0&&I.range!==1/0||F<I.distance:!I.inRange&&F<I.distance)||(I.target=A,I.distance=F,I.range=M,I.inRange=K,I.delta.x=O,I.delta.y=Y)}return I.inRange&&(n.x=I.target.x,n.y=I.target.y),o.closest=I,I},defaults:{range:1/0,targets:null,offset:null,offsetWithOrigin:!0,origin:null,relativePoints:null,endOnly:!1,enabled:!1}},Do=le(wt,"snap"),Ke={start:function(t){var e=t.state,n=t.edges,o=e.options;if(!n)return null;t.state={options:{targets:null,relativePoints:[{x:n.left?0:1,y:n.top?0:1}],offset:o.offset||"self",origin:{x:0,y:0},range:o.range}},e.targetFields=e.targetFields||[["width","height"],["x","y"]],wt.start(t),e.offsets=t.state.offsets,t.state=e},set:function(t){var e=t.interaction,n=t.state,o=t.coords,r=n.options,i=n.offsets,s={x:o.x-i[0].x,y:o.y-i[0].y};n.options=z({},r),n.options.targets=[];for(var c=0,d=r.targets||[];c<d.length;c++){var f=d[c],p=void 0;if(p=y.func(f)?f(s.x,s.y,e):f){for(var v=0,m=n.targetFields;v<m.length;v++){var _=m[v],x=_[0],E=_[1];if(x in p||E in p){p.x=p[x],p.y=p[E];break}}n.options.targets.push(p)}}var S=wt.set(t);return n.options=r,S},defaults:{range:1/0,targets:null,offset:null,endOnly:!1,enabled:!1}},$o=le(Ke,"snapSize"),kt={aspectRatio:_o,restrictEdges:To,restrict:Eo,restrictRect:Io,restrictSize:Po,snapEdges:le({start:function(t){var e=t.edges;return e?(t.state.targetFields=t.state.targetFields||[[e.left?"left":"right",e.top?"top":"bottom"]],Ke.start(t)):null},set:Ke.set,defaults:z(ye(Ke.defaults),{targets:void 0,range:void 0,offset:{x:0,y:0}})},"snapEdges"),snap:Do,snapSize:$o,spring:Ye,avoid:Ye,transform:Ye,rubberband:Ye},Co={id:"modifiers",install:function(t){var e=t.interactStatic;for(var n in t.usePlugin(an),t.usePlugin(yo),e.modifiers=kt,kt){var o=kt[n],r=o._defaults,i=o._methods;r._methods=i,t.defaults.perAction[n]=r}}},Oo=Co,Tn=(function(t){g(n,t);var e=D(n);function n(o,r,i,s,c,d){var f;if(a(this,n),Ae(k(f=e.call(this,c)),i),i!==r&&Ae(k(f),r),f.timeStamp=d,f.originalEvent=i,f.type=o,f.pointerId=_e(r),f.pointerType=Rt(r),f.target=s,f.currentTarget=null,o==="tap"){var p=c.getPointerIndex(r);f.dt=f.timeStamp-c.pointers[p].downTime;var v=f.timeStamp-c.tapTime;f.double=!!c.prevTap&&c.prevTap.type!=="doubletap"&&c.prevTap.target===f.target&&v<500}else o==="doubletap"&&(f.dt=r.timeStamp-c.tapTime,f.double=!0);return f}return u(n,[{key:"_subtractOrigin",value:function(o){var r=o.x,i=o.y;return this.pageX-=r,this.pageY-=i,this.clientX-=r,this.clientY-=i,this}},{key:"_addOrigin",value:function(o){var r=o.x,i=o.y;return this.pageX+=r,this.pageY+=i,this.clientX+=r,this.clientY+=i,this}},{key:"preventDefault",value:function(){this.originalEvent.preventDefault()}}]),n})(Fe),Pe={id:"pointer-events/base",before:["inertia","modifiers","auto-start","actions"],install:function(t){t.pointerEvents=Pe,t.defaults.actions.pointerEvents=Pe.defaults,z(t.actions.phaselessTypes,Pe.types)},listeners:{"interactions:new":function(t){var e=t.interaction;e.prevTap=null,e.tapTime=0},"interactions:update-pointer":function(t){var e=t.down,n=t.pointerInfo;!e&&n.hold||(n.hold={duration:1/0,timeout:null})},"interactions:move":function(t,e){var n=t.interaction,o=t.pointer,r=t.event,i=t.eventTarget;t.duplicate||n.pointerIsDown&&!n.pointerWasMoved||(n.pointerIsDown&&_t(t),pe({interaction:n,pointer:o,event:r,eventTarget:i,type:"move"},e))},"interactions:down":function(t,e){(function(n,o){for(var r=n.interaction,i=n.pointer,s=n.event,c=n.eventTarget,d=n.pointerIndex,f=r.pointers[d].hold,p=Ct(c),v={interaction:r,pointer:i,event:s,eventTarget:c,type:"hold",targets:[],path:p,node:null},m=0;m<p.length;m++){var _=p[m];v.node=_,o.fire("pointerEvents:collect-targets",v)}if(v.targets.length){for(var x=1/0,E=0,S=v.targets;E<S.length;E++){var T=S[E].eventable.options.holdDuration;T<x&&(x=T)}f.duration=x,f.timeout=setTimeout((function(){pe({interaction:r,eventTarget:c,pointer:i,event:s,type:"hold"},o)}),x)}})(t,e),pe(t,e)},"interactions:up":function(t,e){_t(t),pe(t,e),(function(n,o){var r=n.interaction,i=n.pointer,s=n.event,c=n.eventTarget;r.pointerWasMoved||pe({interaction:r,eventTarget:c,pointer:i,event:s,type:"tap"},o)})(t,e)},"interactions:cancel":function(t,e){_t(t),pe(t,e)}},PointerEvent:Tn,fire:pe,collectEventTargets:Sn,defaults:{holdDuration:600,ignoreFrom:null,allowFrom:null,origin:{x:0,y:0}},types:{down:!0,move:!0,up:!0,cancel:!0,tap:!0,doubletap:!0,hold:!0}};function pe(t,e){var n=t.interaction,o=t.pointer,r=t.event,i=t.eventTarget,s=t.type,c=t.targets,d=c===void 0?Sn(t,e):c,f=new Tn(s,o,r,i,n,e.now());e.fire("pointerEvents:new",{pointerEvent:f});for(var p={interaction:n,pointer:o,event:r,eventTarget:i,targets:d,type:s,pointerEvent:f},v=0;v<d.length;v++){var m=d[v];for(var _ in m.props||{})f[_]=m.props[_];var x=we(m.eventable,m.node);if(f._subtractOrigin(x),f.eventable=m.eventable,f.currentTarget=m.node,m.eventable.fire(f),f._addOrigin(x),f.immediatePropagationStopped||f.propagationStopped&&v+1<d.length&&d[v+1].node!==f.currentTarget)break}if(e.fire("pointerEvents:fired",p),s==="tap"){var E=f.double?pe({interaction:n,pointer:o,event:r,eventTarget:i,type:"doubletap"},e):f;n.prevTap=E,n.tapTime=E.timeStamp}return f}function Sn(t,e){var n=t.interaction,o=t.pointer,r=t.event,i=t.eventTarget,s=t.type,c=n.getPointerIndex(o),d=n.pointers[c];if(s==="tap"&&(n.pointerWasMoved||!d||d.downTarget!==i))return[];for(var f=Ct(i),p={interaction:n,pointer:o,event:r,eventTarget:i,type:s,path:f,targets:[],node:null},v=0;v<f.length;v++){var m=f[v];p.node=m,e.fire("pointerEvents:collect-targets",p)}return s==="hold"&&(p.targets=p.targets.filter((function(_){var x,E;return _.eventable.options.holdDuration===((x=n.pointers[c])==null||(E=x.hold)==null?void 0:E.duration)}))),p.targets}function _t(t){var e=t.interaction,n=t.pointerIndex,o=e.pointers[n].hold;o&&o.timeout&&(clearTimeout(o.timeout),o.timeout=null)}var Lo=Object.freeze({__proto__:null,default:Pe});function Ao(t){var e=t.interaction;e.holdIntervalHandle&&(clearInterval(e.holdIntervalHandle),e.holdIntervalHandle=null)}var No={id:"pointer-events/holdRepeat",install:function(t){t.usePlugin(Pe);var e=t.pointerEvents;e.defaults.holdRepeatInterval=0,e.types.holdrepeat=t.actions.phaselessTypes.holdrepeat=!0},listeners:["move","up","cancel","endall"].reduce((function(t,e){return t["pointerEvents:".concat(e)]=Ao,t}),{"pointerEvents:new":function(t){var e=t.pointerEvent;e.type==="hold"&&(e.count=(e.count||0)+1)},"pointerEvents:fired":function(t,e){var n=t.interaction,o=t.pointerEvent,r=t.eventTarget,i=t.targets;if(o.type==="hold"&&i.length){var s=i[0].eventable.options.holdRepeatInterval;s<=0||(n.holdIntervalHandle=setTimeout((function(){e.pointerEvents.fire({interaction:n,eventTarget:r,type:"hold",pointer:o,event:o},e)}),s))}}})},jo=No,Fo={id:"pointer-events/interactableTargets",install:function(t){var e=t.Interactable;e.prototype.pointerEvents=function(o){return z(this.events.options,o),this};var n=e.prototype._backCompatOption;e.prototype._backCompatOption=function(o,r){var i=n.call(this,o,r);return i===this&&(this.events.options[o]=r),i}},listeners:{"pointerEvents:collect-targets":function(t,e){var n=t.targets,o=t.node,r=t.type,i=t.eventTarget;e.interactables.forEachMatch(o,(function(s){var c=s.events,d=c.options;c.types[r]&&c.types[r].length&&s.testIgnoreAllow(d,o,i)&&n.push({node:o,eventable:c,props:{interactable:s}})}))},"interactable:new":function(t){var e=t.interactable;e.events.getRect=function(n){return e.getRect(n)}},"interactable:set":function(t,e){var n=t.interactable,o=t.options;z(n.events.options,e.pointerEvents.defaults),z(n.events.options,o.pointerEvents||{})}}},qo=Fo,Ro={id:"pointer-events",install:function(t){t.usePlugin(Lo),t.usePlugin(jo),t.usePlugin(qo)}},Ho=Ro,Go={id:"reflow",install:function(t){var e=t.Interactable;t.actions.phases.reflow=!0,e.prototype.reflow=function(n){return(function(o,r,i){for(var s=o.getAllElements(),c=i.window.Promise,d=c?[]:null,f=function(){var v=s[p],m=o.getRect(v);if(!m)return 1;var _,x=Te(i.interactions.list,(function(T){return T.interacting()&&T.interactable===o&&T.element===v&&T.prepared.name===r.name}));if(x)x.move(),d&&(_=x._reflowPromise||new c((function(T){x._reflowResolve=T})));else{var E=tt(m),S=(function(T){return{coords:T,get page(){return this.coords.page},get client(){return this.coords.client},get timeStamp(){return this.coords.timeStamp},get pageX(){return this.coords.page.x},get pageY(){return this.coords.page.y},get clientX(){return this.coords.client.x},get clientY(){return this.coords.client.y},get pointerId(){return this.coords.pointerId},get target(){return this.coords.target},get type(){return this.coords.type},get pointerType(){return this.coords.pointerType},get buttons(){return this.coords.buttons},preventDefault:function(){}}})({page:{x:E.x,y:E.y},client:{x:E.x,y:E.y},timeStamp:i.now()});_=(function(T,I,$,A,M){var O=T.interactions.new({pointerType:"reflow"}),Y={interaction:O,event:M,pointer:M,eventTarget:$,phase:"reflow"};O.interactable=I,O.element=$,O.prevEvent=M,O.updatePointer(M,M,$,!0),Nt(O.coords.delta),pt(O.prepared,A),O._doPhase(Y);var F=T.window,K=F.Promise,ee=K?new K((function(ae){O._reflowResolve=ae})):void 0;return O._reflowPromise=ee,O.start(A,I,$),O._interacting?(O.move(Y),O.end(M)):(O.stop(),O._reflowResolve()),O.removePointer(M,M),ee})(i,o,v,r,S)}d&&d.push(_)},p=0;p<s.length&&!f();p++);return d&&c.all(d).then((function(){return o}))})(this,n,t)}},listeners:{"interactions:stop":function(t,e){var n=t.interaction;n.pointerType==="reflow"&&(n._reflowResolve&&n._reflowResolve(),(function(o,r){o.splice(o.indexOf(r),1)})(e.interactions.list,n))}}},Bo=Go;if(B.use(rn),B.use(pn),B.use(Ho),B.use(ao),B.use(Oo),B.use(Un),B.use(Nn),B.use(Fn),B.use(Bo),B.default=B,(typeof ve>"u"?"undefined":R(ve))==="object"&&ve)try{ve.exports=B}catch{}return B.default=B,B}))});var nr={};Zo(nr,{workshopBoard:()=>Tt});var X=Jo(zn()),te={yellow:"#fbbf24",blue:"#60a5fa",green:"#4ade80",pink:"#f472b6",purple:"#a78bfa",orange:"#fb923c",teal:"#2dd4bf",red:"#f87171"};var Mn={note:{width:200,height:150,color:"yellow"},text:{width:300,height:40,color:"yellow"},section:{width:500,height:400,color:"yellow"},shape:{width:120,height:120,color:"blue"},connector:{width:0,height:0,color:"blue"},kanban:{width:600,height:400,color:"blue"},image:{width:300,height:300,color:"yellow"},image_grid:{width:500,height:400,color:"yellow"},video:{width:480,height:300,color:"blue"}},W='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:12px;height:12px;"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>';function Tt({notes:N=[],canvasBlocks:j=[],gridLayout:R={}}={}){return{panX:0,panY:0,scale:1,_isPanning:!1,_panStart:null,_panButton:-1,_spaceDown:!1,_listeners:[],_saveTimers:{},_textTimers:{},_kanbanTimers:{},_nextTempId:-1,_pendingUploadTarget:null,_mediaTimers:{},colorPickerOpen:null,_connectorMode:!1,_connectorFrom:null,_svgLayer:null,colors:Object.keys(te),isFullscreen:!1,init(){this._initialized||(this._initialized=!0,this.$nextTick(()=>{let a=document.createElementNS("http://www.w3.org/2000/svg","svg");a.classList.add("workshop-connectors-layer"),a.setAttribute("style","position:absolute;inset:0;width:100%;height:100%;pointer-events:none;overflow:visible;"),a.innerHTML=`<defs>
          <marker id="arrowhead" markerWidth="10" markerHeight="7" refX="9" refY="3.5" orient="auto">
            <polygon points="0 0, 10 3.5, 0 7" fill="#6b7280"/>
          </marker>
        </defs>`,this.$refs.board.prepend(a),this._svgLayer=a;let l=document.createElement("input");l.type="file",l.id="workshop-file-input",l.style.display="none",l.accept="image/*,video/*",this.$refs.board.parentElement.appendChild(l),this._fileInput=l,l.addEventListener("change",u=>{let h=u.target.files[0];h&&(this.$wire.upload("workshopFile",h,()=>{},()=>{console.error("Upload failed")},g=>{}),l.value="")}),this.$wire.on("workshop-file-uploaded",([u])=>{let h=this._pendingUploadTarget;if(this._pendingUploadTarget=null,!h||!h.noteEl)return;let g=h.noteEl,w=parseInt(g.dataset.noteId);h.type==="image"?(this._applyImageUpload(g,u),w>0&&this._saveMediaMetadata(g,w)):h.type==="image_grid"?(this._applyImageGridUpload(g,u),w>0&&this._saveMediaMetadata(g,w)):h.type==="video"&&(this._applyVideoUpload(g,u),w>0&&this._saveMediaMetadata(g,w))}),this._renderNotes(N),this._initPanZoom(),this._initInteract(),this._fitGrid(),this._on(document,"keydown",u=>{if(u.key==="Escape"){if(this._connectorMode){u.preventDefault(),this._cancelConnectorMode();return}this.isFullscreen&&(u.preventDefault(),this.isFullscreen=!1,this._fitAfterDelay())}},!1)}))},destroy(){this._listeners.forEach(([a,l,u,h])=>a.removeEventListener(l,u,h)),this._listeners=[],(0,X.default)(".workshop-note").unset(),(0,X.default)(".workshop-text").unset(),(0,X.default)(".workshop-section").unset(),(0,X.default)(".workshop-shape").unset(),(0,X.default)(".workshop-kanban").unset(),(0,X.default)(".workshop-image").unset(),(0,X.default)(".workshop-image-grid").unset(),(0,X.default)(".workshop-video").unset(),(0,X.default)(".workshop-canvas-background").unset(),this._fileInput&&(this._fileInput.remove(),this._fileInput=null)},_on(a,l,u,h){a.addEventListener(l,u,h),this._listeners.push([a,l,u,h])},_renderNotes(a){let l=this.$refs.board;a.forEach(u=>l.appendChild(this._createNoteEl(u)))},_createNoteEl(a){switch(a.type||"note"){case"text":return this._createTextEl(a);case"section":return this._createSectionEl(a);case"shape":return this._createShapeEl(a);case"connector":return this._createConnectorEl(a);case"kanban":return this._createKanbanEl(a);case"image":return this._createImageEl(a);case"image_grid":return this._createImageGridEl(a);case"video":return this._createVideoEl(a);default:return this._createStickyEl(a)}},_createStickyEl(a){let l=a.color||"yellow",u=a.x??0,h=a.y??0,g=a.width??200,w=a.height??150,b=document.createElement("div");return b.className=`workshop-note workshop-note-${l}`,b.dataset.noteId=a.id,b.dataset.noteType="note",b.dataset.x=u,b.dataset.y=h,b.style.cssText=`width:${g}px;height:${w}px;transform:translate(${u}px,${h}px);`,b.innerHTML=`
        <div class="drag-handle">
          <div style="display:flex;align-items:center;gap:6px;">
            <div class="drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></div>
            ${this._colorDotHTML(l)}
          </div>
          <button class="note-delete" data-action="delete" title="Loeschen">${W}</button>
        </div>
        <div class="note-body">
          <input type="text" value="${this._esc(a.title||"")}" placeholder="Titel..." />
          <textarea placeholder="Notiz...">${this._esc(a.content||"")}</textarea>
        </div>
        <div class="resize-handle"></div>
      `,this._bindNoteEvents(b),this._bindTextSave(b),b},_createTextEl(a){let l=a.x??0,u=a.y??0,h=a.width??300,g=a.height??40,w=a.metadata?.fontSize||Math.max(14,Math.round(h/12)),b=document.createElement("div");return b.className="workshop-text",b.dataset.noteId=a.id,b.dataset.noteType="text",b.dataset.x=l,b.dataset.y=u,b.style.cssText=`width:${h}px;height:${g}px;transform:translate(${l}px,${u}px);`,b.innerHTML=`
        <div class="drag-handle text-drag-handle">
          <div class="drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></div>
          <div class="text-body">
            <input type="text" value="${this._esc(a.title||"")}" placeholder="Text eingeben..." style="font-size:${w}px;" />
          </div>
          <button class="note-delete" data-action="delete" title="Loeschen">${W}</button>
        </div>
        <div class="resize-handle"></div>
      `,this._bindDeleteEvent(b),this._bindTextInputSave(b),b},_createSectionEl(a){let l=a.color||"yellow",u=a.x??0,h=a.y??0,g=a.width??500,w=a.height??400,b=document.createElement("div");return b.className=`workshop-section workshop-section-${l}`,b.dataset.noteId=a.id,b.dataset.noteType="section",b.dataset.x=u,b.dataset.y=h,b.style.cssText=`width:${g}px;height:${w}px;transform:translate(${u}px,${h}px);border-color:${te[l]||te.yellow};`,b.innerHTML=`
        <div class="drag-handle section-drag-handle">
          <div style="display:flex;align-items:center;gap:6px;flex:1;min-width:0;">
            <div class="drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></div>
            ${this._colorDotHTML(l)}
            <input type="text" class="section-title" value="${this._esc(a.title||"")}" placeholder="Section..." />
          </div>
          <button class="note-delete" data-action="delete" title="Loeschen">${W}</button>
        </div>
        <div class="resize-handle"></div>
      `,this._bindNoteEvents(b),this._bindSectionTextSave(b),b},_createShapeEl(a){let l=a.color||"blue",u=a.metadata?.shape||"rect",h=a.x??0,g=a.y??0,w=a.width??120,b=a.height??120,k=document.createElement("div");return k.className=`workshop-shape workshop-shape-${u} workshop-shape-color-${l}`,k.dataset.noteId=a.id,k.dataset.noteType="shape",k.dataset.shape=u,k.dataset.x=h,k.dataset.y=g,k.style.cssText=`width:${w}px;height:${b}px;transform:translate(${h}px,${g}px);`,k.innerHTML=`
        <div class="shape-visual"></div>
        <div class="drag-handle shape-drag-handle">
          <div style="display:flex;align-items:center;gap:4px;">
            <div class="drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></div>
            ${this._colorDotHTML(l)}
            <button class="shape-toggle" data-action="toggle-shape" title="Form wechseln">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:10px;height:10px;"><path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H4.598a.75.75 0 00-.75.75v3.634a.75.75 0 001.5 0v-2.033l.312.312a7 7 0 0011.712-3.138.75.75 0 00-1.449-.39zm1.06-7.846a.75.75 0 00-1.5 0v2.034l-.312-.312A7 7 0 002.848 8.438a.75.75 0 001.449.39 5.5 5.5 0 019.201-2.466l.312.311H11.38a.75.75 0 000 1.5h3.634a.75.75 0 00.75-.75V3.578z" clip-rule="evenodd"/></svg>
            </button>
            <button class="note-delete" data-action="delete" title="Loeschen">${W}</button>
          </div>
        </div>
        <div class="shape-body">
          <input type="text" value="${this._esc(a.title||"")}" placeholder="..." />
        </div>
        <div class="resize-handle"></div>
      `,this._bindShapeEvents(k),this._bindShapeTextSave(k),k},_colorDotHTML(a){return`<div class="color-dot-wrap" style="position:relative;">
        <div class="color-dot" style="background:${te[a]||te.yellow};" data-action="color"></div>
        <div class="color-picker-dd" style="display:none;position:absolute;top:100%;left:0;margin-top:4px;padding:4px;background:white;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.15);border:1px solid #e5e7eb;z-index:50;gap:3px;flex-wrap:nowrap;">
          ${this.colors.map(l=>`<div class="color-dot${l===a?" active":""}" style="background:${te[l]};" data-pick-color="${l}"></div>`).join("")}
        </div>
      </div>`},_bindNoteEvents(a){a.addEventListener("click",l=>{if(this._handleConnectorClick(a)){l.stopPropagation();return}let u=l.target.closest("[data-action]")?.dataset.action,h=l.target.closest("[data-pick-color]")?.dataset.pickColor,g=parseInt(a.dataset.noteId);if(h){l.stopPropagation(),this._changeColor(a,g,h);return}if(u==="color"){l.stopPropagation(),this._toggleColorPicker(a);return}if(u==="delete"){l.stopPropagation(),confirm("Element loeschen?")&&this._deleteNote(a,g);return}})},_bindDeleteEvent(a){a.addEventListener("click",l=>{if(this._handleConnectorClick(a)){l.stopPropagation();return}let u=l.target.closest("[data-action]")?.dataset.action,h=parseInt(a.dataset.noteId);u==="delete"&&(l.stopPropagation(),confirm("Element loeschen?")&&this._deleteNote(a,h))})},_bindShapeEvents(a){a.addEventListener("click",l=>{if(this._handleConnectorClick(a)){l.stopPropagation();return}let u=l.target.closest("[data-action]")?.dataset.action,h=l.target.closest("[data-pick-color]")?.dataset.pickColor,g=parseInt(a.dataset.noteId);if(h){l.stopPropagation(),this._changeShapeColor(a,g,h);return}if(u==="color"){l.stopPropagation(),this._toggleColorPicker(a);return}if(u==="toggle-shape"){l.stopPropagation(),this._toggleShape(a,g);return}if(u==="delete"){l.stopPropagation(),confirm("Element loeschen?")&&this._deleteNote(a,g);return}})},_bindTextSave(a){let l=a.querySelector(".note-body input"),u=a.querySelector(".note-body textarea"),h=()=>{let g=parseInt(a.dataset.noteId);g<0||(clearTimeout(this._textTimers[g]),this._textTimers[g]=setTimeout(()=>{this.$wire.call("updateNoteText",g,l.value,u.value)},400))};l.addEventListener("blur",h),u.addEventListener("blur",h),l.addEventListener("keydown",g=>{g.key==="Enter"&&g.target.blur()})},_bindTextInputSave(a){let l=a.querySelector(".text-body input"),u=()=>{let h=parseInt(a.dataset.noteId);h<0||(clearTimeout(this._textTimers[h]),this._textTimers[h]=setTimeout(()=>{this.$wire.call("updateNoteText",h,l.value,"")},400))};l.addEventListener("blur",u),l.addEventListener("keydown",h=>{h.key==="Enter"&&h.target.blur()})},_bindSectionTextSave(a){let l=a.querySelector(".section-title"),u=()=>{let h=parseInt(a.dataset.noteId);h<0||(clearTimeout(this._textTimers[h]),this._textTimers[h]=setTimeout(()=>{this.$wire.call("updateNoteText",h,l.value,"")},400))};l.addEventListener("blur",u),l.addEventListener("keydown",h=>{h.key==="Enter"&&h.target.blur()})},_bindShapeTextSave(a){let l=a.querySelector(".shape-body input"),u=()=>{let h=parseInt(a.dataset.noteId);h<0||(clearTimeout(this._textTimers[h]),this._textTimers[h]=setTimeout(()=>{this.$wire.call("updateNoteText",h,l.value,"")},400))};l.addEventListener("blur",u),l.addEventListener("keydown",h=>{h.key==="Enter"&&h.target.blur()})},_createKanbanEl(a){let l=a.color||"blue",u=a.x??0,h=a.y??0,g=a.width??600,w=a.height??400,b=a.metadata?.columns||[],k=document.createElement("div");k.className=`workshop-kanban workshop-kanban-${l}`,k.dataset.noteId=a.id,k.dataset.noteType="kanban",k.dataset.x=u,k.dataset.y=h,k.style.cssText=`width:${g}px;height:${w}px;transform:translate(${u}px,${h}px);`,k._kanbanData={columns:JSON.parse(JSON.stringify(b))},k.innerHTML=`
        <div class="drag-handle kanban-drag-handle">
          <div style="display:flex;align-items:center;gap:6px;flex:1;min-width:0;">
            <div class="drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></div>
            ${this._colorDotHTML(l)}
            <input type="text" class="kanban-board-title" value="${this._esc(a.title||"")}" placeholder="Board..." />
          </div>
          <div style="display:flex;align-items:center;gap:4px;">
            <button class="kanban-add-col-btn" data-kanban-action="add-column" title="Spalte hinzufuegen">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:12px;height:12px;"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/></svg>
            </button>
            <button class="note-delete" data-action="delete" title="Loeschen">${W}</button>
          </div>
        </div>
        <div class="kanban-columns"></div>
        <div class="resize-handle"></div>
      `;let D=k.querySelector(".kanban-columns");return k._kanbanData.columns.forEach(C=>{D.appendChild(this._createKanbanColumnEl(k,C))}),this._bindKanbanEvents(k),this._bindNoteEvents(k),this._bindKanbanTitleSave(k),k},_createKanbanColumnEl(a,l){let u=document.createElement("div");u.className="kanban-column",u.dataset.colId=l.id;let h=l.cards?.length||0,g=l.wipLimit>0?`${h}/${l.wipLimit}`:`${h}`,w=l.wipLimit>0&&h>l.wipLimit;u.innerHTML=`
        <div class="kanban-column-header${w?" wip-exceeded":""}">
          <div style="display:flex;align-items:center;gap:4px;flex:1;min-width:0;">
            <input type="text" class="kanban-col-title" value="${this._esc(l.title||"")}" placeholder="Spalte..." />
            <span class="kanban-card-count">${g}</span>
          </div>
          <button class="kanban-col-delete" data-kanban-action="delete-column" data-col-id="${l.id}" title="Spalte loeschen">${W}</button>
        </div>
        <div class="kanban-cards"></div>
        <button class="kanban-add-card" data-kanban-action="add-card" data-col-id="${l.id}">+ Karte</button>
      `;let b=u.querySelector(".kanban-cards");(l.cards||[]).forEach(D=>{b.appendChild(this._createKanbanCardEl(a,D))}),this._bindKanbanDropZone(a,u);let k=u.querySelector(".kanban-col-title");return k.addEventListener("blur",()=>{l.title=k.value,this._saveKanbanMetadata(a)}),k.addEventListener("keydown",D=>{D.key==="Enter"&&D.target.blur()}),u},_createKanbanCardEl(a,l){let u=document.createElement("div");u.className="kanban-card",u.draggable=!0,u.dataset.cardId=l.id,u.innerHTML=`
        <div style="display:flex;align-items:center;gap:4px;">
          <input type="text" class="kanban-card-title" value="${this._esc(l.title||"")}" placeholder="Karte..." />
          <button class="kanban-card-delete" data-kanban-action="delete-card" data-card-id="${l.id}" title="Karte loeschen">${W}</button>
        </div>
      `,u.addEventListener("dragstart",g=>{g.stopPropagation();let w=u.closest(".kanban-column");g.dataTransfer.setData("application/kanban-card",JSON.stringify({cardId:l.id,sourceColId:w?.dataset.colId||""})),g.dataTransfer.effectAllowed="move",u.classList.add("kanban-card-dragging")}),u.addEventListener("dragend",()=>{u.classList.remove("kanban-card-dragging")});let h=u.querySelector(".kanban-card-title");return h.addEventListener("blur",()=>{l.title=h.value,this._saveKanbanMetadata(a)}),h.addEventListener("keydown",g=>{g.key==="Enter"&&g.target.blur()}),u},_bindKanbanDropZone(a,l){let u=l.querySelector(".kanban-cards");l.addEventListener("dragover",h=>{h.dataTransfer.types.includes("application/kanban-card")&&(h.preventDefault(),h.dataTransfer.dropEffect="move",l.classList.add("kanban-drop-target"))}),l.addEventListener("dragleave",h=>{l.contains(h.relatedTarget)||l.classList.remove("kanban-drop-target")}),l.addEventListener("drop",h=>{h.preventDefault(),l.classList.remove("kanban-drop-target");let g=h.dataTransfer.getData("application/kanban-card");if(!g)return;let{cardId:w,sourceColId:b}=JSON.parse(g),k=l.dataset.colId,D=a._kanbanData,C=D.columns.find(J=>J.id===b),L=D.columns.find(J=>J.id===k);if(!C||!L||L.wipLimit>0&&L.cards.length>=L.wipLimit&&b!==k)return;let q=C.cards.findIndex(J=>J.id===w);if(q===-1)return;let[Z]=C.cards.splice(q,1);L.cards.push(Z);let H=a.querySelector(`[data-card-id="${w}"]`);H&&u.appendChild(H),this._updateKanbanCounts(a),this._saveKanbanMetadata(a)})},_bindKanbanEvents(a){a.addEventListener("click",l=>{let u=l.target.closest("[data-kanban-action]")?.dataset.kanbanAction;if(u){if(u==="add-column"){l.stopPropagation();let h={id:"col_"+Date.now().toString(36),title:"",wipLimit:0,cards:[]};a._kanbanData.columns.push(h);let g=this._createKanbanColumnEl(a,h);a.querySelector(".kanban-columns").appendChild(g),this._saveKanbanMetadata(a),setTimeout(()=>g.querySelector(".kanban-col-title")?.focus(),50);return}if(u==="add-card"){l.stopPropagation();let h=l.target.closest("[data-col-id]")?.dataset.colId,g=a._kanbanData.columns.find(D=>D.id===h);if(!g||g.wipLimit>0&&g.cards.length>=g.wipLimit)return;let w={id:"card_"+Date.now().toString(36),title:"",content:""};g.cards.push(w);let b=a.querySelector(`[data-col-id="${h}"]`),k=this._createKanbanCardEl(a,w);b.querySelector(".kanban-cards").appendChild(k),this._updateKanbanCounts(a),this._saveKanbanMetadata(a),setTimeout(()=>k.querySelector(".kanban-card-title")?.focus(),50);return}if(u==="delete-column"){l.stopPropagation();let h=l.target.closest("[data-col-id]")?.dataset.colId;a._kanbanData.columns=a._kanbanData.columns.filter(g=>g.id!==h),a.querySelector(`[data-col-id="${h}"]`)?.remove(),this._saveKanbanMetadata(a);return}if(u==="delete-card"){l.stopPropagation();let h=l.target.closest("[data-card-id]")?.dataset.cardId;for(let g of a._kanbanData.columns)g.cards=g.cards.filter(w=>w.id!==h);a.querySelector(`[data-card-id="${h}"]`)?.remove(),this._updateKanbanCounts(a),this._saveKanbanMetadata(a);return}}})},_bindKanbanTitleSave(a){let l=a.querySelector(".kanban-board-title"),u=()=>{let h=parseInt(a.dataset.noteId);h<0||(clearTimeout(this._textTimers[h]),this._textTimers[h]=setTimeout(()=>{this.$wire.call("updateNoteText",h,l.value,"")},400))};l.addEventListener("blur",u),l.addEventListener("keydown",h=>{h.key==="Enter"&&h.target.blur()})},_saveKanbanMetadata(a){let l=parseInt(a.dataset.noteId);l<0||(clearTimeout(this._kanbanTimers[l]),this._kanbanTimers[l]=setTimeout(()=>{this.$wire.call("updateNoteMetadata",l,{columns:a._kanbanData.columns})},400))},_updateKanbanCounts(a){a._kanbanData.columns.forEach(u=>{let h=a.querySelector(`[data-col-id="${u.id}"]`);if(!h)return;let g=u.cards.length,w=u.wipLimit>0?`${g}/${u.wipLimit}`:`${g}`,b=h.querySelector(".kanban-column-header"),k=h.querySelector(".kanban-card-count");k&&(k.textContent=w),b&&b.classList.toggle("wip-exceeded",u.wipLimit>0&&g>u.wipLimit)})},_createImageEl(a){let l=a.color||"yellow",u=a.x??0,h=a.y??0,g=a.width??300,w=a.height??300,b=a.metadata||{},k=document.createElement("div");k.className=`workshop-image workshop-image-${l}`,k.dataset.noteId=a.id,k.dataset.noteType="image",k.dataset.x=u,k.dataset.y=h,k.style.cssText=`width:${g}px;height:${w}px;transform:translate(${u}px,${h}px);`,k._imageData={contextFileId:b.contextFileId||null,src:b.src||"",alt:b.alt||"",objectFit:b.objectFit||"cover"};let D=!!b.src;return k.innerHTML=`
        <div class="drag-handle image-drag-handle">
          <div style="display:flex;align-items:center;gap:6px;">
            <div class="drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></div>
            ${this._colorDotHTML(l)}
          </div>
          <div style="display:flex;align-items:center;gap:4px;">
            <input type="text" class="image-alt-input" value="${this._esc(b.alt||"")}" placeholder="Alt..." title="Bildbeschreibung" />
            <button class="note-delete" data-action="delete" title="Loeschen">${W}</button>
          </div>
        </div>
        <div class="image-container">
          ${D?`<img src="${this._esc(b.src)}" alt="${this._esc(b.alt||"")}" style="object-fit:${b.objectFit||"cover"};" />`:`<div class="image-upload-zone" data-action="upload-image">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:32px;height:32px;"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" /></svg>
                <span>Bild hochladen</span>
              </div>`}
        </div>
        <div class="resize-handle"></div>
      `,this._bindNoteEvents(k),this._bindImageEvents(k),k},_bindImageEvents(a){a.addEventListener("click",u=>{u.target.closest('[data-action="upload-image"]')&&(u.stopPropagation(),this._pendingUploadTarget={noteEl:a,type:"image"},this._fileInput.accept="image/*",this._fileInput.click())});let l=a.querySelector(".image-alt-input");l&&(l.addEventListener("blur",()=>{a._imageData.alt=l.value;let u=a.querySelector(".image-container img");u&&(u.alt=l.value);let h=parseInt(a.dataset.noteId);h>0&&this._saveMediaMetadata(a,h)}),l.addEventListener("keydown",u=>{u.key==="Enter"&&u.target.blur()}))},_applyImageUpload(a,l){a._imageData.contextFileId=l.contextFileId,a._imageData.src=l.url;let u=a.querySelector(".image-container");u.innerHTML=`<img src="${this._esc(l.url)}" alt="${this._esc(a._imageData.alt)}" style="object-fit:${a._imageData.objectFit};" />`},_createImageGridEl(a){let l=a.color||"yellow",u=a.x??0,h=a.y??0,g=a.width??500,w=a.height??400,b=a.metadata||{},k=b.images||[],D=b.columns||2,C=b.gap||4,L=document.createElement("div");L.className=`workshop-image-grid workshop-image-grid-${l}`,L.dataset.noteId=a.id,L.dataset.noteType="image_grid",L.dataset.x=u,L.dataset.y=h,L.style.cssText=`width:${g}px;height:${w}px;transform:translate(${u}px,${h}px);`,L._imageGridData={images:JSON.parse(JSON.stringify(k)),columns:D,gap:C},L.innerHTML=`
        <div class="drag-handle image-grid-drag-handle">
          <div style="display:flex;align-items:center;gap:6px;">
            <div class="drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></div>
            ${this._colorDotHTML(l)}
          </div>
          <div style="display:flex;align-items:center;gap:4px;">
            <div class="image-grid-cols-control">
              <button data-grid-action="cols-dec" title="Weniger Spalten">-</button>
              <span class="image-grid-cols-count">${D}</span>
              <button data-grid-action="cols-inc" title="Mehr Spalten">+</button>
            </div>
            <button class="image-grid-add-btn" data-grid-action="add-image" title="Bild hinzufuegen">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:12px;height:12px;"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/></svg>
            </button>
            <button class="note-delete" data-action="delete" title="Loeschen">${W}</button>
          </div>
        </div>
        <div class="image-grid-container" style="grid-template-columns:repeat(${D},1fr);gap:${C}px;"></div>
        <div class="resize-handle"></div>
      `;let q=L.querySelector(".image-grid-container");return k.forEach(Z=>q.appendChild(this._createGridItemEl(L,Z))),this._bindNoteEvents(L),this._bindImageGridEvents(L),L},_createGridItemEl(a,l){let u=document.createElement("div");return u.className="image-grid-item",u.dataset.imageId=l.id,u.innerHTML=`
        <img src="${this._esc(l.src)}" alt="${this._esc(l.alt||"")}" />
        <button class="image-grid-item-delete" data-grid-action="delete-image" data-image-id="${l.id}" title="Entfernen">${W}</button>
      `,u},_bindImageGridEvents(a){a.addEventListener("click",l=>{let u=l.target.closest("[data-grid-action]")?.dataset.gridAction;if(u){if(u==="add-image"){l.stopPropagation(),this._pendingUploadTarget={noteEl:a,type:"image_grid"},this._fileInput.accept="image/*",this._fileInput.click();return}if(u==="cols-dec"){if(l.stopPropagation(),a._imageGridData.columns>1){a._imageGridData.columns--,this._updateGridLayout(a);let h=parseInt(a.dataset.noteId);h>0&&this._saveMediaMetadata(a,h)}return}if(u==="cols-inc"){if(l.stopPropagation(),a._imageGridData.columns<6){a._imageGridData.columns++,this._updateGridLayout(a);let h=parseInt(a.dataset.noteId);h>0&&this._saveMediaMetadata(a,h)}return}if(u==="delete-image"){l.stopPropagation();let h=l.target.closest("[data-image-id]")?.dataset.imageId;a._imageGridData.images=a._imageGridData.images.filter(w=>w.id!==h),a.querySelector(`[data-image-id="${h}"]`)?.closest(".image-grid-item")?.remove();let g=parseInt(a.dataset.noteId);g>0&&this._saveMediaMetadata(a,g);return}}})},_updateGridLayout(a){let l=a.querySelector(".image-grid-container");l.style.gridTemplateColumns=`repeat(${a._imageGridData.columns},1fr)`,l.style.gap=`${a._imageGridData.gap}px`,a.querySelector(".image-grid-cols-count").textContent=a._imageGridData.columns},_applyImageGridUpload(a,l){let h={id:"img_"+Date.now().toString(36),contextFileId:l.contextFileId,src:l.url,alt:""};a._imageGridData.images.push(h),a.querySelector(".image-grid-container").appendChild(this._createGridItemEl(a,h))},_createVideoEl(a){let l=a.color||"blue",u=a.x??0,h=a.y??0,g=a.width??480,w=a.height??300,b=a.metadata||{},k=document.createElement("div");k.className=`workshop-video workshop-video-${l}`,k.dataset.noteId=a.id,k.dataset.noteType="video",k.dataset.x=u,k.dataset.y=h,k.style.cssText=`width:${g}px;height:${w}px;transform:translate(${u}px,${h}px);`,k._videoData={src:b.src||"",provider:b.provider||"",embedUrl:b.embedUrl||"",contextFileId:b.contextFileId||null};let D=!!(b.embedUrl||b.src),C;return b.embedUrl?C=`<iframe src="${this._esc(b.embedUrl)}" allowfullscreen allow="autoplay; encrypted-media"></iframe>`:b.src&&b.provider==="upload"?C=`<video src="${this._esc(b.src)}" controls></video>`:b.src?C=`<video src="${this._esc(b.src)}" controls></video>`:C=`
          <div class="video-upload-zone">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:32px;height:32px;"><path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
            <input type="text" class="video-url-input" placeholder="YouTube/Vimeo URL einfuegen..." />
            <div style="display:flex;align-items:center;gap:6px;margin-top:4px;">
              <span style="font-size:10px;color:#9ca3af;">oder</span>
              <button class="video-upload-btn" data-action="upload-video">Datei hochladen</button>
            </div>
          </div>
        `,k.innerHTML=`
        <div class="drag-handle video-drag-handle">
          <div style="display:flex;align-items:center;gap:6px;">
            <div class="drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></div>
            ${this._colorDotHTML(l)}
          </div>
          <button class="note-delete" data-action="delete" title="Loeschen">${W}</button>
        </div>
        <div class="video-container">${C}</div>
        <div class="resize-handle"></div>
      `,this._bindNoteEvents(k),this._bindVideoEvents(k),k},_bindVideoEvents(a){let l=a.querySelector(".video-url-input");if(l){let u=()=>{let h=l.value.trim();if(!h)return;let g=this._parseVideoUrl(h);a._videoData={...a._videoData,...g};let w=a.querySelector(".video-container");g.embedUrl?w.innerHTML=`<iframe src="${this._esc(g.embedUrl)}" allowfullscreen allow="autoplay; encrypted-media"></iframe>`:g.src&&(w.innerHTML=`<video src="${this._esc(g.src)}" controls></video>`);let b=parseInt(a.dataset.noteId);b>0&&this._saveMediaMetadata(a,b)};l.addEventListener("keydown",h=>{h.key==="Enter"&&(h.preventDefault(),u())}),l.addEventListener("blur",u)}a.addEventListener("click",u=>{u.target.closest('[data-action="upload-video"]')&&(u.stopPropagation(),this._pendingUploadTarget={noteEl:a,type:"video"},this._fileInput.accept="video/*",this._fileInput.click())})},_applyVideoUpload(a,l){a._videoData={src:l.url,provider:"upload",embedUrl:"",contextFileId:l.contextFileId};let u=a.querySelector(".video-container");u.innerHTML=`<video src="${this._esc(l.url)}" controls></video>`},_parseVideoUrl(a){let l=a.match(/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/);return l?{provider:"youtube",embedUrl:`https://www.youtube.com/embed/${l[1]}`,src:a}:(l=a.match(/vimeo\.com\/(\d+)/),l?{provider:"vimeo",embedUrl:`https://player.vimeo.com/video/${l[1]}`,src:a}:{provider:"direct",src:a,embedUrl:""})},_saveMediaMetadata(a,l){clearTimeout(this._mediaTimers[l]),this._mediaTimers[l]=setTimeout(()=>{let u=a.dataset.noteType,h={};u==="image"?h={...a._imageData}:u==="image_grid"?h={images:a._imageGridData.images,columns:a._imageGridData.columns,gap:a._imageGridData.gap}:u==="video"&&(h={...a._videoData}),this.$wire.call("updateNoteMetadata",l,h)},400)},_esc(a){let l=document.createElement("div");return l.textContent=a,l.innerHTML},_applyTransform(){let a=this.$refs.board;a&&(a.style.transform=`translate(${this.panX}px,${this.panY}px) scale(${this.scale})`)},_screenToBoard(a,l){return{x:(a-this.panX)/this.scale,y:(l-this.panY)/this.scale}},_zoomTo(a,l,u){let h=this.$refs.board?.parentElement;if(!h)return;let g=h.getBoundingClientRect(),w=l-g.left,b=u-g.top,k=Math.max(.1,Math.min(4,a)),D=k/this.scale;this.panX=w-(w-this.panX)*D,this.panY=b-(b-this.panY)*D,this.scale=k,this._applyTransform()},_initPanZoom(){let a=this.$refs.board;if(!a)return;let l=a.parentElement;a.style.transformOrigin="0 0",this._on(l,"wheel",u=>{u.preventDefault(),u.ctrlKey||u.metaKey?this._zoomTo(this.scale*(1-u.deltaY*.003),u.clientX,u.clientY):(this.panX-=u.deltaX,this.panY-=u.deltaY,this._applyTransform())},{passive:!1}),this._on(l,"pointerdown",u=>{u.target.closest(".workshop-note, .workshop-text, .workshop-section, .workshop-shape, .workshop-kanban, .workshop-image, .workshop-image-grid, .workshop-video, .workshop-toolbar, .workshop-zoom-controls")||(u.button===1||u.button===0&&this._spaceDown)&&(this._isPanning=!0,this._panButton=u.button,this._panStart={x:u.clientX,y:u.clientY,px:this.panX,py:this.panY},l.style.cursor="grabbing",l.setPointerCapture(u.pointerId),u.preventDefault())},!1),this._on(l,"pointermove",u=>{if(this._isPanning&&this._panStart&&(this.panX=this._panStart.px+(u.clientX-this._panStart.x),this.panY=this._panStart.py+(u.clientY-this._panStart.y),this._applyTransform()),this._previewLine&&this._connectorMode&&this._connectorFrom){let h=this._screenToBoard(u.clientX,u.clientY);this._previewLine.setAttribute("x2",h.x),this._previewLine.setAttribute("y2",h.y)}},!1),this._on(l,"pointerup",u=>{this._isPanning&&(this._isPanning=!1,this._panStart=null,l.style.cursor=this._spaceDown?"grab":"")},!1),this._on(l,"contextmenu",u=>{this._panButton===1&&u.preventDefault()},!1),this._on(document,"keydown",u=>{u.code==="Space"&&!u.repeat&&!u.target.matches("input,textarea,[contenteditable]")&&(u.preventDefault(),this._spaceDown=!0,l.style.cursor="grab")},!1),this._on(document,"keyup",u=>{u.code==="Space"&&(this._spaceDown=!1,this._isPanning||(l.style.cursor=""))},!1),this._on(document,"click",()=>{a.querySelectorAll('.color-picker-dd[style*="flex"]').forEach(u=>u.style.display="none")},!1)},zoomIn(){this._zoomToCenter(this.scale*1.3)},zoomOut(){this._zoomToCenter(this.scale/1.3)},resetZoom(){this.scale=1,this.panX=0,this.panY=0,this._applyTransform()},fitToScreen(){this._fitGrid()},toggleFullscreen(){this.isFullscreen=!this.isFullscreen,this._fitAfterDelay()},_fitAfterDelay(){setTimeout(()=>this._fitGrid(),50),setTimeout(()=>this._fitGrid(),200),setTimeout(()=>this._fitGrid(),500)},_zoomToCenter(a){let l=this.$refs.board?.parentElement;if(!l)return;let u=l.getBoundingClientRect();this._zoomTo(a,u.left+l.clientWidth/2,u.top+l.clientHeight/2)},_fitGrid(){let a=this.$refs.board,l=a?.parentElement,u=a?.querySelector(".workshop-canvas-background");if(!u||!l)return;let h=u.offsetWidth,g=u.offsetHeight,w=u.offsetLeft,b=u.offsetTop,k=l.clientWidth,D=l.clientHeight,C=40,L=Math.min((k-C*2)/h,(D-C*2)/g,1);this.scale=L,this.panX=(k-h*L)/2-w*L,this.panY=(D-g*L)/2-b*L,this._applyTransform()},_initInteract(){let a=this,l=".workshop-note, .workshop-text, .workshop-section, .workshop-shape, .workshop-kanban, .workshop-image, .workshop-image-grid, .workshop-video",u=l;(0,X.default)(l).draggable({allowFrom:".drag-handle",ignoreFrom:"input, textarea, .note-delete, .shape-toggle, .color-dot, .color-picker-dd, .kanban-cards, .kanban-card, .kanban-column, .kanban-add-card, .kanban-add-col-btn, .kanban-col-title, .kanban-col-delete, .kanban-card-title, .kanban-card-delete, .image-upload-zone, .image-grid-add-btn, .image-grid-container, .image-grid-cols-control, .video-url-input, .video-upload-btn, .video-upload-zone, .video-container iframe, .video-container video",inertia:!1,listeners:{start(h){h.target.classList.add("dragging")},move(h){let g=h.target,w=(parseFloat(g.dataset.x)||0)+h.dx/a.scale,b=(parseFloat(g.dataset.y)||0)+h.dy/a.scale;g.style.transform=`translate(${w}px,${b}px)`,g.dataset.x=w,g.dataset.y=b,a._updateConnectors()},end(h){h.target.classList.remove("dragging");let g=h.target,w=parseInt(g.dataset.noteId);w<0||a._savePos(w,g)}}}),(0,X.default)(u).resizable({edges:{right:".resize-handle",bottom:".resize-handle"},modifiers:[X.default.modifiers.restrictSize({min:{width:60,height:30}})],listeners:{move(h){let g=h.target,w=parseFloat(g.dataset.x)||0,b=parseFloat(g.dataset.y)||0,k=h.rect.width/a.scale,D=h.rect.height/a.scale;if(g.style.width=k+"px",g.style.height=D+"px",w+=h.deltaRect.left/a.scale,b+=h.deltaRect.top/a.scale,g.style.transform=`translate(${w}px,${b}px)`,g.dataset.x=w,g.dataset.y=b,g.dataset.noteType==="text"){let C=Math.max(14,Math.round(k/12)),L=g.querySelector(".text-body input");L&&(L.style.fontSize=C+"px")}a._updateConnectors()},end(h){let g=h.target,w=parseInt(g.dataset.noteId);w<0||a._savePos(w,g)}}}),(0,X.default)(".workshop-canvas-background").resizable({edges:{right:!0,bottom:!0},modifiers:[X.default.modifiers.restrictSize({min:{width:400,height:300}})],listeners:{move(h){let g=h.target;g.style.width=h.rect.width/a.scale+"px",g.style.minHeight=h.rect.height/a.scale+"px"},end(h){let g=h.target,w=parseInt(g.style.width)||1200,b=parseInt(g.style.minHeight)||800;clearTimeout(a._gridSaveTimer),a._gridSaveTimer=setTimeout(()=>{a.$wire.call("updateWorkshopSettings",{gridWidth:w,gridHeight:b})},400)}}})},_savePos(a,l){clearTimeout(this._saveTimers[a]),this._saveTimers[a]=setTimeout(()=>{let u=this._detectBlock(l);this.$wire.call("updateNotePosition",a,{x:parseFloat(l.dataset.x)||0,y:parseFloat(l.dataset.y)||0,width:parseInt(l.style.width)||200,height:parseInt(l.style.height)||150,blockId:u})},300)},_detectBlock(a){let l=parseFloat(a.dataset.x)||0,u=parseFloat(a.dataset.y)||0,h=l+(parseInt(a.style.width)||0)/2,g=u+(parseInt(a.style.height)||0)/2,w=this.$refs.board?.querySelectorAll(".workshop-grid-block[data-block-id]");if(!w)return null;for(let b of w){let k=b.offsetParent,D=b.offsetLeft+(k?.offsetLeft||0),C=b.offsetTop+(k?.offsetTop||0),L=b.offsetWidth,q=b.offsetHeight;if(h>=D&&h<=D+L&&g>=C&&g<=C+q)return parseInt(b.dataset.blockId)||null}return null},addElement(a="note"){let l=this.$refs.board?.parentElement;if(!l)return;let u=l.getBoundingClientRect(),h=Mn[a]||Mn.note,g=(u.width/2-this.panX)/this.scale,w=(u.height/2-this.panY)/this.scale,b=Math.round(g-h.width/2),k=Math.round(w-h.height/2),D=this._nextTempId--,C=a==="shape"?{shape:"rect"}:a==="kanban"?{columns:[{id:"col_"+Date.now().toString(36)+"a",title:"To Do",wipLimit:0,cards:[]},{id:"col_"+Date.now().toString(36)+"b",title:"In Progress",wipLimit:3,cards:[]},{id:"col_"+Date.now().toString(36)+"c",title:"Done",wipLimit:0,cards:[]}]}:a==="image_grid"?{images:[],columns:2,gap:4}:null,L=this._createNoteEl({id:D,type:a,title:"",content:"",color:h.color,x:b,y:k,width:h.width,height:h.height,metadata:C});this.$refs.board.appendChild(L),this.$wire.call("addWorkshopNote",{x:b,y:k},a).then(()=>{this.$wire.call("getWorkshopNotes").then(q=>{if(Array.isArray(q)&&q.length>0){let Z=q.reduce((H,J)=>H.id>J.id?H:J);L.dataset.noteId=Z.id}})}),setTimeout(()=>{L.querySelector(".note-body input, .text-body input, .section-title, .shape-body input, .kanban-board-title, .image-alt-input, .video-url-input")?.focus()},100)},addNote(){this.addElement("note")},_deleteNote(a,l){if(a.remove(),this._svgLayer){let u=String(l);this._svgLayer.querySelectorAll(".workshop-connector-path").forEach(h=>{if(h.dataset.fromNoteId===u||h.dataset.toNoteId===u){let g=this._svgLayer.querySelector(`.connector-delete-fo[data-connector-id="${h.dataset.connectorId}"]`);g&&g.remove();let w=this.$refs.board.querySelector(`[data-note-id="${h.dataset.connectorId}"][data-note-type="connector"]`);w&&w.remove(),h.remove()}})}l>0&&this.$wire.call("deleteWorkshopNote",l)},_changeColor(a,l,u){let h=a.dataset.noteType||"note";h==="note"?a.className=a.className.replace(/workshop-note-\w+/,`workshop-note-${u}`):h==="section"?(a.className=a.className.replace(/workshop-section-\w+/,`workshop-section-${u}`),a.style.borderColor=te[u]||te.yellow):h==="kanban"?a.className=a.className.replace(/workshop-kanban-\w+/,`workshop-kanban-${u}`):h==="image"?a.className=a.className.replace(/workshop-image-\w+/,`workshop-image-${u}`):h==="image_grid"?a.className=a.className.replace(/workshop-image-grid-\w+/,`workshop-image-grid-${u}`):h==="video"&&(a.className=a.className.replace(/workshop-video-\w+/,`workshop-video-${u}`)),a.querySelector(".drag-handle .color-dot")?.setAttribute("style",`background:${te[u]}`),a.querySelector(".color-picker-dd").style.display="none",a.querySelectorAll(".color-picker-dd .color-dot").forEach(g=>{g.classList.toggle("active",g.dataset.pickColor===u)}),l>0&&this.$wire.call("updateNoteColor",l,u)},_changeShapeColor(a,l,u){a.className=a.className.replace(/workshop-shape-color-\w+/,`workshop-shape-color-${u}`);let h=a.querySelector(".shape-visual");h&&(h.className="shape-visual"),a.querySelector(".color-dot")?.setAttribute("style",`background:${te[u]}`),a.querySelector(".color-picker-dd").style.display="none",a.querySelectorAll(".color-picker-dd .color-dot").forEach(g=>{g.classList.toggle("active",g.dataset.pickColor===u)}),l>0&&this.$wire.call("updateNoteColor",l,u)},_toggleShape(a,l){let u=["rect","circle","diamond"],h=a.dataset.shape||"rect",g=u[(u.indexOf(h)+1)%u.length];a.dataset.shape=g,a.className=a.className.replace(/workshop-shape-(?:rect|circle|diamond)/,`workshop-shape-${g}`),l>0&&this.$wire.call("updateNoteMetadata",l,{shape:g})},_toggleColorPicker(a){let l=a.querySelector(".color-picker-dd");if(!l)return;let u=l.style.display==="flex";this.$refs.board.querySelectorAll(".color-picker-dd").forEach(h=>h.style.display="none"),l.style.display=u?"none":"flex"},_createConnectorEl(a){let l=a.metadata||{},u=document.createElement("div");if(u.style.cssText="position:absolute;width:0;height:0;pointer-events:none;",u.dataset.noteId=a.id,u.dataset.noteType="connector",u.dataset.fromNoteId=l.fromNoteId||"",u.dataset.toNoteId=l.toNoteId||"",this._svgLayer){let h=document.createElementNS("http://www.w3.org/2000/svg","path");h.classList.add("workshop-connector-path"),h.dataset.connectorId=a.id,h.dataset.fromNoteId=l.fromNoteId||"",h.dataset.toNoteId=l.toNoteId||"",h.setAttribute("marker-end","url(#arrowhead)"),h.setAttribute("fill","none"),h.setAttribute("stroke","#6b7280"),h.setAttribute("stroke-width","2"),h.style.pointerEvents="stroke",h.style.cursor="pointer",this._svgLayer.appendChild(h);let g=document.createElementNS("http://www.w3.org/2000/svg","foreignObject");g.classList.add("connector-delete-fo"),g.dataset.connectorId=a.id,g.setAttribute("width","24"),g.setAttribute("height","24"),g.style.overflow="visible",g.style.display="none",g.innerHTML=`<button xmlns="http://www.w3.org/1999/xhtml" class="connector-delete-btn" title="Loeschen">${W}</button>`,this._svgLayer.appendChild(g),h.addEventListener("mouseenter",()=>{g.style.display="",h.classList.add("hovered")}),h.addEventListener("mouseleave",()=>{setTimeout(()=>{g.matches(":hover")||(g.style.display="none",h.classList.remove("hovered"))},200)}),g.addEventListener("mouseleave",()=>{g.style.display="none",h.classList.remove("hovered")}),g.querySelector(".connector-delete-btn").addEventListener("click",w=>{w.stopPropagation();let b=parseInt(a.id);h.remove(),g.remove(),u.remove(),b>0&&this.$wire.call("deleteWorkshopNote",b)}),this._updateSingleConnector(h,g)}return u},_getAnchorPoint(a){let l=this.$refs.board.querySelector(`[data-note-id="${a}"]:not([data-note-type="connector"])`);if(!l)return null;let u=parseFloat(l.dataset.x)||0,h=parseFloat(l.dataset.y)||0,g=parseInt(l.style.width)||0,w=parseInt(l.style.height)||0;return{x:u,y:h,w:g,h:w,cx:u+g/2,cy:h+w/2}},_bestAnchors(a,l){let u=l.cx-a.cx,h=l.cy-a.cy,g,w;return Math.abs(u)>Math.abs(h)?u>0?(g={x:a.x+a.w,y:a.cy},w={x:l.x,y:l.cy}):(g={x:a.x,y:a.cy},w={x:l.x+l.w,y:l.cy}):h>0?(g={x:a.cx,y:a.y+a.h},w={x:l.cx,y:l.y}):(g={x:a.cx,y:a.y},w={x:l.cx,y:l.y+l.h}),{from:g,to:w}},_buildConnectorPath(a,l){let u=l.x-a.x,h=l.y-a.y,g=Math.sqrt(u*u+h*h),w=Math.min(g*.4,80),b,k,D,C;return Math.abs(u)>Math.abs(h)?(b=a.x+w*Math.sign(u),k=a.y,D=l.x-w*Math.sign(u),C=l.y):(b=a.x,k=a.y+w*Math.sign(h),D=l.x,C=l.y-w*Math.sign(h)),`M ${a.x},${a.y} C ${b},${k} ${D},${C} ${l.x},${l.y}`},_updateSingleConnector(a,l){let u=a.dataset.fromNoteId,h=a.dataset.toNoteId;if(!u||!h)return;let g=this._getAnchorPoint(u),w=this._getAnchorPoint(h);if(!g||!w){a.setAttribute("d","");return}let{from:b,to:k}=this._bestAnchors(g,w);a.setAttribute("d",this._buildConnectorPath(b,k));let D=(b.x+k.x)/2-12,C=(b.y+k.y)/2-12;l.setAttribute("x",D),l.setAttribute("y",C)},_updateConnectors(){if(!this._svgLayer)return;this._svgLayer.querySelectorAll(".workshop-connector-path").forEach(l=>{let u=this._svgLayer.querySelector(`.connector-delete-fo[data-connector-id="${l.dataset.connectorId}"]`);u&&this._updateSingleConnector(l,u)})},startConnectorMode(){if(this._connectorMode){this._cancelConnectorMode();return}this._connectorMode=!0,this._connectorFrom=null,this.$refs.board.parentElement.classList.add("connector-mode")},_cancelConnectorMode(){this._connectorMode=!1,this._connectorFrom=null,this.$refs.board.parentElement.classList.remove("connector-mode"),this.$refs.board.querySelectorAll(".connector-source-selected").forEach(a=>a.classList.remove("connector-source-selected")),this._previewLine&&(this._previewLine.remove(),this._previewLine=null)},_handleConnectorClick(a){if(!this._connectorMode)return!1;let l=parseInt(a.dataset.noteId);if(a.dataset.noteType==="connector")return!1;if(this._connectorFrom){if(l===this._connectorFrom)return!0;let h=this._connectorFrom,g=l,w=this._nextTempId--,b=this._createConnectorEl({id:w,type:"connector",title:"",content:"",color:"blue",x:0,y:0,width:0,height:0,metadata:{fromNoteId:h,toNoteId:g,style:"solid",arrowHead:"end"}});return this.$refs.board.appendChild(b),this.$wire.call("addConnector",h,g).then(()=>{this.$wire.call("getWorkshopNotes").then(k=>{if(Array.isArray(k)){let D=k.filter(C=>C.type==="connector");if(D.length>0){let C=D.reduce((Z,H)=>Z.id>H.id?Z:H);b.dataset.noteId=C.id;let L=this._svgLayer.querySelector(`.workshop-connector-path[data-connector-id="${w}"]`),q=this._svgLayer.querySelector(`.connector-delete-fo[data-connector-id="${w}"]`);L&&(L.dataset.connectorId=C.id),q&&(q.dataset.connectorId=C.id)}}})}),this._cancelConnectorMode(),!0}else{this._connectorFrom=l,a.classList.add("connector-source-selected");let h=document.createElementNS("http://www.w3.org/2000/svg","line");h.classList.add("workshop-connector-preview"),h.setAttribute("stroke","#f2ca52"),h.setAttribute("stroke-width","2"),h.setAttribute("stroke-dasharray","6 4"),h.style.pointerEvents="none";let g=this._getAnchorPoint(l);return g&&(h.setAttribute("x1",g.cx),h.setAttribute("y1",g.cy),h.setAttribute("x2",g.cx),h.setAttribute("y2",g.cy)),this._svgLayer.appendChild(h),this._previewLine=h,!0}}}}var Pn=`/* \u2500\u2500\u2500 Board (infinite canvas) \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500 */
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

.kanban-card.kanban-card-dragging {
  opacity: 0.4;
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

.workshop-image-grid .image-grid-container {
  flex: 1;
  display: grid;
  padding: 6px;
  overflow-y: auto;
}

.workshop-image-grid .image-grid-item {
  position: relative;
  overflow: hidden;
  border-radius: 4px;
  background: #f3f4f6;
  aspect-ratio: 1;
}

.workshop-image-grid .image-grid-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
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
`;function tr(){if(document.getElementById("platform-workshop-styles"))return;let N=document.createElement("style");N.id="platform-workshop-styles",N.textContent=Pn,document.head.appendChild(N)}function St(){let N=window.Alpine;N&&N.data("workshopBoard",Tt)}typeof document<"u"&&(tr(),document.addEventListener("livewire:init",St),document.readyState!=="loading"?setTimeout(St,0):document.addEventListener("DOMContentLoaded",St));return Qo(nr);})();
