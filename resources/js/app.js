import './bootstrap';
import Alpine from 'alpinejs';

window.printCustomerDocument = async (source, mimeType, button) => {
    const printArea = document.querySelector('[data-customer-document-print-area]');
    if (!printArea || !source) return;

    const originalTitle = button?.title;
    if (button) {
        button.disabled = true;
        button.title = 'Menyiapkan dokumen...';
        button.classList.add('animate-pulse');
    }

    try {
        if (!String(mimeType).startsWith('image/')) {
            window.open(source, '_blank', 'noopener,noreferrer');
            return;
        }

        printArea.replaceChildren();
        printArea.classList.add('customer-document-is-preparing');
        const image = new Image();
        image.alt = 'Dokumen customer';
        image.src = source;
        await image.decode();
        const page = document.createElement('div');
        page.className = 'customer-document-print-page';
        page.appendChild(image);
        printArea.appendChild(page);
        printArea.classList.remove('customer-document-is-preparing');
        await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
        window.print();
    } catch (error) {
        console.error(error);
        window.alert('Dokumen belum dapat disiapkan untuk dicetak. Silakan muat ulang halaman lalu coba kembali.');
    } finally {
        if (button) {
            button.disabled = false;
            button.title = originalTitle || 'Cetak dokumen';
            button.classList.remove('animate-pulse');
        }
    }
};

const formatMoney = value => {
    let source = String(value ?? '').trim();
    if (/^-?\d+\.\d{2}$/.test(source)) source = source.slice(0, -3);
    const digits = source.replace(/\D/g, '').replace(/^0+(?=\d)/, '');
    return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
};

