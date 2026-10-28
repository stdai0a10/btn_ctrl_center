import { test, before } from 'node:test';
import assert from 'node:assert/strict';
import { build } from 'esbuild';
import { JSDOM } from 'jsdom';
import { writeFile, unlink } from 'node:fs/promises';
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
let Components;
before(async () => {
    const result = await build({ stdin: { contents: "export {default as Buttons} from './resources/js/pages/Buttons/Index.jsx'; export {default as Layout} from './resources/js/layouts/AppLayout.jsx';", resolveDir: process.cwd(), loader: 'jsx' }, bundle: true, format: 'esm', platform: 'node', jsx: 'automatic', external: ['react', 'react-dom', 'lucide-react', 'react/jsx-runtime'], write: false, plugins: [{ name: 'inertia-test-context', setup(b) { b.onResolve({filter: /^@inertiajs\/react$/}, () => ({path:'inertia', namespace:'test'})); b.onLoad({filter:/.*/, namespace:'test'}, () => ({contents: "import React from 'react'; export const Head=()=>null; export const Link=({href,children,...p})=>React.createElement('a',{href,...p},children); export const usePage=()=>({props:{auth:{user:globalThis.testUser}}}); export const router={visit:(url)=>globalThis.visits.push(url)};"})); } }] });
    const path = new URL('./.offline-test-bundle.mjs', import.meta.url);
    await writeFile(path, result.outputFiles[0].text);
    try { Components = await import(path.href); } finally { await unlink(path); }
});
const page = { public_id:'page1', name:'客廳', layout_columns:3, buttons:[{public_id:'button1', label:'開燈', content_type:'text', shape:'rounded_square', device:{serial_number:'device1'}, function:{code:'power'}, availability:{available:true}}] };
const response = (data) => ({data:{data}});
async function mount({get, post, storage, online=true, component='Buttons', user='user1'}={}) {
    const dom = new JSDOM('<div id="root"></div>', {url:'https://example.test/buttons'});
    globalThis.window=dom.window; globalThis.document=dom.window.document;
    Object.defineProperty(window.navigator,'onLine',{value:online,configurable:true});
    window.requestAnimationFrame=(fn)=>setTimeout(fn,0); window.cancelAnimationFrame=clearTimeout;
    window.HTMLElement.prototype.scrollIntoView=()=>{};
    globalThis.IS_REACT_ACT_ENVIRONMENT=true; globalThis.testUser={public_id:user}; globalThis.visits=[];
    if(storage) window.localStorage.setItem('button-action-pending',storage);
    let posts=0; window.axios={get:get??(async url=>response(url==='/api/button-pages'?[page]:url.includes('targets')?[]:null)),post:async(...args)=>{posts++;return post?.(...args)}};
    const root=createRoot(document.getElementById('root'));
    await act(async()=>{ root.render(React.createElement(Components[component])); await new Promise(r=>setTimeout(r,20)); });
    return {root, rerender:async user=>{globalThis.testUser={public_id:user};await act(async()=>{root.render(React.createElement(Components[component]));await new Promise(r=>setTimeout(r,10));});}, get posts(){return posts}, text:()=>document.body.textContent, close:async()=>{await act(async()=>root.unmount());dom.window.close();}};
}
async function click(text){ const button=[...document.querySelectorAll('button')].find(b=>b.textContent.includes(text)); assert.ok(button,`missing button ${text}`); await act(async()=>{button.click();await new Promise(r=>setTimeout(r,10));}); }
test('first load failure displays connection state and disables modifications', async()=>{
    const app=await mount({get:async()=>{throw new Error('network')}});
    try{assert.match(app.text(),/連線中斷/); assert.equal(document.querySelector('button[type=submit]').matches(':disabled'),true);}finally{await app.close();}
});
test('lost POST response shows unknown, keeps only identifiers, and never resends', async()=>{
    const app=await mount({post:async()=>{throw new Error('network')}});
    try{await click('開燈');assert.match(app.text(),/結果不確定/);assert.equal(app.posts,1);const saved=JSON.parse(window.localStorage.getItem('button-action-pending'));assert.deepEqual(Object.keys(saved).sort(),['created_at','request_id','user_public_id']);assert.equal(saved.user_public_id,'user1');await act(async()=>window.dispatchEvent(new window.Event('online')));assert.equal(app.posts,1);}finally{await app.close();}
});
for(const missing of [false,true])test(`relaunch ${missing?'missing record requires manual confirmation':'recovers completed job'} without POST`,async()=>{
    const app=await mount({storage:JSON.stringify({request_id:'old-id',user_public_id:'user1',created_at:Date.now()}),get:async url=>{if(url.includes('/request/')){if(missing)throw {response:{status:404}};return response({job:{public_id:'job1',status:'succeeded'}});}return response(url==='/api/button-pages'?[page]:url.includes('targets')?[]:null);}});
    try{assert.match(app.text(),missing?/手動確認/:/執行成功/);assert.equal(app.posts,0);if(!missing)assert.equal(window.localStorage.getItem('button-action-pending'),null);}finally{await app.close();}
});
test('offline logout explains block and sends nothing',async()=>{
    const app=await mount({online:false,component:'Layout'});try{await click('登出');assert.match(app.text(),/離線.*無法登出/);assert.equal(app.posts,0);assert.equal(visits.length,0);}finally{await app.close();}
});
test('different account clears prior pending marker without lookup',async()=>{
    const calls=[];const app=await mount({storage:JSON.stringify({request_id:'old-id',user_public_id:'other',created_at:Date.now()}),get:async url=>{calls.push(url);return response(url==='/api/button-pages'?[page]:url.includes('targets')?[]:null);}});
    try{assert.equal(window.localStorage.getItem('button-action-pending'),null);assert.ok(!calls.some(url=>url.includes('/request/')));assert.doesNotMatch(app.text(),/結果不確定/);}finally{await app.close();}
});


