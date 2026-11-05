function applyTheme(dark){document.body.classList.toggle('dark',dark);document.documentElement.setAttribute('data-theme',dark?'dark':'light');localStorage.setItem('stock-theme',dark?'dark':'light');}
document.getElementById('themeToggle')?.addEventListener('click',()=>applyTheme(!document.body.classList.contains('dark')));
if(localStorage.getItem('stock-theme')==='dark')applyTheme(true);
document.getElementById('sidebarToggle')?.addEventListener('click',()=>{document.body.classList.toggle('sidebar-collapsed');});
document.querySelectorAll('[data-confirm]')?.forEach(el=>el.addEventListener('click',ev=>{if(!confirm(el.dataset.confirm))ev.preventDefault();}));
function makeChart(id,type,labels,data,label){const el=document.getElementById(id);if(!el||typeof Chart==='undefined')return;new Chart(el,{type:type,data:{labels:labels,datasets:[{label:label,data:data,backgroundColor:'rgba(16,185,129,.55)',borderColor:'#059669',borderWidth:2,borderRadius:6}]},options:{responsive:true,plugins:{legend:{display:false}}}});}
const gs=document.getElementById('globalSearch'), gsr=document.getElementById('globalSearchResults');
gs?.addEventListener('input',async()=>{ if(gs.value.trim().length<2){gsr.innerHTML='';return;} const r=await fetch('api/product_search.php?q='+encodeURIComponent(gs.value.trim())); const rows=await r.json(); gsr.innerHTML=rows.map(p=>'<a class="d-block" href="products.php?q='+encodeURIComponent(p.sku)+'">'+p.name+' · '+p.sku+' · stock '+p.stock_qty+'</a>').join('')||'<span class="text-muted">No matches</span>'; });
document.addEventListener('click',e=>{ if(gsr && !e.target.closest('.search-wrap')) gsr.innerHTML=''; });
function addItemRow(tableId){const t=document.getElementById(tableId);const row=t.querySelector('tr.item-row').cloneNode(true);row.querySelectorAll('input').forEach(i=>i.value='');t.appendChild(row);}