const initScrollableSelects = () => {
    const controls = new Map();
    let activeControl = null;

    const closeControl = control => {
        if (!control) return;
        control.open = false;
        control.menu.hidden = true;
        control.button.setAttribute('aria-expanded', 'false');
        if (activeControl === control) activeControl = null;
    };

    const positionMenu = control => {
        if (!control?.open || !control.button.isConnected || !control.menu.isConnected) return;
        const rect = control.button.getBoundingClientRect();
        const gap = 5;
        const preferredHeight = Math.min(264, Math.max(120, control.menu.scrollHeight));
        const roomBelow = window.innerHeight - rect.bottom - gap - 8;
        const roomAbove = rect.top - gap - 8;
        const openAbove = roomBelow < Math.min(preferredHeight, 180) && roomAbove > roomBelow;
        const available = Math.max(96, openAbove ? roomAbove : roomBelow);

        control.menu.style.left = `${Math.max(8, Math.min(rect.left, window.innerWidth - Math.max(rect.width, 180) - 8))}px`;
        control.menu.style.width = `${Math.min(Math.max(rect.width, 180), window.innerWidth - 16)}px`;
        control.menu.style.maxHeight = `${Math.min(preferredHeight, available)}px`;
        control.menu.style.top = openAbove ? 'auto' : `${rect.bottom + gap}px`;
        control.menu.style.bottom = openAbove ? `${window.innerHeight - rect.top + gap}px` : 'auto';
    };

    const renderOptions = control => {
        const { select, menu } = control;
        menu.replaceChildren();
        const options = Array.from(select.options);

        if (!options.length) {
            const empty = document.createElement('div');
            empty.className = 'px-3 py-3 text-center text-xs text-slate-400';
            empty.textContent = 'Tidak ada pilihan';
            menu.appendChild(empty);
            return;
        }

        options.forEach(option => {
            const item = document.createElement('button');
            item.type = 'button';
            item.dataset.value = option.value;
            item.disabled = option.disabled;
            item.className = 'flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left text-sm transition hover:bg-indigo-50 hover:text-indigo-700 disabled:cursor-not-allowed disabled:opacity-40';

            const label = document.createElement('span');
            label.className = 'min-w-0 flex-1 whitespace-normal break-words';
            label.textContent = option.textContent.trim();
            const check = document.createElement('span');
            check.className = 'shrink-0 text-sm font-black text-indigo-600';
            check.textContent = option.selected ? '✓' : '';
            if (option.selected) item.classList.add('bg-indigo-50', 'font-semibold', 'text-indigo-700');
            item.append(label, check);

            item.addEventListener('click', () => {
                if (option.disabled) return;
                select.value = option.value;
                select.dispatchEvent(new Event('input', { bubbles: true }));
                select.dispatchEvent(new Event('change', { bubbles: true }));
                closeControl(control);
                window.setTimeout(() => syncControl(control), 0);
            });
            menu.appendChild(item);
        });
    };

    const optionSignature = select => Array.from(select.options)
        .map(option => [option.value, option.textContent.trim(), option.disabled ? 1 : 0, option.selected ? 1 : 0].join('\u0001'))
        .join('\u0002');

    const syncControl = control => {
        const { select, root, button, label } = control;
        if (!select.isConnected) return;
        const selected = select.options[select.selectedIndex];
        const text = selected?.textContent?.trim() || select.getAttribute('placeholder') || 'Pilih';
        if (label.textContent !== text) label.textContent = text;
        button.disabled = select.disabled;
        button.classList.toggle('cursor-not-allowed', select.disabled);
        button.classList.toggle('opacity-60', select.disabled);
        root.style.display = select.style.display === 'none' ? 'none' : '';
        const signature = optionSignature(select);
        if (control.optionSignature !== signature) {
            control.optionSignature = signature;
            renderOptions(control);
        }
        positionMenu(control);
    };

    const enhance = select => {
        if (!(select instanceof HTMLSelectElement) || select.multiple || select.size > 1 || select.dataset.nativeSelect !== undefined || controls.has(select)) return;

        const root = document.createElement('div');
        root.dataset.scrollSelect = '';
        root.className = 'relative min-w-0';
        const parentDisplay = window.getComputedStyle(select.parentElement).display;
        root.classList.add(parentDisplay.includes('flex') ? 'shrink-0' : 'w-full');

        Array.from(select.classList).forEach(className => {
            if (/^(?:[a-z]+:)*(?:w-|min-w-|max-w-|flex-|grow|shrink)/.test(className)) root.classList.add(className);
        });

        select.parentNode.insertBefore(root, select);
        root.appendChild(select);

        const button = document.createElement('button');
        button.type = 'button';
        button.className = `${select.className} flex w-full items-center justify-between gap-3 text-left`;
        button.setAttribute('aria-haspopup', 'listbox');
        button.setAttribute('aria-expanded', 'false');
        button.setAttribute('aria-label', select.getAttribute('aria-label') || select.name || 'Pilih opsi');

        const label = document.createElement('span');
        label.className = 'min-w-0 flex-1 truncate';
        const chevron = document.createElement('span');
        chevron.className = 'shrink-0 text-slate-500';
        chevron.innerHTML = '<svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 8 4 4 4-4"/></svg>';
        button.append(label, chevron);
        root.appendChild(button);

        Object.assign(select.style, {
            position: 'absolute',
            width: '1px',
            height: '1px',
            opacity: '0',
            pointerEvents: 'none',
            inset: '0 auto auto 0',
        });
        select.tabIndex = -1;

        const menu = document.createElement('div');
        menu.hidden = true;
        menu.setAttribute('role', 'listbox');
        menu.className = 'scrollbar-thin fixed z-[300] overflow-x-hidden overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-2xl ring-1 ring-slate-900/5';
        document.body.appendChild(menu);

        const control = { select, root, button, label, menu, open: false };
        controls.set(select, control);

        button.addEventListener('click', () => {
            if (select.disabled) return;
            if (control.open) return closeControl(control);
            if (activeControl) closeControl(activeControl);
            control.open = true;
            activeControl = control;
            renderOptions(control);
            control.optionSignature = optionSignature(select);
            menu.hidden = false;
            button.setAttribute('aria-expanded', 'true');
            positionMenu(control);
        });
        button.addEventListener('keydown', event => {
            if (event.key === 'Escape') closeControl(control);
            if (['ArrowDown', 'Enter', ' '].includes(event.key) && !control.open) {
                event.preventDefault();
                button.click();
            }
        });
        select.addEventListener('change', () => syncControl(control));
        select.addEventListener('input', () => syncControl(control));
        select.addEventListener('invalid', () => {
            button.focus();
            button.classList.add('ring-2', 'ring-rose-300');
            window.setTimeout(() => button.classList.remove('ring-2', 'ring-rose-300'), 1600);
        });
        select.form?.addEventListener('reset', () => window.setTimeout(() => syncControl(control), 0));

        const selectObserver = new MutationObserver(() => syncControl(control));
        selectObserver.observe(select, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ['disabled', 'style'] });
        control.observer = selectObserver;
        syncControl(control);
    };

    const enhanceWithin = node => {
        if (!(node instanceof Element || node instanceof Document)) return;
        if (node instanceof HTMLSelectElement) enhance(node);
        node.querySelectorAll?.('select').forEach(enhance);
    };

    enhanceWithin(document);
    const documentObserver = new MutationObserver(mutations => {
        mutations.forEach(mutation => mutation.addedNodes.forEach(enhanceWithin));
        controls.forEach((control, select) => {
            if (select.isConnected) return;
            closeControl(control);
            control.observer.disconnect();
            control.menu.remove();
            controls.delete(select);
        });
    });
    documentObserver.observe(document.body, { childList: true, subtree: true });

    document.addEventListener('pointerdown', event => {
        if (activeControl && !activeControl.button.contains(event.target) && !activeControl.menu.contains(event.target)) closeControl(activeControl);
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') closeControl(activeControl);
    });
    window.addEventListener('resize', () => positionMenu(activeControl));
    window.addEventListener('scroll', () => positionMenu(activeControl), true);
    window.setInterval(() => {
        if (document.visibilityState !== 'visible') return;
        controls.forEach(syncControl);
    }, 400);
};

document.addEventListener('input', event => {
    if (event.target.matches('[data-money], [data-quantity]')) event.target.value = formatMoney(event.target.value);
});

document.addEventListener('DOMContentLoaded', () => {
    const moneyFields = ['credit_limit', 'estimated_monthly_purchase', 'estimated_value', 'target_price', 'offered_price', 'previous_value', 'requested_value'];
    moneyFields.forEach(name => document.querySelectorAll(`[name="${name}"]`).forEach(input => {
        input.type = 'text';
        input.inputMode = 'numeric';
        input.dataset.money = '';
        input.placeholder ||= '0';
    }));
    document.querySelectorAll('[data-money]').forEach(input => input.value = formatMoney(input.value));
    document.querySelectorAll('[data-quantity]').forEach(input => input.value = formatMoney(input.value));
    document.querySelectorAll('form').forEach(form => form.addEventListener('submit', () => {
        form.querySelectorAll('[data-money], [data-quantity]').forEach(input => input.value = input.value.replace(/\D/g, ''));
    }));

    initScrollableSelects();

    initNotificationPolling();
    initRoomChat();
    initHeicPreviews();
    initEvidenceLightbox();
    initKanbanScroller();
    initKanbanDragAndDrop();
    initPresenceHeartbeat();
    initCustomerDuplicateCheck();
});

