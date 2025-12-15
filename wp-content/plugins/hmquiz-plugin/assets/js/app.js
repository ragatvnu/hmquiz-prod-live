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

  function hmqzGetNextHubUrl(cfg) {
    const config = cfg || window.HMQZCFG || {};
    const ret = (config && typeof config.returnUrl === 'string') ? config.returnUrl.trim() : '';
    if (ret) return ret;

    const hub = (config && typeof config.hubUrl === 'string') ? config.hubUrl.trim() : '';
    if (hub) return hub;

    const topics = (config && typeof config.topicsUrl === 'string') ? config.topicsUrl.trim() : '';
    if (topics) return topics;

    return '/';
  }
  if (!window.hmqzGetNextHubUrl) {
    window.hmqzGetNextHubUrl = hmqzGetNextHubUrl;
  }

  const normalizeLegacyBankSlug = (bank = '') => {
    const trimmed = String(bank || '').trim().replace(/^\/+/, '');
    // Keep this in sync with the PHP normalizer (hmqz_normalize_bank_slug).
    if (trimmed.includes('/')) return trimmed;
    if (trimmed.startsWith('mcq_confusing_words_')) {
      const suffix = trimmed.slice('mcq_confusing_words_'.length);
      return `english_grammar/confusing_words/mcq_confusables_${suffix}`;
    }
    if (/^mcq_confusables_.*\.json$/i.test(trimmed)) {
      return `english_grammar/confusing_words/${trimmed}`;
    }
    return trimmed;
  };
  const sanitizeBankName = (name = '') => {
    const normalized = normalizeLegacyBankSlug(name);
    return String(normalized)
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
    const url = base.startsWith('http')
      ? `${base}/${safeName}`
      : `${location.origin}${base}/${safeName}`;

    const res = await fetch(url, { headers: { Accept: 'application/json' } });
    if (!res.ok) {
      throw new Error(`Bank fetch failed (${res.status})`);
    }

    const data = await res.json();
    const rawItems = Array.isArray(data.items)
      ? data.items
      : (Array.isArray(data) ? data : []);

    if (!rawItems.length) {
      throw new Error("Bank has no questions");
    }

    const questions = rawItems.map((item) => {
      const text = String(item.q ?? item.text ?? '');
      let choices = item.options ?? item.choices ?? [];
      if (!Array.isArray(choices)) choices = [];
      choices = choices.map((c) => String(c ?? ''));
      if (!choices.length) {
        throw new Error("Question missing options");
      }

      let correct = 0;
      if (typeof item.correct_index === 'number') correct = item.correct_index;
      else if (typeof item.answer === 'number') correct = item.answer;
      else if (typeof item.answer_index === 'number') correct = item.answer_index;

      correct = Math.max(0, Math.min(choices.length - 1, correct));

      const topicList = [];
      if (Array.isArray(item.topics)) topicList.push(...item.topics);
      if (Array.isArray(item.tags)) topicList.push(...item.tags);
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

      const explanation = String(item.explanation ?? item.explain ?? item.meta?.explanation ?? item.meta?.explain ?? '').trim();

      return {
        text,
        choices,
        correct,
        explanation,
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

    // NEW: choose display title (JSON title → URL title → filename)
    const qs = new URLSearchParams(window.location.search);
    const urlTitle = (qs.get("title") || "").trim();

    const displayTitle =
      (data && typeof data.title === "string" && data.title.trim()) ||
      urlTitle ||
      safeName.replace(/_/g, ' ');

    return {
      title: displayTitle,
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

    const qs = new URLSearchParams(window.location.search);
    const urlTitle = (qs.get("title") || "").trim();

    const payloadTitle =
      payload && typeof payload.title === "string"
        ? payload.title.trim()
        : "";

    const effectiveTitle =
      urlTitle ||
      payloadTitle ||      
      "Quiz";

    const levels = payload.levels || [];
    const allQ = (levels || []).flatMap(l => l.questions || []);

    const head = el("div", { className: "hmqz-headrow" },
      el("h2", { className: "hmqz-title" }, effectiveTitle),
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

    const qs = new URLSearchParams(window.location.search);
    const urlTitle = (qs.get("title") || "").trim();
    const payloadTitle =
      payload && typeof payload.title === "string"
        ? payload.title.trim()
        : "";
    const effectiveTitle =
      urlTitle ||  
      payloadTitle ||      
      "Quiz";

    const levels = (payload.levels || []).map(l => ({ ...l }));

    let idx = 0;
    let passed = 0;

    // header
    const badge = el("div", { className: "hmqz-level-badge" }, `Level ${idx + 1}/${levels.length}`);
    const head = el("div", { className: "hmqz-headrow" },
      el("h2", { className: "hmqz-title" }, effectiveTitle),
      badge
    );
    const subtitle = el(
      "div",
      { className: "hmqz-subtitle" },
      `Pass ≥ ${Math.ceil(perLevel * passRatio)} of ${perLevel} to advance`
    );
    const progTxt = el("div", { className: "hmqz-prog-text" }, `Level ${idx + 1}/${levels.length} — Passed ${passed}/${levels.length}`);
    const barInner = el("div", { className: "hmqz-prog-bar-inner" });
    const bar = el("div", { className: "hmqz-prog-bar" }, barInner);
    const headerWrap = el("div", { className: "hmqz-header" }, head, subtitle, progTxt, bar);
    root.append(headerWrap);

    const card = el("div", { className: "hmqz-card" });
    root.append(card);

    const cta = el("div", { className: "hmqz-cta" });
    const btnSubmit = el("button", { className: "hmqz-btn primary" }, "Submit Level");
    const btnRetry  = el("button", { className: "hmqz-btn hmqz-retry", disabled: true }, "Retry Level");
    const btnNext   = el("button", { className: "hmqz-btn hmqz-next", disabled: true }, "Next Level");
    cta.append(btnSubmit, btnRetry, btnNext);
    root.append(cta);

    function updateHeader() {
      badge.textContent = `Level ${idx + 1}/${levels.length}`;
      progTxt.textContent = `Level ${idx + 1}/${levels.length} — Passed ${passed}/${levels.length}`;
      const pct = levels.length ? (passed / levels.length) * 100 : 0;
      barInner.style.width = `${pct}%`;
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
        const subj = encodeURIComponent(`${effectiveTitle} — I scored ${pct}%`);
        const body = encodeURIComponent(`I completed ${effectiveTitle} on HMQUIZ and passed ${passed}/${total} levels!`);
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
    const percent = stats.total ? Math.round((stats.correct / stats.total) * 100) : 0;
    const perLevelRule = Math.max(1, Number(stats.rules?.per_level) || 5);
    const wrapClasses = ['hmqz-emailbox'];
    if (!container.closest('.hmqz-modal')) {
      wrapClasses.push('hmqz-emailbox-inline');
    }
    const group = el("div", { className: wrapClasses.join(' ') });
    const card = el("div", { className: "hmqz-emailbox-card" });
    card.innerHTML = `
      <div class="hmqz-score-row">
        <div class="hmqz-score-pill">
          <span class="hmqz-score-label">Score</span>
          <span class="hmqz-score-value">${stats.correct}/${stats.total}</span>
        </div>
        <div class="hmqz-score-pill">
          <span class="hmqz-score-label">Accuracy</span>
          <span class="hmqz-score-value">${percent}%</span>
        </div>
        <div class="hmqz-score-pill">
          <span class="hmqz-score-label">Rule</span>
          <span class="hmqz-score-value">${perLevelRule}&nbsp;Q/level</span>
        </div>
      </div>
      <div class="hmqz-email-fields">
        <input type="text" class="hmqz-input hmqz-name" placeholder="Player name">
        <input type="email" class="hmqz-input hmqz-email" placeholder="you@example.com" value="${storedEmail.replace(/\"/g, '&quot;')}">
      </div>
      <div class="hmqz-email-actions">
        <button type="button" class="hmqz-btn primary hmqz-email-send">Email score</button>
        <button type="button" class="hmqz-btn ghost hmqz-print">Save / Print PDF</button>
        <button type="button" class="hmqz-btn secondary hmqz-copy-link">Copy share link</button>
      </div>
      <p class="hmqz-email-note">We only use this to send your scorecard.</p>
    `;
    group.append(card);
    container.append(group);

    const nameInput = card.querySelector('.hmqz-name');
    const emailInput = card.querySelector('.hmqz-email');
    const note = card.querySelector('.hmqz-email-note');
    const printBtn = card.querySelector('.hmqz-print');
    const copyBtn = card.querySelector('.hmqz-copy-link');
    const shareBtnWrap = el("div", { className: "hmqz-share-buttons" });
    const lastHistory = Array.isArray(stats.history) && stats.history.length
      ? stats.history[stats.history.length - 1]
      : null;
    const derivedHistoryTopic = lastHistory && Array.isArray(lastHistory.topics) && lastHistory.topics.length
      ? lastHistory.topics[0]
      : '';
    const shareTopic = stats.shareTopic
      || (Array.isArray(stats.filters?.topics) && stats.filters.topics[0])
      || (Array.isArray(stats.filters?.categories) && stats.filters.categories[0])
      || derivedHistoryTopic
      || '';
    const shareTitle = stats.title || document.title || 'HMQUIZ';
    const shareUrl = location.href.split('#')[0];
    const shareMessage = shareTopic
      ? `I just scored ${stats.correct}/${stats.total} on ${shareTitle} (${shareTopic})!`
      : `I just scored ${stats.correct}/${stats.total} on ${shareTitle}!`;
    const shareTargets = [
      {
        id: 'facebook',
        label: 'Facebook',
        buildUrl: () =>
          `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(shareUrl)}&quote=${encodeURIComponent(shareMessage)}`,
      },
      {
        id: 'twitter',
        label: 'X / Twitter',
        buildUrl: () =>
          `https://twitter.com/intent/tweet?text=${encodeURIComponent(shareMessage)}&url=${encodeURIComponent(shareUrl)}`,
      },
      {
        id: 'whatsapp',
        label: 'WhatsApp',
        buildUrl: () =>
          `https://wa.me/?text=${encodeURIComponent(`${shareMessage} ${shareUrl}`)}`,
      },
    ];
    shareTargets.forEach(({ id, label, buildUrl }) => {
      const btn = el("button", { className: `hmqz-share-btn ${id}`, type: "button" }, label);
      btn.addEventListener('click', () => {
        const link = buildUrl();
        window.open(link, '_blank', 'noopener');
      });
      shareBtnWrap.append(btn);
    });
    card.insertBefore(shareBtnWrap, card.querySelector('.hmqz-email-note'));
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

    if (copyBtn) {
      copyBtn.addEventListener('click', async () => {
        try {
          await navigator.clipboard.writeText(location.href);
          note.textContent = 'Link copied. Share it with friends!';
          note.style.color = '#15803d';
        } catch (err) {
          console.warn('[HMQUIZ] clipboard failed', err);
          note.textContent = 'Unable to copy automatically — select the URL and copy.';
          note.style.color = '#dc2626';
        }
      });
    }

    const sendBtn = card.querySelector('.hmqz-email-send');
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
            const res = await fetch(window.hmqzApi.endpoint, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': window.hmqzApi.nonce,
              },
              body: JSON.stringify(payload),
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
  }

  function hmqzValidateEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email).trim());
  }

  function renderLegacyTimer(root, payload) {
    // Reset completion flag when (re)starting play
    document.body.classList.remove('hmqz-quiz-done', 'hmqz-review-mode');
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
    const perQuestionTime = Number(qs.get('time')) || 15;
    const passThreshold = clamp01(payload.rules?.pass_ratio ?? 0.8);
    const headerLevelEl = document.querySelector('.js-hmqz-level');
    const headerTimerEl = document.querySelector('.js-hmqz-timer');
    const headerProgressEl = document.querySelector('.js-hmqz-progress');
    const headerMetaEl = document.querySelector('.js-hmqz-qmeta');
    const nextBtn = document.querySelector('.js-hmqz-next');
    let inlineReviewPanel = null;
    const topicsHref = hmqzGetNextHubUrl(window.HMQZCFG);
    let modalStep = 'rate'; // 'rate' | 'score'
    const defaultPerLevel = Math.max(1, Number(payload.rules?.per_level) || 5);
    const levelQuestionCounts = levelData.map((lvl) => {
      const len = Array.isArray(lvl.questions) ? lvl.questions.length : 0;
      return len || defaultPerLevel;
    });
    const stats = {
      quizId: payload.id,
      title,
      total: 0,
      correct: 0,
      history: [],
      rules: payload.rules || {},
      filters: payload.filters || {},
    };

    const formatDuration = (secs) => {
      const clamped = Math.max(0, Math.floor(secs));
      const mm = String(Math.floor(clamped / 60)).padStart(2, '0');
      const ss = String(clamped % 60).padStart(2, '0');
      return `${mm}:${ss}`;
    };

    const updateNextButton = (label, disabled = false) => {
      if (!nextBtn) return;
      const labelSpan = nextBtn.querySelector('span');
      if (labelSpan) {
        labelSpan.textContent = label;
      } else {
        nextBtn.textContent = label;
      }
      nextBtn.disabled = !!disabled;
    };

    const updateHeaderProgress = (levelIdx, localIdx) => {
      if (!headerMetaEl && !headerProgressEl) return;
      const levelTotal = levelQuestionCounts[levelIdx] || defaultPerLevel;
      const current = Math.min(levelTotal, localIdx + 1);
      if (headerMetaEl) {
        headerMetaEl.textContent = `Q ${current}/${levelTotal}`;
      }
      if (headerProgressEl) {
        const pct = (current / levelTotal) * 100;
        headerProgressEl.style.width = `${Math.min(100, Math.max(0, pct))}%`;
      }
    };

    const setCompletionCTA = () => {
      if (!nextBtn) return;
      const labelSpan = nextBtn.querySelector('span');
      if (labelSpan) {
        labelSpan.textContent = 'Play another quiz';
      } else {
        nextBtn.textContent = 'Play another quiz';
      }
      nextBtn.disabled = false;
      nextBtn.onclick = () => {
        window.location.href = topicsHref;
      };
    };

    const sendQuickRating = (value) => {
      if (!window.hmqzRating || !hmqzRating.restUrl || !hmqzRating.postId) return;
      try {
        const params = new URLSearchParams();
        params.append('post_id', hmqzRating.postId);
        params.append('rating', value);
        fetch(hmqzRating.restUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            ...(hmqzRating.nonce ? { 'X-WP-Nonce': hmqzRating.nonce } : {})
          },
          body: params.toString()
        }).catch((err) => console.error('[HMQUIZ rating]', err));
      } catch (err) {
        console.error('[HMQUIZ rating]', err);
      }
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
      const close = () => {
        overlay.classList.remove('is-visible');
        document.body.classList.remove('hmqz-modal-open');
        document.removeEventListener('keydown', onKeyDown);
        setTimeout(() => overlay.remove(), 200);
      };
      const onKeyDown = (evt) => {
        if (evt.key === 'Escape') close();
      };
      overlay.addEventListener('click', (evt) => {
        if (evt.target === overlay) close();
      });
      document.body.append(overlay);
      document.body.classList.add('hmqz-modal-open');
      document.addEventListener('keydown', onKeyDown);
      requestAnimationFrame(() => overlay.classList.add('is-visible'));
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
      document.body.classList.remove('hmqz-quiz-done', 'hmqz-review-mode');
      const level = levelData[levelIdx];
      const questions = (level.questions || []).map(normalizeQuestion);
      if (headerLevelEl) {
        headerLevelEl.textContent = `Level ${levelIdx + 1}/${levelData.length}`;
      }
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
      let hasAnswered = false;
      let currentQuestion = null;

      clear(root);
      const card = el("div", { className: "hmqz-legacy-card" });
      root.append(card);
      // Inline review panel lives under the play card; hidden until requested post-quiz.
      inlineReviewPanel = document.querySelector('.hmqz-review-panel-inline');
      if (!inlineReviewPanel) {
        inlineReviewPanel = el("div", { className: "hmqz-review-panel hmqz-review-panel-inline", hidden: true });
        root.append(inlineReviewPanel);
      } else {
        inlineReviewPanel.innerHTML = '';
        inlineReviewPanel.hidden = true;
        inlineReviewPanel.classList.remove('hmqz-review-open');
        inlineReviewPanel.dataset.ready = "0";
      }
      updateHeaderProgress(levelIdx, qIndex);
      if (headerTimerEl) {
        headerTimerEl.textContent = formatDuration(perQuestionTime);
      }

      function updateTimer(isTimeup = false) {
        const safeRemaining = Math.max(0, remaining);
        if (headerTimerEl) {
          headerTimerEl.textContent = formatDuration(safeRemaining);
        }
        card.classList.toggle('hmqz-card-timeup', !!isTimeup);
        card.classList.toggle('hmqz-card-low', !isTimeup && safeRemaining <= 5);
        if (!isTimeup && safeRemaining > 5) {
          card.classList.remove('hmqz-card-low');
        }
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

      function advanceImmediately() {
        clearAdvance();
        stopTimer();
        if (qIndex + 1 >= questions.length) {
          showLevelSummary();
        } else {
          qIndex += 1;
          renderQuestion();
        }
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
        hasAnswered = true;
        updateNextButton('Next question', false);
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
          selected: Number.isFinite(selectedIndex) ? Number(selectedIndex) : -1,
          explanation:
            question.explanation ||
            question.explain ||
            (question.meta && (question.meta.explanation || question.meta.explain)) ||
            ''
        });
      }

      function renderQuestion() {
        const q = questions[qIndex];
        if (!q) {
          showLevelSummary();
          return;
        }
        currentQuestion = q;
        hasAnswered = false;
        const performRender = () => {
          card.classList.remove('hmqz-card-low', 'hmqz-card-timeup', 'hmqz-q-exit');
          card.innerHTML = '';
          clearAdvance();
          updateHeaderProgress(levelIdx, qIndex);
          const qTitle = el("div", { className: "hmqz-qtext" }, `${qIndex + 1}. ${q.text || ''}`);
          const grid = el("div", { className: "hmqz-grid-choices" });
          const footer = el("div", { className: "hmqz-next-hint", hidden: true }, "Next question…");
          card.append(qTitle, grid, footer);

          updateNextButton('Skip question', false);

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

          requestAnimationFrame(() => {
            card.classList.add('hmqz-q-enter');
            setTimeout(() => {
              card.classList.remove('hmqz-q-enter');
            }, 240);
          });
        };

        if (card.childElementCount) {
          card.classList.add('hmqz-q-exit');
          setTimeout(performRender, 140);
        } else {
          performRender();
        }
      }

      if (nextBtn) {
        nextBtn.onclick = () => {
          if (!currentQuestion) return;
          if (!hasAnswered) {
            // Skip current question immediately
            handleAnswer(currentQuestion, -1);
            advanceImmediately();
          } else {
            advanceImmediately();
          }
        };
      }

      function showLevelSummary() {
        clearAdvance();
        document.body.classList.add('hmqz-quiz-done');
        document.body.classList.add('hmqz-modal-open');
        updateNextButton('Next question', true);
        setCompletionCTA();
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

        const buildReview = () => {
          if (!inlineReviewPanel || inlineReviewPanel.dataset.ready === "1") return;
          inlineReviewPanel.innerHTML = '';
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
            if (entry.explanation) {
              const expl = el("div", { className: "hmqz-review-expl" }, entry.explanation);
              item.append(expl);
            }
            inlineReviewPanel.append(item);
          });
          const panelCta = el("div", { className: "hmqz-review-cta" });
          const panelBtn = el("button", { className: "hmqz-btn secondary" }, "Play another quiz");
          panelBtn.addEventListener('click', () => {
            window.location.href = topicsHref;
          });
          panelCta.append(panelBtn);
          inlineReviewPanel.append(panelCta);

          inlineReviewPanel.dataset.ready = "1";
        };

        const { modal, close } = createModal();
        modal.innerHTML = '';

        const setStep = (step) => {
          modalStep = step;
          modal.dataset.hmqzStep = step;
          modal.classList.toggle('hmqz-step-rate', step === 'rate');
          modal.classList.toggle('hmqz-step-score', step === 'score');
          document.body.classList.add('hmqz-modal-open');
        };

        // Step 1: rating
        let pickedRating = 0;
        const rateStep = el("div", { className: "hmqz-modal-step hmqz-modal-step-rate" });
        rateStep.append(
          el("div", { className: "hmqz-modal-head" },
            el("div", { className: "hmqz-modal-emoji" }, "⭐"),
            el("div", {},
              el("h3", {}, "How was this quiz?"),
              el("p", { className: "hmqz-modal-sub" }, "Please rate this quiz before seeing your score.")
            )
          )
        );
        const starsWrap = el("div", { className: "hmqz-modal-rating js-hmqz-modal-rating" });
        const starButtons = [];
        for (let i = 1; i <= 5; i++) {
          const btn = el("button", { type: "button", className: "hmqz-modal-star", "data-value": i }, "★");
          btn.addEventListener('click', () => {
            pickedRating = i;
            starButtons.forEach((b, idx) => b.classList.toggle('is-selected', idx < i));
            continueBtn.disabled = false;
          });
          starsWrap.append(btn);
          starButtons.push(btn);
        }
        rateStep.append(starsWrap);
        const rateActions = el("div", { className: "hmqz-cta modal-actions" });
        const continueBtn = el("button", { className: "hmqz-btn primary", type: "button", disabled: true }, "Continue");
        const skipBtn = el("button", { className: "hmqz-btn ghost", type: "button" }, "Skip for now");
        continueBtn.addEventListener('click', () => {
          if (pickedRating > 0) sendQuickRating(pickedRating);
          setStep('score');
        });
        skipBtn.addEventListener('click', () => setStep('score'));
        rateActions.append(continueBtn, skipBtn);
        rateStep.append(rateActions);

        // Step 2: score
        const scoreStep = el("div", { className: "hmqz-modal-step hmqz-modal-step-score" });
        scoreStep.classList.toggle('hmqz-pass', !!passed);
        scoreStep.classList.toggle('hmqz-fail', !passed);
        const headBlock = el("div", { className: "hmqz-modal-head" },
          el("div", { className: "hmqz-modal-emoji" }, passed ? "🎉" : "⚡"),
          el("div", {},
            el("h3", {}, passed ? `Level ${levelIdx + 1} complete!` : `Level ${levelIdx + 1}: almost there`),
            el("p", { className: "hmqz-modal-sub" }, `Score: ${correct}/${questions.length} (${pct}%). Need ${passCountTarget} correct to pass.`)
          )
        );
        scoreStep.append(headBlock);
        if (passed) launchConfetti(scoreStep);

        const shareWrap = el("div", { className: "hmqz-sharewrap" });
        const primaryTopic = levelMetaTotals.topics[0]
          || (Array.isArray(stats.filters?.topics) && stats.filters.topics[0])
          || (Array.isArray(stats.filters?.categories) && stats.filters.categories[0])
          || '';
        addEmailBox(shareWrap, {
          quizId: stats.quizId,
          title,
          correct,
          total: questions.length,
          history: stats.history.slice(),
          filters: stats.filters,
          rules: stats.rules,
          shareTopic: primaryTopic,
        });
        scoreStep.append(shareWrap);

        const reviewNote = el("div", { className: "hmqz-review-note" }, "Want to see your answers? Open the review below.");
        const reviewToggle = el("button", { className: "hmqz-review-toggle js-hmqz-toggle-review", type: "button" }, "Review answers below");
        reviewToggle.addEventListener('click', () => {
          if (!inlineReviewPanel) return;
          buildReview();
          inlineReviewPanel.hidden = false;
          inlineReviewPanel.classList.add('hmqz-review-open');
          document.body.classList.add('hmqz-review-mode');
          document.body.classList.remove('hmqz-modal-open');
          close();
          setCompletionCTA();
          inlineReviewPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
        scoreStep.append(reviewNote, reviewToggle);

        const actions = el("div", { className: "hmqz-cta modal-actions" });
        const retryBtn = el("button", { className: "hmqz-btn" }, passed ? "Replay this level" : "Retry this level");
        retryBtn.addEventListener('click', () => { document.body.classList.remove('hmqz-modal-open'); close(); playLevel(levelIdx); });
        actions.append(retryBtn);

        if (passed && levelIdx + 1 < levelData.length) {
          const nextBtn = el("button", { className: "hmqz-btn primary" }, "Continue to next level");
          nextBtn.addEventListener('click', () => { document.body.classList.remove('hmqz-modal-open'); close(); playLevel(levelIdx + 1); });
          actions.append(nextBtn);
        } else if (passed) {
          const finishBtn = el("button", { className: "hmqz-btn primary" }, "View final results");
          finishBtn.addEventListener('click', () => { document.body.classList.remove('hmqz-modal-open'); close(); showFinalSummary(); });
          actions.append(finishBtn);
        }

        const topicsBtn = el("button", { className: "hmqz-btn secondary" }, "Play another quiz");
        topicsBtn.addEventListener('click', () => {
          document.body.classList.remove('hmqz-modal-open');
          close();
          window.location.href = topicsHref;
        });
        actions.append(topicsBtn);
        scoreStep.append(actions);

        modal.append(rateStep, scoreStep);
        setStep('rate');
      }

      renderQuestion();
    }

    function showFinalSummary() {
      clear(root);
      const pct = stats.total ? Math.round((stats.correct / stats.total) * 100) : 0;
      const wrap = el("div", { className: "hmqz-legacy-summary" });
      wrap.append(
        el("div", { className: "hmqz-card-eyebrow" }, "Final results"),
        el("h3", {}, stats.title ? `${stats.title} — ${pct}%` : `Quiz complete — ${pct}%`),
        el("p", {}, `You answered ${stats.correct} of ${stats.total} questions correctly.`)
      );
      const history = el("ul", { className: "hmqz-final-history" });
      stats.history.forEach((lvl) => {
        const item = el("li", {},
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
      const summaryTopic = (Array.isArray(stats.filters?.topics) && stats.filters.topics[0])
        || (Array.isArray(stats.filters?.categories) && stats.filters.categories[0])
        || (stats.history.length && Array.isArray(stats.history[stats.history.length - 1].topics) && stats.history[stats.history.length - 1].topics[0])
        || '';
      addEmailBox(wrap, { ...stats, history: stats.history.slice(), shareTopic: summaryTopic });
      document.body.classList.add('hmqz-quiz-done');
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
        const restPath = `/wp-json/hmqz/v1/flash/${encodeURIComponent(quizId)}`;
        const primary  = `${location.origin}/wp-json${restPath}`;
        const fallback = `${location.origin}/?rest_route=${restPath}`;
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
