# Yardımcı Göz — Accessible Document Assistant

An accessibility-focused web application that helps elderly and visually impaired users understand complex documents through AI-powered analysis, text-to-speech, and voice interaction.

---

## Purpose

Many elderly individuals struggle to understand official documents — utility bills, medical prescriptions, legal notices — due to complex language or small print. Yardımcı Göz (meaning "Helpful Eye" in Turkish) lets users photograph a document and receive a plain-language summary, read aloud by the application.

---

## Features

- Camera and gallery input for document capture
- Client-side OCR via Tesseract.js (Turkish + English; no image leaves the browser)
- OCR quality detection with a user warning before proceeding on low-confidence scans
- AI summarization in plain Turkish via Groq API (Llama 3.1 8B), with automatic flagging of important dates, deadlines, and risks (🔴)
- Text-to-speech via Microsoft Edge TTS (`tts-proxy.php`), with Web Speech API as automatic fallback
- Per-message "Dinle" (listen) buttons on every AI response
- In-browser TTS audio caching to avoid redundant proxy requests
- Voice input (STT) via the browser-native Web Speech API
- Multi-turn conversational Q&A grounded in the document context
- Three-level font size control and image lightbox for accessibility
- WCAG AA compliant color contrast

---

## Tech Stack

| Layer | Technology |
|---|---|
| Frontend | HTML5, CSS3, Vanilla JavaScript |
| OCR | Tesseract.js 5.1 (client-side, `tur+eng`) |
| AI Model | Groq API — `llama-3.1-8b-instant` |
| API Proxy | PHP (cURL) — `proxy.php` |
| TTS (primary) | Microsoft Edge TTS via PHP WebSocket — `tts-proxy.php` (voice: `tr-TR-EmelNeural`, MP3 output) |
| TTS (fallback) | Web Speech API (browser-native, `tr-TR`) |
| STT | Web Speech API (browser-native) |
| Hosting | Any PHP-capable web host with cURL enabled (cPanel compatible) |

---

## Project Structure

```
yardimci-goz/
├── index.html           # Full frontend — UI, OCR, TTS, STT, chat
├── proxy.php            # Server-side proxy to Groq API
├── tts-proxy.php        # Server-side Edge TTS proxy (WebSocket → MP3 → base64)
├── config.php           # API key and origin config (not in version control)
├── config.example.php   # Template for config.php
├── resim.png            # Favicon
├── .htaccess            # Blocks direct access to config.php
├── .gitignore
└── README.md
```

---

## Getting Started

**Prerequisites:** A PHP-capable web server with cURL and SSL stream socket support enabled, and a [Groq API key](https://console.groq.com/keys).

```bash
git clone https://github.com/kaanilker/yardimci-goz.git
cd yardimci-goz
cp config.example.php config.php
# Open config.php and fill in your Groq API key and allowed origin
```

Upload all files to your server and open the domain in a modern browser (Chrome, Edge, or Safari). Grant camera and microphone permissions when prompted.

**Local development:**
```bash
php -S localhost:8000
```

> ⚠️ `ALLOWED_ORIGIN` in `config.php` must match your local address (e.g. `http://localhost:8000`) when developing locally, otherwise the proxy will reject requests due to CORS.

> Voice input (STT) requires HTTPS in production. It will not work over plain HTTP.

---

## How TTS Works

On each AI response, the frontend calls `tts-proxy.php`, which opens a WebSocket connection to Microsoft Edge TTS (`speech.platform.bing.com`) and streams back an MP3 audio file encoded as base64. The result is played directly in the browser via the Web Audio API.

If the Edge TTS request fails or times out, the app automatically falls back to the browser's built-in Web Speech API (`speechSynthesis`). Successful audio responses are cached in memory for the duration of the session.

---

## Security Notes

- `config.php` is in `.gitignore` — never commit your API key.
- Images are processed entirely in the browser by Tesseract.js and are never uploaded to the server.
- Only the extracted text is forwarded to the Groq API through the server-side proxy.
- `tts-proxy.php` currently disables SSL peer verification (`verify_peer => false`) for the Edge TTS connection — consider enabling this in hardened environments.

---

## License

This project is licensed under the **GNU General Public License v2.0**.
See the [GNU GPL v2](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html) for details.