const initCustomerDuplicateCheck = () => {
    document.querySelectorAll('[data-duplicate-check]').forEach(form => {
        const endpoint = form.dataset.duplicateUrl;
        const warning = form.querySelector('[data-duplicate-warning]');
        const confirmed = form.querySelector('[data-duplicate-confirmed]');
        const fields = ['company_name', 'phone', 'email', 'npwp'];
        let matches = [];
        let timer;
        const render = () => {
            warning?.classList.toggle('hidden', matches.length === 0);
            warning?.replaceChildren();
            if (!warning || !matches.length) return;
            const title = document.createElement('div'); title.className = 'text-xs font-extrabold text-amber-900'; title.textContent = 'Kemungkinan data sudah terdaftar';
            const note = document.createElement('p'); note.className = 'mt-1 text-[11px] leading-relaxed text-amber-700'; note.textContent = 'Periksa data berikut sebelum menyimpan agar customer tidak tercatat dua kali.';
            warning.append(title, note);
            matches.forEach(match => {
                const item = document.createElement('div'); item.className = 'mt-3 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-white/80 px-3 py-2';
                const identity = document.createElement('span'); identity.className = 'text-xs font-bold text-slate-700'; identity.textContent = `${match.name} · ${match.type} ${match.code || ''}`;
                const reason = document.createElement('span'); reason.className = 'text-[10px] font-semibold text-amber-700'; reason.textContent = match.reasons.join(', ');
                item.append(identity, reason); warning.append(item);
            });
        };
        const check = async () => {
            const params = new URLSearchParams();
            fields.forEach(name => { const value = form.elements[name]?.value?.trim(); if (value) params.set(name, value); });
            if (form.dataset.exceptCustomer) params.set('except_customer', form.dataset.exceptCustomer);
            if (form.dataset.exceptLead) params.set('except_lead', form.dataset.exceptLead);
            if (![...params.keys()].some(key => fields.includes(key))) { matches = []; render(); return; }
            try { const response = await fetch(`${endpoint}?${params}`, { headers: { Accept: 'application/json' } }); const result = await response.json(); matches = response.ok ? (result.matches || []) : []; render(); } catch (_) {}
        };
        fields.forEach(name => form.elements[name]?.addEventListener('input', () => { confirmed.value = '0'; clearTimeout(timer); timer = setTimeout(check, 550); }));
        form.addEventListener('submit', event => {
            if (!matches.length || confirmed.value === '1') return;
            if (!window.confirm('Data serupa ditemukan. Yakin data ini berbeda dan tetap ingin menyimpan?')) { event.preventDefault(); warning?.scrollIntoView({ behavior: 'smooth', block: 'center' }); return; }
            confirmed.value = '1';
        });
        check();
    });
};

const initPresenceHeartbeat = () => {
    const endpoint = document.body.dataset.presenceHeartbeatUrl;
    if (!endpoint) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const send = () => {
        if (document.visibilityState !== 'visible') return;
        fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: true,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ path: location.pathname + location.search, page: document.title }),
        }).catch(() => {});
    };

    send();
    window.setInterval(send, 45000);
    document.addEventListener('visibilitychange', send);
};