test('polling failure preserves displayed buttons and disables existing controls',async()=>{
    let currentCalls=0;
    const app=await mount({get:async url=>{
        if(url==='/api/button-pages')return response([page]);
        if(url.includes('targets'))return response([]);
        if(++currentCalls>1)throw new Error('network');
        return response({job:{public_id:'job1',status:'running'}});
    }});
    try{
        await act(async()=>{await new Promise(r=>setTimeout(r,2100));});
        assert.match(app.text(),/連線中斷/);assert.match(app.text(),/開燈/);
        assert.ok([...document.querySelectorAll('button')].find(b=>b.textContent==='開燈').matches(':disabled'));
        assert.match(app.text(),/結果尚未確認/);assert.equal(app.posts,0);
    }finally{await app.close();}
});
test('lost logout response remains unknown until reconnect confirms signed out',async()=>{
    const app=await mount({component:'Layout',storage:JSON.stringify({request_id:'id',user_public_id:'user1',created_at:Date.now()}),post:async()=>{throw new Error('network')},get:async()=>{throw {response:{status:401}}}});
    try{
        await click('登出');assert.match(app.text(),/登出結果不確定/);assert.equal(visits.length,0);
        assert.ok(window.localStorage.getItem('button-action-pending'));
        await act(async()=>{window.dispatchEvent(new window.Event('online'));await new Promise(r=>setTimeout(r,10));});
        assert.deepEqual(visits,['/']);assert.equal(window.localStorage.getItem('button-action-pending'),null);assert.equal(app.posts,1);
    }finally{await app.close();}
});
test('expired marker is removed without lookup or resend',async()=>{
    const calls=[];const app=await mount({storage:JSON.stringify({request_id:'old',user_public_id:'user1',created_at:Date.now()-86400001}),get:async url=>{calls.push(url);return response(url==='/api/button-pages'?[page]:url.includes('targets')?[]:null);}});
    try{assert.equal(window.localStorage.getItem('button-action-pending'),null);assert.ok(!calls.some(url=>url.includes('/request/')));assert.equal(app.posts,0);}finally{await app.close();}
});

