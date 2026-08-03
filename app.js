/**
 * ============================================================
 * Luna AI Voice Assistant — app.js
 * ------------------------------------------------------------
 * - Captures the microphone via the Web Speech API
 * - Transcribes speech in English
 * - Sends the recognized text to config.php (Cohere backend)
 * - Sends Luna's reply to speak.php for text-to-speech audio
 * - Plays the returned audio and drives the orb's visual states
 * ============================================================
 */

(() => {
  'use strict';

  // ---------------- DOM references ----------------
  const orb            = document.getElementById('orb');
  const statusEl        = document.getElementById('status');
  const statusSubEl     = document.getElementById('statusSub');
  const conversationEl  = document.getElementById('conversation');
  const conversationEmpty = document.getElementById('conversationEmpty');
  const micNote         = document.getElementById('micNote');
  const audioEl         = document.getElementById('lunaAudio');

  // ---------------- State ----------------
  const APP_STATE = { IDLE: 'idle', LISTENING: 'listening', PROCESSING: 'processing', SPEAKING: 'speaking' };
  let currentState = APP_STATE.IDLE;

  const RECOGNITION_LANG = 'en-US';
  const TTS_LANG = 'en';

  const SpeechRecognitionAPI = window.SpeechRecognition || window.webkitSpeechRecognition;
  const speechSupported = !!SpeechRecognitionAPI;

  // ---------------- Init ----------------
  function init() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !speechSupported) {
      setStatus('Voice input isn’t supported in this browser', 'Please try Luna in Chrome or Edge');
      micNote.textContent = 'Speech recognition is unavailable here';
      return;
    }
    micNote.textContent = 'Tap to allow microphone access';
    orb.addEventListener('click', handleOrbTap);
    orb.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); handleOrbTap(); }
    });
  }

  function handleOrbTap() {
    if (currentState === APP_STATE.LISTENING) return; // already listening
    if (currentState === APP_STATE.PROCESSING || currentState === APP_STATE.SPEAKING) return; // busy
    startListening();
  }

  // ---------------- UI state machine ----------------
  function setAppState(state) {
    currentState = state;
    orb.classList.remove('is-listening', 'is-processing', 'is-speaking');
    orb.setAttribute('aria-pressed', 'false');

    switch (state) {
      case APP_STATE.LISTENING:
        orb.classList.add('is-listening');
        orb.setAttribute('aria-pressed', 'true');
        setStatus('Listening…', 'Speak naturally, I’m listening');
        break;
      case APP_STATE.PROCESSING:
        orb.classList.add('is-processing');
        setStatus('Processing…', 'Luna is thinking');
        break;
      case APP_STATE.SPEAKING:
        orb.classList.add('is-speaking');
        setStatus('Speaking…', 'Luna is replying');
        break;
      default:
        setStatus('Ready when you are', 'Tap the orb and start talking');
    }
  }

  function setStatus(main, sub) {
    statusEl.textContent = main;
    statusSubEl.textContent = sub || '';
  }

  // ---------------- Speech recognition ----------------
  function runRecognition(lang) {
    return new Promise((resolve, reject) => {
      const recognizer = new SpeechRecognitionAPI();
      recognizer.lang = lang;
      recognizer.interimResults = false;
      recognizer.maxAlternatives = 1;
      recognizer.continuous = false;

      let settled = false;

      recognizer.onresult = (event) => {
        const result = event.results[0][0];
        settled = true;
        resolve({ text: result.transcript.trim(), confidence: result.confidence || 0 });
      };
      recognizer.onerror = (event) => {
        if (settled) return;
        settled = true;
        reject(event.error || 'recognition-error');
      };
      recognizer.onend = () => {
        if (!settled) reject('no-speech');
      };

      try {
        recognizer.start();
      } catch (err) {
        reject(err);
      }
    });
  }

  async function startListening() {
    setAppState(APP_STATE.LISTENING);
    micNote.textContent = 'Listening…';

    try {
      const { text } = await runRecognition(RECOGNITION_LANG);

      if (!text) {
        throw 'no-speech';
      }

      micNote.textContent = 'Tap to allow microphone access';
      await handleUserUtterance(text);

    } catch (err) {
      setAppState(APP_STATE.IDLE);
      if (err === 'not-allowed' || err === 'permission-denied') {
        setStatus('Microphone access denied', 'Please allow microphone access and try again');
        micNote.textContent = 'Microphone permission is blocked';
      } else if (err === 'no-speech') {
        setStatus('I didn’t catch that', 'Tap the orb and try again');
      } else {
        setStatus('Something went wrong', 'Please try again');
      }
    }
  }

  // ---------------- Conversation UI ----------------
  function appendMessage(text, sender) {
    if (conversationEmpty) conversationEmpty.style.display = 'none';
    const bubble = document.createElement('div');
    bubble.className = 'bubble ' + (sender === 'user' ? 'bubble--user' : 'bubble--luna');
    bubble.textContent = text;
    conversationEl.appendChild(bubble);
    conversationEl.scrollTop = conversationEl.scrollHeight;
  }

  // ---------------- Backend calls ----------------
  async function fetchChatReply(text) {
    const res = await fetch('config.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ text, lang: TTS_LANG }),
    });
    const data = await res.json();
    if (!res.ok || data.error) {
      throw new Error(data.error || 'Chat request failed');
    }
    return data.reply;
  }

  async function fetchSpeechAudio(text) {
    const res = await fetch('speak.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ text, lang: TTS_LANG }),
    });
    const data = await res.json();
    if (!res.ok || data.error) {
      // Signal caller to use the browser voice fallback instead.
      return { mode: 'browser' };
    }
    return data;
  }

  // ---------------- Text-to-speech playback ----------------
  function speakWithBrowserVoice(text) {
    return new Promise((resolve) => {
      if (!('speechSynthesis' in window)) { resolve(); return; }
      const utterance = new SpeechSynthesisUtterance(text);
      utterance.lang = RECOGNITION_LANG;
      utterance.onend = resolve;
      utterance.onerror = resolve;
      window.speechSynthesis.speak(utterance);
    });
  }

  function playAudioUrl(url) {
    return new Promise((resolve) => {
      audioEl.src = url;
      audioEl.onended = resolve;
      audioEl.onerror = resolve;
      audioEl.play().catch(resolve);
    });
  }

  // ---------------- Main turn handler ----------------
  async function handleUserUtterance(text) {
    appendMessage(text, 'user');
    setAppState(APP_STATE.PROCESSING);

    let reply;
    try {
      reply = await fetchChatReply(text);
    } catch (err) {
      setAppState(APP_STATE.IDLE);
      setStatus('Luna couldn’t respond', 'Check the Cohere API key in config.php');
      appendMessage('Sorry, something went wrong reaching the assistant. Please check the API key.', 'luna');
      return;
    }

    appendMessage(reply, 'luna');

    setAppState(APP_STATE.SPEAKING);
    try {
      const speech = await fetchSpeechAudio(reply);
      if (speech.mode === 'audio' && speech.url) {
        await playAudioUrl(speech.url);
      } else {
        await speakWithBrowserVoice(reply);
      }
    } catch (err) {
      await speakWithBrowserVoice(reply);
    }

    setAppState(APP_STATE.IDLE);
  }

  init();
})();