const initNotificationPolling = () => {
    const endpoint = document.body.dataset.notificationPollUrl;
    const userId = document.body.dataset.notificationUser;
    if (!endpoint || !userId) return;

    const storageKey = `crm-notification-latest-${userId}`;
    let latestId = sessionStorage.getItem(storageKey);
    const soundEnabled = true;
    let audioContext;

    const unlockAudio = async () => {
        if (!soundEnabled) return null;
        audioContext ||= new (window.AudioContext || window.webkitAudioContext)();
        if (audioContext.state === 'suspended') await audioContext.resume().catch(() => {});
        return audioContext;
    };

    const playChime = async () => {
        if (!soundEnabled) return;
        try {
            await unlockAudio();
            if (!audioContext || audioContext.state !== 'running') return;
            const now = audioContext.currentTime;
            [[0, 740], [0.12, 988], [0.24, 1318]].forEach(([delay, frequency], index) => {
                const oscillator = audioContext.createOscillator();
                const gain = audioContext.createGain();
                oscillator.type = index === 2 ? 'sine' : 'triangle';
                oscillator.frequency.value = frequency;
                gain.gain.setValueAtTime(0.0001, now + delay);
                gain.gain.exponentialRampToValueAtTime(index === 2 ? 0.34 : 0.42, now + delay + 0.012);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + delay + 0.2);
                oscillator.connect(gain).connect(audioContext.destination);
                oscillator.start(now + delay);
                oscillator.stop(now + delay + 0.22);
            });
        } catch (_) {}
    };

    const showToast = notification => {
        const toast = document.createElement(notification.url ? 'a' : 'div');
        if (notification.url) toast.href = notification.url;
        toast.className = 'fixed right-4 top-20 z-[80] w-[min(360px,calc(100vw-2rem))] rounded-2xl border border-indigo-100 bg-white p-4 shadow-2xl shadow-slate-900/15 transition';
        toast.innerHTML = `<div class="flex gap-3"><span class="grid size-9 shrink-0 place-items-center rounded-xl bg-indigo-50 text-indigo-600">🔔</span><span class="min-w-0"><strong data-toast-title class="block text-sm text-slate-900"></strong><span data-toast-message class="mt-1 block text-xs leading-relaxed text-slate-500"></span></span></div>`;
        toast.querySelector('[data-toast-title]').textContent = notification.title;
        toast.querySelector('[data-toast-message]').textContent = notification.message || '';
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 7000);
    };

    const updateIndicators = count => {
        document.querySelectorAll('[data-notification-count]').forEach(badge => {
            badge.textContent = count;
            badge.classList.toggle('hidden', count < 1);
            badge.classList.toggle('grid', count > 0);
        });
        document.querySelectorAll('[data-notification-dot]').forEach(dot => dot.classList.toggle('hidden', count < 1));
        document.querySelectorAll('[data-header-notification-count]').forEach(label => {
            label.textContent = `${count} baru`;
            label.classList.toggle('hidden', count < 1);
        });
    };

    const updateHeaderNotifications = notifications => {
        const list = document.querySelector('[data-header-notification-list]');
        if (!list || !Array.isArray(notifications)) return;
        list.replaceChildren();

        if (!notifications.length) {
            const empty = document.createElement('div');
            empty.className = 'px-6 py-10 text-center';
            const heading = document.createElement('div');
            heading.className = 'text-sm font-bold text-slate-600';
            heading.textContent = 'Belum ada notifikasi';
            const hint = document.createElement('p');
            hint.className = 'mt-1 text-[11px] text-slate-400';
            hint.textContent = 'Update terbaru akan tampil di sini.';
            empty.append(heading, hint);
            list.appendChild(empty);
            return;
        }

        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        notifications.forEach(notification => {
            const wrapper = document.createElement(notification.read_url ? 'form' : 'a');
            if (notification.read_url) {
                wrapper.method = 'POST';
                wrapper.action = notification.read_url;
                const token = document.createElement('input');
                token.type = 'hidden';
                token.name = '_token';
                token.value = csrf;
                wrapper.appendChild(token);
            } else {
                wrapper.href = notification.url || '/notifications';
            }

            const item = document.createElement(notification.read_url ? 'button' : 'span');
            item.className = 'flex w-full gap-3 px-4 py-3 text-left transition hover:bg-slate-50';
            const dot = document.createElement('span');
            const followUp = String(notification.id || '').startsWith('follow-up-');
            dot.className = `mt-1.5 size-2.5 shrink-0 rounded-full ${notification.read ? 'bg-slate-200' : (followUp ? 'bg-amber-400 ring-4 ring-amber-50' : 'bg-brand-500 ring-4 ring-brand-50')}`;
            const content = document.createElement('span');
            content.className = 'min-w-0 flex-1';
            const heading = document.createElement('span');
            heading.className = 'block truncate text-xs font-extrabold text-ink';
            heading.textContent = notification.title || 'Notifikasi';
            const message = document.createElement('span');
            message.className = 'mt-1 line-clamp-2 block text-[11px] leading-relaxed text-slate-500';
            message.textContent = notification.message || '';
            const time = document.createElement('span');
            time.className = 'mt-1.5 block text-[9px] font-semibold text-slate-400';
            time.textContent = notification.created_at || '';
            content.append(heading, message, time);
            item.append(dot, content);
            wrapper.appendChild(item);
            list.appendChild(wrapper);
        });
    };

    const poll = async () => {
        try {
            const response = await fetch(endpoint, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) return;
            const data = await response.json();
            updateIndicators(Number(data.unread_count || 0));
            updateHeaderNotifications(data.notifications);

            if (!data.latest) return;
            if (latestId && data.latest.id !== latestId) {
                playChime();
                showToast(data.latest);
            }
            latestId = data.latest.id;
            sessionStorage.setItem(storageKey, latestId);
        } catch (_) {}
    };

    document.addEventListener('pointerdown', unlockAudio, { once: true });
    document.addEventListener('keydown', unlockAudio, { once: true });
    poll();
    setInterval(poll, 3000);
};

const initRoomChat = () => {
    const chats = document.querySelectorAll('[data-room-chat]');
    if (!chats.length) return;

    chats.forEach(chat => {
    const list = chat.querySelector('[data-room-message-list]');
    const form = chat.querySelector('[data-room-message-form]');
    const textarea = form?.querySelector('textarea[name="body"]');
    const button = form?.querySelector('button');
    const status = form?.querySelector('[data-room-send-status]');
    const messagesUrl = chat.dataset.roomMessagesUrl;
    const currentUser = Number(chat.dataset.currentUser);
    let lastId = Number(chat.dataset.lastMessageId || 0);
    let polling = false;

    const scrollToLatest = behavior => list?.scrollTo({ top: list.scrollHeight, behavior });

    const appendMessage = message => {
        if (list?.querySelector(`[data-message-id="${message.id}"]`)) return;
        list?.querySelector('[data-room-empty]')?.remove();

        const article = document.createElement('article');
        article.dataset.messageId = message.id;
        article.className = `flex gap-3 p-5 ${Number(message.user_id) === currentUser ? 'bg-indigo-50/30' : ''}`;

        const avatar = document.createElement('div');
        avatar.className = 'grid size-9 shrink-0 place-items-center rounded-xl bg-brand-100 text-xs font-extrabold text-brand-700';
        avatar.textContent = message.initial;

        const content = document.createElement('div');
        content.className = 'min-w-0 flex-1';
        const header = document.createElement('div');
        header.className = 'flex items-center justify-between gap-3';
        const name = document.createElement('div');
        name.className = 'text-sm font-bold text-ink';
        name.textContent = message.user_name;
        const time = document.createElement('time');
        time.className = 'text-[10px] text-slate-400';
        time.textContent = message.created_at;
        const body = document.createElement('p');
        body.className = 'mt-2 whitespace-pre-line break-words text-sm leading-relaxed text-slate-600';
        body.textContent = message.body;

        header.append(name, time);
        content.append(header, body);
        article.append(avatar, content);
        list?.appendChild(article);
        lastId = Math.max(lastId, Number(message.id));
    };

    const pollMessages = async () => {
        if (polling || document.hidden) return;
        polling = true;
        try {
            const url = new URL(messagesUrl, window.location.origin);
            url.searchParams.set('after_id', lastId);
            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) return;
            const data = await response.json();
            if (data.messages?.length) {
                data.messages.forEach(appendMessage);
                scrollToLatest('smooth');
            }
        } catch (_) {
        } finally {
            polling = false;
        }
    };

    form?.addEventListener('submit', async event => {
        event.preventDefault();
        if (!textarea?.value.trim()) return;
        button.disabled = true;
        status.textContent = 'Mengirim...';
        status.classList.remove('hidden', 'text-rose-500');

        try {
            const response = await fetch(form.action, {
                method: 'POST', body: new FormData(form), credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) throw new Error('Pesan gagal dikirim. Coba kembali.');
            const data = await response.json();
            appendMessage(data.message);
            textarea.value = '';
            status.classList.add('hidden');
            scrollToLatest('smooth');
        } catch (error) {
            status.textContent = error.message;
            status.classList.add('text-rose-500');
        } finally {
            button.disabled = false;
            textarea.focus();
        }
    });

    textarea?.addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.requestSubmit();
        }
    });

    scrollToLatest('auto');
    setInterval(pollMessages, 2000);
    });
};

