#!/usr/bin/env bash
set -e

# Assumes WP_PATH is already exported, e.g.:
# export WP_PATH="/home/u290655997/domains/hmquiz.com/public_html"

cd "$WP_PATH"

echo "== 1) Ensure 'Quizzes' parent page exists =="

QUIZZ_PARENT_ID=$(wp --path="$WP_PATH" post list \
  --post_type=page \
  --name=quizzes \
  --field=ID \
  --format=ids)

if [ -z "$QUIZZ_PARENT_ID" ]; then
  echo "Creating parent page: Quizzes (/quizzes/)"
  QUIZZ_PARENT_ID=$(wp --path="$WP_PATH" post create \
    --post_type=page \
    --post_status=publish \
    --post_title="Quizzes" \
    --post_name="quizzes" \
    --porcelain)
else
  echo "Found existing 'Quizzes' page with ID: $QUIZZ_PARENT_ID"
fi

echo "Parent ID = $QUIZZ_PARENT_ID"
echo

###############################################################################
# 2) English Grammar Hub
###############################################################################
cat > /tmp/hmqz-hub-english-grammar.txt << 'EOF'
<h1>English Grammar Quizzes – Fix Common Mistakes Fast</h1>
<p>Grammar mistakes happen to everyone – even fluent speakers. This page collects all our English grammar quizzes in one place so you can practise confusing words, punctuation, 
tenses, and more. Start with easy quizzes and slowly move to harder levels as your confidence grows.</p>

<h2>Popular Grammar Quizzes</h2>
<ul>
  <li><a href="/quiz/english-grammar-test/">English Grammar Test</a> – A quick check of your overall grammar.</li>
  <li><a href="/quiz/affect-vs-effect/">Affect vs Effect</a> – Master one of the most confusing pairs in English.</li>
  <li><a href="/quiz/semicolon-or-comma/">Semicolon or Comma</a> – Test your punctuation sense.</li>
</ul>

<h2>Confusing Words Quizzes</h2>
<ul>
  <li><a href="/quiz/whose-vs-whos/">Whose vs Who’s</a></li>
  <li><a href="/quiz/its-vs-its/">Its vs It’s</a></li>
</ul>

<p>Want more practice? Go back to the main <a href="/quiz/">Play Quizzes</a> hub.</p>
EOF

echo "Creating English Grammar hub page…"
/usr/local/bin/wp --path="$WP_PATH" post create /tmp/hmqz-hub-english-grammar.txt \
  --post_type=page \
  --post_status=publish \
  --post_title="English Grammar Quizzes" \
  --post_name="english-grammar" \
  --post_parent="$QUIZZ_PARENT_ID" \
  --porcelain

###############################################################################
# 3) General Knowledge Hub
###############################################################################
cat > /tmp/hmqz-hub-gk.txt << 'EOF'
<h1>General Knowledge Quizzes – Test Your GK Every Day</h1>
<p>This hub brings together all our General Knowledge quizzes: countries, capitals, science, history, and more. Use these quizzes to prepare for exams, interview tests, or just 
to challenge your friends.</p>

<h2>Essential GK Quizzes</h2>
<ul>
  <li>World Capitals Quiz (coming soon)</li>
  <li>India GK Quiz (coming soon)</li>
  <li>Science &amp; Space Quiz (coming soon)</li>
</ul>

<h2>Quick Practice</h2>
<p>Play a short mixed GK quiz from our game engine:</p>
<ul>
  <li><a href="/play/?bank=mcq_master.json&amp;topics=General%20Knowledge&amp;per=10&amp;random=1">10 random GK questions</a></li>
</ul>

<p>Explore more categories on the main <a href="/quiz/">Play Quizzes</a> hub.</p>
EOF

echo "Creating General Knowledge hub page…"
wp --path="$WP_PATH" post create /tmp/hmqz-hub-gk.txt \
  --post_type=page \
  --post_status=publish \
  --post_title="General Knowledge Quizzes" \
  --post_name="general-knowledge" \
  --post_parent="$QUIZZ_PARENT_ID" \
  --porcelain

