
/* =========================================
   LINTALA EDIR - RESPONSIVE JAVASCRIPT
   Mobile menu and responsive tables
========================================= */

(function () {

    var fixedHeaderResizeObserver;

    function initializeHeaderProfilePhoto() {

        var welcome = document.getElementById('welcome-user');

        if (!welcome || welcome.querySelector('#welcome-user-photo')) {
            return;
        }

        var photo = document.createElement('img');
        photo.className = 'header-profile-photo';
        photo.alt = '';
        photo.style.display = 'none';

        var name = document.createElement('span');
        name.className = 'header-profile-name';

        function currentName() {
            return welcome.textContent
                .replace(/^\s*👤\s*/, '')
                .replace(/^Welcome,\s*/i, '')
                .trim();
        }

        function renderName(value) {
            name.textContent = value;

            if (!welcome.contains(photo)) {
                welcome.textContent = '';
                welcome.appendChild(photo);
            }

            if (!welcome.contains(name)) {
                welcome.appendChild(name);
            }
        }

        var observer = new MutationObserver(function () {
            if (!welcome.contains(photo) || !welcome.contains(name)) {
                renderName(currentName());
            }
        });

        observer.observe(welcome, {
            childList: true,
            characterData: true,
            subtree: true
        });

        renderName(currentName());

        fetch('../update_profile.php')
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (data.status !== 'success') {
                    console.error('Could not load the header profile photo:', data.message || data.status);
                    return;
                }

                if (data.full_name) {
                    name.textContent = data.full_name;
                }

                if (data.profile_photo) {
                    photo.src = '../' + data.profile_photo;
                    photo.style.display = 'inline-block';
                }
            })
            .catch(function (error) {
                console.error('Could not load the header profile photo:', error);
            });
    }

    function initializeHeaderPhotoPreview() {

        var photos = document.querySelectorAll('#welcome-user-photo');

        if (!photos.length) {
            return;
        }

        var overlay = document.createElement('div');
        overlay.className = 'header-photo-lightbox';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-hidden', 'true');
        overlay.setAttribute('aria-label', 'Profile photo');

        var enlargedPhoto = document.createElement('img');
        overlay.appendChild(enlargedPhoto);
        document.body.appendChild(overlay);

        function closeOverlay() {
            overlay.classList.remove('open');
            overlay.setAttribute('aria-hidden', 'true');
        }

        photos.forEach(function (photo) {
            photo.addEventListener('click', function () {
                enlargedPhoto.src = photo.src;
                enlargedPhoto.alt = photo.alt || 'Profile photo';
                overlay.classList.add('open');
                overlay.setAttribute('aria-hidden', 'false');
            });
        });

        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) {
                closeOverlay();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && overlay.classList.contains('open')) {
                closeOverlay();
            }
        });
    }

    function initializeFixedHeaderScroll() {

        var header = document.querySelector('header');

        if (!header || !document.body) {
            return;
        }

        function updateHeaderHeight() {
            document.body.style.setProperty(
                '--fixed-header-height',
                header.getBoundingClientRect().height + 'px'
            );
        }

        updateHeaderHeight();
        document.body.classList.add('has-fixed-page-header');
        window.requestAnimationFrame(updateHeaderHeight);

        if (window.ResizeObserver) {
            fixedHeaderResizeObserver = new window.ResizeObserver(updateHeaderHeight);
            fixedHeaderResizeObserver.observe(header);
        }

        window.addEventListener('resize', function () {
            window.requestAnimationFrame(updateHeaderHeight);
        });
    }

    function initializeResponsiveLayout() {

        initializeFixedHeaderScroll();
        initializeHeaderProfilePhoto();
        initializeHeaderPhotoPreview();

        var header = document.querySelector('header');
        var sidebar = document.querySelector('.sidebar');

        /* CREATE MOBILE MENU */

        if (
            header &&
            sidebar &&
            !document.querySelector('.mobile-menu-toggle')
        ) {

            var toggle = document.createElement('button');

            toggle.type = 'button';
            toggle.className = 'mobile-menu-toggle';

            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-controls', 'mobile-navigation');
            toggle.setAttribute('aria-label', 'Open navigation menu');

            toggle.innerHTML = '<span aria-hidden="true">☰</span>';

            sidebar.id = sidebar.id || 'mobile-navigation';

            var heading = header.querySelector('h1, h2, h3');

            header.insertBefore(toggle, heading || header.firstChild);

            /* OPEN AND CLOSE MENU */

            toggle.addEventListener('click', function () {

                var isOpen =
                    sidebar.classList.toggle('mobile-menu-open');

                toggle.setAttribute(
                    'aria-expanded',
                    String(isOpen)
                );
                toggle.setAttribute(
                    'aria-label',
                    isOpen ? 'Close navigation menu' : 'Open navigation menu'
                );

                toggle.innerHTML = isOpen
                    ? '<span aria-hidden="true">✕</span>'
                    : '<span aria-hidden="true">☰</span>';

            });

            /* CLOSE MENU AFTER CLICKING A LINK */

            sidebar.addEventListener('click', function (event) {

                if (
                    event.target.closest('a') &&
                    window.matchMedia('(max-width: 700px)').matches
                ) {

                    sidebar.classList.remove('mobile-menu-open');

                    toggle.setAttribute('aria-expanded', 'false');
                    toggle.setAttribute('aria-label', 'Open navigation menu');

                    toggle.innerHTML = '<span aria-hidden="true">☰</span>';
                }

            });
        }

        /* MAKE TABLES RESPONSIVE */

        function wrapTables(root) {

            var tables = [];

            if (root && root.matches && root.matches('table')) {
                tables.push(root);
            }

            if (root && root.querySelectorAll) {
                tables = tables.concat(
                    Array.from(root.querySelectorAll('table'))
                );
            }

            tables.forEach(function (table) {

                if (
                    table.parentElement &&
                    table.parentElement.classList.contains(
                        'responsive-table-wrapper'
                    )
                ) {
                    return;
                }

                var wrapper = document.createElement('div');

                wrapper.className = 'responsive-table-wrapper';

                table.parentNode.insertBefore(wrapper, table);

                wrapper.appendChild(table);

            });
        }

        wrapTables(document);

        /* SUPPORT DYNAMICALLY CREATED TABLES */

        if (window.MutationObserver && document.body) {

            var observer = new MutationObserver(function (records) {

                records.forEach(function (record) {

                    record.addedNodes.forEach(function (node) {

                        if (node.nodeType === 1) {
                            wrapTables(node);
                        }

                    });

                });

            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    }

    if (document.readyState === 'loading') {

        document.addEventListener(
            'DOMContentLoaded',
            initializeResponsiveLayout
        );

    } else {

        initializeResponsiveLayout();

    }

})();
