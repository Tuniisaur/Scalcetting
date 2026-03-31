
// --- SHOP & BONUS SYSTEM ---

let activeShopTab = 'bonus';
let shopItems = [];
let userInventory = [];
let shopModalTimeout = null;

// openShopModal()
async function openShopModal() {
    if (!currentUser.id) {
        showToast('Devi essere loggato per accedere al negozio!', 'error');
        toggleAuthModal();
        return;
    }

    const panel = document.getElementById('shopPanel');
    const overlay = document.getElementById('shopOverlay');
    const modal = document.getElementById('shopModal');

    // Scroll lock
    document.body.classList.add('overflow-hidden');

    // Show modal
    modal.classList.remove('hidden');
    // Animate in
    requestAnimationFrame(() => {
        overlay.classList.remove('opacity-0');
        panel.classList.remove('opacity-0', 'translate-y-4', 'sm:translate-y-0', 'sm:scale-95');
    });

    // Fetch Items
    try {
        const res = await fetch(`shop_api.php?action=list&table=${tableId}`);
        const data = await res.json();

        if (data.success) {
            shopItems = data.items;
            document.getElementById('shop-user-credits').textContent = data.user_credits;
            renderShopItems();
        }
    } catch (e) {
        console.error("Shop error:", e);
        showToast('Errore caricamento negozio', 'error');
    }
}

function switchShopTab(tab) {
    activeShopTab = tab;
    
    // UI Update
    document.querySelectorAll('.shop-tab-btn').forEach(btn => btn.classList.remove('active'));
    document.getElementById(`shop-tab-${tab}`).classList.add('active');
    
    renderShopItems();
}

/**
 * Shared template for item cards to ensure perfect dimensional consistency.
 */
function generateItemCardHTML(item, options = {}) {
    const { 
        index = 0, 
        isInventory = false, 
        actionHTML = '' 
    } = options;

    const isAesthetic = item.item_type === 'aesthetic' || 
                        (item.key_name && (
                            item.key_name.startsWith('aura_') || 
                            item.key_name.startsWith('title_') || 
                            item.key_name.startsWith('color_') ||
                            item.key_name.startsWith('style_') ||
                            item.key_name.startsWith('name_')
                        ));
    
    // Standardized visual tokens
    const iconBg = isAesthetic ? 'bg-indigo-100 dark:bg-indigo-900/40' : 'bg-yellow-100 dark:bg-yellow-900/40';
    const iconText = isAesthetic ? 'text-indigo-600 dark:text-indigo-400' : 'text-yellow-600 dark:text-yellow-400';
    const typeClass = isAesthetic ? 'aesthetic' : 'bonus';

    const subText = isInventory 
        ? (isAesthetic ? '<span class="text-indigo-500 font-bold">Estetico</span>' : `<span class="text-yellow-600 font-bold">Quantità: ${item.quantity}</span>`)
        : item.description;

    return `
        <div onclick="openShopDetailModal(${item.id})" class="shop-card ${typeClass} flex items-center gap-4 rounded-2xl p-4 border shadow-sm shop-item-animate h-20 min-h-[80px] max-h-[80px] shrink-0 overflow-hidden relative box-border xl:cursor-pointer" style="animation-delay: ${index * 0.05}s">
            <!-- Icon -->
            <div class="h-12 w-12 shrink-0 rounded-2xl ${iconBg} flex items-center justify-center ${iconText} shadow-sm transition-all group-hover:scale-110">
                <span class="material-symbols-outlined text-2xl">${item.icon}</span>
            </div>

            <!-- Info -->
            <div class="flex-1 min-w-0">
                <h4 class="font-bold text-gray-900 dark:text-gray-100 text-sm leading-none uppercase truncate">${item.name}</h4>
                <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-1.5 leading-tight line-clamp-1">${subText}</p>
            </div>

            <!-- Action Area -->
            <div class="shrink-0 flex items-center justify-end gap-2 min-w-[110px]" onclick="event.stopPropagation()">
                ${actionHTML}
            </div>
        </div>
    `;
}

/**
 * Opens a detailed view of a shop item with a live preview for aesthetics.
 */
