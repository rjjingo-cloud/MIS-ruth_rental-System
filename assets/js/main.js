document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (event) {
            if (!window.confirm(el.getAttribute('data-confirm'))) {
                event.preventDefault();
                event.stopPropagation();
            }
            if (el.type === 'submit') {
                event.stopPropagation();
            }
        });
    });

    setTimeout(function () {
        document.querySelectorAll('.alert').forEach(function (alert) {
            alert.classList.add('alert-fade');
        });
    }, 5000);

    var thread = document.getElementById('chatThread');
    if (thread) {
        thread.scrollTop = thread.scrollHeight;
    }

    var navToggle = document.getElementById('navToggle');
    var navLinks = document.getElementById('navLinks');
    if (navToggle && navLinks) {
        navToggle.addEventListener('click', function () {
            var open = navLinks.classList.toggle('open');
            navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        navLinks.addEventListener('click', function (event) {
            if (event.target.closest('a') && window.matchMedia('(max-width: 768px)').matches) {
                navLinks.classList.remove('open');
                navToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    var slider = document.getElementById('heroSlider');
    if (slider) {
        var slides = slider.querySelectorAll('.slide');
        var dots = slider.querySelectorAll('.slider-dot');
        var prev = slider.querySelector('.slider-arrow.prev');
        var next = slider.querySelector('.slider-arrow.next');
        var current = 0;
        var timer = null;
        var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        function show(index) {
            if (!slides.length) {
                return;
            }
            current = (index + slides.length) % slides.length;
            slides.forEach(function (slide, i) {
                slide.classList.toggle('active', i === current);
            });
            dots.forEach(function (dot, i) {
                dot.classList.toggle('active', i === current);
                dot.setAttribute('aria-current', i === current ? 'true' : 'false');
            });
        }

        function start() {
            stop();
            if (!reduceMotion && slides.length > 1) {
                timer = setInterval(function () {
                    show(current + 1);
                }, 6000);
            }
        }

        function stop() {
            if (timer) {
                clearInterval(timer);
                timer = null;
            }
        }

        if (prev) {
            prev.addEventListener('click', function () {
                show(current - 1);
                start();
            });
        }
        if (next) {
            next.addEventListener('click', function () {
                show(current + 1);
                start();
            });
        }
        dots.forEach(function (dot) {
            dot.addEventListener('click', function () {
                show(parseInt(dot.getAttribute('data-slide'), 10) || 0);
                start();
            });
        });
        slider.addEventListener('mouseenter', stop);
        slider.addEventListener('mouseleave', start);
        start();
    }

    document.querySelectorAll('.password-field').forEach(function (field) {
        var input = field.querySelector('input');
        var toggle = field.querySelector('[data-password-toggle]');
        if (!input || !toggle) {
            return;
        }
        toggle.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            toggle.classList.toggle('is-visible', show);
            toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
            toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            input.focus();
        });
    });

    var pwInput = document.getElementById('password');
    var pwReqs = document.querySelector('[data-password-reqs]');
    if (pwInput && pwReqs) {
        var pwFill = document.querySelector('[data-password-strength-fill]');
        var pwText = document.querySelector('[data-password-strength-text]');
        var rules = {
            length: function (value) { return value.length >= 8; },
            lower: function (value) { return /[a-z]/.test(value); },
            upper: function (value) { return /[A-Z]/.test(value); },
            digit: function (value) { return /\d/.test(value); },
            symbol: function (value) { return /[^A-Za-z0-9]/.test(value); }
        };
        var labels = ['', 'Weak', 'Weak', 'Fair', 'Good', 'Strong'];
        var levels = ['', 'weak', 'weak', 'fair', 'good', 'strong'];
        var updateStrength = function () {
            var value = pwInput.value;
            var met = 0;
            Object.keys(rules).forEach(function (key) {
                var ok = rules[key](value);
                var item = pwReqs.querySelector('[data-req="' + key + '"]');
                if (item) {
                    item.classList.toggle('met', ok);
                }
                if (ok) {
                    met++;
                }
            });
            var score = value.length === 0 ? 0 : met;
            if (pwFill) {
                pwFill.style.width = (score / 5 * 100) + '%';
                pwFill.className = 'password-strength-fill ' + (levels[score] || '');
            }
            if (pwText) {
                pwText.textContent = score === 0
                    ? 'Enter a password to check its strength.'
                    : 'Password strength: ' + labels[score];
            }
        };
        pwInput.addEventListener('input', updateStrength);
        updateStrength();
    }
});

window.addEventListener('pageshow', function (event) {
    if (event.persisted) {
        window.location.reload();
    }
});
