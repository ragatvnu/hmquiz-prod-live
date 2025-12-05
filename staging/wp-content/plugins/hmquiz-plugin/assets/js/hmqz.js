(function(){
  function emitEvent(name, detail){
    try { window.dispatchEvent(new CustomEvent(name, { detail })); } catch(e){}
    if (typeof window.gtag === 'function') {
      var map = { 'hmqz:start': 'hmqz_start_quiz', 'hmqz:answer': 'hmqz_answer', 'hmqz:finish': 'hmqz_finish_quiz' };
      var evt = map[name] || name;
      try { window.gtag('event', evt, detail || {}); } catch(e){}
    }
  }
  function hmqzValidateEmail(email){
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email).trim());
  }
  function $(s,c){ return (c||document).querySelector(s); }
  function $all(s,c){ return Array.from((c||document).querySelectorAll(s)); }

  /* ---------------- Core Quiz Renderer (same as v0.3.1) ---------------- */
  function renderQuiz(root, data, opts){
    opts = opts || {};
    if(!data || !Array.isArray(data.questions)) { root.innerHTML='<div class="hmqz-card">Invalid quiz data.</div>'; return; }
    var started=false,i=0,score=0,total=data.questions.length,quizTitle=data.title||'';
    var rawCats=[];
    if (data.meta && Array.isArray(data.meta.categories)) { rawCats = rawCats.concat(data.meta.categories); }
    if (data.meta && data.meta.category) { rawCats.push(data.meta.category); }
    if (!rawCats.length && Array.isArray(data.questions)) {
      data.questions.forEach(function(q){
        if (q && q.category) { rawCats.push(q.category); }
      });
    }
    var seenCats={}; var quizCategories=[];
    rawCats.forEach(function(label){
      var str=String(label||'').trim();
      if (!str) return;
      var key=str.toLowerCase();
      if (seenCats[key]) return;
      seenCats[key]=true;
      quizCategories.push(str);
    });
    var wrapper=root.closest('.hmqz-wrapper'),bar=$('.hmqz-progress-bar',wrapper),timerEl=$('.hmqz-timer',wrapper);
    var secondsPerQ=opts.time || (parseInt(timerEl && timerEl.getAttribute('data-seconds'))||15), tick=null, remaining=secondsPerQ;
    var history=[];

    function startOnce(){ if(!started){ started=true; emitEvent('hmqz:start',{title:quizTitle,total:total}); } }
    function progress(){ var pct=Math.round((i)/total*100); if(bar) bar.style.width=pct+'%'; }
    function drawTimer(){ if(!timerEl) return; timerEl.textContent=remaining+'s'; timerEl.classList.toggle('hmqz-danger', remaining<=3); }
    function setTimer(sec){ remaining=sec; drawTimer(); if(tick){clearInterval(tick); tick=null;}
      tick=setInterval(function(){ remaining-=1; drawTimer(); if(remaining<=0){ clearInterval(tick); tick=null;
        var q=data.questions[i]; lockOptions(); showExplanation(q,false,true); record(idx=-1,false,true); }},1000);
    }
    function lockOptions(){ $all('.hmqz-options button', root).forEach(b=>b.disabled=true); }
    function markCorrect(ul, correctIdx){ var all=$all('button', ul); if(correctIdx>=0 && correctIdx<all.length) all[correctIdx].classList.add('hmqz-correct'); }
    function showExplanation(q, correct, viaTimeout){
      var exp=q && q.explanation ? String(q.explanation) : '';
      var expBox=document.createElement('div'); expBox.className='hmqz-expl';
      expBox.innerHTML=(correct?'✅ ':'ℹ️ ')+(exp?exp:(correct?'Correct.':(viaTimeout?'Time up!':'Answer recorded.')));
      root.appendChild(expBox);
      var next=document.createElement('button'); next.className='hmqz-btn hmqz-next'; next.textContent=(i===total-1)?'Finish':'Next';
      next.addEventListener('click', function(){ i+=1; show(i); });
      root.appendChild(next); setTimeout(function(){ try{ next.focus(); }catch(e){} }, 0);
    }
    function record(pickedIndex, isCorrect, viaTimeout){
      var q=data.questions[i];
      history.push({ qIndex:i, pickedIndex:pickedIndex, correctIndex:q.answer, correct:!!isCorrect, timeout:!!viaTimeout });
      if (pickedIndex>=0 && isCorrect) score+=1;
      emitEvent('hmqz:answer',{title:quizTitle,question_index:i+1,selected_index:pickedIndex>=0?(pickedIndex+1):null,correct:!!isCorrect,score:score,total:total,timeout:!!viaTimeout});
    }

    function show(k){
      startOnce(); progress(); if(tick){clearInterval(tick); tick=null;}
      var q=data.questions[k]; if(!q) return results();
      root.innerHTML='';
      var card=document.createElement('div'); card.className='hmqz-card';
      var head=document.createElement('div'); head.className='hmqz-card-head';
      var qq=document.createElement('div'); qq.className='hmqz-question'; qq.textContent='Q'+(k+1)+'/'+total+' — '+(q.question||''); head.appendChild(qq);
      card.appendChild(head);
      var ul=document.createElement('ul'); ul.className='hmqz-options';
      (q.options||[]).forEach(function(opt, idx){
        var li=document.createElement('li');
        var btn=document.createElement('button'); btn.type='button'; btn.className='hmqz-btn hmqz-opt'; btn.textContent=(idx+1)+'. '+opt;
        btn.addEventListener('click', function(){
          lockOptions(); btn.classList.add('hmqz-picked'); var ok=(idx===q.answer);
          if(ok){ btn.classList.add('hmqz-correct'); } else { btn.classList.add('hmqz-wrong'); }
          markCorrect(ul, q.answer);
          if(tick){clearInterval(tick); tick=null;}
          showExplanation(q, ok, false); record(idx, ok, false);
        });
        li.appendChild(btn); ul.appendChild(li);
      });
      card.appendChild(ul);
      var meta=document.createElement('div'); meta.className='hmqz-meta'; meta.textContent='Time per question: '+secondsPerQ+'s'; card.appendChild(meta);
      root.appendChild(card);
      setTimer(secondsPerQ);

      function keyHandler(ev){
        if (ev.key>='1' && ev.key<='9'){ var n=parseInt(ev.key,10)-1; var opt=$all('.hmqz-options .hmqz-opt', root)[n]; if (opt && !opt.disabled) opt.click(); }
        else if (ev.key==='Enter'){ var next=$('.hmqz-next', root); if (next) next.click(); }
      }
      root._hmqzKey && document.removeEventListener('keydown', root._hmqzKey);
      root._hmqzKey = keyHandler; document.addEventListener('keydown', keyHandler);
    }

    function results(){
      if(tick){clearInterval(tick); tick=null;} progress(); root.innerHTML='';
      var card=document.createElement('div'); card.className='hmqz-card hmqz-results';
      var h=document.createElement('div'); h.className='hmqz-question'; h.textContent='Score: '+score+' / '+total; card.appendChild(h);

      var share=document.createElement('button'); share.className='hmqz-btn'; share.textContent='Share Score';
      share.addEventListener('click', async function(){
        var text=(quizTitle?('Quiz: '+quizTitle+'\n'):'')+'Score: '+score+' / '+total;
        try{ if (navigator.share) { await navigator.share({ title: quizTitle||'HMQUIZ', text, url: location.href }); }
             else if (navigator.clipboard) { await navigator.clipboard.writeText(text+'\\n'+location.href); alert('Copied to clipboard!'); }
             else { alert(text); } }catch(e){}
      });
      card.appendChild(share);

      var review=document.createElement('button'); review.className='hmqz-btn'; review.style.marginLeft='8px'; review.textContent='Review Answers';
      review.addEventListener('click', function(){ showReview(); }); card.appendChild(review);

      var again=document.createElement('button'); again.className='hmqz-btn hmqz-restart'; again.style.marginLeft='8px'; again.textContent='Restart';
      again.addEventListener('click', function(){ i=0; score=0; history=[]; show(0); }); card.appendChild(again);

      root.appendChild(card);
      emitEvent('hmqz:finish',{title:quizTitle,score:score,total:total,categories:quizCategories});
    }

    function showReview(){
      root.innerHTML='';
      var card=document.createElement('div'); card.className='hmqz-card hmqz-review';
      var title=document.createElement('div'); title.className='hmqz-question'; title.textContent='Review Answers'; card.appendChild(title);
      var list=document.createElement('ol'); list.className='hmqz-review-list';
      history.forEach(function(item){
        var q=data.questions[item.qIndex];
        var li=document.createElement('li'); li.className='hmqz-review-item';
        var qtxt=document.createElement('div'); qtxt.className='hmqz-review-q'; qtxt.textContent=q.question||''; li.appendChild(qtxt);
        var det=document.createElement('div'); det.className='hmqz-review-det';
        var picked = item.pickedIndex>=0 ? (q.options[item.pickedIndex]||'') : '(No answer)';
        var correct = q.answer>=0 ? (q.options[q.answer]||'') : '';
        det.innerHTML = (item.correct ? '✅ ' : (item.timeout ? '⏱️ ' : '❌ ')) +
                        '<b>Your answer:</b> ' + picked +
                        ' &nbsp; | &nbsp; <b>Correct:</b> ' + correct;
        li.appendChild(det);
        if (q.explanation){ var ex=document.createElement('div'); ex.className='hmqz-review-expl'; ex.textContent=q.explanation; li.appendChild(ex); }
        list.appendChild(li);
      });
      card.appendChild(list);
      var back=document.createElement('button'); back.className='hmqz-btn'; back.textContent='Back to Results';
      back.addEventListener('click', function(){ results(); });
      card.appendChild(back);
      root.appendChild(card);

      (function addEmailAndPrint(resultRoot, scoreVal, totalVal, quizTitleVal, quizCats) {
        var reviewNode = resultRoot.querySelector('.hmqz-review');
        if (!reviewNode) return;
        var stored = '';
        try { stored = localStorage.getItem('hmqz_email') || ''; } catch(e){}
        var box = document.createElement('div');
        box.className = 'hmqz-emailbox';
        var safeValue = stored.replace(/"/g, '&quot;');
        box.innerHTML = `
  <div class="hmqz-card" style="margin-top:12px;padding:12px;border:1px solid #e5e7eb;border-radius:12px;background:#fff">
    <div style="font-weight:700;margin-bottom:6px;">Get your score by email</div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <input type="email" class="hmqz-email-input" placeholder="you@example.com"
             value="${safeValue}"
             style="flex:1;min-width:220px;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
      <button type="button" class="hmqz-email-send hmqz-btn"
              style="padding:10px 14px;border:1px solid #d1d5db;border-radius:8px;background:#111;color:#fff;">
        Email my score
      </button>
      <button type="button" class="hmqz-print hmqz-btn"
              style="padding:10px 14px;border:1px solid #d1d5db;border-radius:8px;background:#fff;">
        Print / Save as PDF
      </button>
    </div>
    <div class="hmqz-email-note" style="font-size:12px;color:#666;margin-top:6px;">
      We'll only use your email to send this score. No spam.
    </div>
  </div>`;
        reviewNode.appendChild(box);

        var printBtn = box.querySelector('.hmqz-print');
        if (printBtn) {
          printBtn.addEventListener('click', function () {
            document.documentElement.classList.add('hmqz-printing');
            window.print();
            setTimeout(function () {
              document.documentElement.classList.remove('hmqz-printing');
            }, 500);
          });
        }

        var sendBtn = box.querySelector('.hmqz-email-send');
        if (sendBtn) {
          sendBtn.addEventListener('click', async function () {
            var input = box.querySelector('.hmqz-email-input');
            var email = input && input.value ? input.value.trim() : '';
            if (!hmqzValidateEmail(email)) {
              if (input) {
                if (input.setCustomValidity) input.setCustomValidity('Please enter a valid email');
                if (input.reportValidity) input.reportValidity();
                input.focus();
                setTimeout(function(){ if (input && input.setCustomValidity) input.setCustomValidity(''); }, 1500);
              }
              return;
            }
            if (input && input.setCustomValidity) input.setCustomValidity('');
            try { localStorage.setItem('hmqz_email', email); } catch(e){}
            var categoriesValue = Array.isArray(quizCats) ? quizCats.join(', ') : '';
            var payload = { email: email, score: scoreVal, total: totalVal, title: quizTitleVal || document.title, url: location.href, categories: categoriesValue };
            var note = box.querySelector('.hmqz-email-note');

            function fallbackMailto() {
              var subject = encodeURIComponent('Your HMQUIZ score');
              var catLine = payload.categories ? '\nCategories: ' + payload.categories : '';
              var body = encodeURIComponent('Quiz: ' + payload.title + catLine + '\nScore: ' + scoreVal + '/' + totalVal + '\nLink: ' + payload.url);
              window.location.href = 'mailto:' + encodeURIComponent(email) + '?subject=' + subject + '&body=' + body;
            }

            if (window.hmqzApi && window.hmqzApi.endpoint && window.hmqzApi.nonce) {
              try {
                var res = await fetch(window.hmqzApi.endpoint, {
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.hmqzApi.nonce
                  },
                  body: JSON.stringify(payload),
                  credentials: 'same-origin'
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                if (note) note.textContent = 'Score sent! Please check your inbox.';
              } catch (err) {
                fallbackMailto();
              }
            } else {
              fallbackMailto();
            }
          });
        }
      })(root, score, total, quizTitle, quizCategories);
    }

    show(0);
  }

  /* ---------------- Topic multipicker ---------------- */
  function uniqueTopics(bank){
    var set = new Set();
    (bank.questions||[]).forEach(function(q){
      if(Array.isArray(q.tags)){ q.tags.forEach(t=>set.add(t)); }
    });
    return Array.from(set).sort();
  }
  function sample(array, k){
    var arr=array.slice(); var out=[]; for(let i=arr.length-1;i>0;i--){ let j=Math.floor(Math.random()*(i+1)); [arr[i],arr[j]]=[arr[j],arr[i]]; }
    for(let i=0;i<Math.min(k, arr.length); i++) out.push(arr[i]); return out;
  }
  function filterByTopics(bank, topics){
    if(!topics || topics.length===0) return bank;
    var qs=(bank.questions||[]).filter(function(q){
      if(!Array.isArray(q.tags)) return false;
      return q.tags.some(t=>topics.includes(String(t)));
    });
    return { title: bank.title || 'Quiz', questions: qs };
  }
  function limitQuestions(bank, k){
    var qs = sample(bank.questions||[], k);
    return { title: bank.title || 'Quiz', questions: qs };
  }

  function renderMultipicker(node, bank, opts){
    opts = opts || {};
    var pick = parseInt(node.getAttribute('data-pick')) || 3;
    var k    = parseInt(node.getAttribute('data-k')) || 10;
    var time = parseInt(node.getAttribute('data-time')) || 15;

    var topics = uniqueTopics(bank);
    var picked = new Set();

    node.innerHTML = '';
    var card = document.createElement('div'); card.className='hmqz-card';
    var title = document.createElement('div'); title.className='hmqz-question'; title.textContent='Pick '+pick+' topics'; card.appendChild(title);

    var chips = document.createElement('div'); chips.className='hmqz-chips';
    topics.forEach(function(t){
      var b=document.createElement('button'); b.type='button'; b.className='hmqz-chip'; b.textContent=t;
      b.addEventListener('click', function(){
        if(picked.has(t)){ picked.delete(t); b.classList.remove('is-picked'); }
        else if(picked.size < pick){ picked.add(t); b.classList.add('is-picked'); }
      });
      chips.appendChild(b);
    });
    if (topics.length===0){
      var note = document.createElement('div'); note.className='hmqz-meta'; note.textContent='No topics found in bank. Add "tags" arrays to questions to enable topic filtering.';
      card.appendChild(note);
    }
    card.appendChild(chips);

    var start = document.createElement('button'); start.className='hmqz-btn'; start.style.marginTop='10px'; start.textContent='Start MCQ';
    start.addEventListener('click', function(){
      if (picked.size !== pick && topics.length>0){ alert('Please pick '+pick+' topics.'); return; }
      var sel = Array.from(picked);
      var filtered = filterByTopics(bank, sel);
      var limited  = limitQuestions(filtered, k);
      // Build a fresh quiz wrapper below picker
      var wrapper = document.createElement('div'); wrapper.className='hmqz-wrapper';
      wrapper.innerHTML = '<div class="hmqz-progress"><div class="hmqz-progress-bar" style="width:0%"></div></div>' +
                          '<div class="hmqz-timer" data-seconds="'+time+'"></div>' +
                          '<div class="hmqz-quiz"></div>';
      node.parentNode.insertBefore(wrapper, node.nextSibling);
      renderQuiz($('.hmqz-quiz', wrapper), limited, { time: time });
      // Scroll to quiz
      try{ wrapper.scrollIntoView({behavior:"smooth", block:"start"}); }catch(e){}
    });
    card.appendChild(start);
    node.appendChild(card);
  }

  /* ---------------- Hub ---------------- */
  function attachHub(wrapper){
    var cards = $all('.hmqz-hub-card', wrapper);
    var target = $('.hmqz-hub-target', wrapper);
    cards.forEach(function(btn){
      btn.addEventListener('click', function(){
        var action = btn.getAttribute('data-action');
        if (action==='mcq'){
          // Render an inline multipicker using whichever bank the page chose via meta (or default sample_capitals.json message)
          target.innerHTML = '<div class="hmqz-card"><div class="hmqz-question">Multiple Choice</div><div class="hmqz-meta">Use the multipicker shortcode on this page to load your bank, or set a default bank in the page meta box.</div></div>';
          window.scrollTo({top: wrapper.getBoundingClientRect().top + window.scrollY - 20, behavior:'smooth'});
        }
      });
    });
  }

  /* ---------------- Bootstrap ---------------- */
  document.addEventListener('DOMContentLoaded', function(){
    // Core quizzes
    document.querySelectorAll('.hmqz-quiz').forEach(function(node){
      try{ var id=node.getAttribute('data-quiz-id'); var script=document.getElementById(id+'-data'); var data=JSON.parse(script.textContent); renderQuiz(node, data); }
      catch(e){ /* ignore on pages without core quiz JSON */ }
    });
    // Multipicker instances
    document.querySelectorAll('.hmqz-multipicker').forEach(function(node){
      try{ var id=node.getAttribute('id'); var script=document.getElementById(id+'-data'); var bank=JSON.parse(script.textContent); renderMultipicker(node, bank, {}); }
      catch(e){ node.innerHTML='<div class="hmqz-card">Failed to load bank for multipicker.</div>'; }
    });
    // Hub
    document.querySelectorAll('.hmqz-hub').forEach(function(w){ attachHub(w); });
  });
})();

