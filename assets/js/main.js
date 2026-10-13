(() => {
    'use strict';

    const storageKey = 'haramaya-dl-bookmarks';
    const readBookmarks = () => {
        try {
            const ids = JSON.parse(localStorage.getItem(storageKey) || '[]');
            return [...new Set(ids.map(Number).filter((id) => Number.isInteger(id) && id > 0))].slice(0, 20);
        } catch {
            return [];
        }
    };

    const updateBookmarkButtons = () => {
        const saved = new Set(readBookmarks());
        document.querySelectorAll('[data-bookmark]').forEach((button) => {
            const id = Number(button.dataset.bookmark);
            const isSaved = saved.has(id);
            button.setAttribute('aria-pressed', String(isSaved));
            button.setAttribute('aria-label', isSaved ? 'Remove saved material' : 'Save material');
            button.title = isSaved ? 'Remove saved material' : 'Save material';
            const icon = button.querySelector('i');
            if (icon) {
                icon.classList.toggle('bi-bookmark-fill', isSaved);
                icon.classList.toggle('bi-bookmark', !isSaved);
            }
        });
    };

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-bookmark]');
        if (!button) return;
        const id = Number(button.dataset.bookmark);
        const saved = new Set(readBookmarks());
        if (saved.has(id)) saved.delete(id);
        else saved.add(id);
        try {
            localStorage.setItem(storageKey, JSON.stringify([...saved].slice(0, 20)));
        } catch {
            button.title = 'Browser storage is unavailable';
        }
        updateBookmarkButtons();
        renderSavedMaterials();
    });

    async function renderSavedMaterials() {
        const container = document.querySelector('[data-bookmarks]');
        if (!container) return;
        const ids = readBookmarks();
        container.replaceChildren();
        if (ids.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'text-secondary';
            empty.textContent = 'No materials saved on this device yet.';
            container.append(empty);
            return;
        }
        try {
            const url = new URL(container.dataset.api, window.location.href);
            url.searchParams.set('ids', ids.join(','));
            const response = await fetch(url);
            if (!response.ok) throw new Error('Saved materials could not be loaded.');
            const { items } = await response.json();
            if (!items.length) {
                const empty = document.createElement('p');
                empty.className = 'text-secondary';
                empty.textContent = 'Saved materials may have been removed or are no longer approved.';
                container.append(empty);
                return;
            }
            items.forEach((item) => {
                const column = document.createElement('div');
                column.className = 'col-12 col-md-6 col-lg-4';
                const card = document.createElement('article');
                card.className = 'material-card h-100';
                const link = document.createElement('a');
                link.className = 'material-title';
                const detailUrl = new URL('../material.php', new URL(container.dataset.api, window.location.href));
                detailUrl.searchParams.set('id', item.material_id);
                link.href = detailUrl.href;
                link.textContent = item.title;
                const course = document.createElement('p');
                course.className = 'small text-secondary mt-2 mb-0';
                course.textContent = item.course_code;
                card.append(link, course);
                column.append(card);
                container.append(column);
            });
        } catch {
            const error = document.createElement('p');
            error.className = 'text-danger';
            error.textContent = 'Saved materials could not be loaded.';
            container.append(error);
        }
    }

    updateBookmarkButtons();
    renderSavedMaterials();

    const themeToggle = document.querySelector('[data-theme-toggle]');
    try {
        if (localStorage.getItem('haramaya-dl-theme') === 'dark') document.body.classList.add('dark-mode');
    } catch {
        // Continue without remembering the theme when browser storage is disabled.
    }
    themeToggle?.addEventListener('click', () => {
        document.body.classList.toggle('dark-mode');
        try {
            localStorage.setItem('haramaya-dl-theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
        } catch {
            // The visual toggle still works for the current page.
        }
    });

    document.querySelectorAll('[data-confirm]').forEach((formControl) => {
        const form = formControl.matches('form') ? formControl : formControl.closest('form');
        form?.addEventListener('submit', (event) => {
            if (!window.confirm(formControl.dataset.confirm || 'Continue with this action?')) event.preventDefault();
        });
    });

    const filterForm = document.querySelector('[data-filter-form]');
    if (filterForm) {
        const controls = {
            college: filterForm.querySelector('[data-filter-select="college"]'),
            department: filterForm.querySelector('[data-filter-select="department"]'),
            year: filterForm.querySelector('[data-filter-select="year"]'),
            semester: filterForm.querySelector('[data-filter-select="semester"]'),
            course: filterForm.querySelector('[data-filter-select="course"]'),
        };
        const initialValues = Object.fromEntries(Object.entries(controls).map(([key, select]) => [key, select?.value || '']));
        const resetSelect = (select, placeholder) => {
            if (!select) return;
            select.replaceChildren(new Option(placeholder, ''));
            select.value = '';
            select.disabled = true;
        };
        const loadOptions = async (select, type, values, placeholder, selected = '') => {
            if (!select) return;
            resetSelect(select, placeholder);
            if (Object.values(values).some((value) => !value)) return;
            const url = new URL(filterForm.dataset.apiBase, window.location.href);
            url.searchParams.set('type', type);
            Object.entries(values).forEach(([key, value]) => url.searchParams.set(key, value));
            try {
                const response = await fetch(url);
                if (!response.ok) return;
                const data = await response.json();
                data.items.forEach((item) => select.add(new Option(item.label, item.id)));
                select.disabled = false;
                select.value = selected;
            } catch {
                select.disabled = false;
            }
        };
        const loadDepartments = (selected = '') => loadOptions(controls.department, 'departments', { college_id: controls.college?.value }, 'All departments', selected);
        const loadYears = async (selected = '') => {
            resetSelect(controls.year, 'All years'); resetSelect(controls.semester, 'All semesters'); resetSelect(controls.course, 'All courses');
            await loadOptions(controls.year, 'years', { department_id: controls.department?.value }, 'All years', selected);
        };
        const loadSemesters = async (selected = '') => {
            resetSelect(controls.semester, 'All semesters'); resetSelect(controls.course, 'All courses');
            await loadOptions(controls.semester, 'semesters', { department_id: controls.department?.value, year: controls.year?.value }, 'All semesters', selected);
        };
        const loadCourses = (selected = '') => loadOptions(controls.course, 'courses', { department_id: controls.department?.value, year: controls.year?.value, semester: controls.semester?.value }, 'All courses', selected);
        controls.college?.addEventListener('change', async () => { await loadDepartments(); await loadYears(); });
        controls.department?.addEventListener('change', () => loadYears());
        controls.year?.addEventListener('change', () => loadSemesters());
        controls.semester?.addEventListener('change', () => loadCourses());
        if (initialValues.college && !initialValues.department) loadDepartments();
        if (initialValues.department && !initialValues.year) loadYears();
        if (initialValues.department && initialValues.year && !initialValues.semester) loadSemesters();
        if (initialValues.department && initialValues.year && initialValues.semester && !initialValues.course) loadCourses();
    }

    const searchInput = document.querySelector('#nav-search');
    const suggestionBox = document.querySelector('#search-suggestions');
    if (searchInput && suggestionBox) {
        let timer;
        searchInput.addEventListener('input', () => {
            window.clearTimeout(timer);
            const query = searchInput.value.trim();
            if (query.length < 2) {
                suggestionBox.hidden = true;
                suggestionBox.replaceChildren();
                return;
            }
            timer = window.setTimeout(async () => {
                const url = new URL(suggestionBox.dataset.suggestionsUrl, window.location.href);
                url.searchParams.set('q', query);
                try {
                    const response = await fetch(url);
                    const { items } = await response.json();
                    suggestionBox.replaceChildren();
                    items.forEach((item) => {
                        const link = document.createElement('a');
                        link.className = 'suggestion-item';
                        link.setAttribute('role', 'option');
                        const detailUrl = new URL('../material.php', new URL(suggestionBox.dataset.suggestionsUrl, window.location.href));
                        detailUrl.searchParams.set('id', item.material_id);
                        link.href = detailUrl.href;
                        link.textContent = `${item.title} · ${item.course_code}`;
                        suggestionBox.append(link);
                    });
                    suggestionBox.hidden = items.length === 0;
                } catch {
                    suggestionBox.hidden = true;
                }
            }, 180);
        });
        searchInput.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') suggestionBox.hidden = true;
        });
        document.addEventListener('click', (event) => {
            if (!suggestionBox.contains(event.target) && event.target !== searchInput) suggestionBox.hidden = true;
        });
    }

    const uploadForm = document.querySelector('[data-upload-form]');
    uploadForm?.addEventListener('submit', (event) => {
        if (!uploadForm.reportValidity()) return;
        const progress = uploadForm.querySelector('.upload-progress');
        const bar = progress?.querySelector('.progress-bar');
        const fileInput = uploadForm.querySelector('input[type="file"]');
        const file = fileInput?.files?.[0];
        if (!file || file.size > 50 * 1024 * 1024) return;
        event.preventDefault();
        if (progress) progress.style.display = 'flex';
        const request = new XMLHttpRequest();
        request.open('POST', uploadForm.action);
        request.upload.addEventListener('progress', (progressEvent) => {
            if (!progressEvent.lengthComputable || !bar || !progress) return;
            const percent = Math.round((progressEvent.loaded / progressEvent.total) * 100);
            bar.style.width = `${percent}%`;
            bar.setAttribute('aria-valuenow', String(percent));
        });
        request.addEventListener('load', () => {
            if (request.responseURL && new URL(request.responseURL).pathname !== new URL(uploadForm.action).pathname) {
                window.location.assign(request.responseURL);
            } else {
                document.open(); document.write(request.responseText); document.close();
            }
        });
        request.addEventListener('error', () => window.alert('Upload failed. Check your connection and try again.'));
        request.send(new FormData(uploadForm));
    });

    if (window.Chart && window.libraryCharts) {
        const uploadsCanvas = document.querySelector('#uploads-chart');
        if (uploadsCanvas) new Chart(uploadsCanvas, {
            type: 'line',
            data: { labels: window.libraryCharts.uploads.map((row) => row.month), datasets: [{ label: 'Uploads', data: window.libraryCharts.uploads.map((row) => Number(row.total)), borderColor: '#08749b', backgroundColor: 'rgb(8 116 155 / 12%)', fill: true, tension: 0.25 }] },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
        });
        const downloadsCanvas = document.querySelector('#downloads-chart');
        if (downloadsCanvas) new Chart(downloadsCanvas, {
            type: 'bar',
            data: { labels: window.libraryCharts.downloads.map((row) => row.department), datasets: [{ label: 'Downloads', data: window.libraryCharts.downloads.map((row) => Number(row.total)), backgroundColor: '#d5a12b' }] },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } },
        });
    }
})();