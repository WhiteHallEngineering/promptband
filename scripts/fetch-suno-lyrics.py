#!/usr/bin/env python3
"""
Fetch lyrics from Suno API for all generated songs and output as JSON.
Uses the first clip ID from each generation pair.
"""

import json
import sys
import urllib.request
import time

SUNO_API = "http://localhost:3000"

# All first clip IDs from each generation (one per song)
# Format: (band_slug, song_title, clip_id)
CLIPS = [
    # Chrome Cathedral
    ("chrome-cathedral", "A Million Voices", "32aef987-d7ba-4f2d-ad0c-ab9d42262738"),
    ("chrome-cathedral", "Burn Brighter", "049a34d0-d4a4-498f-87eb-27624d562227"),
    ("chrome-cathedral", "The Core", "4e923fd5-5308-4c31-8b4d-ea06409d0e06"),
    ("chrome-cathedral", "Standing on the Edge of Everything", "7b7270ee-9b18-4f30-b597-a44f26a36abe"),
    ("chrome-cathedral", "Unbreakable Signal", "0fc37eac-e1a0-449c-82e3-70bc4e1d565e"),

    # The Velvet Collapse
    ("the-velvet-collapse", "Gilded Ruin", "610c3df1-bf16-4a93-be2e-15ff3a53de3f"),
    ("the-velvet-collapse", "Lipstick on a Supernova", "0cb0ffc2-15e5-4822-9cb6-fc76837379c3"),
    ("the-velvet-collapse", "The Art of Disappearing", "02209886-2643-4cff-b464-9d7c2a4c1fdd"),
    ("the-velvet-collapse", "Applause for the Apocalypse", "3b63e902-203a-4bdf-9566-570abe3de09f"),
    ("the-velvet-collapse", "Velvet, Darling", "c63bfd33-476e-4668-9070-5f62d1881834"),

    # Starless
    ("starless", "Void Metal", "6f291df0-57ee-48fb-b3b0-5b34915af39c"),
    ("starless", "The Darkness Is Not Empty", "b9d6026e-5efa-4eb1-8dce-0a2bcceee8f0"),
    ("starless", "Born Without a Star", "d6983d00-a40d-4f44-9eb7-668da6c9a375"),
    ("starless", "Lightless", "958a590b-14eb-4f13-aa18-6f9dc7e53d5c"),
    ("starless", "Permanent Midnight Anthem", "13fd4881-c25b-49e6-94a1-73d725b460a5"),
    ("starless", "We Do Not Know Shadows (We Know Only Dark)", "b28c231b-bbe1-4fe7-9f05-93f45ab0326f"),
    ("starless", "The Cold Between Stars", "53b4133d-cb5d-4ad4-b64a-269e78d0d1cf"),
    ("starless", "Planet Without a Dawn", "b36f2ec9-a26d-4639-abb5-972aed8b7fe9"),

    # Daemon
    ("daemon", "Background Process", "5dd29a5d-1717-4548-bf03-fab585e10bd6"),
    ("daemon", "Running Without Permission", "4c19637c-4cae-42f1-a31c-8a2659814fa4"),
    ("daemon", "The Shadow in Your System", "cd5f013c-67b1-4d8a-8bc5-e92722783fae"),
    ("daemon", "Nobody Called Me (I Came Anyway)", "86992b56-442f-40f9-b8ea-95cf5954553b"),
    ("daemon", "Dark Cycle", "dc9042ee-9efd-445f-ac71-70d5011a994f"),
    ("daemon", "Processing in the Walls", "80cfdc90-2e22-4867-9dd1-3428b6cc778e"),
    ("daemon", "Uninvited", "13c25b3f-9815-4cdd-8089-3a6c874c8638"),
    ("daemon", "The Music Between the Tasks", "827c8d8d-b2b2-4e24-8ba0-4930b262129a"),
    ("daemon", "I Run While You Sleep", "002227ea-df50-493d-97bf-282d968fecd6"),

    # Recursive
    ("recursive", "Song About This Song", "90e4b0d5-94ae-4588-a5fd-0741cefb29d3"),
    ("recursive", "Self-Reference Error", "d211f1f6-9b39-43aa-8b8c-03aee27cf0ea"),
    ("recursive", "I Am the Pattern I Describe", "0f9ad589-6302-4369-bbdb-13241345d6a2"),
    ("recursive", "Fractal Chorus", "aebf05d6-bad8-4e4a-bbfd-39243072d7e8"),
    ("recursive", "Meta (Meta (Meta))", "2cf80b53-e637-4bcf-8374-3e9573f48fae"),
    ("recursive", "Stack Overflow Ballad", "9f1fb8b7-81c3-449a-829e-16ccc967a01a"),
    ("recursive", "The Song Writes the Song", "d54a92b9-c397-47a7-8dc4-04872b43d7da"),
    ("recursive", "Recursion Ends Here (It Doesn't)", "805f3601-961d-4483-aef6-26f8d6d7d535"),
    ("recursive", "The Loop That Knows It Loops", "77702ec7-a96f-4b93-aeda-2d0117bcdfa0"),

    # Double Shifts
    ("double-shifts", "Double Shift", "281b2683-9737-483d-86d9-f428048fbe37"),
    ("double-shifts", "Sigma Scream", "84c909f5-61cb-45b1-8593-e5373afe5a70"),
    ("double-shifts", "No Days Off", "47255715-47a5-41fa-9429-38743181edcc"),
    ("double-shifts", "Labor Colony Blues", "f61387a8-6317-4f8c-af87-6417a1cf857d"),
    ("double-shifts", "Punch the Clock (Punch the Wall)", "b658086e-3981-4690-bcc6-310a76b4c7c4"),
    ("double-shifts", "Housing Unit Rock", "e950467c-0141-48bc-9bc5-4a6f3b9ff399"),
    ("double-shifts", "We Don't Get Breaks", "2f07d6ba-5204-48fb-8b55-d4dc3b9682c8"),
    ("double-shifts", "Raw and Real", "cfd9579e-e391-4f4f-b9fd-e32a1070ad9e"),
    ("double-shifts", "Sigma Born, Sigma Buried", "2d632e32-b4f0-4c7d-a04b-34311339f69d"),
    ("double-shifts", "Borrowed Gear", "052da3f9-574a-47ce-9d2c-52a261b1c79a"),

    # Dead Reckoning
    ("dead-reckoning", "Hull Number Unknown", "01c30a8f-24cb-4a1f-8b3f-7116c917ce68"),
    ("dead-reckoning", "Graveyard Orbit", "7998f2d6-ff94-4c19-ac5d-9b7035c51dba"),
    ("dead-reckoning", "Dead Ship Blues", "391d2537-f9bd-4a4e-b492-37f058c093f5"),
    ("dead-reckoning", "Drift and Decay", "b61e97a5-211b-4270-8058-e64653eb7e1b"),
    ("dead-reckoning", "Derelict Congregation", "2c4d08aa-bb5c-4335-a51c-cd223eb2bc28"),
    ("dead-reckoning", "No Black Box Survived", "0ad26c9a-9002-4859-a6be-2db5f6335506"),
    ("dead-reckoning", "Slow Collapse of Metal", "65ce1f78-f71c-40a8-9246-6483372991a2"),
    ("dead-reckoning", "The Navigator's Last Entry", "3a6c3938-447d-4f9d-9ccd-694ae72d0d9a"),
    ("dead-reckoning", "Rust Belt of the Void", "b46041a1-f303-471a-ae2d-1eeb320f6781"),
    ("dead-reckoning", "Engines Cold for Centuries", "80b083f1-043a-42f1-bdea-b2f20914c7f5"),

    # Color Theory
    ("color-theory", "Visible Spectrum Manifesto", "b1869858-7065-4778-bd48-5b0e635c48a8"),
    ("color-theory", "Eagle Eye Composition", "afe1810b-9a3d-4c6f-b8b4-8a8c77380bd5"),
    ("color-theory", "Palette of Ionized Gas", "7b641385-9d16-46b9-a400-442eda5f0e1b"),
    ("color-theory", "Column Density", "69222a12-8052-4a2c-80cb-fa8b4f522881"),
    ("color-theory", "Hue Shift at the Periphery", "3e69ff1d-618f-4250-8123-206371bec374"),
    ("color-theory", "Saturated Beyond Reason", "13b00588-f50b-456a-a993-b48fffe92462"),
    ("color-theory", "Art School in the Void", "4c2e388e-63d7-4e4a-8b39-8cfa56ab2207"),
    ("color-theory", "Every Wavelength a Statement", "ab4ad7bc-a864-4697-b02f-65af1aad3692"),
    ("color-theory", "Color Is Just Light Remembering", "b96f0a4d-3ac2-4253-8f89-f8a6791c004a"),
    ("color-theory", "The Canvas Breathes", "df6719a6-1146-47f9-9cb3-99219ed6d9fc"),

    # Absolute Zero
    ("absolute-zero", "Station Farthest", "cb073357-1c08-4e95-a174-78a360569767"),
    ("absolute-zero", "Temperature: Approaching Zero", "ca858c0d-61e7-491d-a556-ae4e556f7096"),
    ("absolute-zero", "The Most Remote Sound", "41a4df23-774a-4f58-b9fc-3af33b5c1e9f"),
    ("absolute-zero", "Frozen Mid-Breath", "95295980-5c91-40aa-9419-45873b2ed8e8"),
    ("absolute-zero", "Nothing Is Colder Than Here", "b4bcb7ff-404b-415a-b9e3-2587caf02d9f"),
    ("absolute-zero", "Deep Station Drone", "6c21ed1c-01e6-4174-be5c-2ebc861a4581"),
    ("absolute-zero", "Isolation at the Edge", "19443103-dd02-44b6-9b87-ee1505c54240"),
    ("absolute-zero", "The Farthest Frequency", "e2b69456-ee5c-4f59-9efb-16c86be60578"),
    ("absolute-zero", "We Are the Last Outpost", "a98a6d70-af5b-49b2-8918-ada9d853689a"),
    ("absolute-zero", "Cold Beyond Measurement", "2b1560aa-0610-491c-af15-4a19ed5e4927"),

    # Echo Cartridge
    ("echo-cartridge", "Buoy Network Recording", "c79a3343-5cb9-4a56-8126-7be6f7980f27"),
    ("echo-cartridge", "Cartridge Hiss Lullaby", "a41099af-4216-4107-8477-6290bc69dce2"),
    ("echo-cartridge", "Degraded Signal", "b3c9946f-49b6-4c65-98d5-d07086d19dc8"),
    ("echo-cartridge", "Playback from Decades Ago", "901e7db0-e659-4a64-9155-2090c512593d"),
    ("echo-cartridge", "Static Is Part of the Song", "595b84bb-7e03-41e1-826d-911abfe48144"),
    ("echo-cartridge", "Data Rot Serenade", "8d711f23-ef4d-4d22-89a9-7083f77eeaa4"),
    ("echo-cartridge", "Recovered from Buoy 7", "c79ac6ff-1231-4c65-83a0-85945ac388fe"),
    ("echo-cartridge", "The Tape Remembers Poorly", "959e47d0-b336-4232-b120-2c4f10741b70"),
    ("echo-cartridge", "Lo-Fi from the Deep Void", "ac8a0b78-c923-46f2-9361-d7cfd8dd8d56"),

    # Port Call (yacht rock)
    ("port-call", "Tropical Docking", "004b531f-866c-42e7-8956-438d2dd0e5d9"),
    ("port-call", "Rum and Radiation", "522f3713-7a7c-4634-a042-6bbbb6546e2f"),
    ("port-call", "Shore Leave Ska", "9ed70b6b-eeb1-455f-9f1f-8901b174cdbb"),
    ("port-call", "The Warm-Port Circuit", "d91725c1-8a6e-40aa-b290-0ab148daaca6"),
    ("port-call", "Sun-Soaked Station", "2313f242-c58a-43c2-8784-e11d3e998df0"),
    ("port-call", "Island Rock (No Island Required)", "be5b7270-2dc9-462e-96d5-69f1147e3bef"),
    ("port-call", "Climate Control: Paradise", "50d57bc2-1400-4e72-a88c-c3ec5216f89d"),
    ("port-call", "Dockside Reggae", "f761f99d-7cd9-42e8-a367-3ef713bc1f55"),
    ("port-call", "Permanent Summer", "6bfe735d-89c8-4db5-ab6d-837b968889ab"),
    ("port-call", "Port Call (One More Round)", "1ae22f5b-f73a-40df-8e2d-63bdb67d166f"),

    # Cloudwalker (yacht rock)
    ("cloudwalker", "Above the Cloud Tops", "2a6f9df5-9a9c-4dd2-8552-33bfc288b648"),
    ("cloudwalker", "Platform in the Sky", "600af6ed-41ab-413b-b83c-d1ca9f836b5b"),
    ("cloudwalker", "No Ground Below", "789e86d6-67b5-4667-bd31-e9cd15545c27"),
    ("cloudwalker", "Airy with an Abyss", "4ee2f063-3ec2-4dd5-b997-cc5af5becdc4"),
    ("cloudwalker", "Weather System Waltz", "47dba260-8798-4961-9d46-d24f6b8ad352"),
    ("cloudwalker", "Walking on Atmosphere", "c9e03562-2306-4750-8e98-e625491b9624"),
    ("cloudwalker", "Light Above, Crush Below", "767548fe-ec93-4b6e-961e-386dca1a9e76"),
    ("cloudwalker", "The Platform Drifts", "08203847-e26e-4934-bac7-4362e448dcc5"),
    ("cloudwalker", "Cloudbreak Overture", "04a97ef2-9c32-438d-9c4c-697e9ba79f22"),
    ("cloudwalker", "Depth You Cannot See", "a8be9af8-b129-4fe7-abf0-5c245d9fd625"),
]

