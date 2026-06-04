<?php
// scoreboard.php – Premium Responsive Redesign v8
require_once 'database.php';
session_start();
$db   = new Database();
$conn = $db->getConnection();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#080b14">
    <title>Scalcetting · Live Scoreboard</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

    <!-- Confetti -->
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>

    <style>
        /* ─── Reset & Base ───────────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:        #080b14;
            --surface:   rgba(255,255,255,0.04);
            --border:    rgba(255,255,255,0.08);
            --blue:      #38bdf8;
            --blue-dark: #0284c7;
            --red:       #f87171;
            --red-dark:  #dc2626;
            --gold:      #fbbf24;
            --text:      #f1f5f9;
            --muted:     rgba(241,245,249,0.45);

            /* fluid sizes */
            --score-fs:    clamp(5rem, 18vw, 18rem);
            --avatar-size: clamp(52px, 8vw, 100px);
            --name-fs:     clamp(0.7rem, 1.8vw, 1.1rem);
        }

        html, body {
            width: 100%; height: 100%;
            overflow: hidden;
            background: var(--bg);
            color: var(--text);
            font-family: 'Lexend', sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        /* ─── Animated background ────────────────────────────────────── */
        .bg-layer {
            position: fixed; inset: 0; z-index: 0; pointer-events: none;
            overflow: hidden;
        }
        .bg-blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(100px);
            opacity: 0.18;
            animation: blob-float 20s ease-in-out infinite;
        }
        .bg-blob--blue  { width: 55vw; height: 55vw; background: #0ea5e9; top: -20%; left: -15%; }
        .bg-blob--red   { width: 50vw; height: 50vw; background: #ef4444; bottom: -20%; right: -15%; animation-delay: -10s; }
        .bg-blob--violet{ width: 30vw; height: 30vw; background: #8b5cf6; top: 40%; left: 40%; animation-delay: -5s; opacity: 0.10; }
        @keyframes blob-float {
            0%,100% { transform: translate(0,0) scale(1); }
            33%      { transform: translate(30px,-20px) scale(1.05); }
            66%      { transform: translate(-20px,30px) scale(0.97); }
        }

        /* Fine noise overlay */
        .bg-noise {
            position: fixed; inset: 0; z-index: 1; pointer-events: none;
            opacity: 0.03;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
            background-size: 256px;
        }

        /* ─── Layout ─────────────────────────────────────────────────── */
        .page {
            position: relative; z-index: 2;
            display: grid;
            grid-template-rows: auto 1fr auto;
            width: 100%; height: 100%;
        }

        /* ─── Top Bar ────────────────────────────────────────────────── */
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: clamp(8px,1.5vh,16px) clamp(12px,3vw,32px);
            border-bottom: 1px solid var(--border);
            background: rgba(8,11,20,0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            gap: 8px;
            flex-wrap: wrap;
            z-index: 10;
        }
        .topbar-pill {
            display: flex; align-items: center; gap: 6px;
            padding: 5px 12px; border-radius: 999px;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border);
            font-size: clamp(8px, 1.5vw, 11px);
            font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase;
            color: var(--muted);
            white-space: nowrap;
        }
        .topbar-pill .dot {
            width: 7px; height: 7px; border-radius: 50%;
            background: #22c55e;
            box-shadow: 0 0 6px #22c55e;
            animation: pulse-dot 2s ease-in-out infinite;
        }
        @keyframes pulse-dot { 0%,100%{opacity:1;} 50%{opacity:0.4;} }

        #table-badge {
            background: rgba(99,102,241,0.12);
            border-color: rgba(99,102,241,0.3);
            color: #a5b4fc;
            opacity: 0;
            transition: opacity .5s;
        }
        #table-badge.visible { opacity: 1; }

        /* ─── Main Scoreboard ────────────────────────────────────────── */
        .scoreboard {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: stretch;
            gap: clamp(8px, 2vw, 24px);
            padding: clamp(8px, 2vh, 20px) clamp(8px, 2vw, 20px);
            height: 100%;
            min-height: 0;
        }

        /* ─── Team Panel ─────────────────────────────────────────────── */
        .team-panel {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: clamp(16px, 3vw, 32px);
            padding: clamp(12px, 2.5vw, 28px);
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            gap: clamp(8px, 2vh, 16px);
        }
        .team-panel::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
        }
        .team-panel--blue::before  { background: linear-gradient(90deg, transparent, var(--blue), transparent); box-shadow: 0 0 20px var(--blue); }
        .team-panel--red::before   { background: linear-gradient(90deg, transparent, var(--red), transparent); box-shadow: 0 0 20px var(--red); }

        /* Corner glow */
        .team-panel::after {
            content: '';
            position: absolute;
            width: 200px; height: 200px;
            border-radius: 50%;
            opacity: 0.06;
            filter: blur(60px);
            pointer-events: none;
        }
        .team-panel--blue::after { background: var(--blue); top: -30px; left: -30px; }
        .team-panel--red::after  { background: var(--red);  bottom: -30px; right: -30px; }

        /* Team badge */
        .team-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 14px; border-radius: 999px;
            font-size: clamp(8px, 1.4vw, 11px);
            font-weight: 800; letter-spacing: 0.15em; text-transform: uppercase;
            flex-shrink: 0;
        }
        .team-badge--blue { background: rgba(56,189,248,0.1); border: 1px solid rgba(56,189,248,0.25); color: var(--blue); }
        .team-badge--red  { background: rgba(248,113,113,0.1); border: 1px solid rgba(248,113,113,0.25); color: var(--red); }
        .team-badge .dot  { width: 6px; height: 6px; border-radius: 50%; }
        .team-badge--blue .dot { background: var(--blue); }
        .team-badge--red  .dot { background: var(--red); }

        /* ─── Score Section ──────────────────────────────────────────── */
        .score-section {
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            flex: 1;
            width: 100%;
            min-height: 0;
        }

        .score-btn {
            position: absolute;
            display: flex; align-items: center; justify-content: center;
            width: clamp(36px,6vw,60px); height: clamp(36px,6vw,60px);
            border-radius: clamp(8px,1.5vw,14px);
            border: 1px solid var(--border);
            background: rgba(255,255,255,0.05);
            cursor: pointer;
            transition: all .15s;
            color: var(--muted);
            z-index: 2;
            flex-shrink: 0;
        }
        .score-btn:hover  { background: rgba(255,255,255,0.12); color: var(--text); transform: scale(1.05); }
        .score-btn:active { transform: scale(0.92); }
        .score-btn.plus   { right: 0; }
        .score-btn.minus  { left: 0; }
        .score-btn.plus--blue  { background: rgba(56,189,248,0.15); border-color: rgba(56,189,248,0.3); color: var(--blue); }
        .score-btn.plus--blue:hover  { background: rgba(56,189,248,0.3); }
        .score-btn.plus--red   { background: rgba(248,113,113,0.15); border-color: rgba(248,113,113,0.3); color: var(--red); }
        .score-btn.plus--red:hover   { background: rgba(248,113,113,0.3); }

        .score-btn .material-symbols-outlined { font-size: clamp(18px,3.5vw,30px); }

        .score-digit {
            font-size: var(--score-fs);
            font-weight: 900;
            letter-spacing: -0.04em;
            line-height: 1;
            select: none;
            filter: drop-shadow(0 0 clamp(8px,2vw,20px) currentColor);
            transition: transform .3s cubic-bezier(.34,1.56,.64,1), filter .3s;
        }
        .score-digit--blue { color: var(--blue); }
        .score-digit--red  { color: var(--red); }
        .score-digit.hit {
            animation: score-bounce .45s cubic-bezier(.175,.885,.32,1.275);
        }
        @keyframes score-bounce {
            0%   { transform: scale(1); }
            45%  { transform: scale(1.28) rotate(3deg); filter: drop-shadow(0 0 30px currentColor) brightness(1.6); }
            100% { transform: scale(1); }
        }

        /* ─── Players Grid ───────────────────────────────────────────── */
        .players-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: clamp(8px, 2vw, 20px);
            width: 100%;
        }

        .player-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
        }

        .player-avatar-wrap {
            position: relative;
            width: var(--avatar-size);
            height: var(--avatar-size);
        }
        .player-aura-ring {
            position: absolute;
            inset: -8px;
            border-radius: 50%;
            z-index: 0;
            opacity: 0.75;
        }
        .player-avatar {
            position: relative; z-index: 1;
            width: 100%; height: 100%;
            border-radius: clamp(8px, 1.5vw, 16px);
            overflow: hidden;
            border: 2px solid;
            background: rgba(255,255,255,0.06);
            display: flex; align-items: center; justify-content: center;
        }
        .player-avatar--blue { border-color: rgba(56,189,248,0.4); }
        .player-avatar--red  { border-color: rgba(248,113,113,0.4); }
        .player-avatar img   { width:100%; height:100%; object-fit:cover; }
        .player-initial      { font-size: clamp(1rem,2.5vw,2rem); font-weight: 900; color: rgba(255,255,255,0.3); }

        .player-name {
            font-size: var(--name-fs);
            font-weight: 800;
            text-align: center;
            line-height: 1.1;
            letter-spacing: -0.01em;
        }
        .player-role {
            font-size: clamp(6px, 1.2vw, 9px);
            font-weight: 700;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--muted);
        }
        .player-title-badge {
            font-size: clamp(6px, 1.1vw, 8px);
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: rgba(251,191,36,0.8);
        }

        /* empty slot */
        .player-empty {
            display: flex; flex-direction: column; align-items: center; gap: 6px;
            opacity: 0.25;
        }
        .player-empty-box {
            width: var(--avatar-size); height: var(--avatar-size);
            border-radius: clamp(8px,1.5vw,16px);
            border: 2px dashed rgba(255,255,255,0.15);
            display: flex; align-items: center; justify-content: center;
        }
        .player-empty-box .material-symbols-outlined { font-size: clamp(16px,3vw,28px); color: rgba(255,255,255,0.2); }

        /* ─── VS Divider ─────────────────────────────────────────────── */
        .vs-divider {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0 clamp(4px, 1vw, 12px);
        }
        .vs-line {
            flex: 1; width: 1px;
            background: linear-gradient(to bottom, transparent, var(--border) 30%, var(--border) 70%, transparent);
        }
        .vs-badge {
            width: clamp(32px, 5vw, 52px);
            height: clamp(32px, 5vw, 52px);
            border-radius: 50%;
            background: var(--surface);
            border: 1px solid var(--border);
            display: flex; align-items: center; justify-content: center;
            font-size: clamp(9px, 1.5vw, 13px);
            font-weight: 900;
            color: rgba(255,255,255,0.2);
            letter-spacing: -0.05em;
            flex-shrink: 0;
        }

        /* ─── Log Bar (bottom) ───────────────────────────────────────── */
        .log-bar {
            display: flex;
            align-items: stretch;
            gap: 0;
            border-top: 1px solid var(--border);
            background: rgba(8,11,20,0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            height: clamp(36px, 7vh, 52px);
            overflow: hidden;
        }
        .log-bar-label {
            display: flex; align-items: center; gap: 5px;
            padding: 0 clamp(8px,2vw,16px);
            border-right: 1px solid var(--border);
            font-size: clamp(7px, 1.2vw, 9px);
            font-weight: 800; letter-spacing: 0.15em; text-transform: uppercase;
            color: var(--muted); white-space: nowrap; flex-shrink: 0;
        }
        .log-scroller {
            flex: 1;
            overflow: hidden;
            position: relative;
        }
        .log-ticker {
            display: flex;
            align-items: center;
            gap: clamp(24px, 4vw, 48px);
            height: 100%;
            white-space: nowrap;
            transition: transform .4s ease;
            padding: 0 clamp(8px,2vw,16px);
        }
        .log-entry {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: clamp(8px, 1.4vw, 11px);
            font-weight: 600; color: var(--muted);
            flex-shrink: 0;
        }
        .log-entry .material-symbols-outlined { font-size: clamp(11px,1.8vw,14px); }
        .log-entry.blue  { color: var(--blue); }
        .log-entry.red   { color: var(--red); }
        .log-entry.sys   { color: rgba(148,163,184,0.7); font-style: italic; }

        .log-time-pill {
            margin-left: auto;
            display: flex; align-items: center;
            padding: 0 clamp(8px,2vw,14px);
            border-left: 1px solid var(--border);
            font-size: clamp(8px,1.3vw,11px);
            font-weight: 700; letter-spacing: 0.1em;
            color: var(--muted); white-space: nowrap;
        }

        /* ─── Waiting State ──────────────────────────────────────────── */
        .waiting-overlay {
            position: fixed; inset: 0; z-index: 50;
            display: flex; align-items: center; justify-content: center;
            background: rgba(8,11,20,0.92);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            transition: opacity .6s;
        }
        .waiting-card {
            text-align: center;
            padding: clamp(24px,5vw,56px);
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border);
            border-radius: clamp(20px,4vw,40px);
            max-width: min(90vw, 480px);
            width: 100%;
        }
        .waiting-icon {
            width: clamp(64px,12vw,100px); height: clamp(64px,12vw,100px);
            margin: 0 auto clamp(16px,3vh,28px);
            background: rgba(56,189,248,0.08);
            border: 1px solid rgba(56,189,248,0.2);
            border-radius: clamp(14px,2.5vw,24px);
            display: flex; align-items: center; justify-content: center;
            animation: waiting-bob 3s ease-in-out infinite;
        }
        .waiting-icon .material-symbols-outlined { font-size: clamp(28px,6vw,52px); color: var(--blue); }
        @keyframes waiting-bob { 0%,100%{transform:translateY(0) rotate(-3deg);} 50%{transform:translateY(-8px) rotate(3deg);} }

        .waiting-title {
            font-size: clamp(1.4rem,4vw,2.8rem);
            font-weight: 900; letter-spacing: -0.03em;
            text-transform: uppercase; font-style: italic;
            color: var(--text);
            margin-bottom: 8px;
        }
        .waiting-title span { color: var(--blue); }
        .waiting-sub {
            font-size: clamp(0.75rem,1.8vw,1rem);
            color: var(--muted); margin-bottom: clamp(20px,4vh,32px);
            line-height: 1.6;
        }
        .waiting-nfc {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 20px; border-radius: 12px;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border);
            font-size: clamp(9px,1.6vw,12px);
            font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase;
            color: var(--muted);
        }
        .waiting-nfc .material-symbols-outlined { color: var(--blue); animation: pulse-dot 1.5s ease-in-out infinite; font-size: 18px; }

        /* ─── Match Over Overlay ─────────────────────────────────────── */
        .matchover-overlay {
            position: fixed; inset: 0; z-index: 60;
            display: flex; align-items: center; justify-content: center;
            background: rgba(8,11,20,0.96);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            opacity: 0;
            pointer-events: none;
            transition: opacity .5s;
        }
        .matchover-overlay.active {
            opacity: 1; pointer-events: auto;
        }
        .matchover-card {
            text-align: center;
            padding: clamp(20px,4vw,48px);
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border);
            border-radius: clamp(20px,4vw,40px);
            max-width: min(92vw, 640px);
            width: 100%;
            position: relative; overflow: hidden;
        }
        .matchover-card::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; height: 3px;
        }
        .matchover-blue::before  { background: linear-gradient(90deg, transparent, var(--blue), transparent); }
        .matchover-red::before   { background: linear-gradient(90deg, transparent, var(--red), transparent); }

        .matchover-trophy {
            width: clamp(56px,10vw,88px); height: clamp(56px,10vw,88px);
            margin: 0 auto clamp(12px,2.5vh,20px);
            border-radius: 50%;
            background: rgba(251,191,36,0.1);
            border: 2px solid rgba(251,191,36,0.3);
            display: flex; align-items: center; justify-content: center;
            position: relative;
        }
        .matchover-trophy .material-symbols-outlined { font-size: clamp(28px,5vw,44px); color: var(--gold); }
        .matchover-trophy::after {
            content: '';
            position: absolute; inset: -4px; border-radius: 50%;
            background: rgba(251,191,36,0.12);
            animation: trophy-ping 1.5s ease-out infinite;
        }
        @keyframes trophy-ping { 0%{transform:scale(1);opacity:.8;} 100%{transform:scale(1.5);opacity:0;} }

        .matchover-result {
            font-size: clamp(1.6rem,5vw,3.5rem);
            font-weight: 900; letter-spacing: -0.03em;
            text-transform: uppercase; font-style: italic;
            margin-bottom: 4px;
        }
        .matchover-result--blue { color: var(--blue); }
        .matchover-result--red  { color: var(--red); }

        .matchover-score {
            font-size: clamp(2.5rem,9vw,6rem);
            font-weight: 900; letter-spacing: -0.06em;
            color: var(--text); margin-bottom: clamp(16px,3vh,28px);
        }

        .matchover-players {
            display: flex; justify-content: center;
            gap: clamp(16px,4vw,48px);
            margin-bottom: clamp(16px,3vh,24px);
        }

        .matchover-player {
            display: flex; flex-direction: column; align-items: center; gap: 8px;
        }
        .matchover-av-wrap {
            position: relative;
        }
        .matchover-av {
            width: clamp(56px,10vw,96px); height: clamp(56px,10vw,96px);
            border-radius: clamp(12px,2vw,20px);
            overflow: hidden;
            border: 3px solid var(--gold);
            background: rgba(255,255,255,0.06);
            display: flex; align-items: center; justify-content: center;
        }
        .matchover-av img { width:100%; height:100%; object-fit:cover; }
        .matchover-av-initial { font-size: 1.5rem; font-weight: 900; color: rgba(255,255,255,0.3); }
        .matchover-name {
            font-size: clamp(0.75rem,2vw,1.1rem);
            font-weight: 800; text-align: center;
            text-transform: uppercase; font-style: italic;
            letter-spacing: -0.01em;
        }
        .matchover-waiting {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 8px 18px; border-radius: 999px;
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border);
            font-size: clamp(8px,1.4vw,10px);
            font-weight: 700; letter-spacing: 0.15em; text-transform: uppercase;
            color: var(--muted);
        }
        .matchover-waiting .material-symbols-outlined { font-size: 14px; animation: spin .8s linear infinite; }
        @keyframes spin { to{transform:rotate(360deg);} }

        /* ─── Aesthetic name/aura ────────────────────────────────────── */
        [data-color] { display: inline; }

        /* ─── AURA CSS GLOW RINGS (all 8 auras) ─────────────────────── */
        /* FIRE */
        .aura-fire { border-radius:50%; mix-blend-mode:screen; overflow:visible; animation:aura-fire-g 2s ease-in-out infinite alternate; }
        @keyframes aura-fire-g { 0%{box-shadow:0 0 12px 5px rgba(255,80,0,.6),0 0 28px 10px rgba(255,30,0,.3);} 100%{box-shadow:0 0 22px 8px rgba(255,100,0,.8),0 0 45px 16px rgba(255,50,0,.45);} }

        /* STORM */
        .aura-storm { border-radius:50%; mix-blend-mode:screen; overflow:visible; animation:aura-storm-g .15s steps(2,end) infinite,aura-storm-s 2s ease-in-out infinite; }
        @keyframes aura-storm-g { 0%{box-shadow:0 0 8px 3px rgba(34,211,238,.5),0 0 18px 6px rgba(14,165,233,.25);} 50%{box-shadow:0 0 16px 6px rgba(34,211,238,.9),0 0 35px 12px rgba(14,165,233,.6);} 100%{box-shadow:0 0 10px 3px rgba(34,211,238,.6),0 0 22px 7px rgba(14,165,233,.35);} }
        @keyframes aura-storm-s { 0%,100%{transform:scale(1);} 30%{transform:scale(1.04);} 60%{transform:scale(0.99);} }

        /* VOID */
        .aura-void { border-radius:50%; animation:aura-void-g 4s ease-in-out infinite alternate; }
        @keyframes aura-void-g { 0%{box-shadow:0 0 12px 4px rgba(88,28,135,.5),0 0 25px 8px rgba(30,0,60,.25);} 100%{box-shadow:0 0 22px 8px rgba(139,92,246,.6),0 0 45px 16px rgba(88,28,135,.4);} }

        /* FLARE */
        .aura-flare { border-radius:50%; mix-blend-mode:screen; animation:aura-flare-g 3s ease-in-out infinite; }
        @keyframes aura-flare-g { 0%,100%{box-shadow:0 0 10px 3px rgba(251,191,36,.5),0 0 20px 6px rgba(251,191,36,.2);} 35%{box-shadow:0 0 18px 7px rgba(255,223,100,.8),0 0 38px 13px rgba(251,191,36,.4);} }

        /* ICE */
        .aura-ice { border-radius:50%; mix-blend-mode:screen; animation:aura-ice-g 3s ease-in-out infinite alternate; }
        @keyframes aura-ice-g { 0%{box-shadow:0 0 10px 3px rgba(125,211,252,.4),0 0 22px 7px rgba(56,189,248,.2);} 100%{box-shadow:0 0 18px 7px rgba(186,230,253,.7),0 0 38px 13px rgba(125,211,252,.35);} }

        /* TOXIC */
        .aura-toxic { border-radius:50%; mix-blend-mode:screen; animation:aura-toxic-g 2s ease-in-out infinite alternate; }
        @keyframes aura-toxic-g { 0%{box-shadow:0 0 8px 3px rgba(132,204,22,.4),0 0 18px 6px rgba(77,124,15,.2);} 100%{box-shadow:0 0 18px 7px rgba(217,255,0,.65),0 0 35px 12px rgba(132,204,22,.35);} }

        /* GALAXY */
        .aura-galaxy { border-radius:50%; mix-blend-mode:screen; animation:aura-galaxy-g 8s linear infinite; }
        @keyframes aura-galaxy-g { 0%{box-shadow:0 0 14px 5px rgba(139,92,246,.4),0 0 30px 10px rgba(99,102,241,.2);transform:rotate(0deg) scale(1);} 50%{box-shadow:0 0 20px 8px rgba(196,181,253,.5),0 0 45px 16px rgba(139,92,246,.3);transform:rotate(180deg) scale(1.04);} 100%{box-shadow:0 0 14px 5px rgba(139,92,246,.4),0 0 30px 10px rgba(99,102,241,.2);transform:rotate(360deg) scale(1);} }

        /* BLOOD */
        .aura-blood { border-radius:50%; mix-blend-mode:screen; animation:aura-blood-g 1.2s ease-in-out infinite alternate; }
        @keyframes aura-blood-g { 0%{box-shadow:0 0 10px 3px rgba(185,28,28,.45),0 0 20px 6px rgba(127,29,29,.2);} 100%{box-shadow:0 0 20px 8px rgba(239,68,68,.7),0 0 40px 14px rgba(185,28,28,.4);} }

        /* ─── name color/style system ────────────────────────────────── */
        [data-color="#ef4444"]{color:#ef4444!important;}
        [data-color="#f97316"]{color:#f97316!important;}
        [data-color="#fbbf24"]{color:#fbbf24!important;}
        [data-color="#22c55e"]{color:#22c55e!important;}
        [data-color="#3b82f6"]{color:#3b82f6!important;}
        [data-color="#8b5cf6"]{color:#8b5cf6!important;}
        [data-color="#ec4899"]{color:#ec4899!important;}
        [data-color="#06b6d4"]{color:#06b6d4!important;}
        [data-color="#f8fafc"]{color:#f8fafc!important;}
        [data-color="#94a3b8"]{color:#94a3b8!important;}
        [data-color="#dc2626"]{color:#dc2626!important;}
        [data-color="#d97706"]{color:#d97706!important;}
        [data-color="#059669"]{color:#059669!important;}
        [data-color="#1d4ed8"]{color:#1d4ed8!important;}
        [data-color="#a855f7"]{color:#a855f7!important;}
        [data-color="#4f46e5"]{color:#4f46e5!important;}
        [data-color="#0891b2"]{color:#0891b2!important;}
        [data-color="#f43f5e"]{color:#f43f5e!important;}
        [data-color="gold"] { background:linear-gradient(90deg,#bf953f,#fcf6ba,#b38728,#fbf5b7,#aa771c)!important; background-size:200% auto!important; -webkit-background-clip:text!important; background-clip:text!important; -webkit-text-fill-color:transparent!important; font-weight:800!important; animation:gold-sh 2s linear infinite!important; display:inline-block!important; }
        @keyframes gold-sh { from{background-position:0% center;} to{background-position:200% center;} }
        [data-color="rainbow"] { display:inline-block!important; background:linear-gradient(90deg,#ef4444,#f59e0b,#10b981,#3b82f6,#8b5cf6,#ef4444)!important; background-size:200% auto!important; -webkit-background-clip:text!important; background-clip:text!important; -webkit-text-fill-color:transparent!important; animation:rainbow-sh 2.5s linear infinite!important; }
        @keyframes rainbow-sh { from{background-position:0% center;} to{background-position:200% center;} }
        [data-color="neon"] { color:#fff!important; text-shadow:0 0 7px #fff,0 0 21px #ff00ff,0 0 42px #ff00ff!important; animation:neon-fl 2s infinite alternate!important; }
        @keyframes neon-fl { 0%,100%{text-shadow:0 0 2px #fff,0 0 10px #ff00ff,0 0 20px #ff00ff;} 50%{text-shadow:none;opacity:.8;} }
        [data-color="plasma"] { color:#fff!important; text-shadow:0 0 4px #fff,0 0 8px #22d3ee,0 0 16px #22d3ee,0 0 32px #22d3ee!important; animation:plasma-p 1.5s ease-in-out infinite alternate!important; }
        @keyframes plasma-p { from{filter:brightness(1);} to{filter:brightness(1.5);} }

        /* ─── SVG Filters (fire/void/storm turbulence) ───────────────── */
        .svg-filters { position:absolute; width:0; height:0; overflow:hidden; }

        /* ─── Responsive overrides ───────────────────────────────────── */
        /* Portrait / small screens: stack vertically */
        @media (max-width: 600px), (orientation: portrait) and (max-width: 900px) {
            .scoreboard {
                grid-template-columns: 1fr;
                grid-template-rows: 1fr auto 1fr;
            }
            .vs-divider {
                flex-direction: row;
                padding: 0;
                height: auto;
                gap: 8px;
            }
            .vs-line { flex: 1; height: 1px; width: auto;
                background: linear-gradient(to right, transparent, var(--border) 30%, var(--border) 70%, transparent);
            }
            :root { --score-fs: clamp(4rem, 22vw, 8rem); }
        }

        /* Large landscape: bigger score */
        @media (min-width: 1400px) {
            :root { --score-fs: clamp(8rem, 16vw, 20rem); --avatar-size: clamp(72px,7vw,112px); }
        }
    </style>
</head>
<body>

<!-- SVG Filters -->
<svg class="svg-filters" xmlns="http://www.w3.org/2000/svg">
    <defs>
        <filter id="f-fire"><feTurbulence type="turbulence" baseFrequency="0.015 0.03" numOctaves="3" seed="2" result="turb"/><feDisplacementMap in="SourceGraphic" in2="turb" scale="12" xChannelSelector="R" yChannelSelector="G"/></filter>
        <filter id="f-void"><feTurbulence type="turbulence" baseFrequency="0.025" numOctaves="4" seed="5" result="turb"/><feDisplacementMap in="SourceGraphic" in2="turb" scale="18" xChannelSelector="R" yChannelSelector="G"/></filter>
        <filter id="f-storm"><feTurbulence type="turbulence" baseFrequency="0.02 0.04" numOctaves="3" seed="8" result="turb"/><feDisplacementMap in="SourceGraphic" in2="turb" scale="10" xChannelSelector="R" yChannelSelector="G"/></filter>
    </defs>
</svg>

<!-- Background -->
<div class="bg-layer">
    <div class="bg-blob bg-blob--blue"></div>
    <div class="bg-blob bg-blob--red"></div>
    <div class="bg-blob bg-blob--violet"></div>
</div>
<div class="bg-noise"></div>

<!-- Page Grid -->
<div class="page">

    <!-- TOP BAR -->
    <header class="topbar">
        <div class="topbar-pill">
            <div class="dot"></div>
            Live
        </div>

        <div id="table-badge" class="topbar-pill">
            <span class="material-symbols-outlined" style="font-size:14px;">table_bar</span>
            <span id="table-name">—</span>
        </div>

        <div class="topbar-pill" id="time-pill" style="margin-left:auto;">
            <span class="material-symbols-outlined" style="font-size:12px;">schedule</span>
            <span id="current-time">—</span>
        </div>
    </header>

    <!-- MAIN SCOREBOARD -->
    <main class="scoreboard" id="scoreboard-main">

        <!-- TEAM BLU -->
        <div class="team-panel team-panel--blue">
            <div class="team-badge team-badge--blue">
                <div class="dot"></div>
                Squadra Blu
            </div>

            <div class="score-section">
                <button class="score-btn minus" onclick="adjustScore(1,'sub_goal')" aria-label="Rimuovi gol blu">
                    <span class="material-symbols-outlined">remove</span>
                </button>
                <div class="score-digit score-digit--blue" id="score-s1">0</div>
                <button class="score-btn plus plus--blue" onclick="adjustScore(1,'add_goal')" aria-label="Aggiungi gol blu">
                    <span class="material-symbols-outlined">add</span>
                </button>
            </div>

            <div class="players-grid" id="players-s1"></div>
        </div>

        <!-- VS DIVIDER -->
        <div class="vs-divider">
            <div class="vs-line"></div>
            <div class="vs-badge">VS</div>
            <div class="vs-line"></div>
        </div>

        <!-- TEAM ROSSA -->
        <div class="team-panel team-panel--red">
            <div class="team-badge team-badge--red">
                <div class="dot"></div>
                Squadra Rossa
            </div>

            <div class="score-section">
                <button class="score-btn minus" onclick="adjustScore(2,'sub_goal')" aria-label="Rimuovi gol rosso">
                    <span class="material-symbols-outlined">remove</span>
                </button>
                <div class="score-digit score-digit--red" id="score-s2">0</div>
                <button class="score-btn plus plus--red" onclick="adjustScore(2,'add_goal')" aria-label="Aggiungi gol rosso">
                    <span class="material-symbols-outlined">add</span>
                </button>
            </div>

            <div class="players-grid" id="players-s2"></div>
        </div>

    </main>

    <!-- LOG BAR -->
    <footer class="log-bar">
        <div class="log-bar-label">
            <span class="material-symbols-outlined" style="font-size:12px;">terminal</span>
            Live Log
        </div>
        <div class="log-scroller">
            <div class="log-ticker" id="log-ticker">
                <span class="log-entry sys">
                    <span class="material-symbols-outlined">info</span>
                    In attesa di connessione...
                </span>
            </div>
        </div>
        <div class="log-time-pill" id="log-time-pill">—</div>
    </footer>
</div>

<!-- ── WAITING STATE ──────────────────────────────────────────── -->
<div class="waiting-overlay" id="waiting-state">
    <div class="waiting-card">
        <div class="waiting-icon">
            <span class="material-symbols-outlined">sports_soccer</span>
        </div>
        <h1 class="waiting-title">Calcetto <span>Live</span></h1>
        <p class="waiting-sub">Il campo è pronto. Siediti al tavolo per iniziare una nuova sfida leggendaria.</p>
        <div class="waiting-nfc">
            <span class="material-symbols-outlined">nfc</span>
            Scansiona il tag NFC per iniziare
        </div>
    </div>
</div>

<!-- ── MATCH OVER OVERLAY ─────────────────────────────────────── -->
<div class="matchover-overlay" id="matchover-state">
    <div class="matchover-card" id="matchover-card">
        <div class="matchover-trophy">
            <span class="material-symbols-outlined">emoji_events</span>
        </div>
        <div class="matchover-result" id="matchover-result">VITTORIA BLU</div>
        <div class="matchover-score" id="matchover-score">0 - 0</div>
        <div class="matchover-players" id="matchover-players"></div>
        <div class="matchover-waiting">
            <span class="material-symbols-outlined">progress_activity</span>
            Prossima sfida a breve
        </div>
    </div>
</div>

<script>
    /* ── Config ─────────────────────────────────────────────────── */
    const urlParams    = new URLSearchParams(window.location.search);
    const tableId      = urlParams.get('table') || '1';
    const LIVE_API_URL = `live.php?api=1&table=${tableId}`;

    let state = { scoreS1: 0, scoreS2: 0, filled: 0 };
    let lastMatchId = 0;
    let matchOverTimer = null;

    /* ── Clock ──────────────────────────────────────────────────── */
    function updateClock() {
        const t = new Date().toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' });
        document.getElementById('current-time').textContent = t;
        document.getElementById('log-time-pill').textContent = t;
    }
    updateClock();
    setInterval(updateClock, 5000);

    /* ── Log ticker ─────────────────────────────────────────────── */
    const MAX_LOGS = 20;
    let logs = [];

    function pushLog(msg, type = 'sys') {
        const time = new Date().toLocaleTimeString('it-IT', { hour:'2-digit', minute:'2-digit' });
        logs.push({ msg, type, time });
        if (logs.length > MAX_LOGS) logs.shift();
        renderLogs();
    }

    function renderLogs() {
        const ticker = document.getElementById('log-ticker');
        const iconMap = { sys: 'info', blue: 'sports_soccer', red: 'sports_soccer' };
        ticker.innerHTML = logs.map(l => `
            <span class="log-entry ${l.type}">
                <span class="material-symbols-outlined">${iconMap[l.type] || 'info'}</span>
                ${l.msg}
                <span style="opacity:.4;font-size:.85em;margin-left:4px">${l.time}</span>
            </span>
        `).join('');
        // Auto-scroll to end
        ticker.parentElement.scrollLeft = ticker.parentElement.scrollWidth;
    }

    /* ── Player card ─────────────────────────────────────────────── */
    function renderPlayerCard(player, role, team) {
        const color = team === 'blue' ? 'blue' : 'red';
        if (!player) return `
            <div class="player-empty">
                <div class="player-empty-box"><span class="material-symbols-outlined">person_add</span></div>
                <span class="player-role">${role}</span>
            </div>`;

        const av = player.avatar_url
            ? `<img src="${player.avatar_url}" alt="${player.nome}" loading="lazy">`
            : `<div class="player-initial">${player.nome.charAt(0).toUpperCase()}</div>`;

        const nameColor = player.active_name_color || '';
        const nameStyle = player.active_name_style || '';

        return `
        <div class="player-card">
            <div class="player-avatar-wrap">
                ${player.active_aura ? `<div class="player-aura-ring aura-${player.active_aura}"></div>` : ''}
                <div class="player-avatar player-avatar--${color}">${av}</div>
            </div>
            <div class="player-name">
                <span data-color="${nameColor}" data-style="${nameStyle}">${player.nome}</span>
            </div>
            ${player.active_title ? `<div class="player-title-badge">${player.active_title}</div>` : ''}
            <div class="player-role">${role}</div>
        </div>`;
    }

    /* ── Score animation ─────────────────────────────────────────── */
    function animScore(elId, newVal) {
        const el = document.getElementById(elId);
        if (parseInt(el.textContent) !== newVal) {
            el.textContent = newVal;
            el.classList.remove('hit');
            void el.offsetWidth;
            el.classList.add('hit');
            setTimeout(() => el.classList.remove('hit'), 500);
        }
    }

    /* ── Waiting state ───────────────────────────────────────────── */
    function showWaiting() {
        const el = document.getElementById('waiting-state');
        el.style.display = 'flex';
        requestAnimationFrame(() => el.style.opacity = '1');
    }
    function hideWaiting() {
        const el = document.getElementById('waiting-state');
        el.style.opacity = '0';
        setTimeout(() => el.style.display = 'none', 600);
    }

    /* ── Match Over ──────────────────────────────────────────────── */
    function showMatchOver(data) {
        const rm = data.recent_match;
        const w1 = data.recent_winner_1;
        const w2 = data.recent_winner_2;
        const isBlue = parseInt(rm.vincitore) === 1;

        // Result text
        const resultEl = document.getElementById('matchover-result');
        resultEl.textContent = isBlue ? 'VITTORIA BLU' : 'VITTORIA ROSSA';
        resultEl.className = `matchover-result matchover-result--${isBlue ? 'blue' : 'red'}`;

        // Score
        const s1 = rm.score_s1 !== null ? rm.score_s1 : state.scoreS1;
        const s2 = rm.score_s2 !== null ? rm.score_s2 : state.scoreS2;
        document.getElementById('matchover-score').textContent = `${s1} - ${s2}`;

        // Card accent
        const card = document.getElementById('matchover-card');
        card.className = `matchover-card matchover-${isBlue ? 'blue' : 'red'}`;

        // Players
        const getAv = p => p.avatar_url
            ? `<img src="${p.avatar_url}" style="width:100%;height:100%;object-fit:cover;">`
            : `<div class="matchover-av-initial">${p.nome.charAt(0).toUpperCase()}</div>`;

        let playersHtml = '';
        [w1, w2].forEach(p => {
            if (!p || p.nome === 'Ospite') return;
            const nc = p.active_name_color || '';
            const ns = p.active_name_style || '';
            playersHtml += `
            <div class="matchover-player">
                <div class="matchover-av-wrap">
                    ${p.active_aura ? `<div class="player-aura-ring aura-${p.active_aura}" style="inset:-12px;"></div>` : ''}
                    <div class="matchover-av">${getAv(p)}</div>
                </div>
                <div class="matchover-name">
                    <span data-color="${nc}" data-style="${ns}">${p.nome}</span>
                </div>
                ${p.active_title ? `<div class="player-title-badge">${p.active_title}</div>` : ''}
            </div>`;
        });
        document.getElementById('matchover-players').innerHTML = playersHtml;

        // Show
        const overlay = document.getElementById('matchover-state');
        overlay.classList.add('active');

        if (window.confetti) {
            const cols = isBlue ? ['#38bdf8','#ffffff','#0ea5e9'] : ['#f87171','#ffffff','#dc2626'];
            confetti({ particleCount: 160, spread: 80, origin: { y: 0.55 }, colors: cols });
        }

        pushLog(`GARA FINITA — Vince Squadra ${isBlue ? 'Blu' : 'Rossa'} (${s1} - ${s2})`, isBlue ? 'blue' : 'red');

        clearTimeout(matchOverTimer);
        matchOverTimer = setTimeout(() => {
            overlay.classList.remove('active');
        }, 11000);
    }

    /* ── Main update ─────────────────────────────────────────────── */
    function updateScoreboard(data) {
        const positions = ['s1_portiere','s1_attaccante','s2_portiere','s2_attaccante'];
        const filled = positions.filter(p => data.players && data.players[p]).length;
        const s1 = parseInt(data.score_s1) || 0;
        const s2 = parseInt(data.score_s2) || 0;

        // Table badge
        const tName = (data.table_id == 2) ? 'Calcetto Margot' : 'Calcetto Tuni';
        document.getElementById('table-name').textContent = tName;
        document.getElementById('table-badge').classList.add('visible');

        // Logs
        if (state.filled === 0 && filled > 0) pushLog('Match pronto — il riscaldamento inizia!', 'sys');
        if (filled > 0 && (s1 > 0 || s2 > 0) && state.scoreS1 === 0 && state.scoreS2 === 0) pushLog('Palla al centro — sfida iniziata!', 'sys');
        if (s1 > state.scoreS1) pushLog(`GOAL BLU! (${s1} - ${s2})`, 'blue');
        if (s2 > state.scoreS2) pushLog(`GOAL ROSSO! (${s1} - ${s2})`, 'red');
        if (s1 < state.scoreS1) pushLog('Gol annullato — Squadra Blu', 'sys');
        if (s2 < state.scoreS2) pushLog('Gol annullato — Squadra Rossa', 'sys');

        state.filled = filled;

        // Match over
        const matchOverEl = document.getElementById('matchover-state');
        if (data.recent_match && parseInt(data.recent_match.id) !== lastMatchId && !matchOverEl.classList.contains('active')) {
            lastMatchId = parseInt(data.recent_match.id);
            showMatchOver(data);
            return;
        }
        if (matchOverEl.classList.contains('active')) return;

        if (filled === 0) {
            showWaiting();
            animScore('score-s1', 0);
            animScore('score-s2', 0);
            state.scoreS1 = 0; state.scoreS2 = 0;
            return;
        }
        hideWaiting();

        animScore('score-s1', s1);
        animScore('score-s2', s2);
        state.scoreS1 = s1; state.scoreS2 = s2;

        // Render players
        const ps = data.players;
        document.getElementById('players-s1').innerHTML =
            renderPlayerCard(ps.s1_portiere,   'Portiere',   'blue') +
            renderPlayerCard(ps.s1_attaccante, 'Attaccante', 'blue');
        document.getElementById('players-s2').innerHTML =
            renderPlayerCard(ps.s2_portiere,   'Portiere',   'red') +
            renderPlayerCard(ps.s2_attaccante, 'Attaccante', 'red');
    }

    /* ── Fetch loop ─────────────────────────────────────────────── */
    async function fetchLive() {
        try {
            const res = await fetch(LIVE_API_URL);
            if (!res.ok) return;
            const data = await res.json();
            if (data.players !== undefined) updateScoreboard(data);
        } catch(e) { console.warn('Scoreboard fetch error', e); }
    }

    /* ── Manual score buttons ───────────────────────────────────── */
    async function adjustScore(team, action) {
        const btn = event.currentTarget;
        if (btn.disabled) return;
        btn.disabled = true;
        try {
            const res = await fetch(LIVE_API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action, team, table: tableId })
            });
            if (res.ok) await fetchLive();
        } catch(e) { console.error(e); }
        finally { btn.disabled = false; }
    }

    /* ── Boot ───────────────────────────────────────────────────── */
    fetchLive();
    setInterval(fetchLive, 1000);
</script>

<script src="aura-engine.js?v=<?php echo time(); ?>"></script>
</body>
</html>
