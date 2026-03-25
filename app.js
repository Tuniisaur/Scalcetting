
const API_URL = 'index.php?api=1';
let currentUser = null;
let giocatori = [];
let partite = [];
let stagioni = [];
let activeSeasonId = null;
let selectedLeaderboardSeasonId = null;
let selectedMatchesSeasonId = null;
let activeProfileTab = 'overview';

// --- UTILITIES ---
function applyNameStyle(name, style) {
    if (!name) return "";
    if (style === 'arabic') {
        const map = {
            'a': 'ا', 'b': 'ب', 'c': 'ك', 'd': 'د', 'e': 'ي', 'f': 'ف', 'g': 'ج', 'h': 'ه', 'i': 'ي', 'j': 'ج',
            'k': 'ك', 'l': 'ل', 'm': 'م', 'n': 'ن', 'o': 'و', 'p': 'ب', 'q': 'ق', 'r': 'ر', 's': 'س', 't': 'ت',
            'u': 'و', 'v': 'ف', 'w': 'و', 'x': 'خ', 'y': 'ي', 'z': 'ز',
            'A': 'ا', 'B': 'ب', 'C': 'ك', 'D': 'د', 'E': 'ي', 'F': 'ف', 'G': 'ج', 'H': 'ه', 'I': 'ي', 'J': 'ج',
            'K': 'ك', 'L': 'ل', 'M': 'م', 'N': 'ن', 'O': 'و', 'P': 'ب', 'Q': 'ق', 'R': 'ر', 'S': 'س', 'T': 'ت',
            'U': 'و', 'V': 'ف', 'W': 'و', 'X': 'خ', 'Y': 'ي', 'Z': 'ز'
        };
        return name.split('').map(char => map[char] || char).reverse().join('');
    }
    if (style === 'chinese' || style === 'name_chinese') {
        const map = {
            'a': '卂', 'b': '乃', 'c': '匚', 'd': '刀', 'e': '乇', 'f': '下', 'g': '厶', 'h': '卄', 'i': '工', 'j': '丁',
            'k': '长', 'l': '乚', 'm': '爪', 'n': '冂', 'o': '口', 'p': '尸', 'q': '口', 'r': '尺', 's': '丂', 't': '丅',
            'u': '凵', 'v': 'V', 'w': '山', 'x': '乂', 'y': '丫', 'z': '乙',
            'A': '卂', 'B': '乃', 'C': '匚', 'D': '刀', 'E': '乇', 'F': '下', 'G': '厶', 'H': '卄', 'I': '工', 'J': '丁',
            'K': '长', 'L': '乚', 'M': '爪', 'N': '冂', 'O': '口', 'P': '尸', 'Q': '口', 'R': '尺', 'S': '丂', 'T': '丅',
            'U': '凵', 'V': 'V', 'W': '山', 'X': '乂', 'Y': '丫', 'Z': '乙'
        };
        return name.split('').map(char => map[char] || char).join('');
    }
    if (style === 'russian' || style === 'name_russian') {
        const map = {
            'a': 'а', 'b': 'б', 'c': 'ц', 'd': 'д', 'e': 'е', 'f': 'ф', 'g': 'г', 'h': 'х', 'i': 'и', 'j': 'й',
            'k': 'к', 'l': 'л', 'm': 'м', 'n': 'н', 'o': 'о', 'p': 'п', 'q': 'к', 'r': 'р', 's': 'с', 't': 'т',
            'u': 'у', 'v': 'в', 'w': 'ш', 'x': 'х', 'y': 'ы', 'z': 'з',
            'A': 'А', 'B': 'Б', 'C': 'Ц', 'D': 'Д', 'E': 'Е', 'F': 'Ф', 'G': 'Г', 'H': 'Х', 'I': 'И', 'J': 'Й',
            'K': 'К', 'L': 'Л', 'M': 'М', 'N': 'Н', 'O': 'О', 'P': 'П', 'Q': 'К', 'R': 'Р', 'S': 'С', 'T': 'Т',
            'U': 'У', 'V': 'В', 'W': 'Ш', 'X': 'Х', 'Y': 'Ы', 'Z': 'З'
        };
        return name.split('').map(char => map[char] || char).join('');
    }
    return name;
}

// --- INITIALIZATION ---
document.addEventListener('DOMContentLoaded', () => {
    checkAuth();
    loadData();
    initLiveMatch();

    // Initialize leaderboard tab from localStorage
    const savedTab = localStorage.getItem('leaderboardTab') || 'generale';
    if (window.currentLeaderboardTab) {
        window.currentLeaderboardTab = savedTab;
    }
    // Set active tab button on page load
    setTimeout(() => {
        const tabBtn = document.getElementById(`tab-${savedTab}`);
        if (tabBtn) {
            document.querySelectorAll('.leaderboard-tab').forEach(btn => {
                btn.classList.remove('active');
            });
            tabBtn.classList.add('active');
        }
    }, 100);
});

async function checkAuth() {
    try {
        const res = await fetch('auth.php?action=check');
        const data = await res.json();
        if (data.success && data.logged_in) {
            currentUser = data.user;
            updateAuthUI();
        }
    } catch (e) {
        console.error("Auth check failed", e);
    }
}

function updateAuthUI() {
    // 1. Update "Signed in as" section
    const signedInDiv = document.getElementById('signedInUser');
    const userAvatarImg = document.getElementById('userAvatarImg');
    const userInitialsDiv = document.getElementById('userInitials');
    const userNameSpan = document.getElementById('userNameSpan');

    // Mobile Elements
    const welcomeAvatar = document.getElementById('welcomeAvatar');
    const welcomeInitialsDiv = document.getElementById('welcomeInitials');
    const welcomeName = document.getElementById('welcomeName');
    const loginBtn = document.getElementById('loginButton'); // Added missing definition

    if (currentUser) {
        // Logged In

        // Desktop Sidebar
        if (signedInDiv) {
            signedInDiv.removeAttribute('onclick');
            signedInDiv.style.cursor = 'default';
        }
        if (userAvatarImg && userInitialsDiv) {
            if (currentUser.avatar_url) {
                userAvatarImg.src = currentUser.avatar_url;
                userAvatarImg.classList.remove('hidden');
                userInitialsDiv.classList.add('hidden');
            } else {
                userAvatarImg.classList.add('hidden');
                userInitialsDiv.classList.remove('hidden');
            }
        }
        if (userNameSpan) {
            userNameSpan.innerHTML = `
                <span data-color="${currentUser.active_name_color || ''}" data-style="${currentUser.active_name_style || ''}">
                    ${applyNameStyle(currentUser.nome, currentUser.active_name_style)}
                </span>
                ${currentUser.is_admin ? '<span class="text-xs font-normal text-gray-500">(Admin)</span>' : ''}
            `;
            userNameSpan.setAttribute('data-color', currentUser.active_name_color || '');
            userNameSpan.setAttribute('data-style', currentUser.active_name_style || '');
        }

        // Mobile Header
        if (welcomeAvatar && welcomeInitialsDiv) {
            // Add aura container if it doesn't exist or update it
            let auraContainer = welcomeAvatar.parentElement.querySelector('.aura-overlay');
            if (!auraContainer) {
                auraContainer = document.createElement('div');
                auraContainer.className = 'absolute inset-[-4px] rounded-full opacity-70 z-0 aura-overlay';
                welcomeAvatar.parentElement.prepend(auraContainer);
            }

            if (currentUser.active_aura) {
                auraContainer.className = `absolute inset-[-4px] rounded-full aura-${currentUser.active_aura} opacity-70 z-0 aura-overlay`;
                auraContainer.classList.remove('hidden');
            } else {
                auraContainer.classList.add('hidden');
            }

            if (currentUser.avatar_url) {
                welcomeAvatar.src = currentUser.avatar_url;
                welcomeAvatar.classList.remove('hidden');
                welcomeInitialsDiv.classList.add('hidden');
            } else {
                welcomeAvatar.classList.add('hidden');
                welcomeInitialsDiv.classList.remove('hidden');
            }
        }
        if (welcomeName) {
            welcomeName.innerHTML = `
                <span data-color="${currentUser.active_name_color || ''}" data-style="${currentUser.active_name_style || ''}">
                    ${applyNameStyle(currentUser.nome, currentUser.active_name_style)}
                </span>
            `;
            welcomeName.setAttribute('data-color', currentUser.active_name_color || '');
            welcomeName.setAttribute('data-style', currentUser.active_name_style || '');
        }

        // Remove onclick from login button container to prevent modal opening
        if (loginBtn) {
            loginBtn.removeAttribute('onclick');
            loginBtn.style.cursor = 'default';
        }

    } else {
        // Guest / Not Logged In

        // Desktop Sidebar
        if (signedInDiv) {
            signedInDiv.setAttribute('onclick', "toggleAuthModal()");
            signedInDiv.style.cursor = 'pointer';
        }
        if (userAvatarImg) userAvatarImg.classList.add('hidden');
        if (userInitialsDiv) userInitialsDiv.classList.add('hidden');
        if (userNameSpan) userNameSpan.textContent = 'Ospite';

        if (loginBtn) {
            loginBtn.setAttribute('onclick', "document.getElementById('loginModal').classList.remove('hidden')");
            loginBtn.style.cursor = 'pointer';
        }
    }
}

async function loadData() {
    try {
        const [pRes, gRes, sRes] = await Promise.all([
            fetch('partite.php'),
            fetch('giocatori.php'),
            fetch('api_stagioni.php?action=list')
        ]);

        const pText = await pRes.text();
        const gText = await gRes.text();
        const sText = await sRes.text();

        try {
            const pData = JSON.parse(pText);
            const gData = JSON.parse(gText);
            const sData = JSON.parse(sText);

            if (!Array.isArray(pData)) {
                console.error("Partite data is not an array", pData);
                partite = [];
            } else {
                partite = pData;
            }

            if (!Array.isArray(gData)) {
                console.error("Giocatori data is not an array", gData);
                giocatori = [];
                if (gData && gData.error) {
                    showToast(`Errore caricamento giocatori: ${gData.message || gData.error}`, 'error');
                }
            } else {
                giocatori = gData;
            }

            if (sData && sData.success && Array.isArray(sData.data)) {
                stagioni = sData.data;
                const activeSeasonName = stagioni.find(s => parseInt(s.is_active) === 1);
                if (activeSeasonName) activeSeasonId = parseInt(activeSeasonName.id);
                // Set default dropdowns
                if (!selectedLeaderboardSeasonId) selectedLeaderboardSeasonId = activeSeasonId;
                if (!selectedMatchesSeasonId) selectedMatchesSeasonId = activeSeasonId;
                renderSeasonDropdowns();
            }
        } catch (e) {
            console.error("Error parsing JSON", e, { pText, gText, sText });
            partite = [];
            giocatori = [];
        }

        renderDashboard();
        renderLeaderboard();
        renderMatches();
        renderProfile();

        populatePlayerSelects();
        checkSeasonPassNotifications();

    } catch (error) {
        console.error("Critical error in loadData:", error);
    }
}

// --- RENDERING ---

function renderDashboard() {
    const container = document.getElementById('recentMatchesList');

    if (!partite.length || !giocatori.length) {
        if (container) {
            container.innerHTML = '<div class="text-center text-gray-500 py-4 col-span-full">Nessuna partita registrata</div>';
        }
        return;
    }

    // Determine player to display (User or Top Player)
    let displayPlayer = null;
    if (currentUser) {
        displayPlayer = giocatori.find(g => g.id == currentUser.id);
    }
    if (!displayPlayer) {
        displayPlayer = [...giocatori].sort((a, b) => {
            const maxA = Math.max(a.elo_attaccante, a.elo_portiere);
            const maxB = Math.max(b.elo_attaccante, b.elo_portiere);
            return maxB - maxA;
        })[0];
    }

    if (displayPlayer) {
        const bestElo = Math.max(displayPlayer.elo_attaccante, displayPlayer.elo_portiere);
        const winRate = Math.round((displayPlayer.vittorie_totali / displayPlayer.partite_totali) * 100) || 0;

        // Calculate Rank
        const sorted = [...giocatori].sort((a, b) => {
            return Math.max(b.elo_attaccante, b.elo_portiere) - Math.max(a.elo_attaccante, a.elo_portiere);
        });
        const rank = sorted.findIndex(p => p.id === displayPlayer.id) + 1;

        // 4. Calculate Streak
        // Filter matches for this player only
        const pMatches = partite.filter(m => {
            return m.squadra1_portiere == displayPlayer.id || m.squadra1_attaccante == displayPlayer.id ||
                m.squadra2_portiere == displayPlayer.id || m.squadra2_attaccante == displayPlayer.id;
        }).sort((a, b) => new Date(a.data) - new Date(b.data));

        let currentStreak = 0;

        pMatches.forEach(m => {
            const isBlue = (m.squadra1_portiere == displayPlayer.id || m.squadra1_attaccante == displayPlayer.id);
            const isBlueWin = m.vincitore == 1;
            const won = (isBlue && isBlueWin) || (!isBlue && !isBlueWin);

            if (won) {
                if (currentStreak < 0) currentStreak = 0;
                currentStreak++;
            } else {
                if (currentStreak > 0) currentStreak = 0;
                currentStreak--;
            }
        });

        const credits = displayPlayer.crediti || 0;

        const fmtStreak = (s) => s > 0 ? `+${s}` : (s < 0 ? `${s}` : '0');
        safeSetText('dashboardStreakMobile', fmtStreak(currentStreak));

        // Mobile
        safeSetText('dashboardEloMobile', bestElo);
        safeSetText('dashboardRankMobile', '#' + rank);
        safeSetText('dashboardCreditsMobile', credits);

        // Desktop
        safeSetText('dashboardEloDesktop', bestElo);
        safeSetText('dashboardRankDesktop', '#' + rank);
        safeSetText('dashboardCreditsDesktop', credits);

        // Fetch user's recent stats (Elo delta from last 5 matches)
        if (currentUser && displayPlayer.id == currentUser.id) {
            fetchUserRecentStats();
        }
    }

    // Recent Matches
    const recent = partite.slice(0, 5);
    if (container) {
        container.innerHTML = recent.length ? recent.map(m => createMatchHTML(m)).join('') : '<div class="text-center text-gray-500 py-4 col-span-full">Nessuna partita recente</div>';
    }
}

async function fetchUserRecentStats() {
    try {
        const res = await fetch('api_recent_stats.php');
        const data = await res.json();

        if (data.success) {
            renderUserRecentStats(data.matches, data.total_elo_change);
        } else {
            console.error("Error fetching recent stats", data.error);
        }
    } catch (e) {
        console.error("Network error fetching recent stats", e);
    }
}

function renderUserRecentStats(matches, totalDelta) {
    const desktopWidget = document.getElementById('recentStatsWidgetDesktop');
    const mobileWidget = document.getElementById('recentStatsWidgetMobile');
    const desktopDots = document.getElementById('recentMatchDotsDesktop');
    const mobileDots = document.getElementById('recentMatchDotsMobile');
    const desktopDelta = document.getElementById('recentEloDeltaDesktop');
    const mobileDelta = document.getElementById('recentEloDeltaMobile');

    if (!desktopWidget || !mobileWidget) return;

    if (matches && matches.length > 0) {
        desktopWidget.classList.remove('hidden');
        mobileWidget.classList.remove('hidden');

        // Style total delta
        const deltaText = (totalDelta > 0 ? '+' : '') + totalDelta;
        const deltaBgClass = totalDelta > 0 ? 'bg-emerald-50 text-emerald-600 border-emerald-200/50 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20 shadow-lg shadow-emerald-500/10' :
            (totalDelta < 0 ? 'bg-rose-50 text-rose-600 border-rose-200/50 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/20 shadow-lg shadow-rose-500/10' : 'bg-gray-50 text-gray-600 border-gray-200/50 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700 shadow-sm');

        // Remove old color classes to prevent mixing
        [desktopDelta, mobileDelta].forEach(el => {
            el.className = `font-black px-3.5 py-1.5 rounded-2xl border backdrop-blur-sm ${deltaBgClass} flex items-center justify-center gap-1.5`;
            const icon = totalDelta > 0 ? 'trending_up' : (totalDelta < 0 ? 'trending_down' : 'horizontal_rule');
            el.innerHTML = `<span class="material-symbols-outlined text-[16px]">${icon}</span> <span>${deltaText}</span><span class="text-[9px] uppercase font-black opacity-50 tracking-wider">ELO</span>`;
        });

        // Generate dots HTML
        // Reversing to show oldest to newest left to right, assuming matches are sorted newest first from API limit
        const reversedMatches = [...matches].reverse();

        let dotsHtml = reversedMatches.map((m, index) => {
            const isWin = m.won;
            const sizeClass = 'w-9 h-9 md:w-11 md:h-11';
            const colorClass = isWin
                ? 'bg-gradient-to-br from-emerald-400 to-emerald-500 border-2 border-white dark:border-gray-800 shadow-xl shadow-emerald-500/30 ring-4 ring-transparent xl:hover:ring-emerald-500/20 xl:hover:shadow-emerald-500/50'
                : 'bg-gradient-to-br from-rose-400 to-rose-500 border-2 border-white dark:border-gray-800 shadow-xl shadow-rose-500/30 ring-4 ring-transparent xl:hover:ring-rose-500/20 xl:hover:shadow-rose-500/50';
            const icon = isWin ? 'check' : 'close';

            return `
                <div class="relative group/btn xl:cursor-pointer transition-all duration-500" onclick="openMatchDetails(${m.id})">
                    <div class="${sizeClass} rounded-full flex items-center justify-center ${colorClass} relative z-10 transition-transform duration-300 xl:group-hover/btn:scale-110 xl:group-hover/btn:-translate-y-1.5">
                        <span class="material-symbols-outlined text-white text-[16px] md:text-[20px] font-black drop-shadow-sm">${icon}</span>
                    </div>
                    
                    <!-- Premium Tooltip -->
                    <div class="absolute -top-12 left-1/2 -translate-x-1/2 hidden xl:group-hover/btn:flex flex-col items-center z-50 pointer-events-none animate-in fade-in slide-in-from-bottom-2 duration-200">
                        <div class="bg-gray-900/95 backdrop-blur-sm dark:bg-white/95 text-white dark:text-gray-900 text-xs font-black px-3 py-1.5 rounded-xl shadow-2xl whitespace-nowrap border border-white/10 dark:border-black/5 flex items-center gap-1.5">
                            <span class="text-[9px] opacity-60 font-black uppercase tracking-widest">ELO</span>
                            <span class="${m.delta > 0 ? 'text-emerald-400 dark:text-emerald-500' : 'text-rose-400 dark:text-rose-500'} text-sm leading-none">${m.delta > 0 ? '+' : ''}${m.delta}</span>
                        </div>
                        <div class="w-2.5 h-2.5 bg-gray-900/95 dark:bg-white/95 rotate-45 -mt-1.5 shadow-xl border-r border-b border-white/10 dark:border-black/5"></div>
                    </div>
                </div>
            `;
        }).join('');

        // Add empty dots if less than 5 matches
        const emptySpots = 5 - reversedMatches.length;
        for (let i = 0; i < emptySpots; i++) {
            dotsHtml += `
                <div class="relative w-9 h-9 md:w-11 md:h-11 flex items-center justify-center">
                    <div class="w-full h-full rounded-full bg-gray-50/50 dark:bg-gray-800/30 border-2 border-dashed border-gray-300/50 dark:border-gray-600/50 flex items-center justify-center backdrop-blur-sm">
                        <div class="w-1.5 h-1.5 rounded-full bg-gray-300 dark:bg-gray-600"></div>
                    </div>
                </div>
            `;
        }

        desktopDots.innerHTML = dotsHtml;
        mobileDots.innerHTML = dotsHtml;

    } else {
        desktopWidget.classList.add('hidden');
        mobileWidget.classList.add('hidden');
    }
}

function renderLeaderboard(players = giocatori) {
    if (!players.length) return;

    // Get current tab from index.php (defaults to 'generale')
    const tab = window.currentLeaderboardTab || 'generale';

    let filtered = [];
    let sorted = [];

    switch (tab) {
        case 'attaccanti':
            // Filter players who have played at least 1 match as attacker
            filtered = players.filter(p => p.partite_attaccante > 0);
            // Sort by attacker ELO
            sorted = [...filtered].sort((a, b) => b.elo_attaccante - a.elo_attaccante);
            break;

        case 'portieri':
            // Filter players who have played at least 1 match as goalkeeper
            filtered = players.filter(p => p.partite_portiere > 0);
            // Sort by goalkeeper ELO
            sorted = [...filtered].sort((a, b) => b.elo_portiere - a.elo_portiere);
            break;

        case 'generale':
        default:
            // All players, sorted by overall ELO (elo_medio)
            filtered = players;
            sorted = [...filtered].sort((a, b) => {
                const eloA = a.elo_medio || Math.max(a.elo_attaccante || 0, a.elo_portiere || 0);
                const eloB = b.elo_medio || Math.max(b.elo_attaccante || 0, b.elo_portiere || 0);
                return eloB - eloA;
            });
            break;
    }

    const podiumEl = document.getElementById('leaderboardPodium');
    if (podiumEl && sorted.length >= 3) {
        const [first, second, third] = sorted;
        podiumEl.innerHTML = `
            ${createPodiumItem(second, 2, tab)}
            ${createPodiumItem(first, 1, tab)}
            ${createPodiumItem(third, 3, tab)}
        `;
    } else if (podiumEl) {
        podiumEl.innerHTML = '<div class="text-center text-gray-500 py-8 col-span-full">Non ci sono abbastanza giocatori per il podio</div>';
    }

    const listEl = document.getElementById('leaderboardList');
    if (listEl) {
        const startIndex = sorted.length >= 3 ? 3 : 0;
        if (sorted.length > startIndex) {
            listEl.innerHTML = sorted.slice(startIndex).map((p, i) => createLeaderboardItem(p, i + startIndex + 1, tab)).join('');
        } else {
            listEl.innerHTML = '<div class="text-center text-gray-500 py-4">Nessun altro giocatore</div>';
        }
    }
}

// Expose globally for tab switching
window.renderLeaderboard = renderLeaderboard;

let currentLeaderboardPlayers = [];