let heicModulePromise;
const getHeicConverter = () => {
    heicModulePromise ||= import('heic2any');
    return heicModulePromise;
};

const initHeicPreviews = () => {
    const previews = [...document.querySelectorAll('[data-heic-preview]')];
    if (!previews.length) return;
    const converted = new Map();

    const getPreviewUrl = source => {
        if (!converted.has(source)) {
            converted.set(source, (async () => {
                const [{ default: heic2any }, response] = await Promise.all([
                    getHeicConverter(),
                    fetch(source, { credentials: 'same-origin' }),
                ]);

                if (!response.ok) throw new Error('HEIC tidak dapat dibaca.');
                const output = await heic2any({ blob: await response.blob(), toType: 'image/jpeg', quality: 0.86 });
                return URL.createObjectURL(Array.isArray(output) ? output[0] : output);
            })().catch(error => {
                converted.delete(source);
                throw error;
            }));
        }

        return converted.get(source);
    };

    previews.forEach(preview => {
        const source = preview.dataset.heicPreview;
        if (!source) return;
        const trigger = preview.closest('[data-heic-link]') || preview;
        let loading = false;

        preview.title = 'Klik untuk memuat preview HEIC';
        if (trigger === preview) {
            preview.setAttribute('role', 'button');
            preview.setAttribute('tabindex', '0');
        }

        const loadPreview = async event => {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            if (loading) return;

            loading = true;
            const originalContent = preview.textContent;
            preview.textContent = 'Memuat…';

            try {
                const previewUrl = await getPreviewUrl(source);

                const image = document.createElement('img');
                image.src = previewUrl;
                image.alt = preview.dataset.heicAlt || 'Preview HEIC';
                image.className = preview.dataset.heicClass || 'h-full w-full object-cover';
                preview.replaceWith(image);
                document.querySelectorAll('[data-heic-link]').forEach(link => {
                    if (link.dataset.heicLink === source) link.href = previewUrl;
                });
                trigger.removeEventListener('click', loadPreview);
                trigger.removeEventListener('keydown', handleKeydown);
            } catch (_) {
                loading = false;
                preview.textContent = originalContent;
                preview.title = 'Preview HEIC tidak dapat dibuat di perangkat ini. Klik untuk mencoba lagi.';
            }
        };

        const handleKeydown = event => {
            if (event.key === 'Enter' || event.key === ' ') loadPreview(event);
        };

        trigger.addEventListener('click', loadPreview);
        if (trigger === preview) trigger.addEventListener('keydown', handleKeydown);
    });
};

window.createHeicPreview = async file => {
    const { default: heic2any } = await getHeicConverter();
    const output = await heic2any({ blob: file, toType: 'image/jpeg', quality: 0.86 });
    return URL.createObjectURL(Array.isArray(output) ? output[0] : output);
};

