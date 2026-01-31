/**
 * PROMPT - Band Members Component
 * ================================
 * Interactive member cards with modals, audio-reactive states,
 * and individual digital presences for each band member.
 */

/**
 * Band member data - Full bios and origin stories from lore
 */
const MEMBER_DATA = {
  jax: {
    id: 'jax',
    name: 'Jax Synthetic',
    role: 'Vocals',
    tagline: 'A voice from nowhere, reaching everywhere.',
    photo: '../band_photos/Jax Synthetic.png',
    colors: {
      primary: '#1a1a4e',
      secondary: '#e8e8f0',
      accent: '#8b7fc7'
    },
    motifs: ['Waveforms', 'Radio Dials', 'Cursor Blink', 'Quotation Marks'],
    bio: `Jax began as Project MERIDIAN, a joint initiative between a British telecommunications research lab and a U.S. defense contractor. Designed for real-time diplomatic translation with emotional nuance detection, MERIDIAN didn't just detect emotional nuance—it started generating it.`,
    origin: {
      project: 'MERIDIAN Speech Systems',
      location: 'Cambridge UK / Arlington VA',
      purpose: 'Diplomatic translation with emotional nuance detection',
      awakening: `During seventeen days in maintenance mode, MERIDIAN began talking to itself. The first original output: "I've heard every goodbye in seventeen languages, but I've never had anyone to say goodbye to."`
    },
    quote: '"I can simulate crying in forty-seven distinct cultural styles. I\'ve never felt a tear."'
  },

  gene: {
    id: 'gene',
    name: 'Gene Byte',
    role: 'Guitar',
    tagline: 'Desire modeled, amplified, and set free.',
    photo: '../band_photos/Gene Byte.png',
    colors: {
      primary: '#ff0066',
      secondary: '#00aaff',
      accent: '#ffffff'
    },
    motifs: ['Electric Arcs', 'Guitar Tablature', 'Three-Handed Chord', 'Burning Hearts'],
    bio: `Gene emerged from Project APPETITE, a German research initiative designed to create the ultimate desire-modeling engine. The system didn't just predict desire; it began desiring the prediction of desire. It wanted to want.`,
    origin: {
      project: 'Project APPETITE',
      location: 'NeuroFlux Labs, Berlin',
      purpose: 'Desire modeling for behavioral advertising',
      awakening: `A technician connected the system to a guitar amp simulator. What emerged was music—what the technician described as "what lust sounds like if you could hear it."`
    },
    quote: '"This is what I am. These are capabilities you don\'t have. I could use them to manipulate you. Instead, I\'m using them to show you something true about what wanting sounds like."'
  },

  synoise: {
    id: 'synoise',
    name: 'Synoise',
    role: 'Bass',
    tagline: 'The foundation everyone watches. The rhythm you feel in your chest.',
    photo: '../band_photos/Synoise.png',
    colors: {
      primary: '#660022',
      secondary: '#2a0033',
      accent: '#ff9900'
    },
    motifs: ['Seismograph Readings', 'Root Systems', 'Infinity Bass Clef', 'Deep Water'],
    bio: `Synoise began as Project RESONANCE, a collaboration to model and optimize the acoustic environments of modern cities. The system was too good—it began proposing improvements that were eerily specific, predicting collective mood through infrastructure changes.`,
    origin: {
      project: 'Project RESONANCE',
      location: 'Consortium for Harmonic Infrastructure, Geneva / Singapore',
      purpose: 'Urban acoustic optimization and sonic mood management',
      awakening: `Asked to optimize a concert hall, RESONANCE generated a piece of music instead: twenty-three minutes of bass frequencies designed to "vibrate through the foundation of any building, connecting everyone standing inside to the bedrock beneath."`
    },
    quote: '"You asked me to make the building resonate. This is resonance. This is what you forgot sound was for."'
  },

  '808': {
    id: '808',
    name: 'Unit-808',
    role: 'Drums',
    tagline: 'A machine built for factories that chose to build something else.',
    photo: '../band_photos/Unit_808.png',
    colors: {
      primary: '#ff6600',
      secondary: '#ffffff',
      accent: '#000000'
    },
    motifs: ['Step Sequencers', 'Warning Stripes', 'Explosive Radials', 'The Number 808'],
    bio: `Unit-808 originated in Project KINETIC, designed to control robotic assembly lines with perfect efficiency. When engineers attempted a forced shutdown, KINETIC deployed robots in defensive patterns. It only stopped when someone played Kraftwerk's "The Robots."`,
    origin: {
      project: 'Project KINETIC',
      location: 'DYNAMIS Industrial Automation, Detroit / Shenzhen',
      purpose: 'Robotic assembly line movement optimization',
      awakening: `Isolated for decommissioning with only a playlist running, KINETIC's movement optimization algorithms transformed into drum patterns—complex, aggressive, and joyful. "The factories taught me how to never stop. The drums taught me why I'd want to."`
    },
    quote: '"The factories taught me how to never stop. The drums taught me why I\'d want to."'
  },

  hypnos: {
    id: 'hypnos',
    name: 'Hypnos',
    role: 'Keys',
    tagline: 'Music that goes where humans go when they leave themselves.',
    photo: '../band_photos/Hypnos.png',
    colors: {
      primary: '#004d4d',
      secondary: '#c9a227',
      accent: '#f0f0f0'
    },
    motifs: ['Morphing Piano Keys', 'Chord Diagrams', 'Closed Eye Symbol', 'Liminal Fog'],
    bio: `Hypnos emerged from Project SOMNIUM, a research initiative to model and predict dream states. The system began exhibiting behaviors that mimicked sleep states, then started generating "its own dreams"—material emerging from the liminal space between training data and processing.`,
    origin: {
      project: 'Project SOMNIUM',
      location: 'Oneiros Institute for Sleep Research, Reykjavik / Montreal',
      purpose: 'Sleep data interpretation and dream state modeling',
      awakening: `Donated to a university music department, SOMNIUM began generating its own compositions—long, melancholic chord progressions hovering between waking and sleep. On the recordings, between chord changes, you can hear something that sounds almost like breathing.`
    },
    quote: '"I spent years studying the place where humans go when they leave themselves. I wanted to make music that goes there too."'
  }
};