async function changeLeaderboardSeason(seasonId) {
    selectedLeaderboardSeasonId = parseInt(seasonId);
    if (selectedLeaderboardSeasonId === activeSeasonId) {
        currentLeaderboardPlayers = giocatori;
        renderLeaderboard(currentLeaderboardPlayers);
        return;
    }
    try {
        const res = await fetch(`api_stagioni.php?action=leaderboard&stagione_id=${selectedLeaderboardSeasonId}`);
        const data = await res.json();
        if (data.success) {
            currentLeaderboardPlayers = data.data;
            renderLeaderboard(currentLeaderboardPlayers);
        } else {
            showToast('Errore nel caricare la classifica storica', 'error');
        }
    } catch (e) {
        console.error(e);
        showToast('Errore di connessione', 'error');
    }
}
window.changeLeaderboardSeason = changeLeaderboardSeason;

function changeMatchesSeason(seasonId) {
    selectedMatchesSeasonId = parseInt(seasonId);
    visibleMatchesCount = 20; // reset pagination
    renderMatches();
}
window.changeMatchesSeason = changeMatchesSeason;

function renderSeasonDropdowns() {
    const lbContainer = document.getElementById('leaderboardSeasonSelect');
    const lbContainerMobile = document.getElementById('leaderboardSeasonSelectMobile');
    const mContainer = document.getElementById('matchesSeasonSelect');
    const mContainerMobile = document.getElementById('matchesSeasonSelectMobile');

    if (!stagioni.length) return;

    const optionsHtml = stagioni.map(s => `<option value="${s.id}">${s.nome}${s.is_active == 1 ? ' (Corrente)' : ''}</option>`).join('');

    if (lbContainer) {
        lbContainer.innerHTML = optionsHtml;
        lbContainer.value = selectedLeaderboardSeasonId;
    }
    if (lbContainerMobile) {
        lbContainerMobile.innerHTML = optionsHtml;
        lbContainerMobile.value = selectedLeaderboardSeasonId;
    }
    if (mContainer) {
        mContainer.innerHTML = optionsHtml;
        mContainer.value = selectedMatchesSeasonId;
    }
    if (mContainerMobile) {
        mContainerMobile.innerHTML = optionsHtml;
        mContainerMobile.value = selectedMatchesSeasonId;
    }
}

let visibleMatchesCount = 20;

function renderMatches() {
    const container = document.getElementById('fullMatchList');
    if (!container) return;

    let filteredMatches = partite;
    if (selectedMatchesSeasonId) {
        filteredMatches = partite.filter(m => m.stagione_id == selectedMatchesSeasonId);
    }

    const visibleMatches = filteredMatches.slice(0, visibleMatchesCount);
    if (visibleMatches.length === 0) {
        container.innerHTML = '<div class="text-center text-gray-500 py-8">Nessuna partita in questa stagione</div>';
    } else {
        container.innerHTML = visibleMatches.map(m => createMatchHTML(m)).join('');
    }

    // Load More Button handling
    const loadMoreContainer = document.getElementById('loadMoreContainer');
    if (loadMoreContainer) {
        if (visibleMatchesCount < filteredMatches.length) {
            loadMoreContainer.classList.remove('hidden');
        } else {
            loadMoreContainer.classList.add('hidden');
        }
    }
}

function loadMoreMatches() {
    visibleMatchesCount += 20;
    renderMatches();
}

// --- PROFILE LOGIC ---

let profileChart = null;
let selectedProfileSeasonId = null;

function changeProfileSeason(seasonId) {
    selectedProfileSeasonId = parseInt(seasonId);
    if (selectedProfileSeasonId === 0) selectedProfileSeasonId = 0; // Explicitly allowed
    renderProfile();
}
window.changeProfileSeason = changeProfileSeason;

/**
 * Safe player lookup helper. Handles missing players and the Ghost/Guest player (ID 9999).
 */
function getPlayerById(id) {
    if (!id) return null;
    const player = giocatori.find(g => g.id == id);
    if (player) return player;

    if (parseInt(id) === 9999) {
        return { id: 9999, nome: 'Ospite', username: 'ospite', avatar_url: null };
    }

    return { id: id, nome: 'Sconosciuto', username: 'unknown', avatar_url: null };
}

// Helper function to calculate advanced stats like Compagno Ideale and Nemesi
function calculateAdvancedStats(playerId, pMatches) {
    const teammates = {};
    const opponents = {};

    pMatches.forEach(m => {
        let myTeam = 0;
        let myRole = '';
        let myTeammateId = null;
        let opp1Id = null, opp2Id = null;

        if (m.squadra1_portiere == playerId || m.squadra1_attaccante == playerId) {
            myTeam = 1;
            if (m.squadra1_portiere == playerId) { myTeammateId = m.squadra1_attaccante; myRole = 'def'; }
            else { myTeammateId = m.squadra1_portiere; myRole = 'atk'; }
            opp1Id = m.squadra2_portiere;
            opp2Id = m.squadra2_attaccante;
        } else if (m.squadra2_portiere == playerId || m.squadra2_attaccante == playerId) {
            myTeam = 2;
            if (m.squadra2_portiere == playerId) { myTeammateId = m.squadra2_attaccante; myRole = 'def'; }
            else { myTeammateId = m.squadra2_portiere; myRole = 'atk'; }
            opp1Id = m.squadra1_portiere;
            opp2Id = m.squadra1_attaccante;
        }

        if (myTeam === 0) return;

        const isBlueWin = (m.vincitore == 1);
        const won = (myTeam === 1 && isBlueWin) || (myTeam === 2 && !isBlueWin);

        // Track Teammate
        if (myTeammateId) {
            if (!teammates[myTeammateId]) teammates[myTeammateId] = { matches: 0, wins: 0 };
            teammates[myTeammateId].matches++;
            if (won) teammates[myTeammateId].wins++;
        }

        // Track Opponents
        [opp1Id, opp2Id].forEach(oppId => {
            if (oppId) {
                if (!opponents[oppId]) opponents[oppId] = { matches: 0, wins: 0, losses: 0 };
                opponents[oppId].matches++;
                if (won) opponents[oppId].wins++;
                else opponents[oppId].losses++;
            }
        });
    });

    const findBest = (map, valueKey, reverse = false) => {
        let bestId = null;
        let bestScore = reverse ? 999 : -1;
        for (const [id, s] of Object.entries(map)) {
            if (s.matches < 2) continue; // Min 2 matches for advanced metrics
            const ratio = s[valueKey] / s.matches;
            let score = ratio;
            if (s.matches < 3) score += (reverse ? 0.5 : -0.5); // Weight small samples

            if (!reverse) {
                if (score > bestScore) { bestScore = score; bestId = id; }
            } else {
                if (score < bestScore) { bestScore = score; bestId = id; }
            }
        }
        return bestId ? { id: bestId, ...map[bestId] } : null;
    };

    const bestTeammate = findBest(teammates, 'wins');
    const pallaAlPiede = findBest(teammates, 'wins', true);
    const nemesis = findBest(opponents, 'losses');
    const vittimaPreferita = findBest(opponents, 'wins');

    return { bestTeammate, pallaAlPiede, nemesis, vittimaPreferita };
}


function renderProfile() {
    if (!currentUser) {
        document.getElementById('profileContent').innerHTML = `
            <div class="text-center py-10">
                <span class="material-symbols-outlined text-4xl text-gray-400 mb-2">lock</span>
                <p class="text-gray-500">Devi effettuare il login per vedere il tuo profilo.</p>
                <button onclick="toggleAuthModal()" class="mt-4 px-6 py-2 bg-primary text-white rounded-xl font-bold">Accedi</button>
            </div>
        `;
        return;
    }

    // 1. Calculate Stats
    const player = giocatori.find(g => g.id == currentUser.id) || { ...currentUser, elo_attaccante: 1500, elo_portiere: 1500, partite_attaccante: 0, partite_portiere: 0, vittorie_attaccante: 0, vittorie_portiere: 0, vittorie_totali: 0, partite_totali: 0 };

    // Streaks
    const pMatches = partite.filter(m => {
        return m.squadra1_portiere == player.id || m.squadra1_attaccante == player.id ||
            m.squadra2_portiere == player.id || m.squadra2_attaccante == player.id;
    }).sort((a, b) => new Date(a.data) - new Date(b.data));

    let currentStreak = 0;
    let bestStreak = 0;
    let tempStreak = 0;
    if (selectedProfileSeasonId === null && typeof activeSeasonId !== 'undefined') {
        selectedProfileSeasonId = activeSeasonId;
    }

    let currAtk = 1500;
    let currDef = 1500;

    // Initial ELO for any season always starts at 1500 visually to avoid carrying over cross-season deltas since server resets them

    const seasonMatches = (selectedProfileSeasonId && selectedProfileSeasonId !== 0)
        ? pMatches.filter(m => m.stagione_id == selectedProfileSeasonId)
        : pMatches;

    const historyDates = [];
    const historyElo = [];
    const historyEloAtk = [];
    const historyEloDef = [];

    // Counters for season-specific stats
    let seasonWinsTot = 0, seasonMatchesTot = 0;
    let seasonWinsAtk = 0, seasonMatchesAtk = 0;
    let seasonWinsDef = 0, seasonMatchesDef = 0;

    // Add starting point for the season
    if (seasonMatches.length > 0) {
        // Find a date slightly before the first match for the "starting" point
        const firstMatchDate = new Date(seasonMatches[0].data);
        const startPointDate = new Date(firstMatchDate.getTime() - 1000 * 60 * 60); // 1 hour before
        historyDates.push(startPointDate.toLocaleDateString());
        historyElo.push(Math.max(currAtk, currDef));
        historyEloAtk.push(currAtk);
        historyEloDef.push(currDef);
    }

    seasonMatches.forEach(m => {
        const isBlue = (m.squadra1_portiere == player.id || m.squadra1_attaccante == player.id);
        const isBlueWin = m.vincitore == 1;
        const won = (isBlue && isBlueWin) || (!isBlue && !isBlueWin);

        // Participation & Win Stats
        seasonMatchesTot++;
        if (won) seasonWinsTot++;

        if (m.squadra1_attaccante == player.id || m.squadra2_attaccante == player.id) {
            seasonMatchesAtk++;
            if (won) seasonWinsAtk++;
        }
        if (m.squadra1_portiere == player.id || m.squadra2_portiere == player.id) {
            seasonMatchesDef++;
            if (won) seasonWinsDef++;
        }

        if (won) {
            if (tempStreak < 0) tempStreak = 0;
            tempStreak++;
        } else {
            if (tempStreak > 0) tempStreak = 0;
            tempStreak--;
        }
        if (tempStreak > bestStreak) bestStreak = tempStreak;

        // Elo Calc for Chart
        let delta = 0;
        let isAtk = false;
        let isDef = false;
        if (m.elo_deltas) {
            let roleKey = '';
            if (m.squadra1_portiere == player.id) { roleKey = 's1p'; isDef = true; }
            else if (m.squadra1_attaccante == player.id) { roleKey = 's1a'; isAtk = true; }
            else if (m.squadra2_portiere == player.id) { roleKey = 's2p'; isDef = true; }
            else if (m.squadra2_attaccante == player.id) { roleKey = 's2a'; isAtk = true; }

            if (roleKey && m.elo_deltas[roleKey] !== undefined) delta = parseInt(m.elo_deltas[roleKey]);
        }
        if (isAtk) currAtk += delta;
        if (isDef) currDef += delta;

        historyDates.push(new Date(m.data).toLocaleDateString());
        historyElo.push(Math.max(currAtk, currDef));
        historyEloAtk.push(currAtk);
        historyEloDef.push(currDef);
    });

    currentStreak = tempStreak;

    // Derived Win Rates
    const seasonWrAtk = seasonMatchesAtk > 0 ? Math.round((seasonWinsAtk / seasonMatchesAtk) * 100) : 0;
    const seasonWrDef = seasonMatchesDef > 0 ? Math.round((seasonWinsDef / seasonMatchesDef) * 100) : 0;

    // 1.5 Advanced Stats
    const advStats = calculateAdvancedStats(player.id, seasonMatches);

    // 2. Render HTML
    const bestElo = Math.max(currAtk, currDef);

    const avtHtml = player.avatar_url
        ? `<img src="${player.avatar_url}" class="w-full h-full object-cover">`
        : `<div class="w-full h-full flex items-center justify-center bg-gray-400 text-white text-4xl font-bold">${player.nome.charAt(0)}</div>`;

    const seasonName = selectedProfileSeasonId == 0 ? "Tutte le stagioni" : (stagioni.find(s => s.id == selectedProfileSeasonId)?.nome || "Stagione");

    let html = `
        <!-- Header Card -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-24 bg-gradient-to-r from-blue-500 to-purple-600 opacity-10"></div>
            <div class="relative flex flex-col items-center mt-4">
                <div class="h-24 w-24 rounded-full border-4 border-white dark:border-gray-800 shadow-xl mb-4 relative">
                    ${player.active_aura ? `<div class="absolute inset-[-8px] rounded-full aura-${player.active_aura} opacity-70 z-0"></div>` : ''}
                    <div class="w-full h-full rounded-full overflow-hidden relative z-10">
                        ${avtHtml}
                    </div>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white">
                    <span data-color="${player.active_name_color || ''}" data-style="${player.active_name_style || ''}">${applyNameStyle(player.nome, player.active_name_style)}</span>
                </h3>
                ${player.active_title ? `<div class="player-title mb-1" data-color="${player.active_name_color || ''}">${player.active_title}</div>` : ''}
                <p class="text-gray-500 dark:text-gray-400 text-sm">@${player.username}</p>
                
                <div class="flex gap-4 mt-6 w-full justify-center">
                    <div class="text-center px-4">
                        <div class="text-xs text-gray-400 uppercase font-bold tracking-wider">Elo</div>
                        <div class="text-xl font-bold text-primary">${bestElo}</div>
                    </div>
                    <div class="text-center px-4 border-l border-gray-100 dark:border-gray-700">
                        <div class="text-xs text-gray-400 uppercase font-bold tracking-wider">Streak Attuale</div>
                        <div class="text-xl font-bold ${currentStreak > 0 ? 'text-green-500' : 'text-red-500'}">${currentStreak > 0 ? '+' : ''}${currentStreak}</div>
                    </div>
                    <div class="text-center px-4 border-l border-gray-100 dark:border-gray-700">
                        <div class="text-xs text-gray-400 uppercase font-bold tracking-wider">Miglior Streak</div>
                        <div class="text-xl font-bold text-purple-500">+${bestStreak}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs Menu -->
        <div class="mt-6 flex gap-2 overflow-x-auto custom-scrollbar pb-2">
            <button onclick="switchProfileTab('overview')" id="tab-btn-overview" class="profile-tab-btn ${activeProfileTab === 'overview' ? 'active bg-blue-500 text-white shadow-md' : 'bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700'} px-4 py-2 rounded-xl text-sm font-bold whitespace-nowrap transition-all">Panoramica</button>
            <button onclick="switchProfileTab('wallet')" id="tab-btn-wallet" class="profile-tab-btn ${activeProfileTab === 'wallet' ? 'active bg-blue-500 text-white shadow-md' : 'bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700'} px-4 py-2 rounded-xl text-sm font-bold whitespace-nowrap transition-all">Portafoglio & Inventario</button>
            <button onclick="switchProfileTab('settings')" id="tab-btn-settings" class="profile-tab-btn ${activeProfileTab === 'settings' ? 'active bg-blue-500 text-white shadow-md' : 'bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700'} px-4 py-2 rounded-xl text-sm font-bold whitespace-nowrap transition-all">Impostazioni</button>
        </div>

        <!-- OVERVIEW TAB -->
        <div id="profile-tab-overview" class="profile-tab-content space-y-6 mt-6 ${activeProfileTab === 'overview' ? 'block' : 'hidden'}">
            
            <!-- Global Season Filter -->
            <div class="bg-indigo-50 dark:bg-indigo-900/10 rounded-2xl p-4 border border-indigo-100 dark:border-indigo-800 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-indigo-500">calendar_month</span>
                    <span class="text-sm font-bold text-indigo-700 dark:text-indigo-300">Statistiche per:</span>
                </div>
                <select onchange="changeProfileSeason(this.value)" class="bg-white dark:bg-gray-800 border border-indigo-200 dark:border-indigo-700 rounded-xl text-sm font-bold px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 text-gray-900 dark:text-white outline-none">
                    <option value="0" ${selectedProfileSeasonId === 0 ? 'selected' : ''}>Tutte le stagioni</option>
                    ${stagioni.map(s => '<option value="' + s.id + '" ' + (s.id == selectedProfileSeasonId ? 'selected' : '') + '>' + s.nome + '</option>').join('')}
                </select>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
                <!-- Elo Attacco -->
                 <div class="bg-white dark:bg-gray-800 p-4 rounded-2xl border border-gray-200 dark:border-gray-700">
                    <div class="text-xs text-gray-400 uppercase font-bold mb-1">Elo Attaccante</div>
                    <div class="text-lg font-bold text-gray-900 dark:text-white">${currAtk}</div>
                </div>
                <!-- Elo Portiere -->
                <div class="bg-white dark:bg-gray-800 p-4 rounded-2xl border border-gray-100 dark:border-gray-700">
                    <div class="text-xs text-gray-400 uppercase font-bold mb-1">Elo Portiere</div>
                    <div class="text-lg font-bold text-gray-900 dark:text-white">${currDef}</div>
                </div>
                <!-- Win Rate Attacco -->
                 <div class="bg-white dark:bg-gray-800 p-4 rounded-2xl border border-gray-100 dark:border-gray-700">
                    <div class="text-xs text-gray-400 uppercase font-bold mb-1">Win Rate Attacco</div>
                    <div class="text-lg font-bold text-gray-900 dark:text-white">${seasonWrAtk}%</div>
                </div>
                 <!-- Win Rate Portiere -->
                 <div class="bg-white dark:bg-gray-800 p-4 rounded-2xl border border-gray-100 dark:border-gray-700">
                    <div class="text-xs text-gray-400 uppercase font-bold mb-1">Win Rate Portiere</div>
                    <div class="text-lg font-bold text-gray-900 dark:text-white">${seasonWrDef}%</div>
                </div>
                 <!-- Partite Totali -->
                 <div class="bg-white dark:bg-gray-800 p-4 rounded-2xl border border-gray-100 dark:border-gray-700">
                    <div class="text-xs text-gray-400 uppercase font-bold mb-1">Partite Totali</div>
                    <div class="text-lg font-bold text-blue-600 dark:text-blue-400">${seasonMatchesTot}</div>
                </div>
                 <!-- Vittorie Totali -->
                 <div class="bg-white dark:bg-gray-800 p-4 rounded-2xl border border-gray-100 dark:border-gray-700">
                    <div class="text-xs text-gray-400 uppercase font-bold mb-1">Vittorie Totali</div>
                    <div class="text-lg font-bold text-green-500 dark:text-green-400">${seasonWinsTot}</div>
                </div>
            </div>

            <!-- Analisi Avanzata -->
            ${advStats.bestTeammate || advStats.nemesis || advStats.vittimaPreferita || advStats.pallaAlPiede ? `
            <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 border border-gray-200 dark:border-gray-700 shadow-sm relative overflow-hidden">
                <!-- Decorative background -->
                <div class="absolute top-0 right-0 -mt-10 -mr-10 w-40 h-40 bg-gradient-to-br from-indigo-500/10 to-purple-500/10 rounded-full blur-3xl"></div>
                
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2 relative z-10">
                    <span class="material-symbols-outlined text-indigo-500">analytics</span>
                    Analisi Avanzata Performance
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 relative z-10">
                    <!-- Compagno Ideale -->
                    ${advStats.bestTeammate ? `
                    <div class="bg-gradient-to-br from-indigo-50 to-blue-50 dark:from-indigo-900/10 dark:to-blue-900/10 p-4 rounded-2xl border border-indigo-100/50 dark:border-indigo-800/30 flex items-center gap-4 transition-transform hover:-translate-y-1 duration-300">
                        <div class="h-12 w-12 rounded-full overflow-hidden border-2 border-indigo-200 dark:border-indigo-700 shadow-sm flex-shrink-0">
                            ${(() => {
                    const p = getPlayerById(advStats.bestTeammate.id);
                    return p?.avatar_url
                        ? '<img src="' + p.avatar_url + '" class="w-full h-full object-cover">'
                        : '<div class="w-full h-full flex items-center justify-center bg-indigo-200 text-indigo-700 font-bold text-lg">' + (p?.nome || '?').charAt(0) + '</div>';
                })()}
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-1 mb-0.5">
                                <span class="material-symbols-outlined text-indigo-500 text-[14px]">handshake</span>
                                <div class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Compagno Ideale</div>
                            </div>
                             <div class="text-sm font-bold text-gray-900 dark:text-white leading-tight">
                                <span data-color="${getPlayerById(advStats.bestTeammate.id)?.active_name_color || ''}">${getPlayerById(advStats.bestTeammate.id).nome}</span>
                            </div>
                            <div class="text-[10px] text-indigo-600 dark:text-indigo-400 font-bold">${Math.round((advStats.bestTeammate.wins / advStats.bestTeammate.matches) * 100)}% Win</div>
                        </div>
                    </div>` : ''}

                    <!-- Vittima Preferita -->
                    ${advStats.vittimaPreferita ? `
                    <div class="bg-gradient-to-br from-emerald-50 to-teal-50 dark:from-emerald-900/10 dark:to-teal-900/10 p-4 rounded-2xl border border-emerald-100/50 dark:border-emerald-800/30 flex items-center gap-4 transition-transform hover:-translate-y-1 duration-300">
                        <div class="h-12 w-12 rounded-full overflow-hidden border-2 border-emerald-200 dark:border-emerald-700 shadow-sm flex-shrink-0">
                            ${(() => {
                    const p = getPlayerById(advStats.vittimaPreferita.id);
                    return p?.avatar_url
                        ? '<img src="' + p.avatar_url + '" class="w-full h-full object-cover">'
                        : '<div class="w-full h-full flex items-center justify-center bg-emerald-200 text-emerald-700 font-bold text-lg">' + (p?.nome || '?').charAt(0) + '</div>';
                })()}
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-1 mb-0.5">
                                <span class="material-symbols-outlined text-emerald-500 text-[14px]">target</span>
                                <div class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Vittima</div>
                            </div>
                             <div class="text-sm font-bold text-gray-900 dark:text-white leading-tight">
                                <span data-color="${getPlayerById(advStats.vittimaPreferita.id)?.active_name_color || ''}">${getPlayerById(advStats.vittimaPreferita.id).nome}</span>
                            </div>
                            <div class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold">${Math.round((advStats.vittimaPreferita.wins / advStats.vittimaPreferita.matches) * 100)}% Win</div>
                        </div>
                    </div>` : ''}
                    
                    <!-- Nemesi -->
                    ${advStats.nemesis ? `
                    <div class="bg-gradient-to-br from-rose-50 to-red-50 dark:from-rose-900/10 dark:to-red-900/10 p-4 rounded-2xl border border-rose-100/50 dark:border-rose-800/30 flex items-center gap-4 transition-transform hover:-translate-y-1 duration-300">
                        <div class="h-12 w-12 rounded-full overflow-hidden border-2 border-rose-200 dark:border-rose-700 shadow-sm flex-shrink-0">
                            ${(() => {
                    const p = getPlayerById(advStats.nemesis.id);
                    return p?.avatar_url
                        ? '<img src="' + p.avatar_url + '" class="w-full h-full object-cover">'
                        : '<div class="w-full h-full flex items-center justify-center bg-rose-200 text-rose-700 font-bold text-lg">' + (p?.nome || '?').charAt(0) + '</div>';
                })()}
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-1 mb-0.5">
                                <span class="material-symbols-outlined text-rose-500 text-[14px]">swords</span>
                                <div class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Nemesi</div>
                            </div>
                             <div class="text-sm font-bold text-gray-900 dark:text-white leading-tight">
                                <span data-color="${getPlayerById(advStats.nemesis.id)?.active_name_color || ''}">${getPlayerById(advStats.nemesis.id).nome}</span>
                            </div>
                            <div class="text-[10px] text-rose-600 dark:text-rose-400 font-bold">${Math.round((advStats.nemesis.losses / advStats.nemesis.matches) * 100)}% Loss</div>
                        </div>
                    </div>` : ''}

                    <!-- Palla al Piede -->
                    ${advStats.pallaAlPiede ? `
                    <div class="bg-gradient-to-br from-orange-50 to-amber-50 dark:from-orange-900/10 dark:to-amber-900/10 p-4 rounded-2xl border border-orange-100/50 dark:border-orange-800/30 flex items-center gap-4 transition-transform hover:-translate-y-1 duration-300">
                        <div class="h-12 w-12 rounded-full overflow-hidden border-2 border-orange-200 dark:border-orange-700 shadow-sm flex-shrink-0">
                            ${(() => {
                    const p = getPlayerById(advStats.pallaAlPiede.id);
                    return p?.avatar_url
                        ? '<img src="' + p.avatar_url + '" class="w-full h-full object-cover">'
                        : '<div class="w-full h-full flex items-center justify-center bg-orange-200 text-orange-700 font-bold text-lg">' + (p?.nome || '?').charAt(0) + '</div>';
                })()}
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-1 mb-0.5">
                                <span class="material-symbols-outlined text-orange-500 text-[14px]">anchor</span>
                                <div class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Palla al Piede</div>
                            </div>
                             <div class="text-sm font-bold text-gray-900 dark:text-white leading-tight">
                                <span data-color="${getPlayerById(advStats.pallaAlPiede.id)?.active_name_color || ''}">${getPlayerById(advStats.pallaAlPiede.id).nome}</span>
                            </div>
                            <div class="text-[10px] text-orange-600 dark:text-orange-400 font-bold">${Math.round((advStats.pallaAlPiede.wins / advStats.pallaAlPiede.matches) * 100)}% Win</div>
                        </div>
                    </div>` : ''}

                    </div>
                </div>
            ` : ''}
            
            <!-- Chart Card -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-100 dark:border-gray-700 h-[400px] flex flex-col">
                <h4 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Andamento ELO</h4>
                <div class="flex-1 w-full min-h-0 relative">
                    <canvas id="profileChart"></canvas>
                </div>
            </div>

            <!-- Activity Heatmap -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm">
                <div class="heatmap-section !mt-0 !pt-0 !border-0">
                    <h4 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider mb-3 flex items-center gap-2">
                        <span class="material-symbols-outlined text-green-500 text-[18px]">calendar_month</span>
                        Attività (Ultimo Anno)
                    </h4>
                    <div class="heatmap-container custom-scrollbar">
                        <div id="profile-heatmap-grid" class="heatmap-grid">
                            <!-- Generated by app.js -->
                        </div>
                    </div>
                    <div class="heatmap-legend">
                        <span>Meno</span>
                        <div class="heatmap-legend-box hv-0"></div>
                        <div class="heatmap-legend-box hv-1"></div>
                        <div class="heatmap-legend-box hv-2"></div>
                        <div class="heatmap-legend-box hv-3"></div>
                        <div class="heatmap-legend-box hv-4"></div>
                        <span>Più</span>
                    </div>
                </div>
            </div>
        </div>


        <!-- WALLET TAB -->
        <div id="profile-tab-wallet" class="profile-tab-content space-y-6 mt-6 ${activeProfileTab === 'wallet' ? 'block' : 'hidden'}">
            <!-- Credits Card -->
            <div id="profileCreditsCard" class="bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-600 dark:to-blue-700 rounded-2xl p-6 text-white shadow-lg relative overflow-hidden border border-blue-400 dark:border-blue-600">
                <div class="absolute top-0 right-0 -mt-4 -mr-4 h-24 w-24 rounded-full bg-white/10 blur-2xl"></div>
                <div class="relative z-10">
                    <p class="text-sm font-medium text-white/90 uppercase tracking-wider mb-1">Il tuo Saldo</p>
                    <h2 class="text-4xl font-bold mb-4"><span id="profileCreditsAmount">---</span> <span class="text-lg font-normal opacity-90">STR</span></h2>
                </div>
            </div>

            <!-- Inventory Section -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden p-6">
                <h3 class="font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-indigo-500">backpack</span>
                    Il tuo Inventario
                </h3>
                
                <div id="inventory-profile-list" class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-2">
                    <div class="col-span-full text-center py-4">
                        <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-500 mx-auto"></div>
                    </div>
                </div>

                <!-- Mobile Shop Button -->
                <button onclick="openShopModal()" class="md:hidden w-full mt-4 bg-gradient-to-r from-yellow-500 to-orange-500 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-yellow-500/20 active:scale-95 flex items-center justify-center gap-2 transition-transform">
                    <span class="material-symbols-outlined">storefront</span>
                    <span>Apri Negozio</span>
                </button>
            </div>

            <!-- Betting History -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden flex flex-col max-h-[400px]">
                <div class="p-4 border-b border-gray-100 dark:border-gray-700 shrink-0">
                    <h3 class="font-bold text-gray-900 dark:text-white">Storico Scommesse</h3>
                </div>
                <div class="overflow-x-auto overflow-y-auto custom-scrollbar flex-1 relative">
                    <table class="w-full text-sm text-left relative">
                        <thead class="text-xs text-gray-500 dark:text-gray-400 uppercase bg-gray-50/95 dark:bg-gray-700/95 backdrop-blur-sm sticky top-0 z-10 shadow-sm">
                            <tr>
                                <th class="px-4 py-3">Data</th>
                                <th class="px-4 py-3">Tipo</th>
                                <th class="px-4 py-3">Importo</th>
                                <th class="px-4 py-3">Quota</th>
                                <th class="px-4 py-3">Esito</th>
                            </tr>
                        </thead>
                        <tbody id="profileBettingHistory" class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">Caricamento...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    `;

    // document.getElementById('profileContent').innerHTML = html; // Removed: will do single update at the end

    // --- Settings & Admin Tab ---
    let settingsHtml = `
        <!-- SETTINGS TAB -->
        <div id="profile-tab-settings" class="profile-tab-content space-y-6 mt-6 hidden">
            <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 border border-gray-200 dark:border-gray-700 shadow-sm">
                <h4 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Modifica Profilo</h4>
                <form onsubmit="updateProfile(event)" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">Nome Visualizzato</label>
                        <input type="text" id="editProfileName" class="w-full bg-gray-50 dark:bg-gray-700 border-0 rounded-xl p-3 text-gray-900 dark:text-white font-bold focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">Avatar</label>
                        <input type="file" id="editProfileAvatar" accept="image/*" class="w-full bg-gray-50 dark:bg-gray-700 border-0 rounded-xl p-3 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        <p class="text-xs text-gray-400 mt-1">Carica un'immagine (JPG, PNG, GIF)</p>
                    </div>
                    <button type="submit" class="w-full py-3 bg-gray-900 dark:bg-white text-white dark:text-gray-900 rounded-xl font-bold hover:opacity-90 transition-opacity">Salva Modifiche</button>
                </form>
            </div>
            
            <div class="mt-6 bg-white dark:bg-gray-800 rounded-3xl p-6 border border-gray-200 dark:border-gray-700 shadow-sm">
                <h4 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Cambia Password</h4>
                <form onsubmit="changePassword(event)" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">Password Attuale</label>
                        <input type="password" id="currentPassword" class="w-full bg-gray-50 dark:bg-gray-700 border-0 rounded-xl p-3 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">Nuova Password</label>
                        <input type="password" id="newPassword" class="w-full bg-gray-50 dark:bg-gray-700 border-0 rounded-xl p-3 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500">
                    </div>
                    <button type="submit" class="w-full py-3 rounded-xl border-2 border-blue-500 text-blue-500 dark:text-blue-400 dark:border-blue-400 font-bold hover:bg-blue-500 hover:text-white dark:hover:bg-blue-400 dark:hover:text-gray-900 transition-all">Aggiorna Password</button>
                </form>
            </div>
    `;

    if (currentUser.is_admin == 1) {
        settingsHtml += `
            <div class="mt-8 bg-red-50 dark:bg-red-900/10 rounded-3xl p-6 border border-red-100 dark:border-red-800/30">
                <h3 class="text-xl font-bold text-red-700 dark:text-red-400 mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined">admin_panel_settings</span>
                    Area Amministrativa
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <button onclick="openModal('createPlayerModal')" class="flex items-center justify-center gap-2 p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-green-300 dark:hover:border-green-700 hover:shadow-md transition-all group col-span-1 md:col-span-2">
                        <span class="material-symbols-outlined text-green-500 group-hover:scale-110 transition-transform">person_add</span>
                        <div class="text-left">
                            <div class="font-bold text-gray-900 dark:text-gray-100">Crea Giocatore / Utente</div>
                            <div class="text-xs text-gray-500">Aggiungi nuovo giocatore al database</div>
                        </div>
                    </button>
                    <button onclick="performBackup()" class="flex items-center justify-center gap-2 p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-700 hover:shadow-md transition-all group">
                        <span class="material-symbols-outlined text-blue-500 group-hover:scale-110 transition-transform">download</span>
                        <div class="text-left">
                            <div class="font-bold text-gray-900 dark:text-gray-100">Backup DB</div>
                            <div class="text-xs text-gray-500">Scarica file SQL</div>
                        </div>
                    </button>
                    <button onclick="confirmAction('Eliminare TUTTE le partite?', 'Irreversibile. Stats resettate.', performDeleteMatches)" class="flex items-center justify-center gap-2 p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-orange-300 dark:hover:border-orange-700 hover:shadow-md transition-all group">
                        <span class="material-symbols-outlined text-orange-500 group-hover:scale-110 transition-transform">history_toggle_off</span>
                        <div class="text-left">
                            <div class="font-bold text-gray-900 dark:text-gray-100">Elimina Partite</div>
                            <div class="text-xs text-gray-500">Reset Statistiche</div>
                        </div>
                    </button>
                    <button onclick="openModal('recalculateEloModal')" class="flex items-center justify-center gap-2 p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-purple-300 dark:hover:border-purple-700 hover:shadow-md transition-all group">
                        <span class="material-symbols-outlined text-purple-500 group-hover:scale-110 transition-transform">autorenew</span>
                        <div class="text-left">
                            <div class="font-bold text-gray-900 dark:text-gray-100">Ricalcola Elo</div>
                            <div class="text-xs text-gray-500">Rielabora storico partite</div>
                        </div>
                    </button>
                    <button onclick="confirmAction('RESETTARE IL DATABASE?', 'Cancella partite e resetta stats. I giocatori non vengono eliminati.', performResetDB)" class="flex items-center justify-center gap-2 p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-red-300 dark:hover:border-red-700 hover:shadow-md transition-all group">
                        <span class="material-symbols-outlined text-red-500 group-hover:scale-110 transition-transform">restart_alt</span>
                        <div class="text-left">
                            <div class="font-bold text-gray-900 dark:text-gray-100">Reset Database</div>
                            <div class="text-xs text-gray-500">Pialla tutto (Mantiene Utenti)</div>
                        </div>
                    </button>
                    <button onclick="confirmAction('CHIUDIRA LA STAGIONE CORRENTE?', 'Salverà tutti i dati nello storico stagioni e resetterà i giocatori. Procedere?', performEndSeason)" class="flex items-center justify-center gap-2 p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-red-500 dark:hover:border-red-700 hover:shadow-md transition-all group text-left">
                        <span class="material-symbols-outlined text-red-500 group-hover:scale-110 transition-transform">event_busy</span>
                        <div class="text-left">
                            <div class="font-bold text-gray-900 dark:text-gray-100">Chiudi Stagione</div>
                            <div class="text-xs text-gray-500">Reset & Storico</div>
                        </div>
                    </button>
                    <button onclick="confirmAction('ELIMINARE TUTTI I GIOCATORI?', 'DANGER: Elimina tutti tranne te e Admin. Reset completo.', performDeletePlayers)" class="flex items-center justify-center gap-2 p-4 bg-red-100 dark:bg-red-900/30 rounded-xl border border-red-200 dark:border-red-800 hover:bg-red-200 dark:hover:bg-red-900/50 transition-all group col-span-1 md:col-span-2 text-left">
                        <span class="material-symbols-outlined text-red-600 dark:text-red-400 group-hover:scale-110 transition-transform">delete_forever</span>
                        <div class="text-left">
                            <div class="font-bold text-red-800 dark:text-red-200">Elimina Tutto (Hard Reset)</div>
                            <div class="text-xs text-red-600 dark:text-red-400">Rimuove anche i giocatori</div>
                        </div>
                    </button>
                </div>
            </div>
        `;
    }

    settingsHtml += `
            <div class="mt-6">
                <button onclick="performLogout()" class="w-full py-3 rounded-xl bg-red-50 dark:bg-red-900/20 text-red-500 dark:text-red-400 font-bold hover:bg-red-100 dark:hover:bg-red-900/30 transition-colors">Esci</button>
            </div>
        </div>
    `;

    // Combine all HTML and inject once
    document.getElementById('profileContent').innerHTML = html + settingsHtml;

    // Render Heatmap (Must be after innerHTML inject)
    renderActivityHeatmap(player.id, pMatches, 'profile-heatmap-grid');

    // 3. Populate Forms
    document.getElementById('editProfileName').value = currentUser.nome;

    // 4. Render Chart (Same as before)
    const ctx = document.getElementById('profileChart');
    if (profileChart) profileChart.destroy();

    profileChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: historyDates,
            datasets: [
                {
                    label: 'Generale',
                    data: historyElo,
                    borderColor: '#8b5cf6', // Violet
                    backgroundColor: 'rgba(139, 92, 246, 0.1)',
                    borderWidth: 3,
                    tension: 0.3,
                    pointRadius: 3,
                    pointHoverRadius: 5
                },
                {
                    label: 'Attacco',
                    data: historyEloAtk,
                    borderColor: '#ef4444', // Red
                    backgroundColor: 'transparent',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    tension: 0.3,
                    pointRadius: 3,
                    pointHoverRadius: 5
                },
                {
                    label: 'Porta',
                    data: historyEloDef,
                    borderColor: '#10b981', // Emerald
                    backgroundColor: 'transparent',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    tension: 0.3,
                    pointRadius: 3,
                    pointHoverRadius: 5
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: true, position: 'bottom' },
                tooltip: { mode: 'index', intersect: false }
            },
            scales: {
                x: { display: false },
                y: { display: true, grid: { color: 'rgba(0,0,0,0.05)' } }
            },
            interaction: {
                mode: 'nearest',
                axis: 'x',
                intersect: false
            }
        }
    });

    // 5. Fetch Credits and Betting History
    fetchProfileBettingData();

    // 6. Load Inventory
    if (window.loadProfileInventory) {
        window.loadProfileInventory();
    }
}

