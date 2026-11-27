(() => {
    'use strict';
    const form = document.querySelector('#ticket-form');
    if (!form) return;
    const canvas = document.querySelector('#ticket-screen'), ctx = canvas.getContext('2d');
    const status = document.querySelector('#ticket-status'), open = document.querySelector('#ticket-open');
    const close = document.querySelector('#ticket-close');
    let active = false, busy = false, polling = false, chain = Promise.resolve(), dragging = false, lastMove = 0, epoch = 0;
    function command(data) {
        const next = chain.then(async () => {
            const csrf = form.querySelector('input[type=hidden]'), body = new FormData();
            body.append(csrf.name, csrf.value);
            Object.entries(data).forEach(([key, value]) => body.append(key, value));
            const response = await fetch('/tools/ticket-browser/command', {method:'POST',body,cache:'no-store',headers:{'X-Requested-With':'XMLHttpRequest'}});
            const value = await response.json();
            if (value.csrf) csrf.value = value.csrf;
            if (!response.ok || value.error) throw new Error(value.error || 'Session expired. Refresh this page.');
            return value;
        });
        chain = next.catch(() => {});
        return next;
    }
    const fail = error => {status.textContent = error.message;};
    async function frame() {
        if (!active || busy || polling || document.hidden) return;
        polling = true;
        const version = epoch;
        try {
            const response = await fetch('/tools/ticket-browser/frame', {cache:'no-store',headers:{'X-Requested-With':'XMLHttpRequest'}});
            if (!response.ok || !response.headers.get('content-type')?.startsWith('image/jpeg')) throw new Error('Browser disconnected or idle session expired.');
            const bitmap = await createImageBitmap(await response.blob());
            if (active && version === epoch) ctx.drawImage(bitmap,0,0,1200,800);
            bitmap.close();
        } catch (error) {if (version === epoch) {active=false; open.disabled=false; fail(error);}}
        finally {polling=false;}
    }
    setInterval(frame,1200);
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (busy || active) return;
        busy=true; open.disabled=true; epoch++;
        status.textContent='Opening TicketPay...';
        try {await command({action:'start'}); active=true; close.disabled=false; status.textContent='TicketPay | Japan browser';}
        catch(error){fail(error);}
        finally {busy=false; open.disabled=active; frame();}
    });
    close.addEventListener('click', async () => {
        if (busy) return;
        busy=true; active=false; epoch++;
        try {await command({action:'close'}); close.disabled=true; status.textContent='Closed. Browser profile cleared.';}
        catch(error){fail(error);}
        finally {busy=false;open.disabled=false;ctx.clearRect(0,0,1200,800);}
    });
    function input(data) {if(active&&!busy) command({action:'input',...data}).then(frame).catch(fail);}
    function point(event) {const r=canvas.getBoundingClientRect();return {x:Math.max(0,Math.min(1199,(event.clientX-r.left)*1200/r.width)),y:Math.max(0,Math.min(799,(event.clientY-r.top)*800/r.height))};}
    canvas.addEventListener('pointerdown',event=>{if(!active||busy)return;canvas.focus();canvas.setPointerCapture(event.pointerId);dragging=true;input({kind:'down',...point(event)});});
    canvas.addEventListener('pointermove',event=>{if(dragging&&Date.now()-lastMove>100){lastMove=Date.now();input({kind:'move',...point(event)});}});
    for(const name of ['pointerup','pointercancel'])canvas.addEventListener(name,event=>{if(dragging)input({kind:'up',...point(event)});dragging=false;});
    canvas.addEventListener('wheel',event=>{if(active){event.preventDefault();input({kind:'wheel',delta:event.deltaY});}},{passive:false});
    canvas.addEventListener('keydown',event=>{
        if(!active||event.isComposing)return;
        if(event.ctrlKey&&event.key.toLowerCase()==='a'){event.preventDefault();input({kind:'key',key:'Control+A'});}
        else if(['Enter','Tab','Backspace','Delete','Escape','ArrowUp','ArrowDown','ArrowLeft','ArrowRight'].includes(event.key)){event.preventDefault();input({kind:'key',key:event.shiftKey&&event.key==='Tab'?'Shift+Tab':event.key});}
        else if(event.key.length===1&&!event.ctrlKey&&!event.metaKey){event.preventDefault();input({kind:'text',text:event.key});}
    });
    document.querySelectorAll('[data-key]').forEach(button=>button.addEventListener('click',()=>input({kind:'key',key:button.dataset.key})));
    document.querySelector('#ticket-text-form').addEventListener('submit',event=>{event.preventDefault();const field=document.querySelector('#ticket-text');input({kind:'text',text:field.value});field.value='';});
})();