function openShopDetailModal(itemId) {
    try {
        console.log("Opening shop detail for item:", itemId);
        
        // Search in all possible item sources
        let item = null;
        if (typeof shopItems !== 'undefined' && Array.isArray(shopItems)) {
            item = shopItems.find(i => i.id == itemId);
        }
        if (!item && typeof userInventory !== 'undefined' && Array.isArray(userInventory)) {
            item = userInventory.find(i => i.id == itemId);
        }

        if (!item) {
            console.error("Item not found in any source", itemId);
            return;
        }

        // Detailed element check
        const ids = ['sd-name', 'sd-desc', 'sd-icon', 'sd-cost', 'sd-icon-container', 'sd-preview-container', 'sd-preview-content', 'sd-buy-btn', 'sd-modal', 'sd-overlay', 'sd-panel'];
        const missing = ids.filter(id => !document.getElementById(id));
        if (missing.length > 0) {
            throw new Error("Elementi mancanti nel DOM: " + missing.join(', '));
        }

        // Basic Info
        document.getElementById('sd-name').textContent = item.name;
        document.getElementById('sd-desc').textContent = item.description;
        document.getElementById('sd-icon').textContent = item.icon;
        document.getElementById('sd-cost').textContent = `${item.cost} Strisciate`;
        
        // Style Icon Container
        const isAesthetic = item.item_type === 'aesthetic' || 
                            (item.key_name && (
                                item.key_name.startsWith('aura_') || 
                                item.key_name.startsWith('title_') || 
                                item.key_name.startsWith('color_') || 
                                item.key_name.startsWith('style_') ||
                                item.key_name.startsWith('name_')
                            ));
        const container = document.getElementById('sd-icon-container');
        container.className = `h-14 w-14 rounded-2xl flex items-center justify-center text-3xl shadow-sm ${isAesthetic ? 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400' : 'bg-yellow-100 dark:bg-yellow-900/40 text-yellow-600 dark:text-yellow-400'}`;

        // Preview
        const previewContainer = document.getElementById('sd-preview-container');
        const previewContent = document.getElementById('sd-preview-content');
        
        if (isAesthetic) {
            previewContainer.classList.remove('hidden');
            renderShopDetailPreview(item, previewContent);
        } else {
            previewContainer.classList.add('hidden');
        }

        // Buy Button Action
        const buyBtn = document.getElementById('sd-buy-btn');
        const isOwned = isAesthetic && parseInt(item.owned_quantity) > 0;
        
        if (isOwned) {
            if (buyBtn) {
                buyBtn.onclick = null;
                document.getElementById('sd-cost').textContent = 'Già in possesso';
                const icon = buyBtn.querySelector('.material-symbols-outlined');
                if (icon) icon.classList.add('hidden');
                buyBtn.classList.add('opacity-50', 'cursor-not-allowed');
                buyBtn.disabled = true;
            }
        } else {
            if (buyBtn) {
                buyBtn.onclick = () => {
                    closeShopDetailModal();
                    openShopPurchaseConfirmation(itemId);
                };
                document.getElementById('sd-cost').textContent = `${item.cost} Strisciate`;
                const icon = buyBtn.querySelector('.material-symbols-outlined');
                if (icon) icon.classList.remove('hidden');
                buyBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                buyBtn.disabled = false;
            }
        }

        // Show Modal
        const modal = document.getElementById('sd-modal');
        const overlay = document.getElementById('sd-overlay');
        const panel = document.getElementById('sd-panel');

        // Clear any pending close timeout to prevent race conditions
        if (shopModalTimeout) {
            clearTimeout(shopModalTimeout);
            shopModalTimeout = null;
        }

        // Reset animations state
        overlay.classList.add('opacity-0');
        panel.classList.add('opacity-0', 'scale-95');
        panel.classList.remove('scale-100');

        document.body.classList.add('overflow-hidden');
        modal.classList.remove('hidden');
        requestAnimationFrame(() => {
            overlay.classList.remove('opacity-0');
            panel.classList.remove('opacity-0', 'scale-95');
            panel.classList.add('scale-100');
        });
    } catch (err) {
        console.error("Critical error in openShopDetailModal:", err);
        alert("Errore apertura anteprima: " + err.message);
    }
}

