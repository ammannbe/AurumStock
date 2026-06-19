(function(){
    function getFrontBackFromElem(t){
        var front = t.dataset.front || null;
        var back = t.dataset.back || null;
        if (!front && t.getAttribute('alt')){
            var parts = t.getAttribute('alt').split('||');
            front = parts[0] || t.src;
            back = parts[1] || null;
        }
        if (!front) front = t.src;
        return {front: front, back: back};
    }

    function createOverlay(images, startIndex){
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

        var img = document.createElement('img');
        img.style.maxWidth = '90vw'; img.style.maxHeight = '90vh'; img.style.objectFit = 'contain';

        var index = startIndex || 0;
        function show(i){
            index = (i + images.length) % images.length;
            img.src = images[index];
            counter.textContent = (index + 1) + ' / ' + images.length;
        }

        var prev = document.createElement('button');
        prev.className = 'product-slider-btn prev';
        prev.setAttribute('aria-label', 'Previous image');
        prev.style.marginRight = '0.5rem'; prev.style.padding = '0.5rem 0.75rem';
        prev.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M15 19l-7-7 7-7"/></svg>';
        prev.onclick = function(e){ e.stopPropagation(); show(index - 1); };

        var next = document.createElement('button');
        next.className = 'product-slider-btn next';
        next.setAttribute('aria-label', 'Next image');
        next.style.marginLeft = '0.5rem'; next.style.padding = '0.5rem 0.75rem';
        next.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M9 5l7 7-7 7"/></svg>';
        next.onclick = function(e){ e.stopPropagation(); show(index + 1); };

        var counter = document.createElement('div');
        counter.style.position = 'absolute'; counter.style.bottom = '0.75rem'; counter.style.left = '50%'; counter.style.transform = 'translateX(-50%)'; counter.style.color = 'white'; counter.style.background = 'rgba(0,0,0,0.4)'; counter.style.padding = '0.25rem 0.5rem'; counter.style.borderRadius = '0.25rem';

        container.appendChild(prev);
        container.appendChild(img);
        container.appendChild(next);

        var btnClose = document.createElement('button');
        btnClose.setAttribute('aria-label', 'Close preview');
        btnClose.classList.add('close');
        btnClose.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><path d="M6 18L18 6M6 6l12 12"/></svg>';
        btnClose.onclick = function(){ overlay.remove(); };

        overlay.appendChild(container);
        overlay.appendChild(btnClose);
        overlay.appendChild(counter);
        document.body.appendChild(overlay);

        show(index);

        function onKey(e){
            if (e.key === 'ArrowLeft') { show(index - 1); }
            if (e.key === 'ArrowRight') { show(index + 1); }
            if (e.key === 'Escape') { overlay.remove(); document.removeEventListener('keydown', onKey); }
        }
        document.addEventListener('keydown', onKey);
        overlay.addEventListener('click', function(){ overlay.remove(); document.removeEventListener('keydown', onKey); });
        container.addEventListener('click', function(e){ e.stopPropagation(); });
    }

    document.addEventListener('click', function(e){
        var t = e.target.closest('.product-preview');
        if (!t) return;
        e.preventDefault();
        e.stopPropagation();
        var fb = getFrontBackFromElem(t);
        var images = [fb.front];
        if (fb.back) images.push(fb.back);
        createOverlay(images, 0);
    });

    document.addEventListener('mouseover', function(e){
        var t = e.target.closest('.product-preview');
        if (!t) return;
        var fb = getFrontBackFromElem(t);
        if (fb.back){ t.dataset.orig = t.src; t.src = fb.back; }
    });

    document.addEventListener('mouseout', function(e){
        var t = e.target.closest('.product-preview');
        if (!t) return;
        if (t.dataset.orig){ t.src = t.dataset.orig; delete t.dataset.orig; }
    });
})();