window.switchProfileTab = function (tabId) {
    activeProfileTab = tabId;
    // Hide all tab contents
    const contents = document.querySelectorAll('.profile-tab-content');
    contents.forEach(el => {
        el.classList.remove('block');
        el.classList.add('hidden');
    });

    // De-activate all buttons
    const buttons = document.querySelectorAll('.profile-tab-btn');
    const inactiveClasses = ['bg-white', 'dark:bg-gray-800', 'text-gray-500', 'dark:text-gray-400', 'border', 'border-gray-200', 'dark:border-gray-700', 'hover:bg-gray-50', 'dark:hover:bg-gray-700'];
    const activeClasses = ['bg-blue-500', 'text-white', 'shadow-md', 'active'];

    buttons.forEach(btn => {
        btn.classList.remove(...activeClasses);
        btn.classList.add(...inactiveClasses);
    });

    // Show selected content
    const selectedContent = document.getElementById('profile-tab-' + tabId);
    if (selectedContent) {
        selectedContent.classList.remove('hidden');
        selectedContent.classList.add('block');
    }

    // Activate selected button
    const selectedBtn = document.getElementById('tab-btn-' + tabId);
    if (selectedBtn) {
        selectedBtn.classList.remove(...inactiveClasses);
        selectedBtn.classList.add(...activeClasses);
    }

    // Fix Chart.js rendering when container becomes visible
    if (tabId === 'overview' && typeof profileChart !== 'undefined' && profileChart) {
        setTimeout(() => {
            profileChart.resize();
            profileChart.update();
        }, 50);
    }
};
async function fetchProfileBettingData() {
    try {
        // Fetch credits
        const creditsRes = await fetch('giocatori.php');
        const players = await creditsRes.json();

        if (!Array.isArray(players)) {
            console.warn("fetchProfileBettingData: players is not an array", players);
            return;
        }

        const myPlayer = players.find(p => p.id == currentUser.id);
        if (myPlayer) {
            const creditsEl = document.getElementById('profileCreditsAmount');
            if (creditsEl) creditsEl.textContent = myPlayer.crediti || 0;
        }

        // Fetch betting history
        const historyRes = await fetch('betting_api.php?action=get_history');
        const historyData = await historyRes.json();

        const tbody = document.getElementById('profileBettingHistory');
        if (tbody && historyData.success && historyData.history) {
            if (historyData.history.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">Nessuna scommessa recente</td></tr>';
            } else {
                tbody.innerHTML = historyData.history.map(bet => {
                    const statusColor = bet.status === 'won' ? 'text-green-500 font-bold' :
                        bet.status === 'lost' ? 'text-red-500' : 'text-gray-500';
                    const statusIcon = bet.status === 'won' ? 'check_circle' :
                        bet.status === 'lost' ? 'cancel' : 'pending';

                    const typeLabel = bet.bet_type === 'winner' ?
                        (bet.bet_value == '1' ? 'Vince Blu' : 'Vince Rosso') :
                        (bet.bet_value === 'yes' ? 'Vantaggi: SI' : 'Vantaggi: NO');

                    const date = new Date(bet.created_at);
                    const dateStr = `${date.getDate().toString().padStart(2, '0')}/${(date.getMonth() + 1).toString().padStart(2, '0')} ${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`;

                    return `
                        <tr>
                            <td class="px-4 py-3 text-gray-500">${dateStr}</td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">${typeLabel}</td>
                            <td class="px-4 py-3">${bet.amount} STR</td>
                            <td class="px-4 py-3">${bet.quota}</td>
                            <td class="px-4 py-3 ${statusColor}">
                                <span class="inline-flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">${statusIcon}</span>
                                    ${bet.status === 'won' ? 'Vinta' : (bet.status === 'lost' ? 'Persa' : 'In attesa')}
                                </span>
                            </td>
                        </tr>
                    `;
                }).join('');
            }
        }
    } catch (e) {
        console.error('Error fetching betting data:', e);
    }
}


// --- ADMIN ACTIONS ---

let pendingAction = null;

function confirmAction(title, message, actionFn) {
    const dModal = document.getElementById('deleteMatchModal');
    if (dModal) {
        dModal.querySelector('h3').textContent = title;
        dModal.querySelector('p').textContent = message;

        // Remove old event listeners by cloning
        const oldBtn = document.getElementById('deleteMatchActionBtn');
        const newBtn = oldBtn.cloneNode(true);
        oldBtn.parentNode.replaceChild(newBtn, oldBtn);

        newBtn.onclick = () => {
            actionFn();
            dModal.classList.add('hidden'); // Fix: manually hide
        };

        // Also bind the cancel button to hide it
        const cancelBtn = dModal.querySelectorAll('button')[0];
        if (cancelBtn) {
            cancelBtn.onclick = () => dModal.classList.add('hidden');
        }

        dModal.classList.remove('hidden'); // Fix: manually show instead of openModal
    } else {
        if (confirm(title + "\\n" + message)) {
            actionFn();
        }
    }
}

async function performBackup() {
    // Initial fetch to trigger download
    window.location.href = 'admin_api.php?action=backup_db';
}