###############################################################################
# 4) Confusing Words Hub
###############################################################################
cat > /tmp/hmqz-hub-confusables.txt << 'EOF'
<h1>Confusing Words Quizzes – Stop Making Silly Mistakes</h1>
<p>English is full of words that look or sound similar but mean very different things. This page collects all our “confusing words” quizzes so you can practise them in one 
place.</p>

<h2>Must-Do Confusing Words Quizzes</h2>
<ul>
  <li><a href="/quiz/affect-vs-effect/">Affect vs Effect</a></li>
  <li><a href="/quiz/whose-vs-whos/">Whose vs Who’s</a></li>
  <li><a href="/quiz/its-vs-its/">Its vs It’s</a></li>
</ul>

<p>For more grammar practice, visit the <a href="/quizzes/english-grammar/">English Grammar hub</a> or go back to the main <a href="/quiz/">Play Quizzes</a> page.</p>
EOF

echo "Creating Confusing Words hub page…"
wp --path="$WP_PATH" post create /tmp/hmqz-hub-confusables.txt \
  --post_type=page \
  --post_status=publish \
  --post_title="Confusing Words Quizzes" \
  --post_name="confusables" \
  --post_parent="$QUIZZ_PARENT_ID" \
  --porcelain

###############################################################################
# 5) Emoji & Fun Hub
###############################################################################
cat > /tmp/hmqz-hub-emoji.txt << 'EOF'
<h1>Emoji &amp; Fun Quizzes – Train Your Brain the Fun Way</h1>
<p>Love visual puzzles and emojis? This hub collects all our “fun” quizzes – odd-one-out emoji games, hidden patterns, fast reaction challenges, and more. Great for kids, 
families, and anyone who loves visual brain games.</p>

<h2>Emoji &amp; Visual Puzzles</h2>
<ul>
  <li>Spot the odd emoji (coming soon)</li>
  <li>Emoji memory challenges (coming soon)</li>
  <li>Visual pattern puzzles (coming soon)</li>
</ul>

<p>Check back soon as we add more emoji and visual quizzes, or explore all categories on the <a href="/quiz/">Play Quizzes</a> hub.</p>
EOF

echo "Creating Emoji & Fun hub page…"
wp --path="$WP_PATH" post create /tmp/hmqz-hub-emoji.txt \
  --post_type=page \
  --post_status=publish \
  --post_title="Emoji & Fun Quizzes" \
  --post_name="emoji" \
  --post_parent="$QUIZZ_PARENT_ID" \
  --porcelain

###############################################################################
# 6) Brain Teasers Hub
###############################################################################
cat > /tmp/hmqz-hub-brain.txt << 'EOF'
<h1>Brain Teaser Quizzes – Logic, Memory and Focus</h1>
<p>Short brain teasers are one of the best ways to keep your mind sharp. Use our brain teaser quizzes to practise logic, memory, patterns, and problem-solving.</p>

<h2>Brain Teaser Ideas</h2>
<ul>
  <li>Pattern recognition quizzes (coming soon)</li>
  <li>Logic mini-tests (coming soon)</li>
  <li>More brain teasers and puzzles (coming soon)</li>
</ul>

<p>Start with our English grammar or general knowledge quizzes, then come back here as we add more brain teaser games. Visit the main <a href="/quiz/">Play Quizzes</a> page to 
see all categories.</p>
EOF

echo "Creating Brain Teasers hub page…"
wp --path="$WP_PATH" post create /tmp/hmqz-hub-brain.txt \
  --post_type=page \
  --post_status=publish \
  --post_title="Brain Teaser Quizzes" \
  --post_name="brain-teasers" \
  --post_parent="$QUIZZ_PARENT_ID" \
  --porcelain

echo
echo "All hub pages created. You can verify with:"
echo "  wp --path=\"$WP_PATH\" post list --post_type=page --post_status=publish | grep -E 'Quizzes|English Grammar|General Knowledge|Confusing Words|Emoji & Fun|Brain Teaser'"

