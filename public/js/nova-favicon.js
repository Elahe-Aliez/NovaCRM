(function () {
    function setFavicon() {
        var head = document.head;
        if (!head) {
            return;
        }

        var href = '/img/nova-favicon.svg';
        var icon = document.querySelector('link[rel="icon"]');

        if (!icon) {
            icon = document.createElement('link');
            icon.setAttribute('rel', 'icon');
            head.appendChild(icon);
        }

        icon.setAttribute('type', 'image/svg+xml');
        icon.setAttribute('href', href);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setFavicon);
    } else {
        setFavicon();
    }
})();
