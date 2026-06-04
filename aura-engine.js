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

    /* ─── ICE AURA (Crystalline Orbiting Shards) ─────────────────────── */
    class IceAura extends LocalAura {
        tick(dt) {
            if (this.width < 10 || this.height < 10) return;
            const ctx = this.ctx;
            ctx.clearRect(0, 0, this.width, this.height);
            ctx.globalCompositeOperation = 'screen';

            this.timer += dt;
            const cx = this.width / 2;
            const cy = this.height / 2;
            const avatarRadius = (this.width - 40) / 2;

            // Spawn ice crystals on the outer ring
            if (this.particles.length < 40 && Math.random() < 0.5) {
                const angle = Math.random() * Math.PI * 2;
                const orbitR = avatarRadius + 8 + Math.random() * 12;
                this.particles.push({
                    angle,
                    orbitR,
                    orbitSpeed: (0.6 + Math.random() * 1.2) * (Math.random() < 0.5 ? 1 : -1),
                    size: 1.5 + Math.random() * 3,
                    life: 1.0,
                    decay: 0.25 + Math.random() * 0.3,
                    rotation: Math.random() * Math.PI * 2,
                    rotSpeed: (Math.random() - 0.5) * 4,
                    drift: (Math.random() - 0.5) * 6
                });
            }

            // Icy inner glow ring
            const ringGrad = ctx.createRadialGradient(cx, cy, avatarRadius * 0.9, cx, cy, avatarRadius * 1.25);
            ringGrad.addColorStop(0, 'rgba(186, 230, 253, 0.0)');
            ringGrad.addColorStop(0.4, 'rgba(125, 211, 252, 0.25)');
            ringGrad.addColorStop(0.7, 'rgba(56, 189, 248, 0.12)');
            ringGrad.addColorStop(1, 'rgba(56, 189, 248, 0)');
            ctx.beginPath();
            ctx.arc(cx, cy, avatarRadius * 1.25, 0, Math.PI * 2);
            ctx.fillStyle = ringGrad;
            ctx.fill();

            for (let i = this.particles.length - 1; i >= 0; i--) {
                const p = this.particles[i];
                p.life -= p.decay * dt;
                if (p.life <= 0) { this.particles.splice(i, 1); continue; }

                p.angle += p.orbitSpeed * dt;
                p.rotation += p.rotSpeed * dt;
                p.orbitR += p.drift * dt * p.life;

                const x = cx + Math.cos(p.angle) * p.orbitR;
                const y = cy + Math.sin(p.angle) * p.orbitR;
                const alpha = Math.min(1, p.life * 2);

                ctx.save();
                ctx.translate(x, y);
                ctx.rotate(p.rotation);

                // Draw hexagonal crystal shard
                ctx.beginPath();
                for (let s = 0; s < 6; s++) {
                    const a = (s / 6) * Math.PI * 2;
                    s === 0 ? ctx.moveTo(Math.cos(a) * p.size, Math.sin(a) * p.size)
                            : ctx.lineTo(Math.cos(a) * p.size, Math.sin(a) * p.size);
                }
                ctx.closePath();

                const grad = ctx.createRadialGradient(0, 0, 0, 0, 0, p.size);
                grad.addColorStop(0, `rgba(224, 242, 254, ${alpha * 0.95})`);
                grad.addColorStop(0.5, `rgba(125, 211, 252, ${alpha * 0.7})`);
                grad.addColorStop(1, `rgba(56, 189, 248, ${alpha * 0.3})`);
                ctx.fillStyle = grad;
                ctx.shadowBlur = 6;
                ctx.shadowColor = '#bae6fd';
                ctx.fill();

                ctx.restore();
            }
            ctx.globalCompositeOperation = 'source-over';
        }
    }

    /* ─── TOXIC AURA (Radioactive Green Vapor) ────────────────────────── */
    class ToxicAura extends LocalAura {
        tick(dt) {
            if (this.width < 10 || this.height < 10) return;
            const ctx = this.ctx;
            ctx.fillStyle = 'rgba(0,0,0,0.18)';
            ctx.globalCompositeOperation = 'destination-out';
            ctx.fillRect(0, 0, this.width, this.height);
            ctx.globalCompositeOperation = 'screen';

            this.timer += dt;
            const cx = this.width / 2;
            const cy = this.height / 2;
            const avatarRadius = (this.width - 40) / 2;

            // Spawn toxic bubbles rising from the bottom arc
            if (Math.random() < 0.55) {
                const angle = Math.PI * 0.1 + Math.random() * Math.PI * 0.8;
                const spawnR = avatarRadius * (0.9 + Math.random() * 0.2);
                this.particles.push({
                    x: cx + Math.cos(angle) * spawnR,
                    y: cy + Math.sin(angle) * spawnR,
                    vx: (Math.random() - 0.5) * 12,
                    vy: -(20 + Math.random() * 35),
                    size: (avatarRadius * 0.2) + Math.random() * (avatarRadius * 0.25),
                    life: 1.0,
                    decay: 0.5 + Math.random() * 0.7,
                    wobble: Math.random() * Math.PI * 2,
                    wobbleSpeed: 2 + Math.random() * 3
                });
            }

            for (let i = this.particles.length - 1; i >= 0; i--) {
                const p = this.particles[i];
                p.life -= p.decay * dt;
                if (p.life <= 0) { this.particles.splice(i, 1); continue; }

                p.wobble += p.wobbleSpeed * dt;
                p.x += (p.vx + Math.sin(p.wobble) * 10) * dt;
                p.y += p.vy * dt;

                const currentSize = p.size * (0.4 + p.life * 0.6);
                if (currentSize <= 0) continue;

                const grad = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, currentSize);
                if (p.life > 0.6) {
                    grad.addColorStop(0, `rgba(217, 255, 0, ${p.life * 0.7})`);
                    grad.addColorStop(0.4, `rgba(132, 204, 22, ${p.life * 0.6})`);
                    grad.addColorStop(1, `rgba(77, 124, 15, 0)`);
                } else if (p.life > 0.25) {
                    grad.addColorStop(0, `rgba(132, 204, 22, ${p.life * 0.6})`);
                    grad.addColorStop(0.6, `rgba(54, 83, 20, ${p.life * 0.4})`);
                    grad.addColorStop(1, `rgba(20, 40, 5, 0)`);
                } else {
                    grad.addColorStop(0, `rgba(77, 124, 15, ${p.life * 0.4})`);
                    grad.addColorStop(1, `rgba(10, 20, 0, 0)`);
                }

                ctx.beginPath();
                ctx.arc(p.x, p.y, currentSize, 0, Math.PI * 2);
                ctx.fillStyle = grad;
                ctx.fill();

                // Occasional drip particle
                if (Math.random() < 0.05 * p.life) {
                    ctx.beginPath();
                    ctx.arc(p.x + (Math.random() - 0.5) * currentSize, p.y + currentSize * 0.5, 1.5, 0, Math.PI * 2);
                    ctx.fillStyle = `rgba(166, 255, 0, ${p.life * 0.8})`;
                    ctx.fill();
                }
            }
            ctx.globalCompositeOperation = 'source-over';
        }
    }

    /* ─── GALAXY AURA (Rotating Nebula + Stars) ───────────────────────── */
    class GalaxyAura extends LocalAura {
        tick(dt) {
            if (this.width < 10 || this.height < 10) return;
            const ctx = this.ctx;
            ctx.fillStyle = 'rgba(0,0,0,0.12)';
            ctx.globalCompositeOperation = 'destination-out';
            ctx.fillRect(0, 0, this.width, this.height);
            ctx.globalCompositeOperation = 'screen';

            this.timer += dt;
            const cx = this.width / 2;
            const cy = this.height / 2;
            const avatarRadius = (this.width - 40) / 2;

            // Spawn stars and nebula wisps
            if (this.particles.length < 55 && Math.random() < 0.7) {
                const angle = Math.random() * Math.PI * 2;
                const dist = avatarRadius * (0.85 + Math.random() * 0.45);
                const isNebula = Math.random() < 0.3;
                this.particles.push({
                    angle,
                    dist,
                    orbitSpeed: (0.3 + Math.random() * 0.8) * (Math.random() < 0.5 ? 1 : -1),
                    size: isNebula ? 6 + Math.random() * 10 : 0.8 + Math.random() * 2,
                    life: 1.0,
                    decay: 0.2 + Math.random() * 0.4,
                    isNebula,
                    colorIdx: Math.floor(Math.random() * 4),
                    twinkle: Math.random() * Math.PI * 2,
                    twinkleSpeed: 3 + Math.random() * 5
                });
            }

            const nebulaColors = [
                [139, 92, 246],   // violet
                [99, 102, 241],   // indigo
                [59, 130, 246],   // blue
                [236, 72, 153]    // pink
            ];

            for (let i = this.particles.length - 1; i >= 0; i--) {
                const p = this.particles[i];
                p.life -= p.decay * dt;
                if (p.life <= 0) { this.particles.splice(i, 1); continue; }

                p.angle += p.orbitSpeed * dt;
                p.twinkle += p.twinkleSpeed * dt;

                const x = cx + Math.cos(p.angle) * p.dist;
                const y = cy + Math.sin(p.angle) * p.dist;
                const twinkleFactor = 0.5 + 0.5 * Math.sin(p.twinkle);
                const alpha = p.life * twinkleFactor;

                if (p.isNebula) {
                    const [r, g, b] = nebulaColors[p.colorIdx];
                    const grad = ctx.createRadialGradient(x, y, 0, x, y, p.size);
                    grad.addColorStop(0, `rgba(${r},${g},${b},${alpha * 0.4})`);
                    grad.addColorStop(1, `rgba(${r},${g},${b},0)`);
                    ctx.beginPath();
                    ctx.arc(x, y, p.size, 0, Math.PI * 2);
                    ctx.fillStyle = grad;
                    ctx.fill();
                } else {
                    // Star
                    ctx.beginPath();
                    ctx.arc(x, y, p.size * twinkleFactor, 0, Math.PI * 2);
                    ctx.fillStyle = `rgba(255, 255, 255, ${alpha * 0.9})`;
                    ctx.shadowBlur = p.size * 3;
                    ctx.shadowColor = `rgba(167, 139, 250, ${alpha})`;
                    ctx.fill();
                    ctx.shadowBlur = 0;
                }
            }

            // Galactic core glow
            const coreGrad = ctx.createRadialGradient(cx, cy, 0, cx, cy, avatarRadius * 0.6);
            coreGrad.addColorStop(0, `rgba(196, 181, 253, ${0.08 + Math.sin(this.timer * 1.5) * 0.03})`);
            coreGrad.addColorStop(1, 'rgba(139, 92, 246, 0)');
            ctx.beginPath();
            ctx.arc(cx, cy, avatarRadius * 0.6, 0, Math.PI * 2);
            ctx.fillStyle = coreGrad;
            ctx.fill();

            ctx.globalCompositeOperation = 'source-over';
        }
    }

    /* ─── BLOOD AURA (Falling Crimson Drops) ─────────────────────────── */
    class BloodAura extends LocalAura {
        tick(dt) {
            if (this.width < 10 || this.height < 10) return;
            const ctx = this.ctx;
            ctx.fillStyle = 'rgba(0,0,0,0.2)';
            ctx.globalCompositeOperation = 'destination-out';
            ctx.fillRect(0, 0, this.width, this.height);
            ctx.globalCompositeOperation = 'screen';

            this.timer += dt;
            const cx = this.width / 2;
            const cy = this.height / 2;
            const avatarRadius = (this.width - 40) / 2;

            // Spawn drips from top arc
            if (Math.random() < 0.35) {
                const angle = Math.PI * 1.1 + Math.random() * Math.PI * 0.8;
                const spawnR = avatarRadius * (0.9 + Math.random() * 0.15);
                this.particles.push({
                    x: cx + Math.cos(angle) * spawnR,
                    y: cy + Math.sin(angle) * spawnR,
                    vx: (Math.random() - 0.5) * 6,
                    vy: 25 + Math.random() * 50,
                    size: 2 + Math.random() * 4,
                    elongation: 1 + Math.random() * 2.5,
                    life: 1.0,
                    decay: 0.6 + Math.random() * 0.8
                });
            }

            for (let i = this.particles.length - 1; i >= 0; i--) {
                const p = this.particles[i];
                p.life -= p.decay * dt;
                if (p.life <= 0) { this.particles.splice(i, 1); continue; }

                p.vy += 60 * dt; // gravity acceleration
                p.x += p.vx * dt;
                p.y += p.vy * dt;

                const alpha = Math.min(1, p.life * 1.8);
                const dropH = p.size * p.elongation * (0.5 + p.life * 0.5);
                const dropW = p.size * (0.3 + p.life * 0.7);

                ctx.save();
                ctx.translate(p.x, p.y);

                // Teardrop shape
                ctx.beginPath();
                ctx.ellipse(0, 0, dropW, dropH, 0, 0, Math.PI * 2);

                const grad = ctx.createRadialGradient(0, -dropH * 0.2, 0, 0, 0, dropH);
                grad.addColorStop(0, `rgba(255, 50, 50, ${alpha * 0.9})`);
                grad.addColorStop(0.5, `rgba(180, 0, 0, ${alpha * 0.7})`);
                grad.addColorStop(1, `rgba(80, 0, 0, 0)`);
                ctx.fillStyle = grad;
                ctx.shadowBlur = 5;
                ctx.shadowColor = '#b91c1c';
                ctx.fill();
                ctx.shadowBlur = 0;

                ctx.restore();
            }

            // Deep crimson edge glow
            const edgeGrad = ctx.createRadialGradient(cx, cy, avatarRadius * 0.85, cx, cy, avatarRadius * 1.2);
            edgeGrad.addColorStop(0, 'rgba(185, 28, 28, 0)');
            edgeGrad.addColorStop(0.5, `rgba(185, 28, 28, ${0.12 + Math.sin(this.timer * 2) * 0.04})`);
            edgeGrad.addColorStop(1, 'rgba(127, 29, 29, 0)');
            ctx.beginPath();
            ctx.arc(cx, cy, avatarRadius * 1.2, 0, Math.PI * 2);
            ctx.fillStyle = edgeGrad;
            ctx.fill();

            ctx.globalCompositeOperation = 'source-over';
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

        attachToClass('aura-fire',   FireAura);
        attachToClass('aura-storm',  StormAura);
        attachToClass('aura-void',   VoidAura);
        attachToClass('aura-flare',  FlareAura);
        attachToClass('aura-ice',    IceAura);
        attachToClass('aura-toxic',  ToxicAura);
        attachToClass('aura-galaxy', GalaxyAura);
        attachToClass('aura-blood',  BloodAura);
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