function closeShopDetailModal() {
    const modal = document.getElementById('sd-modal');
    const overlay = document.getElementById('sd-overlay');
    const panel = document.getElementById('sd-panel');
    if (!modal) return;

    if (shopModalTimeout) {
        clearTimeout(shopModalTimeout);
    }

    overlay.classList.add('opacity-0');
    panel.classList.add('opacity-0', 'scale-95');
    panel.classList.remove('scale-100');

    shopModalTimeout = setTimeout(() => {
        modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
        shopModalTimeout = null;
    }, 200);
}

/**
 * Basic phonetic transliteration to Arabic characters for aesthetic preview.
 */
function transliterateToArabic(text) {
    if (!text) return "";
    const map = {
        'a': 'ا', 'b': 'ب', 'c': 'ك', 'd': 'د', 'e': 'ي', 'f': 'ف', 'g': 'ج', 'h': 'ه', 'i': 'ي', 'j': 'ج',
        'k': 'ك', 'l': 'ل', 'm': 'م', 'n': 'ن', 'o': 'و', 'p': 'ب', 'q': 'ق', 'r': 'ر', 's': 'س', 't': 'ت',
        'u': 'و', 'v': 'ف', 'w': 'و', 'x': 'خ', 'y': 'ي', 'z': 'ز',
        'A': 'ا', 'B': 'ب', 'C': 'ك', 'D': 'د', 'E': 'ي', 'F': 'ف', 'G': 'ج', 'H': 'ه', 'I': 'ي', 'J': 'ج',
        'K': 'ك', 'L': 'ل', 'M': 'م', 'N': 'ن', 'O': 'و', 'P': 'ب', 'Q': 'ق', 'R': 'ر', 'S': 'س', 'T': 'ت',
        'U': 'و', 'V': 'ف', 'W': 'و', 'X': 'خ', 'Y': 'ي', 'Z': 'ز', ' ': ' '
    };
    return text.split('').map(char => map[char] || char).reverse().join('');
}

function renderShopDetailPreview(item, container) {
    container.innerHTML = '';
    const key = item.key_name;
    const name = item.name;
    const sampleName = (currentUser && currentUser.nome ? currentUser.nome : 'GIOCATORE');
    const avatar = (currentUser && currentUser.avatar_url) ? currentUser.avatar_url : 'img/avatar_placeholder.png';

    let html = '';

    if (key && key.startsWith('aura_')) {
        const auraClass = key.replace('_', '-'); // aura_flare -> aura-flare
        html = `
            <div class="aura-preview-container flex items-center justify-center p-8">
                <div class="relative">
                    <div class="absolute inset-[-8px] rounded-full ${auraClass} opacity-70 z-0"></div>
                    <div class="h-20 w-20 rounded-full border-4 border-white dark:border-gray-800 shadow-xl relative z-10 overflow-hidden bg-gray-200">
                        <img src="${avatar}" class="w-full h-full object-cover" />
                    </div>
                </div>
            </div>
        `;
    } else if (key && key.startsWith('title_')) {
        const titleText = name.replace('Titolo: ', '');
        html = `
            <div class="flex flex-col items-center p-4">
                <div class="podium-title-badge mb-3">${titleText}</div>
                <h3 class="text-xl font-black text-gray-900 dark:text-white">${sampleName}</h3>
            </div>
        `;
    } else if (key && key.startsWith('color_')) {
        const colorType = key.replace('color_', '');
        html = `
            <div class="flex flex-col items-center p-4">
                <h3 class="text-2xl font-black">
                    <span data-color="${colorType}">${sampleName}</span>
                </h3>
            </div>
        `;
    } else if (key && (key.startsWith('style_') || key.startsWith('name_'))) {
        const styleType = key.replace('style_', '').replace('name_', '');
        let displayName = sampleName;
        if (styleType === 'arabic') {
            displayName = transliterateToArabic(sampleName);
        }
        html = `
            <div class="flex flex-col items-center p-4">
                <h3 class="text-2xl font-black text-gray-900 dark:text-white">
                    <span data-style="${styleType}">${displayName}</span>
                </h3>
            </div>
        `;
    } else {
        html = `
            <div class="flex flex-col items-center p-6 text-gray-300">
                <span class="material-symbols-outlined text-6xl">${item.icon}</span>
            </div>
        `;
    }

    container.innerHTML = html;
}

