// Ativa imediatamente esta versão para substituir qualquer Service Worker antigo.
self.addEventListener("install",()=>self.skipWaiting()),
// Remove o registro antigo do Service Worker.
// Isso evita novas requisições concorrentes a /sw.js passando pelo PHP,
// que podem sobrescrever o cookie da sessão logo após o login.
self.addEventListener("activate",e=>{e.waitUntil(self.registration.unregister())});