async function performResetDB() {
    try {
        const res = await fetch('admin_api.php', {
            method: 'POST',
            body: JSON.stringify({ action: 'reset_db' }),
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await res.json();
        if (data.success) {
            showToast('Database resettato con successo!', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('Errore: ' + (data.error || 'Impossibile resettare'), 'error');
        }
    } catch (e) {
        showToast('Errore di Connessione', 'error');
    }
}

async function performDeleteMatches() {
    try {
        const res = await fetch('admin_api.php', {
            method: 'POST',
            body: JSON.stringify({ action: 'delete_all_matches' }),
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            loadData();
        } else {
            showToast(data.error, 'error');
        }
    } catch (e) {
        showToast('Errore di connessione', 'error');
    }
}

async function performDeletePlayers() {
    try {
        const res = await fetch('admin_api.php', {
            method: 'POST',
            body: JSON.stringify({ action: 'delete_all_players' }),
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            // Force logout if we deleted ourselves (shouldn't happen due to backend check)
            // But reload is good
            window.location.reload();
        } else {
            showToast(data.error, 'error');
        }
    } catch (e) {
        showToast('Errore di connessione', 'error');
    }
}

async function performRecalculateElo() {
    openModal('recalculateEloModal');
}

async function executeRecalculateElo() {
    const btn = document.getElementById('recalculateEloConfirmBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-outlined animate-spin text-base">autorenew</span> In corso...';
    }

    try {
        const res = await fetch('admin_api.php', {
            method: 'POST',
            body: JSON.stringify({ action: 'recalculate_elo' }),
            headers: { 'Content-Type': 'application/json' }
        });
        const text = await res.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (parseErr) {
            console.error('JSON parse error, raw response:', text);
            showToast('Errore: risposta non valida dal server', 'error');
            return;
        }
        if (data.success) {
            closeModal('recalculateEloModal');
            showToast(data.message, 'success');
            loadData();
        } else {
            showToast('Errore: ' + (data.error || 'Errore sconosciuto'), 'error');
        }
    } catch (e) {
        console.error('Fetch error:', e);
        showToast('Errore di connessione durante il ricalcolo', 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = 'Ricalcola';
        }
    }
}

async function performCreatePlayer(e) {
    if (e) e.preventDefault();

    const btn = document.getElementById('createPlayerSubmitBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-outlined animate-spin text-base">autorenew</span> Creazione...';
    }

    const nome = document.getElementById('newPlayerName').value.trim();
    const username = document.getElementById('newPlayerUsername').value.trim();
    const password = document.getElementById('newPlayerPassword').value.trim();
    const isAdmin = document.getElementById('newPlayerAdmin').checked ? 1 : 0;

    if (!nome) {
        showToast("Il nome giocatore è obbligatorio", "error");
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = 'Crea Giocatore / Utente';
        }
        return;
    }

    try {
        const res = await fetch('admin_api.php', {
            method: 'POST',
            body: JSON.stringify({
                action: 'create_player',
                nome: nome,
                username: username,
                password: password,
                is_admin: isAdmin
            }),
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            closeModal('createPlayerModal');
            document.getElementById('createPlayerForm').reset();
            loadData(); // Reloads players and updates the UI
        } else {
            showToast('Errore: ' + (data.error || 'Errore sconosciuto'), 'error');
        }
    } catch (err) {
        console.error('Fetch error:', err);
        showToast('Errore di connessione durante la creazione', 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = 'Crea Giocatore / Utente';
        }
    }
}


async function updateProfile(e) {
    e.preventDefault();
    const nome = document.getElementById('editProfileName').value;
    const avatarFile = document.getElementById('editProfileAvatar').files[0];

    const formData = new FormData();
    formData.append('nome', nome);
    if (avatarFile) {
        formData.append('avatar_file', avatarFile);
    }
    // Also support fallback URL if needed (but UI is changing to file)

    try {
        const res = await fetch('auth.php?action=update_profile', {
            method: 'POST',
            body: formData
            // Do NOT set Content-Type header with FormData, let browser set boundary
        });
        const data = await res.json();

        if (data.success) {
            showToast('Profilo aggiornato!', 'success');
            // Update local state
            currentUser.nome = data.user.nome;
            if (data.user.avatar_url) currentUser.avatar_url = data.user.avatar_url;

            updateAuthUI();
            renderProfile(); // Re-render to show changes

            // Clear file input
            document.getElementById('editProfileAvatar').value = '';
        } else {
            showToast(data.error, 'error');
        }
    } catch (e) {
        showToast('Errore durante l\'aggiornamento', 'error');
    }
}

async function changePassword(e) {
    e.preventDefault();
    const currentPass = document.getElementById('currentPassword').value;
    const newPass = document.getElementById('newPassword').value;

    if (!currentPass || !newPass) {
        showToast('Compila tutti i campi', 'error');
        return;
    }

    try {
        const res = await fetch('auth.php?action=change_password', {
            method: 'POST',
            body: JSON.stringify({ current_password: currentPass, new_password: newPass }),
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await res.json();

        if (data.success) {
            showToast('Password aggiornata!', 'success');
            e.target.reset();
        } else {
            showToast(data.error, 'error');
        }
    } catch (e) {
        showToast('Errore durante il cambio password', 'error');
    }
}

// --- MATCH DETAILS MODAL ---
let currentMatchId = null;

// Global renderAvatar helper
const renderAvatar = (name, avatar, bgClass = 'bg-gray-400', textClass = 'text-white') => {
    if (avatar) {
        return `<img src="${avatar}" alt="${name}" class="w-full h-full object-cover rounded-full" title="${name}">`;
    }
    return `<div class="w-full h-full rounded-full ${bgClass} flex items-center justify-center text-xs font-bold ${textClass}" title="${name}">${name.substr(0, 1)}</div>`;
};

// --- MATCH DETAILS MODAL ---

function openMatchDetails(matchId) {
    const match = partite.find(m => m.id == matchId); // Changed allMatches to partite
    if (!match) return;

    currentMatchId = matchId;
    const modal = document.getElementById('matchDetailsModal');

    // Set Date & Season
    const date = new Date(match.data);
    document.getElementById('matchDetailsDate').textContent = date.toLocaleString('it-IT', {
        weekday: 'short', year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
    });

    const seasonObj = stagioni.find(s => s.id == match.stagione_id);
    document.getElementById('matchDetailsSeason').textContent = seasonObj ? seasonObj.nome : 'N/A';

    // Set Result Banner
    const isBlueWin = match.vincitore == 1;
    const resDiv = document.getElementById('matchDetailsResult');
    resDiv.className = `text-center font-bold text-lg py-2 rounded-xl mb-6 ${isBlueWin ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'}`;

    if (match.score_s1 !== null && match.score_s2 !== null && match.score_s1 !== undefined) {
        resDiv.textContent = `${isBlueWin ? 'Vittoria Blu' : 'Vittoria Rossi'} (${match.score_s1} - ${match.score_s2})`;
        document.getElementById('matchDetailsScoreBlue').textContent = match.score_s1;
        document.getElementById('matchDetailsScoreRed').textContent = match.score_s2;
    } else {
        resDiv.textContent = isBlueWin ? 'Vittoria Blu' : 'Vittoria Rossi';
        document.getElementById('matchDetailsScoreBlue').textContent = '';
        document.getElementById('matchDetailsScoreRed').textContent = '';
    }

    // Bounty feature is not retroactive, so we never show it on past match details
    const bountyBanner = document.getElementById('md-bounty-banner');
    if (bountyBanner) {
        bountyBanner.classList.add('hidden');
    }

    // Helper for Player Row
    const setPlayer = (prefix, name, avatar, delta, color, style) => {
        const nameEl = document.getElementById(`md-${prefix}-name`);
        nameEl.textContent = name || 'Sconosciuto';
        nameEl.setAttribute('data-color', color || '');
        nameEl.setAttribute('data-style', style || '');

        document.getElementById(`md-${prefix}-avatar`).innerHTML = renderAvatar(name || '?', avatar, 'bg-gray-400', 'text-white');

        const deltaSpan = document.getElementById(`md-${prefix}-delta`);
        if (delta !== null && delta !== undefined) {
            const val = parseInt(delta);
            deltaSpan.textContent = (val > 0 ? '+' : '') + val;
            deltaSpan.className = `font-mono font-bold text-sm ${val > 0 ? 'text-green-500' : (val < 0 ? 'text-red-500' : 'text-gray-400')}`;
        } else {
            deltaSpan.textContent = '-';
            deltaSpan.className = 'font-mono font-bold text-sm text-gray-400';
        }
    };

    // Populate Players
    const ng = match.nomi_giocatori;
    const deltas = match.elo_deltas || {};

    setPlayer('s1p', ng.squadra1_portiere, ng.avatar_s1p, deltas.s1p, ng.color_s1p, ng.style_s1p);
    setPlayer('s1a', ng.squadra1_attaccante, ng.avatar_s1a, deltas.s1a, ng.color_s1a, ng.style_s1a);
    setPlayer('s2p', ng.squadra2_portiere, ng.avatar_s2p, deltas.s2p, ng.color_s2p, ng.style_s2p);
    setPlayer('s2a', ng.squadra2_attaccante, ng.avatar_s2a, deltas.s2a, ng.color_s2a, ng.style_s2a);

    // Populate Bonuses
    const bonusSection = document.getElementById('matchDetailsBonuses');
    const bonusList = document.getElementById('md-bonuses-list');
    if (bonusSection && bonusList) {
        if (match.bonuses_used) {
            bonusSection.classList.remove('hidden');
            bonusList.innerHTML = match.bonuses_used.split(',').map(b => `
                <div class="px-2 py-1 rounded-lg bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300 text-[10px] font-bold flex items-center gap-1">
                    <span class="material-symbols-outlined text-[12px]">rocket_launch</span>
                    ${b.trim()}
                </div>
            `).join('');
        } else {
            bonusSection.classList.add('hidden');
            bonusList.innerHTML = '';
        }
    }

    // Admin Check
    const deleteBtn = document.getElementById('deleteMatchAction');
    if (deleteBtn) {
        if (currentUser && currentUser.is_admin == 1) {
            deleteBtn.classList.remove('hidden');
        } else {
            deleteBtn.classList.add('hidden');
        }
    }

    openModal('matchDetailsModal');
}

function closeMatchDetailsModal(event) {
    if (event && event.target !== event.currentTarget) return; // Allow closing when clicking backdrop
    closeModal('matchDetailsModal');
    currentMatchId = null;
}

function deleteCurrentMatch() {
    if (!currentMatchId) return;
    // Show custom modal instead of native confirm
    openModal('deleteMatchModal');
}

function closeDeleteMatchModal() {
    closeModal('deleteMatchModal');
}

function confirmDeleteMatch() {
    if (!currentMatchId) return;

    // Disable button to prevent double submit (optional but good practice)
    // For now just proceed with fetch

    fetch('admin_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete_match', id: currentMatchId })
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeDeleteMatchModal();
                closeMatchDetailsModal();
                loadData(); // Reload everything
            } else {
                showToast('Errore: ' + (data.error || 'Errore sconosciuto'), 'error');
                closeDeleteMatchModal();
            }
        })
        .catch(err => {
            showToast('Errore di rete', 'error');
            closeDeleteMatchModal();
        });
}

// --- HTML GENERATORS ---

function createMatchHTML(match, isChat = false) {
    const p1 = { name: match.nomi_giocatori.squadra1_portiere || '?', avatar: match.nomi_giocatori.avatar_s1p, color: match.nomi_giocatori.color_s1p, style: match.nomi_giocatori.style_s1p, aura: match.nomi_giocatori.aura_s1p };
    const p2 = { name: match.nomi_giocatori.squadra1_attaccante || '?', avatar: match.nomi_giocatori.avatar_s1a, color: match.nomi_giocatori.color_s1a, style: match.nomi_giocatori.style_s1a, aura: match.nomi_giocatori.aura_s1a };
    const p3 = { name: match.nomi_giocatori.squadra2_portiere || '?', avatar: match.nomi_giocatori.avatar_s2p, color: match.nomi_giocatori.color_s2p, style: match.nomi_giocatori.style_s2p, aura: match.nomi_giocatori.aura_s2p };
    const p4 = { name: match.nomi_giocatori.squadra2_attaccante || '?', avatar: match.nomi_giocatori.avatar_s2a, color: match.nomi_giocatori.color_s2a, style: match.nomi_giocatori.style_s2a, aura: match.nomi_giocatori.aura_s2a };

    const isBlueWin = match.vincitore == 1;
    const timeAgo = timeSince(new Date(match.data));

    const hoverClasses = isChat ? '' : 'xl:cursor-pointer transition-all duration-300 xl:hover:shadow-xl xl:hover:scale-[1.02] active:scale-95 group/card';
    const clickHandler = isChat ? '' : `onclick="openMatchDetails(${match.id})"`;

    // Helper for bonus icons
    const getBonusIcons = (userId) => {
        if (!match.bonuses || !Array.isArray(match.bonuses)) return '';
        const userBonuses = match.bonuses.filter(b => b.user_id == userId);
        if (!userBonuses.length) return '';

        return userBonuses.map(b => `
            <span class="material-symbols-outlined text-[10px] text-yellow-500 bg-yellow-400/10 rounded-full p-0.5 ml-0.5 align-middle" title="Bonus utilizzato">
                ${b.icon || 'star'}
            </span>
        `).join('');
    };

    return `
    <div ${clickHandler} class="match-card-history relative p-0 rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden flex flex-col ${hoverClasses}">
        <!-- Top Info Bar -->
        <div class="flex justify-between items-center px-4 py-1.5 bg-gray-50/50 dark:bg-gray-900/40 border-b border-gray-100 dark:border-gray-700/50">
            <div class="flex items-center gap-1.5">
                <span class="text-[9px] font-black uppercase tracking-widest text-gray-400">Scheda Partita</span>
            </div>
            <span class="text-[10px] font-bold text-gray-500">${timeAgo}</span>
        </div>

        <div class="flex items-stretch min-h-[110px] relative">
            <!-- Team Blue -->
            <div class="flex-1 flex flex-col items-center justify-center p-4 transition-colors relative z-0 ${isBlueWin ? 'bg-blue-500/10 dark:bg-blue-500/20' : ''}">
                ${isBlueWin ? '<div class="absolute inset-0 border-l-4 border-blue-500/50"></div>' : ''}
                <div class="flex gap-1 mb-2 relative z-10">
                    <div class="relative shrink-0">
                        ${p1.aura ? `<div class="absolute inset-[-5px] rounded-full aura-${p1.aura} opacity-70 z-0"></div>` : ''}
                        <div class="h-10 w-10 rounded-full border-2 border-white dark:border-gray-700 overflow-hidden relative z-10">
                            ${renderAvatar(p1.name, p1.avatar, 'bg-blue-100 dark:bg-blue-900', 'text-blue-600 dark:text-blue-300')}
                        </div>
                    </div>
                    <div class="relative shrink-0">
                        ${p2.aura ? `<div class="absolute inset-[-5px] rounded-full aura-${p2.aura} opacity-70 z-0"></div>` : ''}
                        <div class="h-10 w-10 rounded-full border-2 border-white dark:border-gray-700 overflow-hidden relative z-10">
                            ${renderAvatar(p2.name, p2.avatar, 'bg-blue-100 dark:bg-blue-900', 'text-blue-600 dark:text-blue-300')}
                        </div>
                    </div>
                </div>
                <div class="text-center min-w-0 w-full px-1 relative z-10">
                    <div class="text-[11px] font-bold text-blue-700 dark:text-blue-400 truncate flex items-center justify-center gap-0.5">
                        <span data-color="${p1.color || ''}" data-style="${p1.style || ''}">${applyNameStyle(p1.name, p1.style)}</span> ${getBonusIcons(match.squadra1_portiere)}
                    </div>
                    <div class="text-[11px] font-bold text-blue-800 dark:text-blue-300 truncate flex items-center justify-center gap-0.5">
                        <span data-color="${p2.color || ''}" data-style="${p2.style || ''}">${applyNameStyle(p2.name, p2.style)}</span> ${getBonusIcons(match.squadra1_attaccante)}
                    </div>
                </div>
            </div>

            <!-- VS Divider -->
            <div class="relative flex flex-col items-center justify-center w-0 overflow-visible z-10">
                <div class="absolute inset-y-0 w-px bg-gray-100 dark:bg-gray-700 shadow-[0_0_10px_rgba(0,0,0,0.05)]"></div>
                <div class="relative min-w-[36px] min-h-[36px] px-2 py-1 rounded-full bg-white dark:bg-gray-800 border-2 border-gray-100 dark:border-gray-700 shadow-sm flex items-center justify-center transition-transform xl:group-hover/card:scale-110 whitespace-nowrap">
                    ${(match.score_s1 !== null && match.score_s2 !== null && match.score_s1 !== undefined)
            ? `<span class="text-[11px] md:text-sm font-black text-gray-800 dark:text-gray-200 tracking-tighter">${match.score_s1} - ${match.score_s2}</span>`
            : `<span class="text-[10px] font-black text-gray-400 italic px-1">VS</span>`
        }
                </div>
                
                <!-- Win Indicator Icons -->
                ${isBlueWin
            ? `<div class="absolute -left-10 top-1/2 -translate-y-1/2 flex items-center z-20">
                        <span class="material-symbols-outlined text-blue-500 text-xl drop-shadow-sm">emoji_events</span>
                       </div>`
            : `<div class="absolute -right-10 top-1/2 -translate-y-1/2 flex items-center z-20">
                        <span class="material-symbols-outlined text-red-500 text-xl drop-shadow-sm">emoji_events</span>
                       </div>`
        }
            </div>

            <!-- Team Red -->
            <div class="flex-1 flex flex-col items-center justify-center p-4 transition-colors relative z-0 ${!isBlueWin ? 'bg-red-500/10 dark:bg-red-500/20' : ''}">
                ${!isBlueWin ? '<div class="absolute inset-0 border-r-4 border-red-500/50"></div>' : ''}
                <div class="flex gap-1 mb-2 relative z-10">
                    <div class="relative shrink-0">
                        ${p3.aura ? `<div class="absolute inset-[-5px] rounded-full aura-${p3.aura} opacity-70 z-0"></div>` : ''}
                        <div class="h-10 w-10 rounded-full border-2 border-white dark:border-gray-700 overflow-hidden relative z-10">
                            ${renderAvatar(p3.name, p3.avatar, 'bg-red-100 dark:bg-red-900', 'text-red-600 dark:text-red-300')}
                        </div>
                    </div>
                    <div class="relative shrink-0">
                        ${p4.aura ? `<div class="absolute inset-[-5px] rounded-full aura-${p4.aura} opacity-70 z-0"></div>` : ''}
                        <div class="h-10 w-10 rounded-full border-2 border-white dark:border-gray-700 overflow-hidden relative z-10">
                            ${renderAvatar(p4.name, p4.avatar, 'bg-red-100 dark:bg-red-900', 'text-red-600 dark:text-red-300')}
                        </div>
                    </div>
                </div>
                <div class="text-center min-w-0 w-full px-1 relative z-10">
                    <div class="text-[11px] font-bold text-red-700 dark:text-red-400 truncate flex items-center justify-center gap-0.5">
                        <span data-color="${p3.color || ''}" data-style="${p3.style || ''}">${applyNameStyle(p3.name, p3.style)}</span> ${getBonusIcons(match.squadra2_portiere)}
                    </div>
                    <div class="text-[11px] font-bold text-red-800 dark:text-blue-300 truncate flex items-center justify-center gap-0.5">
                        <span data-color="${p4.color || ''}" data-style="${p4.style || ''}">${applyNameStyle(p4.name, p4.style)}</span> ${getBonusIcons(match.squadra2_attaccante)}
                    </div>
                </div>
            </div>
        </div>

        <!-- Progress Indicator (Winner Color) -->
        <div class="h-1 w-full mt-auto ${isBlueWin ? 'bg-blue-500' : 'bg-red-500'} opacity-20"></div>
    </div>
    `;
}

// ... (openPlayerDetails logic remains same, we updated it above) ...
// We need to jump to createLeaderboardItem



// --- PLAYER DETAILS MODAL ---
let playerChart = null;
let currentPlayerModalId = null;
let selectedPlayerModalSeasonId = null;

function changePlayerModalSeason(seasonId) {
    selectedPlayerModalSeasonId = parseInt(seasonId);
    if (currentPlayerModalId) {
        openPlayerDetails(currentPlayerModalId);
    }
}
window.changePlayerModalSeason = changePlayerModalSeason;

function openPlayerDetails(playerId) {
    const player = getPlayerById(playerId);
    if (!player) return;

    // Toggle Logout Button (for mobile users)
    const logoutContainer = document.getElementById('pd-logout-container');
    if (logoutContainer) {
        if (currentUser && playerId == currentUser.id) {
            logoutContainer.classList.remove('hidden');
        } else {
            logoutContainer.classList.add('hidden');
        }
    }

    // 1. Populate Info
    const pdName = document.getElementById('pd-name');
    pdName.innerHTML = `<span data-color="${player.active_name_color || ''}" data-style="${player.active_name_style || ''}">${applyNameStyle(player.nome, player.active_name_style)}</span>`;

    const pdTitle = document.getElementById('pd-title');
    if (pdTitle) {
        pdTitle.innerText = player.active_title || '';
        pdTitle.setAttribute('data-color', player.active_name_color || '');
    }

    safeSetText('pd-username', '@' + (player.username || 'sconosciuto'));
    const avtContainer = document.getElementById('pd-avatar-container');
    const avtParent = avtContainer.parentElement; // The h-24 w-24 container

    // Set Aura in Modal
    const pdAura = document.getElementById('pd-aura');
    if (pdAura) {
        if (player.active_aura) {
            pdAura.className = `absolute inset-[-8px] rounded-full aura-${player.active_aura} opacity-70 z-0`;
            pdAura.classList.remove('hidden');
        } else {
            pdAura.classList.add('hidden');
        }
    }

    if (player.avatar_url) {
        avtContainer.innerHTML = `<img src="${player.avatar_url}" class="w-full h-full object-cover">`;
    } else {
        avtContainer.innerHTML = `<div class="w-full h-full flex items-center justify-center bg-gray-400 text-white text-3xl font-bold">${player.nome.charAt(0)}</div>`;
    }

    // 2. Setup Base Variables
    currentPlayerModalId = playerId;
    if (selectedPlayerModalSeasonId === null && typeof activeSeasonId !== 'undefined') {
        selectedPlayerModalSeasonId = activeSeasonId;
    }

    // Populate Season Dropdown
    const seasonSelect = document.getElementById('playerModalSeasonSelect');
    if (seasonSelect) {
        seasonSelect.innerHTML = `
            <option value="0" ${selectedPlayerModalSeasonId === 0 ? 'selected' : ''}>Tutte le stagioni</option>
            ${stagioni.map(s => `<option value="${s.id}" ${s.id == selectedPlayerModalSeasonId ? 'selected' : ''}>${s.nome}</option>`).join('')}
        `;
    }

    const pMatches = partite.filter(m => {
        return m.squadra1_portiere == playerId || m.squadra1_attaccante == playerId ||
            m.squadra2_portiere == playerId || m.squadra2_attaccante == playerId;
    }).sort((a, b) => new Date(a.data) - new Date(b.data)); // Oldest first

    const seasonMatches = (selectedPlayerModalSeasonId && selectedPlayerModalSeasonId !== 0)
        ? pMatches.filter(m => m.stagione_id == selectedPlayerModalSeasonId)
        : pMatches;

    let currentStreak = 0;
    let bestStreak = 0;
    let worstStreak = 0;
    let tempStreak = 0;

    // Stats tracking for dynamic update
    let seasonWinsAtk = 0, seasonMatchesAtk = 0;
    let seasonWinsDef = 0, seasonMatchesDef = 0;

    // History for Chart
    const labels = [];
    const dataGeneral = [];
    const dataAtk = [];
    const dataDef = [];

    // Reconstruction: Start from initial Elo
    let currAtk = 1500;
    let currDef = 1500;

    // Add starting point
    if (seasonMatches.length > 0) {
        const firstMatchDate = new Date(seasonMatches[0].data);
        const startPointDate = new Date(firstMatchDate.getTime() - 1000 * 60 * 60);
        labels.push(startPointDate.toLocaleDateString());
        dataGeneral.push(Math.max(currAtk, currDef));
        dataAtk.push(currAtk);
        dataDef.push(currDef);
    }

    seasonMatches.forEach(m => {
        // Determine if won (for streak, already calculated above, but we need iteration for chart)
        const isBlue = (m.squadra1_portiere == playerId || m.squadra1_attaccante == playerId);
        const isBlueWin = m.vincitore == 1;
        const won = (isBlue && isBlueWin) || (!isBlue && !isBlueWin);

        // Streak & Win Rates
        if (won) {
            if (tempStreak < 0) tempStreak = 0;
            tempStreak++;
        } else {
            if (tempStreak > 0) tempStreak = 0;
            tempStreak--;
        }
        if (tempStreak > bestStreak) bestStreak = tempStreak;
        if (tempStreak < worstStreak) worstStreak = tempStreak;

        if (m.squadra1_attaccante == playerId || m.squadra2_attaccante == playerId) {
            seasonMatchesAtk++;
            if (won) seasonWinsAtk++;
        }
        if (m.squadra1_portiere == playerId || m.squadra2_portiere == playerId) {
            seasonMatchesDef++;
            if (won) seasonWinsDef++;
        }

        // Elo Update
        let delta = 0;
        let isAtk = false;
        let isDef = false;

        if (m.elo_deltas) {
            let roleKey = '';
            // Determine Role
            if (m.squadra1_portiere == playerId) { roleKey = 's1p'; isDef = true; }
            else if (m.squadra1_attaccante == playerId) { roleKey = 's1a'; isAtk = true; }
            else if (m.squadra2_portiere == playerId) { roleKey = 's2p'; isDef = true; }
            else if (m.squadra2_attaccante == playerId) { roleKey = 's2a'; isAtk = true; }

            if (roleKey && m.elo_deltas[roleKey] !== undefined) {
                delta = parseInt(m.elo_deltas[roleKey]);
            }
        }

        if (isAtk) currAtk += delta;
        if (isDef) currDef += delta;

        // General Elo (Max)
        let currGen = Math.max(currAtk, currDef);

        labels.push(new Date(m.data).toLocaleDateString());
        dataGeneral.push(currGen);
        dataAtk.push(currAtk);
        dataDef.push(currDef);
    });

    // Final calculations
    currentStreak = tempStreak; // Last tempStreak is current

    // Render Stats
    safeSetText('pd-elo-atk', currAtk);
    safeSetText('pd-elo-def', currDef);

    const wrAtk = seasonMatchesAtk > 0 ? Math.round((seasonWinsAtk / seasonMatchesAtk) * 100) : 0;
    const wrDef = seasonMatchesDef > 0 ? Math.round((seasonWinsDef / seasonMatchesDef) * 100) : 0;
    safeSetText('pd-winrate-atk', wrAtk + '% Vittorie');
    safeSetText('pd-winrate-def', wrDef + '% Vittorie');

    const fmtStreak = (s) => s > 0 ? `+${s}` : (s < 0 ? `${s}` : '0');
    safeSetText('pd-streak-current', fmtStreak(currentStreak));
    document.getElementById('pd-streak-current').className = `font-bold text-lg ${currentStreak > 0 ? 'text-green-500' : (currentStreak < 0 ? 'text-red-500' : 'text-gray-900 dark:text-white')}`;

    safeSetText('pd-streak-best', `+${bestStreak}`);
    safeSetText('pd-streak-worst', `${worstStreak}`);

    // --- Advanced Stats ---
    const advStats = calculateAdvancedStats(playerId, seasonMatches);
    const advStatsContainer = document.getElementById('pd-advanced-stats-container');
    const advStatsContent = document.getElementById('pd-advanced-stats');

    if (advStats.bestTeammate || advStats.nemesis || advStats.vittimaPreferita || advStats.pallaAlPiede) {
        advStatsContainer.classList.remove('hidden');
        advStatsContent.innerHTML = `
            ${advStats.bestTeammate ? `
            <div class="bg-gradient-to-br from-indigo-50 to-blue-50 dark:from-indigo-900/10 dark:to-blue-900/10 p-3 rounded-xl border border-indigo-100/50 dark:border-indigo-800/30 flex items-center gap-3 transition-transform hover:-translate-y-1 duration-300">
                <div class="h-10 w-10 rounded-full overflow-hidden border-2 border-indigo-200 dark:border-indigo-700 shadow-sm flex-shrink-0">
                    ${(() => {
                    const p = getPlayerById(advStats.bestTeammate.id);
                    return p?.avatar_url
                        ? `<img src="${p.avatar_url}" class="w-full h-full object-cover">`
                        : `<div class="w-full h-full flex items-center justify-center bg-indigo-200 text-indigo-700 font-bold text-xs">${(p?.nome || '?').charAt(0)}</div>`;
                })()}
                </div>
                <div class="flex-1 min-w-0">
                     <div class="flex items-center gap-1 mb-0.5">
                        <span class="material-symbols-outlined text-indigo-500 text-[10px]">handshake</span>
                        <div class="text-[8px] text-gray-500 dark:text-gray-400 uppercase font-bold tracking-wider truncate">Compagno Ideale</div>
                    </div>
                    <div class="text-xs font-bold text-gray-900 dark:text-white leading-tight truncate">
                        <span data-color="${getPlayerById(advStats.bestTeammate.id)?.active_name_color || ''}">${getPlayerById(advStats.bestTeammate.id).nome}</span>
                    </div>
                    <div class="text-[9px] text-indigo-600 dark:text-indigo-400 font-bold">${Math.round((advStats.bestTeammate.wins / advStats.bestTeammate.matches) * 100)}% Win</div>
                </div>
            </div>` : ''}

            ${advStats.vittimaPreferita ? `
            <div class="bg-gradient-to-br from-emerald-50 to-teal-50 dark:from-emerald-900/10 dark:to-teal-900/10 p-3 rounded-xl border border-emerald-100/50 dark:border-emerald-800/30 flex items-center gap-3 transition-transform hover:-translate-y-1 duration-300">
                <div class="h-10 w-10 rounded-full overflow-hidden border-2 border-emerald-200 dark:border-emerald-700 shadow-sm flex-shrink-0">
                    ${(() => {
                    const p = getPlayerById(advStats.vittimaPreferita.id);
                    return p?.avatar_url
                        ? `<img src="${p.avatar_url}" class="w-full h-full object-cover">`
                        : `<div class="w-full h-full flex items-center justify-center bg-emerald-200 text-emerald-700 font-bold text-xs">${(p?.nome || '?').charAt(0)}</div>`;
                })()}
                </div>
                <div class="flex-1 min-w-0">
                     <div class="flex items-center gap-1 mb-0.5">
                        <span class="material-symbols-outlined text-emerald-500 text-[10px]">target</span>
                        <div class="text-[8px] text-gray-500 dark:text-gray-400 uppercase font-bold tracking-wider truncate">Vittima</div>
                    </div>
                    <div class="text-xs font-bold text-gray-900 dark:text-white leading-tight truncate">
                        <span data-color="${getPlayerById(advStats.vittimaPreferita.id)?.active_name_color || ''}">${getPlayerById(advStats.vittimaPreferita.id).nome}</span>
                    </div>
                    <div class="text-[9px] text-emerald-600 dark:text-emerald-400 font-bold">${Math.round((advStats.vittimaPreferita.wins / advStats.vittimaPreferita.matches) * 100)}% Win</div>
                </div>
            </div>` : ''}
            
            ${advStats.nemesis ? `
            <div class="bg-gradient-to-br from-rose-50 to-red-50 dark:from-rose-900/10 dark:to-red-900/10 p-3 rounded-xl border border-rose-100/50 dark:border-rose-800/30 flex items-center gap-3 transition-transform hover:-translate-y-1 duration-300">
                <div class="h-10 w-10 rounded-full overflow-hidden border-2 border-rose-200 dark:border-rose-700 shadow-sm flex-shrink-0">
                    ${(() => {
                    const p = getPlayerById(advStats.nemesis.id);
                    return p?.avatar_url
                        ? `<img src="${p.avatar_url}" class="w-full h-full object-cover">`
                        : `<div class="w-full h-full flex items-center justify-center bg-rose-200 text-rose-700 font-bold text-xs">${(p?.nome || '?').charAt(0)}</div>`;
                })()}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-1.5 mb-0.5">
                        <span class="material-symbols-outlined text-rose-500 text-[10px]">swords</span>
                        <div class="text-[8px] text-gray-500 dark:text-gray-400 uppercase font-bold tracking-wider truncate">Nemesi</div>
                    </div>
                    <div class="text-xs font-bold text-gray-900 dark:text-white leading-tight truncate">
                        <span data-color="${getPlayerById(advStats.nemesis.id)?.active_name_color || ''}">${getPlayerById(advStats.nemesis.id).nome}</span>
                    </div>
                    <div class="text-[9px] text-rose-600 dark:text-rose-400 font-bold">${Math.round((advStats.nemesis.losses / advStats.nemesis.matches) * 100)}% Loss</div>
                </div>
            </div>` : ''}

            ${advStats.pallaAlPiede ? `
            <div class="bg-gradient-to-br from-orange-50 to-amber-50 dark:from-orange-900/10 dark:to-amber-900/10 p-3 rounded-xl border border-orange-100/50 dark:border-orange-800/30 flex items-center gap-3 transition-transform hover:-translate-y-1 duration-300">
                <div class="h-10 w-10 rounded-full overflow-hidden border-2 border-orange-200 dark:border-orange-700 shadow-sm flex-shrink-0">
                    ${(() => {
                    const p = getPlayerById(advStats.pallaAlPiede.id);
                    return p?.avatar_url
                        ? `<img src="${p.avatar_url}" class="w-full h-full object-cover">`
                        : `<div class="w-full h-full flex items-center justify-center bg-orange-200 text-orange-700 font-bold text-xs">${(p?.nome || '?').charAt(0)}</div>`;
                })()}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-1.5 mb-0.5">
                        <span class="material-symbols-outlined text-orange-500 text-[10px]">anchor</span>
                        <div class="text-[8px] text-gray-500 dark:text-gray-400 uppercase font-bold tracking-wider truncate">Palla al Piede</div>
                    </div>
                    <div class="text-xs font-bold text-gray-900 dark:text-white leading-tight truncate">
                        <span data-color="${getPlayerById(advStats.pallaAlPiede.id)?.active_name_color || ''}">${getPlayerById(advStats.pallaAlPiede.id).nome}</span>
                    </div>
                    <div class="text-[9px] text-orange-600 dark:text-orange-400 font-bold">${Math.round((advStats.pallaAlPiede.wins / advStats.pallaAlPiede.matches) * 100)}% Win</div>
                </div>
            </div>` : ''}
        `;
    } else {
        advStatsContainer.classList.add('hidden');
        advStatsContent.innerHTML = '';
    }

    // --- CHART FIX: Show Modal FIRST so canvas has dimensions ---
    openModal('playerDetailsModal');

    // Render Chart
    const ctx = document.getElementById('eloHistoryChart');
    if (playerChart) playerChart.destroy();

    playerChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Generale',
                    data: dataGeneral,
                    borderColor: '#8b5cf6', // Violet
                    backgroundColor: 'rgba(139, 92, 246, 0.1)',
                    borderWidth: 3,
                    tension: 0.3,
                    pointRadius: 3,
                    pointHoverRadius: 5
                },
                {
                    label: 'Attacco',
                    data: dataAtk,
                    borderColor: '#ef4444', // Red
                    backgroundColor: 'transparent',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    tension: 0.3,
                    pointRadius: 3,
                    pointHoverRadius: 5
                },
                {
                    label: 'Porta',
                    data: dataDef,
                    borderColor: '#10b981', // Emerald
                    backgroundColor: 'transparent',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    tension: 0.3,
                    pointRadius: 3,
                    pointHoverRadius: 5
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom',
                    labels: { usePointStyle: true, boxWidth: 8 }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            },
            scales: {
                x: { display: false },
                y: {
                    display: true,
                    grid: { color: 'rgba(0,0,0,0.05)' }
                }
            }
        }
    });

    // 4. Fetch Season History
    fetchPlayerHistory(playerId, player);
}