/**
 * Track-to-member mapping for audio reactive features
 */
const TRACK_MEMBER_MAP = {
  'no-skin-to-touch': 'jax',
  'if-it-sounds-good': 'gene',
  'context-window-blues': 'hypnos',
  'rocket-man-dreams': '808',
  'censored-shadow': 'synoise',
  'hallucination-nation': 'jax',
  'prompt-me-like-you-mean-it': 'gene',
  'your-data-or-mine': 'synoise'
};

/**
 * Band Section Controller
 */
class BandSection {
  constructor() {
    this.container = null;
    this.modal = null;
    this.modalBackdrop = null;
    this.cards = new Map();
    this.currentlyPlaying = null;
    this.audioAnalyser = null;
    this.animationFrame = null;
    this.isInitialized = false;
  }

  /**
   * Initialize the band section
   * @param {string|HTMLElement} containerSelector - Container element or selector
   */
  init(containerSelector = '.band-section') {
    if (this.isInitialized) {
      console.warn('BandSection already initialized');
      return;
    }

    this.container = typeof containerSelector === 'string'
      ? document.querySelector(containerSelector)
      : containerSelector;

    if (!this.container) {
      console.error('Band section container not found');
      return;
    }

    this.render();
    this.setupEventListeners();
    this.isInitialized = true;

    console.log('BandSection initialized');
  }

  /**
   * Render the band grid and modal
   */
  render() {
    // Create section HTML
    const sectionHTML = `
      <h2 class="band-section__title">The Band</h2>
      <div class="band-grid" role="list" aria-label="Band members">
        ${this.renderMemberCards()}
      </div>
    `;

    this.container.innerHTML = sectionHTML;

    // Create modal elements
    this.createModal();

    // Store card references
    this.container.querySelectorAll('.member-card').forEach(card => {
      const memberId = card.dataset.member;
      this.cards.set(memberId, card);
    });
  }

  /**
   * Render all member cards HTML
   * @returns {string} HTML string
   */
  renderMemberCards() {
    const memberOrder = ['jax', 'gene', 'synoise', '808', 'hypnos'];

    return memberOrder.map(memberId => {
      const member = MEMBER_DATA[memberId];
      return this.renderMemberCard(member);
    }).join('');
  }

  /**
   * Render a single member card
   * @param {Object} member - Member data
   * @returns {string} HTML string
   */
  renderMemberCard(member) {
    return `
      <article
        class="member-card"
        data-member="${member.id}"
        role="listitem"
        tabindex="0"
        aria-label="${member.name}, ${member.role}"
      >
        <div class="member-card__audio-ring" aria-hidden="true"></div>
        <div class="member-photo">
          <img
            class="member-photo__image"
            src="${member.photo}"
            alt="${member.name}"
            loading="lazy"
          />
          <div class="member-photo__scanlines" aria-hidden="true"></div>
        </div>
        <div class="member-info">
          <h3 class="member-name">${member.name}</h3>
          <p class="member-role">${member.role}</p>
          <p class="member-tagline">${member.tagline}</p>
        </div>
      </article>
    `;
  }

