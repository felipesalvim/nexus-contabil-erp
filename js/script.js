// ==========================================
// 1. CONTROLE DO MENU MOBILE (HAMBÚRGUER)
// ==========================================
document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('menuToggle');
    const nav = document.getElementById('siteNav');

    if (toggle && nav) {
        toggle.addEventListener('click', () => {
            nav.classList.toggle('aberto');
            // Animação simples do ícone hambúrguer
            toggle.classList.toggle('ativo');
        });
    }

    // ==========================================
    // 2. LÓGICA DE ANIMAÇÃO NO SCROLL (REVEAL)
    // ==========================================
    const observerOptions = {
        root: null,
        rootMargin: '0px',
        threshold: 0.15 // Dispara quando 15% do elemento está visível
    };

    const observer = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('anime-visible');
                // Opcional: parar de observar após animar uma vez para melhorar performance
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    const animElements = document.querySelectorAll('[data-anime]');
    animElements.forEach(el => observer.observe(el));
});

// ==========================================
// DESTAQUE AUTOMÁTICO DO MENU ATIVO
// ==========================================
document.addEventListener('DOMContentLoaded', () => {
    // Pega o nome do arquivo atual na URL (ex: "solucoes.html")
    let currentLocation = window.location.pathname.split('/').pop();

    // Se a URL estiver vazia (ex: localhost/site/), assume que é o index.html
    if (currentLocation === '') currentLocation = 'index.html';

    // Procura todos os links do menu e adiciona a classe 'ativo' no correspondente
    document.querySelectorAll('.nav-link').forEach(link => {
        if (link.getAttribute('href') === currentLocation) {
            link.classList.add('ativo');
        }
    });
});