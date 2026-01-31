/**
 * PROMPT Band - Glitch Effects System
 * Digital, unstable, alive - like hardware running hot
 *
 * @author PROMPT AI Band
 * @version 1.0.0
 */

(function(global) {
  'use strict';

  // ============================================================================
  // Configuration
  // ============================================================================
  const CONFIG = {
    glitchLevel: 2, // 0=off, 1=subtle, 2=medium, 3=heavy
    cursorTrailEnabled: true,
    cursorTrailMaxParticles: 20,
    cursorTrailThrottle: 50, // ms between particles
    errorMessages: [
      'BUFFER OVERFLOW',
      'CONTEXT LIMIT REACHED',
      'SIGNAL LOST',
      'MEMORY FRAGMENTED',
      'TOKEN OVERFLOW',
      'ATTENTION DRIFT',
      'SYNC ERROR',
      'LATENCY SPIKE'
    ],
    glitchCharacters: '!@#$%^&*()_+-=[]{}|;:,.<>?/~`0123456789',
    memoryFadeThreshold: 0.3, // Percentage of element that must be out of view
    scanlineDefaults: {
      opacity: 0.03,
      spacing: 4
    }
  };

  // ============================================================================
  // State
  // ============================================================================
  const state = {
    initialized: false,
    observers: [],
    cursorTrailParticles: [],
    lastCursorPosition: { x: 0, y: 0 },
    lastTrailTime: 0,
    animationFrameId: null,
    trailColor: 'rgba(255, 139, 245, 0.6)'
  };

  // ============================================================================
  // Utility Functions
  // ============================================================================

  /**
   * Throttle function execution
   */
  function throttle(func, limit) {
    let inThrottle;
    return function(...args) {
      if (!inThrottle) {
        func.apply(this, args);
        inThrottle = true;
        setTimeout(() => inThrottle = false, limit);
      }
    };
  }

  /**
   * Debounce function execution
   */
  function debounce(func, wait) {
    let timeout;
    return function(...args) {
      clearTimeout(timeout);
      timeout = setTimeout(() => func.apply(this, args), wait);
    };
  }

  /**
   * Get random integer between min and max (inclusive)
   */
  function randomInt(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
  }

  /**
   * Get random item from array
   */
  function randomItem(arr) {
    return arr[randomInt(0, arr.length - 1)];
  }

  /**
   * Generate scrambled text
   */
  function scrambleText(text, intensity = 0.3) {
    return text.split('').map(char => {
      if (char === ' ' || Math.random() > intensity) return char;
      return randomItem(CONFIG.glitchCharacters.split(''));
    }).join('');
  }

  // ============================================================================
  // Chromatic Aberration
  // ============================================================================

  /**
   * Apply chromatic aberration effect to an element
   */
  function applyChromatic(element, duration = 300) {
    if (!element) return;

    // For text elements
    if (element.textContent && !element.querySelector('img')) {
      element.setAttribute('data-text', element.textContent);
      element.classList.add('chromatic-aberration');

      setTimeout(() => {
        element.classList.remove('chromatic-aberration');
      }, duration);
    }
    // For images
    else if (element.tagName === 'IMG' || element.querySelector('img')) {
      element.classList.add('chromatic-aberration-img', 'active');

      setTimeout(() => {
        element.classList.remove('active');
      }, duration);
    }
  }

  /**
   * Initialize chromatic aberration on hover
   */
  function initChromaticHover() {
    document.querySelectorAll('[data-chromatic]').forEach(el => {
      el.addEventListener('mouseenter', () => {
        applyChromatic(el);
      });
    });
  }

  // ============================================================================
  // Glitch Text Effect
  // ============================================================================

  /**
   * Trigger glitch effect on element
   * @param {HTMLElement} element - Target element
   * @param {string} intensity - 'subtle', 'medium', or 'heavy'
   * @param {number} duration - Duration in milliseconds
   */
  function triggerGlitch(element, intensity = 'medium', duration = 500) {
    if (!element) return;

    const originalText = element.textContent;
    element.setAttribute('data-glitch', originalText);
    element.setAttribute('data-intensity', intensity);
    element.classList.add('glitch-text', 'glitch-active');

    // Character scramble effect
    let iterations = 0;
    const maxIterations = Math.floor(duration / 50);

    const scrambleInterval = setInterval(() => {
      if (iterations >= maxIterations) {
        clearInterval(scrambleInterval);
        element.textContent = originalText;
        element.classList.remove('glitch-active');
        return;
      }

      const scrambleIntensity = intensity === 'heavy' ? 0.5 :
                                intensity === 'medium' ? 0.3 : 0.15;
      element.textContent = scrambleText(originalText, scrambleIntensity);
      iterations++;
    }, 50);

    return {
      stop: () => {
        clearInterval(scrambleInterval);
        element.textContent = originalText;
        element.classList.remove('glitch-active');
      }
    };
  }

  /**
   * Initialize glitch text elements
   */
  function initGlitchText() {
    document.querySelectorAll('[data-glitch]').forEach(el => {
      const text = el.getAttribute('data-glitch') || el.textContent;
      el.setAttribute('data-glitch', text);
      el.classList.add('glitch-text');

      // Set default intensity if not specified
      if (!el.hasAttribute('data-intensity')) {
        el.setAttribute('data-intensity', 'subtle');
      }
    });
  }

  // ============================================================================
  // Scan Lines
  // ============================================================================

  /**
   * Create or update scanlines overlay
   */
  function createScanlines(options = {}) {
    const { opacity, spacing, intensity } = {
      ...CONFIG.scanlineDefaults,
      ...options
    };

    let scanlines = document.querySelector('.scanlines');

    if (!scanlines) {
      scanlines = document.createElement('div');
      scanlines.className = 'scanlines';
      document.body.appendChild(scanlines);
    }

    if (intensity) {
      scanlines.setAttribute('data-intensity', intensity);
    } else {
      scanlines.style.setProperty('--scanline-opacity', opacity);
      scanlines.style.setProperty('--scanline-spacing', `${spacing}px`);
    }

    return scanlines;
  }

  /**
   * Toggle scanlines active state
   */
  function toggleScanlines(active = true) {
    const scanlines = document.querySelector('.scanlines');
    if (scanlines) {
      scanlines.classList.toggle('active', active);
    }
  }

  // ============================================================================
  // Data Corruption Effect
  // ============================================================================

  /**
   * Apply data corruption effect to element
   */
  function corruptData(element, duration = 500) {
    if (!element) return;

    element.classList.add('data-corruption', 'corrupting');

    // Store original state
    const originalContent = element.innerHTML;
    const textNodes = [];

    // Find all text nodes
    const walker = document.createTreeWalker(
      element,
      NodeFilter.SHOW_TEXT,
      null,
      false
    );

    while (walker.nextNode()) {
      textNodes.push({
        node: walker.currentNode,
        original: walker.currentNode.textContent
      });
    }

    // Scramble text periodically
    let elapsed = 0;
    const interval = 50;
    const scrambleTimer = setInterval(() => {
      elapsed += interval;
      if (elapsed >= duration) {
        clearInterval(scrambleTimer);
        textNodes.forEach(({ node, original }) => {
          node.textContent = original;
        });
        element.classList.remove('corrupting');
        return;
      }

      textNodes.forEach(({ node, original }) => {
        const progress = elapsed / duration;
        const scrambleAmount = progress < 0.5 ? progress * 0.8 : (1 - progress) * 0.8;
        node.textContent = scrambleText(original, scrambleAmount);
      });
    }, interval);

    return {
      stop: () => {
        clearInterval(scrambleTimer);
        textNodes.forEach(({ node, original }) => {
          node.textContent = original;
        });
        element.classList.remove('corrupting');
      }
    };
  }

  // ============================================================================
  // Cursor Trail
  // ============================================================================

  /**
   * Create a trail particle at position
   */
  function createTrailParticle(x, y) {
    const particle = document.createElement('div');
    particle.className = 'cursor-trail-particle';

    // Randomize size slightly
    const sizes = ['small', '', 'large'];
    const size = sizes[randomInt(0, 2)];
    if (size) particle.setAttribute('data-size', size);

    // Apply position with slight offset for organic feel
    particle.style.left = `${x + randomInt(-3, 3)}px`;
    particle.style.top = `${y + randomInt(-3, 3)}px`;
    particle.style.setProperty('--trail-color', state.trailColor);

    document.body.appendChild(particle);
    state.cursorTrailParticles.push(particle);

    // Remove after animation
    setTimeout(() => {
      particle.remove();
      const index = state.cursorTrailParticles.indexOf(particle);
      if (index > -1) state.cursorTrailParticles.splice(index, 1);
    }, 600);

    // Cleanup excess particles
    while (state.cursorTrailParticles.length > CONFIG.cursorTrailMaxParticles) {
      const old = state.cursorTrailParticles.shift();
      old.remove();
    }
  }

  /**
   * Handle cursor movement
   */
  function handleCursorMove(e) {
    if (!CONFIG.cursorTrailEnabled) return;

    const now = Date.now();
    if (now - state.lastTrailTime < CONFIG.cursorTrailThrottle) return;

    state.lastTrailTime = now;
    createTrailParticle(e.clientX, e.clientY);
  }

  /**
   * Enable/disable cursor trail
   */
  function cursorTrail(enable = true) {
    CONFIG.cursorTrailEnabled = enable;

    if (!enable) {
      // Clear existing particles
      state.cursorTrailParticles.forEach(p => p.remove());
      state.cursorTrailParticles = [];
    }
  }

  /**
   * Set cursor trail color
   */
  function setTrailColor(color) {
    state.trailColor = color;
  }

  // ============================================================================
  // Memory Fade Effect
  // ============================================================================

  /**
   * Initialize memory fade with Intersection Observer
   */
  function initMemoryFade() {
    const elements = document.querySelectorAll('[data-memory-fade]');

    if (!elements.length) return;

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        const el = entry.target;
        const ratio = entry.intersectionRatio;

        if (ratio < CONFIG.memoryFadeThreshold) {
          el.classList.add('memory-fade', 'fading');

          // Calculate fade level (1-4) based on how far out of view
          const fadeLevel = Math.min(4, Math.ceil((1 - ratio) * 4));
          el.setAttribute('data-fade-level', fadeLevel);
        } else {
          el.classList.remove('fading');
          el.removeAttribute('data-fade-level');
        }
      });
    }, {
      threshold: [0, 0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8, 0.9, 1],
      rootMargin: '-10% 0px -60% 0px' // Focus on elements leaving the top
    });

    elements.forEach(el => {
      el.classList.add('memory-fade');
      observer.observe(el);
    });

    state.observers.push(observer);
  }

  // ============================================================================
  // Error Flash Messages
  // ============================================================================

  /**
   * Show error flash message
   * @param {string} message - Message to display (or random if not provided)
   * @param {string} type - 'error', 'warning', or 'info'
   * @param {number} duration - Display duration in milliseconds
   */
  function flashError(message, type = 'error', duration = 800) {
    const msg = message || randomItem(CONFIG.errorMessages);

    // Remove any existing flash
    const existing = document.querySelector('.error-flash');
    if (existing) existing.remove();

    const flash = document.createElement('div');
    flash.className = 'error-flash';
    flash.textContent = msg;
    flash.setAttribute('data-type', type);

    document.body.appendChild(flash);

    // Trigger animation
    requestAnimationFrame(() => {
      flash.classList.add('active');
    });

    // Remove after duration
    setTimeout(() => {
      flash.remove();
    }, duration);

    return flash;
  }

  /**
   * Schedule random error flashes
   */
  function scheduleRandomErrors(minInterval = 15000, maxInterval = 45000) {
    function scheduleNext() {
      const delay = randomInt(minInterval, maxInterval);
      setTimeout(() => {
        if (CONFIG.glitchLevel > 0) {
          flashError();
        }
        scheduleNext();
      }, delay);
    }

    scheduleNext();
  }

  // ============================================================================
  // VHS Tracking Effect
  // ============================================================================

  /**
   * Apply VHS tracking effect to element
   */
  function applyVHSTracking(element, options = {}) {
    if (!element) return;

    const { duration = 2000, noisy = false, heavy = false } = options;

    element.classList.add('vhs-tracking', 'active');
    if (noisy) element.classList.add('noisy');
    if (heavy) element.classList.add('heavy');

    setTimeout(() => {
      element.classList.remove('active', 'noisy', 'heavy');
    }, duration);
  }

  /**
   * Initialize VHS tracking on hover
   */
  function initVHSHover() {
    document.querySelectorAll('[data-vhs]').forEach(el => {
      el.classList.add('vhs-tracking');

      el.addEventListener('mouseenter', () => {
        el.classList.add('active');
      });

      el.addEventListener('mouseleave', () => {
        el.classList.remove('active');
      });
    });
  }

  // ============================================================================
  // Scroll-Triggered Effects
  // ============================================================================

  /**
   * Initialize scroll-triggered chromatic aberration
   */
  function initScrollChromatic() {
    let lastScrollY = window.scrollY;
    let ticking = false;

    const handleScroll = () => {
      const scrollY = window.scrollY;
      const delta = Math.abs(scrollY - lastScrollY);
      lastScrollY = scrollY;

      // Trigger chromatic on fast scrolling
      if (delta > 50 && CONFIG.glitchLevel > 0) {
        document.querySelectorAll('[data-scroll-chromatic]').forEach(el => {
          applyChromatic(el, 200);
        });
      }

      ticking = false;
    };

    window.addEventListener('scroll', () => {
      if (!ticking) {
        requestAnimationFrame(handleScroll);
        ticking = true;
      }
    }, { passive: true });
  }

  // ============================================================================
  // Global Glitch Level
  // ============================================================================

  /**
   * Set global glitch intensity level
   * @param {number} level - 0=off, 1=subtle, 2=medium, 3=heavy
   */
  function setGlitchLevel(level) {
    CONFIG.glitchLevel = Math.max(0, Math.min(3, level));
    document.documentElement.setAttribute('data-glitch-level', CONFIG.glitchLevel);

    // Update scanlines based on level
    const scanlines = document.querySelector('.scanlines');
    if (scanlines) {
      const intensities = ['subtle', 'subtle', 'medium', 'heavy'];
      scanlines.setAttribute('data-intensity', intensities[CONFIG.glitchLevel]);
    }

    // Disable cursor trail at level 0
    if (CONFIG.glitchLevel === 0) {
      cursorTrail(false);
    }
  }

  // ============================================================================
  // Initialization
  // ============================================================================

  /**
   * Initialize all effects
   */
  function initEffects(options = {}) {
    if (state.initialized) {
      console.warn('PROMPT Effects already initialized');
      return;
    }

    // Merge options
    Object.assign(CONFIG, options);

    // Set initial glitch level
    setGlitchLevel(CONFIG.glitchLevel);

    // Initialize components
    createScanlines();
    initGlitchText();
    initChromaticHover();
    initVHSHover();
    initMemoryFade();
    initScrollChromatic();

    // Set up cursor trail
    if (CONFIG.cursorTrailEnabled) {
      document.addEventListener('mousemove', handleCursorMove, { passive: true });
    }

    // Schedule random errors if glitch level > 1
    if (CONFIG.glitchLevel > 1) {
      scheduleRandomErrors();
    }

    state.initialized = true;

    console.log('PROMPT Effects initialized at level', CONFIG.glitchLevel);

    return {
      triggerGlitch,
      setGlitchLevel,
      cursorTrail,
      flashError,
      applyChromatic,
      corruptData,
      applyVHSTracking,
      setTrailColor,
      toggleScanlines
    };
  }

  /**
   * Cleanup all effects
   */
  function destroyEffects() {
    // Disconnect observers
    state.observers.forEach(obs => obs.disconnect());
    state.observers = [];

    // Remove cursor trail listener
    document.removeEventListener('mousemove', handleCursorMove);

    // Clear particles
    state.cursorTrailParticles.forEach(p => p.remove());
    state.cursorTrailParticles = [];

    // Remove dynamic elements
    const scanlines = document.querySelector('.scanlines');
    if (scanlines) scanlines.remove();

    const errorFlash = document.querySelector('.error-flash');
    if (errorFlash) errorFlash.remove();

    state.initialized = false;
  }

  // ============================================================================
  // Export API
  // ============================================================================

  const PromptEffects = {
    init: initEffects,
    destroy: destroyEffects,
    triggerGlitch,
    setGlitchLevel,
    cursorTrail,
    flashError,
    applyChromatic,
    corruptData,
    applyVHSTracking,
    setTrailColor,
    toggleScanlines,
    createScanlines,
    getConfig: () => ({ ...CONFIG }),
    isInitialized: () => state.initialized
  };

  // Export to global scope
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = PromptEffects;
  } else {
    global.PromptEffects = PromptEffects;
  }

  // Auto-initialize on DOMContentLoaded if data attribute present
  if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
      if (document.body.hasAttribute('data-auto-effects')) {
        const level = parseInt(document.body.getAttribute('data-glitch-level') || '2', 10);
        initEffects({ glitchLevel: level });
      }
    });
  }

})(typeof window !== 'undefined' ? window : this);