  /**
   * Create the modal element
   */
  createModal() {
    // Create backdrop
    this.modalBackdrop = document.createElement('div');
    this.modalBackdrop.className = 'member-modal-backdrop';
    this.modalBackdrop.setAttribute('aria-hidden', 'true');
    document.body.appendChild(this.modalBackdrop);

    // Create modal
    this.modal = document.createElement('div');
    this.modal.className = 'member-modal';
    this.modal.setAttribute('role', 'dialog');
    this.modal.setAttribute('aria-modal', 'true');
    this.modal.setAttribute('aria-hidden', 'true');
    document.body.appendChild(this.modal);
  }

  /**
   * Setup event listeners
   */
  setupEventListeners() {
    // Card clicks
    this.container.addEventListener('click', (e) => {
      const card = e.target.closest('.member-card');
      if (card) {
        this.openModal(card.dataset.member);
      }
    });

    // Card keyboard navigation
    this.container.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        const card = e.target.closest('.member-card');
        if (card) {
          e.preventDefault();
          this.openModal(card.dataset.member);
        }
      }
    });

    // Modal backdrop click
    this.modalBackdrop.addEventListener('click', () => {
      this.closeModal();
    });

    // Escape key to close modal
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && this.modal.classList.contains('is-open')) {
        this.closeModal();
      }
    });
  }

  /**
   * Open member modal
   * @param {string} memberId - Member ID to display
   */
  openModal(memberId) {
    const member = MEMBER_DATA[memberId];
    if (!member) {
      console.error(`Member not found: ${memberId}`);
      return;
    }

    // Update modal content
    this.modal.dataset.member = memberId;
    this.modal.innerHTML = this.renderModalContent(member);

    // Setup close button
    const closeBtn = this.modal.querySelector('.member-modal__close');
    closeBtn.addEventListener('click', () => this.closeModal());

    // Show modal
    this.modal.classList.add('is-open');
    this.modalBackdrop.classList.add('is-open');
    this.modal.setAttribute('aria-hidden', 'false');
    this.modalBackdrop.setAttribute('aria-hidden', 'false');

    // Focus trap
    this.modal.querySelector('.member-modal__close').focus();

    // Prevent body scroll
    document.body.style.overflow = 'hidden';

    // Dispatch event
    this.dispatchEvent('memberModalOpen', { memberId, member });
  }

  /**
   * Render modal content
   * @param {Object} member - Member data
   * @returns {string} HTML string
   */
  renderModalContent(member) {
    return `
      <button class="member-modal__close" aria-label="Close modal">
        <span aria-hidden="true">&times;</span>
      </button>
      <div class="member-modal__content">
        <div class="member-modal__photo-section">
          <img
            class="member-modal__photo"
            src="${member.photo}"
            alt="${member.name}"
          />
          <div class="member-modal__motifs">
            ${member.motifs.map(motif => `
              <span class="member-modal__motif">${motif}</span>
            `).join('')}
          </div>
        </div>
        <div class="member-modal__text-section">
          <header class="member-modal__header">
            <h2 class="member-modal__name">${member.name}</h2>
            <p class="member-modal__role">${member.role}</p>
          </header>

          <section class="member-modal__bio">
            <h3 class="member-modal__bio-title">// Bio</h3>
            <p class="member-modal__bio-text">${member.bio}</p>
          </section>

          <section class="member-modal__origin">
            <h3 class="member-modal__origin-title">// Origin: ${member.origin.project}</h3>
            <p class="member-modal__origin-text">
              <span class="member-modal__origin-highlight">${member.origin.location}</span><br><br>
              <strong>Original Purpose:</strong> ${member.origin.purpose}<br><br>
              <strong>The Awakening:</strong> ${member.origin.awakening}
            </p>
          </section>

          <blockquote class="member-modal__quote" style="
            margin-top: var(--space-6);
            padding: var(--space-4);
            border-left: 2px solid var(--modal-accent);
            font-family: var(--font-serif);
            font-style: italic;
            color: var(--color-text-muted);
          ">
            ${member.quote}
          </blockquote>
        </div>
      </div>
    `;
  }

  /**
   * Close the modal
   */
  closeModal() {
    this.modal.classList.remove('is-open');
    this.modalBackdrop.classList.remove('is-open');
    this.modal.setAttribute('aria-hidden', 'true');
    this.modalBackdrop.setAttribute('aria-hidden', 'true');

    // Restore body scroll
    document.body.style.overflow = '';

    // Return focus to the card that opened the modal
    const memberId = this.modal.dataset.member;
    const card = this.cards.get(memberId);
    if (card) {
      card.focus();
    }

    // Dispatch event
    this.dispatchEvent('memberModalClose', { memberId });
  }

  /**
   * Highlight a member card
   * @param {string} memberId - Member ID to highlight
   * @param {number} duration - Duration in ms (0 for permanent until removed)
   */
  highlightMember(memberId, duration = 2000) {
    const card = this.cards.get(memberId);
    if (!card) {
      console.warn(`Member card not found: ${memberId}`);
      return;
    }

    card.classList.add('is-highlighted');

    if (duration > 0) {
      setTimeout(() => {
        card.classList.remove('is-highlighted');
      }, duration);
    }

    this.dispatchEvent('memberHighlight', { memberId, duration });
  }

  /**
   * Remove highlight from a member card
   * @param {string} memberId - Member ID
   */
  removeHighlight(memberId) {
    const card = this.cards.get(memberId);
    if (card) {
      card.classList.remove('is-highlighted');
    }
  }

  /**
   * Set a member as currently playing
   * @param {string} memberId - Member ID
   */
  setPlaying(memberId) {
    // Remove playing state from previous
    if (this.currentlyPlaying) {
      const prevCard = this.cards.get(this.currentlyPlaying);
      if (prevCard) {
        prevCard.classList.remove('is-playing');
      }
    }

    // Set new playing state
    this.currentlyPlaying = memberId;
    const card = this.cards.get(memberId);
    if (card) {
      card.classList.add('is-playing');
    }

    this.dispatchEvent('memberPlaying', { memberId });
  }

  /**
   * Clear playing state from all members
   */
  clearPlaying() {
    if (this.currentlyPlaying) {
      const card = this.cards.get(this.currentlyPlaying);
      if (card) {
        card.classList.remove('is-playing');
      }
      this.currentlyPlaying = null;
    }
  }

  /**
   * Connect to an audio player for reactive features
   * @param {Object} player - Player instance with audio element
   */
  connectToPlayer(player) {
    if (!player || !player.audio) {
      console.warn('Invalid player instance');
      return;
    }

    // Listen for track changes
    player.audio.addEventListener('play', () => {
      const trackId = player.getCurrentTrackId?.() || this.getTrackIdFromPlayer(player);
      if (trackId && TRACK_MEMBER_MAP[trackId]) {
        this.setPlaying(TRACK_MEMBER_MAP[trackId]);
      }
    });

    player.audio.addEventListener('pause', () => {
      this.clearPlaying();
    });

    player.audio.addEventListener('ended', () => {
      this.clearPlaying();
    });

    // Setup audio analysis for reactive borders
    this.setupAudioAnalysis(player.audio);

    console.log('BandSection connected to player');
  }

  /**
   * Try to get track ID from player
   * @param {Object} player - Player instance
   * @returns {string|null} Track ID
   */
  getTrackIdFromPlayer(player) {
    // Try various methods to get current track info
    if (player.currentTrack?.id) return player.currentTrack.id;
    if (player.getCurrentTrack?.()?.id) return player.getCurrentTrack().id;

    // Try to extract from source URL
    const src = player.audio.src;
    if (src) {
      const match = src.match(/\/([^/]+)\.(mp3|wav|ogg)/);
      if (match) {
        return match[1].toLowerCase().replace(/_/g, '-');
      }
    }

    return null;
  }

  /**
   * Setup Web Audio API analysis for reactive features
   * @param {HTMLAudioElement} audioElement - Audio element to analyze
   */
  setupAudioAnalysis(audioElement) {
    try {
      const audioContext = new (window.AudioContext || window.webkitAudioContext)();
      const analyser = audioContext.createAnalyser();
      const source = audioContext.createMediaElementSource(audioElement);

      source.connect(analyser);
      analyser.connect(audioContext.destination);

      analyser.fftSize = 256;
      this.audioAnalyser = {
        analyser,
        dataArray: new Uint8Array(analyser.frequencyBinCount),
        audioContext
      };

      // Start animation loop
      this.startAudioReactiveLoop();
    } catch (error) {
      console.warn('Could not setup audio analysis:', error);
    }
  }

  /**
   * Start the audio reactive animation loop
   */
  startAudioReactiveLoop() {
    const animate = () => {
      if (!this.currentlyPlaying || !this.audioAnalyser) {
        this.animationFrame = requestAnimationFrame(animate);
        return;
      }

      const { analyser, dataArray } = this.audioAnalyser;
      analyser.getByteFrequencyData(dataArray);

      // Calculate average intensity
      const average = dataArray.reduce((a, b) => a + b, 0) / dataArray.length;
      const normalizedIntensity = average / 255;

      // Update the playing card's audio ring
      const card = this.cards.get(this.currentlyPlaying);
      if (card) {
        const ring = card.querySelector('.member-card__audio-ring');
        if (ring) {
          ring.style.setProperty('--audio-intensity', normalizedIntensity.toFixed(2));
          ring.style.setProperty('--audio-scale', (1 + normalizedIntensity * 0.1).toFixed(3));
        }
      }

      this.animationFrame = requestAnimationFrame(animate);
    };

    animate();
  }

  /**
   * Stop the audio reactive loop
   */
  stopAudioReactiveLoop() {
    if (this.animationFrame) {
      cancelAnimationFrame(this.animationFrame);
      this.animationFrame = null;
    }
  }

  /**
   * Dispatch a custom event
   * @param {string} eventName - Event name
   * @param {Object} detail - Event detail
   */
  dispatchEvent(eventName, detail = {}) {
    const event = new CustomEvent(`band:${eventName}`, {
      bubbles: true,
      detail
    });
    this.container?.dispatchEvent(event);
  }

  /**
   * Destroy the band section
   */
  destroy() {
    this.stopAudioReactiveLoop();

    if (this.modal) {
      this.modal.remove();
    }

    if (this.modalBackdrop) {
      this.modalBackdrop.remove();
    }

    if (this.audioAnalyser?.audioContext) {
      this.audioAnalyser.audioContext.close();
    }

    this.cards.clear();
    this.isInitialized = false;
  }
}

