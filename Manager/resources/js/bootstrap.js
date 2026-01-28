import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
window.Pusher = Pusher;

const echo = new Echo({
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: import.meta.env.VITE_REVERB_HOST ?? '127.0.0.1',
  wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
  wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
  forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
  enabledTransports: ['ws','wss'],
});

// IMPORTANT
echo.channel('tradeConfigChannel').listen('.tradeConfigEvent', (e) => {
    console.log(e);
    
    const messages = document.getElementById('messages');
    if (messages) messages.insertAdjacentHTML('beforeend', `<p>${e.message}</p>`);
});

// Send message (vanilla JS)
const sendBtn = document.getElementById('sendMessage');
sendBtn.addEventListener('click', async () => {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  try {
    const res = await fetch('https://app.signalvision.ai/send-message', {
      method: 'GET', // keep as GET to match your snippet
      headers: {
        // many backends accept either of these:
        'X-CSRF-Token': csrf,
        'CSRF-Token': csrf,
        'Accept': 'application/json'
      },
      credentials: 'include' // include cookies if your server needs them
    });

    if (!res.ok) throw new Error(`Request failed: ${res.status} ${res.statusText}`);
    // const data = await res.json(); // handle response if needed
  } catch (err) {
    console.error(err);
  }
});
