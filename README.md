# 🌙 Luna — AI Voice Assistant

Luna is an English voice-to-voice AI assistant that runs entirely on plain **HTML, CSS, JavaScript, and PHP** — no Node.js, no build step, no frameworks. It's designed to be uploaded straight into a shared-hosting `htdocs` folder (e.g. **InfinityFree**) and just work.

👉🏻 [Explore Live Demo](https://luna-ai-assistant.lovestoblog.com/) You speak → Luna listens → Luna understands (Cohere AI) → Luna talks back.

---

## ✨ Features

- 🎙️ **Voice input** via the browser's microphone — no typing required
- 🧠 **AI responses powered by Cohere**
- 🔊 **Text-to-speech playback** with an English voice
- 💎 **Premium, feminine, iPhone/Siri-inspired UI** — pastel pink & lavender glassmorphism
- 📱 **Fully responsive** — looks great on desktop and mobile
- 🔒 **API key stays server-side** — never exposed to the browser
- 🪶 **Zero installation hosting** — pure HTML/CSS/JS/PHP, works on any shared host

---

## 🔁 System Workflow

```
        🎤 Voice Input
             │
             ▼
   📝 Speech-to-Text (Web Speech API, English)
             │
             ▼
   🤖 Cohere AI Processing (config.php → Cohere Chat API)
             │
             ▼
   🔊 Text-to-Speech (speak.php → English audio)
             │
             ▼
        🎧 Voice Response
```

1. **Speech-to-Text** — `app.js` uses the browser's built-in Web Speech API to capture the microphone and transcribe English speech.
2. **Cohere AI Processing** — the transcript is POSTed to `config.php`, which calls the Cohere Chat API server-side (API key never touches the browser) and returns Luna's reply.
3. **Text-to-Speech** — the reply is POSTed to `speak.php`, which generates English audio and returns a playable file.
4. **Voice Response** — `app.js` plays the audio through the page's `<audio>` element (falling back to the browser's own speech synthesis if the server TTS call fails).

---

## 📁 File Overview

| File | Purpose |
|---|---|
| `index.html` | Page structure: the Luna orb/mic button, status text, conversation transcript, hidden audio player. |
| `style.css` | The full feminine, glassmorphic, iPhone-inspired visual design and animations, responsive down to mobile. |
| `app.js` | Microphone access, English speech recognition, calls to the PHP backend, playback of Luna's voice, and all UI state changes (idle / listening / processing / speaking). |
| `config.php` | Holds the Cohere API key, model name, and Luna's system prompt/personality **(kept hidden server-side)**. Also acts as the chat endpoint that `app.js` calls to get Luna's reply. |
| `speak.php` | Takes Luna's reply text and returns spoken English audio. |
| `README.md` | This file. |

---

## ⚙️ Setup

### 1. Get a Cohere API key
Sign up at [dashboard.cohere.com](https://dashboard.cohere.com/api-keys) and copy your API key.

### 2. Add your key to `config.php`
Open `config.php` and replace the placeholder:

```php
define('COHERE_API_KEY', 'YOUR_COHERE_API_KEY_HERE');
```

with your real key. That's the only edit required to go live.

---

## 🚀 Deploying to InfinityFree

1. **Create a hosting account** at [infinityfree.net](https://infinityfree.net) and create a new hosting slot (you'll get a free subdomain or can connect your own domain).
2. **Open the File Manager** (or connect via FTP with the credentials InfinityFree gives you) and go into the `htdocs` folder.
3. **Upload all five project files** (`index.html`, `style.css`, `app.js`, `config.php`, `speak.php`) directly into `htdocs` — no subfolders needed.
4. **Add your Cohere API key** to `config.php` (see Setup above) either before uploading or by editing it directly in InfinityFree's file editor afterward.
5. **Open your website URL** (e.g. `https://yourname.infinityfreeapp.com`) — grant microphone permission when prompted, and start talking to Luna.

> Note: InfinityFree serves sites over HTTPS on its free subdomains, which is required for microphone access in the browser.

---

## 📸 Screenshots

<img width="1440" height="828" alt="image" src="https://github.com/user-attachments/assets/38a1915c-5bcc-49d8-91e8-02a9f363beea" />

---

## 🎬 Demo Video

https://github.com/user-attachments/assets/b52ea10e-8984-4255-a8a2-648dc78b2ec9

---

## 🧾 Project Summary

| | |
|---|---|
| **Assistant name** | Luna |
| **Interface language** | English |
| **Voice understanding** | English |
| **AI responses** | English |
| **AI provider** | Cohere API |
| **Text-to-speech** | English voice |
| **Hosting** | InfinityFree (shared hosting, no Node.js) |
| **Stack** | HTML, CSS, JavaScript, PHP only |


💙 If you like this project, give it a ⭐ and share it with friends!


