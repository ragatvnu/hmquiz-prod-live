// Global fallback definition so other bundles can reuse it
const HMQUIZ_BANK_BASE = (() => {
  if (window.HMQZCFG && window.HMQZCFG.bankBase) {
    return window.HMQZCFG.bankBase.replace(/\/$/, '');
  }
  return `${location.origin}/wp-content/uploads/hmquiz/banks`;
})();

const HMQZ_LOGO_URL = window.HMQZCFG && window.HMQZCFG.logo ? window.HMQZCFG.logo : '';

(function () {
  function _fetchJSONWithFallback(primary, fallback) {
    const opts = { headers: { Accept: 'application/json' } };
    const ok = (res) => {
      if (!res.ok) throw res;
      return res.json();
    };
    return fetch(primary, opts).then(ok).catch(() => fetch(fallback, opts).then(ok));
  }
  if (typeof window.fetchJSONWithFallback !== 'function') {
    window.fetchJSONWithFallback = _fetchJSONWithFallback;
  }
})();

/*! HMQUIZ app.js v0.3.9 */
(() => {
  "use strict";

  // ---------- tiny helpers ----------
  const qs = new URLSearchParams(location.search);
  const parseList = (value) => {
    if (!value) return [];
    return String(value)
      .split(/[,\|]/)
      .map((s) => s.trim())
      .filter(Boolean);
  };
  const $  = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const el = (tag, props = {}, ...kids) => {
    const n = document.createElement(tag);
    Object.assign(n, props);
    for (const k of kids) n.append(k?.nodeType ? k : document.createTextNode(k));
    return n;
  };
  const clear = (node) => { while (node.firstChild) node.removeChild(node.firstChild); };

  const topicsHref = (window.HMQZCFG && window.HMQZCFG.topicsUrl) || '/quiz/general-knowledge/';
  const sanitizeBankName = (name = '') => {
    return String(name)
      .split('/')
      .map(part => part.trim())
      .filter(Boolean)
      .map(part => {
        const cleaned = part.replace(/[^0-9A-Za-z._-]/g, '');
        if (!cleaned || cleaned === '.' || cleaned === '..') return null;
        return cleaned;
      })
      .filter(Boolean)
      .join('/');
  };
  const clamp01 = (val, fallback = 0.6) => {
    let num = parseFloat(val);
    if (num > 1) num = num / 100;
    if (!isFinite(num) || num <= 0 || num > 1) return fallback;
    return num;
  };

  async function buildPayloadFromBank(bankName, perLevel, passRatio) {
    const safeName = sanitizeBankName(bankName);
    if (!safeName) throw new Error("Invalid bank name");
    const base = HMQUIZ_BANK_BASE;
    const url = `${base}/${safeName}`;
    const res = await fetch(url, { headers: { Accept: 'application/json' } });
    if (!res.ok) {
      throw new Error(`Bank fetch failed (${res.status})`);
    }
    const data = await res.json();
    const rawItems = Array.isArray(data.items) ? data.items : (Array.isArray(data) ? data : []);
    if (!rawItems.length) {
      throw new Error("Bank has no questions");
    }
    const questions = rawItems.map((item) => {
      const text = String(item.q ?? item.text ?? '');
      let choices = item.options ?? item.choices ?? [];
      if (!Array.isArray(choices)) choices = [];
      choices = choices.map((choice) => typeof choice === 'string' ? choice : String(choice));
      const topicList = [];
      if (Array.isArray(item.topics)) topicList.push(...item.topics);
      if (item.topic) topicList.push(item.topic);
      const categoryList = [];
      if (Array.isArray(item.categories)) categoryList.push(...item.categories);
      if (item.category) categoryList.push(item.category);
      if (item.meta && item.meta.category) categoryList.push(item.meta.category);
      const normalizedTopics = topicList
        .map((label) => String(label || '').trim())
        .filter(Boolean);
      const normalizedCategories = categoryList
        .map((label) => String(label || '').trim())
        .filter(Boolean);

      let correct = 0;
      if (typeof item.correct_index === 'number') correct = item.correct_index;
      else if (typeof item.answer_index === 'number') correct = item.answer_index;
      else if (typeof item.answer === 'number') correct = item.answer;
      else if (typeof item.answer === 'string') {
        const idx = choices.findIndex(choice => choice.toLowerCase() === item.answer.toLowerCase());
        if (idx >= 0) correct = idx;
      }
      correct = Math.max(0, Math.min(choices.length - 1, Number(correct) || 0));
      return {
        text,
        choices,
        correct_index: correct,
        topic: normalizedTopics[0] || '',
        category: normalizedCategories[0] || '',
        topics: normalizedTopics,
        categories: normalizedCategories,
        meta: {
          topics: normalizedTopics,
          categories: normalizedCategories,
        },
      };
    });

    const normalizedPer = Math.max(1, Number(perLevel) || 5);
    const levels = [];
    for (let i = 0; i < questions.length; i += normalizedPer) {
      levels.push({ questions: questions.slice(i, i + normalizedPer) });
    }

    return {
      title: data.title || safeName.replace(/_/g, ' '),
      bank: safeName,
      question_count: questions.length,
      rules: { per_level: normalizedPer, pass_ratio: clamp01(passRatio) },
      levels: levels.length ? levels : [{ questions }],
    };
  }

  function collectQuestionMetaLists(question) {
    const topicSet = new Set();
    const catSet = new Set();
    const pushMany = (targetSet, list) => {
      (list || []).forEach((entry) => {
        const label = String(entry || '').trim();
        if (label) targetSet.add(label);
      });
    };
    const metaTopics = Array.isArray(question?.meta?.topics) ? question.meta.topics : [];
    const metaCats = Array.isArray(question?.meta?.categories) ? question.meta.categories : [];
    pushMany(topicSet, metaTopics);
    pushMany(catSet, metaCats);
    if (Array.isArray(question.topics)) pushMany(topicSet, question.topics);
    if (Array.isArray(question.categories)) pushMany(catSet, question.categories);
    if (typeof question.topic === 'string') pushMany(topicSet, [question.topic]);
    if (typeof question.category === 'string') pushMany(catSet, [question.category]);
    return {
      topics: Array.from(topicSet),
      categories: Array.from(catSet),
    };
  }

  function rebuildLevelsFromQuestions(questions, perLevel) {
    const chunk = Math.max(1, Number(perLevel) || 5);
    const levels = [];
    for (let i = 0; i < questions.length; i += chunk) {
      levels.push({ questions: questions.slice(i, i + chunk) });
    }
    return levels;
  }

  function applyQuestionFilters(payload, topicFilters, categoryFilters, forceShuffle = false) {
    const topics = Array.isArray(topicFilters) ? topicFilters.filter(Boolean) : [];
    const categories = Array.isArray(categoryFilters) ? categoryFilters.filter(Boolean) : [];
    const hasTopic = topics.length > 0;
    const hasCategory = categories.length > 0;
    payload.filters = { topics, categories };
    if (!hasTopic && !hasCategory) return payload;

    const allQuestions = [];
    (payload.levels || []).forEach((level) => {
      (level.questions || []).forEach((q) => allQuestions.push(q));
    });
    if (!allQuestions.length) return payload;

    const lcTopics = topics.map((t) => t.toLowerCase());
    const lcCats = categories.map((c) => c.toLowerCase());

    const filtered = allQuestions.filter((q) => {
      const meta = collectQuestionMetaLists(q);
      const topicMatch = !hasTopic || meta.topics.some((label) => lcTopics.includes(label.toLowerCase()));
      const catMatch = !hasCategory || meta.categories.some((label) => lcCats.includes(label.toLowerCase()));
      return topicMatch && catMatch;
    });
    if (!filtered.length) return payload;

    let working = filtered.slice();

    if (hasCategory && categories.length > 1) {
      const buckets = new Map();
      working.forEach((question) => {
        const meta = collectQuestionMetaLists(question);
        const primaryCat = (meta.categories[0] || '').toLowerCase() || '_';
        if (!buckets.has(primaryCat)) buckets.set(primaryCat, []);
        buckets.get(primaryCat).push(question);
      });
      buckets.forEach((list) => {
        for (let i = list.length - 1; i > 0; i--) {
          const j = Math.floor(Math.random() * (i + 1));
          [list[i], list[j]] = [list[j], list[i]];
        }
      });
      const order = categories
        .map((cat) => cat.toLowerCase())
        .filter((cat) => buckets.has(cat));
      if (!order.length && buckets.size) {
        order.push(...buckets.keys());
      }
      const interleaved = [];
      let remaining = true;
      while (remaining) {
        remaining = false;
        order.forEach((catKey) => {
          const bucket = buckets.get(catKey);
          if (bucket && bucket.length) {
            interleaved.push(bucket.shift());
            remaining = true;
          }
        });
      }
      working = interleaved.length ? interleaved : working;
    } else if (forceShuffle || hasTopic || hasCategory) {
      for (let i = working.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [working[i], working[j]] = [working[j], working[i]];
      }
    }

    const perLevel = Math.max(1, Number(payload.rules?.per_level) || 5);
    payload.levels = rebuildLevelsFromQuestions(working, perLevel);
    payload.question_count = working.length;
    payload.filtered = true;
    if (topics.length) {
      payload.title = topics.join(' / ');
    }
    return payload;
  }

  // ---------- render: classic list (non-levelled) ----------
  function renderClassic(root, payload) {
    clear(root);

    const { title, levels } = payload;
    const allQ = (levels || []).flatMap(l => l.questions || []);

    const head = el("div", { className: "hmqz-headrow" },
      el("h2", { className: "hmqz-title" }, title || "Quiz"),
      el("div", { className: "hmqz-level-badge" }, "Classic Mode")
    );
    root.append(head);

    const wrap = el("div", { className: "hmqz-classic" });
    allQ.forEach((q, i) => {
      const block = el("div", { className: "hmqz-qblock" });
      block.append(el("p", {}, `${i + 1}. ${q.text}`));
      const group = `q${i}`;
      q.choices.forEach((choice, idx) => {
        const id = `${group}_${idx}`;
        const lab = el("label", { className: "hmqz-opt", htmlFor: id }, choice);
        const inp = el("input", { type: "radio", name: group, id, value: idx });
        lab.prepend(inp);
        block.append(lab);
      });
      wrap.append(block);
    });
    root.append(wrap);

    const cta = el("div", { className: "hmqz-cta" });
    const submit = el("button", { className: "hmqz-btn primary" }, "Submit");
    const retry  = el("button", { className: "hmqz-btn hmqz-retry" }, "Retry");
    retry.addEventListener("click", () => renderClassic(root, payload));
    submit.addEventListener("click", () => {
      let score = 0;
      allQ.forEach((q, i) => {
        const sel = $(`input[name="q${i}"]:checked`);
        if (sel && Number(sel.value) === Number(q.correct_index)) score++;
      });
      const pct = Math.round((score / allQ.length) * 100);
      const sumH = el("div", { className: "hmqz-summary-h" }, `Score: ${score}/${allQ.length} (${pct}%)`);
      const sumS = el("div", { className: "hmqz-summary-s" }, "Review your selections and try again if you like.");
      root.append(el("div", {}, sumH, sumS));
    });
    cta.append(submit, retry);
    root.append(cta);
  }

  // ---------- render: levels with pass/fail gate ----------
  function renderLevels(root, payload) {
    clear(root);

    const rules = payload.rules || {};
    const perLevel  = Math.max(1, Number(rules.per_level) || 5);
    const passRatio = Math.min(1, Math.max(0, Number(rules.pass_ratio) || 0.6));

    const title  = payload.title || "Quiz";
    const levels = (payload.levels || []).map(l => ({ ...l }));

    let idx = 0;
    let passed = 0;

    // header
    const head = el("div", { className: "hmqz-headrow" },
      el("h2", { className: "hmqz-title" }, title),
      el("div", { className: "hmqz-level-badge" }, () => `Level ${idx + 1}/${levels.length}`)
    );
    const subtitle = el("div", { className: "hmqz-subtitle" }, `Pass ≥ ${Math.ceil(perLevel * passRatio)} of ${perLevel} to advance`);
    const progTxt  = el("div", { className: "hmqz-progress" }, "Progress");
    const bar      = el("div", { className: "hmqz-bar" }, el("div", { className: "hmqz-bar-fill" }));
    root.append(head, subtitle, progTxt, bar);

    const card = el("div", { className: "hmqz-card" });
    root.append(card);

    const cta = el("div", { className: "hmqz-cta" });
    const btnSubmit = el("button", { className: "hmqz-btn primary" }, "Submit Level");
    const btnRetry  = el("button", { className: "hmqz-btn hmqz-retry", disabled: true }, "Retry Level");
    const btnNext   = el("button", { className: "hmqz-btn hmqz-next", disabled: true }, "Next Level");
    cta.append(btnSubmit, btnRetry, btnNext);
    root.append(cta);

    function updateHeader() {
      head.lastChild.textContent = `Level ${idx + 1}/${levels.length}`;
      const pct = Math.round((idx / levels.length) * 100);
      progTxt.textContent = `Progress: ${idx}/${levels.length} levels done`;
      $(".hmqz-bar-fill", bar).style.width = `${pct}%`;
    }

    function renderLevel() {
      updateHeader();
      clear(card);

      const lvl = levels[idx];
      const questions = (lvl.questions || []).slice(0, perLevel);

      questions.forEach((q, qn) => {
        const block = el("div", { className: "hmqz-qblock" });
        block.append(el("p", { className: "hmqz-qtext" }, `${qn + 1}. ${q.text}`));

        const group = `L${idx}_Q${qn}`;
        q.choices.forEach((choice, ci) => {
          const id = `${group}_${ci}`;
          const row = el("label", { className: "hmqz-choice", htmlFor: id });
          const dot = el("span", { className: "hmqz-dot" });
          const inp = el("input", { type: "radio", id, name: group, value: ci, hidden: true });
          row.append(dot, choice);
          row.addEventListener("click", () => {
            // visual selection toggle
            $$(".hmqz-choice", block).forEach(n => n.classList.remove("is-selected"));
            row.classList.add("is-selected");
            inp.checked = true;
          });
          block.append(row);
        });

        card.append(block);
      });

      btnSubmit.disabled = false;
      btnRetry.disabled  = true;
      btnNext.disabled   = true;
    }

    function gradeLevel() {
      const lvl = levels[idx];
      const questions = (lvl.questions || []).slice(0, perLevel);

      let correct = 0;
      questions.forEach((q, qn) => {
        const sel = $(`input[name="L${idx}_Q${qn}"]:checked`);
        const chosen = sel ? Number(sel.value) : -1;
        const isCorrect = chosen === Number(q.correct_index);
        if (isCorrect) correct++;

        // decorate choices
        const block = card.children[qn];
        $$(".hmqz-choice", block).forEach((row, ci) => {
          row.classList.remove("is-selected");
          if (ci === q.correct_index) row.classList.add("is-correct");
          if (ci === chosen && ci !== q.correct_index) row.classList.add("is-wrong");
        });
      });

      const need = Math.ceil(perLevel * passRatio);
      const ok = correct >= need;

      const banner = el("div", { className: `hmqz-banner ${ok ? "pass" : "fail"}` },
        el("strong", {}, ok ? "Passed" : "Try again"),
        `— You got ${correct}/${perLevel}. Need ${need} to pass.`
      );
      card.append(banner);

      if (ok) passed++;
      btnSubmit.disabled = true;
      btnRetry.disabled  = !true;
      btnNext.disabled   = !ok;
    }

    function nextLevel() {
      if (idx + 1 >= levels.length) {
        summary();
        return;
      }
      idx++;
      renderLevel();
    }

    function retryLevel() {
      renderLevel();
    }

    function summary() {
      clear(root);
      root.classList.add('hmqz-play-shell');
      const total = levels.length;
      const pct = Math.round((passed / total) * 100);
      root.append(
        el("div", { className: "hmqz-summary-h" }, `Completed: ${passed}/${total} levels (${pct}%)`),
        el("div", { className: "hmqz-summary-s" }, "Great job! You can retry or share your score.")
      );

      const actions = el("div", { className: "hmqz-cta" });
      const again   = el("button", { className: "hmqz-btn" }, "Play Again");
      const share   = el("button", { className: "hmqz-btn" }, "Share by Email");
      again.addEventListener("click", () => renderLevels(root, payload));
      share.addEventListener("click", () => {
        const subj = encodeURIComponent(`${title} — I scored ${pct}%`);
        const body = encodeURIComponent(`I completed ${title} on HMQUIZ and passed ${passed}/${total} levels!`);
        location.href = `mailto:?subject=${subj}&body=${body}`;
      });
      actions.append(again, share);
      root.append(actions);
    }

    // wire buttons
    btnSubmit.addEventListener("click", gradeLevel);
    btnRetry.addEventListener("click", retryLevel);
    btnNext.addEventListener("click", nextLevel);

    // initial
    renderLevel();
  }

  function addEmailBox(container, stats) {
    if (!container) return;
    const storedEmail = (() => {
      try { return localStorage.getItem('hmqz_email') || ''; } catch(e) { return ''; }
    })();
    const group = el("div", { className: "hmqz-emailbox" });
    group.innerHTML = `
      <div style="border:1px solid #e5e7eb;border-radius:12px;padding:16px;background:#fff">
        <div style="font-weight:600;margin-bottom:8px;">Save or email your score</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
          <input type="text" class="hmqz-name" placeholder="Player name" style="flex:1;min-width:180px;">
          <input type="email" class="hmqz-email" placeholder="you@example.com" value="${storedEmail.replace(/"/g,'&quot;')}">
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <button type="button" class="hmqz-btn primary hmqz-email-send">Email score</button>
          <button type="button" class="hmqz-btn secondary hmqz-print">Save / Print PDF</button>
          <button type="button" class="hmqz-btn secondary hmqz-share-facebook">Facebook</button>
          <button type="button" class="hmqz-btn secondary hmqz-share-twitter">Twitter</button>
          <button type="button" class="hmqz-btn secondary hmqz-share-whatsapp">WhatsApp</button>
        </div>
        <div class="hmqz-email-note" style="font-size:.85rem;margin-top:6px;color:#6b7280;">We only use your email to send this score.</div>
      </div>
    `;
    container.append(group);

    const nameInput = group.querySelector('.hmqz-name');
    const emailInput = group.querySelector('.hmqz-email');
    const note = group.querySelector('.hmqz-email-note');
    const printBtn = group.querySelector('.hmqz-print');
    const requireInfo = (action) => {
      const player = nameInput?.value?.trim() || '';
      const email = emailInput?.value?.trim() || '';
      if (!player) {
        note.textContent = `Please enter your name to ${action}.`;
        note.style.color = '#dc2626';
        nameInput?.focus();
        return null;
      }
      if (!hmqzValidateEmail(email)) {
        note.textContent = 'Please enter a valid email address.';
        note.style.color = '#dc2626';
        emailInput?.focus();
        return null;
      }
      note.textContent = '';
      note.style.color = '#6b7280';
      return { player, email };
    };

    const renderPrintSheet = (info) => {
      const existing = document.querySelector('.hmqz-print-sheet');
      if (existing) existing.remove();
      const sheet = el("div", { className: "hmqz-print-sheet" });
      const percent = stats.total ? Math.round((stats.correct / stats.total) * 100) : 0;
      const levelHistory = Array.isArray(stats.history) && stats.history.length
        ? stats.history
        : [{ level: '-', correct: stats.correct, total: stats.total, percent }];
      const passedLevels = levelHistory.filter(h => h.passed !== false).length;
      const dateStr = new Date().toLocaleString();

      const hero = el("section", { className: "hmqz-print-hero" });
      const brandLogo = HMQZ_LOGO_URL
        ? `<img src="${HMQZ_LOGO_URL}" alt="HMQUIZ logo" class="hmqz-print-logo-img" />`
        : `<span class="hmqz-print-logo">HMQUIZ</span>`;
      hero.innerHTML = `
        <div class="hmqz-print-brand">
          ${brandLogo}
          <span class="hmqz-print-tag">Official scorecard</span>
        </div>
        <div class="hmqz-print-quiz">
          <div class="hmqz-print-quiz-title">${stats.title || 'Quiz Result'}</div>
          <div class="hmqz-print-player">Player: ${info.player || '—'} · Email: ${info.email || '—'}</div>
          <div class="hmqz-print-date">${dateStr}</div>
        </div>
      `;
      sheet.append(hero);

      const summary = el("section", { className: "hmqz-print-summary" });
      const metric = (label, value, accent = '') => {
        const node = el("div", { className: "hmqz-print-metric" });
        node.innerHTML = `<div class="hmqz-print-metric-label">${label}</div><div class="hmqz-print-metric-value ${accent}">${value}</div>`;
        return node;
      };
      const filterTopics = Array.isArray(stats.filters?.topics) ? stats.filters.topics.join(', ') : '';
      const filterCats = Array.isArray(stats.filters?.categories) ? stats.filters.categories.join(', ') : '';
      summary.append(
        metric('Score', `${percent}%`, 'accent'),
        metric('Correct answers', `${stats.correct}/${stats.total}`),
        metric('Levels passed', `${passedLevels}/${levelHistory.length}`),
        metric('Per-level rule', `${Math.max(1, Number(stats.rules?.per_level) || 5)} questions`)
      );
      if (filterTopics) summary.append(metric('Topics', filterTopics));
      if (filterCats) summary.append(metric('Categories', filterCats));
      sheet.append(summary);

      const tableCard = el("section", { className: "hmqz-print-table-card" });
      const heading = el("h3", { className: "hmqz-print-section-title" }, "Level breakdown");
      tableCard.append(heading);
      const table = el("table", { className: "hmqz-print-table" });
      const thead = document.createElement('thead');
      thead.innerHTML = '<tr><th>Level</th><th>Score</th><th>Percent</th><th>Status</th></tr>';
      table.append(thead);
      const tbody = document.createElement('tbody');
      levelHistory.forEach(entry => {
        const row = document.createElement('tr');
        const pctVal = entry.percent ?? Math.round((entry.correct / entry.total) * 100);
        const topicCell = entry.topics && entry.topics.length
          ? entry.topics.join(', ')
          : (Array.isArray(stats.filters?.topics) && stats.filters.topics.length ? stats.filters.topics.join(', ') : '');
        const catCell = entry.categories && entry.categories.length
          ? entry.categories.join(', ')
          : (Array.isArray(stats.filters?.categories) && stats.filters.categories.length ? stats.filters.categories.join(', ') : '');
        const detailLine = [topicCell && `Topics: ${topicCell}`, catCell && `Categories: ${catCell}`]
          .filter(Boolean)
          .join(' · ');
        const detailHtml = detailLine ? `<div class="hmqz-print-level-meta">${detailLine}</div>` : '';
        row.innerHTML = `
          <td>${entry.level || '-'}</td>
          <td>${entry.correct}/${entry.total}${detailHtml}</td>
          <td>${pctVal}%</td>
          <td>${entry.passed === false ? '❌' : '✅'}</td>
        `;
        tbody.append(row);
      });
      table.append(tbody);
      tableCard.append(table);
      sheet.append(tableCard);

      const signature = el("div", { className: "hmqz-print-signature" },
        el("div", { className: "hmqz-sign-line" }),
        el("div", { className: "hmqz-sign-label" }, "HMQUIZ Coaches")
      );
      sheet.append(signature);

      document.body.append(sheet);
      document.body.classList.add('hmqz-printing');
      window.print();
      setTimeout(() => {
        document.body.classList.remove('hmqz-printing');
        sheet.remove();
      }, 600);
    };

    if (printBtn) {
      printBtn.addEventListener('click', () => {
        const info = requireInfo('save your PDF');
        if (!info) return;
        renderPrintSheet(info);
      });
    }

    const sendBtn = group.querySelector('.hmqz-email-send');
    if (sendBtn) {
      sendBtn.addEventListener('click', async () => {
        const info = requireInfo('email your score');
        if (!info) return;
        note.textContent = 'Sending...';
        note.style.color = '#6b7280';
        try { localStorage.setItem('hmqz_email', info.email); } catch(e) {}
        const payload = {
          quiz_id: stats.quizId,
          email: info.email,
          name: info.player,
          score: { correct: stats.correct, total: stats.total, percent: Math.round((stats.correct / stats.total) * 100) },
          meta: { mode: 'legacy-timer', url: location.href }
        };
        if (window.hmqzApi && window.hmqzApi.endpoint && window.hmqzApi.nonce) {
          try {
            const subject = `HMQUIZ score — ${stats.title}`;
            const body = `Quiz: ${stats.title}\nScore: ${payload.score.correct}/${payload.score.total}\nLink: ${location.href}`;
            const res = await fetch(window.hmqzApi.shareEndpoint, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': window.hmqzApi.nonce,
              },
              body: JSON.stringify({
                email: info.email,
                subject: subject,
                body: body,
              }),
            });
            if (!res.ok) throw new Error('REST email failed');
            note.textContent = 'Score sent! Check your inbox.';
            note.style.color = '#15803d';
            return;
          } catch (err) {
            console.warn('[HMQUIZ] email REST failed, falling back to mailto', err);
          }
        }
        const subject = encodeURIComponent(`HMQUIZ score — ${stats.title}`);
        const body = encodeURIComponent(`Quiz: ${stats.title}\nScore: ${payload.score.correct}/${payload.score.total}\nLink: ${location.href}`);
        location.href = `mailto:${encodeURIComponent(info.email)}?subject=${subject}&body=${body}`;
        note.textContent = 'Opened email client to send the score.';
        note.style.color = '#15803d';
      });
    }

    const fbBtn = group.querySelector('.hmqz-share-facebook');
    if (fbBtn) {
      fbBtn.addEventListener('click', () => {
        const url = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(location.href)}`;
        window.open(url, '_blank', 'noopener,noreferrer');
      });
    }

    const twBtn = group.querySelector('.hmqz-share-twitter');
    if (twBtn) {
      twBtn.addEventListener('click', () => {
        const text = `I scored ${stats.correct}/${stats.total} on the ${stats.title} quiz!`;
        const url = `https://twitter.com/intent/tweet?url=${encodeURIComponent(location.href)}&text=${encodeURIComponent(text)}`;
        window.open(url, '_blank', 'noopener,noreferrer');
      });
    }

    const waBtn = group.querySelector('.hmqz-share-whatsapp');
    if (waBtn) {
      waBtn.addEventListener('click', () => {
        const text = `I scored ${stats.correct}/${stats.total} on the ${stats.title} quiz!`;
        const url = `https://api.whatsapp.com/send?text=${encodeURIComponent(text)}%20${encodeURIComponent(location.href)}`;
        window.open(url, '_blank', 'noopener,noreferrer');
      });
    }
  }

  function hmqzValidateEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email).trim());
  }

  function renderLegacyTimer(root, payload) {
    clear(root);
    const title = payload.title || 'Quiz';
    const levelData = (Array.isArray(payload.levels) ? payload.levels : []).filter(
      (lvl) => lvl && Array.isArray(lvl.questions) && lvl.questions.length
    );
    if (!levelData.length) {
      root.textContent = 'No levels configured for this quiz.';
      return;
    }
    const payloadFilters = payload.filters || {};
    const filterTopics = payloadFilters.topics && payloadFilters.topics.length
      ? payloadFilters.topics
      : parseList(qs.get('topics'));
    const urlTopics = filterTopics;
    const derivedTopics = (() => {
      const bag = new Set();
      levelData.forEach((lvl) => {
        (lvl.questions || []).forEach((q) => {
          const topicSources = [
            ...(Array.isArray(q?.meta?.topics) ? q.meta.topics : []),
            ...(Array.isArray(q.topics) ? q.topics : []),
          ];
          if (typeof q.topic === 'string') topicSources.push(q.topic);
          topicSources.forEach((name) => {
            const cleaned = String(name || '').trim();
            if (cleaned) bag.add(cleaned);
          });
        });
      });
      return Array.from(bag);
    })();
    const activeTopics = urlTopics.length ? urlTopics : derivedTopics;
    const topicsTitle = activeTopics.length ? activeTopics.join(' • ') : title;
    const derivedCategories = (() => {
      const set = new Set();
      levelData.forEach((lvl) => {
        (lvl.questions || []).forEach((q) => {
          const catSources = [
            ...(Array.isArray(q?.meta?.categories) ? q.meta.categories : []),
            ...(Array.isArray(q.categories) ? q.categories : []),
          ];
          if (typeof q.category === 'string') catSources.push(q.category);
          catSources.forEach((name) => {
            const cleaned = String(name || '').trim();
            if (cleaned) set.add(cleaned);
          });
        });
      });
      return Array.from(set);
    })();
    const filterCategories = Array.isArray(payload.filters?.categories) ? payload.filters.categories.filter(Boolean) : [];
    const activeCategories = filterCategories.length ? filterCategories : derivedCategories;
    const perQuestionTime = Number(qs.get('time')) || 15;
    const passThreshold = clamp01(payload.rules?.pass_ratio ?? 0.8);
    const stats = {
      quizId: payload.id,
      title,
      total: 0,
      correct: 0,
      history: [],
      rules: payload.rules || {},
      filters: payload.filters || {},
    };

    function normalizeQuestion(q) {
      let choices = Array.isArray(q.choices) ? q.choices.slice() : Array.isArray(q.options) ? q.options.slice() : [];
      choices = choices.map((choice, idx) => (typeof choice === 'string' ? choice : `Option ${idx + 1}`));
      while (choices.length < 4) {
        const fillers = ['None of the above', 'All of the above', 'Skip', 'Not sure'];
        choices.push(fillers[choices.length % fillers.length]);
      }
      choices = choices.slice(0, 4);
      let correct = Number(q.correct_index ?? q.answer_index ?? q.answer ?? 0);
      if (!Number.isFinite(correct)) correct = 0;
      correct = Math.max(0, Math.min(choices.length - 1, correct));
      return { ...q, choices, correct_index: correct };
    }

    function createModal() {
      const overlay = el("div", { className: "hmqz-overlay" });
      const modal = el("div", { className: "hmqz-modal" });
      overlay.append(modal);
      document.body.append(overlay);
      requestAnimationFrame(() => overlay.classList.add('is-visible'));
      const close = () => {
        overlay.classList.remove('is-visible');
        setTimeout(() => overlay.remove(), 200);
      };
      return { modal, close };
    }

    function launchConfetti(target) {
      if (!target) return;
      const colors = ['#34d399', '#fbbf24', '#f472b6', '#60a5fa'];
      for (let i = 0; i < 14; i++) {
        const piece = el("span", { className: "hmqz-confetti" });
        piece.style.setProperty('--cf', colors[i % colors.length]);
        piece.style.left = `${Math.random() * 90 + 5}%`;
        piece.style.animationDelay = `${Math.random() * 0.25}s`;
        target.append(piece);
        setTimeout(() => piece.remove(), 1500);
      }
    }

    function playLevel(levelIdx) {
      const level = levelData[levelIdx];
      const questions = (level.questions || []).map(normalizeQuestion);
      const perLevelTarget = Number(payload.rules?.per_level) || questions.length;
      const passCountTarget = Math.min(
        questions.length,
        Math.max(1, Math.ceil(perLevelTarget * passThreshold))
      );
      const answeredLog = [];
      let qIndex = 0;
      let correct = 0;
      let timerId = null;
      let advanceTimeout = null;
      let remaining = perQuestionTime;

      clear(root);
      const hero = el("div", { className: "hmqz-legacy-hero" });
      const topRow = el("div", { className: "hmqz-hero-top" });
      const levelBadge = el("div", { className: "hmqz-level-pill" }, `Level ${levelIdx + 1}/${levelData.length}`);
      const timeupBadge = el("span", { className: "hmqz-timeup-badge", hidden: true }, "Time's up!");
      const timerTray = el("div", { className: "hmqz-head-timer" },
        el("span", { className: "hmqz-timer-label" }, "Per-question timer"),
        el("div", { className: "hmqz-timer-pill" }, `${remaining}s`),
        timeupBadge
      );
      if (HMQZ_LOGO_URL) {
        const logoImg = el("img", {
          src: HMQZ_LOGO_URL,
          alt: "HMQUIZ logo",
          className: "hmqz-hero-logo",
          decoding: "async",
        });
        topRow.append(logoImg);
      }
      topRow.append(levelBadge, timerTray);
      hero.append(topRow);

      const titleBlock = el("div", { className: "hmqz-legacy-title" }, topicsTitle || title);
      hero.append(titleBlock);

      const chipWrap = el("div", { className: "hmqz-hero-chips" });
      const addChipGroup = (list, type) => {
        list.forEach((label) => {
          chipWrap.append(el("span", { className: `hmqz-chip ${type}` }, label));
        });
      };
      if (activeTopics.length) addChipGroup(activeTopics, 'topic');
      if (activeCategories.length) addChipGroup(activeCategories, 'category');
      hero.append(chipWrap);

      const card = el("div", { className: "hmqz-legacy-card" });
      root.append(hero, card);

      function updateTimer(isTimeup = false) {
        hero.querySelectorAll('.hmqz-timer-pill').forEach((pill) => {
          pill.textContent = `${Math.max(0, remaining)}s`;
          pill.classList.toggle('is-low', remaining <= 5 && !isTimeup);
          pill.classList.toggle('hmqz-timeup', isTimeup);
        });
        timeupBadge.hidden = !isTimeup;
      }

      function stopTimer() {
        if (timerId) {
          clearInterval(timerId);
          timerId = null;
        }
      }

      function clearAdvance() {
        if (advanceTimeout) {
          clearTimeout(advanceTimeout);
          advanceTimeout = null;
        }
      }

      function queueAdvance() {
        if (advanceTimeout) return;
        advanceTimeout = setTimeout(() => {
          advanceTimeout = null;
          if (qIndex + 1 >= questions.length) {
            showLevelSummary();
          } else {
            qIndex += 1;
            renderQuestion();
          }
        }, 900);
      }

      function startTimer(onExpire) {
        remaining = perQuestionTime;
        updateTimer();
        stopTimer();
        clearAdvance();
        timerId = setInterval(() => {
          remaining -= 1;
          updateTimer();
          if (remaining <= 0) {
            stopTimer();
            onExpire();
          }
        }, 1000);
      }

      function handleAnswer(question, selectedIndex) {
        stopTimer();
        clearAdvance();
        const isCorrect = Number(selectedIndex) === Number(question.correct_index);
        if (isCorrect) correct += 1;
        stats.total += 1;
        if (isCorrect) stats.correct += 1;
        $$(".hmqz-choice-btn", card).forEach((btn, i) => {
          btn.disabled = true;
          btn.classList.toggle('is-correct', i === Number(question.correct_index));
          btn.classList.toggle('is-wrong', !btn.classList.contains('is-correct') && i === selectedIndex);
        });
        answeredLog.push({
          idx: qIndex + 1,
          text: question.text || '',
          choices: Array.isArray(question.choices) ? question.choices.slice() : [],
          correct: Number(question.correct_index),
          selected: Number.isFinite(selectedIndex) ? Number(selectedIndex) : -1
        });
      }

      function renderQuestion() {
        const q = questions[qIndex];
        if (!q) {
          showLevelSummary();
          return;
        }
        card.innerHTML = '';
        clearAdvance();
        const qTitle = el("div", { className: "hmqz-qtext" }, `${qIndex + 1}. ${q.text || ''}`);
        const grid = el("div", { className: "hmqz-grid-choices" });
        const footer = el("div", { className: "hmqz-next-hint", hidden: true }, "Next question…");
        card.append(qTitle, grid, footer);

        q.choices.forEach((choice, idx) => {
          const btn = el("button", { className: "hmqz-choice-btn", type: "button" }, choice);
          btn.addEventListener('click', () => {
            handleAnswer(q, idx);
            footer.hidden = false;
            queueAdvance();
          });
          grid.append(btn);
        });

        startTimer(() => {
          updateTimer(true);
          handleAnswer(q, -1);
          footer.hidden = false;
          queueAdvance();
        });
      }

      function showLevelSummary() {
        clearAdvance();
        const pct = questions.length ? Math.round((correct / questions.length) * 100) : 0;
        const passed = correct >= passCountTarget;
        const levelMetaTotals = questions.reduce((acc, question) => {
          const meta = collectQuestionMetaLists(question);
          meta.topics.forEach((t) => acc.topics.add(t));
          meta.categories.forEach((c) => acc.categories.add(c));
          return acc;
        }, { topics: new Set(), categories: new Set() });
        stats.history.push({
          level: levelIdx + 1,
          correct,
          total: questions.length,
          percent: pct,
          passed,
          topics: Array.from(levelMetaTotals.topics),
          categories: Array.from(levelMetaTotals.categories),
        });
        const { modal, close } = createModal();
        modal.innerHTML = '';
        modal.classList.toggle('hmqz-pass', !!passed);
        modal.classList.toggle('hmqz-fail', !passed);
        const headBlock = el("div", { className: "hmqz-modal-head" },
          el("div", { className: "hmqz-modal-emoji" }, passed ? "🎉" : "⚡"),
          el("div", {},
            el("h3", {}, passed ? `Level ${levelIdx + 1} complete!` : `Level ${levelIdx + 1}: almost there`),
            el("p", { className: "hmqz-modal-sub" }, `Score: ${correct}/${questions.length} (${pct}%). Need ${passCountTarget} correct to pass.`)
          )
        );
        modal.append(headBlock);
        if (passed) launchConfetti(modal);

        const shareWrap = el("div", { className: "hmqz-sharewrap" });
        addEmailBox(shareWrap, { quizId: stats.quizId, title, correct, total: questions.length, history: stats.history.slice() });
        modal.append(shareWrap);

        const actions = el("div", { className: "hmqz-cta modal-actions" });
        const retryBtn = el("button", { className: "hmqz-btn" }, passed ? "Replay this level" : "Retry this level");
        retryBtn.addEventListener('click', () => { close(); playLevel(levelIdx); });
        actions.append(retryBtn);

        if (passed && levelIdx + 1 < levelData.length) {
          const nextBtn = el("button", { className: "hmqz-btn primary" }, "Continue to next level");
          nextBtn.addEventListener('click', () => { close(); playLevel(levelIdx + 1); });
          actions.append(nextBtn);
        } else if (passed) {
          const finishBtn = el("button", { className: "hmqz-btn primary" }, "View final results");
          finishBtn.addEventListener('click', () => { close(); showFinalSummary(); });
          actions.append(finishBtn);
        }

        if (!passed) {
          const topicsBtn = el("a", { className: "hmqz-btn secondary" }, "New set of topics");
          topicsBtn.href = topicsHref;
          actions.append(topicsBtn);
        } else {
          const topicsLink = el("a", {
            className: "hmqz-btn secondary",
            href: topicsHref,
            target: "_blank",
            rel: "noopener"
          }, "Pick new topics");
          actions.append(topicsLink);
        }
        modal.append(actions);

        const reviewToggle = el("button", { className: "hmqz-btn tertiary" }, "Review answers");
        const reviewPanel = el("div", { className: "hmqz-review-panel", hidden: true });
        const buildReview = () => {
          if (reviewPanel.dataset.ready === "1") return;
          answeredLog.forEach((entry) => {
            const item = el("div", { className: "hmqz-review-item" });
            item.append(el("div", { className: "hmqz-review-q" }, `${entry.idx}. ${entry.text || 'Untitled question'}`));
            const choiceWrap = el("div", { className: "hmqz-review-choices" });
            entry.choices.forEach((choice, cIdx) => {
              const classes = ["hmqz-review-choice"];
              if (cIdx === entry.correct) classes.push("is-correct");
              if (entry.selected === cIdx && cIdx !== entry.correct) classes.push("is-picked");
              if (entry.selected === cIdx && cIdx === entry.correct) classes.push("is-picked-correct");
              choiceWrap.append(el("div", { className: classes.join(" ") }, choice));
            });
            if (entry.selected === -1) {
              choiceWrap.append(el("div", { className: "hmqz-review-miss" }, "Skipped / timed out"));
            }
            item.append(choiceWrap);
            reviewPanel.append(item);
          });
          reviewPanel.dataset.ready = "1";
        };
        reviewToggle.addEventListener('click', () => {
          if (reviewPanel.hidden) buildReview();
          reviewPanel.hidden = !reviewPanel.hidden;
          reviewToggle.textContent = reviewPanel.hidden ? "Review answers" : "Hide review";
        });
        modal.append(reviewToggle, reviewPanel);
      }

      renderQuestion();
    }

    function showFinalSummary() {
      clear(root);
      const pct = stats.total ? Math.round((stats.correct / stats.total) * 100) : 0;
      const wrap = el("div", { className: "hmqz-legacy-summary" });
      wrap.append(
        el("h3", {}, `Quiz complete — ${pct}%`),
        el("p", {}, `Correct answers: ${stats.correct} of ${stats.total}`)
      );
      const history = el("ul", { style: "list-style:none;padding:0;margin:12px 0;" });
      stats.history.forEach((lvl) => {
        const item = el("li", { style: "display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(0,0,0,.05);" },
          el("span", {}, `Level ${lvl.level}`),
          el("span", {}, `${lvl.correct}/${lvl.total} (${lvl.percent}%) ${lvl.passed ? '✅' : '❌'}`)
        );
        history.append(item);
      });
      wrap.append(history);
      const actions = el("div", { className: "hmqz-cta" });
      const replay = el("button", { className: "hmqz-btn primary" }, "Play again");
      replay.addEventListener('click', () => playLevel(0));
      actions.append(replay, el("a", { className: "hmqz-btn secondary", href: topicsHref }, "Pick topics again"));
      wrap.append(actions);
      root.append(wrap);
      addEmailBox(wrap, { ...stats, history: stats.history.slice() });
    }

    playLevel(0);
  }

  // ---------- page boot ----------
  async function bootPlayPage() {
    try {
      // find mount
      const root = $("#hmqz-app") || $(".hmqz-app") || (() => {
        const d = el("div", { id: "hmqz-app", className: "hmqz-app" });
        document.body.append(d);
        return d;
      })();
      document.body.classList.add('hmqz-play-active');
      const wpTitle = document.querySelector('.entry-title');
      if (wpTitle) wpTitle.style.display = 'none';
      const entryHeader = document.querySelector('.entry-header');
      if (entryHeader) entryHeader.style.display = 'none';

      const quizId = qs.get("quiz");
      const allowOverride = qs.get("allowOverride") === "1";
      let payload;
      let bankSource = "url";
      const filterTopics = parseList(qs.get("topics"));
      const filterCategories = parseList(qs.get("categories"));
      const forceShuffle = qs.get("random") === "1";

      if (quizId) {
        const restPath = `/hmqz/v1/flash/${encodeURIComponent(quizId)}`;
const apiRoot = (window.wpApiSettings && window.wpApiSettings.root) ?
  window.wpApiSettings.root.replace(/\/+$/, "/") : "/wp-json/";
        const primary  = `${location.origin}/wp-json${restPath}`;
        const fallback = `${location.origin}/wp-json${restPath}`;
        const fetcher  = window.fetchJSONWithFallback || ((p, f) => fetch(p).then(r => r.json()).catch(() => fetch(f).then(r => r.json())));
        payload = await fetcher(primary, fallback);
        bankSource = (allowOverride && qs.get("bank")) ? "url" : "quiz-meta";

        if (allowOverride) {
          const overridePer = Number(qs.get("per"));
          if (overridePer > 0) {
            payload.rules = payload.rules || {};
            payload.rules.per_level = overridePer;
          }
          const overridePass = clamp01(qs.get("threshold") || qs.get("pass_ratio") || qs.get("pass"), payload.rules?.pass_ratio || 0.6);
          payload.rules = payload.rules || {};
          payload.rules.pass_ratio = overridePass;

          const overrideBank = qs.get("bank");
          if (overrideBank) {
            payload = await buildPayloadFromBank(overrideBank, payload.rules.per_level, payload.rules.pass_ratio);
          }
        }
      } else {
        const demoBank = qs.get("bank");
        if (!demoBank) return;
        const per = Number(qs.get("per")) || 5;
        const pass = clamp01(qs.get("threshold") || qs.get("pass_ratio") || qs.get("pass"), 0.6);
        payload = await buildPayloadFromBank(demoBank, per, pass);
        bankSource = "url";
      }

      payload = applyQuestionFilters(payload, filterTopics, filterCategories, forceShuffle);

      console.info("[HMQUIZ] bank source =", bankSource);

      const mode = (qs.get("mode") || "legacy").toLowerCase();
      if (mode === "classic") {
        renderClassic(root, payload);
      } else if (mode === "legacy" || mode === "timer") {
        renderLegacyTimer(root, payload);
      } else {
        renderLevels(root, payload);
      }

      window.HMQUIZ = {
        payload,
        mode,
        source: bankSource,
        rerenderClassic: () => renderClassic(root, payload),
        rerenderLevels: () => renderLevels(root, payload),
      };
    } catch (err) {
      console.error("[HMQUIZ] boot error:", err);

      // visible error for users
      const root = $("#hmqz-app") || $(".hmqz-app") || document.body;
      const box  = el("div", { className: "hmqz-banner fail" },
        el("strong", {}, "Error"),
        " — Unable to load quiz. Please refresh or try again."
      );
      root.append(box);
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bootPlayPage);
  } else {
    bootPlayPage();
  }
})();