(function(){
  if (window.__HMQZ_RESULTS_WIRED__) return;
  window.__HMQZ_RESULTS_WIRED__ = true;
  var NAME_STORAGE_KEY = 'hmqz_player_name';

  function esc(str){
    return String(str || '').replace(/[&<>"']/g, function(ch){
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"})[ch] || ch;
    });
  }

  function validEmail(email){
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email).trim());
  }

  function ensureCertificate(detail){
    var cert = document.getElementById('hmqz-print-cert');
    if (!cert) {
      cert = document.createElement('div');
      cert.id = 'hmqz-print-cert';
      cert.className = 'hmqz-certificate';
      cert.style.display = 'none';
      cert.setAttribute('data-ready', '0');
      document.body.appendChild(cert);
    }
    var title = esc(detail && detail.title ? detail.title : (document.title || 'Quiz'));
    var score = detail && typeof detail.score === 'number' ? detail.score : 0;
    var total = detail && typeof detail.total === 'number' ? detail.total : 0;
    var cats = [];
    if (detail && Array.isArray(detail.categories)) {
      detail.categories.forEach(function(c){
        var label = String(c || '').trim();
        if (label) cats.push(label);
      });
    }
    var catText = cats.length ? esc(cats.join(', ')) : '';
    var catHtml = catText ? '<p>' + catText + '</p>' : '';
    var dateStr = esc(new Date().toLocaleString());
    cert.innerHTML = '<h1>HMQUIZ — Score Certificate</h1>' +
                     '<p><strong>' + title + '</strong></p>' +
                     '<p>Score: ' + esc(String(score)) + ' / ' + esc(String(total)) + '</p>' +
                     catHtml +
                     '<p>Date: ' + dateStr + '</p>';
    cert.setAttribute('data-ready', '1');
    return cert;
  }

  function ensureUI(){
    if (document.getElementById('hmqz-finish-modal')) return;
    var modal = document.createElement('div');
    modal.id = 'hmqz-finish-modal';
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.5);display:none;align-items:center;justify-content:center;z-index:9999';
    modal.innerHTML = '' +
      '<div style="background:#fff;max-width:420px;width:92%;padding:18px;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.15);font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto;">' +
        '<h3 style="margin:0 0 8px">Get your score by email</h3>' +
        '<p style="margin:0 0 12px">Enter your name (optional) and email (required) to save your score and receive future quizzes.</p>' +
        '<input id="hmqz-name" type="text" placeholder="Your name (optional)" style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;margin:0 0 10px"/>' +
        '<input id="hmqz-email" type="email" placeholder="you@example.com" style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;margin:0 0 12px"/>' +
        '<div style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap">' +
          '<button id="hmqz-print" type="button" class="hmqz-btn">Print score</button>' +
          '<button id="hmqz-send" type="button" class="hmqz-btn">Send</button>' +
          '<button id="hmqz-close" type="button" class="hmqz-btn">Close</button>' +
        '</div>' +
      '</div>';
    document.body.appendChild(modal);

    var closeBtn = document.getElementById('hmqz-close');
    if (closeBtn) closeBtn.onclick = function(){ modal.style.display = 'none'; };
    var printBtn = document.getElementById('hmqz-print');
    if (printBtn) printBtn.onclick = function(){ window.print(); };

    var sendBtn = document.getElementById('hmqz-send');
    if (sendBtn) {
      sendBtn.onclick = async function(){
        var nameInput = document.getElementById('hmqz-name');
        var emailInput = document.getElementById('hmqz-email');
        var name = nameInput && nameInput.value ? nameInput.value.trim() : '';
        var email = emailInput && emailInput.value ? emailInput.value.trim() : '';
        if (!validEmail(email)) {
          alert('Please enter a valid email address.');
          if (emailInput) emailInput.focus();
          return;
        }
        var last = window.__hmqz_last || {};
        var cats = Array.isArray(last.categories) ? last.categories : [];
        var topics = Array.isArray(last.topics) ? last.topics : [];
        var score = last.score || 0;
        var total = last.total || 0;
        var threshold = typeof last.threshold === 'number' ? last.threshold : 0.8;
        var status = total ? ((score/total) >= threshold ? 'pass' : 'fail') : '';
        var payload = {
          email: email,
          name: name,
          score: score,
          total: total,
          percent: total ? Math.round((score/total)*100) : 0,
          level: last.level || 0,
          levels: last.levels || 0,
          status: status,
          badge: last.badge || '',
          categories: cats,
          topics: topics,
          elapsed: last.elapsed || '',
          title: last.title || document.title,
          url: window.location.href
        };
        try {
          localStorage.setItem('hmqz_email', email);
          if (name) localStorage.setItem(NAME_STORAGE_KEY, name); else localStorage.removeItem(NAME_STORAGE_KEY);
        } catch(e){}

        async function sendViaRest() {
          if (window.hmqzApi && window.hmqzApi.endpoint) {
            var res = await fetch(window.hmqzApi.endpoint, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': window.hmqzApi.nonce || ''
              },
              body: JSON.stringify(payload),
              credentials: 'same-origin'
            });
            if (!res.ok) throw new Error('HTTP '+res.status);
            return true;
          }
          return false;
        }

        async function sendViaAjax() {
          var endpoint = (window.HMQZ_RESULTS && HMQZ_RESULTS.ajax) || '/wp-admin/admin-ajax.php';
          var res = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
              action: 'hmqz_save_result',
              nonce: (window.HMQZ_RESULTS && HMQZ_RESULTS.nonce) || '',
              email: payload.email,
              name: payload.name || '',
              score: payload.score,
              total: payload.total,
              title: payload.title,
              categories: cats.join(', ')
            }).toString(),
            credentials: 'same-origin'
          });
          if (!res.ok) throw new Error('Request failed');
        }

        try {
          var handled = await sendViaRest();
          if (!handled) await sendViaAjax();
          alert('Score saved. Thank you!');
          modal.style.display = 'none';
        } catch (err) {
          alert('Could not save right now. Please try later.');
        }
      };
    }
  }

  window.addEventListener('hmqz:finish', function(ev){
    var detail = (ev && ev.detail) ? ev.detail : {};
    var cats = [];
    if (detail && Array.isArray(detail.categories)) {
      cats = detail.categories.slice();
    } else if (detail && detail.category) {
      cats = [detail.category];
    }
    detail.categories = cats;
    window.__hmqz_last = {
      title: detail.title || document.title,
      score: detail.score || 0,
      total: detail.total || 0,
      categories: cats
    };
    ensureUI();
    var modal = document.getElementById('hmqz-finish-modal');
    if (modal) {
      modal.style.display = 'flex';
      var nameInput = document.getElementById('hmqz-name');
      var emailInput = document.getElementById('hmqz-email');
      if (nameInput) {
        var storedName = '';
        try { storedName = localStorage.getItem(NAME_STORAGE_KEY) || ''; } catch(e){}
        if (storedName && !nameInput.value) nameInput.value = storedName;
      }
      if (emailInput) {
        var storedEmail = '';
        try { storedEmail = localStorage.getItem('hmqz_email') || ''; } catch(e){}
        if (storedEmail && !emailInput.value) emailInput.value = storedEmail;
        setTimeout(function(){ (nameInput && !nameInput.value ? nameInput : emailInput).focus(); }, 50);
      }
    }
    ensureCertificate(detail);
  }, { passive: true });
})();
