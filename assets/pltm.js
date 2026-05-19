(function(){
function initBox(box){
  const search=box.querySelector('.pltm-search'), select=box.querySelector('.pltm-per-page'), tbody=box.querySelector('tbody'), pager=box.querySelector('.pltm-pagination');
  if(!tbody) return; const rows=Array.from(tbody.querySelectorAll('tr')); let page=1;
  function filtered(){ const q=(search.value||'').toLowerCase().trim(); return rows.filter(r=>!q || r.innerText.toLowerCase().indexOf(q)>-1); }
  function render(){ const list=filtered(); const per=select.value==='all'?list.length:parseInt(select.value||'50',10); const pages=Math.max(1,Math.ceil(list.length/(per||1))); if(page>pages) page=pages; rows.forEach(r=>r.style.display='none'); list.slice((page-1)*per,page*per).forEach(r=>r.style.display=''); pager.innerHTML=''; if(select.value!=='all' && pages>1){ for(let i=1;i<=pages;i++){ if(i>3 && i<pages-2 && Math.abs(i-page)>1){ if(!pager.querySelector('.dots')){let d=document.createElement('span');d.className='dots';d.textContent='...';pager.appendChild(d)} continue;} let b=document.createElement('button'); b.type='button'; b.className='pltm-page'+(i===page?' active':''); b.textContent=i; b.onclick=function(){page=i;render()}; pager.appendChild(b);} } }
  search.addEventListener('input',()=>{page=1;render()}); select.addEventListener('change',()=>{page=1;render()}); render();
}
document.addEventListener('DOMContentLoaded',()=>document.querySelectorAll('.pltm-table-wrap').forEach(initBox));
})();
