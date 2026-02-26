(function () {
    if (document.querySelector('[data-scroll-top]')) {
        return;
    }

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.setAttribute('aria-label', 'Scroll to top');
    btn.setAttribute('title', 'Scroll to top');
    btn.setAttribute('data-scroll-top', 'true');
    btn.innerHTML = '&#8593;';

    var style = document.createElement('style');
    style.textContent = [
        '[data-scroll-top]{position:fixed;right:18px;bottom:18px;width:46px;height:46px;border:none;border-radius:999px;cursor:pointer;z-index:1300;color:#fff;font-size:20px;font-weight:800;line-height:1;background:linear-gradient(135deg,#1e3a8a,#2563eb);box-shadow:0 12px 28px rgba(15,23,42,.24);opacity:0;transform:translateY(8px) scale(.94);pointer-events:none;transition:opacity .22s ease,transform .22s ease,background .22s ease;}',
        '[data-scroll-top].is-visible{opacity:1;transform:translateY(0) scale(1);pointer-events:auto;}',
        '[data-scroll-top]:hover{background:linear-gradient(135deg,#1d4ed8,#3b82f6);}',
        '[data-scroll-top]:focus-visible{outline:2px solid #93c5fd;outline-offset:2px;}'
    ].join('');

    document.head.appendChild(style);
    document.body.appendChild(btn);

    var toggle = function () {
        if (window.scrollY > 220) {
            btn.classList.add('is-visible');
        } else {
            btn.classList.remove('is-visible');
        }
    };

    btn.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    window.addEventListener('scroll', toggle, { passive: true });
    toggle();
})();
