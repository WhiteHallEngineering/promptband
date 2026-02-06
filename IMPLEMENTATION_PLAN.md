# PROMPT Website Enhancement Plan

## Phase 1: Content Updates
1. **Rewrite Rock Ranger Article** - COMPLETED
   - Added Captain Rock Ranger as Star Fleet Captain on USS Silmaril
   - Former submariner, one of first to Terminus on Mars
   - Elon friendship and "powering the band" backstory
   - April 20th Terminus reunion with Elon

2. **Add Rock & Roll Magazine** - TODO (need cover image)
3. **Add Studio Lore** - TODO (Data Forge section - terminal commands added)

## Phase 2: Interactive Features
4. **Countdown Timer** - COMPLETED
   - Live countdown to April 20, 2030 Mars show
   - Displays days, hours, minutes, seconds
   - Signal delay note for immersion

5. **Quote Rotator** - COMPLETED
   - 6 rotating Jax Synthetic quotes in hero section
   - Auto-rotates every 6 seconds
   - Smooth fade transitions

6. **Terminal Easter Eggs** - COMPLETED
   - Secret commands: rock, silmaril, dataforge, terminus, mars, jax, 420, hallucinate
   - Updated Elon quote with Rock Ranger lore
   - Added hint to help menu about hidden commands

## Phase 3: Visual Features
7. **Photo Gallery** - COMPLETED
   - New Gallery section with all 5 band member photos
   - Hover overlays with names and roles
   - Responsive grid layout
   - Images copied to website/images/gallery/ with proper naming

8. **Epic Boot Sequence** - COMPLETED
   - ASCII art logo with glow effects
   - Detailed boot messages including band members, Data Forge, Mars relay
   - Progress bar
   - Skip functionality (any key or click)
   - Test triggers: `?boot=1` URL param or `Ctrl+Shift+B`
   - localStorage check for first visit

## Phase 4: Growth Features
9. **Streaming Links** - COMPLETED
   - Spotify, Apple Music, YouTube Music, Bandcamp buttons
   - Added below track list in Music section
   - Hover effects with platform-specific colors

10. **Newsletter Signup** - COMPLETED
    - Email capture form in Contact section
    - Styled with gradient background
    - Loading state and success feedback
    - "No spam. Only signal." note

## Phase 5: Polish
11. **Merch Section** - COMPLETED
    - "Coming Soon" teaser section
    - Hologram-style floating item mockups
    - "Physical Artifacts Loading..." messaging
    - "Notify Me" button

---

## All Features Implemented!

### To Deploy:
```bash
ssh hallmar3@162.241.225.117 -i ~/.ssh/bluehost_promptband
cd public_html
# Upload files
```

### Test Boot Sequence:
- Add `?boot=1` to URL
- Or press `Ctrl+Shift+B`

### Test Terminal:
- Press backtick (`) to open
- Try: `rock`, `mars`, `420`, `hallucinate`