function renderShopItems() {
    const grid = document.getElementById('shop-items-grid');
    if (!grid) return;

    const filteredItems = shopItems.filter(item => {
        const isActuallyAesthetic = item.item_type === 'aesthetic' || 
                           (item.key_name && (
                               item.key_name.startsWith('aura_') || 
                               item.key_name.startsWith('title_') || 
                               item.key_name.startsWith('color_') ||
                               item.key_name.startsWith('style_') ||
                               item.key_name.startsWith('name_')
                           ));
        
        const targetType = isActuallyAesthetic ? 'aesthetic' : 'bonus';
        return targetType === activeShopTab;
    });

    if (filteredItems.length === 0) {
        grid.innerHTML = `<div class="col-span-full py-20 text-center text-gray-400 italic">Nessun oggetto disponibile in questa categoria.</div>`;
        return;
    }

    grid.innerHTML = filteredItems.map((item, index) => {
        const isAesthetic = item.item_type === 'aesthetic' || 
                            (item.key_name && (
                                item.key_name.startsWith('aura_') || 
                                item.key_name.startsWith('title_') || 
                                item.key_name.startsWith('color_') ||
                                item.key_name.startsWith('style_') ||
                                item.key_name.startsWith('name_')
                            ));
        
        const isOwned = isAesthetic && parseInt(item.owned_quantity) > 0;
        
        let actionHTML = '';
        if (isOwned) {
            actionHTML = `
                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 font-bold text-xs border border-green-200 dark:border-green-800/50">
                    <span class="material-symbols-outlined text-sm">check_circle</span>
                    <span>POSSEDUTO</span>
                </div>
            `;
        } else {
            actionHTML = `
                <div class="shop-item-price px-2.5 py-1.5 rounded-xl flex items-center gap-1.5 font-bold text-xs">
                    <span class="material-symbols-outlined text-sm">monetization_on</span>
                    <span>${item.cost}</span>
                </div>
                <button onclick="buyItem(${item.id})" class="h-9 w-9 flex items-center justify-center rounded-xl bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900 active:scale-90 transition-all shadow-sm">
                    <span class="material-symbols-outlined text-xl">shopping_cart</span>
                </button>
            `;
        }
        return generateItemCardHTML(item, { index, actionHTML });
    }).join('');
}

function openShopPurchaseConfirmation(itemId) {
    const item = shopItems.find(i => i.id == itemId);
    if (!item) return;

    // Populate Modal
    document.getElementById('shopConfirmIcon').textContent = item.icon;
    document.getElementById('shopConfirmName').textContent = item.name;
    document.getElementById('shopConfirmCost').textContent = item.cost;

    // Set Action
    const btn = document.getElementById('shopConfirmBtn');
    btn.onclick = () => confirmBuyItem(itemId);

    // Show Modal
    const modal = document.getElementById('shopPurchaseModal');
    const overlay = document.getElementById('shopPurchaseOverlay');
    const panel = document.getElementById('shopPurchasePanel');

    document.body.classList.add('overflow-hidden');
    modal.classList.remove('hidden');
    requestAnimationFrame(() => {
        overlay.classList.remove('opacity-0');
        panel.classList.remove('opacity-0', 'scale-95');
        panel.classList.add('scale-100');
    });
}

async function confirmBuyItem(itemId) {
    // Close confirmation modal
    closeModal('shopPurchaseModal');

    try {
        const fd = new FormData();
        fd.append('item_id', itemId);
        const res = await fetch('shop_api.php?action=buy', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, 'success');
            document.getElementById('shop-user-credits').textContent = data.new_credits;
            loadUserInventory();
            if (window.loadProfileInventory) window.loadProfileInventory(); // Refresh profile inventory too
        } else {
            showToast(data.error || 'Errore acquisto', 'error');
        }
    } catch (e) {
        console.error(e);
        showToast('Errore di connessione', 'error');
    }
}

// Redirect buyItem to open confirmation
const buyItem = openShopPurchaseConfirmation;

// INVENTORY WIDGET (Live Match)

