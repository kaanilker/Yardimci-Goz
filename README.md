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
- Markdown stripped before TTS to prevent symbols like `**` being read aloud
- Per-message "Dinle" (listen) buttons on every AI response
- In-browser TTS audio caching to avoid redundant proxy requests
- Voice input (STT) via Groq Whisper Large V3 Turbo (`whisper-proxy.php`) — high-accuracy Turkish speech recognition
- Conversation history capped at 10 messages to avoid exceeding model token limits
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
| STT | Groq Whisper Large V3 Turbo — `whisper-proxy.php` (via MediaRecorder API) |
| Hosting | Any PHP-capable web host with cURL enabled (cPanel compatible) |

---

## Project Structure

```
yardimci-goz/
├── index.html           # Full frontend — UI, OCR, TTS, STT, chat
├── proxy.php            # Server-side proxy to Groq API (LLM)
├── tts-proxy.php        # Server-side Edge TTS proxy (WebSocket → MP3 → base64)
├── whisper-proxy.php    # Server-side Groq Whisper STT proxy
├── config.php           # API key and origin config (not in version control)
├── config.example.php   # Template for config.php
├── resim.png            # Favicon
├── .htaccess            # Security rules and access control
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

On each AI response, the frontend strips markdown symbols before sending text to `tts-proxy.php`, which opens a WebSocket connection to Microsoft Edge TTS (`speech.platform.bing.com`) and streams back an MP3 audio file encoded as base64. The result is played directly in the browser via the Web Audio API.

If the Edge TTS request fails or times out, the app automatically falls back to the browser's built-in Web Speech API (`speechSynthesis`). Successful audio responses are cached in memory for the duration of the session.

---

## How STT Works

When the user presses the voice button, the browser records audio via the `MediaRecorder` API. On stop, the audio blob is sent to `whisper-proxy.php`, which forwards it to the Groq Whisper Large V3 Turbo endpoint with `language: tr` forced for higher Turkish accuracy. The transcribed text is returned and sent directly to the LLM as a question.

The button cycles through three states during this flow: **recording** (red pulse) → **processing** (muted blue) → **idle**.

---

## Rate Limits (Groq Free Tier)

| Resource | Limit |
|---|---|
| LLM requests (llama-3.1-8b-instant) | 30 RPM / 14,400 RPD |
| Whisper requests | 20 RPM / 2,000 RPD |
| Whisper audio | 7,200 sec/hour / 28,800 sec/day |

For higher limits, upgrade to the Groq Developer plan.

---

## Security Notes

- `config.php` is in `.gitignore` — never commit your API key.
- Images are processed entirely in the browser by Tesseract.js and are never uploaded to the server.
- Only the extracted text is forwarded to the Groq API through the server-side proxy.
- All three proxy files validate the request method (POST only) and check `ALLOWED_ORIGIN` against `config.php`.
- `.htaccess` blocks direct browser access to `config.php`, hides PHP version and error output, sets security headers (`X-Frame-Options`, `X-Content-Type-Options`, `X-XSS-Protection`, `Permissions-Policy`), and restricts Referer on all proxy files.
- `whisper-proxy.php` enforces a 25 MB file size limit and cleans up the temporary upload after use.
- `tts-proxy.php` currently disables SSL peer verification (`verify_peer => false`) for the Edge TTS connection — consider enabling this in hardened environments.

---

## License

This project is licensed under the **GNU General Public License v2.0**.
See the [GNU GPL v2](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html) for details.