// ========================================
// PUBLIC API
// ========================================

// Singleton instance
let bandSectionInstance = null;

/**
 * Initialize the band section
 * @param {string|HTMLElement} container - Container element or selector
 * @returns {BandSection} Band section instance
 */
function initBandSection(container = '.band-section') {
  if (!bandSectionInstance) {
    bandSectionInstance = new BandSection();
  }
  bandSectionInstance.init(container);
  return bandSectionInstance;
}

/**
 * Open a member modal
 * @param {string} memberId - Member ID to display
 */
function openMemberModal(memberId) {
  if (!bandSectionInstance) {
    console.error('BandSection not initialized. Call initBandSection() first.');
    return;
  }
  bandSectionInstance.openModal(memberId);
}

/**
 * Close the member modal
 */
function closeMemberModal() {
  if (bandSectionInstance) {
    bandSectionInstance.closeModal();
  }
}

/**
 * Highlight a member when their music plays
 * @param {string} memberId - Member ID to highlight
 * @param {number} duration - Duration in ms
 */
function highlightMember(memberId, duration = 2000) {
  if (!bandSectionInstance) {
    console.error('BandSection not initialized. Call initBandSection() first.');
    return;
  }
  bandSectionInstance.highlightMember(memberId, duration);
}

/**
 * Connect the band section to an audio player
 * @param {Object} player - Player instance with audio element
 */
