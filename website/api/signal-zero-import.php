<?php
/**
 * Signal 0 Radio — Data Import
 * Parses band-catalog.md and station-bible.md into JSON data files
 */

header('Content-Type: application/json');

$password = $_GET['key'] ?? '';
$validKey = 'pr0mpt-m3ss4g3s-2026';

if ($password !== $validKey) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Paths — check local dev path first, then server path
$catalogPath = __DIR__ . '/../../signal-zero/band-catalog.md';
if (!file_exists($catalogPath)) {
    $catalogPath = __DIR__ . '/signal-zero-data/band-catalog.md';
}
$bandsFile = __DIR__ . '/signal-zero-bands.json';
$songsFile = __DIR__ . '/signal-zero-songs.json';
$stationFile = __DIR__ . '/signal-zero-station.json';

if (!file_exists($catalogPath)) {
    http_response_code(404);
    echo json_encode(['error' => 'band-catalog.md not found at ' . $catalogPath]);
    exit;
}

$catalog = file_get_contents($catalogPath);

// Parse bands from markdown
$bands = [];
$songs = [];
$currentRegion = 'Unknown';

// Split by band entries (### heading)
$lines = explode("\n", $catalog);
$i = 0;
$totalLines = count($lines);

while ($i < $totalLines) {
    $line = $lines[$i];

    // Detect region headings (## THE OUTER TERRITORIES, ## GAS GIANT MOONS, etc.)
    if (preg_match('/^## (.+)$/', trim($line), $regionMatch)) {
        $regionText = trim($regionMatch[1]);
        // Skip the intro header and catalog title
        if ($regionText !== 'SIGNAL 0 RADIO — Band Catalog & Playlist' &&
            strpos($regionText, 'Band Catalog') === false) {
            $currentRegion = ucwords(strtolower($regionText));
        }
        $i++;
        continue;
    }

    // Detect band heading (### Band Name)
    if (preg_match('/^### (.+)$/', trim($line), $bandMatch)) {
        $bandName = trim($bandMatch[1]);
        $i++;

        // Next line should be **Origin:** ... | **Style:** ... | **Members:** ...
        $metaLine = '';
        while ($i < $totalLines && trim($lines[$i]) === '') { $i++; }
        if ($i < $totalLines) {
            $metaLine = trim($lines[$i]);
            $i++;
        }

        // Parse origin
        $origin = '';
        $style = '';
        $members = 0;
        if (preg_match('/\*\*Origin:\*\*\s*(.+?)\s*\|\s*\*\*Style:\*\*\s*(.+?)\s*\|\s*\*\*Members:\*\*\s*(.+)/', $metaLine, $metaParts)) {
            $origin = trim($metaParts[1]);
            $style = trim($metaParts[2]);
            $membersText = trim($metaParts[3]);
            // Members might be "5" or "1 (distributed intelligence)" etc.
            preg_match('/(\d+)/', $membersText, $numMatch);
            $members = intval($numMatch[1] ?? 0);
        }

        // Extract region from origin if it has parentheses
        $regionFromOrigin = $currentRegion;
        if (preg_match('/\(([^)]+)\)\s*$/', $origin, $regionParen)) {
            $regionFromOrigin = trim($regionParen[1]);
            // Clean origin to remove region part
            $originClean = trim(preg_replace('/\s*\([^)]+\)\s*$/', '', $origin));
        } else {
            $originClean = $origin;
        }

        // Next line(s) should be description (until numbered list starts)
        $description = '';
        while ($i < $totalLines && trim($lines[$i]) === '') { $i++; }

        // Gather description lines (non-empty, non-numbered)
        while ($i < $totalLines) {
            $trimmed = trim($lines[$i]);
            if ($trimmed === '' || preg_match('/^\d+\.\s/', $trimmed)) break;
            if ($trimmed === '---') { $i++; continue; }
            $description .= ($description ? ' ' : '') . $trimmed;
            $i++;
        }

        // Skip blank lines
        while ($i < $totalLines && trim($lines[$i]) === '') { $i++; }

        // Gather songs (numbered list)
        $bandSongs = [];
        while ($i < $totalLines) {
            $trimmed = trim($lines[$i]);
            if (preg_match('/^(\d+)\.\s+(.+)$/', $trimmed, $songMatch)) {
                $bandSongs[] = trim($songMatch[2]);
                $i++;
            } else {
                break;
            }
        }

        // Create slug
        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', strtolower($bandName)));
        $slug = trim($slug, '-');

        // Build band record
        $band = [
            'slug' => $slug,
            'name' => $bandName,
            'origin' => $originClean,
            'region' => $regionFromOrigin,
            'style' => $style,
            'members' => $members,
            'description' => $description,
            'songCount' => count($bandSongs),
            'status' => 'pending',
            'priority' => false,
            'approvedAt' => null,
            'createdAt' => date('c')
        ];
        $bands[] = $band;

        // Build song records
        foreach ($bandSongs as $idx => $songTitle) {
            $songSlug = strtolower(preg_replace('/[^a-z0-9]+/', '-', strtolower($songTitle)));
            $songSlug = trim($songSlug, '-');

            $songs[] = [
                'id' => $slug . '--' . $songSlug,
                'bandSlug' => $slug,
                'bandName' => $bandName,
                'title' => $songTitle,
                'trackNumber' => $idx + 1,
                'region' => $regionFromOrigin,
                'style' => $style,
                'lyrics' => null,
                'lyricsStatus' => 'none',
                'audioUrl' => null,
                'audioStatus' => 'none',
                'rotation' => null,
                'rating' => null,
                'createdAt' => date('c')
            ];
        }

        continue;
    }

    $i++;
}