const initEvidenceLightbox = () => {
    let lightbox;
    let image;
    let title;
    let canvas;
    let zoomLabel;
    let loader;
    let loadMessage;
    let previousOverflow = '';
    let openingToken = 0;
    let scale = 1;
    let translateX = 0;
    let translateY = 0;
    let dragging = false;
    let dragStartX = 0;
    let dragStartY = 0;
    const preloaded = new Map();

    const renderTransform = () => {
        image.style.transform = `translate(${translateX}px, ${translateY}px) scale(${scale})`;
        image.style.cursor = scale > 1 ? (dragging ? 'grabbing' : 'grab') : 'zoom-in';
        if (zoomLabel) zoomLabel.textContent = `${Math.round(scale * 100)}%`;
    };

    const setScale = nextScale => {
        scale = Math.min(4, Math.max(0.5, nextScale));
        if (scale <= 1) {
            translateX = 0;
            translateY = 0;
        }
        renderTransform();
    };

    const resetZoom = () => {
        scale = 1;
        translateX = 0;
        translateY = 0;
        renderTransform();
    };

    const preload = trigger => {
        if (!trigger?.href) return Promise.resolve(null);
        if (preloaded.has(trigger.href)) return preloaded.get(trigger.href);
        const promise = new Promise(resolve => {
            const preloadImage = new Image();
            preloadImage.decoding = 'async';
            preloadImage.onload = async () => {
                await preloadImage.decode?.().catch(() => {});
                resolve(trigger.href);
            };
            preloadImage.onerror = () => resolve(null);
            preloadImage.src = trigger.href;
        });
        preloaded.set(trigger.href, promise);
        return promise;
    };

    const preloadActivityEvidence = opener => {
        const scope = opener?.closest?.('[data-activity-evidence-scope]');
        if (!scope) return;

        // Modal images are intentionally lazy so a 20-row activity table stays light.
        // Warm their small thumbnails as soon as the user shows intent to open Detail.
        scope.querySelectorAll('img[data-evidence-src], img[src]').forEach(image => {
            const source = image.dataset.evidenceSrc || image.currentSrc || image.src;
            if (!source || preloaded.has(source)) return;

            const promise = new Promise(resolve => {
                const preloadImage = new Image();
                preloadImage.decoding = 'async';
                preloadImage.onload = async () => {
                    await preloadImage.decode?.().catch(() => {});
                    if (image.dataset.evidenceSrc === source) {
                        image.src = source;
                        image.removeAttribute('data-evidence-src');
                    }
                    resolve(source);
                };
                preloadImage.onerror = () => resolve(null);
                preloadImage.src = source;
            });
            preloaded.set(source, promise);
        });

        // Warm only the first HD evidence for the selected activity. This keeps
        // Detail instant without downloading evidence from every table row.
        const firstEvidence = scope.querySelector('[data-evidence-lightbox]');
        if (firstEvidence) preload(firstEvidence);
    };

    document.addEventListener('pointerenter', event => {
        const trigger = event.target.closest?.('[data-evidence-lightbox]');
        if (trigger) preload(trigger);
    }, true);

    document.addEventListener('focusin', event => {
        const trigger = event.target.closest?.('[data-evidence-lightbox]');
        if (trigger) preload(trigger);
    });

    document.addEventListener('pointerover', event => {
        const opener = event.target.closest?.('[data-preload-activity-evidence]');
        if (opener) preloadActivityEvidence(opener);
    });

    document.addEventListener('pointerdown', event => {
        const opener = event.target.closest?.('[data-preload-activity-evidence]');
        if (opener) preloadActivityEvidence(opener);
    }, true);

    document.addEventListener('focusin', event => {
        const opener = event.target.closest?.('[data-preload-activity-evidence]');
        if (opener) preloadActivityEvidence(opener);
    });

    document.addEventListener('click', event => {
        const opener = event.target.closest?.('[data-preload-activity-evidence]');
        if (!opener) return;
        preloadActivityEvidence(opener);
    }, true);

    const close = () => {
        if (!lightbox || lightbox.classList.contains('hidden')) return;
        lightbox.classList.add('hidden');
        lightbox.classList.remove('flex');
        openingToken++;
        resetZoom();
        image.removeAttribute('src');
        document.body.style.overflow = previousOverflow;
    };

    const ensureLightbox = () => {
        if (lightbox) return;
        lightbox = document.createElement('div');
        lightbox.className = 'fixed inset-0 z-[180] hidden items-center justify-center bg-slate-950/90 p-2 backdrop-blur-sm sm:p-3';
        lightbox.innerHTML = `
            <div class="flex h-full w-full flex-col">
                <header class="flex shrink-0 items-center justify-between gap-4 px-1 pb-2 text-white">
                    <div class="min-w-0">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Preview bukti aktivitas</div>
                        <div data-evidence-lightbox-title class="mt-1 truncate text-sm font-semibold"></div>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <div class="flex h-10 items-center overflow-hidden rounded-xl bg-white/10">
                            <button type="button" data-evidence-zoom-out class="grid h-full w-10 place-items-center text-lg hover:bg-white/10" aria-label="Perkecil">−</button>
                            <button type="button" data-evidence-zoom-reset class="h-full min-w-16 border-x border-white/10 px-2 text-xs font-bold hover:bg-white/10" aria-label="Reset zoom"><span data-evidence-zoom-label>100%</span></button>
                            <button type="button" data-evidence-zoom-in class="grid h-full w-10 place-items-center text-lg hover:bg-white/10" aria-label="Perbesar">+</button>
                        </div>
                        <button type="button" data-evidence-lightbox-close class="grid size-10 place-items-center rounded-full bg-white/10 text-white transition hover:bg-white/20" aria-label="Tutup preview">
                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 3l10 10M13 3L3 13"/></svg>
                        </button>
                    </div>
                </header>
                <div data-evidence-lightbox-backdrop class="relative min-h-0 flex flex-1 items-center justify-center overflow-hidden rounded-xl bg-black/25">
                    <div data-evidence-lightbox-loader class="absolute inset-0 grid place-items-center">
                        <div class="text-center text-white">
                            <span class="mx-auto block size-9 animate-spin rounded-full border-2 border-white/20 border-t-white"></span>
                            <span data-evidence-lightbox-load-message class="mt-3 block text-xs font-semibold text-slate-300">Menyiapkan gambar HD...</span>
                        </div>
                    </div>
                    <img data-evidence-lightbox-image draggable="false" class="h-full w-full select-none object-contain opacity-0 transition-transform duration-100" alt="">
                </div>
                <p class="shrink-0 py-1.5 text-center text-[10px] text-slate-400">Scroll atau double-click untuk zoom · Geser gambar saat diperbesar · Esc untuk menutup</p>
            </div>`;
        document.body.appendChild(lightbox);
        image = lightbox.querySelector('[data-evidence-lightbox-image]');
        canvas = lightbox.querySelector('[data-evidence-lightbox-backdrop]');
        title = lightbox.querySelector('[data-evidence-lightbox-title]');
        zoomLabel = lightbox.querySelector('[data-evidence-zoom-label]');
        loader = lightbox.querySelector('[data-evidence-lightbox-loader]');
        loadMessage = lightbox.querySelector('[data-evidence-lightbox-load-message]');
        lightbox.querySelector('[data-evidence-lightbox-close]').addEventListener('click', close);
        lightbox.querySelector('[data-evidence-zoom-in]').addEventListener('click', () => setScale(scale + 0.25));
        lightbox.querySelector('[data-evidence-zoom-out]').addEventListener('click', () => setScale(scale - 0.25));
        lightbox.querySelector('[data-evidence-zoom-reset]').addEventListener('click', resetZoom);
        canvas.addEventListener('wheel', event => {
            event.preventDefault();
            setScale(scale + (event.deltaY < 0 ? 0.2 : -0.2));
        }, { passive: false });
        image.addEventListener('dblclick', () => setScale(scale >= 2 ? 1 : scale + 0.5));
        image.addEventListener('pointerdown', event => {
            if (scale <= 1) return;
            dragging = true;
            dragStartX = event.clientX - translateX;
            dragStartY = event.clientY - translateY;
            image.setPointerCapture(event.pointerId);
            renderTransform();
        });
        image.addEventListener('pointermove', event => {
            if (!dragging) return;
            translateX = event.clientX - dragStartX;
            translateY = event.clientY - dragStartY;
            renderTransform();
        });
        image.addEventListener('pointerup', event => {
            dragging = false;
            image.releasePointerCapture(event.pointerId);
            renderTransform();
        });
        lightbox.addEventListener('click', event => {
            if (event.target === lightbox || event.target.hasAttribute('data-evidence-lightbox-backdrop')) close();
        });
    };

    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-evidence-lightbox]');
        if (!trigger) return;
        event.preventDefault();
        const fullPreview = preload(trigger);
        ensureLightbox();
        resetZoom();
        const token = ++openingToken;
        image.removeAttribute('src');
        image.classList.add('opacity-0');
        loader.classList.remove('hidden');
        loadMessage.textContent = 'Menyiapkan gambar HD...';
        image.alt = trigger.dataset.evidenceName || 'Bukti aktivitas';
        title.textContent = trigger.dataset.evidenceName || 'Bukti aktivitas';
        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        lightbox.classList.remove('hidden');
        lightbox.classList.add('flex');
        fullPreview.then(source => {
            if (token !== openingToken) return;
            if (!source) {
                loadMessage.textContent = 'Gambar HD tidak dapat dimuat.';
                return;
            }
            image.src = source;
            image.classList.remove('opacity-0');
            loader.classList.add('hidden');
        });
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') close();
    });
};