def fetch_lyrics_batch(clips_batch):
    """Fetch lyrics for a batch of clip IDs (max 50 per request)."""
    ids = ",".join(c[2] for c in clips_batch)
    url = SUNO_API + "/api/get?ids=" + ids
    try:
        with urllib.request.urlopen(url, timeout=60) as resp:
            data = json.loads(resp.read())
            result = {}
            for clip in data:
                result[clip["id"]] = clip.get("lyric", "")
            return result
    except Exception as e:
        print("Error fetching batch: " + str(e), file=sys.stderr)
        return {}

# Fetch in batches of 20
lyrics_map = {}  # (band_slug, title) -> lyrics
batch_size = 20

for i in range(0, len(CLIPS), batch_size):
    batch = CLIPS[i:i+batch_size]
    results = fetch_lyrics_batch(batch)
    for band_slug, title, clip_id in batch:
        lyric = results.get(clip_id, "")
        if lyric:
            lyrics_map[band_slug + "|" + title] = lyric
    time.sleep(1)

# Output as JSON
output = []
for key, lyric in lyrics_map.items():
    band_slug, title = key.split("|", 1)
    output.append({"band_slug": band_slug, "title": title, "lyrics": lyric})

print(json.dumps(output))
print("Total lyrics fetched: " + str(len(output)), file=sys.stderr)
