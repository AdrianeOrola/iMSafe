<button id="imassistLauncher" class="imassist-launcher" type="button" aria-controls="imassistDialog" aria-haspopup="dialog">
  <span class="imassist-mark" aria-hidden="true">iA</span>
  <span><strong>iMAssist</strong><small>Disaster information</small></span>
</button>
<dialog id="imassistDialog" class="imassist-dialog" aria-labelledby="imassistTitle">
  <header class="imassist-header">
    <div><span class="imassist-mark" aria-hidden="true">iA</span><span><strong id="imassistTitle">iMAssist</strong><small>Disaster information assistant</small></span></div>
    <button id="imassistClose" type="button" aria-label="Close iMAssist">&times;</button>
  </header>
  <a class="imassist-emergency" href="tel:911"><strong>Immediate danger?</strong><span>Call 911 now</span></a>
  <div id="imassistMessages" class="imassist-messages" role="log" aria-live="polite" aria-relevant="additions">
    <article class="imassist-message assistant">
      <span class="imassist-message-label">General safety guidance</span>
      <p>Hello. I can help with disaster safety, preparedness, current alerts, incident reporting, and report tracking. What do you need?</p>
    </article>
  </div>
  <div id="imassistQuickPrompts" class="imassist-quick" aria-label="Suggested questions">
    <button type="button" data-prompt="What are the current disaster alerts?">Current alerts</button>
    <button type="button" data-prompt="What should I do during a flood?">Flood safety</button>
    <button type="button" data-prompt="How do I report an incident?">Report incident</button>
    <button type="button" data-prompt="How can I track my report?">Track report</button>
  </div>
  <p id="imassistActivity" class="imassist-activity" role="status" aria-live="polite"></p>
  <form id="imassistForm" class="imassist-form">
    <label for="imassistInput">Ask about a disaster or emergency</label>
    <div><textarea id="imassistInput" rows="2" maxlength="800" placeholder="Example: Is there a flood report in Bacoor?" required></textarea><button id="imassistSend" type="submit">Send</button></div>
    <small>Do not share passwords or private contact information. iMAssist does not replace emergency responders.</small>
  </form>
</dialog>
<script>window.imSafeAssistConfig=<?= json_encode(['endpoint' => 'imassist-api.php', 'csrf' => csrf_token()], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?>;</script>