const initKanbanScroller = () => {
    const shell = document.querySelector('[data-kanban-shell]');
    if (!shell) return;

    const scroller = shell.querySelector('[data-kanban-scroll]');
    const previous = shell.querySelector('[data-kanban-prev]');
    const next = shell.querySelector('[data-kanban-next]');
    const position = shell.querySelector('[data-kanban-position]');
    const hint = shell.querySelector('[data-kanban-hint]');
    const leftShadow = shell.querySelector('[data-kanban-left-shadow]');
    const rightShadow = shell.querySelector('[data-kanban-right-shadow]');
    const columns = [...scroller.children];

    const update = () => {
        const maxScroll = Math.max(0, scroller.scrollWidth - scroller.clientWidth);
        const atStart = scroller.scrollLeft <= 4;
        const atEnd = scroller.scrollLeft >= maxScroll - 4;
        previous.disabled = atStart;
        next.disabled = atEnd;
        leftShadow.classList.toggle('opacity-0', atStart);
        rightShadow.classList.toggle('opacity-0', atEnd);
        hint.textContent = maxScroll <= 4
            ? 'Semua tahap sudah terlihat'
            : atStart
                ? 'Masih ada tahap lain di sebelah kanan'
                : atEnd
                    ? 'Masih ada tahap sebelumnya di sebelah kiri'
                    : 'Masih ada tahap lain di kiri dan kanan';

        if (!columns.length) {
            position.textContent = 'Belum ada tahap';
            return;
        }

        const firstVisible = columns.findIndex(column => column.offsetLeft + column.offsetWidth > scroller.scrollLeft + 16);
        const visibleStart = Math.max(0, firstVisible);
        let visibleEnd = visibleStart;
        columns.forEach((column, index) => {
            if (column.offsetLeft < scroller.scrollLeft + scroller.clientWidth - 16) visibleEnd = index;
        });
        position.textContent = `Menampilkan tahap ${visibleStart + 1}–${visibleEnd + 1} dari ${columns.length}`;
    };

    const move = direction => {
        const columnWidth = columns[0]?.offsetWidth || 292;
        const visibleColumns = Math.max(1, Math.floor(scroller.clientWidth / (columnWidth + 12)) - 1);
        scroller.scrollBy({ left: direction * (columnWidth + 12) * visibleColumns, behavior: 'smooth' });
    };

    previous.addEventListener('click', () => move(-1));
    next.addEventListener('click', () => move(1));
    scroller.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    update();
};

