<?php
// scoreboard.php
// Standalone scoreboard page for iPad/Tablet display - PREMIUM REDESIGN
require_once 'database.php';
session_start();

$db = new Database();
$conn = $db->getConnection();
?>
<!DOCTYPE html>
<html lang="it" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Scalcetting Scoreboard</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700;800;900&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#0284c7", // Sky 600
                        "primary-glow": "rgba(2, 132, 199, 0.15)",
                        "red-glow": "rgba(220, 38, 38, 0.15)",
                        "background-light": "#f8fafc",
                        "panel-light": "rgba(255, 255, 255, 0.5)",
                    },
                    fontFamily: {
                        "display": ["Lexend", "sans-serif"]
                    },
                    boxShadow: {
                        "glow-blue": "0 20px 40px rgba(14, 165, 233, 0.15)",
                        "glow-red": "0 20px 40px rgba(239, 68, 68, 0.15)",
                        "glass": "0 8px 32px 0 rgba(31, 38, 135, 0.07)",
                    },
                    backgroundImage: {
                        "radial-light": "radial-gradient(circle at center, #ffffff 0%, #f1f5f9 100%)",
                    }
                },
            },
        }
    </script>
    <style>
        body {
            overflow: hidden;
            background: #f8fafc;
            background: radial-gradient(circle at 50% 50%, #ffffff 0%, #f1f5f9 100%);
            color: #0f172a;
        }
        
        .glass-panel {
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.08);
        }

        .score-number {
            font-variant-numeric: tabular-nums;
            letter-spacing: -4px;
            font-weight: 900;
            filter: drop-shadow(0 0 15px currentColor);
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes score-hit {
            0% { transform: scale(1); }
            50% { transform: scale(1.3) rotate(5deg); filter: drop-shadow(0 0 40px currentColor) brightness(1.5); }
            100% { transform: scale(1); }
        }

        .animate-score-hit {
            animation: score-hit 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes float-background {
            0% { transform: translate(0, 0); }
            50% { transform: translate(-20px, 20px); }
            100% { transform: translate(0, 0); }
        }

        .bg-glow-blur {
            position: absolute;
            width: 600px;
            height: 600px;
            border-radius: 50%;
            filter: blur(120px);
            z-index: -1;
            opacity: 0.15;
            animation: float-background 20s infinite ease-in-out;
        }

        /* Custom Scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.05);
            border-radius: 2px;
        }

        /* --- Aesthetic Customizations --- */
        [data-aura="flare"] {
            box-shadow: 0 0 15px 5px rgba(251, 191, 36, 0.6), 0 0 30px 10px rgba(251, 191, 36, 0.3);
            animation: aura-flare-pulse 2s infinite alternate ease-in-out;
            border-color: #fbbf24 !important;
        }
        @keyframes aura-flare-pulse {
            0% { box-shadow: 0 0 10px 2px rgba(251, 191, 36, 0.4); transform: scale(1); }
            100% { box-shadow: 0 0 25px 8px rgba(251, 191, 36, 0.7); transform: scale(1.02); }
        }
        [data-aura="fire"] {
            position: relative;
            box-shadow: 0 0 20px #ff4d00;
            border-color: #ff4d00 !important;
        }
        [data-aura="fire"]::after {
            content: '';
            position: absolute;
            inset: -15px;
            border-radius: 50%;
            background: 
                radial-gradient(circle at var(--c1-x, 50%) var(--c1-y, 20%), #ff4d00 0%, #ff0000 40%, transparent 60%),
                radial-gradient(circle at var(--c2-x, 80%) var(--c2-y, 35%), #ff4d00 0%, #ff0000 40%, transparent 60%),
                radial-gradient(circle at var(--c3-x, 90%) var(--c3-y, 65%), #ff4d00 0%, #ff0000 40%, transparent 60%),
                radial-gradient(circle at var(--c4-x, 65%) var(--c4-y, 90%), #ff4d00 0%, #ff0000 40%, transparent 60%),
                radial-gradient(circle at var(--c5-x, 35%) var(--c5-y, 90%), #ff4d00 0%, #ff0000 40%, transparent 60%),
                radial-gradient(circle at var(--c6-x, 10%) var(--c6-y, 65%), #ff4d00 0%, #ff0000 40%, transparent 60%),
                radial-gradient(circle at var(--c7-x, 20%) var(--c7-y, 35%), #ff4d00 0%, #ff0000 40%, transparent 60%),
                radial-gradient(circle at var(--c8-x, 50%) var(--c8-y, 50%), #ff4d00 0%, #ff0000 70%, transparent 75%);
            background-size: 100% 100%;
            filter: blur(4px) contrast(20);
            z-index: -2;
            animation: fire-corona 2s infinite ease-in-out;
            opacity: 1;
            mix-blend-mode: screen;
        }
        [data-aura="fire"]::before {
            content: '';
            position: absolute;
            inset: -8px;
            border-radius: 50%;
            background: radial-gradient(circle, transparent 60%, rgba(255, 255, 0, 0.4) 70%, #ffff00 85%, #ff8c00 100%);
            filter: blur(2px);
            z-index: -1;
            animation: fire-corona-inner 1s infinite alternate ease-in-out;
        }
        @keyframes fire-corona {
            0%, 100% { 
                --c1-y: 20%; --c2-x: 80%; --c3-y: 65%; --c4-x: 65%;
                --c5-y: 90%; --c6-x: 10%; --c7-y: 35%; --c8-x: 50%;
                transform: rotate(0deg) scale(1);
            }
            50% {
                --c1-y: 15%; --c2-x: 85%; --c3-y: 70%; --c4-x: 70%;
                --c5-y: 95%; --c6-x: 5%; --c7-y: 30%; --c8-x: 52%;
                transform: rotate(180deg) scale(1.1);
            }
        }
        @keyframes fire-corona-inner {
            0% { transform: scale(1); opacity: 0.6; }
            100% { transform: scale(1.05); opacity: 1; }
        }
        [data-color="gold"] {
            background: linear-gradient(to right, #bf953f, #fcf6ba, #b38728, #fbf5b7, #aa771c) !important;
            -webkit-background-clip: text !important;
            background-clip: text !important;
            -webkit-text-fill-color: transparent !important;
            font-weight: 800 !important;
            filter: drop-shadow(0 0 1px rgba(191, 149, 63, 0.5)) !important;
        }
        [data-color="neon"] {
            color: #ff00ff !important;
            text-shadow: 0 0 5px #ff00ff, 0 0 10px #ff00ff, 0 0 20px #ff00ff !important;
            font-weight: 800 !important;
        }
        [data-color="rainbow"] {
            background: linear-gradient(to right, #ef4444, #f59e0b, #10b981, #3b82f6, #6366f1, #8b5cf6, #ef4444) !important;
            -webkit-background-clip: text !important;
            background-clip: text !important;
            -webkit-text-fill-color: transparent !important;
            font-weight: 800 !important;
        }
        [data-color="lime"] { color: #84cc16 !important; text-shadow: 0 0 8px rgba(132, 204, 22, 0.4) !important; font-weight: 800 !important; }
        [data-color="red"] { color: #ef4444 !important; text-shadow: 0 0 8px rgba(239, 68, 68, 0.4) !important; font-weight: 800 !important; }
        [data-color="orange"] { color: #f97316 !important; text-shadow: 0 0 8px rgba(249, 115, 22, 0.4) !important; font-weight: 800 !important; }

        .aura-flare {
            background: radial-gradient(circle, rgba(99, 102, 241, 0.6) 0%, rgba(99, 102, 241, 0) 70%) !important;
            filter: blur(8px) !important;
            animation: aura-pulse 2s ease-in-out infinite !important;
        }
        .aura-fire {
            background: radial-gradient(circle, rgba(245, 158, 11, 0.6) 0%, rgba(245, 158, 11, 0) 70%) !important;
            filter: blur(8px) !important;
            animation: aura-pulse 1.5s ease-in-out infinite alternate !important;
        }
        @keyframes aura-pulse {
            0% { transform: scale(0.8); opacity: 0.5; }
            50% { transform: scale(1.2); opacity: 0.8; }
            100% { transform: scale(0.8); opacity: 0.5; }
        }
        .player-title {
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: -2px;
            opacity: 0.8;
            color: #64748b; /* Slate 500 */
        }

    </style>
</head>
<body class="dark h-screen w-screen overflow-hidden flex flex-col items-center justify-center font-display antialiased p-4 sm:p-6 text-white relative">
    
    <!-- Animated background glows -->
    <div class="bg-glow-blur bg-sky-500 top-[-200px] left-[-200px]"></div>
    <div class="bg-glow-blur bg-red-500 bottom-[-200px] right-[-200px]"></div>

    <!-- Top Bar -->
    <div class="fixed top-0 left-0 right-0 h-16 md:h-20 flex items-center justify-between px-6 md:px-12 z-40">
        <div id="status-indicator" class="flex items-center gap-2 bg-white/10 backdrop-blur-md px-4 py-2 rounded-xl border border-white/10 shadow-sm pointer-events-none">
            <div class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse shadow-[0_0_8px_rgba(34,197,94,0.8)]"></div>
            <span class="text-[9px] uppercase font-black tracking-widest text-white/60">Live Connected</span>
        </div>

        <div id="table-indicator-badge" class="flex items-center gap-2 bg-indigo-500/20 backdrop-blur-md px-4 py-2 rounded-xl border border-indigo-500/30 shadow-lg shadow-indigo-500/10 opacity-0 transition-opacity duration-500 pointer-events-none">
            <span class="material-symbols-outlined text-[18px] text-indigo-400">table_bar</span>
            <span class="text-[10px] uppercase font-black tracking-widest text-indigo-100">Caricamento Tavolo...</span>
        </div>

        <div class="hidden md:flex items-center gap-2 bg-white/10 backdrop-blur-md px-4 py-2 rounded-xl border border-white/10 shadow-sm pointer-events-none">
            <span class="text-[9px] uppercase font-black tracking-widest text-white/60" id="current-time">00:00</span>
        </div>
    </div>
    
    <!-- Main Scoreboard Container -->
    <div id="scoreboard-container" class="w-full h-full max-w-7xl flex flex-col md:flex-row items-stretch justify-center gap-6 md:gap-8 lg:gap-12 mt-20 mb-32 transition-all duration-700">
        
        <!-- Team BLU (S1) -->
        <div class="flex-1 flex flex-col items-center justify-between glass-panel rounded-[2.5rem] p-6 md:p-8 relative overflow-hidden group shadow-glow-blue border-sky-500/20">
            <div class="absolute top-0 left-0 right-0 h-1.5 bg-sky-500 shadow-[0_0_15px_rgba(14,165,233,0.8)]"></div>
            
            <div class="text-center z-10 w-full">
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-sky-50 border border-sky-100 mb-4">
                    <div class="w-1.5 h-1.5 rounded-full bg-sky-500"></div>
                    <span class="text-xs font-black uppercase tracking-widest text-sky-600">Squadra Blu</span>
                </div>
            </div>
            
            <div class="flex-1 flex items-center justify-center w-full z-10 relative">
                <div class="absolute left-0 lg:left-4 z-20">
                    <button onclick="adjustScore(1, 'sub_goal')" class="h-16 w-16 md:h-20 md:w-20 rounded-2xl glass-panel hover:bg-white flex items-center justify-center transition-all active:scale-90 border-white pointer-events-auto">
                        <span class="material-symbols-outlined text-3xl md:text-4xl text-slate-300">remove</span>
                    </button>
                </div>
                
                <div class="text-[12rem] md:text-[18rem] lg:text-[22rem] score-number text-sky-500 leading-none select-none px-12" id="score-s1">0</div>
                
                <div class="absolute right-0 lg:right-4 z-20">
                    <button onclick="adjustScore(1, 'add_goal')" class="h-16 w-16 md:h-20 md:w-20 rounded-2xl bg-sky-500 shadow-lg shadow-sky-500/30 flex items-center justify-center transition-all hover:scale-105 active:scale-90 pointer-events-auto">
                        <span class="material-symbols-outlined text-3xl md:text-4xl font-bold">add</span>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 md:gap-8 w-full z-10 mt-4" id="players-s1">
                <!-- Players will be injected here -->
            </div>
        </div>

        <!-- Center VS -->
        <div class="hidden md:flex flex-col items-center justify-center z-20">
            <div class="h-32 w-px bg-gradient-to-t from-white/20 to-transparent"></div>
            <div class="w-16 h-16 rounded-full glass-panel flex items-center justify-center border-white/20 my-4">
                <span class="text-2xl font-black text-white/20 tracking-tighter">VS</span>
            </div>
            <div class="h-32 w-px bg-gradient-to-b from-white/20 to-transparent"></div>
        </div>

        <!-- Team ROSSA (S2) -->
        <div class="flex-1 flex flex-col items-center justify-between glass-panel rounded-[2.5rem] p-6 md:p-8 relative overflow-hidden group shadow-glow-red border-red-500/20">
            <div class="absolute top-0 left-0 right-0 h-1.5 bg-red-500 shadow-[0_0_15px_rgba(239,68,68,0.8)]"></div>
            
            <div class="text-center z-10 w-full">
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-red-50 border border-red-100 mb-4">
                    <div class="w-1.5 h-1.5 rounded-full bg-red-500"></div>
                    <span class="text-xs font-black uppercase tracking-widest text-red-600">Squadra Rossa</span>
                </div>
            </div>
            
            <div class="flex-1 flex items-center justify-center w-full z-10 relative">
                <div class="absolute left-0 lg:left-4 z-20">
                    <button onclick="adjustScore(2, 'sub_goal')" class="h-16 w-16 md:h-20 md:w-20 rounded-2xl glass-panel hover:bg-white flex items-center justify-center transition-all active:scale-90 border-white pointer-events-auto">
                        <span class="material-symbols-outlined text-3xl md:text-4xl text-slate-300">remove</span>
                    </button>
                </div>
                
                <div class="text-[12rem] md:text-[18rem] lg:text-[22rem] score-number text-red-500 leading-none select-none px-12" id="score-s2">0</div>
                
                <div class="absolute right-0 lg:right-4 z-20">
                    <button onclick="adjustScore(2, 'add_goal')" class="h-16 w-16 md:h-20 md:w-20 rounded-2xl bg-red-500 shadow-lg shadow-red-500/30 flex items-center justify-center transition-all hover:scale-105 active:scale-90 pointer-events-auto">
                        <span class="material-symbols-outlined text-3xl md:text-4xl font-bold">add</span>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 md:gap-8 w-full z-10 mt-4" id="players-s2">
                <!-- Players will be injected here -->
            </div>
        </div>

    </div>

    <!-- Connection Info (Bottom Right) -->
    <div class="fixed bottom-6 right-6 z-30 pointer-events-none">
        <div class="glass-panel px-5 py-3 rounded-2xl border-white">
            <div class="text-[10px] font-black uppercase tracking-tighter text-slate-400 mb-0.5">Dispositivo</div>
            <div class="text-sm font-bold text-slate-700 uppercase tracking-wide">calcetto_tuni</div>
        </div>
    </div>

    <!-- Activity Log (Bottom Center) -->
    <div class="fixed bottom-6 left-1/2 -translate-x-1/2 z-30 pointer-events-none">
        <div class="w-72 md:w-96 glass-panel rounded-3xl h-24 md:h-32 lg:h-40 flex flex-col overflow-hidden pointer-events-auto transition-all duration-500">
            <!-- Fixed Header -->
            <div class="flex items-center justify-center gap-2 py-3 px-4 border-b border-black/5 bg-white/30 backdrop-blur-md shrink-0">
                <span class="material-symbols-outlined text-[14px] text-slate-400">terminal</span>
                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Logs Partita</span>
            </div>
            <!-- Scrolling Content -->
            <div id="activity-log-content" class="flex-1 overflow-y-auto p-4 flex flex-col gap-2 custom-scrollbar">
                <!-- Log entries injected here -->
            </div>
        </div>
    </div>

    <!-- Empty State / Waiting for players -->
    <div id="waiting-state" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-50/80 backdrop-blur-md transition-opacity duration-700">
        <div class="glass-panel p-12 rounded-[3rem] w-[90%] max-w-xl shadow-2xl border-white transform transition-all scale-100 text-center relative overflow-hidden bg-white/70">
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-transparent via-sky-500/30 to-transparent"></div>
            
            <div class="h-28 w-28 bg-sky-50 text-sky-500 rounded-3xl flex items-center justify-center mx-auto mb-8 border border-sky-100 shadow-glow-blue rotate-3">
                <span class="material-symbols-outlined text-6xl">sports_soccer</span>
            </div>
            
            <h2 class="text-4xl md:text-5xl font-black text-slate-800 mb-3 tracking-tighter uppercase italic">Calcetto <span class="text-sky-500">Libero</span></h2>
            <p class="text-slate-500 mb-10 text-lg font-medium leading-relaxed max-w-sm mx-auto">Il campo è pronto. Siediti per iniziare una nuova sfida leggendaria.</p>
            
            <div class="flex items-center justify-center gap-3 bg-slate-100/50 border border-slate-200 px-6 py-4 rounded-2xl">
                <span class="material-symbols-outlined text-sky-500 animate-pulse">nfc</span>
                <span class="text-sm font-bold tracking-widest uppercase text-slate-600">Scansiona il tag per iniziare</span>
            </div>
        </div>
    </div>

    <!-- Match Over Overlay -->
    <div id="match-over-state" class="fixed inset-0 z-50 flex items-center justify-center bg-white/90 backdrop-blur-xl transition-opacity duration-700 hidden opacity-0">
        <div class="glass-panel p-12 rounded-[4rem] w-[95%] max-w-2xl border-white transform transition-all scale-100 text-center relative overflow-hidden bg-white/80">
            <div class="absolute inset-0 bg-gradient-to-b from-yellow-500/10 to-transparent"></div>
            
            <div class="h-32 w-32 bg-yellow-50 text-yellow-600 rounded-full flex items-center justify-center mx-auto mt-4 mb-8 border-2 border-yellow-200 relative shadow-lg">
                <span class="material-symbols-outlined text-7xl relative z-10">emoji_events</span>
                <div class="absolute inset-0 bg-yellow-500 rounded-full animate-ping opacity-10"></div>
            </div>
            
            <h2 class="text-5xl md:text-7xl font-black mb-2 tracking-tighter uppercase italic" id="winner-text">Vittoria</h2>
            <div class="text-7xl md:text-9xl font-black mb-10 text-slate-800 tracking-tighter" id="winner-score-text">10 - 8</div>
            
            <div class="flex justify-center gap-8 md:gap-12 mb-12" id="winner-players"></div>
            
            <div class="inline-flex items-center gap-3 bg-slate-100 border border-slate-200 px-8 py-3 rounded-full text-xs font-black tracking-[0.2em] uppercase text-slate-400 overflow-hidden relative">
                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white to-transparent animate-[shimmer_2s_infinite]"></div>
                <span class="material-symbols-outlined text-sm animate-spin">progress_activity</span>
                Prossima sfida a breve
            </div>
        </div>
    </div>

    <script>
        const urlParams = new URLSearchParams(window.location.search);
        const tableId = urlParams.get('table') || '1';
        const LIVE_API_URL = `live.php?api=1&table=${tableId}`;
        
        let currentState = {
            tableId: tableId,
            scoreS1: 0,
            scoreS2: 0,
            filledCount: 0,
            isMatchActive: false
        };
        
        let matchOverTimeout = null;
        let lastAnimatedMatchId = 0;

        function renderPlayerCard(player, roleStr, colorTheme) {
            if (!player) {
                return `
                <div class="flex flex-col items-center opacity-40">
                    <div class="h-16 w-16 md:h-20 md:w-20 rounded-2xl glass-panel border-dashed border-slate-200 flex items-center justify-center mb-3">
                        <span class="material-symbols-outlined text-slate-300 text-3xl">add</span>
                    </div>
                </div>`;
            }

            const avatarHtml = player.avatar_url 
                ? `<img src="${player.avatar_url}" alt="Avatar" class="w-full h-full object-cover">`
                : `<div class="w-full h-full bg-gradient-to-br from-white/10 to-white/5 flex items-center justify-center text-white/40 font-black text-2xl">${player.nome.charAt(0).toUpperCase()}</div>`;

            const borderColor = colorTheme === 'blu' ? 'border-sky-500/50' : 'border-red-500/50';
            const bgColor = colorTheme === 'blu' ? 'bg-sky-500/20' : 'bg-red-500/20';

            return `
            <div class="flex flex-col items-center">
                <div class="relative mb-2">
                    ${player.active_aura ? `<div class="absolute inset-[-6px] rounded-full aura-${player.active_aura} opacity-70 z-0"></div>` : ''}
                    <div class="h-16 w-16 md:h-16 md:w-16 lg:h-24 lg:w-24 rounded-2xl glass-panel border-2 ${borderColor} relative group shadow-sm bg-white/40 z-10">
                        <div class="w-full h-full rounded-2xl overflow-hidden">
                            ${avatarHtml}
                        </div>
                        <!-- Elo -->
                        <div class="absolute bottom-0 left-0 right-0 bg-white/90 backdrop-blur-md text-center py-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                            <span class="text-[10px] font-black text-slate-600">${roleStr === 'Portiere' ? player.elo_portiere : player.elo_attaccante}</span>
                        </div>
                    </div>
                </div>
                <div class="flex flex-col items-center">
                    <span class="text-sm md:text-base lg:text-lg font-black text-slate-800 tracking-tight leading-none mb-1">
                        <span data-color="${player.active_name_color || ''}">${player.nome}</span>
                    </span>
                    ${player.active_title ? `<div class="player-title" data-color="${player.active_name_color || ''}">${player.active_title}</div>` : ''}
                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] italic mt-0.5">${roleStr}</span>
                </div>
            </div>`;
        }

        function addLog(message, type = 'info') {
            const logContainer = document.getElementById('activity-log-content');
            if (!logContainer) return;

            const time = new Date().toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' });
            let icon = 'info';
            let colorClass = 'text-slate-400';
            
            if (type === 'goal_blu') { colorClass = 'text-sky-600 font-bold'; icon = 'bolt'; }
            if (type === 'goal_red') { colorClass = 'text-red-600 font-bold'; icon = 'bolt'; }
            if (type === 'system') { colorClass = 'text-slate-500 italic'; icon = 'settings'; }
            
            const logEntry = document.createElement('div');
            logEntry.className = 'flex items-center gap-2 text-[11px] border-l-2 pl-2 border-black/5 hover:bg-black/5 py-1 rounded-r-lg transition-colors';
            
            logEntry.innerHTML = `
                <span class="text-slate-300 font-mono shrink-0">${time}</span>
                <span class="material-symbols-outlined text-[12px] ${colorClass}">${icon}</span>
                <span class="${colorClass} truncate">${message}</span>
            `;
            
            logContainer.appendChild(logEntry);
            
            // Limit logs
            while (logContainer.children.length > 40) {
                logContainer.removeChild(logContainer.children[0]);
            }
            logContainer.scrollTop = logContainer.scrollHeight;
        }

        async function fetchLiveStatus() {
            try {
                const res = await fetch(LIVE_API_URL);
                if (!res.ok) return;
                const data = await res.json();
                if (!data.players) return;
                updateScoreboard(data);
            } catch (err) {
                console.error("Scoreboard API Error:", err);
            }
        }

        function updateScoreboard(data) {
            let filledCount = 0;
            const positions = ['s1_portiere', 's1_attaccante', 's2_portiere', 's2_attaccante'];
            positions.forEach(p => { if (data.players && data.players[p]) filledCount++; });

            const s1 = parseInt(data.score_s1) || 0;
            const s2 = parseInt(data.score_s2) || 0;
            
            if (currentState.filledCount === 0 && filledCount > 0) addLog("Match pronto. Inizia il riscaldamento!", "system");
            if (filledCount > 0 && (s1 > 0 || s2 > 0) && currentState.scoreS1 === 0 && currentState.scoreS2 === 0) addLog("Palla al centro. Sfida iniziata!", "system");

            if (s1 > currentState.scoreS1) addLog(`GOAL BLU! (${s1} - ${s2})`, 'goal_blu');
            if (s2 > currentState.scoreS2) addLog(`GOAL ROSSO! (${s1} - ${s2})`, 'goal_red');
            if (s1 < currentState.scoreS1) addLog(`Gol annullato Squadra Blu`, 'system');
            if (s2 < currentState.scoreS2) addLog(`Gol annullato Squadra Rossa`, 'system');
            
            currentState.filledCount = filledCount;

            // Update Table Indicator & Clock
            const tableId = data.table_id || 1;
            const tableName = tableId == 1 ? "Calcetto Tuni" : "Calcetto Margot";
            const tBadge = document.getElementById('table-indicator-badge');
            if (tBadge) {
                tBadge.querySelector('span:last-child').textContent = tableName;
                tBadge.classList.replace('opacity-0', 'opacity-100');
            }
            const timeEl = document.getElementById('current-time');
            if (timeEl) {
                const now = new Date();
                timeEl.textContent = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
            }
            
            // Win Handling
            if (data.recent_match && parseInt(data.recent_match.id) !== lastAnimatedMatchId && !document.getElementById('match-over-state').classList.contains('opacity-100')) {
                lastAnimatedMatchId = parseInt(data.recent_match.id);
                showMatchOver(data);
                return;
            }

            if (document.getElementById('match-over-state').classList.contains('opacity-100')) return;

            if (filledCount === 0) {
                showWaitingState();
                resetScoresUI();
                return;
            }

            hideWaitingState();
            
            animateScoreUpdate('score-s1', s1, 'blue');
            animateScoreUpdate('score-s2', s2, 'red');

            document.getElementById('players-s1').innerHTML = 
                renderPlayerCard(data.players.s1_portiere, 'Portiere', 'blu') + 
                renderPlayerCard(data.players.s1_attaccante, 'Attaccante', 'blu');
                
            document.getElementById('players-s2').innerHTML = 
                renderPlayerCard(data.players.s2_portiere, 'Portiere', 'rossa') + 
                renderPlayerCard(data.players.s2_attaccante, 'Attaccante', 'rossa');
            
            currentState.scoreS1 = s1;
            currentState.scoreS2 = s2;
        }

        function animateScoreUpdate(elementId, newScore, type) {
            const el = document.getElementById(elementId);
            if (parseInt(el.innerText) !== newScore) {
                el.innerText = newScore;
                el.classList.remove('animate-score-hit');
                void el.offsetWidth; // Trigger reflow
                el.classList.add('animate-score-hit');
            }
        }


        function showMatchOver(data) {
            const rm = data.recent_match;
            const p1 = data.recent_winner_1;
            const p2 = data.recent_winner_2;
            const getAv = (p) => p.avatar_url ? `<img src="${p.avatar_url}" class="w-full h-full object-cover">` : `<div class="w-full h-full bg-gradient-to-br from-white/10 to-white/5 flex items-center justify-center text-white/40 font-black text-3xl">${p.nome.charAt(0).toUpperCase()}</div>`;

            let playersHtml = '';
            if (p1 && p1.nome !== 'Ospite') playersHtml += `
                <div class="flex flex-col items-center">
                    <div class="relative mb-4 rotate-[-3deg]">
                        ${p1.active_aura ? `<div class="absolute inset-[-10px] rounded-full aura-${p1.active_aura} opacity-70 z-0"></div>` : ''}
                        <div class="h-28 w-28 md:h-36 md:w-36 rounded-3xl glass-panel border-4 border-yellow-500 shadow-glow-yellow relative z-10">
                            <div class="w-full h-full rounded-3xl overflow-hidden">${getAv(p1)}</div>
                        </div>
                    </div>
                    <span class="font-black text-xl md:text-2xl text-white uppercase italic tracking-tighter">
                        <span data-color="${p1.active_name_color || ''}">${p1.nome}</span>
                    </span>
                    ${p1.active_title ? `<div class="player-title text-white/80 mt-1" data-color="${p1.active_name_color || ''}">${p1.active_title}</div>` : ''}
                </div>`;
            if (p2 && p2.nome !== 'Ospite') playersHtml += `
                <div class="flex flex-col items-center">
                    <div class="relative mb-4 rotate-[3deg]">
                        ${p2.active_aura ? `<div class="absolute inset-[-10px] rounded-full aura-${p2.active_aura} opacity-70 z-0"></div>` : ''}
                        <div class="h-28 w-28 md:h-36 md:w-36 rounded-3xl glass-panel border-4 border-yellow-500 shadow-glow-yellow relative z-10">
                            <div class="w-full h-full rounded-3xl overflow-hidden">${getAv(p2)}</div>
                        </div>
                    </div>
                    <span class="font-black text-xl md:text-2xl text-white uppercase italic tracking-tighter">
                        <span data-color="${p2.active_name_color || ''}">${p2.nome}</span>
                    </span>
                    ${p2.active_title ? `<div class="player-title text-white/80 mt-1" data-color="${p2.active_name_color || ''}">${p2.active_title}</div>` : ''}
                </div>`;

            document.getElementById('winner-players').innerHTML = playersHtml;
            document.getElementById('winner-text').innerText = rm.vincitore == 1 ? "VITTORIA BLU" : "VITTORIA ROSSA";
            document.getElementById('winner-text').className = `text-5xl md:text-7xl font-black mb-2 tracking-tighter uppercase italic ${rm.vincitore == 1 ? 'text-sky-600 drop-shadow-[0_10px_20px_rgba(2,132,199,0.2)]' : 'text-red-600 drop-shadow-[0_10px_20px_rgba(220,38,38,0.2)]'}`;
            
            const finalS1 = rm.score_s1 !== null ? rm.score_s1 : currentState.scoreS1;
            const finalS2 = rm.score_s2 !== null ? rm.score_s2 : currentState.scoreS2;
            document.getElementById('winner-score-text').innerText = `${finalS1} - ${finalS2}`;
            
            const el = document.getElementById('match-over-state');
            el.classList.remove('hidden');
            setTimeout(() => {
                el.classList.replace('opacity-0', 'opacity-100');
                if (window.confetti) {
                    confetti({ particleCount: 150, spread: 70, origin: { y: 0.6 }, colors: (rm.vincitore == 1 ? ['#0ea5e9', '#ffffff'] : ['#ef4444', '#ffffff']) });
                }
            }, 50);

            addLog(`GARA FINITA: Vince Squadra ${rm.vincitore == 1 ? 'Blu' : 'Rossa'}`, "system");
            clearTimeout(matchOverTimeout);
            matchOverTimeout = setTimeout(() => {
                el.classList.replace('opacity-100', 'opacity-0');
                setTimeout(() => el.classList.add('hidden'), 700);
            }, 10000);
        }

        function showWaitingState() {
            const el = document.getElementById('waiting-state');
            el.classList.replace('hidden', 'flex');
            setTimeout(() => el.classList.replace('opacity-0', 'opacity-100'), 50);
        }

        function hideWaitingState() {
            const el = document.getElementById('waiting-state');
            el.classList.replace('opacity-100', 'opacity-0');
            setTimeout(() => el.classList.replace('flex', 'hidden'), 700);
        }

        function resetScoresUI() {
            document.getElementById('score-s1').innerText = '0';
            document.getElementById('score-s2').innerText = '0';
            currentState.scoreS1 = 0; currentState.scoreS2 = 0;
        }

        async function adjustScore(team, action) {
            try {
                const btn = event.currentTarget;
                if (btn.disabled) return;
                btn.disabled = true;
                const res = await fetch(LIVE_API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: action, team: team, table: tableId })
                });
                if (res.ok) await fetchLiveStatus();
                btn.disabled = false;
            } catch (err) {
                console.error("Manual Score Adjust Error:", err);
            }
        }

        setInterval(fetchLiveStatus, 1000);
        fetchLiveStatus();
    </script>
</body>
</html>