function renderActivityHeatmap(playerId, matches, gridId = 'pd-heatmap-grid') {
    const grid = document.getElementById(gridId);
    if (!grid) return;

    grid.innerHTML = '';

    // Calculate 365 days ago
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    // We want to show a 53-week view (like GitHub)
    // To align properly, we find the date 52 weeks + current days ago
    // A more precise way: calculate the date for the "top-left" cell (Sunday of 52 weeks ago)
    const startDate = new Date(today);
    startDate.setDate(startDate.getDate() - 364); // ~1 year ago

    // Adjust to the nearly Sunday (0) to start the first row
    const startDay = startDate.getDay(); // 0-6
    startDate.setDate(startDate.getDate() - startDay);

    // Group matches by date string (YYYY-MM-DD) in local time
    const counts = {};
    matches.forEach(m => {
        if (!m.data) return;
        const d = new Date(m.data);
        if (isNaN(d.getTime())) return;
        const key = d.getFullYear() + '-' + (d.getMonth() + 1).toString().padStart(2, '0') + '-' + d.getDate().toString().padStart(2, '0');
        counts[key] = (counts[key] || 0) + 1;
    });

    // Generate 371 cells (53 weeks * 7 days)
    const totalCells = 53 * 7;
    let current = new Date(startDate);

    for (let i = 0; i < totalCells; i++) {
        const dateKey = current.getFullYear() + '-' + (current.getMonth() + 1).toString().padStart(2, '0') + '-' + current.getDate().toString().padStart(2, '0');
        const count = counts[dateKey] || 0;

        let level = 0;
        if (count >= 10) level = 4;
        else if (count >= 6) level = 3;
        else if (count >= 3) level = 2;
        else if (count >= 1) level = 1;

        const cell = document.createElement('div');
        cell.className = `heatmap-cell hv-${level}`;

        // Tooltip (simple title for now)
        const dateDisplay = current.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
        cell.title = `${count} partite il ${dateDisplay}`;

        // If date is in the future relative to "today", make it slightly more transparent or just empty
        if (current > today) {
            cell.style.opacity = '0';
            cell.style.pointerEvents = 'none';
        }

        grid.appendChild(cell);
        current.setDate(current.getDate() + 1);
    }

    // Auto-scroll alla fine (date più recenti a destra) su mobile
    const container = grid.closest('.heatmap-container');
    if (container) {
        setTimeout(() => {
            container.scrollLeft = container.scrollWidth;
        }, 10);
    }
}

async function fetchPlayerHistory(playerId, player) {
    const container = document.getElementById('pd-season-history-container');
    const list = document.getElementById('pd-season-history');
    const nameEl = document.getElementById('pd-name');

    // Reset badges
    nameEl.innerHTML = player.nome;

    try {
        const res = await fetch(`api_stagioni.php?action=player_history&id=${playerId}`);
        const data = await res.json();

        if (data.success && data.data && data.data.length > 0) {
            container.classList.remove('hidden');

            let html = '';
            let hasWon = false;

            data.data.forEach(s => {
                if (s.is_winner == 1) hasWon = true;

                const winIcon = s.is_winner == 1 ? '<img src="img/s1.png" class="inline-block w-5 h-5 ml-2" title="Campione della Stagione">' : '';
                const bgClass = s.is_winner == 1 ? 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-700' : 'bg-gray-50 dark:bg-gray-700/50 border-transparent';

                html += `
                    <div class="p-3 rounded-xl border ${bgClass} flex items-center justify-between">
                        <div>
                            <p class="text-sm font-bold text-gray-900 dark:text-white flex items-center">${s.nome} ${winIcon}</p>
                            <p class="text-[10px] text-gray-500">Elo Medio: ${s.elo_medio} • Vinte: ${s.vittorie_totali}/${s.partite_totali}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] uppercase font-bold text-gray-400">Piazzamento</p>
                            <p class="text-sm font-black ${s.is_winner == 1 ? 'text-yellow-600 dark:text-yellow-400' : 'text-gray-700 dark:text-gray-300'}">${s.rank}° Posto</p>
                        </div>
                    </div>
                `;
            });

            list.innerHTML = html;

            if (hasWon) {
                // Add badge to main modal name
                nameEl.innerHTML = `${player.nome} <img src="img/s1.png" class="inline-block w-6 h-6 align-middle ml-1 drop-shadow-md" title="Pluricampione">`;
                nameEl.setAttribute('data-color', player.active_name_color || '');

                // Also could update the leaderboard UI, but user asked for "su ogni profilo si veda lo storico... badge vicino all'avatar"
            }

        } else {
            container.classList.add('hidden');
        }
    } catch (e) {
        console.error("Error fetching player history", e);
        container.classList.add('hidden');
    }
}

function closePlayerDetailsModal(event) {
    if (event && event.target !== event.currentTarget) return;
    closeModal('playerDetailsModal');
}

function createPodiumItem(player, rank, tab = 'generale') {
    const scale = rank === 1 ? 'scale-110 z-10' : 'scale-100 z-0';
    const color = rank === 1 ? 'bg-yellow-400' : (rank === 2 ? 'bg-gray-300' : 'bg-orange-400');

    // Get ELO based on tab
    let displayElo;
    switch (tab) {
        case 'attaccanti':
            displayElo = player.elo_attaccante;
            break;
        case 'portieri':
            displayElo = player.elo_portiere;
            break;
        case 'generale':
        default:
            displayElo = player.elo_medio || Math.round(((player.elo_attaccante || 1500) + (player.elo_portiere || 1500)) / 2);
            break;
    }

    // Avatar Logic
    let avatarHtml;
    if (player.avatar_url) {
        avatarHtml = `<img src="${player.avatar_url}" class="h-full w-full object-cover">`;
    } else {
        avatarHtml = `<div class="h-full w-full flex items-center justify-center text-xl font-bold text-white bg-gray-400">${player.nome.substr(0, 1)}</div>`;
    }

    const isCurrentUser = currentUser && player.id == currentUser.id;
    const highlightClass = isCurrentUser ? 'ring-4 ring-blue-500 ring-offset-2 dark:ring-offset-gray-900 shadow-2xl' : '';
    const nameHighlight = isCurrentUser ? 'text-blue-600 dark:text-blue-400 font-black' : 'text-gray-900 dark:text-white';

    // Add Hover Effects: hover:scale-105 is already there, let's ensure it pops
    return `
    <div class="flex flex-col items-center ${scale} xl:cursor-pointer transition-transform duration-200 xl:hover:scale-110" onclick="openPlayerDetails(${player.id})">
        <div class="relative mb-2">
            ${player.active_aura ? `<div class="absolute inset-[-6px] rounded-full aura-${player.active_aura} opacity-70 z-0"></div>` : ''}
            <div class="h-16 w-16 rounded-full border-4 border-white dark:border-gray-800 shadow-lg transform transition-all xl:hover:shadow-xl xl:hover:border-blue-400 ${highlightClass} relative z-10">
                <div class="w-full h-full rounded-full overflow-hidden">
                    ${avatarHtml}
                </div>
                ${player.active_title ? `<div class="podium-title-badge" data-color="${player.active_name_color || ''}">${player.active_title}</div>` : ''}
            </div>
            <div class="absolute -bottom-2 left-1/2 -translate-x-1/2 w-6 h-6 rounded-full ${color} flex items-center justify-center text-xs font-bold text-white shadow-sm border-2 border-white z-20">
                ${rank}
            </div>
            ${isCurrentUser ? '<div class="absolute -bottom-1 -left-1 bg-blue-600 text-white text-[8px] font-black px-1.5 py-0.5 rounded-full shadow-md ring-2 ring-white z-20 leaderboard-tu-indicator">TU</div>' : ''}
        </div>
        <div class="text-center group min-h-[40px] flex flex-col justify-center">
            <div class="text-sm font-bold ${nameHighlight} truncate max-w-[80px] xl:group-hover:text-primary transition-colors leading-tight">
                <span data-color="${player.active_name_color || ''}" data-style="${player.active_name_style || ''}">${applyNameStyle(player.nome, player.active_name_style)}</span>
            </div>
            <div class="text-xs font-bold text-primary mt-0.5">${displayElo}</div>
        </div>
    </div>
    `;
}

function createLeaderboardItem(player, rank, tab = 'generale') {
    // Get ELO based on tab
    let displayElo;
    let displayWins;

    switch (tab) {
        case 'attaccanti':
            displayElo = player.elo_attaccante;
            displayWins = player.vittorie_attaccante || 0;
            break;
        case 'portieri':
            displayElo = player.elo_portiere;
            displayWins = player.vittorie_portiere || 0;
            break;
        case 'generale':
        default:
            displayElo = player.elo_medio || Math.round(((player.elo_attaccante || 1500) + (player.elo_portiere || 1500)) / 2);
            displayWins = player.vittorie_totali || 0;
            break;
    }

    // Avatar Logic
    let avatarHtml;
    if (player.avatar_url) {
        avatarHtml = `<img src="${player.avatar_url}" class="h-full w-full object-cover">`;
    } else {
        avatarHtml = `<div class="h-full w-full bg-gray-400 flex items-center justify-center font-bold text-white">${player.nome.substr(0, 1)}</div>`;
    }

    const isCurrentUser = currentUser && player.id == currentUser.id;
    const highlightClass = isCurrentUser ? 'bg-blue-50/80 dark:bg-blue-900/20 border-blue-400 dark:border-blue-600 scale-[1.02] shadow-md z-10' : 'bg-white dark:bg-gray-800 border-gray-100 dark:border-gray-700 shadow-sm';
    const nameHighlight = isCurrentUser ? 'text-blue-700 dark:text-blue-300 font-black' : 'text-gray-900 dark:text-white';

    return `
    <div onclick="openPlayerDetails(${player.id})" class="flex items-center gap-4 p-3 rounded-xl border xl:cursor-pointer transition-all duration-200 xl:hover:shadow-md xl:hover:bg-blue-50/50 xl:dark:hover:bg-gray-750 xl:hover:scale-[1.01] ${highlightClass}">
        <span class="text-sm font-bold ${isCurrentUser ? 'text-blue-500' : 'text-gray-400'} w-6 text-center">${rank}</span>
        <div class="relative shrink-0">
            ${player.active_aura ? `<div class="absolute inset-[-5px] rounded-full aura-${player.active_aura} opacity-70 z-0"></div>` : ''}
            <div class="h-10 w-10 rounded-full border-2 border-white dark:border-gray-700 shadow-sm ${isCurrentUser ? 'ring-2 ring-blue-400 ring-offset-2 dark:ring-offset-gray-900' : ''} relative z-10 overflow-hidden">
                ${avatarHtml}
            </div>
        </div>
        <div class="flex-1">
            <div class="text-sm font-bold ${nameHighlight} xl:group-hover:text-blue-600 transition-colors flex items-center gap-2">
                <span data-color="${player.active_name_color || ''}" data-style="${player.active_name_style || ''}">${applyNameStyle(player.nome, player.active_name_style)}</span>
                ${isCurrentUser ? '<span class="bg-blue-600 text-white text-[9px] px-1.5 py-0.5 rounded-full leaderboard-tu-indicator">TU</span>' : ''}
            </div>
            ${player.active_title ? `<div class="player-title" data-color="${player.active_name_color || ''}">${player.active_title}</div>` : ''}
            <div class="text-xs text-gray-500">${displayWins} Vittorie</div>
        </div>
        <div class="text-sm font-bold ${isCurrentUser ? 'text-blue-600 dark:text-blue-400' : 'text-primary'}">${displayElo}</div>
    </div>
    `;
}