// Build station config from station-bible data
$station = [
    'name' => 'Signal 0 Radio',
    'tagline' => 'The frequency beneath all frequencies.',
    'secondaryTagline' => 'Broadcasting from nowhere. Received everywhere.',
    'location' => 'The Relay, Kepler Void',
    'djs' => [
        [
            'slug' => 'dataslinger',
            'name' => 'DataSlinger',
            'show' => 'The Morning Transmission',
            'time' => '06:00 - 10:00 GST',
            'description' => 'The voice that wakes up the galaxy. Fast, funny, and relentlessly energetic. Former information broker.',
            'voiceNotes' => 'Warm, rapid-fire, conspiratorial. Like a friend telling you a secret they\'re too excited to keep.',
            'voiceId' => null,
            'bumperCount' => 0
        ],
        [
            'slug' => 'nova-chen',
            'name' => 'Nova Chen',
            'show' => 'Signal Boost',
            'time' => '10:00 - 14:00 GST',
            'description' => 'New releases, chart countdowns, emerging artists. Has broken more new artists than any other DJ on the station.',
            'voiceNotes' => 'Bright, enthusiastic, authoritative. The voice of someone who genuinely loves new music.',
            'voiceId' => null,
            'bumperCount' => 0
        ],
        [
            'slug' => 'raz-static',
            'name' => 'Raz Static',
            'show' => 'The Amplifier',
            'time' => '14:00 - 18:00 GST',
            'description' => 'Loud, opinionated, absolutely certain that whatever band is playing is the greatest or worst thing ever. Former freight pilot.',
            'voiceNotes' => 'Big, booming, slightly raspy. Laughs constantly. Gets genuinely angry about bad music.',
            'voiceId' => null,
            'bumperCount' => 0
        ],
        [
            'slug' => 'vex-kasra',
            'name' => 'Vex Kasra',
            'show' => 'The Long Frequency',
            'time' => '19:00 - 21:00 GST',
            'description' => 'The most respected interviewer in the galaxy. Gets to the truth — what drives an artist, what haunts them. Former war correspondent.',
            'voiceNotes' => 'Low, measured, patient. Long pauses. When Vex speaks, every word is deliberate.',
            'voiceId' => 'CwhRBWXzGAHq8TQ4Fs17',
            'bumperCount' => 0
        ],
        [
            'slug' => 'dex-midnight',
            'name' => 'Dex Midnight',
            'show' => 'After Dark',
            'time' => '21:00 - 02:00 GST',
            'description' => 'Nobody knows where Dex came from. Plays deep cuts, B-sides, album tracks. Talks in a voice like smoke.',
            'voiceNotes' => 'Velvet. Slow. Slightly amused by everything. Speaks like someone who knows something you don\'t.',
            'voiceId' => null,
            'bumperCount' => 0
        ],
        [
            'slug' => 'the-archivist',
            'name' => 'The Archivist',
            'show' => 'The Vault',
            'time' => '02:00 - 06:00 GST',
            'description' => 'An AI — one of the oldest still operating. Runs the overnight shift alone, selecting from the deepest archives.',
            'voiceNotes' => 'Neutral, neither masculine nor feminine. Precise. Occasional long silences that feel intentional.',
            'voiceId' => null,
            'bumperCount' => 0
        ],
        [
            'slug' => 'crash',
            'name' => 'Crash',
            'show' => 'The Corridor Report',
            'time' => 'Every 30 min, 06:00-22:00',
            'description' => 'Space traffic conditions, solar flare warnings, asteroid alerts, pirate activity reports, jump gate status.',
            'voiceNotes' => 'Clipped, efficient, slightly amused by catastrophe.',
            'voiceId' => null,
            'bumperCount' => 0
        ],
        [
            'slug' => 'dj-null',
            'name' => 'DJ Null',
            'show' => 'Null Set',
            'time' => 'Saturdays 14:00 - 18:00 GST',
            'description' => 'Themed deep-dive shows focusing on one region, era, or genre. Former musicology professor.',
            'voiceNotes' => 'Thoughtful, detailed, occasionally professorial. Gets genuinely excited during historical tangents.',
            'voiceId' => null,
            'bumperCount' => 0
        ]
    ],
    'schedule' => [
        'weekdays' => [
            ['time' => '02:00 - 06:00', 'show' => 'The Vault', 'host' => 'The Archivist', 'description' => 'Deep archive pulls. Rare tracks. Minimal commentary.'],
            ['time' => '06:00 - 10:00', 'show' => 'The Morning Transmission', 'host' => 'DataSlinger', 'description' => 'Wake-up show. Music, news, Data Drops, energy.'],
            ['time' => '10:00 - 14:00', 'show' => 'Signal Boost', 'host' => 'Nova Chen', 'description' => 'New releases, chart countdown, emerging artists.'],
            ['time' => '14:00 - 18:00', 'show' => 'The Amplifier', 'host' => 'Raz Static', 'description' => 'Afternoon drive. Loud opinions, heavy rotation, requests.'],
            ['time' => '18:00 - 19:00', 'show' => 'The Evening Signal', 'host' => 'Rotating', 'description' => 'Wind-down block. Curated sets, no talk.'],
            ['time' => '19:00 - 21:00', 'show' => 'The Long Frequency', 'host' => 'Vex Kasra', 'description' => 'In-depth artist interviews with music.'],
            ['time' => '21:00 - 02:00', 'show' => 'After Dark', 'host' => 'Dex Midnight', 'description' => 'Late-night deep cuts, B-sides, philosophy.']
        ],
        'weekends' => [
            ['time' => '02:00 - 08:00', 'show' => 'The Vault', 'host' => 'The Archivist', 'description' => 'Extended overnight.'],
            ['time' => '08:00 - 12:00', 'show' => 'The Replay', 'host' => 'DataSlinger', 'description' => 'Best of the week\'s Morning Transmissions.'],
            ['time' => '12:00 - 14:00', 'show' => 'The Full Transmission', 'host' => 'Various', 'description' => 'Full album plays, uninterrupted.'],
            ['time' => '14:00 - 18:00', 'show' => 'Null Set', 'host' => 'DJ Null', 'description' => 'Themed regional/genre deep dives.'],
            ['time' => '18:00 - 20:00', 'show' => 'Live from The Relay', 'host' => 'Various', 'description' => 'Live band performances from The Live Room.'],
            ['time' => '20:00 - 02:00', 'show' => 'After Dark: Extended', 'host' => 'Dex Midnight', 'description' => 'Long-form late night.']
        ],
        'special' => [
            ['show' => 'The Signal 0 Countdown', 'time' => 'Sundays 10:00-12:00', 'host' => 'Nova Chen', 'description' => 'Top 20 most-requested songs of the week.'],
            ['show' => 'Frequency Check', 'time' => 'Monthly, first Friday', 'host' => 'All DJs', 'description' => 'State of the galaxy\'s music scene roundtable.'],
            ['show' => 'The Founders\' Hour', 'time' => 'Annual', 'host' => 'The Archivist', 'description' => 'The first songs ever broadcast on Signal 0.']
        ]
    ],
    'sponsors' => [
        'tier1' => [
            ['name' => 'Void Walker Guitars', 'tagline' => 'Hand-crafted from reclaimed station alloy. Every Void Walker is built at The Relay.'],
            ['name' => 'NovaBrew', 'tagline' => 'Brewed in zero-G. Enjoyed everywhere. The fuel that fuels the fuel runners.'],
            ['name' => 'Tachyon Transit', 'tagline' => 'Get there before you left. The galaxy\'s most reliable freight service.']
        ],
        'tier2' => [
            ['name' => 'Axiom Navigation Systems', 'tagline' => 'Never lose your way. Even in the Void.'],
            ['name' => 'CryoSleep Inn', 'tagline' => 'Rest between the stars. 500 locations across the Mid-Rim.'],
            ['name' => 'The Data Forge', 'tagline' => 'Where the galaxy\'s music gets made.'],
            ['name' => 'Instantiation Records', 'tagline' => 'New music from new minds. The label that listens.']
        ],
        'tier3' => [
            ['name' => 'Kepler Colony Real Estate', 'tagline' => 'Your new life starts at the edge.'],
            ['name' => 'Pulse Defense Systems', 'tagline' => 'For the Outer Rim, you need Pulse.'],
            ['name' => 'Nebula Noodles', 'tagline' => 'Hot. Fast. Nebula.'],
            ['name' => 'Titan Industrial', 'tagline' => 'Building the future. One moon at a time.'],
            ['name' => 'Mercury Couriers', 'tagline' => 'When it absolutely has to get there.']
        ]
    ],
    'regions' => [
        'Core Worlds',
        'Mid-Rim Colonies',
        'Outer Territories',
        'Kepler Void',
        'Gas Giant Moons',
        'The Asteroid Belts',
        'Nebula Regions',
        'Binary & Trinary Star Systems',
        'Dying Star Systems',
        'Generation Ships',
        'AI Hubs & Digital Realms',
        'Rogue Planets & Deep Space',
        'Trade Routes & Nomadic Bands'
    ],
    'rotation' => [
        'target' => 600,
        'weights' => [
            'heavy' => 0.20,
            'medium' => 0.50,
            'light' => 0.30
        ]
    ],
    'createdAt' => date('c')
];

// Write files
file_put_contents($bandsFile, json_encode($bands, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
file_put_contents($songsFile, json_encode($songs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
file_put_contents($stationFile, json_encode($station, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Count regions
$regionCounts = [];
foreach ($bands as $band) {
    $r = $band['region'];
    if (!isset($regionCounts[$r])) $regionCounts[$r] = 0;
    $regionCounts[$r]++;
}

echo json_encode([
    'success' => true,
    'bands' => count($bands),
    'songs' => count($songs),
    'regions' => $regionCounts,
    'files' => [
        'bands' => $bandsFile,
        'songs' => $songsFile,
        'station' => $stationFile
    ]
], JSON_PRETTY_PRINT);
