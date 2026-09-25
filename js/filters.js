/**
 * Golden Hive Blocks — AJAX product filters (behaviour).
 *
 * Migrated from the "Advanced Filters" Code Snippet (see includes/filters.php).
 * Config comes from window.BFL_CFG, printed with the shortcode:
 *   scope → the collection being viewed ({tax, term}), sent with every
 *           refresh so results, counts and pagination stay inside it;
 *   ajax  → false on search results: filter changes reload the page there,
 *           so the search engine keeps deciding what matches.
 * Filter state lives in the URL (WooCommerce layered-nav params), so every
 * state is also a plain, shareable page load — which is the fallback whenever
 * a refresh fails.
 */
(function(){
	"use strict";
	var CFG = window.BFL_CFG || {};
	if(!CFG.ajax_url) return;
	var root = document.querySelector('[data-bfl-root]');
	if(!root) return;
	var drawerEl = document.querySelector('[data-bfl-drawer]');
	var AJAX = CFG.ajax !== false;
	var grid;

	function open(){ if(drawerEl){ drawerEl.classList.add('is-open'); drawerEl.setAttribute('aria-hidden','false'); document.body.classList.add('bfl-lock'); } }
	function close(){ if(drawerEl){ drawerEl.classList.remove('is-open'); drawerEl.setAttribute('aria-hidden','true'); document.body.classList.remove('bfl-lock'); } }
	document.querySelectorAll('[data-bfl-open]').forEach(function(b){ b.addEventListener('click', open); });
	document.querySelectorAll('[data-bfl-close]').forEach(function(b){ b.addEventListener('click', close); });
	document.addEventListener('keydown', function(e){ if(e.key==='Escape') close(); });

	function basePath(){ return location.pathname.replace(/\/page\/\d+\/?$/, '/'); }
	function params(){ return new URLSearchParams(location.search); }
	function state(){
		var p = params(), st = { tax:{}, min:null, max:null, instock:false, orderby:p.get('orderby')||null };
		(CFG.taxKeys||[]).forEach(function(k){ var v=p.get('filter_'+k); if(v) st.tax[k]=v.split(',').filter(Boolean); });
		if(p.get('min_price')) st.min=p.get('min_price');
		if(p.get('max_price')) st.max=p.get('max_price');
		if(p.get('instock')==='1') st.instock=true;
		return st;
	}
	function emptyState(){ return { tax:{}, min:null, max:null, instock:false, orderby:state().orderby }; }
	// Not managed by the panel but part of the page: on search results the
	// search itself must survive every filter change.
	var KEEP = ['s','post_type'];
	function toURL(st, pageBase){
		var cur=params(), p=new URLSearchParams();
		KEEP.forEach(function(k){ if(cur.has(k)) p.set(k, cur.get(k)); });
		Object.keys(st.tax).forEach(function(k){ if(st.tax[k]&&st.tax[k].length){ p.set('filter_'+k, st.tax[k].join(',')); if(st.tax[k].length>1) p.set('query_type_'+k,'or'); } });
		if(st.min!=null) p.set('min_price',st.min);
		if(st.max!=null) p.set('max_price',st.max);
		if(st.instock) p.set('instock','1');
		if(st.orderby) p.set('orderby',st.orderby);
		var qs=p.toString();
		return (pageBase||basePath())+(qs?'?'+qs:'');
	}
	function push(st){
		if(!AJAX){ location.href=toURL(st); return; }
		history.pushState({bfl:1},'',toURL(st));
	}

	var reqId=0;
	function load(paged, scroll){
		grid=document.querySelector(CFG.grid);
		root.classList.add('bfl-busy');
		if(grid) grid.classList.add('bfl-grid-loading');
		if(!AJAX) return; // the page is already navigating to the filtered URL
		var id=++reqId;
		var body=new URLSearchParams();
		body.set('action','ghb_filter');
		if(CFG.scope){ body.set('scope_tax',CFG.scope.tax); body.set('scope_term',CFG.scope.term); }
		body.set('paged',paged||1);
		body.set('params', location.search.replace(/^\?/,''));
		fetch(CFG.ajax_url,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body.toString()})
			.then(function(r){return r.json();})
			.then(function(res){ if(id!==reqId) return; if(!res||!res.success) throw 0; render(res.data, scroll); })
			.catch(function(){ location.href=toURL(state()); })
			.finally(function(){ if(id!==reqId) return; root.classList.remove('bfl-busy'); if(grid) grid.classList.remove('bfl-grid-loading'); });
	}
	function render(data, scroll){
		grid=document.querySelector(CFG.grid);
		if(grid) grid.innerHTML = data.found ? data.items : '<li class="product bfl-empty">'+(CFG.i18n.empty||'')+'</li>';
		if(CFG.countSel){ var c=document.querySelector(CFG.countSel); if(c) c.innerHTML=data.count_html; }
		updatePagination(data.pagination);
		if(data.counts) updateCounts(data.counts);
		syncUI();
		reinit();
		if(scroll && grid) window.scrollTo({top:offsetTop(grid)-120,behavior:'smooth'});
	}
	function updatePagination(html){
		var ex=CFG.pagSel?document.querySelector(CFG.pagSel):null;
		if(html){ if(ex){ex.outerHTML=html;} else if(grid&&grid.parentNode){grid.insertAdjacentHTML('afterend',html);} bindPagination(); }
		else if(ex){ ex.remove(); }
	}
	function updateCounts(counts){
		(CFG.taxKeys||[]).forEach(function(k){
			var map=counts[k]||{};
			root.querySelectorAll('[data-bfl-term][data-key="'+k+'"]').forEach(function(cb){
				var n=map[cb.value], badge=cb.parentNode.querySelector('[data-count]');
				if(badge) badge.textContent=(n!=null?n:0);
				var dis=(n==null||n===0)&&!cb.checked;
				cb.disabled=dis; cb.parentNode.classList.toggle('is-disabled',dis);
			});
		});
	}

	function activeCount(st){ var n=0; Object.keys(st.tax).forEach(function(k){n+=st.tax[k].length;}); if(st.min!=null||st.max!=null) n++; if(st.instock) n++; return n; }
	function syncUI(){
		var st=state();
		root.querySelectorAll('[data-bfl-term]').forEach(function(cb){ var arr=st.tax[cb.dataset.key]||[]; cb.checked=arr.indexOf(cb.value)>-1; });
		var stk=root.querySelector('[data-bfl-stock]'); if(stk) stk.checked=st.instock;
		var lo=root.querySelector('[data-price-input="min"]'), hi=root.querySelector('[data-price-input="max"]');
		if(lo) lo.value=(st.min!=null?st.min:CFG.price.min);
		if(hi) hi.value=(st.max!=null?st.max:CFG.price.max);
		var box=root.querySelector('[data-bfl-active]'), wrap=root.querySelector('[data-bfl-activewrap]');
		if(box){
			box.innerHTML='';
			Object.keys(st.tax).forEach(function(k){
				st.tax[k].forEach(function(slug){
					var name=(CFG.terms[k]&&CFG.terms[k][slug])||slug, pre=CFG.chip[k]?CFG.chip[k]+': ':'';
					box.appendChild(chip(pre+name, function(){ toggleTerm(k,slug,false); }));
				});
			});
			if(st.min!=null||st.max!=null){
				var lbl;
				if(st.min!=null&&st.max!=null) lbl=CFG.price.symbol+st.min+' - '+CFG.price.symbol+st.max;
				else if(st.max!=null) lbl=CFG.i18n.fino+' '+CFG.price.symbol+st.max;
				else lbl=CFG.i18n.da+' '+CFG.price.symbol+st.min;
				box.appendChild(chip((CFG.chip.price?CFG.chip.price+': ':'')+lbl, clearPrice));
			}
			if(st.instock) box.appendChild(chip((CFG.chip.stock?CFG.chip.stock+': ':'')+'Disponibile', clearStock));
		}
		var n=activeCount(st);
		if(wrap) wrap.hidden = n===0;
		document.querySelectorAll('[data-bfl-badge]').forEach(function(b){ b.textContent=n; b.hidden=n===0; });
	}
	function chip(text, onRemove){
		var el=document.createElement('span'); el.className='bfl-chip';
		el.appendChild(document.createTextNode(text));
		var b=document.createElement('button'); b.type='button'; b.setAttribute('aria-label','Rimuovi'); b.innerHTML='&times;';
		b.addEventListener('click', onRemove); el.appendChild(b);
		return el;
	}

	function toggleTerm(key, slug, checked){
		var st=state(), arr=st.tax[key]||[], i=arr.indexOf(slug);
		if(checked===undefined) checked=(i===-1);
		if(checked&&i===-1) arr.push(slug);
		if(!checked&&i>-1) arr.splice(i,1);
		if(arr.length) st.tax[key]=arr; else delete st.tax[key];
		push(st); syncUI(); load(1,true);
	}
	function setPrice(min,max){ var st=state(); st.min=(min>CFG.price.min)?min:null; st.max=(max<CFG.price.max)?max:null; push(st); syncUI(); load(1,true); }
	function clearPrice(){ var st=state(); st.min=null; st.max=null; push(st); syncUI(); load(1,true); var sl=root.querySelector('[data-bfl-slider]'); if(sl) initSlider(sl,true); }
	function toggleStock(on){ var st=state(); st.instock=!!on; push(st); syncUI(); load(1,true); }
	function clearStock(){ toggleStock(false); }
	function reset(){ push(emptyState()); syncUI(); load(1,true); var sl=root.querySelector('[data-bfl-slider]'); if(sl) initSlider(sl,true); }

	function initSlider(sl, resetVals){
		var floor=+sl.dataset.floor, ceil=+sl.dataset.ceil;
		var lo=sl.querySelector('[data-range="min"]'), hi=sl.querySelector('[data-range="max"]'), fill=sl.querySelector('[data-fill]');
		var nlo=root.querySelector('[data-price-input="min"]'), nhi=root.querySelector('[data-price-input="max"]');
		if(resetVals){ lo.value=floor; hi.value=ceil; if(nlo)nlo.value=floor; if(nhi)nhi.value=ceil; }
		function paint(){
			var a=+lo.value, b=+hi.value;
			if(a>b){ if(document.activeElement===lo){b=a;hi.value=b;} else {a=b;lo.value=a;} }
			var l=(a-floor)/(ceil-floor)*100, r=(b-floor)/(ceil-floor)*100;
			fill.style.left=l+'%'; fill.style.width=(r-l)+'%';
			if(nlo&&document.activeElement!==nlo) nlo.value=a;
			if(nhi&&document.activeElement!==nhi) nhi.value=b;
		}
		var t;
		function commit(){ clearTimeout(t); t=setTimeout(function(){ setPrice(+lo.value,+hi.value); },350); }
		lo.oninput=paint; hi.oninput=paint; lo.onchange=commit; hi.onchange=commit;
		function fromNums(){
			var a=Math.max(floor,Math.min(ceil,+nlo.value||floor)), b=Math.max(floor,Math.min(ceil,+nhi.value||ceil));
			if(a>b){ b=a; }
			lo.value=a; hi.value=b; paint(); setPrice(a,b);
		}
		if(nlo) nlo.onchange=fromNums;
		if(nhi) nhi.onchange=fromNums;
		paint();
	}

	function bindPagination(){
		if(!AJAX||!CFG.pagSel) return; // without AJAX the links are plain page loads
		var nav=document.querySelector(CFG.pagSel); if(!nav) return;
		nav.querySelectorAll('a.page-numbers').forEach(function(a){
			a.addEventListener('click', function(e){
				e.preventDefault();
				var m=a.getAttribute('href').match(/\/page\/(\d+)/), pg=m?+m[1]:1, st=state();
				history.pushState({bfl:1},'',toURL(st, basePath()+'page/'+pg+'/'));
				load(pg,true);
			});
		});
	}
	function bindThemeSort(){ var sel=document.querySelector('select.orderby'); if(!sel) return; sel.addEventListener('change', function(){ var st=state(); st.orderby=sel.value||null; push(st); load(1,true); }); }

	root.querySelectorAll('[data-bfl-term]').forEach(function(cb){ cb.addEventListener('change', function(){ toggleTerm(cb.dataset.key, cb.value, cb.checked); }); });
	var stk0=root.querySelector('[data-bfl-stock]'); if(stk0) stk0.addEventListener('change', function(){ toggleStock(stk0.checked); });
	root.querySelectorAll('.bfl-toggle').forEach(function(h){ h.addEventListener('click', function(){ h.setAttribute('aria-expanded', h.getAttribute('aria-expanded')==='true'?'false':'true'); }); });

	// Long lists: "Mostra tutti (N)" ⇄ "Mostra meno".
	root.querySelectorAll('[data-bfl-more]').forEach(function(b){
		b.addEventListener('click', function(){
			var facet=b.closest('[data-bfl-facet]'), all=!facet.classList.contains('is-all');
			facet.classList.toggle('is-all', all);
			b.setAttribute('aria-expanded', all?'true':'false');
			b.textContent = all ? b.dataset.less : b.dataset.more;
		});
	});

	// Search inside a long facet: case- and accent-insensitive, every match
	// shown (the "Mostra tutti" fold steps aside while a search runs).
	function fold(s){ return (s||'').normalize('NFD').replace(/[̀-ͯ]/g,'').toLowerCase().trim(); }
	root.querySelectorAll('[data-bfl-search]').forEach(function(inp){
		var facet=inp.closest('[data-bfl-facet]'), none=facet.querySelector('[data-bfl-nomatch]');
		function run(){
			var q=fold(inp.value), hits=0;
			facet.classList.toggle('is-searching', q!=='');
			facet.querySelectorAll('.bfl-opt').forEach(function(o){
				var hit=!q || fold(o.querySelector('.bfl-name').textContent).indexOf(q)>-1;
				o.classList.toggle('is-miss', !hit);
				if(hit) hits++;
			});
			if(none) none.hidden = !q || hits>0;
		}
		inp.addEventListener('input', run);
		// Escape clears the search first instead of closing the drawer.
		inp.addEventListener('keydown', function(e){ if(e.key==='Escape' && inp.value){ e.preventDefault(); e.stopPropagation(); inp.value=''; run(); } });
	});
	var rb=root.querySelector('[data-bfl-reset]'); if(rb) rb.addEventListener('click', reset);
	var sl0=root.querySelector('[data-bfl-slider]'); if(sl0) initSlider(sl0,false);
	if(AJAX){
		window.addEventListener('popstate', function(){ syncUI(); var m=location.pathname.match(/\/page\/(\d+)/); load(m?+m[1]:1,false); var s=root.querySelector('[data-bfl-slider]'); if(s) initSlider(s, !location.search); });
	}
	// Back-button from a product: if the page is served from the bfcache the
	// filtered DOM is preserved as-is; just re-sync the drawer controls to the URL.
	// (Also clears the busy state a search-results reload leaves behind.)
	window.addEventListener('pageshow', function(e){ if(e.persisted){ root.classList.remove('bfl-busy'); var g=document.querySelector(CFG.grid); if(g) g.classList.remove('bfl-grid-loading'); syncUI(); var s=root.querySelector('[data-bfl-slider]'); if(s) initSlider(s,false); } });

	syncUI(); bindPagination();
	if(AJAX) bindThemeSort();

	/* REINIT — re-fire theme/plugin scripts on new cards. Confirm the WCBoost
	 * swatch event by grepping wcboost-variation-swatches/assets/js/. Guarded.
	 * `bfl:loaded` also re-runs the shop-grid height equalizer (js/shop-grid.js). */
	function reinit(){
		grid=document.querySelector(CFG.grid);
		try{
			var $=window.jQuery;
			if($){ $(document.body).trigger('wcboost_variation_swatches_init'); $(document.body).trigger('wc_fragments_loaded'); }
			if(window.elementorFrontend && grid){ elementorFrontend.elementsHandler.runReadyTrigger(grid); }
			if(grid){ grid.querySelectorAll('img[loading="lazy"]').forEach(function(img){ if(img.dataset.src && !img.src) img.src=img.dataset.src; }); }
			document.dispatchEvent(new CustomEvent('bfl:loaded',{detail:{grid:grid}}));
		}catch(e){}
	}
	function offsetTop(el){ var t=0; while(el){ t+=el.offsetTop; el=el.offsetParent; } return t; }
})();