async function loadUserInventory() {
    if (!currentUser.id) return;

    // Visibility Check: only if user is seated at the table (set by updateLiveUI in app.js)
    const widget = document.getElementById('live-bonuses-widget');
    if (widget) {
        if (!window.isUserSeated) {
            widget.classList.add('hidden');
            return;
        }
    }

    try {
        const res = await fetch(`shop_api.php?action=inventory&table=${tableId}`);
        const data = await res.json();

        if (data.success) {
            // Filter out aesthetic items for the live widget (match) as they are not "usable" bonuses
            userInventory = data.inventory.filter(item => {
                const isAesthetic = item.item_type === 'aesthetic' || 
                                   (item.key_name && (
                                       item.key_name.startsWith('aura_') || 
                                       item.key_name.startsWith('title_') || 
                                       item.key_name.startsWith('color_') ||
                                       item.key_name.startsWith('style_') ||
                                       item.key_name.startsWith('name_')
                                   ));
                return !isAesthetic;
            });
            renderInventoryWidget();

            // Show widget if inventory > 0 AND seated
            if (widget) {
                if (userInventory.length > 0) widget.classList.remove('hidden');
                else widget.classList.add('hidden');
            }
        }
    } catch (e) {
        console.error("Inventory error:", e);
    }
}

function renderInventoryWidget() {
    const list = document.getElementById('inventory-list');
    if (!list) return;

    if (userInventory.length === 0) {
        list.innerHTML = '<div class="text-sm text-gray-400 italic w-full text-center py-2">Nessun bonus disponibile</div>';
        return;
    }

    list.innerHTML = userInventory.map(item => `
        <div onclick="openShopUseConfirmation(${item.id})" class="flex-shrink-0 xl:cursor-pointer flex flex-col items-center gap-1 min-w-[65px] p-2 rounded-xl bg-white dark:bg-gray-800 border border-indigo-100 dark:border-indigo-800/50 xl:hover:border-indigo-400 xl:hover:shadow-md transition-all active:scale-95 group relative">
            <div class="h-8 w-8 rounded-full bg-indigo-100 dark:bg-indigo-900/50 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                <span class="material-symbols-outlined text-lg">${item.icon}</span>
            </div>
            <span class="text-[9px] font-black text-gray-800 dark:text-gray-300 text-center leading-tight line-clamp-2 w-full uppercase tracking-tighter">${item.name}</span>
            <div class="absolute -top-1 -right-1 h-4 w-4 rounded-full bg-red-500 text-white text-[9px] flex items-center justify-center font-black shadow-sm border border-white dark:border-gray-800">${item.quantity}</div>
        </div>
    `).join('');
}

function openShopUseConfirmation(itemId) {
    const item = userInventory.find(i => i.id == itemId);
    if (!item) return;

    // Populate Modal
    document.getElementById('shopUseIcon').textContent = item.icon;
    document.getElementById('shopUseName').textContent = item.name;

    // Set Action
    const btn = document.getElementById('shopUseConfirmBtn');
    btn.onclick = () => confirmUseItem(itemId);

    // Show Modal
    const modal = document.getElementById('shopUseModal');
    const overlay = document.getElementById('shopUseOverlay');
    const panel = document.getElementById('shopUsePanel');

    document.body.classList.add('overflow-hidden');
    modal.classList.remove('hidden');
    requestAnimationFrame(() => {
        overlay.classList.remove('opacity-0');
        panel.classList.remove('opacity-0', 'scale-95');
        panel.classList.add('scale-100');
    });
}

async function confirmUseItem(itemId) {
    closeModal('shopUseModal');

    try {
        const fd = new FormData();
        fd.append('item_id', itemId);
        fd.append('table', tableId);
        const res = await fetch('shop_api.php?action=use', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, 'success');

            if (data.key === 'switch') {
                if (window.pollLiveTable) window.pollLiveTable();
                showToast('🔄 SWITCH EFFETTUATO!', 'success');
            } else if (data.key === 'palla_matta') {
                showToast('⚽ PALLA MATTA ATTIVA!', 'warning');
            }

            loadUserInventory();
            if (window.loadProfileInventory) window.loadProfileInventory();
        } else {
            showToast(data.error || 'Impossibile usare', 'error');
        }
    } catch (e) {
        console.error(e);
        showToast('Errore attivazione', 'error');
    }
}