test('switching account in the mounted page removes old job and pending state',async()=>{
    let first=true;
    const app=await mount({storage:JSON.stringify({request_id:'old',user_public_id:'user1',created_at:Date.now()}),get:async url=>{
        if(url.includes('/request/'))return response({job:{public_id:'old-job',status:'succeeded'}});
        if(url==='/api/button-actions/current')return response(null);
        return response(url==='/api/button-pages'?[page]:[]);
    }});
    try{assert.match(app.text(),/執行成功/);await app.rerender('user2');assert.doesNotMatch(app.text(),/執行成功/);assert.equal(app.posts,0);}finally{await app.close();}
});
test('reconnect can be requested while browser still reports online',async()=>{
    let failed=true;
    const app=await mount({get:async url=>{if(failed)throw new Error('network');return response(url==='/api/button-pages'?[page]:url.includes('targets')?[]:null);}});
    try{failed=false;await click('重新連線');assert.match(app.text(),/開燈/);assert.doesNotMatch(app.text(),/連線中斷/);assert.equal(app.posts,0);}finally{await app.close();}
});

test('late response failure from previous account cannot recreate its unknown state',async()=>{
    let rejectPost;
    const app=await mount({post:()=>new Promise((resolve,reject)=>{rejectPost=reject;})});
    try{
        await click('開燈');await app.rerender('user2');
        await act(async()=>{rejectPost(new Error('network'));await new Promise(r=>setTimeout(r,10));});
        assert.doesNotMatch(app.text(),/結果不確定/);
        assert.equal(window.localStorage.getItem('button-action-pending'),null);assert.equal(app.posts,1);
    }finally{await app.close();}
});


test('unknown logout can be checked manually while browser remains online',async()=>{
    let authenticated=true;
    const app=await mount({component:'Layout',storage:JSON.stringify({request_id:'id',user_public_id:'user1',created_at:Date.now()}),post:async()=>{throw new Error('network')},get:async()=>{if(!authenticated)throw {response:{status:401}};return response({});}});
    try{
        await click('登出');assert.equal(window.navigator.onLine,true);assert.match(app.text(),/登出結果不確定/);
        await click('確認登入狀態');assert.match(app.text(),/仍保持登入/);assert.ok(window.localStorage.getItem('button-action-pending'));assert.equal(visits.length,0);
        await click('登出');authenticated=false;await click('確認登入狀態');
        assert.deepEqual(visits,['/']);assert.equal(window.localStorage.getItem('button-action-pending'),null);assert.equal(app.posts,2);
    }finally{await app.close();}
});
test('successful recovery ignores the original POST late rejection',async()=>{
    let rejectPost;
    const app=await mount({post:()=>new Promise((resolve,reject)=>{rejectPost=reject;}),get:async url=>response(url.includes('/request/')?{job:{public_id:'job1',status:'succeeded'}}:url==='/api/button-pages'?[page]:url.includes('targets')?[]:null)});
    try{
        await click('開燈');
        await act(async()=>{window.dispatchEvent(new window.Event('online'));await new Promise(r=>setTimeout(r,10));});
        assert.match(app.text(),/執行成功/);assert.equal(window.localStorage.getItem('button-action-pending'),null);
        await act(async()=>{rejectPost(new Error('network'));await new Promise(r=>setTimeout(r,10));});
        assert.doesNotMatch(app.text(),/結果不確定|連線中斷/);assert.match(app.text(),/執行成功/);
        assert.equal([...document.querySelectorAll('button')].find(b=>b.textContent==='開燈').matches(':disabled'),false);assert.equal(app.posts,1);
    }finally{await app.close();}
});
