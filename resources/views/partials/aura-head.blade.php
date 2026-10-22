<style>
    /* Background styles: Eclipse Flare, Crimson Veil and Witching Hour.
       The dark base colour sits on <html>; each layer blends against it. */
    html[data-aura="eclipse"],
    html[data-aura="crimson"],
    html[data-aura="witching"] { background-color: #100e0b; }

    html[data-aura="eclipse"] body,
    html[data-aura="crimson"] body,
    html[data-aura="witching"] body { background: transparent !important; }

    html[data-aura="eclipse"] [data-flux-main],
    html[data-aura="crimson"] [data-flux-main],
    html[data-aura="witching"] [data-flux-main] { background: transparent !important; }

    .aura-layer, .aura-scrim {
        display: none;
        position: fixed;
        inset: 0;
        z-index: -1;
        pointer-events: none;
        transform: translateZ(0);
        will-change: transform;
    }

    /* A light dark film over the colours keeps text easy to read. Change 0.3 to 0 to remove it. */
    html[data-aura="eclipse"] .aura-scrim,
    html[data-aura="crimson"] .aura-scrim,
    html[data-aura="witching"] .aura-scrim { display: block; background: rgba(16, 14, 11, var(--aura-scrim, 0.3)); }

    /* ---- Eclipse Flare ---- */
    html[data-aura="eclipse"] .aura-eclipse-1,
    html[data-aura="eclipse"] .aura-eclipse-2 { display: block; }
    .aura-eclipse-1 {
        background: radial-gradient(ellipse 89% 99% at 50% -38%, rgba(0,0,0,0) 0%, rgb(30,32,35) 38%, rgb(45,70,115) 70%, rgb(142,123,227) 90%, rgb(248,104,196) 100%);
        mix-blend-mode: hard-light; filter: blur(125px); opacity: 0.5;
    }
    .aura-eclipse-2 {
        background: radial-gradient(ellipse 95% 105% at 50% -34%, rgba(0,0,0,0.15) 0%, rgb(30,32,35) 42%, rgb(55,82,135) 74%, rgb(150,126,228) 92%, rgb(246,108,198) 100%);
        mix-blend-mode: soft-light; filter: blur(200px);
    }

    /* ---- Crimson Veil ---- */
    html[data-aura="crimson"] .aura-crimson-1,
    html[data-aura="crimson"] .aura-crimson-2 { display: block; }
    .aura-crimson-1 {
        background: linear-gradient(rgba(0,0,0,0) 0%, rgba(220,38,38,0.9) 40%, rgb(255,255,255) 70%, rgb(251,146,60) 82%, rgb(250,204,21) 100%);
        mix-blend-mode: hard-light; filter: blur(63px); opacity: 0.6;
    }
    .aura-crimson-2 {
        background: linear-gradient(rgba(0,0,0,0) 0%, rgba(220,38,38,0.9) 40%, rgb(255,255,255) 70%, rgb(251,146,60) 82%, rgb(250,204,21) 100%);
        mix-blend-mode: soft-light; filter: blur(88px); opacity: 0.9;
    }

    /* ---- Witching Hour ---- */
    html[data-aura="witching"] .aura-witching-1,
    html[data-aura="witching"] .aura-witching-2,
    html[data-aura="witching"] .aura-witching-3,
    html[data-aura="witching"] .aura-witching-4 { display: block; }
    .aura-witching-1 {
        background: radial-gradient(circle at 30% 25%, rgba(139,92,246,0.6) 0%, transparent 45%);
        mix-blend-mode: screen; filter: blur(188px);
    }
    .aura-witching-2 {
        background: radial-gradient(circle at 70% 35%, rgba(16,185,129,0.5) 0%, transparent 40%);
        mix-blend-mode: screen; filter: blur(200px);
    }
    .aura-witching-3 {
        background: radial-gradient(circle at 45% 75%, rgba(236,72,153,0.4) 0%, transparent 50%);
        mix-blend-mode: screen; filter: blur(175px);
    }
    .aura-witching-4 {
        background: radial-gradient(circle at 15% 80%, rgba(250,204,21,0.2) 0%, transparent 30%);
        mix-blend-mode: overlay; filter: blur(138px);
    }

    /* Larger blur on desktop screens */
    @media (min-width: 1024px) {
        .aura-eclipse-1 { filter: blur(180px); }
        .aura-eclipse-2 { filter: blur(260px); }
        .aura-crimson-1 { filter: blur(90px); }
        .aura-crimson-2 { filter: blur(126px); }
        .aura-witching-1 { filter: blur(260px); }
        .aura-witching-2 { filter: blur(260px); }
        .aura-witching-3 { filter: blur(252px); }
        .aura-witching-4 { filter: blur(198px); }
    }
</style>

<script>
    (function () {
        var valid = ['none', 'eclipse', 'crimson', 'witching'];

        function saved() {
            try { return localStorage.getItem('aura-theme') || 'none'; } catch (e) { return 'none'; }
        }

        function apply(name) {
            if (valid.indexOf(name) === -1) { name = 'none'; }
            document.documentElement.setAttribute('data-aura', name);
        }

        window.setAura = function (name) {
            try { localStorage.setItem('aura-theme', name); } catch (e) {}
            apply(name);
            window.dispatchEvent(new CustomEvent('aura-changed', { detail: name }));
        };

        apply(saved());
        document.addEventListener('livewire:navigated', function () { apply(saved()); });
    })();
</script>