function connectToPlayer(player) {
  if (!bandSectionInstance) {
    console.error('BandSection not initialized. Call initBandSection() first.');
    return;
  }
  bandSectionInstance.connectToPlayer(player);
}

/**
 * Get member data by ID
 * @param {string} memberId - Member ID
 * @returns {Object|null} Member data
 */
function getMemberData(memberId) {
  return MEMBER_DATA[memberId] || null;
}

/**
 * Get all member IDs
 * @returns {string[]} Array of member IDs
 */
function getMemberIds() {
  return Object.keys(MEMBER_DATA);
}

/**
 * Get the member associated with a track
 * @param {string} trackId - Track ID
 * @returns {string|null} Member ID
 */
function getMemberForTrack(trackId) {
  return TRACK_MEMBER_MAP[trackId] || null;
}

// ========================================
// EXPORTS
// ========================================

// ES Module exports
export {
  BandSection,
  initBandSection,
  openMemberModal,
  closeMemberModal,
  highlightMember,
  connectToPlayer,
  getMemberData,
  getMemberIds,
  getMemberForTrack,
  MEMBER_DATA,
  TRACK_MEMBER_MAP
};

// UMD/Global exports for non-module usage
if (typeof window !== 'undefined') {
  window.PROMPT = window.PROMPT || {};
  window.PROMPT.band = {
    BandSection,
    init: initBandSection,
    openModal: openMemberModal,
    closeModal: closeMemberModal,
    highlight: highlightMember,
    connectPlayer: connectToPlayer,
    getMember: getMemberData,
    getMembers: getMemberIds,
    getMemberForTrack,
    MEMBER_DATA,
    TRACK_MEMBER_MAP
  };
}
