/**
 * Aura Engine v7 — Hyper-Realistic WebGL/Canvas Quality Auras
 *
 * Implements localized Canvas engines inside every single aura element.
 * Uses IntersectionObserver to pause rendering when off-screen for 60FPS.
 * Hyper-realistic physics: Metaball volumetric fire, ray-casted lightning,
 * orbiting gravity particles (Void), and volumetric light rays (Flare).
 */
(function () {
    'use strict';

    if (window.AuraEngineInitialized) return;
    window.AuraEngineInitialized = true;

    // Track active aura canvases
    const activeInstances = new Set();

    // The animation loop
    let lastTime = performance.now();
    let animFrame = null;

    function renderLoop(ts) {
        const dt = Math.min((ts - lastTime) / 1000, 0.05); // cap dt to 50ms to prevent glitches if tab frozen
        lastTime = ts;

        activeInstances.forEach(instance => instance.tick(dt));

        animFrame = requestAnimationFrame(renderLoop);
    }

    // Intersection observer for performance
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            const instance = entry.target.__auraInstance;
            if (instance) {
                if (entry.isIntersecting) {
                    activeInstances.add(instance);
                } else {
                    activeInstances.delete(instance);
                }
            }
        });
    }, { threshold: 0.1 });

    /* ─── Base Aura Class ────────────────────────────────────────────── */
    class LocalAura {
        constructor(el, type) {
            this.el = el;
            this.type = type;
            this.canvas = document.createElement('canvas');
            this.ctx = this.canvas.getContext('2d', { alpha: true });
            
            this.canvas.style.cssText = `
                position: absolute;
                inset: -20px; /* Bleed outside the CSS circle for sparks/rays */
                width: calc(100% + 40px);
                height: calc(100% + 40px);
                pointer-events: none;
                z-index: 5;
            `;
            
            // Re-stack standard CSS glow behind the canvas
            this.el.style.overflow = 'visible';
            this.el.appendChild(this.canvas);

            this.width = 0;
            this.height = 0;
            this.particles = [];
            this.timer = 0;

            this.resizeObserver = new ResizeObserver(() => this.resize());
            this.resizeObserver.observe(this.el);
            this.resize();

            this.el.__auraInstance = this;
            observer.observe(this.el);
        }

        resize() {
            const rect = this.el.getBoundingClientRect();
            // We use inset -20px, so canvas is 40px wider and taller than the element
            this.width = rect.width + 40;
            this.height = rect.height + 40;
            this.canvas.width = this.width;
            this.canvas.height = this.height;
        }

        destroy() {
            observer.unobserve(this.el);
            this.resizeObserver.disconnect();
            if (this.canvas.parentNode) this.canvas.parentNode.removeChild(this.canvas);
            activeInstances.delete(this);
            delete this.el.__auraInstance;
        }
    }

        /* ─── REALISTIC FIRE AURA ────────────────────────────────────────── */
    class FireAura extends LocalAura {
        tick(dt) {
            if (this.width < 10 || this.height < 10) return;
            const ctx = this.ctx;
            ctx.clearRect(0, 0, this.width, this.height);

            // Volumetric overlapping
            ctx.globalCompositeOperation = 'screen';

            const cx = this.width / 2;
            const cy = this.height / 2;
            const avatarRadius = (this.width - 40) / 2;

            // Spawn flames at the bottom arc of the circle
            const spawnRate = this.width > 100 ? 5 : 3;
            for (let i = 0; i < spawnRate; i++) {
                if (Math.random() < 0.7) {
                    // Span from bottom-left to bottom-right of the circle 
                    // Math.PI * 0.1 to Math.PI * 0.9 represents the bottom semicircle.
                    const angle = Math.PI * 0.15 + (Math.random() * Math.PI * 0.7);
                    
                    // Spawn mostly tight to the border of the avatar (from 0.85 to 1.15 times the radius)
                    const spawnR = avatarRadius * (0.85 + Math.random() * 0.3);
                    
                    this.particles.push({
                        x: cx + Math.cos(angle) * spawnR,
                        y: cy + Math.sin(angle) * spawnR,
                        vx: (Math.random() - 0.5) * 15,
                        vy: - (30 + Math.random() * 40),
                        life: 1.0,
                        decay: 0.8 + Math.random() * 0.9,
                        size: (avatarRadius * 0.35) + Math.random() * (avatarRadius * 0.3),
                        phase: Math.random() * Math.PI * 2
                    });
                }
            }

            for (let i = this.particles.length - 1; i >= 0; i--) {
                const p = this.particles[i];
                p.life -= p.decay * dt;
                
                if (p.life <= 0) {
                    this.particles.splice(i, 1);
                    continue;
                }

                // S-curve organic motion
                p.x += (p.vx + Math.sin(p.phase + p.life * 5) * 15) * dt;
                p.y += p.vy * dt;
                
                // Fire shrinks as it rises
                const currentSize = p.size * (0.3 + p.life * 0.7);
                if (currentSize <= 0) continue;

                const grad = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, currentSize);
                
                // Color grades from white-hot -> yellow -> orange -> subtle red
                if (p.life > 0.7) {
                    grad.addColorStop(0, `rgba(255, 255, 200, ${p.life})`);
                    grad.addColorStop(0.3, `rgba(255, 200, 0, ${p.life * 0.8})`);
                    grad.addColorStop(1, `rgba(255, 50, 0, 0)`);
                } else if (p.life > 0.3) {
                    grad.addColorStop(0, `rgba(255, 150, 0, ${p.life * 0.8})`);
                    grad.addColorStop(0.5, `rgba(255, 50, 0, ${p.life * 0.5})`);
                    grad.addColorStop(1, `rgba(200, 0, 0, 0)`);
                } else {
                    grad.addColorStop(0, `rgba(255, 50, 0, ${p.life * 0.5})`);
                    grad.addColorStop(1, `rgba(100, 0, 0, 0)`);
                }

                ctx.beginPath();
                ctx.arc(p.x, p.y, currentSize, 0, Math.PI * 2);
                ctx.fillStyle = grad;
                ctx.fill();
            }
            
            ctx.globalCompositeOperation = 'source-over';
        }
    }

    /* ─── REALISTIC STORM AURA (BRANCHING LIGHTNING) ─────────────────── */
    class StormAura extends LocalAura {
        tick(dt) {
            if (this.width < 10 || this.height < 10) return;
            const ctx = this.ctx;
            // Native trail effect for glowing plasma
            ctx.fillStyle = 'rgba(0, 0, 0, 0.2)';
            ctx.globalCompositeOperation = 'destination-out';
            ctx.fillRect(0, 0, this.width, this.height);
            ctx.globalCompositeOperation = 'lighter';

            // Random lightning strike
            if (Math.random() < 0.08) { // 8% chance per frame per storm aura
                this.generateLightning();
            }

            // Draw lightning branches
            for (let i = this.particles.length - 1; i >= 0; i--) {
                const b = this.particles[i];
                b.life -= dt * (1.0 / b.duration); // normalize duration

                if (b.life <= 0) {
                    this.particles.splice(i, 1);
                    continue;
                }

                const alpha = b.life > 0.5 ? 1 : b.life * 2;
                
                // Outer glow
                ctx.beginPath();
                ctx.moveTo(b.path[0].x, b.path[0].y);
                for (let j = 1; j < b.path.length; j++) {
                    ctx.lineTo(b.path[j].x, b.path[j].y);
                }
                ctx.strokeStyle = `rgba(34, 211, 238, ${alpha * 0.6})`;
                ctx.lineWidth = 3 + Math.random() * 2;
                ctx.shadowColor = '#0ea5e9';
                ctx.shadowBlur = 10;
                ctx.stroke();

                // Inner core
                ctx.strokeStyle = `rgba(255, 255, 255, ${alpha + 0.2})`;
                ctx.lineWidth = 1 + Math.random() * 1;
                ctx.shadowBlur = 0;
                ctx.stroke();
            }
            ctx.globalCompositeOperation = 'source-over';
        }

        generateLightning() {
            const cx = this.width / 2;
            const cy = this.height / 2;
            const avatarRadius = (this.width - 40) / 2;
            const r = avatarRadius + 5; // Lightning runs along the outside of the circle
            
            // Start point on the circular edge
            const angle1 = Math.random() * Math.PI * 2;
            let currentPt = { x: cx + Math.cos(angle1) * r, y: cy + Math.sin(angle1) * r };
            const path = [currentPt];
            
            // Random walk towards a point traversing the circle
            const oppAngle = angle1 + Math.PI + (Math.random() - 0.5);
            let steps = 4 + Math.floor(Math.random() * 5);
            
            for (let i = 0; i < steps; i++) {
                const stepLength = r * 0.4 + Math.random() * r * 0.3;
                const stepAngle = oppAngle + (Math.random() - 0.5) * 1.5; // jagged direction
                
                const nextPt = {
                    x: currentPt.x + Math.cos(stepAngle) * stepLength,
                    y: currentPt.y + Math.sin(stepAngle) * stepLength
                };
                path.push(nextPt);
                currentPt = nextPt;
            }

            this.particles.push({
                path: path,
                life: 1.0,
                duration: 0.15 + Math.random() * 0.15 // Fast flash (150-300ms)
            });
        }
    }

    /* ─── REALISTIC VOID AURA (ACCRETION DISK) ───────────────────────── */
    class VoidAura extends LocalAura {
        tick(dt) {
            if (this.width < 10 || this.height < 10) return;
            const ctx = this.ctx;
            // Ghosting effect for deep space trailing
            ctx.fillStyle = 'rgba(0, 0, 0, 0.15)';
            ctx.globalCompositeOperation = 'destination-out';
            ctx.fillRect(0, 0, this.width, this.height);
            ctx.globalCompositeOperation = 'screen';

            this.timer += dt;
            const cx = this.width / 2;
            const cy = this.height / 2;
            const avatarRadius = (this.width - 40) / 2;
            const spawnRadius = avatarRadius + 15;

            // Spawn matter entering the black hole exactly at the circular bounds
            if (this.particles.length < 60 && Math.random() < 0.6) {
                const angle = Math.random() * Math.PI * 2;
                this.particles.push({
                    angle: angle,
                    distance: spawnRadius + Math.random() * 10,
                    size: 1 + Math.random() * 2.5,
                    speed: 1.0 + Math.random() * 2.5,
                    spiralAlpha: 0 // fade in
                });
            }

            for (let i = this.particles.length - 1; i >= 0; i--) {
                const p = this.particles[i];
                
                // Spiraling math
                p.angle += p.speed * dt;
                p.distance -= (15 / Math.max(1, p.distance * 0.1)) * dt * 20; // Accelerates as it gets closer
                
                if (p.spiralAlpha < 1) p.spiralAlpha += dt * 2;

                if (p.distance <= 5) {
                    this.particles.splice(i, 1);
                    continue;
                }

                const x = cx + Math.cos(p.angle) * p.distance;
                const y = cy + Math.sin(p.angle) * p.distance;
                
                // Color maps to distance (purple edge -> blinding white center)
                const distRatio = Math.max(0, p.distance / spawnRadius);
                const alpha = Math.min(1, p.spiralAlpha) * distRatio * 1.5;
                
                // Draw particle
                ctx.beginPath();
                ctx.arc(x, y, p.size * (distRatio + 0.5), 0, Math.PI * 2);
                if (distRatio < 0.3) {
                    ctx.fillStyle = `rgba(255, 200, 255, ${alpha})`;
                } else if (distRatio < 0.7) {
                    ctx.fillStyle = `rgba(168, 85, 247, ${alpha})`; // Purple 500
                } else {
                    ctx.fillStyle = `rgba(88, 28, 135, ${alpha})`; // Deep purple
                }
                ctx.shadowBlur = p.size * 2;
                ctx.shadowColor = '#d8b4fe';
                ctx.fill();
                ctx.shadowBlur = 0;
            }
            
            // Draw central absolute darkness perfectly matching the avatar circular form
            ctx.globalCompositeOperation = 'source-over';
            ctx.beginPath();
            ctx.arc(cx, cy, avatarRadius * 1.02, 0, Math.PI * 2);
            const voidGrad = ctx.createRadialGradient(cx, cy, 0, cx, cy, avatarRadius * 1.02);
            voidGrad.addColorStop(0, 'rgba(0,0,0,1)');
            voidGrad.addColorStop(0.85, 'rgba(0,0,0,0.95)');
            voidGrad.addColorStop(1, 'rgba(0,0,0,0)');
            ctx.fillStyle = voidGrad;
            ctx.fill();
        }
    }

    /* ─── REALISTIC FLARE AURA (SOLAR CORONA) ────────────────────────── */
    class FlareAura extends LocalAura {
        tick(dt) {
            if (this.width < 10 || this.height < 10) return;
            const ctx = this.ctx;
            ctx.clearRect(0, 0, this.width, this.height);
            ctx.globalCompositeOperation = 'screen';
            
            this.timer += dt;
            const cx = this.width / 2;
            const cy = this.height / 2;
            const avatarRadius = (this.width - 40) / 2;

            // Maintain a persistent array of rays
            if (this.particles.length === 0) {
                const numRays = 14 + Math.floor(Math.random() * 6);
                for (let i = 0; i < numRays; i++) {
                    this.particles.push({
                        angle: (i / numRays) * Math.PI * 2 + Math.random() * 0.3,
                        speed: (Math.random() - 0.5) * 0.3,
                        length: 0.8 + Math.random() * 0.5, // multiplier relative to avatar
                        width: 1.5 + Math.random() * 3.5,
                        phase: Math.random() * Math.PI * 2,
                        pulseFreq: 0.5 + Math.random() * 1.5
                    });
                }
            }

            // Draw the blinding corona core hugging the avatar circle tightly
            const pulse = 1.0 + Math.sin(this.timer * 3) * 0.04;
            ctx.beginPath();
            ctx.arc(cx, cy, avatarRadius * 1.15 * pulse, 0, Math.PI * 2);
            const coreGrad = ctx.createRadialGradient(cx, cy, avatarRadius * 0.5, cx, cy, avatarRadius * 1.15 * pulse);
            coreGrad.addColorStop(0, 'rgba(255, 255, 255, 0.95)');
            coreGrad.addColorStop(0.6, 'rgba(255, 230, 100, 0.7)');
            coreGrad.addColorStop(1, 'rgba(255, 200, 50, 0)');
            ctx.fillStyle = coreGrad;
            ctx.fill();

            // Draw rotating volumetric light rays shooting out radially
            for (let i = 0; i < this.particles.length; i++) {
                const r = this.particles[i];
                r.angle += r.speed * dt;
                
                const intensity = (Math.sin(this.timer * r.pulseFreq + r.phase) + 1) * 0.5; // 0 to 1
                if (intensity < 0.05) continue;

                const rayLen = avatarRadius * r.length;
                
                ctx.save();
                ctx.translate(cx, cy);
                ctx.rotate(r.angle);
                
                const rayGrad = ctx.createLinearGradient(0, 0, rayLen, 0);
                rayGrad.addColorStop(0, `rgba(255, 230, 100, ${0.4 * intensity})`);
                rayGrad.addColorStop(1, `rgba(251, 146, 60, 0)`);
                
                ctx.fillStyle = rayGrad;
                
                // Draw tapered ray
                ctx.beginPath();
                ctx.moveTo(0, -r.width * 2);
                ctx.lineTo(rayLen, -r.width / 2);
                ctx.lineTo(rayLen, r.width / 2);
                ctx.lineTo(0, r.width * 2);
                ctx.fill();
                
                ctx.restore();
            }

            // Occasional floating sparkle along the edge
            if (Math.random() < 0.1) {
                const ang = Math.random() * Math.PI * 2;
                const d = avatarRadius * (0.9 + Math.random() * 0.3);
                ctx.beginPath();
                ctx.arc(cx + Math.cos(ang)*d, cy + Math.sin(ang)*d, 1 + Math.random() * 2, 0, Math.PI*2);
                ctx.fillStyle = 'rgba(255,255,255,0.8)';
                ctx.shadowBlur = 4;
                ctx.shadowColor = 'white';
                ctx.fill();
                ctx.shadowBlur = 0;
            }
        }
    }

    /* ─── Setup and Orchestration ────────────────────────────────────── */
    function attachAuras() {
        const attachToClass = (cls, AuraClass) => {
            const els = document.querySelectorAll(`.${cls}`);
            els.forEach(el => {
                if (!el.__auraInstance) {
                    new AuraClass(el, cls);
                }
            });
        };

        attachToClass('aura-fire', FireAura);
        attachToClass('aura-storm', StormAura);
        attachToClass('aura-void', VoidAura);
        attachToClass('aura-flare', FlareAura);
    }

    function init() {
        // Find existing global canvases from v6 and destroy them if they exist
        const oldFire = document.getElementById('aura-fire-canvas');
        if (oldFire) oldFire.remove();
        const oldStorm = document.getElementById('aura-storm-canvas');
        if (oldStorm) oldStorm.remove();

        attachAuras();
        
        // We use MutationObserver to attach auras to newly created elements dynamically (e.g. scoreboard reload)
        const mutObs = new MutationObserver((mutations) => {
            let shouldCheck = false;
            mutations.forEach(m => {
                if (m.addedNodes.length > 0) shouldCheck = true;
            });
            if (shouldCheck) attachAuras();
        });
        mutObs.observe(document.body, { childList: true, subtree: true });

        animFrame = requestAnimationFrame(renderLoop);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
