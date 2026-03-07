# Yardımcı Göz — Accessible Document Assistant

An accessibility-focused web application that helps elderly and visually impaired users understand complex documents through AI-powered analysis, text-to-speech, and voice interaction.

---

## Purpose

Many elderly individuals struggle to understand official documents — utility bills, medical prescriptions, legal notices — due to complex language or small print. Yardımcı Göz (meaning "Helpful Eye" in Turkish) lets users photograph a document and receive a plain-language summary, read aloud by the application.

---

## Features

- Camera input for document capture
- Client-side OCR via Tesseract.js (no image leaves the browser)
- AI summarization in plain Turkish via Groq API (Llama 3.1 8B)
- Automatic flagging of important dates, deadlines, and risks
- Text-to-speech and voice input using the browser-native Web Speech API
- Multi-turn conversational Q&A grounded in the document context
- Three-level font size control and image lightbox for accessibility
- WCAG AA compliant color contrast

---

## Tech Stack

| Layer | Technology |
|---|---|
| Frontend | HTML5, CSS3, Vanilla JavaScript |
| OCR | Tesseract.js (client-side) |
| AI Model | Groq API — llama-3.1-8b-instant |
| API Proxy | PHP (cURL) |
| TTS / STT | Web Speech API (browser-native) |
| Hosting | Any PHP-capable web host (cPanel compatible) |

---

## Project Structure

```
yardimci-goz/
├── index.html           # Full frontend — UI, OCR, TTS, STT, chat
├── proxy.php            # Server-side proxy to Groq API
├── config.php           # API key storage (not in version control)
├── config.example.php   # Template for config.php
├── .htaccess            # Blocks direct access to config.php
├── .gitignore
└── README.md
```

---

## Getting Started

**Prerequisites:** A PHP-capable web server with cURL enabled, and a [Groq API key](https://console.groq.com/keys).

```bash
git clone https://github.com/kaanilker/yardimci-goz.git
cd yardimci-goz
cp config.example.php config.php
# Open config.php and add your Groq API key
```

Upload all files to your server and open the domain in Chrome. Grant camera and microphone permissions when prompted.

**Local development:**
```bash
php -S localhost:8000
```

> Voice input (STT) requires HTTPS in production. It will not work over plain HTTP.

---

## Security Notes

- `config.php` is in `.gitignore` — never commit your API key.
- Images are processed entirely in the browser by Tesseract.js and are never uploaded to the server.
- Only the extracted text is forwarded to the Groq API through the server-side proxy.

---

## License

This project is licensed under the **GNU General Public License v2.0**.
See the [GNU GPL v2](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html) for details.
