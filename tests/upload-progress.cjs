const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const code = fs.readFileSync('public/assets/upload-progress.js', 'utf8');
function element() {
 const attrs = {}, events = {}, classes = new Set();
 return {attrs, events, hidden:false, textContent:'', disabled:false,
 classList:{add:(...v)=>v.forEach(x=>classes.add(x)),remove:(...v)=>v.forEach(x=>classes.delete(x)),contains:x=>classes.has(x)},
 setAttribute:(k,v)=>attrs[k]=v,removeAttribute:k=>delete attrs[k],
 addEventListener:(k,fn)=>events[k]=fn,focus(){this.focused=true;}};
}
function setup() {
 const modal=element(), form=element(), button=element(), body=element();
 const children={};
 modal.querySelector=s=>children[s]??=element();
 modal.showModal=()=>{modal.open=true;}; modal.close=()=>{modal.open=false;modal.events.close();};
 form.querySelector=()=>null;
 // Real upload forms contain <input name="action">, which shadows form.action.
 form.action={value:'bulk-upload',toString:()=>'[object HTMLInputElement]'};
 form.getAttribute=name=>name==='action'?null:null;
 const center={querySelectorAll:()=>[form]};
 const requests=[]; const location={href:'http://localhost/?page=admin',origin:'http://localhost',assign:v=>location.target=v};
 class XHR {constructor(){this.events={};this.upload=element();requests.push(this);}addEventListener(k,f){this.events[k]=f;}open(method,url){this.method=method;this.url=url;}setRequestHeader(k,v){this.accept=v;}send(v){this.data=v;}}
 vm.runInNewContext(code,{document:{querySelector:s=>s==='#upload-progress'?modal:center,body},window:{location},XMLHttpRequest:XHR,FormData:class{},URL});
 const submit=(prevented=false)=>form.events.submit({defaultPrevented:prevented,preventDefault(){},submitter:button});
 return {modal,children,requests,submit,button,location};
}
let t=setup(); t.submit(true); assert.equal(t.requests.length,0);
t.submit(); assert.equal(t.requests.length,1); assert.equal(t.modal.open,true);
t.submit(); assert.equal(t.requests.length,1);
let x=t.requests[0];assert.equal(x.accept,'application/json');
assert.equal(x.method,'POST');assert.equal(x.url,'http://localhost/?page=admin');
x.upload.events.progress({lengthComputable:true,loaded:56,total:100});
assert.equal(t.children['[data-upload-percent]'].textContent,'56%');
assert.equal(t.children['.queue-ring-fill'].attrs['stroke-dasharray'],'56 100');
x.upload.events.load();assert.equal(t.children.h2.textContent,'Guardando historias…');
assert.equal(t.children['[role="progressbar"]'].attrs['aria-valuenow'],undefined);
x.status=422;x.response={error:'PDF no legible'};x.events.load();
assert.equal(t.children['#upload-progress-message'].textContent,'PDF no legible');
assert.equal(t.button.disabled,false);assert.equal(t.children.button.hidden,false);
t.children.button.events.click();assert.equal(t.modal.open,false);
t.submit();x=t.requests[1];x.status=200;x.response={redirect:'?page=admin'};x.events.load();assert.equal(t.location.target,'http://localhost/?page=admin');
t=setup();t.submit();t.requests[0].events.timeout();assert.match(t.children['#upload-progress-message'].textContent,/podría haberla guardado/);
t=setup();t.submit();x=t.requests[0];x.status=200;x.response={redirect:'https://example.org'};x.events.load();assert.equal(t.location.target,undefined);
console.log('OK: validación, progreso 56%, procesamiento, bloqueo de duplicados, error, cierre, redirección y timeout.');
t=setup();t.submit();x=t.requests[0];x.status=413;x.response=null;x.events.load();assert.match(t.children['#upload-progress-message'].textContent,/tamaño permitido/);
t=setup();t.submit();x=t.requests[0];x.status=504;x.response=null;x.events.load();assert.match(t.children['#upload-progress-message'].textContent,/tiempo de espera/);
console.log('OK: errores HTML del proxy 413 y 504 explicados sin ocultar la causa.');