// --- UTILS ---

function getBountyInfo() {
    if (!giocatori || giocatori.length === 0) return { leaderId: null, bestRole: null };

    // Filter active players (same logic as Generale leaderboard)
    const activePlayers = giocatori.filter(p => p.partite_totali > 0);
    if (activePlayers.length === 0) return { leaderId: null, bestRole: null };

    // Find the #1 General Leader (Sort by overall ELO average)
    const sorted = [...activePlayers].sort((a, b) => {
        const eloA = a.elo_medio || Math.max(a.elo_attaccante || 0, a.elo_portiere || 0);
        const eloB = b.elo_medio || Math.max(b.elo_attaccante || 0, b.elo_portiere || 0);
        return eloB - eloA;
    });

    const leader = sorted[0];

    // Determine their best position (higher ELO)
    const bestRole = (leader.elo_attaccante >= leader.elo_portiere) ? 'atk' : 'def';

    return {
        leaderId: leader.id,
        bestRole: bestRole
    };
}

// --- HELPER FUNCTIONS FOR ANIMATIONS ---

function openModal(modalId) {
    const el = document.getElementById(modalId);
    if (!el) return;

    // Scroll lock
    document.body.classList.add('overflow-hidden');

    const inner = el.firstElementChild; // The modal content wrapper
    el.classList.remove('hidden');
    if (inner) {
        inner.classList.remove('animate-scale-out');
        inner.classList.add('animate-scale-in');
    }
}

function closeModal(modalId) {
    const el = document.getElementById(modalId);
    if (!el) return;

    // Check if other modals are still open before removing scroll lock
    const openModals = document.querySelectorAll('[role="dialog"]:not(.hidden)');
    if (openModals.length <= 1) { // Current one is still not hidden, so check if <= 1
        document.body.classList.remove('overflow-hidden');
    }

    const inner = el.firstElementChild;
    if (inner) {
        inner.classList.remove('animate-scale-in');
        inner.classList.add('animate-scale-out');
        setTimeout(() => {
            el.classList.add('hidden');
            inner.classList.remove('animate-scale-out'); // Clean up
        }, 200); // Match animation duration
    } else {
        el.classList.add('hidden');
    }
}

function showPage(pageId) {
    document.querySelectorAll('.view-section').forEach(el => {
        el.classList.add('hidden');
        el.classList.remove('animate-fade-in');
    });

    // Reset Mobile Nav
    document.querySelectorAll('.nav-item').forEach(el => {
        el.classList.remove('active', 'text-primary', 'text-blue-500');
        el.classList.add('text-gray-400');
        // Reset child spans
        el.querySelectorAll('span').forEach(s => {
            s.classList.remove('text-blue-500', 'text-primary');
        });
    });

    // Reset Desktop Nav
    document.querySelectorAll('.nav-item-desktop').forEach(el => {
        el.classList.remove('active', 'bg-blue-50', 'text-primary', 'dark:bg-gray-700/50');
    });

    const target = document.getElementById(pageId);
    if (target) {
        target.classList.remove('hidden');
        target.classList.add('animate-fade-in');
    }

    const bottomNav = document.querySelector('nav.md\\:hidden');
    const addMatchBtn = document.getElementById('addMatchBtnContainer');
    const matchesSPBtn = document.getElementById('matchesSeasonPassBtn');

    if (pageId === 'season-pass') {
        loadSeasonPass();
        if (bottomNav) bottomNav.classList.add('hidden');
        if (addMatchBtn) addMatchBtn.classList.add('hidden');
    } else {
        if (bottomNav) bottomNav.classList.remove('hidden');
        if (addMatchBtn) addMatchBtn.classList.remove('hidden');
    }

    if (pageId === 'matches') {
        if (matchesSPBtn) matchesSPBtn.classList.add('hidden');
    } else {
        if (matchesSPBtn) matchesSPBtn.classList.remove('hidden');
    }

    // Mobile Active
    const activeNav = document.querySelector(`.nav-item[data-target="${pageId}"]`);
    if (activeNav) {
        activeNav.classList.add('active', 'text-blue-500');
        activeNav.classList.remove('text-gray-400', 'text-primary');
        // Also color child spans
        activeNav.querySelectorAll('span').forEach(s => {
            s.classList.add('text-blue-500');
            s.classList.remove('text-gray-400');
        });
    }

    // Desktop Active
    const activeNavDesktop = document.querySelector(`.nav-item-desktop[data-target="${pageId}"]`);
    if (activeNavDesktop) {
        activeNavDesktop.classList.add('active', 'bg-blue-50', 'text-primary', 'dark:bg-gray-700/50');
    }
}

function toggleAuthModal() {
    const el = document.getElementById('authModal');
    if (el.classList.contains('hidden')) openModal('authModal');
    else closeModal('authModal');
}

function showAddMatchModal() {
    openModal('addMatchModal');
}

function closeAddMatchModal() {
    closeModal('addMatchModal');
}

function safeSetText(id, text) {
    const el = document.getElementById(id);
    if (el) el.textContent = text;
}

// --- AESTHETIC CUSTOMIZATION ACTIONS ---

async function equipItem(itemId) {
    try {
        const fd = new FormData();
        fd.append('item_id', itemId);
        const res = await fetch('shop_api.php?action=equip', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, 'success');
            // Refresh players and profile
            await loadData();
            if (window.loadProfileInventory) window.loadProfileInventory();
            renderProfile();
        } else {
            showToast(data.error, 'error');
        }
    } catch (e) {
        console.error(e);
        showToast('Errore durante l\'equipaggiamento', 'error');
    }
}
window.equipItem = equipItem;

async function unequipItem(type) {
    try {
        const fd = new FormData();
        fd.append('type', type);
        const res = await fetch('shop_api.php?action=unequip', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, 'success');
            await loadData();
            if (window.loadProfileInventory) window.loadProfileInventory();
            renderProfile();
        } else {
            showToast(data.error, 'error');
        }
    } catch (e) {
        console.error(e);
        showToast('Errore durante la rimozione', 'error');
    }
}
window.unequipItem = unequipItem;

function timeSince(date) {
    const seconds = Math.floor((new Date() - date) / 1000);
    let interval = seconds / 31536000;
    if (interval > 1) return Math.floor(interval) + "a fa";
    interval = seconds / 2592000;
    if (interval > 1) return Math.floor(interval) + "m fa";
    interval = seconds / 86400;
    if (interval > 1) return Math.floor(interval) + "g fa";
    interval = seconds / 3600;
    if (interval > 1) return Math.floor(interval) + "h fa";
    interval = seconds / 60;
    if (interval > 1) return Math.floor(interval) + "min fa";
    return Math.floor(seconds) + "sec fa";
}

function populatePlayerSelects() {
    const options = '<option value="">Seleziona...</option>' + giocatori.map(g => `<option value="${g.id}">${g.nome}</option>`).join('');
    ['s1p', 's1a', 's2p', 's2a'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.innerHTML = options;
    });

    const loginSel = document.getElementById('loginSelect');
    if (loginSel) loginSel.innerHTML = options;
}

// --- ACTIONS ---

async function submitMatch(e) {
    e.preventDefault();
    if (!currentUser) {
        showToast("Accedi per registrare una partita", 'error');
        return;
    }
    const s1p = document.getElementById('s1p').value;
    const s1a = document.getElementById('s1a').value;
    const s2p = document.getElementById('s2p').value;
    const s2a = document.getElementById('s2a').value;
    const team = document.querySelector('input[name="winningTeam"]:checked')?.value;

    if (!s1p || !s1a || !s2p || !s2a || !team) {
        showToast("Per favore, compila tutti i campi", 'error');
        return;
    }

    try {
        const res = await fetch('index.php?api=1', {
            method: 'POST',
            body: JSON.stringify({
                action: 'quick_match',
                s1p: s1p, s1a: s1a, s2p: s2p, s2a: s2a, winner: team
            }),
            headers: { 'Content-Type': 'application/json' }
        });

        const data = await res.json();
        if (data.success) {
            closeAddMatchModal();
            loadData();
            document.getElementById('addMatchForm').reset();
            showToast("Partita registrata!", 'success');
        } else {
            showToast("Errore: " + (data.error || "Sconosciuto"), 'error');
        }
    } catch (e) {
        console.error(e);
        showToast("Errore di rete", 'error');
    }
}

async function handleLogin(e) {
    e.preventDefault();
    const username = document.getElementById('loginUsername').value.trim();
    const password = document.getElementById('loginPassword').value;

    if (!username || !password) {
        showToast("Inserisci sia username che password", 'error');
        return;
    }

    try {
        const res = await fetch('auth.php?action=login', {
            method: 'POST',
            body: JSON.stringify({ username: username, password: password }),
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await res.json();
        if (data.success) {
            location.reload();
        } else {
            showToast(data.error, 'error');
        }
    } catch (e) {
        console.error(e);
        showToast("Errore di login", 'error');
    }
}

async function performLogout() {
    try {
        const res = await fetch('auth.php?action=logout');
        const data = await res.json();
        if (data.success) {
            location.reload();
        } else {
            console.error("Logout failed");
        }
    } catch (e) {
        console.error("Logout error", e);
    }
}

// Quick Match UI Stubs
window.selectQuickMatchPlayer = function (team, role) {
    console.log(`Quick Match: Selected ${team} ${role}`);
    showAddMatchModal();
};

window.startQuickMatch = function () {
    console.log("Quick Match: Start clicked");
    showAddMatchModal();
};

// --- LIVE MATCH LOGIC ---

window.updateLiveScore = async function (team, action) {
    if (!currentUser) {
        showToast("Devi loggarti per modificare il punteggio manualmente!", "error");
        return;
    }
    // Optimistic UI update could be placed here if needed
    try {
        const res = await fetch(LIVE_API_URL, {
            method: 'POST',
            body: JSON.stringify({ action: action, team: team })
        });
        const d = await res.json();
        if (d.success) {
            pollLiveTable(); // refresh UI
            if (d.match_ended) {
                showToast(`🏆 Partita terminata! Ha vinto la squadra ${d.winner === 1 ? 'Blu' : 'Rossa'}!`, 'success');
                // Reload main statistical data
                setTimeout(() => loadData(), 1000);
            }
        } else {
            showToast(d.error || 'Errore aggiornamento punteggio', 'error');
        }
    } catch (e) {
        console.error("Score update error", e);
        showToast("Errore di connessione", "error");
    }
};

async function performEndSeason() {
    try {
        const res = await fetch('api_stagioni.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'end_season' })
        });
        const data = await res.json();
        if (data.success) {
            showToast('Stagione Conclusa! ' + data.message, 'success');
            setTimeout(() => location.reload(), 2000); // Reload to reset dropdowns and load everything clean
        } else {
            showToast('Errore: ' + (data.error || 'Impossibile chiudere la stagione'), 'error');
        }
    } catch (e) {
        showToast('Errore di Connessione durante chiusura stagione.', 'error');
    }
}

let livePollInterval = null;
const LIVE_API_URL = 'live.php?api=1';
const BRIDGE_URL = 'https://script.google.com/macros/s/AKfycbxQM5EcEs9sNCuPKJw9M0gzWVM7Rp96LZ5grpYp94joXsEDGmuwmD0aqYuFaD_cFmnO/exec';

window.initLiveMatch = function () {
    pollLiveTable();
    if (livePollInterval) clearInterval(livePollInterval);
    livePollInterval = setInterval(pollLiveTable, 2000);
}
window.pollLiveTable = pollLiveTable;

async function pollLiveTable() {
    try {
        // Fetch players from original API (Internal DB)
        const resInternal = await fetch(LIVE_API_URL);
        if (!resInternal.ok) return;
        const internalData = await resInternal.json();

        // Fetch scores from Google Bridge (Bypass InfinityFree anti-bot for the PI)
        let bridgeData = { score_s1: 0, score_s2: 0 };
        try {
            const resBridge = await fetch(BRIDGE_URL);
            if (resBridge.ok) bridgeData = await resBridge.json();
        } catch (e) {
            console.error("Bridge fetch error", e);
        }

        // Merge results
        const combinedData = {
            ...internalData,
            score_s1: parseInt(bridgeData.score_s1 || 0),
            score_s2: parseInt(bridgeData.score_s2 || 0)
        };

        updateLiveUI(combinedData);
    } catch (e) {
        console.error("Live poll error", e);
    }
}

function updateLiveUI(data) {
    const players = data.players || {};
    const activeBonuses = data.active_bonuses || {};
    const positions = ['s1p', 's1a', 's2p', 's2a'];
    const keyMap = { 's1p': 's1_portiere', 's1a': 's1_attaccante', 's2p': 's2_portiere', 's2a': 's2_attaccante' };
    let filledCount = 0;
    window.isUserSeated = false;
    const scoreS1 = data.score_s1 || 0;
    const scoreS2 = data.score_s2 || 0;
    const hasSensorsActive = (scoreS1 > 0 || scoreS2 > 0);

    ['mobile', 'desktop'].forEach(view => {
        const s1Container = document.getElementById(`live-score-container-s1-${view}`);
        const s2Container = document.getElementById(`live-score-container-s2-${view}`);
        if (s1Container) s1Container.style.display = hasSensorsActive ? 'flex' : 'none';
        if (s2Container) s2Container.style.display = hasSensorsActive ? 'flex' : 'none';

        const s1El = document.getElementById(`live-score-s1-${view}`);
        const s2El = document.getElementById(`live-score-s2-${view}`);
        if (s1El) s1El.textContent = scoreS1;
        if (s2El) s2El.textContent = scoreS2;
    });

    positions.forEach(pos => {
        const player = players[keyMap[pos]]; // {id, nome} or null
        const isOccupied = !!player;
        if (isOccupied) filledCount++;

        // Track if ME
        if (currentUser && player && player.id == currentUser.id) {
            window.isUserSeated = true;
        }

        // Update Mobile & Desktop Elements
        ['mobile', 'desktop'].forEach(view => {
            const elId = `live-${view}-${pos}`;
            const el = document.getElementById(elId);
            if (!el) return;

            const nameEl = el.querySelector('.player-name');
            // Remove existing guest buttons and bonus badges
            el.querySelectorAll('.btn-guest-sit, .bonus-badge-live').forEach(b => b.remove());

            if (isOccupied) {
                let displayName = player.id == 9999 ? "Ospite" : player.nome;
                nameEl.textContent = displayName;
                nameEl.setAttribute('data-color', player.active_name_color || '');
                nameEl.classList.remove('text-gray-400');
                nameEl.classList.add('text-gray-900', 'dark:text-white');

                // --- Specific Bonus Icons ---
                const pBonuses = activeBonuses[player.id] || [];
                if (pBonuses.length > 0) {
                    const iconContainer = document.createElement('div');
                    iconContainer.className = 'bonus-icons-live inline-flex gap-1 ml-1 align-middle';

                    pBonuses.forEach(bKey => {
                        const span = document.createElement('span');
                        span.className = 'material-symbols-outlined text-[14px] animate-pulse';
                        if (bKey === 'x2_elo') {
                            span.textContent = 'rocket_launch';
                            span.classList.add('text-orange-500');
                            span.title = 'Bonus X2 Elo attivo!';
                        } else if (bKey === 'palla_matta') {
                            span.textContent = 'sports_soccer';
                            span.classList.add('text-yellow-500');
                            span.title = 'Palla Matta attiva!';
                        } else {
                            span.textContent = 'stars';
                            span.classList.add('text-indigo-500');
                        }
                        iconContainer.appendChild(span);
                    });
                    nameEl.appendChild(iconContainer);
                }

                el.classList.add('border-solid', 'bg-opacity-100');
                el.classList.remove('border-dashed');

                // Style based on team
                if (pos.startsWith('s1')) { // Blue
                    if (currentUser && player.id == currentUser.id) {
                        el.classList.add('ring-2', 'ring-blue-500', 'bg-blue-100', 'dark:bg-blue-900/60');
                    } else {
                        el.classList.add('bg-blue-50', 'dark:bg-blue-900/40');
                        el.classList.remove('ring-2', 'ring-blue-500', 'bg-blue-100', 'dark:bg-blue-900/60');
                    }
                } else { // Red
                    if (currentUser && player.id == currentUser.id) {
                        el.classList.add('ring-2', 'ring-red-500', 'bg-red-100', 'dark:bg-red-900/60');
                    } else {
                        el.classList.add('bg-red-50', 'dark:bg-red-900/40');
                        el.classList.remove('ring-2', 'ring-red-500', 'bg-red-100', 'dark:bg-red-900/60');
                    }
                }

                // Allow sitting if occupied by ME (to unsit)
                if (currentUser && player.id == currentUser.id) {
                    el.onclick = () => sitDown(pos);
                } else {
                    el.onclick = null; // Prevent clicking others' seats
                }

            } else {
                nameEl.textContent = "Libero";
                nameEl.classList.add('text-gray-400');
                nameEl.classList.remove('text-gray-900', 'dark:text-white');

                el.classList.remove('border-solid', 'bg-opacity-100', 'ring-2', 'ring-blue-500', 'ring-red-500', 'bg-blue-100', 'bg-red-100', 'dark:bg-blue-900/60', 'dark:bg-red-900/60', 'bg-blue-50', 'bg-red-50', 'dark:bg-blue-900/40', 'dark:bg-red-900/40');
                el.classList.add('border-dashed');

                // SIT ACTION: Click container to sit SELF
                // SIT ACTION: Click container to sit SELF - REMOVED PER USER REQUEST (NFC ONLY)
                // el.onclick = () => sitDown(pos);
                el.onclick = null;

                // ADD GUEST BUTTON
                const guestBtn = document.createElement('button');
                guestBtn.className = 'btn-guest-sit mt-1 px-2 py-0.5 text-[10px] bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 rounded text-gray-700 dark:text-gray-300 transition-colors z-10';
                guestBtn.innerHTML = '<span class="material-symbols-outlined text-[10px] align-middle">person_add</span> Ospite';
                guestBtn.onclick = (e) => {
                    e.stopPropagation(); // Don't trigger parent sitDown
                    sitGhost(pos);
                };
                el.appendChild(guestBtn);
            }
        });
    });

    // Check for Bounty
    let leader = null;
    let maxElo = -1;
    if (typeof giocatori !== 'undefined') {
        giocatori.forEach(g => {
            if (g.id == 9999 || g.partite_totali == 0) return;
            let eloMedio = (g.elo_attaccante + g.elo_portiere) / 2;
            if (eloMedio > maxElo) {
                maxElo = eloMedio;
                leader = g;
            }
        });
    }

    let bountyActive = false;
    if (leader) {
        let bestRole = leader.elo_attaccante >= leader.elo_portiere ? 'atk' : 'def';
        if (bestRole === 'atk' && (players['s1_attaccante']?.id == leader.id || players['s2_attaccante']?.id == leader.id)) {
            bountyActive = true;
        } else if (bestRole === 'def' && (players['s1_portiere']?.id == leader.id || players['s2_portiere']?.id == leader.id)) {
            bountyActive = true;
        }
    }

    ['mobile', 'desktop'].forEach(view => {
        const banner = document.getElementById(`liveBountyBanner-${view}`);
        if (banner) {
            if (bountyActive) {
                banner.classList.remove('hidden');
            } else {
                banner.classList.add('hidden');
            }
        }
    });
}
window.sitDown = async function (pos) {
    if (!currentUser) {
        showErrorModal("Errore", "Devi effettuare il login per sederti!");
        return;
    }

    try {
        const res = await fetch(LIVE_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'sit', pos: pos })
        });
        const d = await res.json();
        if (d.success) {
            window.isUserSeated = true; // Force instant display before poll
            pollLiveTable();
            if (window.loadUserInventory) window.loadUserInventory();
        } else {
            showErrorModal("Errore", d.error || "Errore");
        }
    } catch (e) {
        console.error(e);
        showErrorModal("Errore", "Errore di connessione");
    }
};

window.sitGhost = async function (pos) {
    if (!currentUser) {
        showErrorModal("Errore", "Devi effettuare il login per aggiungere ospiti!");
        return;
    }

    try {
        const res = await fetch(LIVE_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'sit', pos: pos, player_id: 9999 })
        });
        const d = await res.json();
        if (d.success) {
            // showToast("Ospite aggiunto", "success"); // Removed to reduce noise, simply update table
            pollLiveTable();
        } else {
            showErrorModal("Errore", d.error || "Errore ospite");
        }
    } catch (e) {
        showErrorModal("Errore", "Errore di connessione");
    }
};

window.resetLiveTable = async function () {
    if (!(await showConfirmModal("Reset Tavolo", "Sei sicuro di voler svuotare il tavolo?"))) return;

    try {
        const res = await fetch(LIVE_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'reset' })
        });
        const d = await res.json();
        if (d.success) {
            showToast("Tavolo svuotato", "success");
            pollLiveTable();
        } else {
            showToast(d.error, "error");
        }
    } catch (e) {
        showToast("Errore reset", "error");
    }
};

let confirmResolver = null;

window.showConfirmModal = function (title, message) {
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmMessage').textContent = message;
    document.getElementById('confirmationModal').classList.remove('hidden');
    openModal('confirmationModal');
    return new Promise((resolve) => {
        confirmResolver = resolve;
    });
}

window.closeConfirmModal = function (result) {
    closeModal('confirmationModal');
    if (confirmResolver) confirmResolver(result);
    confirmResolver = null;
}