const initKanbanDragAndDrop = () => {
    const shell = document.querySelector('[data-kanban-shell]');
    if (!shell) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const cards = [...shell.querySelectorAll('[data-kanban-card]')];
    const stages = [...shell.querySelectorAll('[data-kanban-stage]')];
    const lostModal = document.querySelector('[data-kanban-lost-modal]');
    const lostForm = lostModal?.querySelector('[data-kanban-lost-form]');
    const lostReason = lostModal?.querySelector('[data-kanban-lost-reason]');
    const lostDetail = lostModal?.querySelector('[data-kanban-lost-detail]');
    let draggedCard = null;
    let dragged = false;

    const requestLostReason = () => new Promise(resolve => {
        if (!lostModal || !lostForm || !lostReason || !lostDetail) {
            resolve(null);
            return;
        }

        lostReason.value = '';
        lostDetail.value = '';
        lostModal.classList.remove('hidden');
        lostModal.classList.add('grid');
        document.body.style.overflow = 'hidden';
        lostReason.focus();

        const finish = value => {
            lostModal.classList.add('hidden');
            lostModal.classList.remove('grid');
            document.body.style.overflow = '';
            lostForm.removeEventListener('submit', submit);
            lostModal.removeEventListener('click', backdrop);
            document.removeEventListener('keydown', escape);
            lostModal.querySelectorAll('[data-kanban-lost-cancel]').forEach(button => button.removeEventListener('click', cancel));
            resolve(value);
        };
        const submit = event => {
            event.preventDefault();
            if (!lostForm.reportValidity()) return;
            finish({ lost_reason: lostReason.value, reason: lostDetail.value.trim() });
        };
        const cancel = () => finish(null);
        const backdrop = event => { if (event.target === lostModal) finish(null); };
        const escape = event => { if (event.key === 'Escape') finish(null); };

        lostForm.addEventListener('submit', submit);
        lostModal.addEventListener('click', backdrop);
        document.addEventListener('keydown', escape);
        lostModal.querySelectorAll('[data-kanban-lost-cancel]').forEach(button => button.addEventListener('click', cancel));
    });

    const clearTargets = () => stages.forEach(stage => {
        stage.classList.remove('ring-2', 'ring-brand-400', 'bg-brand-50');
        stage.querySelector('[data-kanban-drop]')?.classList.remove('bg-brand-50/70');
    });

    const createEmptyState = () => {
        const empty = document.createElement('div');
        empty.className = 'kanban-empty grid h-28 place-items-center rounded-lg border border-dashed border-slate-300 bg-white/50 px-5 text-center text-[10px] text-slate-400';
        empty.textContent = 'Belum ada opportunity pada tahap ini';
        return empty;
    };

    const updateStageSummary = (stage) => {
        if (!stage) return;
        const stageCards = [...stage.querySelectorAll('[data-kanban-card]')];
        const count = stage.querySelector('[data-stage-count]');
        const total = stage.querySelector('[data-stage-total]');
        const dropZone = stage.querySelector('[data-kanban-drop]');

        if (count) count.textContent = String(stageCards.length);
        if (total) {
            const value = stageCards.reduce((sum, item) => sum + (Number(item.dataset.value) || 0), 0);
            total.textContent = 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(value);
        }
        dropZone?.querySelector('.kanban-empty')?.remove();
        if (dropZone && stageCards.length === 0) dropZone.append(createEmptyState());
    };

    cards.forEach(card => {
        card.addEventListener('dragstart', event => {
            draggedCard = card;
            dragged = true;
            card.classList.add('opacity-40', 'scale-[.98]');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', card.dataset.moveUrl || '');
        });
        card.addEventListener('dragend', () => {
            card.classList.remove('opacity-40', 'scale-[.98]');
            clearTargets();
            window.setTimeout(() => { dragged = false; }, 0);
            draggedCard = null;
        });
        card.addEventListener('click', event => {
            if (dragged) event.preventDefault();
        });
    });

    stages.forEach(stage => {
        const dropZone = stage.querySelector('[data-kanban-drop]');
        stage.addEventListener('dragover', event => {
            if (!draggedCard) return;
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            if (dropZone) {
                const bounds = dropZone.getBoundingClientRect();
                const edge = 64;
                if (event.clientY < bounds.top + edge) dropZone.scrollTop -= 14;
                if (event.clientY > bounds.bottom - edge) dropZone.scrollTop += 14;
            }
            clearTargets();
            stage.classList.add('ring-2', 'ring-brand-400', 'bg-brand-50');
            dropZone?.classList.add('bg-brand-50/70');
        });
        stage.addEventListener('drop', async event => {
            event.preventDefault();
            if (!draggedCard) return;
            const stageId = stage.dataset.stageId;
            const currentStageId = draggedCard.dataset.stageId;
            clearTargets();
            if (!stageId || stageId === currentStageId) return;

            const card = draggedCard;
            const sourceStage = stages.find(item => item.dataset.stageId === currentStageId);
            const lostData = stage.dataset.stageIsLost === '1' ? await requestLostReason() : null;
            if (stage.dataset.stageIsLost === '1' && !lostData) {
                card.classList.remove('opacity-40', 'scale-[.98]');
                return;
            }
            card.classList.remove('opacity-40');
            card.classList.add('pointer-events-none', 'animate-pulse');
            try {
                const response = await fetch(card.dataset.moveUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({
                        stage_id: Number(stageId),
                        reason: lostData?.reason || 'Dipindahkan melalui drag & drop Kanban',
                        lost_reason: lostData?.lost_reason || null,
                    }),
                });
                const result = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const message = result?.errors?.stage_id?.[0] || result?.message || 'Stage belum dapat dipindahkan.';
                    throw new Error(message);
                }
                dropZone?.querySelector('.kanban-empty')?.remove();
                dropZone?.prepend(card);
                card.dataset.stageId = stageId;
                const daysInStage = card.querySelector('[data-days-in-stage]');
                if (daysInStage) {
                    daysInStage.textContent = '0 hari di tahap ini';
                    daysInStage.classList.remove('font-bold', 'text-rose-500');
                    daysInStage.classList.add('text-slate-400');
                }
                card.classList.remove('pointer-events-none', 'animate-pulse', 'scale-[.98]', 'border-rose-200');
                card.classList.add('border-emerald-300', 'ring-2', 'ring-emerald-100');
                updateStageSummary(sourceStage);
                updateStageSummary(stage);
                window.setTimeout(() => {
                    card.classList.remove('border-emerald-300', 'ring-2', 'ring-emerald-100');
                    card.classList.add('border-slate-200');
                }, 900);
            } catch (error) {
                card.classList.remove('pointer-events-none', 'animate-pulse', 'scale-[.98]');
                window.alert(error.message);
            }
        });
    });
};

window.Alpine = Alpine;
Alpine.start();
