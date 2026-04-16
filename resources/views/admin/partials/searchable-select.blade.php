{{-- Custom Searchable Select Component --}}
{{-- Auto-enhances all <select> elements with class "searchable-select" --}}
{{-- Or use data-searchable on any select to enable --}}
<style>
.ss-wrapper { position: relative; width: 100%; }
.ss-trigger {
    display: flex; align-items: center; justify-content: space-between;
    cursor: pointer; user-select: none; width: 100%;
    background: white; transition: all 0.15s;
}
.ss-trigger:hover { border-color: #a5b4fc; }
.ss-trigger.ss-open { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
.ss-trigger .ss-arrow { transition: transform 0.2s; flex-shrink: 0; }
.ss-trigger.ss-open .ss-arrow { transform: rotate(180deg); }
.ss-trigger .ss-placeholder { color: #9ca3af; }
.ss-dropdown {
    display: none; position: absolute; top: calc(100% + 4px); left: 0; right: 0;
    background: white; border: 1px solid #e5e7eb; border-radius: 0.625rem;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.12), 0 4px 6px -4px rgba(0,0,0,0.07);
    z-index: 999; overflow: hidden; animation: ssSlideDown 0.15s ease; min-width: 180px;
}
.ss-dropdown.ss-up {
    top: auto; bottom: calc(100% + 4px);
    animation: ssSlideUp 0.15s ease;
}
@keyframes ssSlideDown { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
@keyframes ssSlideUp { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
.ss-dropdown.ss-show { display: block; }
.ss-search-box {
    padding: 8px 10px; border-bottom: 1px solid #f3f4f6;
}
.ss-search-box input {
    width: 100%; padding: 6px 10px 6px 30px; font-size: 12px;
    border: 1px solid #e5e7eb; border-radius: 0.5rem; outline: none;
    background: #f9fafb url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%239ca3af'%3e%3cpath fill-rule='evenodd' d='M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z' clip-rule='evenodd'/%3e%3c/svg%3e") no-repeat 8px center / 14px;
    transition: all 0.15s;
}
.ss-search-box input:focus { border-color: #6366f1; background-color: white; box-shadow: 0 0 0 2px rgba(99,102,241,0.1); }
.ss-options { max-height: 220px; overflow-y: auto; padding: 4px; }
.ss-options::-webkit-scrollbar { width: 4px; }
.ss-options::-webkit-scrollbar-track { background: transparent; }
.ss-options::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
.ss-option {
    padding: 7px 10px; font-size: 12.5px; border-radius: 0.375rem;
    cursor: pointer; transition: all 0.1s; display: flex; align-items: center; gap: 6px;
}
.ss-option:hover { background: #eef2ff; color: #4338ca; }
.ss-option.ss-selected { background: #6366f1; color: white; font-weight: 600; }
.ss-option.ss-selected:hover { background: #4f46e5; }
.ss-option.ss-hidden { display: none; }
.ss-empty { padding: 16px; text-align: center; font-size: 12px; color: #9ca3af; }
</style>
<script>
function initSearchableSelect(sel) {
    // Skip selects already enhanced or explicitly excluded
    if (sel.dataset.ssInit || sel.dataset.noSearch) return;

    sel.dataset.ssInit = '1';

        // Measure computed width BEFORE hiding
        const computedStyle = window.getComputedStyle(sel);
        const computedWidth = sel.offsetWidth;
        const selDisplay = computedStyle.display;

        sel.style.display = 'none';

        const wrapper = document.createElement('div');
        wrapper.className = 'ss-wrapper';

        // Copy width-related classes from the select
        const origClasses = sel.className || '';
        const widthClasses = origClasses.match(/(?:w-\S+|max-w-\S+|min-w-\S+|flex-1)/g) || [];
        widthClasses.forEach(function(c) { wrapper.classList.add(c); });

        // If select had inline style width or the parent is flex/grid, set explicit width
        if (sel.style.width) wrapper.style.width = sel.style.width;

        sel.parentNode.insertBefore(wrapper, sel);
        wrapper.appendChild(sel);

        // Detect original select classes for sizing
        const hasSmallText = origClasses.includes('text-[12px]') || origClasses.includes('text-[11px]');
        const fontSize = hasSmallText ? '12px' : '13px';
        const py = hasSmallText ? '6px 10px' : '9px 12px';

        // Build trigger
        const trigger = document.createElement('div');
        trigger.className = 'ss-trigger border border-gray-300 rounded-lg';
        trigger.style.padding = py;
        trigger.style.fontSize = fontSize;

        const label = document.createElement('span');
        label.className = 'ss-label truncate';
        const arrow = document.createElement('span');
        arrow.className = 'ss-arrow text-gray-400 ml-2';
        arrow.innerHTML = '<svg width="12" height="12" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>';
        trigger.appendChild(label);
        trigger.appendChild(arrow);
        wrapper.appendChild(trigger);

        // Build dropdown
        const dropdown = document.createElement('div');
        dropdown.className = 'ss-dropdown';

        const opts = sel.options;
        const showSearch = opts.length > 6;

        let searchBox = null;
        let searchInput = null;
        if (showSearch) {
            searchBox = document.createElement('div');
            searchBox.className = 'ss-search-box';
            searchInput = document.createElement('input');
            searchInput.type = 'text';
            searchInput.placeholder = 'Cari...';
            searchBox.appendChild(searchInput);
            dropdown.appendChild(searchBox);
        }

        const optionsContainer = document.createElement('div');
        optionsContainer.className = 'ss-options';
        const emptyMsg = document.createElement('div');
        emptyMsg.className = 'ss-empty ss-hidden';
        emptyMsg.textContent = 'Tidak ditemukan';

        for (let i = 0; i < opts.length; i++) {
            const o = opts[i];
            const optDiv = document.createElement('div');
            optDiv.className = 'ss-option';
            optDiv.dataset.value = o.value;
            optDiv.dataset.index = i;
            optDiv.textContent = o.textContent;
            if (o.selected) optDiv.classList.add('ss-selected');
            optionsContainer.appendChild(optDiv);
        }
        optionsContainer.appendChild(emptyMsg);
        dropdown.appendChild(optionsContainer);
        wrapper.appendChild(dropdown);

        // Set initial label
        function updateLabel() {
            const selected = sel.options[sel.selectedIndex];
            if (selected && selected.value) {
                label.textContent = selected.textContent;
                label.classList.remove('ss-placeholder');
            } else {
                label.textContent = selected ? selected.textContent : 'Pilih...';
                label.classList.add('ss-placeholder');
            }
        }
        updateLabel();

        // Toggle dropdown
        function openDropdown() {
            // Determine direction
            const rect = trigger.getBoundingClientRect();
            const spaceBelow = window.innerHeight - rect.bottom;
            if (spaceBelow < 260) {
                dropdown.classList.add('ss-up');
            } else {
                dropdown.classList.remove('ss-up');
            }
            dropdown.classList.add('ss-show');
            trigger.classList.add('ss-open');
            if (searchInput) { searchInput.value = ''; filterOptions(''); searchInput.focus(); }
            // Scroll to selected
            const selected = optionsContainer.querySelector('.ss-selected');
            if (selected) selected.scrollIntoView({ block: 'nearest' });
        }
        function closeDropdown() {
            dropdown.classList.remove('ss-show');
            trigger.classList.remove('ss-open');
        }

        trigger.addEventListener('click', function(e) {
            e.stopPropagation();
            if (dropdown.classList.contains('ss-show')) { closeDropdown(); } else { openDropdown(); }
        });

        // Option click
        optionsContainer.addEventListener('click', function(e) {
            const opt = e.target.closest('.ss-option');
            if (!opt) return;
            sel.selectedIndex = parseInt(opt.dataset.index);
            optionsContainer.querySelectorAll('.ss-option').forEach(o => o.classList.remove('ss-selected'));
            opt.classList.add('ss-selected');
            updateLabel();
            closeDropdown();
            sel.dispatchEvent(new Event('change', { bubbles: true }));
        });

        // Search filter
        function filterOptions(query) {
            const q = query.toLowerCase();
            let visible = 0;
            optionsContainer.querySelectorAll('.ss-option').forEach(function(o) {
                const match = o.textContent.toLowerCase().includes(q);
                o.classList.toggle('ss-hidden', !match);
                if (match) visible++;
            });
            emptyMsg.classList.toggle('ss-hidden', visible > 0);
        }

        if (searchInput) {
            searchInput.addEventListener('input', function() { filterOptions(this.value); });
            searchInput.addEventListener('click', function(e) { e.stopPropagation(); });
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') closeDropdown();
            });
        }

        // Close on outside click
        document.addEventListener('click', function(e) {
            if (!wrapper.contains(e.target)) closeDropdown();
        });

        // Handle programmatic value changes
        const origDesc = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'selectedIndex');
        // Watch for changes via MutationObserver
        const observer = new MutationObserver(function() {
            updateLabel();
            optionsContainer.querySelectorAll('.ss-option').forEach(function(o, i) {
                o.classList.toggle('ss-selected', i === sel.selectedIndex);
            });
        });
    observer.observe(sel, { attributes: true, childList: true, subtree: true });
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('select').forEach(function(sel) {
        // Skip selects inside hidden elements
        if (sel.offsetParent === null && !sel.closest('.hidden')) return;
        initSearchableSelect(sel);
    });

    // Re-init for modals/dynamic content that become visible
    document.addEventListener('click', function(e) {
        setTimeout(function() {
            document.querySelectorAll('select:not([data-ss-init])').forEach(function(sel) {
                if (sel.offsetParent !== null) {
                    initSearchableSelect(sel);
                }
            });
        }, 100);
    });
});
</script>