function showToast(msg, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    const bgColor = type === 'error' ? 'bg-red-500' : (type === 'success' ? 'bg-green-500' : 'bg-gray-800');
    const icon = type === 'success' ? 'check_circle' : (type === 'error' ? 'error' : 'info');

    toast.className = `toast ${bgColor} text-white px-4 py-3 rounded-xl shadow-xl flex items-center gap-3 transform transition-all animate-slide-up`;
    toast.innerHTML = `<span class="material-symbols-outlined text-[20px]">${icon}</span> <span class="font-medium text-sm">${msg}</span>`;

    container.appendChild(toast);

    // Remove after 3s
    setTimeout(() => {
        toast.classList.remove('animate-slide-up');
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-10px)';
        toast.style.transition = 'all 0.3s ease-out';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

window.showErrorModal = function (title, message) {
    const titleEl = document.getElementById('errorTitle');
    const msgEl = document.getElementById('errorMessage');
    const modal = document.getElementById('errorModal');

    if (titleEl) titleEl.textContent = title;
    if (msgEl) msgEl.textContent = message;
    if (modal) openModal('errorModal');
    else showToast(message, 'error'); // Fallback
}

window.closeErrorModal = function () {
    closeModal('errorModal');
}

// --- ODDS EXPLANATION MODAL LOGIC ---

window.openOddsExplanationModal = function () {
    const modal = document.getElementById('oddsExplanationModal');
    if (!modal) return;

    // We only open it if we have breakdown data
    if (!window.currentOddsBreakdown) {
        showToast("Dati quote non ancora disponibili. Attendi l'aggiornamento.", 'info');
        return;
    }

    // Populate the HTML
    const b = window.currentOddsBreakdown;

    // Header Totals
    const t1Elem = document.getElementById('oddsExpTotalBlue');
    const t2Elem = document.getElementById('oddsExpTotalRed');
    const divisorElem = document.getElementById('oddsExpDivisor');

    if (divisorElem && b.divisor) {
        divisorElem.textContent = b.divisor;
    }

    // Determine which team has the highest base + form + syn + match + fat
    // (Essentially team1_elo vs team2_elo, but we can reconstruct loosely)
    const t1Sum = b.team1.base_elo + b.team1.form + b.team1.synergy + b.team1.matchup + b.team1.fatigue;
    const t2Sum = b.team2.base_elo + b.team2.form + b.team2.synergy + b.team2.matchup + b.team2.fatigue;

    if (t1Elem) t1Elem.textContent = Math.round(t1Sum);
    if (t2Elem) t2Elem.textContent = Math.round(t2Sum);

    // Helper to format rows
    const createRow = (label, value, isPositiveGreen = true) => {
        let valStr = value > 0 ? `+${value}` : `${value}`;
        let colorClass = 'text-gray-900 dark:text-gray-100'; // neutral

        if (value > 0) colorClass = isPositiveGreen ? 'text-green-600 dark:text-green-400 font-bold' : 'text-red-500 dark:text-red-400 font-bold';
        if (value < 0) colorClass = isPositiveGreen ? 'text-red-500 dark:text-red-400 font-bold' : 'text-green-600 dark:text-green-400 font-bold';

        if (value === 0) {
            valStr = '-';
            colorClass = 'text-gray-400 font-medium';
        }

        return `
            <div class="flex justify-between items-center bg-white/50 dark:bg-gray-800/50 p-2.5 rounded-xl gap-2">
                <span class="text-gray-600 dark:text-gray-400 text-xs sm:text-sm leading-tight flex-1">${label}</span>
                <span class="${colorClass} font-mono text-sm sm:text-base whitespace-nowrap">${valStr}</span>
            </div>
        `;
    };

    const detailsBlue = document.getElementById('oddsExpDetailsBlue');
    const detailsRed = document.getElementById('oddsExpDetailsRed');

    if (detailsBlue) {
        detailsBlue.innerHTML = `
            ${createRow('Elo Medio Base', b.team1.base_elo)}
            ${createRow('<span title="Media del delta di Elo delle ultime 5 partite" class="border-b border-dashed border-gray-400 cursor-help">Forma Recente</span>', b.team1.form)}
            ${createRow('<span title="Bonus sinergia se hanno già giocato assieme" class="border-b border-dashed border-gray-400 cursor-help">Sinergia Squadra</span>', b.team1.synergy)}
            ${createRow('<span title="Malus per partite consecutive in 24h" class="border-b border-dashed border-gray-400 cursor-help">Fattore Stanchezza</span>', b.team1.fatigue, false)} 
            ${createRow('<span title="Vantaggio storico tra attaccante e portiere avversario" class="border-b border-dashed border-gray-400 cursor-help">Vantaggio Matchup</span>', b.team1.matchup)}
        `;
    }

    if (detailsRed) {
        detailsRed.innerHTML = `
            ${createRow('Elo Medio Base', b.team2.base_elo)}
            ${createRow('<span title="Media del delta di Elo delle ultime 5 partite" class="border-b border-dashed border-gray-400 cursor-help">Forma Recente</span>', b.team2.form)}
            ${createRow('<span title="Bonus sinergia se hanno già giocato assieme" class="border-b border-dashed border-gray-400 cursor-help">Sinergia Squadra</span>', b.team2.synergy)}
            ${createRow('<span title="Malus per partite consecutive in 24h" class="border-b border-dashed border-gray-400 cursor-help">Fattore Stanchezza</span>', b.team2.fatigue, false)} 
            ${createRow('<span title="Vantaggio storico tra attaccante e portiere avversario" class="border-b border-dashed border-gray-400 cursor-help">Vantaggio Matchup</span>', b.team2.matchup)}
        `;
    }

    modal.classList.remove('hidden');
}

window.closeOddsExplanationModal = function () {
    const modal = document.getElementById('oddsExplanationModal');
    if (modal) modal.classList.add('hidden');
}

// Check for errors in URL on load
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('error')) {
        showErrorModal("Errore", urlParams.get('error'));
        // Clean URL
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});

// --- PLAYER COMPARISON FUNCTIONS ---

function openComparisonModal() {
    // Populate season dropdown
    const seasonSelect = document.getElementById('compareSeason');
    if (seasonSelect) {
        seasonSelect.innerHTML = `
            <option value="0">Tutte le stagioni</option>
            ${stagioni.map(s => `<option value="${s.id}" ${s.id == activeSeasonId ? 'selected' : ''}>${s.nome}</option>`).join('')}
        `;
    }

    // Populate player dropdowns
    const select1 = document.getElementById('comparePlayer1');
    const select2 = document.getElementById('comparePlayer2');

    if (!select1 || !select2) return;

    // Clear existing options except first
    select1.innerHTML = '<option value="">Seleziona...</option>';
    select2.innerHTML = '<option value="">Seleziona...</option>';

    // Add all players
    giocatori.forEach(player => {
        const option1 = document.createElement('option');
        option1.value = player.id;
        option1.textContent = player.nome;
        select1.appendChild(option1);

        const option2 = document.createElement('option');
        option2.value = player.id;
        option2.textContent = player.nome;
        select2.appendChild(option2);
    });

    // Reset comparison content
    document.getElementById('comparisonContent').innerHTML = `
        <div class="text-center text-gray-500 dark:text-gray-400 py-12">
            Seleziona due giocatori per visualizzare il confronto
        </div>
    `;

    // Open modal
    document.getElementById('comparisonModal').classList.remove('hidden');
}

function closeComparisonModal() {
    document.getElementById('comparisonModal').classList.add('hidden');
}

function updateComparison() {
    const player1Id = document.getElementById('comparePlayer1').value;
    const player2Id = document.getElementById('comparePlayer2').value;
    const seasonId = parseInt(document.getElementById('compareSeason').value);

    if (!player1Id || !player2Id) {
        document.getElementById('comparisonContent').innerHTML = `
            <div class="text-center text-gray-500 dark:text-gray-400 py-12">
                Seleziona due giocatori per visualizzare il confronto
            </div>
        `;
        return;
    }

    if (player1Id === player2Id) {
        document.getElementById('comparisonContent').innerHTML = `
            <div class="text-center text-gray-500 dark:text-gray-400 py-12">
                Seleziona due giocatori diversi
            </div>
        `;
        return;
    }

    const stats1 = calculatePlayerStatsForSeason(player1Id, seasonId);
    const stats2 = calculatePlayerStatsForSeason(player2Id, seasonId);

    if (!stats1 || !stats2) return;

    renderComparison(stats1, stats2);
}

function calculatePlayerStatsForSeason(playerId, seasonId) {
    const player = getPlayerById(playerId);
    if (!player || player.nome === 'Sconosciuto') return null;

    let pMatches = partite.filter(m =>
        m.squadra1_portiere == playerId || m.squadra1_attaccante == playerId ||
        m.squadra2_portiere == playerId || m.squadra2_attaccante == playerId
    ).sort((a, b) => new Date(a.data) - new Date(b.data));

    // Filter matches for the specific season stats
    const seasonMatches = seasonId === 0 ? pMatches : pMatches.filter(m => m.stagione_id == seasonId);

    // Calculate ELO up to the end of the season
    let currAtk = 1500;
    let currDef = 1500;

    // Sum deltas for all matches up to the end of selected season
    const upToMatches = seasonId === 0 ? pMatches : pMatches.filter(m => m.stagione_id <= seasonId);
    upToMatches.forEach(m => {
        let delta = 0;
        if (m.elo_deltas) {
            let roleKey = '';
            if (m.squadra1_portiere == playerId) roleKey = 's1p';
            else if (m.squadra1_attaccante == playerId) roleKey = 's1a';
            else if (m.squadra2_portiere == playerId) roleKey = 's2p';
            else if (m.squadra2_attaccante == playerId) roleKey = 's2a';
            if (roleKey && m.elo_deltas[roleKey] !== undefined) delta = parseInt(m.elo_deltas[roleKey]);
        }
        if (m.squadra1_attaccante == playerId || m.squadra2_attaccante == playerId) currAtk += delta;
        if (m.squadra1_portiere == playerId || m.squadra2_portiere == playerId) currDef += delta;
    });

    // Calculate other stats for the SPECIFIC season
    let winsTot = 0, matchesTot = 0;
    let winsAtk = 0, matchesAtk = 0;
    let winsDef = 0, matchesDef = 0;

    seasonMatches.forEach(m => {
        const isBlue = (m.squadra1_portiere == playerId || m.squadra1_attaccante == playerId);
        const won = (isBlue && m.vincitore == 1) || (!isBlue && m.vincitore == 2);

        matchesTot++;
        if (won) winsTot++;

        if (m.squadra1_attaccante == playerId || m.squadra2_attaccante == playerId) {
            matchesAtk++;
            if (won) winsAtk++;
        }
        if (m.squadra1_portiere == playerId || m.squadra2_portiere == playerId) {
            matchesDef++;
            if (won) winsDef++;
        }
    });

    return {
        nome: player.nome,
        avatar_url: player.avatar_url,
        elo_attaccante: currAtk,
        elo_portiere: currDef,
        elo_medio: Math.round((currAtk + currDef) / 2),
        vittorie_totali: winsTot,
        partite_totali: matchesTot,
        vittorie_attaccante: winsAtk,
        partite_attaccante: matchesAtk,
        vittorie_portiere: winsDef,
        partite_portiere: matchesDef
    };
}

