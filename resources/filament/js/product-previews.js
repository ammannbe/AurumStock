(function(){
    function getGalleryFromElem(t){
        if (t.dataset.gallery){
            try {
                var parsed = JSON.parse(t.dataset.gallery);
                if (Array.isArray(parsed) && parsed.length){ return parsed; }
            } catch (e) { /* fall through to the front/back attributes */ }
        }

        var items = [];
        var front = t.dataset.front || t.src;
        if (front) items.push({url: front, type: 'image', label: ''});
        if (t.dataset.back) items.push({url: t.dataset.back, type: 'image', label: ''});
        return items;
    }

    function createOverlay(items, startIndex){
        var existing = document.getElementById('__img-overlay');
        if (existing){ existing.remove(); }

        var overlay = document.createElement('div');
        overlay.id = '__img-overlay';
        overlay.style.position = 'fixed'; overlay.style.left = 0; overlay.style.top = 0; overlay.style.right = 0; overlay.style.bottom = 0; overlay.style.background = 'rgba(0,0,0,0.85)'; overlay.style.display = 'flex'; overlay.style.alignItems = 'center'; overlay.style.justifyContent = 'center'; overlay.style.zIndex = 9999;

        var container = document.createElement('div');
        container.style.position = 'relative';
        container.style.display = 'flex';
        container.style.gap = '1rem';
        container.style.maxWidth = '95%';
        container.style.maxHeight = '95%';
        container.style.padding = '1rem';
        container.style.alignItems = 'center';

        var stage = document.createElement('div');
        stage.className = 'product-slider-stage';

        var counter = document.createElement('div');
        counter.className = 'product-slider-counter';

        var index = startIndex || 0;

        function close(){
            overlay.remove();
            document.removeEventListener('keydown', onKey);
        }

        function show(i){
            index = (i + items.length) % items.length;
            var item = items[index];

            var current = stage.firstChild;
            if (current && current.tagName === 'VIDEO'){ current.pause(); }
            stage.textContent = '';

            var node;
            if (item.type === 'video'){
                node = document.createElement('video');
                node.src = item.url;
                node.controls = true;
                node.preload = 'metadata';
                node.playsInline = true;
            } else {
                node = document.createElement('img');
                node.src = item.url;
                node.alt = item.label || '';
            }
            stage.appendChild(node);

            var position = items.length > 1 ? (index + 1) + ' / ' + items.length : '';
            counter.textContent = item.label ? (item.label + (position ? ' — ' + position : '')) : position;
            counter.style.display = counter.textContent ? '' : 'none';
        }

        var prev = document.createElement('button');
        prev.className = 'product-slider-btn prev';
        prev.setAttribute('aria-label', 'Previous media');
        prev.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M15 19l-7-7 7-7"/></svg>';
        prev.onclick = function(e){ e.stopPropagation(); show(index - 1); };

        var next = document.createElement('button');
        next.className = 'product-slider-btn next';
        next.setAttribute('aria-label', 'Next media');
        next.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M9 5l7 7-7 7"/></svg>';
        next.onclick = function(e){ e.stopPropagation(); show(index + 1); };

        if (items.length > 1){ container.appendChild(prev); }
        container.appendChild(stage);
        if (items.length > 1){ container.appendChild(next); }

        var btnClose = document.createElement('button');
        btnClose.setAttribute('aria-label', 'Close preview');
        btnClose.classList.add('close');
        btnClose.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><path d="M6 18L18 6M6 6l12 12"/></svg>';
        btnClose.onclick = function(e){ e.stopPropagation(); close(); };

        overlay.appendChild(container);
        overlay.appendChild(btnClose);
        overlay.appendChild(counter);
        document.body.appendChild(overlay);

        show(index);

        function onKey(e){
            // Let the video element keep arrow keys for seeking.
            var onVideo = document.activeElement && document.activeElement.tagName === 'VIDEO';
            if (e.key === 'ArrowLeft' && !onVideo) { e.preventDefault(); show(index - 1); }
            if (e.key === 'ArrowRight' && !onVideo) { e.preventDefault(); show(index + 1); }
            if (e.key === 'Escape') { e.preventDefault(); close(); }
        }
        document.addEventListener('keydown', onKey);
        overlay.addEventListener('click', close);
        container.addEventListener('click', function(e){ e.stopPropagation(); });
    }

    document.addEventListener('click', function(e){
        var t = e.target.closest('.product-preview');
        if (!t) return;
        e.preventDefault();
        e.stopPropagation();
        var items = getGalleryFromElem(t);
        if (items.length){ createOverlay(items, 0); }
    });

    document.addEventListener('mouseover', function(e){
        var t = e.target.closest('.product-preview');
        if (!t) return;
        if (t.dataset.back){ t.dataset.orig = t.src; t.src = t.dataset.back; }
    });

    document.addEventListener('mouseout', function(e){
        var t = e.target.closest('.product-preview');
        if (!t) return;
        if (t.dataset.orig){ t.src = t.dataset.orig; delete t.dataset.orig; }
    });
})();