// Redirect useItem to open confirmation
const useItem = openShopUseConfirmation;

// ACTIVE BONUS POLLING

// ACTIVE BONUS POLLING (Removed as requested)
async function checkActiveBonuses() {
    return;
}

// PROFILE INVENTORY
async function loadProfileInventory() {
    if (!currentUser.id) return;
    try {
        const res = await fetch(`shop_api.php?action=inventory&table=${tableId}`);
        const data = await res.json();
        if (data.success) {
            userInventory = data.inventory;
            renderProfileInventory();
        }
    } catch (e) {
        console.error("Profile Inventory error:", e);
    }
}

function renderProfileInventory() {
    const list = document.getElementById('inventory-profile-list');
    if (!list) return;

    if (userInventory.length === 0) {
        list.innerHTML = '<div class="col-span-full text-center py-8 text-gray-500 dark:text-gray-400 italic">Non hai acquistato ancora nulla.</div>';
        return;
    }

    list.innerHTML = userInventory.map((item, index) => {
        const isAesthetic = item.item_type === 'aesthetic' || 
                           (item.key_name && (
                               item.key_name.startsWith('aura_') || 
                               item.key_name.startsWith('title_') || 
                               item.key_name.startsWith('color_') ||
                               item.key_name.startsWith('style_') ||
                               item.key_name.startsWith('name_')
                           ));
        
        let actionHTML = '';
        if (isAesthetic) {
            const player = (typeof giocatori !== 'undefined' && Array.isArray(giocatori)) 
                ? (giocatori.find(g => g.id == currentUser.id) || currentUser) 
                : currentUser;
            
            let isEquipped = false;
            let type = '';

            if (item.key_name && item.key_name.startsWith('aura_')) {
                type = 'aura';
                isEquipped = player.active_aura === item.key_name.replace('aura_', '');
            } else if (item.key_name && item.key_name.startsWith('title_')) {
                type = 'title';
                isEquipped = player.active_title === item.name.replace('Titolo: ', ''); 
            } else if (item.key_name && item.key_name.startsWith('color_')) {
                type = 'color';
                isEquipped = player.active_name_color === item.key_name.replace('color_', '');
            } else if (item.key_name && (item.key_name.startsWith('style_') || item.key_name.startsWith('name_'))) {
                type = 'style';
                isEquipped = player.active_name_style === item.key_name.replace('style_', '').replace('name_', '');
            }

            if (isEquipped) {
                actionHTML = `<button onclick="unequipItem('${type}')" class="h-9 px-4 rounded-xl bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 text-[10px] font-bold uppercase tracking-wider flex items-center justify-center border border-red-100 dark:border-red-800/50">Rimuovi</button>`;
            } else {
                actionHTML = `<button onclick="equipItem(${item.id})" class="h-9 px-4 rounded-xl bg-green-50 dark:bg-green-900/30 text-green-600 dark:text-green-400 text-[10px] font-bold uppercase tracking-wider flex items-center justify-center border border-green-100 dark:border-green-800/50">Equipaggia</button>`;
            }
        } else {
            actionHTML = `<div class="h-9 px-4 rounded-xl bg-gray-50 dark:bg-gray-800/50 text-gray-400 text-[10px] font-bold uppercase tracking-wider flex items-center justify-center border border-gray-100 dark:border-gray-700/50 opacity-50">In Uso</div>`;
        }

        return generateItemCardHTML(item, { index, isInventory: true, actionHTML });
    }).join('');
}

// Expose
window.openShopModal = openShopModal;
window.buyItem = buyItem;
window.useItem = useItem;
window.loadProfileInventory = loadProfileInventory;
window.loadUserInventory = loadUserInventory;
window.openShopDetailModal = openShopDetailModal;
window.closeShopDetailModal = closeShopDetailModal;
window.switchShopTab = switchShopTab;
window.openShopPurchaseConfirmation = openShopPurchaseConfirmation;
window.openShopUseConfirmation = openShopUseConfirmation;

// Start polling for bonuses
setInterval(checkActiveBonuses, 5000);
// Poll inventory less frequent or same? Same is fine.
setInterval(loadUserInventory, 5000);