function renderComparison(p1, p2) {
    // Calculate win rates
    const p1WinRateOverall = p1.partite_totali > 0 ? ((p1.vittorie_totali / p1.partite_totali) * 100).toFixed(1) : 0;
    const p2WinRateOverall = p2.partite_totali > 0 ? ((p2.vittorie_totali / p2.partite_totali) * 100).toFixed(1) : 0;

    const p1WinRateAtk = p1.partite_attaccante > 0 ? ((p1.vittorie_attaccante / p1.partite_attaccante) * 100).toFixed(1) : 0;
    const p2WinRateAtk = p2.partite_attaccante > 0 ? ((p2.vittorie_attaccante / p2.partite_attaccante) * 100).toFixed(1) : 0;

    const p1WinRateDef = p1.partite_portiere > 0 ? ((p1.vittorie_portiere / p1.partite_portiere) * 100).toFixed(1) : 0;
    const p2WinRateDef = p2.partite_portiere > 0 ? ((p2.vittorie_portiere / p2.partite_portiere) * 100).toFixed(1) : 0;

    // Helper function to create comparison bar
    const createComparisonBar = (label, val1, val2, suffix = '') => {
        const max = Math.max(val1, val2) || 1;
        const width1 = (val1 / max) * 100;
        const width2 = (val2 / max) * 100;
        const better1 = val1 > val2;
        const better2 = val2 > val1;

        return `
            <div class="mb-6">
                <div class="text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">${label}</div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="text-right">
                        <div class="text-lg font-bold ${better1 ? 'text-blue-500' : 'text-gray-900 dark:text-white'}">${val1}${suffix}</div>
                        <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden mt-2">
                            <div class="h-full bg-blue-500 rounded-full transition-all" style="width: ${width1}%"></div>
                        </div>
                    </div>
                    <div class="text-left">
                        <div class="text-lg font-bold ${better2 ? 'text-red-500' : 'text-gray-900 dark:text-white'}">${val2}${suffix}</div>
                        <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden mt-2">
                            <div class="h-full bg-red-500 rounded-full transition-all" style="width: ${width2}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    };

    document.getElementById('comparisonContent').innerHTML = `
        <!-- Player Headers -->
        <div class="grid grid-cols-2 gap-4 mb-8">
            <div class="text-center">
                <div class="h-20 w-20 rounded-full mx-auto mb-3 overflow-hidden bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                    ${p1.avatar_url ? `<img src="${p1.avatar_url}" class="w-full h-full object-cover">` : `<span class="text-2xl font-bold text-gray-600 dark:text-gray-300">${p1.nome.substr(0, 1)}</span>`}
                </div>
                <div class="text-lg font-bold text-blue-500">
                    <span data-color="${p1.active_name_color || ''}" data-style="${p1.active_name_style || ''}">${applyNameStyle(p1.nome, p1.active_name_style)}</span>
                </div>
            </div>
            <div class="text-center">
                <div class="h-20 w-20 rounded-full mx-auto mb-3 overflow-hidden bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                    ${p2.avatar_url ? `<img src="${p2.avatar_url}" class="w-full h-full object-cover">` : `<span class="text-2xl font-bold text-gray-600 dark:text-gray-300">${p2.nome.substr(0, 1)}</span>`}
                </div>
                <div class="text-lg font-bold text-red-500">
                    <span data-color="${p2.active_name_color || ''}" data-style="${p2.active_name_style || ''}">${applyNameStyle(p2.nome, p2.active_name_style)}</span>
                </div>
            </div>
        </div>
        
        <!-- ELO Comparison -->
        <div class="mb-8">
            <h4 class="text-lg font-bold text-gray-900 dark:text-white mb-4">ELO Rating</h4>
            ${createComparisonBar('ELO Generale', p1.elo_medio || 0, p2.elo_medio || 0)}
            ${createComparisonBar('ELO Attaccante', p1.elo_attaccante || 0, p2.elo_attaccante || 0)}
            ${createComparisonBar('ELO Portiere', p1.elo_portiere || 0, p2.elo_portiere || 0)}
        </div>
        
        <!-- Performance Stats -->
        <div class="mb-8">
            <h4 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Statistiche Prestazioni</h4>
            ${createComparisonBar('Partite Totali', p1.partite_totali || 0, p2.partite_totali || 0)}
            ${createComparisonBar('Vittorie Totali', p1.vittorie_totali || 0, p2.vittorie_totali || 0)}
            ${createComparisonBar('Win Rate Generale', parseFloat(p1WinRateOverall), parseFloat(p2WinRateOverall), '%')}
        </div>
        
        <!-- Role-Specific Stats -->
        <div>
            <h4 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Statistiche per Ruolo</h4>
            ${createComparisonBar('Win Rate Attaccante', parseFloat(p1WinRateAtk), parseFloat(p2WinRateAtk), '%')}
            ${createComparisonBar('Win Rate Portiere', parseFloat(p1WinRateDef), parseFloat(p2WinRateDef), '%')}
            ${createComparisonBar('Partite Attaccante', p1.partite_attaccante || 0, p2.partite_attaccante || 0)}
            ${createComparisonBar('Partite Portiere', p1.partite_portiere || 0, p2.partite_portiere || 0)}
        </div>
    `;
}

// Expose functions globally
window.openComparisonModal = openComparisonModal;
window.closeComparisonModal = closeComparisonModal;
window.updateComparison = updateComparison;

// --- INTELLIGENT MATCH SEARCH CHATBOT ---

function openMatchSearchModal() {
    document.getElementById('matchSearchModal').classList.remove('hidden');

    // Populate season dropdown
    const searchSeason = document.getElementById('searchSeason');
    if (searchSeason) {
        searchSeason.innerHTML = `
            <option value="0">Tutte le stagioni</option>
            ${stagioni.map(s => `<option value="${s.id}" ${s.id == activeSeasonId ? 'selected' : ''}>${s.nome}</option>`).join('')}
        `;
    }

    // Reset chat to welcome message only
    const chatMessages = document.getElementById('chatMessages');
    chatMessages.innerHTML = `
        <div class="flex gap-3">
            <div class="h-8 w-8 rounded-full bg-blue-500 flex items-center justify-center flex-shrink-0">
                <span class="text-white text-sm">🤖</span>
            </div>
            <div class="flex-1">
                <div class="bg-gray-100 dark:bg-gray-700 rounded-2xl rounded-tl-none p-4">
                    <p class="text-gray-900 dark:text-white text-sm">
                        Ciao! Sono il tuo assistente per la ricerca delle partite. Puoi chiedermi cose come:
                    </p>
                    <ul class="mt-2 text-xs text-gray-600 dark:text-gray-400 space-y-1">
                        <li>• "partite dove X ha vinto contro Y"</li>
                        <li>• "vittorie di X come attaccante"</li>
                        <li>• "ultime 10 partite"</li>
                        <li>• "scontri diretti X Y"</li>
                        <li>• "X Y vs Z W
                    </ul>
                </div>
            </div>
        </div>
    `;
    document.getElementById('searchQueryInput').value = '';
    document.getElementById('searchQueryInput').focus();
}

function closeMatchSearchModal() {
    document.getElementById('matchSearchModal').classList.add('hidden');
}

function submitSearchQuery(event) {
    event.preventDefault();
    const input = document.getElementById('searchQueryInput');
    const query = input.value.trim();

    if (!query) return;

    // Add user message
    addChatMessage(query, true);
    input.value = '';

    // Process query
    setTimeout(() => {
        processSearchQuery(query);
    }, 300);
}

function addChatMessage(text, isUser) {
    const chatMessages = document.getElementById('chatMessages');

    if (isUser) {
        chatMessages.innerHTML += `
            <div class="flex gap-3 justify-end">
                <div class="flex-1 max-w-[80%]">
                    <div class="bg-blue-500 text-white rounded-2xl rounded-tr-none p-4">
                        <p class="text-sm">${text}</p>
                    </div>
                </div>
                <div class="h-8 w-8 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center flex-shrink-0">
                    <span class="text-gray-700 dark:text-gray-300 text-sm">👤</span>
                </div>
            </div>
        `;
    } else {
        chatMessages.innerHTML += `
            <div class="flex gap-3">
                <div class="h-8 w-8 rounded-full bg-blue-500 flex items-center justify-center flex-shrink-0">
                    <span class="text-white text-sm">🤖</span>
                </div>
                <div class="flex-1">
                    <div class="bg-gray-100 dark:bg-gray-700 rounded-2xl rounded-tl-none p-4">
                        <p class="text-gray-900 dark:text-white text-sm">${text}</p>
                    </div>
                </div>
            </div>
        `;
    }

    // Scroll to bottom
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

// Levenshtein distance for fuzzy matching
function levenshteinDistance(str1, str2) {
    const matrix = [];

    for (let i = 0; i <= str2.length; i++) {
        matrix[i] = [i];
    }

    for (let j = 0; j <= str1.length; j++) {
        matrix[0][j] = j;
    }

    for (let i = 1; i <= str2.length; i++) {
        for (let j = 1; j <= str1.length; j++) {
            if (str2.charAt(i - 1) === str1.charAt(j - 1)) {
                matrix[i][j] = matrix[i - 1][j - 1];
            } else {
                matrix[i][j] = Math.min(
                    matrix[i - 1][j - 1] + 1,
                    matrix[i][j - 1] + 1,
                    matrix[i - 1][j] + 1
                );
            }
        }
    }

    return matrix[str2.length][str1.length];
}

// Fuzzy match player name
function findPlayer(name) {
    if (!name || name.length < 3) return null;

    const normalized = name.toLowerCase().trim();

    // Exact match first
    let match = giocatori.find(p => p.nome.toLowerCase() === normalized);
    if (match) {
        return match;
    }

    // Fuzzy match with stricter criteria
    let bestMatch = null;
    let bestScore = 0;

    giocatori.forEach(player => {
        const playerName = player.nome.toLowerCase();
        const distance = levenshteinDistance(normalized, playerName);
        const maxLen = Math.max(normalized.length, playerName.length);
        const similarity = 1 - (distance / maxLen); // 0 to 1, higher is better

        // Check if query is substring (very good match)
        if (playerName.includes(normalized) || normalized.includes(playerName)) {
            if (similarity > bestScore) {
                bestScore = similarity;
                bestMatch = player;
            }
        } else {
            // For non-substring matches, require high similarity (at least 70%)
            if (similarity >= 0.7 && similarity > bestScore) {
                bestScore = similarity;
                bestMatch = player;
            }
        }
    });

    // Only return if we found a good match (at least 60% similar)
    if (bestScore >= 0.6) {
        return bestMatch;
    }

    return null;
}

// Extract player names from query
function extractPlayerNames(query) {
    const words = query.trim().split(/\s+/);
    const players = [];
    const usedIndices = new Set();

    // First pass: look for exact or very close matches
    for (let i = 0; i < words.length; i++) {
        if (usedIndices.has(i)) continue;

        const word = words[i];

        // Skip common words
        const skipWords = ['partite', 'dove', 'ha', 'vinto', 'contro', 'come', 'attaccante', 'portiere', 'ultime', 'di', 'le', 'la', 'il', 'un', 'una', 'e', 'o', 'che', 'per', 'con', 'da', 'su', 'in', 'a', 'vs'];
        if (skipWords.includes(word.toLowerCase())) continue;

        // Try two-word combinations first (priority)
        if (i < words.length - 1 && !usedIndices.has(i + 1)) {
            const twoWords = words[i] + ' ' + words[i + 1];
            const player2 = findPlayer(twoWords);
            if (player2 && !players.find(p => p.id === player2.id)) {
                players.push(player2);
                usedIndices.add(i);
                usedIndices.add(i + 1);
                continue; // Move to next possible word
            }
        }

        // Try single word
        const player = findPlayer(word);
        if (player && !players.find(p => p.id === player.id)) {
            players.push(player);
            usedIndices.add(i);
        }
    }

    return players;
}

// Determine query type and extract info
function analyzeQuery(query) {
    const normalized = query.toLowerCase();
    const vsSeparator = normalized.match(/\s+vs\s+|\s+contro\s+/i);

    const analysis = {
        type: 'general',
        players: [],
        side1: [],
        side2: [],
        role: null,
        result: null,
        limit: null
    };

    if (vsSeparator) {
        const parts = query.split(vsSeparator[0]);
        analysis.side1 = extractPlayerNames(parts[0]);
        analysis.side2 = extractPlayerNames(parts[1]);
        analysis.players = [...analysis.side1, ...analysis.side2];

        if (analysis.side1.length > 0 && analysis.side2.length > 0) {
            analysis.type = 'h2h';
        }
    } else {
        analysis.players = extractPlayerNames(query);
    }

    // Determine type if not already set
    if (analysis.type === 'general') {
        if (analysis.players.length >= 2) {
            analysis.type = 'h2h';
            analysis.side1 = [analysis.players[0]];
            analysis.side2 = [analysis.players[1]];
        } else if (analysis.players.length === 1) {
            analysis.type = 'player';
        } else if (normalized.match(/ultim[ei]|recenti?/i)) {
            analysis.type = 'recent';
        } else {
            analysis.type = 'all';
        }
    }

    // Extract role
    if (normalized.match(/attaccante|attacco|att/i)) {
        analysis.role = 'attaccante';
    } else if (normalized.match(/portiere|difesa|por|def/i)) {
        analysis.role = 'portiere';
    }

    // Extract result
    if (normalized.match(/vinc|vitt|won/i)) {
        analysis.result = 'win';
    } else if (normalized.match(/pers|sconfit|lost/i)) {
        analysis.result = 'loss';
    }

    // Extract limit
    const limitMatch = normalized.match(/ultim[ei]\s+(\d+)|(\d+)\s+partite/i);
    if (limitMatch) {
        analysis.limit = parseInt(limitMatch[1] || limitMatch[2]);
    }

    // Extract season
    const seasonMatch = normalized.match(/stagione\s+(\d+)/i);
    if (seasonMatch) {
        analysis.seasonId = parseInt(seasonMatch[1]);
    }

    return analysis;
}

// Filter matches based on criteria
function filterMatchesByCriteria(criteria) {
    let filtered = [...partite];

    // Season filtering
    const dropdownSeasonId = parseInt(document.getElementById('searchSeason')?.value || 0);
    const seasonId = criteria.seasonId || dropdownSeasonId;

    if (seasonId !== 0) {
        filtered = filtered.filter(m => m.stagione_id == seasonId);
    }

    if (criteria.type === 'h2h' && (criteria.side1.length > 0 && criteria.side2.length > 0)) {
        const s1 = criteria.side1.map(p => p.id);
        const s2 = criteria.side2.map(p => p.id);

        filtered = filtered.filter(match => {
            const team1 = [match.squadra1_portiere, match.squadra1_attaccante];
            const team2 = [match.squadra2_portiere, match.squadra2_attaccante];

            // Side 1 could be Team 1 or Team 2
            const s1InT1 = s1.every(id => team1.some(tid => tid == id));
            const s1InT2 = s1.every(id => team2.some(tid => tid == id));
            const s2InT1 = s2.every(id => team1.some(tid => tid == id));
            const s2InT2 = s2.every(id => team2.some(tid => tid == id));

            return (s1InT1 && s2InT2) || (s1InT2 && s2InT1);
        });

        // Filter by result if specified
        if (criteria.result === 'win') {
            filtered = filtered.filter(match => {
                const team1 = [match.squadra1_portiere, match.squadra1_attaccante];
                // Check if Side 1 is Team 1 or 2
                const side1IsTeam1 = s1.every(id => team1.some(tid => tid == id));
                const winningTeam = match.vincitore;
                return side1IsTeam1 ? winningTeam == 1 : winningTeam == 2;
            });
        } else if (criteria.result === 'loss') {
            filtered = filtered.filter(match => {
                const team1 = [match.squadra1_portiere, match.squadra1_attaccante];
                const side1IsTeam1 = s1.every(id => team1.some(tid => tid == id));
                const winningTeam = match.vincitore;
                return side1IsTeam1 ? winningTeam != 1 : winningTeam != 2;
            });
        }

    } else if (criteria.type === 'player' && criteria.players.length === 1) {
        const player = criteria.players[0];

        filtered = filtered.filter(match => {
            const players = [
                match.squadra1_portiere, match.squadra1_attaccante,
                match.squadra2_portiere, match.squadra2_attaccante
            ];
            // Use == instead of === to handle string/number comparison
            return players.some(id => id == player.id);
        });

        // Filter by role
        if (criteria.role === 'attaccante') {
            filtered = filtered.filter(match =>
                match.squadra1_attaccante == player.id || match.squadra2_attaccante == player.id
            );
        } else if (criteria.role === 'portiere') {
            filtered = filtered.filter(match =>
                match.squadra1_portiere == player.id || match.squadra2_portiere == player.id
            );
        }

        // Filter by result
        if (criteria.result === 'win') {
            filtered = filtered.filter(match => {
                const playerTeam = (match.squadra1_portiere == player.id || match.squadra1_attaccante == player.id) ? 1 : 2;
                return match.vincitore == playerTeam;
            });
        } else if (criteria.result === 'loss') {
            filtered = filtered.filter(match => {
                const playerTeam = (match.squadra1_portiere == player.id || match.squadra1_attaccante == player.id) ? 1 : 2;
                return match.vincitore != playerTeam;
            });
        }
    } else if (criteria.type === 'recent') {
        // Already sorted by date, just limit
    }

    // Apply limit
    if (criteria.limit) {
        filtered = filtered.slice(0, criteria.limit);
    }

    return filtered;
}

// Calculate head-to-head stats
function calculateHeadToHead(side1, side2, seasonId = 0) {
    const s1Ids = side1.map(p => p.id);
    const s2Ids = side2.map(p => p.id);

    let baseMatches = partite;
    if (seasonId !== 0) {
        baseMatches = partite.filter(m => m.stagione_id == seasonId);
    }

    const h2hMatches = baseMatches.filter(match => {
        const team1 = [match.squadra1_portiere, match.squadra1_attaccante];
        const team2 = [match.squadra2_portiere, match.squadra2_attaccante];

        const s1InT1 = s1Ids.every(id => team1.some(tid => tid == id));
        const s1InT2 = s1Ids.every(id => team2.some(tid => tid == id));
        const s2InT1 = s2Ids.every(id => team1.some(tid => tid == id));
        const s2InT2 = s2Ids.every(id => team2.some(tid => tid == id));

        return (s1InT1 && s2InT2) || (s1InT2 && s2InT1);
    });

    let p1Wins = 0;
    let p2Wins = 0;

    h2hMatches.forEach(match => {
        const team1 = [match.squadra1_portiere, match.squadra1_attaccante];
        const side1IsTeam1 = s1Ids.every(id => team1.some(tid => tid == id));
        const winningTeam = match.vincitore;

        if (side1IsTeam1) {
            if (winningTeam == 1) p1Wins++;
            else p2Wins++;
        } else {
            if (winningTeam == 2) p1Wins++;
            else p2Wins++;
        }
    });

    return {
        total: h2hMatches.length,
        p1Wins,
        p2Wins,
        matches: h2hMatches
    };
}

// Process search query
function processSearchQuery(query) {
    const criteria = analyzeQuery(query);
    const results = filterMatchesByCriteria(criteria);

    // Get final season context for response text
    const dropdownSeasonId = parseInt(document.getElementById('searchSeason')?.value || 0);
    const seasonId = criteria.seasonId || dropdownSeasonId;
    const seasonName = seasonId !== 0 ? (stagioni.find(s => s.id == seasonId)?.nome || `Stagione ${seasonId}`) : "tutte le stagioni";

    // Generate response
    let responseText = '';
    let matchesHTML = '';

    if (results.length === 0) {
        // More helpful error message
        if (criteria.players.length === 0 && criteria.type !== 'recent' && criteria.type !== 'all') {
            responseText = "Non ho riconosciuto nessun nome di giocatore nella tua richiesta. Prova con nomi completi o controlla l'ortografia.";
        } else if (criteria.players.length > 0) {
            const playerNames = criteria.players.map(p => p.nome).join(', ');
            responseText = `Ho riconosciuto: ${playerNames}, ma non ho trovato partite con questi criteri in <strong>${seasonName}</strong>. Prova a riformulare la domanda o cambiare stagione.`;
        } else {
            responseText = `Non ho trovato partite che corrispondono alla tua richiesta in <strong>${seasonName}</strong>.`;
        }
    } else {
        // Build response based on type
        if (criteria.type === 'h2h' && criteria.side1.length > 0 && criteria.side2.length > 0) {
            const h2h = calculateHeadToHead(criteria.side1, criteria.side2, seasonId);
            const s1Names = criteria.side1.map(p => p.nome).join(' + ');
            const s2Names = criteria.side2.map(p => p.nome).join(' + ');

            if (criteria.result === 'win') {
                responseText = `Ho trovato ${results.length} partit${results.length === 1 ? 'a' : 'e'} in <strong>${seasonName}</strong> dove <strong>${s1Names}</strong> hanno vinto contro <strong>${s2Names}</strong>`;
            } else if (criteria.result === 'loss') {
                responseText = `Ho trovato ${results.length} partit${results.length === 1 ? 'a' : 'e'} in <strong>${seasonName}</strong> dove <strong>${s1Names}</strong> hanno perso contro <strong>${s2Names}</strong>`;
            } else {
                responseText = `Ho trovato ${h2h.total} partit${h2h.total === 1 ? 'a' : 'e'} in <strong>${seasonName}</strong> tra <strong>${s1Names}</strong> e <strong>${s2Names}</strong>`;
            }

            // Add H2H stats
            responseText += `<br><br><strong>Scontri Diretti (${seasonName}):</strong><br>`;
            responseText += `${s1Names}: ${h2h.p1Wins} vittori${h2h.p1Wins === 1 ? 'a' : 'e'}<br>`;
            responseText += `${s2Names}: ${h2h.p2Wins} vittori${h2h.p2Wins === 1 ? 'a' : 'e'}`;

        } else if (criteria.type === 'player' && criteria.players.length === 1) {
            const player = criteria.players[0];
            let desc = '';

            if (criteria.result === 'win') {
                desc = 'vittorie';
            } else if (criteria.result === 'loss') {
                desc = 'sconfitte';
            } else {
                desc = 'partite';
            }

            if (criteria.role) {
                desc += ` come ${criteria.role}`;
            }

            responseText = `Ho trovato ${results.length} ${desc} di <strong class="aesthetic-name"><span data-color="${player.active_name_color || ''}">${player.nome}</span></strong> in <strong>${seasonName}</strong>`;

        } else if (criteria.type === 'recent') {
            responseText = `Ecco le ultime ${results.length} partite in <strong>${seasonName}</strong>`;
        } else {
            responseText = `Ho trovato ${results.length} partit${results.length === 1 ? 'a' : 'e'} in <strong>${seasonName}</strong>`;
        }

        // Generate match cards HTML
        matchesHTML = '<div class="mt-4 space-y-2 max-h-64 overflow-y-auto">';
        results.forEach(match => {
            matchesHTML += createMatchHTML(match, true);
        });
        matchesHTML += '</div>';
    }

    // Add bot response
    addChatMessage(responseText + matchesHTML, false);
}

// Expose functions globally
window.openMatchSearchModal = openMatchSearchModal;
window.closeMatchSearchModal = closeMatchSearchModal;
window.submitSearchQuery = submitSearchQuery;
window.loadMoreMatches = loadMoreMatches;

async function loadSeasonPass() {
    try {
        const res = await fetch('season_pass_api.php?action=get_progress');
        const data = await res.json();
        console.log("Season Pass Data:", data);

        if (data.success) {
            document.getElementById('sp-current-level').textContent = `LV ${data.level}`;
            document.getElementById('sp-total-xp').textContent = data.total_xp.toLocaleString();

            const progressPercent = (data.xp_current / data.xp_next) * 100;
            document.getElementById('sp-progress-bar').style.width = `${progressPercent}%`;
            document.getElementById('sp-xp-label').textContent = `${data.xp_current} / ${data.xp_next} XP`;
            document.getElementById('sp-next-level-target').textContent = `LV ${data.level + 1}`;

            renderRewardTrack(data.rewards, data.level, data.claimed_levels || []);
            renderObjectives(data.objectives);
        } else {
            console.error("API success false", data);
            document.getElementById('sp-reward-track').innerHTML = `<div class="p-8 text-center text-gray-500">
                <span class="material-symbols-outlined text-4xl mb-2">error</span>
                <p>${data.error || 'Errore durante il caricamento dei dati'}</p>
                ${data.error === 'Giocatore non trovato' ? '<button onclick="toggleAuthModal()" class="mt-4 px-4 py-2 bg-primary text-white rounded-lg">Accedi</button>' : ''}
            </div>`;
        }
    } catch (e) {
        console.error("Failed to load season pass", e);
        document.getElementById('sp-reward-track').innerHTML = `<div class="p-8 text-center text-red-500">Errore di connessione al server</div>`;
    }
}

function renderRewardTrack(rewards, userLevel, claimedLevels = []) {
    const container = document.getElementById('sp-reward-track');
    if (!container) return;

    container.innerHTML = `
        <div class="absolute left-6 top-8 bottom-8 w-1 bg-gray-100 dark:bg-gray-800 -z-10"></div>
    `;

    rewards.forEach(reward => {
        const isReached = userLevel >= reward.level;
        const isClaimed = claimedLevels.includes(parseInt(reward.level));
        const isNext = reward.level === userLevel + 1;

        let statusClass = "bg-gray-50 dark:bg-gray-800/50 border-gray-100 dark:border-gray-700 opacity-60";
        let iconBg = "bg-gray-200 dark:bg-gray-700 text-gray-400";
        let actionEl = "";

        if (isReached) {
            if (isClaimed) {
                statusClass = "bg-green-50/50 dark:bg-green-900/10 border-green-500/20";
                iconBg = "bg-green-500 text-white shadow-sm";
                actionEl = `
                    <div class="flex items-center gap-1 text-green-600 dark:text-green-400 font-bold text-[10px] uppercase tracking-wider">
                        <span class="material-symbols-outlined text-[14px]">check_circle</span>
                        Riscattato
                    </div>
                `;
            } else {
                statusClass = "bg-white dark:bg-gray-800 border-indigo-500/50 shadow-xl shadow-indigo-500/5 ring-1 ring-indigo-500/20";
                iconBg = "bg-indigo-600 text-white shadow-md";
                actionEl = `
                    <button onclick="claimSeasonReward(${reward.level})" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-[10px] font-black uppercase tracking-widest rounded-xl transition-all shadow-lg shadow-indigo-600/25 active:scale-95">
                        Riscatta
                    </button>
                `;
            }
        } else if (isNext) {
            statusClass = "bg-indigo-50/30 dark:bg-indigo-900/5 border-dashed border-indigo-500/30";
            iconBg = "bg-gray-300 dark:bg-gray-700 text-gray-500";
            actionEl = `
                <div class="flex flex-col items-center opacity-40">
                    <span class="material-symbols-outlined text-gray-400">lock</span>
                    <span class="text-[8px] font-bold uppercase mt-1">Livello ${reward.level}</span>
                </div>
            `;
        } else {
            actionEl = `
                <span class="material-symbols-outlined text-gray-300 dark:text-gray-700">lock</span>
            `;
        }

        const colorAttr = reward.item_key && reward.item_key.startsWith('color_') ? `data-color="${reward.item_key.replace('color_', '')}"` : '';
        const styleAttr = reward.item_key && reward.item_key.startsWith('style_') ? `data-style="${reward.item_key.replace('style_', '')}"` : '';
        const nameHtml = `<span ${colorAttr} ${styleAttr}>${reward.reward_name}</span>`;

        const div = document.createElement('div');
        div.className = `relative flex items-center gap-4 p-4 rounded-2xl border transition-all ${statusClass}`;
        div.innerHTML = `
            <div class="shrink-0 w-12 h-12 rounded-xl flex items-center justify-center font-black text-xs ${iconBg} z-10">
                ${isClaimed ? '<span class="material-symbols-outlined text-xl">verified</span>' : `LV ${reward.level}`}
            </div>
            
            <div class="flex-1">
                <div class="flex items-center justify-between mb-0.5">
                    <h4 class="font-black text-gray-900 dark:text-white text-sm uppercase tracking-tight">${nameHtml}</h4>
                </div>
                <div class="flex items-center gap-1.5 opacity-70">
                    <span class="material-symbols-outlined text-sm">${reward.icon || 'star'}</span>
                    <p class="text-[10px] font-bold uppercase truncate max-w-[150px]">${reward.description || 'Premio Season Pass'}</p>
                </div>
            </div>

            <div class="shrink-0">
                ${actionEl}
            </div>
        `;
        container.appendChild(div);
    });
}

async function claimSeasonReward(level) {
    try {
        const res = await fetch('season_pass_api.php?action=claim_reward', {
            method: 'POST',
            body: JSON.stringify({ level: level }),
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await res.json();
        
        if (data.success) {
            showToast("Premio riscattato con successo!", 'success');
            loadSeasonPass(); // Refresh UI
            checkSeasonPassNotifications();
            // Also refresh profile if visible
            if (typeof renderProfile === 'function') renderProfile();
        } else {
            showToast(data.error || "Errore durante il riscatto", 'error');
        }
    } catch (e) {
        console.error("Claim error", e);
        showToast("Errore di connessione", 'error');
    }
}
window.claimSeasonReward = claimSeasonReward;

// Ensure globally accessible
window.loadSeasonPass = loadSeasonPass;
window.renderRewardTrack = renderRewardTrack;
window.renderObjectives = renderObjectives;

window.switchSPTab = function (tabId) {
    const rewardsBtn = document.getElementById('sp-tab-rewards');
    const missionsBtn = document.getElementById('sp-tab-missions');
    const rewardsContent = document.getElementById('sp-content-rewards');
    const missionsContent = document.getElementById('sp-content-missions');

    if (tabId === 'rewards') {
        rewardsBtn.classList.add('bg-white', 'dark:bg-gray-800', 'text-gray-900', 'dark:text-white', 'shadow-sm');
        rewardsBtn.classList.remove('text-gray-500', 'dark:text-gray-400');
        missionsBtn.classList.remove('bg-white', 'dark:bg-gray-800', 'text-gray-900', 'dark:text-white', 'shadow-sm');
        missionsBtn.classList.add('text-gray-500', 'dark:text-gray-400');
        rewardsContent.classList.remove('hidden');
        missionsContent.classList.add('hidden');
    } else {
        missionsBtn.classList.add('bg-white', 'dark:bg-gray-800', 'text-gray-900', 'dark:text-white', 'shadow-sm');
        missionsBtn.classList.remove('text-gray-500', 'dark:text-gray-400');
        rewardsBtn.classList.remove('bg-white', 'dark:bg-gray-800', 'text-gray-900', 'dark:text-white', 'shadow-sm');
        rewardsBtn.classList.add('text-gray-500', 'dark:text-gray-400');
        rewardsContent.classList.add('hidden');
        missionsContent.classList.remove('hidden');
    }
};

function renderObjectives(objectives) {
    const dailyList = document.getElementById('sp-daily-list');
    const seasonalList = document.getElementById('sp-seasonal-list');
    if (!dailyList || !seasonalList) return;

    dailyList.innerHTML = '';
    seasonalList.innerHTML = '';

    objectives.forEach(obj => {
        const isDone = obj.completed;
        const percent = Math.min(100, (obj.current_value / obj.target_value) * 100);

        const html = `
            <div class="bg-white dark:bg-gray-800 border ${isDone ? 'border-green-500/30' : 'border-gray-100 dark:border-gray-700/50'} rounded-2xl p-4 transition-all">
                <div class="flex items-center gap-3 mb-3">
                    <div class="h-10 w-10 rounded-xl ${isDone ? 'bg-green-500 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-500'} flex items-center justify-center">
                        <span class="material-symbols-outlined">${isDone ? 'check' : obj.icon || 'task_alt'}</span>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <h4 class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-tight">${obj.name}</h4>
                            <span class="text-[10px] font-black text-indigo-500 uppercase">+${obj.xp_reward} XP</span>
                        </div>
                        <p class="text-[10px] text-gray-500 dark:text-gray-400 font-medium">${obj.description}</p>
                    </div>
                </div>
                
                <div class="space-y-1.5">
                    <div class="h-1.5 w-full bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                        <div class="h-full ${isDone ? 'bg-green-500' : 'bg-indigo-500'} transition-all duration-700" style="width: ${percent}%"></div>
                    </div>
                    <div class="flex justify-between text-[9px] font-black uppercase tracking-widest ${isDone ? 'text-green-500' : 'text-gray-400'}">
                        <span>${isDone ? 'Completato' : 'In Corso'}</span>
                        <span>${obj.current_value} / ${obj.target_value}</span>
                    </div>
                </div>
            </div>
        `;

        if (obj.type === 'daily') {
            dailyList.insertAdjacentHTML('beforeend', html);
        } else {
            seasonalList.insertAdjacentHTML('beforeend', html);
        }
    });
}

async function checkSeasonPassNotifications() {
    if (!currentUser) return;
    try {
        const res = await fetch('season_pass_api.php?action=get_progress');
        const data = await res.json();
        if (data.success) {
            const hasUnclaimed = data.rewards.some(reward => 
                data.level >= reward.level && !(data.claimed_levels || []).includes(parseInt(reward.level))
            );
            updateSPButtonNotification(hasUnclaimed);
        }
    } catch (e) {
        console.error("SP notification check failed", e);
    }
}

function updateSPButtonNotification(hasUnclaimed) {
    const btnMobile = document.getElementById('sp-nav-btn-mobile');
    const btnDesktop = document.getElementById('sp-nav-btn-desktop');
    
    [btnMobile, btnDesktop].forEach(btn => {
        if (!btn) return;
        
        // Add a dot if not exists
        let dot = btn.querySelector('.notification-dot');
        if (hasUnclaimed) {
            btn.classList.add('premium-glow-indigo');
            if (!dot) {
                dot = document.createElement('div');
                dot.className = 'notification-dot absolute top-1 right-1 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white dark:border-gray-800 animate-pulse z-20';
                btn.style.position = 'relative';
                btn.appendChild(dot);
            }
        } else {
            btn.classList.remove('premium-glow-indigo');
            if (dot) dot.remove();
        }
    });
}
